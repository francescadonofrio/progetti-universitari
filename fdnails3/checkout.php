<?php
session_start();
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/xml.php";

if (is_file(__DIR__ . "/promozioni.php")) require_once __DIR__ . "/promozioni.php";

$role = current_role();
if ($role === "visitatore") { header("Location: login.php"); exit; }
if ($role === "amministratore") { header("Location: catalogo.php"); exit; }

$PROMO_XML = __DIR__ . "/xml/promozioni.xml";
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

$XML_A = __DIR__ . "/xml/acquisti.xml";
$XML_B = __DIR__ . "/xml/bonus.xml";

function ensure_acquisti_xml(string $path): DOMDocument {
  $make = function () use ($path): void {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->appendChild($doc->createElement("acquisti"));
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

function ensure_bonus_xml(string $path): DOMDocument {
  $dir = dirname($path);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  $make = function () use ($path): void {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->appendChild($doc->createElement("bonus"));
    @$doc->save($path);
    @chmod($path, 0664);
  };

  if (!is_file($path)) $make();

  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;

  if (!@$doc->load($path, LIBXML_NONET) || !($doc->documentElement instanceof DOMElement) || $doc->documentElement->tagName !== "bonus") {
    $make();
    @$doc->load($path, LIBXML_NONET);
  }

  return $doc;
}

function xpath_literal(string $s): string {
  if (strpos($s, "'") === false) return "'" . $s . "'";
  if (strpos($s, '"') === false) return '"' . $s . '"';
  $parts = explode("'", $s);
  $out = "concat(";
  $n = count($parts);
  for ($i = 0; $i < $n; $i++) {
    if ($i > 0) $out .= ", \"'\", ";
    $out .= "'" . $parts[$i] . "'";
  }
  return $out . ")";
}

function assegna_bonus(string $path, string $username, string $today, string $promoXml, string $acquistiXml): void {
  if ($username === "") return;
  if (!function_exists("promo_best_bonus_for_user_xml")) return;

  $bonusInfo = promo_best_bonus_for_user_xml($username, $today, $promoXml, $acquistiXml, $path);

  if (empty($bonusInfo["active"]) || empty($bonusInfo["eligible"])) return;

  $crediti_nuovi = (int)($bonusInfo["crediti"] ?? 0);
  if ($crediti_nuovi <= 0) return;

  $doc = ensure_bonus_xml($path);
  $xp = new DOMXPath($doc);

  $u = $xp->query("//utente[@username=" . xpath_literal($username) . "]")->item(0);

  if (!($u instanceof DOMElement)) {
    $u = $doc->createElement("utente");
    $u->setAttribute("username", $username);
    $u->appendChild($doc->createElement("crediti", (string)$crediti_nuovi));
    $doc->documentElement->appendChild($u);
  } else {
    $c = $u->getElementsByTagName("crediti")->item(0);
    if (!($c instanceof DOMElement)) {
      $c = $doc->createElement("crediti", "0");
      $u->appendChild($c);
    }
    $att = (int)$c->textContent;
    $c->textContent = "";
    $c->appendChild($doc->createTextNode((string)($att + $crediti_nuovi)));
  }

  @$doc->save($path);
  @chmod($path, 0664);
}

$pageMsg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!$righe) {
    $pageMsg = "Carrello vuoto.";
  } else {
    try {
      $doc = ensure_acquisti_xml($XML_A);
      $root = $doc->documentElement;

      $idA = (string)next_acquisto_id($doc);
      $a = $doc->createElement("acquisto");
      $a->setAttribute("id", $idA);
      $a->setAttribute("username", $username);
      $a->setAttribute("data", date("Y-m-d H:i:s"));
      $a->setAttribute("totale", number_format($totale, 2, ".", ""));

      foreach ($righe as $r) {
        $it = $doc->createElement("item");
        $it->setAttribute("smalto_id", $r["id"]);
        $it->setAttribute("qty", (string)$r["qty"]);
        $it->setAttribute("prezzo", number_format((float)$r["prezzo"], 2, ".", ""));
        $a->appendChild($it);
      }

      $root->appendChild($a);
      @$doc->save($XML_A);

      assegna_bonus($XML_B, $username, $today, $PROMO_XML, $XML_A);
    } catch (Throwable $e) {
    }

    unset($_SESSION["carrello"]);
    header("Location: acquisti.php");
    exit;
  }
}

page_header("FD NAILS - Checkout", "carrello");
?>

<div class="page-title"><h1>Conferma acquisto</h1></div>

<div style="padding:20px; max-width:520px;">
  <?php if ($pageMsg !== ""): ?>
    <p style="color:red; font-weight:bold;"><?= htmlspecialchars($pageMsg) ?></p>
  <?php endif; ?>

  <p>Totale: <b><?= number_format((float)$totale, 2, ",", ".") ?> €</b></p>

  <form method="post">
    <button class="btn btn-primary" type="submit">Conferma</button>
    <a style="margin-left:10px;" href="carrello.php">Torna al carrello</a>
  </form>
</div>

<?php page_footer(); ?>