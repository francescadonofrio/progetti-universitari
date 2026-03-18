<?php
session_start();
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/xml.php";

$role = current_role();
$username = $_SESSION["username"] ?? "";

$XML_REC = __DIR__ . "/xml/recensioni.xml";
$XML_CAT = __DIR__ . "/xml/catalogo.xml";

$id_smalto_filter = trim((string)($_GET["id_smalto"] ?? ""));

function ensure_recensioni_xml(string $path): DOMDocument {
  if (!is_file($path)) {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->appendChild($doc->createElement("recensioni"));
    $doc->save($path);
  }
  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $doc->load($path, LIBXML_NONET);
  return $doc;
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

function next_rec_id(DOMDocument $doc): string {
  $max = 0;
  foreach ((new DOMXPath($doc))->query("//recensione/@id") as $attr) {
    $id = (string)$attr->nodeValue;
    if (preg_match("/^R(\d+)$/", $id, $m)) $max = max($max, (int)$m[1]);
  }
  return "R" . str_pad((string)($max + 1), 2, "0", STR_PAD_LEFT);
}

function find_rec_by_id(DOMDocument $doc, string $id): ?DOMElement {
  $n = (new DOMXPath($doc))->query("//recensione[@id='$id']")->item(0);
  return $n instanceof DOMElement ? $n : null;
}

$prodotti = load_catalogo($XML_CAT);
$prodById = array_column($prodotti, "nome", "id");

$msg = "";
$doc = ensure_recensioni_xml($XML_REC);
$root = $doc->documentElement;

$action = $_POST["action"] ?? "";

if ($action === "add") {
  if ($role === "visitatore") { header("Location: login.php"); exit; }

  $id_smalto = trim((string)($_POST["id_smalto"] ?? ""));
  $titolo = trim((string)($_POST["titolo"] ?? ""));
  $testo = trim((string)($_POST["testo"] ?? ""));
  $voto = (int)($_POST["voto"] ?? 0);

  if ($id_smalto === "" || $titolo === "" || $testo === "" || $voto < 1 || $voto > 5) {
    $msg = "Compila tutti i campi (voto 1..5).";
  } else {
    $id = next_rec_id($doc);
    $r = $doc->createElement("recensione");
    $r->setAttribute("id", $id);
    $r->setAttribute("id_smalto", $id_smalto);
    $r->setAttribute("username", $username);

    set_child_text($doc, $r, "titolo", $titolo);
    set_child_text($doc, $r, "testo", $testo);
    set_child_text($doc, $r, "voto", (string)$voto);
    set_child_text($doc, $r, "utilita", "0");
    set_child_text($doc, $r, "supporto", "0");
    set_child_text($doc, $r, "data", date("Y-m-d"));

    $root->appendChild($r);
    $doc->save($XML_REC);

    $msg = "Recensione inserita ($id).";
    $id_smalto_filter = $id_smalto;
  }
} elseif ($action === "vote") {
  if ($role === "visitatore") { header("Location: login.php"); exit; }

  $id = (string)($_POST["id"] ?? "");
  $kind = (string)($_POST["kind"] ?? "");

  if ($id !== "" && ($kind === "utilita" || $kind === "supporto")) {
    $_SESSION["rec_vote"] ??= [];
    $key = $id . ":" . $kind;

    if (!isset($_SESSION["rec_vote"][$key])) {
      $r = find_rec_by_id($doc, $id);
      if ($r) {
        $author = $r->getAttribute("username");
        if ($author !== $username) {
          $curr = (int)get_child_text($r, $kind, "0");
          set_child_text($doc, $r, $kind, (string)($curr + 1));

          if ($kind === "utilita" && first_child_el($r, "supporto") === null) set_child_text($doc, $r, "supporto", "0");
          if ($kind === "supporto" && first_child_el($r, "utilita") === null) set_child_text($doc, $r, "utilita", "0");

          $doc->save($XML_REC);
          $_SESSION["rec_vote"][$key] = true;
        }
      }
    }
  }
}

$lista = [];
foreach ($root->getElementsByTagName("recensione") as $r) {
  if (!($r instanceof DOMElement)) continue;

  $id_smalto = $r->getAttribute("id_smalto");
  if ($id_smalto_filter !== "" && $id_smalto !== $id_smalto_filter) continue;

  $lista[] = [
    "id" => $r->getAttribute("id"),
    "id_smalto" => $id_smalto,
    "nome_smalto" => $prodById[$id_smalto] ?? $id_smalto,
    "username" => $r->getAttribute("username"),
    "titolo" => get_child_text($r, "titolo"),
    "testo" => get_child_text($r, "testo"),
    "voto" => get_child_text($r, "voto"),
    "utilita" => get_child_text($r, "utilita", "0"),
    "supporto" => get_child_text($r, "supporto", "0"),
    "data" => get_child_text($r, "data"),
  ];
}

page_header("FD NAILS - Recensioni", "recensioni");
?>

<div class="page-title">
  <h1>Recensioni<?= ($id_smalto_filter !== "" ? " — " . htmlspecialchars($prodById[$id_smalto_filter] ?? $id_smalto_filter) : "") ?></h1>
</div>

<?php if ($msg !== ""): ?>
  <div style="margin: 15px 0; padding: 10px; border: 1px solid #ccc; border-radius:18px;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<?php if ($role !== "visitatore"): ?>
<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h2 style="margin-top:0;">Scrivi una recensione</h2>

  <form method="post" action="recensioni.php<?= ($id_smalto_filter !== "" ? "?id_smalto=" . urlencode($id_smalto_filter) : "") ?>">
    <input type="hidden" name="action" value="add">

    <div class="filter-row">
      <label>Prodotto</label>
      <select name="id_smalto">
        <?php foreach ($prodotti as $p): ?>
          <option value="<?= htmlspecialchars($p["id"]) ?>" <?= ($id_smalto_filter === (string)$p["id"] ? "selected" : "") ?>>
            <?= htmlspecialchars($p["nome"]) ?> (<?= htmlspecialchars($p["id"]) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label>Titolo</label>
      <input type="text" name="titolo" style="width:100%;">
    </div>

    <div class="form-row">
      <label>Testo</label>
      <input type="text" name="testo" style="width:100%;">
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
<?php endif; ?>

<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h2 style="margin-top:0;">Elenco recensioni</h2>

  <?php if (!$lista): ?>
    <p>Nessuna recensione.</p>
  <?php else: ?>
    <?php foreach ($lista as $r): ?>
      <div style="padding:12px 0; border-top:1px solid #eee;">
        <p style="margin:0 0 6px 0;">
          <b><?= htmlspecialchars($r["nome_smalto"]) ?></b>
          <span style="color:#777;">(<?= htmlspecialchars($r["id_smalto"]) ?>)</span>
          — voto <b><?= htmlspecialchars($r["voto"]) ?>/5</b>
        </p>
        <p style="margin:0 0 6px 0;"><b><?= htmlspecialchars($r["titolo"]) ?></b></p>
        <p style="margin:0 0 8px 0;"><?= htmlspecialchars($r["testo"]) ?></p>
        <p style="margin:0; color:#777;">
          di <?= htmlspecialchars($r["username"]) ?> — <?= htmlspecialchars($r["data"]) ?>
        </p>
        <p style="margin:6px 0 0 0; color:#777;">
          utilità: <b><?= htmlspecialchars($r["utilita"]) ?></b> — supporto: <b><?= htmlspecialchars($r["supporto"]) ?></b>
        </p>

        <?php if ($role !== "visitatore" && $r["username"] !== $username): ?>
          <form method="post" action="recensioni.php<?= ($id_smalto_filter !== "" ? "?id_smalto=" . urlencode($id_smalto_filter) : "") ?>" style="margin-top:8px; display:inline;">
            <input type="hidden" name="action" value="vote">
            <input type="hidden" name="id" value="<?= htmlspecialchars($r["id"]) ?>">
            <input type="hidden" name="kind" value="utilita">
            <input class="btn btn-secondary" type="submit" value="Utilità +1">
          </form>

          <form method="post" action="recensioni.php<?= ($id_smalto_filter !== "" ? "?id_smalto=" . urlencode($id_smalto_filter) : "") ?>" style="margin-top:8px; display:inline;">
            <input type="hidden" name="action" value="vote">
            <input type="hidden" name="id" value="<?= htmlspecialchars($r["id"]) ?>">
            <input type="hidden" name="kind" value="supporto">
            <input class="btn btn-secondary" type="submit" value="Supporto +1">
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php page_footer(); ?>