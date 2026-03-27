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

$prodotti = load_catalogo(__DIR__ . "/xml/catalogo.xml");
$byId = array_column($prodotti, null, "id");

$carrello = isset($_SESSION["carrello"]) && is_array($_SESSION["carrello"]) ? $_SESSION["carrello"] : [];
$righe = [];
$totale = 0.0;
$totale_listino = 0.0;
$totale_sconto = 0.0;

$hasPromo = function_exists("promo_discount_percent_for") && function_exists("promo_apply_discount");

foreach ($carrello as $id => $qty) {
  $p = isset($byId[$id]) ? $byId[$id] : null;
  if (!$p) continue;

  $prezzo = (float)$p["prezzo"];
  $qty = (int)$qty;

  $perc = 0;
  $prezzo_s = $prezzo;

  if ($hasPromo) {
    $perc = promo_discount_percent_for($role, (string)$id, $today, $PROMO_XML);
    $prezzo_s = promo_apply_discount($prezzo, $perc);
  }

  $sub_listino = $prezzo * $qty;
  $sub = $prezzo_s * $qty;

  $totale_listino += $sub_listino;
  $totale += $sub;
  $totale_sconto += ($sub_listino - $sub);

  $righe[] = [
    "id" => (string)$id,
    "p" => $p,
    "qty" => $qty,
    "sub" => $sub,
    "perc" => $perc,
    "prezzo_s" => $prezzo_s
  ];
}

page_header("FD NAILS - Carrello", "carrello");
?>

<div class="page-title"><h1>Carrello</h1></div>

<?php if (!$righe): ?>
  <p style="padding:20px;">Carrello vuoto.</p>
  <p style="padding:0 20px 20px;"><a href="catalogo.php">Torna al catalogo</a></p>
<?php else: ?>

  <div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
    <div style="overflow:auto;">
      <table cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse;">
        <tr style="background:#fafafa;">
          <th style="text-align:left; border-bottom:1px solid #eee;">Prodotto</th>
          <th style="text-align:center; border-bottom:1px solid #eee;">Qta</th>
          <th style="text-align:left; border-bottom:1px solid #eee;">Prezzo</th>
          <th style="text-align:left; border-bottom:1px solid #eee;">Subtotale</th>
          <th style="text-align:left; border-bottom:1px solid #eee; width:160px;">Azioni</th>
        </tr>

        <?php foreach ($righe as $r): ?>
          <tr>
            <td style="border-bottom:1px solid #f0f0f0;"><?= htmlspecialchars($r["p"]["nome"]) ?></td>
            <td style="border-bottom:1px solid #f0f0f0; text-align:center;"><?= (int)$r["qty"] ?></td>
            <td style="border-bottom:1px solid #f0f0f0;">
              <?php if ($r["perc"] > 0): ?>
                <span style="text-decoration:line-through; color:#777;">
                  <?= number_format((float)$r["p"]["prezzo"], 2, ",", ".") ?> crediti
                </span><br>
                <b><?= number_format((float)$r["prezzo_s"], 2, ",", ".") ?> crediti</b>
                <span style="color:#777;">(-<?= (int)$r["perc"] ?>%)</span>
              <?php else: ?>
                <?= number_format((float)$r["p"]["prezzo"], 2, ",", ".") ?> crediti
              <?php endif; ?>
            </td>
            <td style="border-bottom:1px solid #f0f0f0;"><?= number_format((float)$r["sub"], 2, ",", ".") ?> crediti</td>

            <td style="border-bottom:1px solid #f0f0f0; width:160px;">
              <div style="display:flex; gap:6px; align-items:center; justify-content:flex-start;">
                <form method="get" action="aggiungi_carrello.php" style="margin:0;">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($r["id"]) ?>">
                  <input class="btn btn-secondary" style="font-size:0.7rem; padding:0.25rem 0.45rem;" type="submit" value="+1">
                </form>

                <form method="post" action="rimuovi_carrello.php" style="margin:0;">
                  <input type="hidden" name="action" value="dec">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($r["id"]) ?>">
                  <input class="btn btn-secondary" style="font-size:0.7rem; padding:0.25rem 0.45rem;" type="submit" value="-1">
                </form>

                <form method="post" action="rimuovi_carrello.php" style="margin:0;">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($r["id"]) ?>">
                  <input class="btn btn-secondary" style="font-size:0.7rem; padding:0.25rem 0.45rem;" type="submit" value="X">
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if ($totale_sconto > 0.0001): ?>
          <tr>
            <td colspan="3" style="text-align:right; padding-top:14px; border-top:1px solid #eee;">Totale listino</td>
            <td colspan="2" style="padding-top:14px; border-top:1px solid #eee;"><?= number_format((float)$totale_listino, 2, ",", ".") ?> crediti</td>
          </tr>
          <tr>
            <td colspan="3" style="text-align:right;">Sconto</td>
            <td colspan="2">-<?= number_format((float)$totale_sconto, 2, ",", ".") ?> crediti</td>
          </tr>
        <?php endif; ?>

        <tr>
          <td colspan="3" style="text-align:right; padding-top:14px; border-top:1px solid #eee;"><b>Totale</b></td>
          <td colspan="2" style="padding-top:14px; border-top:1px solid #eee;"><b><?= number_format((float)$totale, 2, ",", ".") ?> crediti</b></td>
        </tr>
      </table>
    </div>

    <p style="margin-top:15px;">
      <a class="btn btn-primary" href="checkout.php">Conferma acquisto</a>
      &nbsp;&nbsp;
      <a href="catalogo.php">Continua shopping</a>
    </p>

    <form method="post" action="rimuovi_carrello.php" style="margin-top:10px;">
      <input type="hidden" name="action" value="svuota">
      <input class="btn btn-secondary" type="submit" value="Svuota carrello">
    </form>
  </div>

<?php endif; ?>

<?php page_footer(); ?>