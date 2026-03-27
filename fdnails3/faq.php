<?php
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/auth.php";

page_header("FD NAILS - FAQ", "faq");

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

function get_child_text(DOMElement $parent, string $name, string $default = ""): string {
  $el = first_child_element_by_name($parent, $name);
  return $el ? trim($el->textContent) : $default;
}

$doc = load_faq_dom($XML_PATH);
$root = $doc->documentElement;

$qna_list = [];
foreach ($root->getElementsByTagName("qna") as $qna) {
  if (!($qna instanceof DOMElement)) continue;
  $qna_list[] = [
    "id" => $qna->getAttribute("id"),
    "domanda" => get_child_text($qna, "domanda"),
    "risposta" => get_child_text($qna, "risposta"),
  ];
}

$role = current_role();
?>

<div class="page-title"><h1>FAQ</h1></div>

<?php if ($role === "amministratore"): ?>
  <div style="margin: 15px 0; padding: 10px; border: 1px solid #ccc;">
    <a href="admin_faq.php" style="font-weight: bold;">➜ Gestisci FAQ</a>
  </div>
<?php endif; ?>

<div style="padding:20px;">
  <?php if (!$qna_list): ?>
    <p>Nessuna FAQ disponibile.</p>
  <?php else: ?>
    <?php foreach ($qna_list as $r): ?>
      <div style="margin-bottom:18px;">
        <p style="margin:0 0 6px 0; font-weight:bold;">
          <?= htmlspecialchars($r["domanda"]) ?>
        </p>
        <p style="margin:0;">
          <?= htmlspecialchars($r["risposta"]) ?>
        </p>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php page_footer(); ?>