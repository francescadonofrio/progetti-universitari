<?php
session_start();
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/xml.php";
require_once __DIR__ . "/php/db.php";

if (is_file(__DIR__ . "/promozioni.php")) require_once __DIR__ . "/promozioni.php";

$role = current_role();
if ($role === "visitatore") { header("Location: login.php"); exit; }
if ($role === "amministratore") { header("Location: catalogo.php"); exit; }

$PROMO_XML = __DIR__ . "/xml/promozioni.xml";
$XML_A = __DIR__ . "/xml/acquisti.xml";
$today = date("Y-m-d");

$username = $_SESSION["username"] ?? "";
$carrello = $_SESSION["carrello"] ?? [];

$prodotti = load_catalogo(__DIR__ . "/xml/catalogo.xml");
$byId = array_column($prodotti, null, "id");

$righe = [];
$totale = 0.0;

$hasPromo = function_exists("promo_discount_percent_for") && function_exists("promo_apply_discount");

foreach ($carrello as $id => $qty) {
  $p = $byId[$id] ?? null;
  if (!$p) continue;

  $prezzo = (float)$p["prezzo"];
  $qty = (int)$qty;

  $perc = 0;
  $prezzo_s = $prezzo;

  if ($hasPromo) {
    $perc = promo_discount_percent_for($role, (string)$id, $today, $PROMO_XML);
    $prezzo_s = promo_apply_discount($prezzo, $perc);
  }

  $sub = $prezzo_s * $qty;
  $totale += $sub;

  $righe[] = ["id" => (string)$id, "qty" => $qty, "prezzo" => $prezzo_s];
}

function ensure_acquisti_xml(string $path): DOMDocument {
  $make = function () use ($path): void {
    $imp = new DOMImplementation();
    $dtd = $imp->createDocumentType("acquisti", "", "acquisti.dtd");
    $doc = $imp->createDocument("", "acquisti", $dtd);
    $doc->encoding = "UTF-8";
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->save($path);
  };

  if (!is_file($path)) $make();

  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $doc->load($path, LIBXML_NONET);

  if (!($doc->documentElement instanceof DOMElement) || $doc->documentElement->tagName !== "acquisti") {
    $make();
    $doc->load($path, LIBXML_NONET);
  }

  return $doc;
}

function next_acquisto_id(DOMDocument $doc): int {
  $max = 0;
  foreach ((new DOMXPath($doc))->query("//acquisto/@id") as $attr) {
    $max = max($max, (int)$attr->nodeValue);
  }
  return $max + 1;
}

function bonus_crediti_per_utente(string $username, string $todayYmd, string $promoXmlPath, string $acquistiXmlPath): float {
  if ($username === "" || !function_exists("promo_best_bonus_for_user_xml")) return 0.0;
  $bonusInfo = promo_best_bonus_for_user_xml($username, $todayYmd, $promoXmlPath, $acquistiXmlPath, "");
  if (empty($bonusInfo["active"]) || empty($bonusInfo["eligible"])) return 0.0;
  $crediti = (float)($bonusInfo["crediti"] ?? 0);
  return $crediti > 0 ? $crediti : 0.0;
}

$crediti_disponibili = 0.0;
if (isset($conn) && $username !== "") {
  $stmt = mysqli_prepare($conn, "SELECT crediti FROM utenti WHERE username=? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "s", $username);
  mysqli_stmt_execute($stmt);
  $row = ($res = mysqli_stmt_get_result($stmt)) ? mysqli_fetch_assoc($res) : null;
  if ($row) $crediti_disponibili = (float)$row["crediti"];
}

$pageMsg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!$righe) {
    $pageMsg = "Carrello vuoto.";
  } elseif (!isset($conn)) {
    $pageMsg = "Database non disponibile.";
  } elseif ($crediti_disponibili + 0.00001 < $totale) {
    $pageMsg = "Crediti insufficienti.";
  } else {
    mysqli_begin_transaction($conn);
    try {
      $stmt = mysqli_prepare($conn, "SELECT crediti FROM utenti WHERE username=? LIMIT 1 FOR UPDATE");
      mysqli_stmt_bind_param($stmt, "s", $username);
      mysqli_stmt_execute($stmt);
      $row = ($res = mysqli_stmt_get_result($stmt)) ? mysqli_fetch_assoc($res) : null;
      $crediti_attuali = $row ? (float)$row["crediti"] : 0.0;

      if ($crediti_attuali + 0.00001 < $totale) {
        throw new RuntimeException("Crediti insufficienti.");
      }

      $doc = ensure_acquisti_xml($XML_A);
      $root = $doc->documentElement;

      $idA = (string)next_acquisto_id($doc);
      $a = $doc->createElement("acquisto");
      $a->setAttribute("id", $idA);
      $a->setAttribute("utente", $username);
      $a->setAttribute("data", date("Y-m-d H:i:s"));
      $a->setAttribute("totale", number_format($totale, 2, ".", ""));

      foreach ($righe as $r) {
        $it = $doc->createElement("prodotto");
        $it->setAttribute("id_smalto", $r["id"]);
        $it->setAttribute("quantita", (string)$r["qty"]);
        $it->setAttribute("prezzo_unitario", number_format((float)$r["prezzo"], 2, ".", ""));
        $a->appendChild($it);
      }

      $root->appendChild($a);
      if (@$doc->save($XML_A) === false) {
        throw new RuntimeException("Errore salvataggio acquisto.");
      }

      $bonus_crediti = bonus_crediti_per_utente($username, $today, $PROMO_XML, $XML_A);
      $nuovo_saldo = ($crediti_attuali - $totale) + $bonus_crediti;

      $stmt = mysqli_prepare($conn, "UPDATE utenti SET crediti=? WHERE username=?");
      mysqli_stmt_bind_param($stmt, "ds", $nuovo_saldo, $username);
      mysqli_stmt_execute($stmt);

      mysqli_commit($conn);
      unset($_SESSION["carrello"]);
      header("Location: acquisti.php");
      exit;
    } catch (Throwable $e) {
      mysqli_rollback($conn);
      $pageMsg = $e->getMessage() !== "" ? $e->getMessage() : "Errore durante il checkout.";
    }
  }
}

page_header("FD NAILS - Checkout", "carrello");
?>

<div class="page-title"><h1>Conferma acquisto</h1></div>

<div style="padding:20px; max-width:520px;">
  <?php if ($pageMsg !== ""): ?>
    <p style="color:red; font-weight:bold;"><?= htmlspecialchars($pageMsg) ?></p>
  <?php endif; ?>

  <p>Crediti disponibili: <b><?= number_format((float)$crediti_disponibili, 2, ",", ".") ?></b></p>
  <p>Totale: <b><?= number_format((float)$totale, 2, ",", ".") ?> crediti</b></p>

  <form method="post">
    <button class="btn btn-primary" type="submit">Conferma</button>
    <a style="margin-left:10px;" href="carrello.php">Torna al carrello</a>
  </form>
</div>

<?php page_footer(); ?>