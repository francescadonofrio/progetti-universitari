<?php
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/xml.php";
require_once __DIR__ . "/php/auth.php";

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

function promo_load_counts(string $path, string $productId, string $today): array {
  $out = ["sconti" => 0, "bonus" => 0];

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

      $out["sconti"]++;
    }
  }

  $bonusNode = promo_first_child($root, "bonus");
  if ($bonusNode) {
    foreach ($bonusNode->getElementsByTagName("bonusterm") as $b) {
      if (!($b instanceof DOMElement)) continue;

      $dal = $b->getAttribute("dal");
      $al = $b->getAttribute("al");
      if ($dal === "" || $al === "" || $today < $dal || $today > $al) continue;

      $out["bonus"]++;
    }
  }

  return $out;
}

page_header("FD NAILS - Catalogo", "catalogo");

$role = current_role();
$prodotti = load_catalogo(__DIR__ . "/xml/catalogo.xml");
$today = date("Y-m-d");

$ricerca = trim((string)($_GET["ricerca"] ?? ""));
$prezzo_min_raw = (string)($_GET["prezzo_min"] ?? "");
$prezzo_max_raw = (string)($_GET["prezzo_max"] ?? "");

$prezzo_min = $prezzo_min_raw !== "" ? (float)$prezzo_min_raw : 0.0;
$prezzo_max = $prezzo_max_raw !== "" ? (float)$prezzo_max_raw : 0.0;

if ($prezzo_max > 0 && $prezzo_min > $prezzo_max) {
  [$prezzo_min, $prezzo_max] = [$prezzo_max, $prezzo_min];
  $prezzo_min_raw = (string)$prezzo_min;
  $prezzo_max_raw = (string)$prezzo_max;
}

$filtrati = array_values(array_filter($prodotti, function ($p) use ($ricerca, $prezzo_min, $prezzo_max) {
  $nome = (string)$p["nome"];
  $prezzo = (float)$p["prezzo"];
  if ($ricerca !== "" && stripos($nome, $ricerca) === false) return false;
  if ($prezzo_min > 0 && $prezzo < $prezzo_min) return false;
  if ($prezzo_max > 0 && $prezzo > $prezzo_max) return false;
  return true;
}));
?>

<div class="page-title">
  <h1>Catalogo</h1>
</div>

<?php if ($role === "amministratore"): ?>
  <div style="margin: 15px 0; padding: 10px; border: 1px solid #ccc;">
    <a href="admin_catalogo.php" style="font-weight: bold;">➜ Gestisci catalogo</a>
  </div>
<?php endif; ?>

<div id="filters">
  <form action="catalogo.php" method="get">
    <fieldset>
      <legend>Filtri</legend>

      <div class="filter-row">
        <label for="ricerca">Nome:</label>
        <input id="ricerca" type="text" name="ricerca" value="<?= htmlspecialchars($ricerca) ?>" />
      </div>

      <div class="filter-row">
        <label for="prezzo_min">Prezzo min:</label>
        <input id="prezzo_min" type="number" step="0.01" name="prezzo_min" value="<?= htmlspecialchars($prezzo_min_raw) ?>" />
      </div>

      <div class="filter-row">
        <label for="prezzo_max">Prezzo max:</label>
        <input id="prezzo_max" type="number" step="0.01" name="prezzo_max" value="<?= htmlspecialchars($prezzo_max_raw) ?>" />
      </div>

      <button class="btn btn-secondary" type="submit">Applica</button>
    </fieldset>
  </form>
</div>

<div class="product-grid">
<?php if (!$filtrati): ?>
  <p>Nessun prodotto trovato.</p>
<?php else: ?>
<?php foreach ($filtrati as $p): ?>
  <?php $promoCount = promo_load_counts(__DIR__ . "/xml/promozioni.xml", (string)$p["id"], $today); ?>
  <div class="product-card">

    <img class="product-img" src="media/<?= htmlspecialchars($p["immagine"]) ?>" alt="<?= htmlspecialchars($p["nome"]) ?>">

    <h3 class="game-title"><?= htmlspecialchars($p["nome"]) ?></h3>

    <p class="game-meta"><?= htmlspecialchars($p["descrizione"], ENT_QUOTES, "UTF-8") ?></p>

    <p class="game-price">
      <?= number_format((float)$p["prezzo"], 2, ",", ".") ?> €
    </p>

    <?php if ($promoCount["sconti"] > 0 || $promoCount["bonus"] > 0): ?>
      <p style="margin:8px 0 10px 0;">
        <?php if ($promoCount["sconti"] > 0): ?>
          <span style="display:inline-block; padding:6px 10px; border-radius:999px; font-weight:bold; font-size:.9rem; border:1px solid #c00; color:#c00; margin-right:6px;">
            Sconti attivi: <?= (int)$promoCount["sconti"] ?>
          </span>
        <?php endif; ?>

        <?php if ($promoCount["bonus"] > 0): ?>
          <span style="display:inline-block; padding:6px 10px; border-radius:999px; font-weight:bold; font-size:.9rem; border:1px solid #0a7; color:#0a7;">
            Bonus attivi: <?= (int)$promoCount["bonus"] ?>
          </span>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <p class="game-actions">
      <?php if ($role === "visitatore"): ?>
        <a class="btn btn-primary" href="login.php">Aggiungi al carrello</a>
      <?php elseif ($role === "amministratore"): ?>
        <span style="color:#777;">Acquisto non disponibile per amministratore.</span>
      <?php else: ?>
        <a class="btn btn-primary" href="aggiungi_carrello.php?id=<?= urlencode($p["id"]) ?>">Aggiungi al carrello</a>
      <?php endif; ?>

      <a class="btn btn-secondary" href="prodotto.php?id=<?= urlencode($p["id"]) ?>">Dettaglio / Recensioni</a>
    </p>

  </div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php page_footer(); ?>