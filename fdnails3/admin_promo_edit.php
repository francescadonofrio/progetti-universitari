<?php
session_start();
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/layout.php";
require_role("amministratore");

$XML_PATH = __DIR__ . "/xml/promozioni.xml";

function ensure_promo_file(string $path): void {
  if (is_file($path)) return;

  $doc = new DOMDocument("1.0", "UTF-8");
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;

  $root = $doc->appendChild($doc->createElement("promozioni"));
  $root->appendChild($doc->createElement("sconti"));
  $root->appendChild($doc->createElement("bonus"));

  $doc->save($path);
}

function load_promo_dom(string $path): DOMDocument {
  ensure_promo_file($path);
  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $doc->load($path, LIBXML_NONET);
  return $doc;
}

function first_child_element_by_name(DOMElement $parent, string $name): ?DOMElement {
  foreach ($parent->childNodes as $n) {
    if ($n instanceof DOMElement && $n->tagName === $name) return $n;
  }
  return null;
}

function set_or_create_child(DOMDocument $doc, DOMElement $parent, string $name, string $value): void {
  $el = first_child_element_by_name($parent, $name) ?? $doc->createElement($name);
  if (!$el->parentNode) $parent->appendChild($el);
  $el->textContent = "";
  $el->appendChild($doc->createTextNode($value));
}

function find_by_id(DOMDocument $doc, string $tag, string $id): ?DOMElement {
  $n = (new DOMXPath($doc))->query("//{$tag}[@id='$id']")->item(0);
  return $n instanceof DOMElement ? $n : null;
}

function remove_first_child_by_tag(DOMElement $parent, string $tag): void {
  foreach ($parent->getElementsByTagName($tag) as $el) {
    if ($el instanceof DOMElement) { $parent->removeChild($el); break; }
  }
}

$tag = (string)($_GET["tag"] ?? $_POST["tag"] ?? "");
$id = (string)($_GET["id"] ?? $_POST["id"] ?? "");
$msg = "";

if (($tag !== "sconto" && $tag !== "bonusterm") || $id === "") {
  header("Location: admin_promo.php");
  exit;
}

$doc = load_promo_dom($XML_PATH);
$n = find_by_id($doc, $tag, $id);

if (!$n) {
  header("Location: admin_promo.php");
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($tag === "sconto") {
    $tipo = (string)($_POST["tipo"] ?? "generico");
    $valore = trim((string)($_POST["valore"] ?? ""));
    $dal = trim((string)($_POST["dal"] ?? ""));
    $al = trim((string)($_POST["al"] ?? ""));
    $descr = trim((string)($_POST["descrizione"] ?? ""));
    $idProdotto = trim((string)($_POST["idProdotto"] ?? ""));
    $crit_tipo = trim((string)($_POST["criterio_tipo"] ?? ""));
    $crit_val = trim((string)($_POST["criterio_valore"] ?? ""));

    if (!in_array($tipo, ["generico", "prodotto", "personalizzato"], true)) {
      $tipo = "generico";
    }

    if ($valore === "" || $dal === "" || $al === "" || $descr === "") {
      $msg = "Compila valore, date e descrizione.";
    } elseif ($tipo === "prodotto" && $idProdotto === "") {
      $msg = "Compila ID prodotto.";
    } elseif ($tipo === "personalizzato" && $crit_val === "") {
      $msg = "Compila il valore del criterio.";
    } else {
      $n->setAttribute("tipo", $tipo);
      $n->setAttribute("valorePercento", $valore);
      $n->setAttribute("dal", $dal);
      $n->setAttribute("al", $al);

      set_or_create_child($doc, $n, "descrizione", $descr);

      remove_first_child_by_tag($n, "applicazione");

      $app = $doc->createElement("applicazione");
      if ($tipo === "prodotto") {
        $app->appendChild($doc->createElement("idProdotto", $idProdotto));
      } elseif ($tipo === "personalizzato") {
        $c = $doc->createElement("criterio");
        $c->setAttribute("tipo", $crit_tipo !== "" ? $crit_tipo : "reputazione");
        $c->setAttribute("valore", $crit_val);
        $app->appendChild($c);
      } else {
        $app->appendChild($doc->createElement("tutti"));
      }
      $n->appendChild($app);

      $doc->save($XML_PATH);
      header("Location: admin_promo.php");
      exit;
    }
  }

  if ($tag === "bonusterm") {
    $tipo = (string)($_POST["tipo"] ?? "generico");
    $crediti = trim((string)($_POST["crediti"] ?? ""));
    $dal = trim((string)($_POST["dal"] ?? ""));
    $al = trim((string)($_POST["al"] ?? ""));
    $descr = trim((string)($_POST["descrizione"] ?? ""));
    $crit_tipo = trim((string)($_POST["criterio_tipo"] ?? ""));
    $crit_val = trim((string)($_POST["criterio_valore"] ?? ""));

    if (!in_array($tipo, ["generico", "personalizzato"], true)) {
      $tipo = "generico";
    }

    if ($crediti === "" || $dal === "" || $al === "" || $descr === "") {
      $msg = "Compila crediti, date e descrizione.";
    } elseif ($tipo === "personalizzato" && $crit_val === "") {
      $msg = "Compila il valore del criterio.";
    } else {
      $n->setAttribute("tipo", $tipo);
      $n->setAttribute("crediti", $crediti);
      $n->setAttribute("dal", $dal);
      $n->setAttribute("al", $al);

      set_or_create_child($doc, $n, "descrizione", $descr);

      remove_first_child_by_tag($n, "applicazione");

      $app = $doc->createElement("applicazione");
      if ($tipo === "personalizzato") {
        $c = $doc->createElement("criterio");
        $c->setAttribute("tipo", $crit_tipo !== "" ? $crit_tipo : "reputazione");
        $c->setAttribute("valore", $crit_val);
        $app->appendChild($c);
      } else {
        $app->appendChild($doc->createElement("tutti"));
      }
      $n->appendChild($app);

      $doc->save($XML_PATH);
      header("Location: admin_promo.php");
      exit;
    }
  }
}

$descr = trim((string)first_child_element_by_name($n, "descrizione")?->textContent);
$app = first_child_element_by_name($n, "applicazione");
$idProdotto = trim((string)first_child_element_by_name($app ?? $n, "idProdotto")?->textContent);
$cEl = first_child_element_by_name($app ?? $n, "criterio");

page_header("FD NAILS - Modifica promo", "admin_promo");
?>

<div class="page-title"><h1>Modifica</h1></div>

<?php if ($msg !== ""): ?>
  <div style="margin: 15px 0; padding: 10px; border: 1px solid #ccc;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<div style="padding:20px; border: 1px solid #ccc; margin: 15px 0;">
  <?php if ($tag === "sconto"): ?>
    <h2 style="margin-top:0;">Modifica sconto</h2>
    <form method="post" action="admin_promo_edit.php">
      <input type="hidden" name="tag" value="sconto">
      <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

      <div class="filter-row">
        <label>Tipo</label>
        <select name="tipo" id="tipo_sconto">
          <option value="generico" <?= $n->getAttribute("tipo")==="generico" ? "selected" : "" ?>>generico</option>
          <option value="prodotto" <?= $n->getAttribute("tipo")==="prodotto" ? "selected" : "" ?>>prodotto</option>
          <option value="personalizzato" <?= $n->getAttribute("tipo")==="personalizzato" ? "selected" : "" ?>>personalizzato</option>
        </select>
      </div>

      <div class="filter-row">
        <label>Valore (%)</label>
        <input type="text" name="valore" value="<?= htmlspecialchars($n->getAttribute("valorePercento")) ?>" />
      </div>

      <div class="filter-row">
        <label>Dal</label>
        <input type="date" name="dal" value="<?= htmlspecialchars($n->getAttribute("dal")) ?>" />
      </div>

      <div class="filter-row">
        <label>Al</label>
        <input type="date" name="al" value="<?= htmlspecialchars($n->getAttribute("al")) ?>" />
      </div>

      <div class="filter-row">
        <label>Descrizione</label>
        <input type="text" name="descrizione" style="width:100%;" value="<?= htmlspecialchars($descr) ?>" />
      </div>

      <div class="filter-row" id="row_id_prodotto">
        <label>ID prodotto</label>
        <input type="text" name="idProdotto" placeholder="es: S01" value="<?= htmlspecialchars($idProdotto) ?>" />
      </div>

      <div class="filter-row" id="row_criterio_sconto">
        <label>Criterio</label>
        <select name="criterio_tipo">
          <option value="reputazione" <?= ($cEl && $cEl->getAttribute("tipo")==="reputazione") ? "selected" : "" ?>>reputazione</option>
          <option value="anzianita" <?= ($cEl && $cEl->getAttribute("tipo")==="anzianita") ? "selected" : "" ?>>anzianità</option>
          <option value="crediti_spesi" <?= ($cEl && $cEl->getAttribute("tipo")==="crediti_spesi") ? "selected" : "" ?>>crediti_spesi</option>
        </select>
        <input type="text" name="criterio_valore" placeholder="valore" value="<?= htmlspecialchars($cEl ? $cEl->getAttribute("valore") : "") ?>" />
      </div>

      <div class="filter-row filter-actions">
        <input class="btn btn-primary" type="submit" value="Salva modifiche" />
        <a class="btn btn-secondary" style="margin-left:10px;" href="admin_promo.php">Annulla</a>
      </div>
    </form>
  <?php else: ?>
    <h2 style="margin-top:0;">Modifica bonus</h2>
    <form method="post" action="admin_promo_edit.php">
      <input type="hidden" name="tag" value="bonusterm">
      <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

      <div class="filter-row">
        <label>Tipo</label>
        <select name="tipo" id="tipo_bonus">
          <option value="generico" <?= $n->getAttribute("tipo")==="generico" ? "selected" : "" ?>>generico</option>
          <option value="personalizzato" <?= $n->getAttribute("tipo")==="personalizzato" ? "selected" : "" ?>>personalizzato</option>
        </select>
      </div>

      <div class="filter-row">
        <label>Crediti</label>
        <input type="text" name="crediti" value="<?= htmlspecialchars($n->getAttribute("crediti")) ?>" />
      </div>

      <div class="filter-row">
        <label>Dal</label>
        <input type="date" name="dal" value="<?= htmlspecialchars($n->getAttribute("dal")) ?>" />
      </div>

      <div class="filter-row">
        <label>Al</label>
        <input type="date" name="al" value="<?= htmlspecialchars($n->getAttribute("al")) ?>" />
      </div>

      <div class="filter-row">
        <label>Descrizione</label>
        <input type="text" name="descrizione" style="width:100%;" value="<?= htmlspecialchars($descr) ?>" />
      </div>

      <div class="filter-row" id="row_criterio_bonus">
        <label>Criterio</label>
        <select name="criterio_tipo">
          <option value="reputazione" <?= ($cEl && $cEl->getAttribute("tipo")==="reputazione") ? "selected" : "" ?>>reputazione</option>
          <option value="anzianita" <?= ($cEl && $cEl->getAttribute("tipo")==="anzianita") ? "selected" : "" ?>>anzianità</option>
          <option value="crediti_spesi" <?= ($cEl && $cEl->getAttribute("tipo")==="crediti_spesi") ? "selected" : "" ?>>crediti_spesi</option>
        </select>
        <input type="text" name="criterio_valore" placeholder="valore" value="<?= htmlspecialchars($cEl ? $cEl->getAttribute("valore") : "") ?>" />
      </div>

      <div class="filter-row filter-actions">
        <input class="btn btn-primary" type="submit" value="Salva modifiche" />
        <a class="btn btn-secondary" style="margin-left:10px;" href="admin_promo.php">Annulla</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
function aggiornaCampiSconto() {
  var el = document.getElementById('tipo_sconto');
  if (!el) return;
  var tipo = el.value;
  document.getElementById('row_id_prodotto').style.display = (tipo === 'prodotto') ? '' : 'none';
  document.getElementById('row_criterio_sconto').style.display = (tipo === 'personalizzato') ? '' : 'none';
}

function aggiornaCampiBonus() {
  var el = document.getElementById('tipo_bonus');
  if (!el) return;
  var tipo = el.value;
  document.getElementById('row_criterio_bonus').style.display = (tipo === 'personalizzato') ? '' : 'none';
}

var tipoSconto = document.getElementById('tipo_sconto');
if (tipoSconto) tipoSconto.addEventListener('change', aggiornaCampiSconto);

var tipoBonus = document.getElementById('tipo_bonus');
if (tipoBonus) tipoBonus.addEventListener('change', aggiornaCampiBonus);

aggiornaCampiSconto();
aggiornaCampiBonus();
</script>

<?php page_footer(); ?>