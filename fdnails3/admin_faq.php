<?php
session_start();
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/layout.php";
require_role("amministratore");

$XML_PATH = __DIR__ . "/xml/faq.xml";

function load_faq_dom(string $path): DOMDocument {
  if (!is_file($path)) {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->formatOutput = true;
    $doc->appendChild($doc->createElement("faq"));
    $doc->save($path);
  }

  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $doc->load($path);
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

function get_child_text(DOMElement $parent, string $name, string $default = ""): string {
  $el = first_child_element_by_name($parent, $name);
  return $el ? trim($el->textContent) : $default;
}

function find_qna_by_id(DOMDocument $doc, string $id): ?DOMElement {
  $n = (new DOMXPath($doc))->query("//qna[@id='$id']")->item(0);
  return $n instanceof DOMElement ? $n : null;
}

function next_qna_id(DOMDocument $doc): string {
  $max = 0;
  foreach ((new DOMXPath($doc))->query("//qna/@id") as $attr) {
    $id = (string)$attr->nodeValue;
    if (preg_match("/^F(\d+)$/", $id, $m)) $max = max($max, (int)$m[1]);
  }
  return "F" . str_pad((string)($max + 1), 2, "0", STR_PAD_LEFT);
}

$doc = load_faq_dom($XML_PATH);
$root = $doc->documentElement;

$action = $_POST["action"] ?? $_GET["action"] ?? "";
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($action === "add") {
    $domanda = trim((string)($_POST["domanda"] ?? ""));
    $risposta = trim((string)($_POST["risposta"] ?? ""));

    if ($domanda === "" || $risposta === "") {
      $msg = "Compila domanda e risposta.";
    } else {
      $id = next_qna_id($doc);
      $qna = $doc->createElement("qna");
      $qna->setAttribute("id", $id);
      set_or_create_child($doc, $qna, "domanda", $domanda);
      set_or_create_child($doc, $qna, "risposta", $risposta);
      $root->appendChild($qna);
      $doc->save($XML_PATH);
      $msg = "FAQ aggiunta ($id).";
    }
  } elseif ($action === "update") {
    $id = (string)($_POST["id"] ?? "");
    $domanda = trim((string)($_POST["domanda"] ?? ""));
    $risposta = trim((string)($_POST["risposta"] ?? ""));

    $qna = find_qna_by_id($doc, $id);
    if (!$qna) {
      $msg = "FAQ non trovata.";
    } elseif ($domanda === "" || $risposta === "") {
      $msg = "Compila domanda e risposta.";
    } else {
      set_or_create_child($doc, $qna, "domanda", $domanda);
      set_or_create_child($doc, $qna, "risposta", $risposta);
      $doc->save($XML_PATH);
      $msg = "FAQ aggiornata ($id).";
    }
  } elseif ($action === "delete") {
    $id = (string)($_POST["id"] ?? "");
    $qna = find_qna_by_id($doc, $id);
    if (!$qna) {
      $msg = "FAQ non trovata.";
    } else {
      $qna->parentNode->removeChild($qna);
      $doc->save($XML_PATH);
      $msg = "FAQ eliminata ($id).";
    }
  }
}

$edit_id = (string)($_GET["edit"] ?? "");
$edit_domanda = "";
$edit_risposta = "";
if ($edit_id !== "") {
  $qna = find_qna_by_id($doc, $edit_id);
  if ($qna) {
    $edit_domanda = get_child_text($qna, "domanda");
    $edit_risposta = get_child_text($qna, "risposta");
  } else {
    $edit_id = "";
  }
}

$qna_list = [];
foreach ($root->getElementsByTagName("qna") as $qna) {
  if (!($qna instanceof DOMElement)) continue;
  $qna_list[] = [
    "id" => $qna->getAttribute("id"),
    "domanda" => get_child_text($qna, "domanda"),
    "risposta" => get_child_text($qna, "risposta"),
  ];
}

page_header("FD NAILS - Gestione FAQ", "admin_faq");
?>
<div class="page-title"><h1>Gestione FAQ</h1></div>

<?php if ($msg !== ""): ?>
  <div style="margin: 15px 0; padding: 10px; border: 1px solid #ccc;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<div style="padding:20px; border: 1px solid #ccc; margin: 15px 0;">
  <h2 style="margin-top:0;"><?= ($edit_id !== "") ? "Modifica FAQ" : "Nuova FAQ" ?></h2>

  <form method="post" action="admin_faq.php">
    <?php if ($edit_id !== ""): ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= htmlspecialchars($edit_id) ?>">
    <?php else: ?>
      <input type="hidden" name="action" value="add">
    <?php endif; ?>

    <div class="form-row">
      <label>Domanda</label>
      <input type="text" name="domanda" value="<?= htmlspecialchars($edit_domanda) ?>" style="width:100%;" />
    </div>

    <div class="form-row">
      <label>Risposta</label>
      <input type="text" name="risposta" value="<?= htmlspecialchars($edit_risposta) ?>" style="width:100%;" />
    </div>

    <div class="form-row" style="margin-bottom:0;">
      <input class="btn btn-primary" type="submit" value="<?= ($edit_id !== "") ? "Salva" : "Aggiungi" ?>">
      <?php if ($edit_id !== ""): ?>
        <a class="btn btn-secondary" href="admin_faq.php" style="margin-left:8px;">Annulla</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div style="padding:20px; border: 1px solid #ccc; margin: 15px 0;">
  <h2 style="margin-top:0;">Elenco FAQ</h2>

  <?php if (!$qna_list): ?>
    <p>Nessuna FAQ.</p>
  <?php else: ?>
    <?php foreach ($qna_list as $r): ?>
      <div style="padding:10px 0; border-top:1px solid #eee;">
        <p style="margin:0 0 6px 0;"><b><?= htmlspecialchars($r["id"]) ?></b> — <?= htmlspecialchars($r["domanda"]) ?></p>
        <p style="margin:0 0 10px 0;"><?= htmlspecialchars($r["risposta"]) ?></p>

        <a class="btn btn-secondary" href="admin_faq.php?edit=<?= urlencode($r["id"]) ?>">Modifica</a>

        <form method="post" action="admin_faq.php" style="display:inline;">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= htmlspecialchars($r["id"]) ?>">
          <input class="btn btn-secondary" type="submit" value="Elimina" onclick="return confirm('Eliminare <?= htmlspecialchars($r["id"]) ?>?');">
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php page_footer(); ?>