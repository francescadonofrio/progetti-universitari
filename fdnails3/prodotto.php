<?php
session_start();

require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/xml.php";
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/db.php";

$id = (string)($_GET["id"] ?? "");
if ($id === "") { header("Location: catalogo.php"); exit; }

$prodotti = load_catalogo(__DIR__ . "/xml/catalogo.xml");
$prodotto = null;
foreach ($prodotti as $p) {
  if ((string)$p["id"] === $id) { $prodotto = $p; break; }
}
if (!$prodotto) { header("Location: catalogo.php"); exit; }

$role = current_role();
$username = (string)($_SESSION["username"] ?? "");

$voteAlready = (string)($_GET["vote_already"] ?? "");
$voteScope   = (string)($_GET["vote_scope"] ?? "");
$voteTid     = (string)($_GET["vote_tid"] ?? "");
$voteKind    = (string)($_GET["vote_kind"] ?? "");

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

function first_child_el(DOMElement $parent, string $name): ?DOMElement {
  foreach ($parent->childNodes as $n) {
    if ($n instanceof DOMElement && $n->tagName === $name) return $n;
  }
  return null;
}

function get_child_text(DOMElement $parent, string $name, string $default = ""): string {
  $el = first_child_el($parent, $name);
  return $el ? trim($el->textContent) : $default;
}

function set_child_text(DOMDocument $doc, DOMElement $parent, string $name, string $value): void {
  $el = first_child_el($parent, $name) ?? $doc->createElement($name);
  if (!$el->parentNode) $parent->appendChild($el);
  $el->textContent = "";
  $el->appendChild($doc->createTextNode($value));
}

function ensure_xml(string $path, string $rootTag): DOMDocument {
  $reset = true;

  if (is_file($path)) {
    $tmp = new DOMDocument();
    $tmp->preserveWhiteSpace = false;
    $tmp->formatOutput = true;
    if (@$tmp->load($path, LIBXML_NONET) && ($tmp->documentElement instanceof DOMElement) && $tmp->documentElement->tagName === $rootTag) {
      $reset = false;
    }
  }

  if ($reset) {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->appendChild($doc->createElement($rootTag));
    $doc->save($path);
  }

  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $doc->load($path, LIBXML_NONET);
  return $doc;
}

function next_prefixed_id(DOMDocument $doc, string $tag, string $prefix): string {
  $max = 0;
  foreach ((new DOMXPath($doc))->query("//{$tag}/@id") as $attr) {
    $id = (string)$attr->nodeValue;
    if (preg_match("/^" . preg_quote($prefix, "/") . "(\d+)$/", $id, $m)) $max = max($max, (int)$m[1]);
  }
  return $prefix . str_pad((string)($max + 1), 2, "0", STR_PAD_LEFT);
}

function find_by_id(DOMDocument $doc, string $tag, string $id): ?DOMElement {
  $n = (new DOMXPath($doc))->query("//{$tag}[@id=" . xpath_literal($id) . "]")->item(0);
  return $n instanceof DOMElement ? $n : null;
}

function r_get_int(DOMElement $el, string $name): int {
  $n = first_child_el($el, $name);
  return $n ? (int)trim($n->textContent) : 0;
}

function r_sum_votes_for_user(string $user, string $xmlPath, string $xpath): int {
  if ($user === "" || !is_file($xmlPath)) return 0;

  $d = new DOMDocument();
  $d->preserveWhiteSpace = false;
  $d->formatOutput = true;
  if (!@$d->load($xmlPath, LIBXML_NONET)) return 0;

  $sum = 0;
  $xp = new DOMXPath($d);
  $q = str_replace("{USER}", xpath_literal($user), $xpath);

  foreach ($xp->query($q) as $n) {
    if ($n instanceof DOMElement) $sum += r_get_int($n, "supporto") + r_get_int($n, "utilita");
  }

  return $sum;
}

function r_recompute_reputazione(string $user, string $recXml, string $qaXml): int {
  $sum = 0;
  $sum += r_sum_votes_for_user($user, $recXml, "//recensione[@username={USER}]");
  $sum += r_sum_votes_for_user($user, $qaXml, "//domanda[@username={USER}]");
  $sum += r_sum_votes_for_user($user, $qaXml, "//risposta[@username={USER}]");
  return max(0, $sum);
}

function r_update_reputazione_db(mysqli $conn, string $user, int $value): void {
  $st = mysqli_prepare($conn, "UPDATE utenti SET reputazione=? WHERE username=?");
  if ($st) {
    mysqli_stmt_bind_param($st, "is", $value, $user);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
  }
}

function promo_first_child(DOMElement $parent, string $name): ?DOMElement {
  foreach ($parent->childNodes as $n) {
    if ($n instanceof DOMElement && $n->tagName === $name) return $n;
  }
  return null;
}

function promo_child_text(DOMElement $parent, string $name, string $default = ""): string {
  $el = promo_first_child($parent, $name);
  return $el ? trim($el->textContent) : $default;
}

function load_product_promos(string $path, string $productId, string $today): array {
  $out = ["sconti" => [], "bonus" => []];

  if (!is_file($path)) return $out;

  $doc = new DOMDocument();
  if (!@$doc->load($path, LIBXML_NONET)) return $out;

  $root = $doc->documentElement;
  if (!$root instanceof DOMElement) return $out;

  $scontiNode = promo_first_child($root, "sconti");
  if ($scontiNode) {
    foreach ($scontiNode->getElementsByTagName("sconto") as $s) {
      if (!($s instanceof DOMElement)) continue;

      $dal = $s->getAttribute("dal");
      $al = $s->getAttribute("al");
      if ($dal === "" || $al === "" || $today < $dal || $today > $al) continue;

      $tipo = $s->getAttribute("tipo");
      $app = promo_first_child($s, "applicazione");

      if ($tipo === "prodotto") {
        $idProdotto = $app ? promo_child_text($app, "idProdotto") : "";
        if ($idProdotto !== $productId) continue;
      }

      $criterio = $app ? promo_first_child($app, "criterio") : null;

      $out["sconti"][] = [
        "id" => $s->getAttribute("id"),
        "tipo" => $tipo,
        "valore" => $s->getAttribute("valorePercento"),
        "dal" => $dal,
        "al" => $al,
        "descrizione" => promo_child_text($s, "descrizione"),
        "idProdotto" => $app ? promo_child_text($app, "idProdotto") : "",
        "criterio_tipo" => $criterio ? $criterio->getAttribute("tipo") : "",
        "criterio_valore" => $criterio ? $criterio->getAttribute("valore") : "",
      ];
    }
  }

  $bonusNode = promo_first_child($root, "bonus");
  if ($bonusNode) {
    foreach ($bonusNode->getElementsByTagName("bonusterm") as $b) {
      if (!($b instanceof DOMElement)) continue;

      $dal = $b->getAttribute("dal");
      $al = $b->getAttribute("al");
      if ($dal === "" || $al === "" || $today < $dal || $today > $al) continue;

      $app = promo_first_child($b, "applicazione");
      $criterio = $app ? promo_first_child($app, "criterio") : null;

      $out["bonus"][] = [
        "id" => $b->getAttribute("id"),
        "tipo" => $b->getAttribute("tipo"),
        "crediti" => $b->getAttribute("crediti"),
        "dal" => $dal,
        "al" => $al,
        "descrizione" => promo_child_text($b, "descrizione"),
        "criterio_tipo" => $criterio ? $criterio->getAttribute("tipo") : "",
        "criterio_valore" => $criterio ? $criterio->getAttribute("valore") : "",
      ];
    }
  }

  return $out;
}

$XML_REC = __DIR__ . "/xml/recensioni.xml";
$XML_QA  = __DIR__ . "/xml/domande.xml";

$pageMsg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = (string)($_POST["action"] ?? "");

  if ($role === "visitatore") {
    if (in_array($action, ["add_rec", "vote_rec", "add_domanda", "add_risposta", "vote_domanda", "vote_risposta"], true)) {
      header("Location: login.php");
      exit;
    }
  }

  if ($action === "add_rec") {
    $titolo = trim((string)($_POST["titolo"] ?? ""));
    $testo  = trim((string)($_POST["testo"] ?? ""));
    $voto   = (int)($_POST["voto"] ?? 0);

    if ($titolo === "" || $testo === "" || $voto < 1 || $voto > 5) {
      $pageMsg = "Compila tutti i campi (voto 1..5).";
    } else {
      $doc = ensure_xml($XML_REC, "recensioni");
      $root = $doc->documentElement;

      $rid = next_prefixed_id($doc, "recensione", "R");
      $r = $doc->createElement("recensione");
      $r->setAttribute("id", $rid);
      $r->setAttribute("id_smalto", (string)$prodotto["id"]);
      $r->setAttribute("username", $username);

      set_child_text($doc, $r, "titolo", $titolo);
      set_child_text($doc, $r, "testo", $testo);
      set_child_text($doc, $r, "voto", (string)$voto);
      set_child_text($doc, $r, "utilita", "0");
      set_child_text($doc, $r, "supporto", "0");
      set_child_text($doc, $r, "data", date("Y-m-d"));

      $root->appendChild($r);
      $doc->save($XML_REC);

      header("Location: prodotto.php?id=" . urlencode((string)$prodotto["id"]) . "#recensioni");
      exit;
    }
  } elseif ($action === "vote_rec") {
    $rid  = (string)($_POST["rid"] ?? "");
    $kind = (string)($_POST["kind"] ?? "");

    if ($rid !== "" && ($kind === "utilita" || $kind === "supporto")) {
      $_SESSION["rec_vote"] ??= [];
      $key = $rid . ":" . $kind;

      if (isset($_SESSION["rec_vote"][$key])) {
        header(
          "Location: prodotto.php?id=" . urlencode((string)$prodotto["id"]) .
          "&vote_already=1&vote_scope=rec&vote_tid=" . urlencode($rid) .
          "&vote_kind=" . urlencode($kind) . "#recensioni"
        );
        exit;
      }

      $doc = ensure_xml($XML_REC, "recensioni");
      $r = find_by_id($doc, "recensione", $rid);

      if ($r) {
        $author = (string)$r->getAttribute("username");
        if ($author !== "" && $author !== $username) {
          $curr = (int)get_child_text($r, $kind, "0");
          set_child_text($doc, $r, $kind, (string)($curr + 1));

          if ($kind === "utilita" && first_child_el($r, "supporto") === null) set_child_text($doc, $r, "supporto", "0");
          if ($kind === "supporto" && first_child_el($r, "utilita") === null) set_child_text($doc, $r, "utilita", "0");

          $doc->save($XML_REC);
          $_SESSION["rec_vote"][$key] = true;

          if (isset($conn) && $conn instanceof mysqli) {
            $rep = r_recompute_reputazione($author, $XML_REC, $XML_QA);
            r_update_reputazione_db($conn, $author, $rep);
          }
        }
      }
    }

    header("Location: prodotto.php?id=" . urlencode((string)$prodotto["id"]) . "#recensioni");
    exit;
  } elseif ($action === "add_domanda") {
    $testo = trim((string)($_POST["testo_domanda"] ?? ""));
    if ($testo === "") {
      $pageMsg = "Scrivi una domanda.";
    } else {
      $doc = ensure_xml($XML_QA, "domande");
      $root = $doc->documentElement;

      $did = next_prefixed_id($doc, "domanda", "D");
      $d = $doc->createElement("domanda");
      $d->setAttribute("id", $did);
      $d->setAttribute("id_smalto", (string)$prodotto["id"]);
      $d->setAttribute("username", $username);
      $d->setAttribute("data", date("Y-m-d"));

      set_child_text($doc, $d, "testo", $testo);
      set_child_text($doc, $d, "utilita", "0");
      set_child_text($doc, $d, "supporto", "0");
      $d->appendChild($doc->createElement("risposte"));

      $root->appendChild($d);
      $doc->save($XML_QA);

      header("Location: prodotto.php?id=" . urlencode((string)$prodotto["id"]) . "#domande");
      exit;
    }
  } elseif ($action === "add_risposta") {
    $did = (string)($_POST["did"] ?? "");
    $testo = trim((string)($_POST["testo_risposta"] ?? ""));

    if ($did === "" || $testo === "") {
      $pageMsg = "Scrivi una risposta.";
    } else {
      $doc = ensure_xml($XML_QA, "domande");
      $d = find_by_id($doc, "domanda", $did);

      if ($d) {
        $risposte = first_child_el($d, "risposte") ?? $d->appendChild($doc->createElement("risposte"));

        $aid = next_prefixed_id($doc, "risposta", "A");
        $a = $doc->createElement("risposta");
        $a->setAttribute("id", $aid);
        $a->setAttribute("username", $username);
        $a->setAttribute("data", date("Y-m-d"));

        set_child_text($doc, $a, "testo", $testo);
        set_child_text($doc, $a, "utilita", "0");
        set_child_text($doc, $a, "supporto", "0");

        $risposte->appendChild($a);
        $doc->save($XML_QA);
      }

      header("Location: prodotto.php?id=" . urlencode((string)$prodotto["id"]) . "#domande");
      exit;
    }
  } elseif ($action === "vote_domanda" || $action === "vote_risposta") {
    $kind = (string)($_POST["kind"] ?? "");
    $tid  = (string)($_POST["tid"] ?? "");

    if ($tid !== "" && ($kind === "utilita" || $kind === "supporto")) {
      $_SESSION["qa_vote"] ??= [];
      $key = $action . ":" . $tid . ":" . $kind;

      if (isset($_SESSION["qa_vote"][$key])) {
        $scope = $action === "vote_domanda" ? "domanda" : "risposta";
        header(
          "Location: prodotto.php?id=" . urlencode((string)$prodotto["id"]) .
          "&vote_already=1&vote_scope=" . urlencode($scope) .
          "&vote_tid=" . urlencode($tid) .
          "&vote_kind=" . urlencode($kind) . "#domande"
        );
        exit;
      }

      $doc = ensure_xml($XML_QA, "domande");
      $node = $action === "vote_domanda" ? find_by_id($doc, "domanda", $tid) : find_by_id($doc, "risposta", $tid);

      if ($node) {
        $author = (string)$node->getAttribute("username");
        if ($author !== "" && $author !== $username) {
          $curr = (int)get_child_text($node, $kind, "0");
          set_child_text($doc, $node, $kind, (string)($curr + 1));

          if ($kind === "utilita" && first_child_el($node, "supporto") === null) set_child_text($doc, $node, "supporto", "0");
          if ($kind === "supporto" && first_child_el($node, "utilita") === null) set_child_text($doc, $node, "utilita", "0");

          $doc->save($XML_QA);
          $_SESSION["qa_vote"][$key] = true;

          if (isset($conn) && $conn instanceof mysqli) {
            $rep = r_recompute_reputazione($author, $XML_REC, $XML_QA);
            r_update_reputazione_db($conn, $author, $rep);
          }
        }
      }
    }

    header("Location: prodotto.php?id=" . urlencode((string)$prodotto["id"]) . "#domande");
    exit;
  }
}

$recDoc = ensure_xml($XML_REC, "recensioni");
$recRoot = $recDoc->documentElement;

$recList = [];
foreach ($recRoot->getElementsByTagName("recensione") as $r) {
  if (!($r instanceof DOMElement)) continue;
  if ($r->getAttribute("id_smalto") !== (string)$prodotto["id"]) continue;
  $recList[] = [
    "id" => $r->getAttribute("id"),
    "username" => $r->getAttribute("username"),
    "titolo" => get_child_text($r, "titolo"),
    "testo" => get_child_text($r, "testo"),
    "voto" => get_child_text($r, "voto"),
    "utilita" => get_child_text($r, "utilita", "0"),
    "supporto" => get_child_text($r, "supporto", "0"),
    "data" => get_child_text($r, "data"),
  ];
}

$qaDoc = ensure_xml($XML_QA, "domande");
$qaRoot = $qaDoc->documentElement;

$domande = [];
foreach ($qaRoot->getElementsByTagName("domanda") as $d) {
  if (!($d instanceof DOMElement)) continue;
  if ($d->getAttribute("id_smalto") !== (string)$prodotto["id"]) continue;

  $risposte = [];
  $rispEl = first_child_el($d, "risposte");
  if ($rispEl) {
    foreach ($rispEl->getElementsByTagName("risposta") as $a) {
      if (!($a instanceof DOMElement)) continue;
      $risposte[] = [
        "id" => $a->getAttribute("id"),
        "username" => $a->getAttribute("username"),
        "data" => $a->getAttribute("data"),
        "testo" => get_child_text($a, "testo"),
        "utilita" => get_child_text($a, "utilita", "0"),
        "supporto" => get_child_text($a, "supporto", "0"),
      ];
    }
  }

  $domande[] = [
    "id" => $d->getAttribute("id"),
    "username" => $d->getAttribute("username"),
    "data" => $d->getAttribute("data"),
    "testo" => get_child_text($d, "testo"),
    "utilita" => get_child_text($d, "utilita", "0"),
    "supporto" => get_child_text($d, "supporto", "0"),
    "risposte" => $risposte
  ];
}

$promoData = load_product_promos(__DIR__ . "/xml/promozioni.xml", (string)$prodotto["id"], date("Y-m-d"));

page_header("FD NAILS - Prodotto", "catalogo");
?>

<div class="page-title">
  <h1><?= htmlspecialchars($prodotto["nome"]) ?></h1>
</div>

<?php if ($pageMsg !== ""): ?>
  <div style="padding:10px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
    <?= htmlspecialchars($pageMsg) ?>
  </div>
<?php endif; ?>

<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <img
    src="media/<?= htmlspecialchars($prodotto["immagine"]) ?>"
    alt="<?= htmlspecialchars($prodotto["nome"]) ?>"
    style="max-width:320px; width:100%; height:auto; display:block; margin-bottom:12px;"
  />

  <p class="product-desc" style="margin:0 0 8px 0;">
    <?= nl2br(htmlspecialchars($prodotto["descrizione"], ENT_QUOTES, "UTF-8"), false) ?>
  </p>

  <p style="margin:0 0 12px 0; font-weight:bold; color:#c4161c;">
    <?= number_format((float)$prodotto["prezzo"], 2, ",", ".") ?> crediti
  </p>

  <?php if ($role === "visitatore"): ?>
    <a class="btn btn-primary" href="login.php">Aggiungi al carrello</a>
  <?php elseif ($role === "amministratore"): ?>
    <span style="color:#777;">Acquisto non disponibile per amministratore.</span>
  <?php else: ?>
    <a class="btn btn-primary" href="aggiungi_carrello.php?id=<?= urlencode($prodotto["id"]) ?>">Aggiungi al carrello</a>
  <?php endif; ?>
</div>

<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h2 style="margin-top:0;">Promozioni attive</h2>

  <?php if (!$promoData["sconti"] && !$promoData["bonus"]): ?>
    <p style="margin:0;">Nessuna promozione attiva per questo prodotto.</p>
  <?php else: ?>

    <?php if ($promoData["sconti"]): ?>
      <h3 style="margin:0 0 12px 0;">Sconti</h3>
      <?php foreach ($promoData["sconti"] as $s): ?>
        <div style="padding:12px 0; border-top:1px solid #eee;">
          <p style="margin:0 0 6px 0;"><b><?= htmlspecialchars($s["id"]) ?></b> — <?= htmlspecialchars($s["valore"]) ?>%</p>
          <p style="margin:0 0 6px 0;"><b>Tipo:</b> <?= htmlspecialchars($s["tipo"]) ?></p>
          <p style="margin:0 0 6px 0;"><b>Descrizione:</b> <?= htmlspecialchars($s["descrizione"]) ?></p>
          <p style="margin:0 0 6px 0;"><b>Dal:</b> <?= htmlspecialchars($s["dal"]) ?></p>
          <p style="margin:0;"><b>Al:</b> <?= htmlspecialchars($s["al"]) ?></p>

          <?php if ($s["idProdotto"] !== ""): ?>
            <p style="margin:6px 0 0 0;"><b>ID prodotto:</b> <?= htmlspecialchars($s["idProdotto"]) ?></p>
          <?php endif; ?>

          <?php if ($s["criterio_tipo"] !== ""): ?>
            <p style="margin:6px 0 0 0;"><b>Criterio:</b> <?= htmlspecialchars($s["criterio_tipo"]) ?> = <?= htmlspecialchars($s["criterio_valore"]) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($promoData["bonus"]): ?>
      <h3 style="margin:18px 0 12px 0;">Bonus</h3>
      <?php foreach ($promoData["bonus"] as $b): ?>
        <div style="padding:12px 0; border-top:1px solid #eee;">
          <p style="margin:0 0 6px 0;"><b><?= htmlspecialchars($b["id"]) ?></b> — <?= htmlspecialchars($b["crediti"]) ?> crediti</p>
          <p style="margin:0 0 6px 0;"><b>Tipo:</b> <?= htmlspecialchars($b["tipo"]) ?></p>
          <p style="margin:0 0 6px 0;"><b>Descrizione:</b> <?= htmlspecialchars($b["descrizione"]) ?></p>
          <p style="margin:0 0 6px 0;"><b>Dal:</b> <?= htmlspecialchars($b["dal"]) ?></p>
          <p style="margin:0;"><b>Al:</b> <?= htmlspecialchars($b["al"]) ?></p>

          <?php if ($b["criterio_tipo"] !== ""): ?>
            <p style="margin:6px 0 0 0;"><b>Criterio:</b> <?= htmlspecialchars($b["criterio_tipo"]) ?> = <?= htmlspecialchars($b["criterio_valore"]) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php endif; ?>
</div>

<a id="recensioni"></a>
<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h2 style="margin-top:0;">Recensioni</h2>

  <?php if ($role !== "visitatore"): ?>
    <div style="padding:14px; border:1px solid #eee; border-radius:14px; margin-bottom:16px;">
      <h3 style="margin:0 0 10px 0;">Scrivi una recensione</h3>
      <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#recensioni">
        <input type="hidden" name="action" value="add_rec">

        <div class="form-row">
          <label>Titolo</label>
          <input type="text" name="titolo">
        </div>

        <div class="form-row">
          <label>Testo</label>
          <input type="text" name="testo">
        </div>

        <div class="filter-row">
          <label>Voto</label>
          <select name="voto">
            <option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option>
          </select>
        </div>

        <div class="filter-row filter-actions">
          <input class="btn btn-primary" type="submit" value="Pubblica">
        </div>
      </form>
    </div>
  <?php else: ?>
    <p style="margin:0 0 12px 0;"><a href="login.php">Accedi</a> per scrivere una recensione.</p>
  <?php endif; ?>

  <?php if (!$recList): ?>
    <p style="margin:0;">Nessuna recensione.</p>
  <?php else: ?>
    <?php foreach ($recList as $r): ?>
      <?php
        $_SESSION["rec_vote"] ??= [];
        $keyUtil = (string)$r["id"] . ":utilita";
        $keySupp = (string)$r["id"] . ":supporto";
        $alreadyUtil = isset($_SESSION["rec_vote"][$keyUtil]);
        $alreadySupp = isset($_SESSION["rec_vote"][$keySupp]);

        $recSecondMsg = ($voteAlready === "1" && $voteScope === "rec" && $voteTid === (string)$r["id"] && ($voteKind === "utilita" || $voteKind === "supporto"));
        $btnGrayStyle = "background:#ddd; border-color:#ddd; color:#666; cursor:not-allowed;";
      ?>

      <div style="padding:12px 0; border-top:1px solid #eee;">
        <p style="margin:0 0 6px 0;">
          voto <b><?= htmlspecialchars($r["voto"]) ?>/5</b>
          — <b><?= htmlspecialchars($r["titolo"]) ?></b>
        </p>
        <p style="margin:0 0 8px 0;"><?= htmlspecialchars($r["testo"]) ?></p>
        <p style="margin:0; color:#777;">
          di <?= htmlspecialchars($r["username"]) ?> — <?= htmlspecialchars($r["data"]) ?>
        </p>
        <p style="margin:6px 0 0 0; color:#777;">
          utilità: <b><?= htmlspecialchars($r["utilita"]) ?></b> — supporto: <b><?= htmlspecialchars($r["supporto"]) ?></b>
        </p>

        <?php if ($recSecondMsg): ?>
          <p style="margin:6px 0 0 0; color:#9a9a9a; font-style:italic;">voto già espresso</p>
        <?php endif; ?>

        <?php if ($role !== "visitatore" && $r["username"] !== $username): ?>
          <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#recensioni" style="margin-top:8px; display:inline;">
            <input type="hidden" name="action" value="vote_rec">
            <input type="hidden" name="rid" value="<?= htmlspecialchars($r["id"]) ?>">
            <input type="hidden" name="kind" value="utilita">
            <input class="btn btn-secondary" type="submit" value="Utilità +1" <?= $alreadyUtil ? "disabled" : "" ?> style="<?= $alreadyUtil ? $btnGrayStyle : "" ?>">
          </form>

          <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#recensioni" style="margin-top:8px; display:inline;">
            <input type="hidden" name="action" value="vote_rec">
            <input type="hidden" name="rid" value="<?= htmlspecialchars($r["id"]) ?>">
            <input type="hidden" name="kind" value="supporto">
            <input class="btn btn-secondary" type="submit" value="Supporto +1" <?= $alreadySupp ? "disabled" : "" ?> style="<?= $alreadySupp ? $btnGrayStyle : "" ?>">
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<a id="domande"></a>
<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h2 style="margin-top:0;">Domande e risposte</h2>

  <?php if ($role !== "visitatore"): ?>
    <div style="padding:14px; border:1px solid #eee; border-radius:14px; margin-bottom:16px;">
      <h3 style="margin:0 0 10px 0;">Fai una domanda</h3>
      <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#domande">
        <input type="hidden" name="action" value="add_domanda">
        <div class="form-row">
          <label>Domanda</label>
          <input type="text" name="testo_domanda">
        </div>
        <div class="filter-row filter-actions">
          <input class="btn btn-primary" type="submit" value="Pubblica">
        </div>
      </form>
    </div>
  <?php else: ?>
    <p style="margin:0 0 12px 0;"><a href="login.php">Accedi</a> per fare una domanda o rispondere.</p>
  <?php endif; ?>

  <?php if (!$domande): ?>
    <p style="margin:0;">Nessuna domanda.</p>
  <?php else: ?>
    <?php foreach ($domande as $d): ?>
      <?php
        $_SESSION["qa_vote"] ??= [];
        $keyUtil = "vote_domanda:" . (string)$d["id"] . ":utilita";
        $keySupp = "vote_domanda:" . (string)$d["id"] . ":supporto";
        $alreadyUtil = isset($_SESSION["qa_vote"][$keyUtil]);
        $alreadySupp = isset($_SESSION["qa_vote"][$keySupp]);

        $domSecondMsg = ($voteAlready === "1" && $voteScope === "domanda" && $voteTid === (string)$d["id"] && ($voteKind === "utilita" || $voteKind === "supporto"));
        $btnGrayStyle = "background:#ddd; border-color:#ddd; color:#666; cursor:not-allowed;";
      ?>

      <div style="padding:12px 0; border-top:1px solid #eee;">
        <p style="margin:0 0 8px 0;"><b>Q:</b> <?= htmlspecialchars($d["testo"]) ?></p>
        <p style="margin:0; color:#777;">
          di <?= htmlspecialchars($d["username"]) ?> — <?= htmlspecialchars($d["data"]) ?>
        </p>
        <p style="margin:6px 0 0 0; color:#777;">
          utilità: <b><?= htmlspecialchars($d["utilita"]) ?></b> — supporto: <b><?= htmlspecialchars($d["supporto"]) ?></b>
        </p>

        <?php if ($domSecondMsg): ?>
          <p style="margin:6px 0 0 0; color:#9a9a9a; font-style:italic;">voto già espresso</p>
        <?php endif; ?>

        <?php if ($role !== "visitatore" && $d["username"] !== $username): ?>
          <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#domande" style="margin-top:8px; display:inline;">
            <input type="hidden" name="action" value="vote_domanda">
            <input type="hidden" name="tid" value="<?= htmlspecialchars($d["id"]) ?>">
            <input type="hidden" name="kind" value="utilita">
            <input class="btn btn-secondary" type="submit" value="Utilità +1" <?= $alreadyUtil ? "disabled" : "" ?> style="<?= $alreadyUtil ? $btnGrayStyle : "" ?>">
          </form>
          <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#domande" style="margin-top:8px; display:inline;">
            <input type="hidden" name="action" value="vote_domanda">
            <input type="hidden" name="tid" value="<?= htmlspecialchars($d["id"]) ?>">
            <input type="hidden" name="kind" value="supporto">
            <input class="btn btn-secondary" type="submit" value="Supporto +1" <?= $alreadySupp ? "disabled" : "" ?> style="<?= $alreadySupp ? $btnGrayStyle : "" ?>">
          </form>
        <?php endif; ?>

        <?php if (!empty($d["risposte"])): ?>
          <div style="margin-top:10px; padding-left:14px;">
            <?php foreach ($d["risposte"] as $a): ?>
              <?php
                $_SESSION["qa_vote"] ??= [];
                $keyUtil = "vote_risposta:" . (string)$a["id"] . ":utilita";
                $keySupp = "vote_risposta:" . (string)$a["id"] . ":supporto";
                $alreadyUtil = isset($_SESSION["qa_vote"][$keyUtil]);
                $alreadySupp = isset($_SESSION["qa_vote"][$keySupp]);

                $risSecondMsg = ($voteAlready === "1" && $voteScope === "risposta" && $voteTid === (string)$a["id"] && ($voteKind === "utilita" || $voteKind === "supporto"));
                $btnGrayStyle = "background:#ddd; border-color:#ddd; color:#666; cursor:not-allowed;";
              ?>

              <div style="padding:10px 0; border-top:1px dashed #eee;">
                <p style="margin:0 0 6px 0;"><b>A:</b> <?= htmlspecialchars($a["testo"]) ?></p>
                <p style="margin:0; color:#777;">
                  di <?= htmlspecialchars($a["username"]) ?> — <?= htmlspecialchars($a["data"]) ?>
                </p>
                <p style="margin:6px 0 0 0; color:#777;">
                  utilità: <b><?= htmlspecialchars($a["utilita"]) ?></b> — supporto: <b><?= htmlspecialchars($a["supporto"]) ?></b>
                </p>

                <?php if ($risSecondMsg): ?>
                  <p style="margin:6px 0 0 0; color:#9a9a9a; font-style:italic;">voto già espresso</p>
                <?php endif; ?>

                <?php if ($role !== "visitatore" && $a["username"] !== $username): ?>
                  <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#domande" style="margin-top:8px; display:inline;">
                    <input type="hidden" name="action" value="vote_risposta">
                    <input type="hidden" name="tid" value="<?= htmlspecialchars($a["id"]) ?>">
                    <input type="hidden" name="kind" value="utilita">
                    <input class="btn btn-secondary" type="submit" value="Utilità +1" <?= $alreadyUtil ? "disabled" : "" ?> style="<?= $alreadyUtil ? $btnGrayStyle : "" ?>">
                  </form>
                  <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#domande" style="margin-top:8px; display:inline;">
                    <input type="hidden" name="action" value="vote_risposta">
                    <input type="hidden" name="tid" value="<?= htmlspecialchars($a["id"]) ?>">
                    <input type="hidden" name="kind" value="supporto">
                    <input class="btn btn-secondary" type="submit" value="Supporto +1" <?= $alreadySupp ? "disabled" : "" ?> style="<?= $alreadySupp ? $btnGrayStyle : "" ?>">
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($role !== "visitatore"): ?>
          <div style="margin-top:12px; padding:12px; border:1px solid #eee; border-radius:14px;">
            <form method="post" action="prodotto.php?id=<?= urlencode((string)$prodotto["id"]) ?>#domande">
              <input type="hidden" name="action" value="add_risposta">
              <input type="hidden" name="did" value="<?= htmlspecialchars($d["id"]) ?>">
              <div class="form-row" style="margin-bottom:10px;">
                <label>Rispondi</label>
                <input type="text" name="testo_risposta">
              </div>
              <input class="btn btn-primary" type="submit" value="Invia">
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php page_footer(); ?>