<?php
session_start();
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/xml.php";

if (current_role() === "visitatore") { header("Location: login.php"); exit; }

$username = $_SESSION["username"] ?? "";
$XML_A = __DIR__ . "/xml/acquisti.xml";
$XML_CAT = __DIR__ . "/xml/catalogo.xml";

$prodotti = load_catalogo($XML_CAT);
$prodById = array_column($prodotti, null, "id");

$acquisti = [];

if (is_file($XML_A)) {
  $doc = new DOMDocument();
  $doc->load($XML_A, LIBXML_NONET);
  $xp = new DOMXPath($doc);

  foreach ($xp->query("//acquisto[@utente='$username' or @username='$username']") as $a) {
    if (!($a instanceof DOMElement)) continue;

    $idA = $a->getAttribute("id");
    $data = $a->getAttribute("data") ?: trim((string)$a->getElementsByTagName("data")->item(0)?->textContent);
    $totale = $a->getAttribute("totale") ?: trim((string)$a->getElementsByTagName("totale")->item(0)?->textContent);

    $items = [];

    foreach ($a->getElementsByTagName("prodotto") as $pr) {
      if (!($pr instanceof DOMElement)) continue;
      $items[] = [
        "id" => $pr->getAttribute("id_smalto"),
        "qty" => (int)$pr->getAttribute("quantita"),
        "prezzo" => (float)$pr->getAttribute("prezzo_unitario"),
      ];
    }

    foreach ($a->getElementsByTagName("item") as $it) {
      if (!($it instanceof DOMElement)) continue;
      $items[] = [
        "id" => $it->getAttribute("smalto_id"),
        "qty" => (int)$it->getAttribute("qty"),
        "prezzo" => (float)$it->getAttribute("prezzo"),
      ];
    }

    $acquisti[] = ["id" => $idA, "data" => $data, "totale" => $totale, "items" => $items];
  }
}

page_header("FD NAILS - Acquisti", "acquisti");
?>

<div class="page-title"><h1>I miei acquisti</h1></div>

<div style="padding:20px;">
  <?php if (!$acquisti): ?>
    <p>Nessun acquisto ancora.</p>
  <?php else: ?>

    <?php foreach ($acquisti as $a): ?>
      <div style="border:1px solid #ccc; padding:12px 14px; margin-bottom:14px;">
        <p style="margin:0 0 6px 0;"><b>Acquisto <?= htmlspecialchars($a["id"]) ?></b></p>
        <p style="margin:0 0 6px 0; color:#555;">Data: <?= htmlspecialchars($a["data"]) ?></p>
        <p style="margin:0 0 10px 0; color:#555;">Totale: <b><?= number_format((float)$a["totale"], 2, ",", ".") ?> €</b></p>

        <?php if (!$a["items"]): ?>
          <p style="margin:0; color:#777;">(nessun dettaglio prodotti)</p>
        <?php else: ?>
          <table border="1" cellpadding="6" cellspacing="0">
            <tr>
              <th>Prodotto</th><th>ID</th><th>Qta</th><th>Prezzo unit.</th>
            </tr>
            <?php foreach ($a["items"] as $it): ?>
              <?php $nome = $prodById[$it["id"]]["nome"] ?? $it["id"]; ?>
              <tr>
                <td><?= htmlspecialchars($nome) ?></td>
                <td><?= htmlspecialchars($it["id"]) ?></td>
                <td><?= (int)$it["qty"] ?></td>
                <td><?= number_format((float)$it["prezzo"], 2, ",", ".") ?> €</td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<?php page_footer(); ?>