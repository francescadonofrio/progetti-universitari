<?php
session_start();
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/layout.php";

require_role("amministratore");

$XML_PATH = __DIR__ . "/xml/catalogo.xml";

function load_catalogo_dom(string $path): DOMDocument {
  if (!file_exists($path)) {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->formatOutput = true;
    $root = $doc->createElement("catalogo");
    $doc->appendChild($root);
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
  $el = first_child_element_by_name($parent, $name);
  if (!$el) {
    $el = $doc->createElement($name);
    $parent->appendChild($el);
  }

  while ($el->firstChild) $el->removeChild($el->firstChild);
  $el->appendChild($doc->createTextNode($value));
}

function get_child_text(DOMElement $parent, string $name, string $default = ""): string {
  $el = first_child_element_by_name($parent, $name);
  if (!$el) return $default;
  return trim($el->textContent);
}

function get_child_text_raw(DOMElement $parent, string $name, string $default = ""): string {
  $el = first_child_element_by_name($parent, $name);
  if (!$el) return $default;
  return (string)$el->textContent;
}

function find_smalto_by_id(DOMDocument $doc, string $id): ?DOMElement {
  $xp = new DOMXPath($doc);
  $n = $xp->query("//smalto[@id='$id']")->item(0);
  return ($n instanceof DOMElement) ? $n : null;
}

function next_smalto_id(DOMDocument $doc): string {
  $xp = new DOMXPath($doc);
  $max = 0;

  foreach ($xp->query("//smalto") as $s) {
    if (!($s instanceof DOMElement)) continue;
    $id = $s->getAttribute("id"); // es: S01
    if (preg_match("/^S(\d+)$/", $id, $m)) {
      $num = (int)$m[1];
      if ($num > $max) $max = $num;
    }
  }

  $next = $max + 1;
  return "S" . str_pad((string)$next, 2, "0", STR_PAD_LEFT);
}

$doc = load_catalogo_dom($XML_PATH);
$root = $doc->documentElement;

$action = $_POST["action"] ?? $_GET["action"] ?? "";

if ($action === "add" && $_SERVER["REQUEST_METHOD"] === "POST") {
  $nome = trim($_POST["nome"] ?? "");
  $prezzo = trim($_POST["prezzo"] ?? "");

  $descrizione = (string)($_POST["descrizione"] ?? "");
  $descrizione = str_replace("\r\n", "\n", $descrizione);

  $immagine = trim($_POST["immagine"] ?? "");
  $categoria = trim($_POST["categoria"] ?? "");

  if ($nome !== "" && $prezzo !== "" && $immagine !== "") {
    $id = next_smalto_id($doc);

    $smalto = $doc->createElement("smalto");
    $smalto->setAttribute("id", $id);

    set_or_create_child($doc, $smalto, "nome", $nome);
    set_or_create_child($doc, $smalto, "descrizione", $descrizione);
    set_or_create_child($doc, $smalto, "prezzo", $prezzo);
    set_or_create_child($doc, $smalto, "immagine", $immagine);

    if ($categoria !== "") {
      set_or_create_child($doc, $smalto, "categoria", $categoria);
    }

    $root->appendChild($smalto);
    $doc->save($XML_PATH);
  }
}

if ($action === "delete") {
  $id = $_GET["id"] ?? "";
  $s = ($id !== "") ? find_smalto_by_id($doc, $id) : null;

  if ($s) {
    $s->parentNode->removeChild($s);
    $doc->save($XML_PATH);
  }
}

if ($action === "edit" && $_SERVER["REQUEST_METHOD"] === "POST") {
  $id = $_POST["id"] ?? "";
  $s = ($id !== "") ? find_smalto_by_id($doc, $id) : null;

  if ($s) {
    $nome = trim($_POST["nome"] ?? "");
    $prezzo = trim($_POST["prezzo"] ?? "");

    $descrizione = (string)($_POST["descrizione"] ?? "");
    $descrizione = str_replace("\r\n", "\n", $descrizione);

    $immagine = trim($_POST["immagine"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");

    if ($nome !== "" && $prezzo !== "" && $immagine !== "") {
      $s->setAttribute("id", $id);

      set_or_create_child($doc, $s, "nome", $nome);
      set_or_create_child($doc, $s, "descrizione", $descrizione);
      set_or_create_child($doc, $s, "prezzo", $prezzo);
      set_or_create_child($doc, $s, "immagine", $immagine);

      if ($categoria !== "") {
        set_or_create_child($doc, $s, "categoria", $categoria);
      }

      $doc->save($XML_PATH);
    }
  }
}

$prodotti = [];
$xp = new DOMXPath($doc);
foreach ($xp->query("//smalto") as $s) {
  if (!($s instanceof DOMElement)) continue;

  $id = $s->getAttribute("id");

  $prodotti[] = [
    "id" => $id,
    "nome" => get_child_text($s, "nome", ""),
    "prezzo" => get_child_text($s, "prezzo", ""),
    "categoria" => get_child_text($s, "categoria", ""),
    "descrizione" => get_child_text_raw($s, "descrizione", ""),
    "immagine" => get_child_text($s, "immagine", "")
  ];
}

$editId = $_GET["edit_id"] ?? "";
$editProd = null;
if ($editId !== "") {
  foreach ($prodotti as $pr) {
    if ($pr["id"] === $editId) { $editProd = $pr; break; }
  }
}

$MEDIA_DIR = __DIR__ . "/media";
$immaginiDisponibili = [];

if (is_dir($MEDIA_DIR)) {
  $files = scandir($MEDIA_DIR);
  if ($files !== false) {
    foreach ($files as $f) {
      if ($f === "." || $f === "..") continue;

      $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
      $name = strtolower(pathinfo($f, PATHINFO_FILENAME));

      if ($name === "logo") continue;

      if (in_array($ext, ["jpg", "jpeg", "png", "gif", "webp", "svg"], true)) {
        $immaginiDisponibili[] = $f;
      }
    }
  }
}
sort($immaginiDisponibili);

page_header("FD NAILS - Admin Catalogo", "admin_catalogo");
?>

<h1>Gestione catalogo</h1>

<hr>

<h2><?= $editProd ? "Modifica smalto" : "Aggiungi smalto" ?></h2>

<form method="post">
  <input type="hidden" name="action" value="<?= $editProd ? "edit" : "add" ?>">
  <?php if ($editProd): ?>
    <input type="hidden" name="id" value="<?= htmlspecialchars($editProd["id"]) ?>">
  <?php endif; ?>

  <p>
    <label>Nome<br>
      <input name="nome" required value="<?= htmlspecialchars($editProd["nome"] ?? "") ?>">
    </label>
  </p>

  <p>
    <label>Prezzo<br>
      <input name="prezzo" required value="<?= htmlspecialchars($editProd["prezzo"] ?? "") ?>">
    </label>
  </p>

  <p>
    <label>Immagine<br>
      <select name="immagine" id="immagine" required>
        <option value="">-- Seleziona --</option>
        <?php foreach ($immaginiDisponibili as $img): ?>
          <option value="<?= htmlspecialchars($img) ?>"
            <?= (($editProd["immagine"] ?? "") === $img) ? "selected" : "" ?>>
            <?= htmlspecialchars($img) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <br>
    <img
      id="immagine_preview"
      src="<?= (($editProd["immagine"] ?? "") !== "") ? "media/" . htmlspecialchars($editProd["immagine"]) : "" ?>"
      alt=""
      style="<?= (($editProd["immagine"] ?? "") !== "") ? "" : "display:none;" ?>; max-width:120px; height:auto;"
    >
  </p>

  <p>
    <label>Categoria (opzionale)<br>
      <input name="categoria" value="<?= htmlspecialchars($editProd["categoria"] ?? "") ?>">
    </label>
  </p>

  <p>
    <label>Descrizione<br>
      <textarea name="descrizione" rows="4" cols="50"><?= htmlspecialchars($editProd["descrizione"] ?? "", ENT_QUOTES, "UTF-8") ?></textarea>
    </label>
  </p>

  <button type="submit"><?= $editProd ? "Salva modifiche" : "Aggiungi" ?></button>
  <?php if ($editProd): ?>
    <a href="admin_catalogo.php" style="margin-left:10px;">Annulla</a>
  <?php endif; ?>
</form>

<script>
  (function(){
    const sel = document.getElementById("immagine");
    const img = document.getElementById("immagine_preview");
    if (!sel || !img) return;

    sel.addEventListener("change", function(){
      if (!this.value) {
        img.style.display = "none";
        img.removeAttribute("src");
        return;
      }
      img.style.display = "";
      img.setAttribute("src", "media/" + this.value);
    });
  })();
</script>

<hr>

<h2>Prodotti presenti</h2>

<table border="1" cellpadding="6" cellspacing="0">
  <tr>
    <th>ID</th>
    <th>Nome</th>
    <th>Prezzo</th>
    <th>Immagine</th>
    <th>Azioni</th>
  </tr>
  <?php foreach ($prodotti as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p["id"]) ?></td>
      <td><?= htmlspecialchars($p["nome"]) ?></td>
      <td><?= htmlspecialchars($p["prezzo"]) ?></td>
      <td><?= htmlspecialchars($p["immagine"]) ?></td>
      <td>
        <a href="admin_catalogo.php?edit_id=<?= urlencode($p["id"]) ?>">Modifica</a>
        |
        <a href="admin_catalogo.php?action=delete&id=<?= urlencode($p["id"]) ?>"
           onclick="return confirm('Eliminare lo smalto?');">Elimina</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php page_footer(); ?>
