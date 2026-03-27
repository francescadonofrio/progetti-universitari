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

function next_id(DOMDocument $doc, string $prefix, string $tag): string {
  $max = 0;
  foreach ((new DOMXPath($doc))->query("//{$tag}/@id") as $attr) {
    $id = (string)$attr->nodeValue;
    if (preg_match("/^" . preg_quote($prefix, "/") . "(\d+)$/", $id, $m)) $max = max($max, (int)$m[1]);
  }
  return $prefix . str_pad((string)($max + 1), 2, "0", STR_PAD_LEFT);
}

function remove_first_child_by_tag(DOMElement $parent, string $tag): void {
  foreach ($parent->getElementsByTagName($tag) as $el) {
    if ($el instanceof DOMElement) { $parent->removeChild($el); break; }
  }
}

function ensure_container(DOMDocument $doc, DOMElement $root, string $name): DOMElement {
  return first_child_element_by_name($root, $name) ?? $root->appendChild($doc->createElement($name));
}

$doc = load_promo_dom($XML_PATH);
$root = $doc->documentElement;

$action = $_POST["action"] ?? "";
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($action === "add_sconto") {
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
      $id = next_id($doc, "SC", "sconto");

      $s = $doc->createElement("sconto");
      $s->setAttribute("id", $id);
      $s->setAttribute("tipo", $tipo);
      $s->setAttribute("valorePercento", $valore);
      $s->setAttribute("dal", $dal);
      $s->setAttribute("al", $al);

      set_or_create_child($doc, $s, "descrizione", $descr);

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
      $s->appendChild($app);

      $sconti = ensure_container($doc, $root, "sconti");
      $sconti->appendChild($s);

      $doc->save($XML_PATH);
      $msg = "Sconto aggiunto ($id).";
    }
  } elseif ($action === "add_bonus") {
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
      $id = next_id($doc, "BO", "bonusterm");

      $b = $doc->createElement("bonusterm");
      $b->setAttribute("id", $id);
      $b->setAttribute("tipo", $tipo);
      $b->setAttribute("crediti", $crediti);
      $b->setAttribute("dal", $dal);
      $b->setAttribute("al", $al);

      set_or_create_child($doc, $b, "descrizione", $descr);

      $app = $doc->createElement("applicazione");
      if ($tipo === "personalizzato") {
        $c = $doc->createElement("criterio");
        $c->setAttribute("tipo", $crit_tipo !== "" ? $crit_tipo : "reputazione");
        $c->setAttribute("valore", $crit_val);
        $app->appendChild($c);
      } else {
        $app->appendChild($doc->createElement("tutti"));
      }
      $b->appendChild($app);

      $bonus = ensure_container($doc, $root, "bonus");
      $bonus->appendChild($b);

      $doc->save($XML_PATH);
      $msg = "Bonus aggiunto ($id).";
    }
  } elseif ($action === "delete") {
    $tag = (string)($_POST["tag"] ?? "");
    $id = (string)($_POST["id"] ?? "");
    if (($tag === "sconto" || $tag === "bonusterm") && $id !== "") {
      $n = find_by_id($doc, $tag, $id);
      if ($n) {
        $n->parentNode->removeChild($n);
        $doc->save($XML_PATH);
        $msg = "Eliminato ($id).";
      } else {
        $msg = "Elemento non trovato.";
      }
    }
  }
}

$sconti = [];
$bonus = [];

$scontiNode = first_child_element_by_name($root, "sconti");
if ($scontiNode) {
  foreach ($scontiNode->getElementsByTagName("sconto") as $s) {
    if (!($s instanceof DOMElement)) continue;
    $descr = trim((string)first_child_element_by_name($s, "descrizione")?->textContent);
    $sconti[] = [
      "id" => $s->getAttribute("id"),
      "tipo" => $s->getAttribute("tipo"),
      "valore" => $s->getAttribute("valorePercento"),
      "dal" => $s->getAttribute("dal"),
      "al" => $s->getAttribute("al"),
      "descrizione" => $descr,
    ];
  }
}

$bonusNode = first_child_element_by_name($root, "bonus");
if ($bonusNode) {
  foreach ($bonusNode->getElementsByTagName("bonusterm") as $b) {
    if (!($b instanceof DOMElement)) continue;
    $descr = trim((string)first_child_element_by_name($b, "descrizione")?->textContent);
    $bonus[] = [
      "id" => $b->getAttribute("id"),
      "tipo" => $b->getAttribute("tipo"),
      "crediti" => $b->getAttribute("crediti"),
      "dal" => $b->getAttribute("dal"),
      "al" => $b->getAttribute("al"),
      "descrizione" => $descr,
    ];
  }
}

page_header("FD NAILS - Sconti/Bonus", "admin_promo");
?>
<div class="page-title"><h1>Sconti / Bonus</h1></div>

<?php if ($msg !== ""): ?>
  <div style="margin: 15px 0; padding: 10px; border: 1px solid #ccc;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<div style="padding:20px; border: 1px solid #ccc; margin: 15px 0;">
  <h2 style="margin-top:0;">Aggiungi sconto</h2>
  <form method="post" action="admin_promo.php">
    <input type="hidden" name="action" value="add_sconto">

    <div class="filter-row">
      <label>Tipo</label>
      <select name="tipo" id="tipo_sconto">
        <option value="generico">generico</option>
        <option value="prodotto">prodotto</option>
        <option value="personalizzato">personalizzato</option>
      </select>
    </div>

    <div class="filter-row">
      <label>Valore (%)</label>
      <input type="text" name="valore" value="" />
    </div>

    <div class="filter-row">
      <label>Dal</label>
      <input type="date" name="dal" value="" />
    </div>

    <div class="filter-row">
      <label>Al</label>
      <input type="date" name="al" value="" />
    </div>

    <div class="filter-row">
      <label>Descrizione</label>
      <input type="text" name="descrizione" style="width:100%;" value="" />
    </div>

    <div class="filter-row" id="row_id_prodotto">
      <label>ID prodotto</label>
      <input type="text" name="idProdotto" placeholder="es: S01" value="" />
    </div>

    <div class="filter-row" id="row_criterio_sconto">
      <label>Criterio</label>
      <select name="criterio_tipo">
        <option value="reputazione">reputazione</option>
        <option value="anzianita">anzianità</option>
        <option value="crediti_spesi">crediti_spesi</option>
      </select>
      <input type="text" name="criterio_valore" placeholder="valore" value="" />
    </div>

    <div class="filter-row filter-actions">
      <input class="btn btn-primary" type="submit" value="Aggiungi sconto" />
    </div>
  </form>
</div>

<div style="padding:20px; border: 1px solid #ccc; margin: 15px 0;">
  <h2 style="margin-top:0;">Aggiungi bonus</h2>
  <form method="post" action="admin_promo.php">
    <input type="hidden" name="action" value="add_bonus">

    <div class="filter-row">
      <label>Tipo</label>
      <select name="tipo" id="tipo_bonus">
        <option value="generico">generico</option>
        <option value="personalizzato">personalizzato</option>
      </select>
    </div>

    <div class="filter-row">
      <label>Crediti</label>
      <input type="text" name="crediti" value="" />
    </div>

    <div class="filter-row">
      <label>Dal</label>
      <input type="date" name="dal" value="" />
    </div>

    <div class="filter-row">
      <label>Al</label>
      <input type="date" name="al" value="" />
    </div>

    <div class="filter-row">
      <label>Descrizione</label>
      <input type="text" name="descrizione" style="width:100%;" value="" />
    </div>

    <div class="filter-row" id="row_criterio_bonus">
      <label>Criterio</label>
      <select name="criterio_tipo">
        <option value="reputazione">reputazione</option>
        <option value="anzianita">anzianità</option>
        <option value="crediti_spesi">crediti_spesi</option>
      </select>
      <input type="text" name="criterio_valore" placeholder="valore" value="" />
    </div>

    <div class="filter-row filter-actions">
      <input class="btn btn-primary" type="submit" value="Aggiungi bonus" />
    </div>
  </form>
</div>

<div style="padding:20px; border: 1px solid #ccc; margin: 15px 0;">
  <h2 style="margin-top:0;">Elenco sconti</h2>
  <?php if (!$sconti): ?>
    <p>Nessuno sconto.</p>
  <?php else: ?>
    <?php foreach ($sconti as $s): ?>
      <div style="padding:10px 0; border-top:1px solid #eee;">
        <p style="margin:0;"><b><?= htmlspecialchars($s["id"]) ?></b> — <?= htmlspecialchars($s["tipo"]) ?> — <?= htmlspecialchars($s["valore"]) ?>% (<?= htmlspecialchars($s["dal"]) ?> → <?= htmlspecialchars($s["al"]) ?>)</p>
        <p style="margin:6px 0 10px 0;"><?= htmlspecialchars($s["descrizione"]) ?></p>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <form method="post" action="admin_promo.php" style="margin:0;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="tag" value="sconto">
            <input type="hidden" name="id" value="<?= htmlspecialchars($s["id"]) ?>">
            <input class="btn btn-secondary" style="width:auto; display:inline-block;" type="submit" value="Elimina" onclick="return confirm('Eliminare <?= htmlspecialchars($s["id"]) ?>?');">
          </form>

          <a class="btn btn-secondary" style="width:auto; display:inline-block;" href="admin_promo_edit.php?tag=sconto&id=<?= urlencode($s["id"]) ?>">Modifica</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div style="padding:20px; border: 1px solid #ccc; margin: 15px 0;">
  <h2 style="margin-top:0;">Elenco bonus</h2>
  <?php if (!$bonus): ?>
    <p>Nessun bonus.</p>
  <?php else: ?>
    <?php foreach ($bonus as $b): ?>
      <div style="padding:10px 0; border-top:1px solid #eee;">
        <p style="margin:0;"><b><?= htmlspecialchars($b["id"]) ?></b> — <?= htmlspecialchars($b["tipo"]) ?> — <?= htmlspecialchars($b["crediti"]) ?> crediti (<?= htmlspecialchars($b["dal"]) ?> → <?= htmlspecialchars($b["al"]) ?>)</p>
        <p style="margin:6px 0 10px 0;"><?= htmlspecialchars($b["descrizione"]) ?></p>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <form method="post" action="admin_promo.php" style="margin:0;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="tag" value="bonusterm">
            <input type="hidden" name="id" value="<?= htmlspecialchars($b["id"]) ?>">
            <input class="btn btn-secondary" style="width:auto; display:inline-block;" type="submit" value="Elimina" onclick="return confirm('Eliminare <?= htmlspecialchars($b["id"]) ?>?');">
          </form>

          <a class="btn btn-secondary" style="width:auto; display:inline-block;" href="admin_promo_edit.php?tag=bonusterm&id=<?= urlencode($b["id"]) ?>">Modifica</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function aggiornaCampiSconto() {
  var tipo = document.getElementById('tipo_sconto').value;
  document.getElementById('row_id_prodotto').style.display = (tipo === 'prodotto') ? '' : 'none';
  document.getElementById('row_criterio_sconto').style.display = (tipo === 'personalizzato') ? '' : 'none';
}

function aggiornaCampiBonus() {
  var tipo = document.getElementById('tipo_bonus').value;
  document.getElementById('row_criterio_bonus').style.display = (tipo === 'personalizzato') ? '' : 'none';
}

document.getElementById('tipo_sconto').addEventListener('change', aggiornaCampiSconto);
document.getElementById('tipo_bonus').addEventListener('change', aggiornaCampiBonus);

aggiornaCampiSconto();
aggiornaCampiBonus();
</script>

<?php page_footer(); ?>