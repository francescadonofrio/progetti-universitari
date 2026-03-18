<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

function page_header(string $title, string $current = ""): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
  $role = current_role();
  $is = fn(string $k): string => $current === $k ? "current" : "";
?>
<!doctype html>
<html lang="it">
<head>
  <title><?= htmlspecialchars($title) ?></title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="css/style.css">
</head>
<body>
<div id="page">
  <div id="header">
    <div id="brand">
      <div id="logo-mark">
        <img src="media/logo.jpg" alt="Logo FD NAILS">
      </div>
      <div id="brand-text">
        <span class="brand-name">FD NAILS</span>
      </div>
    </div>
    <div id="main-nav">
      <ul>
        <li><a class="<?= $is("home") ?>" href="index.php">Home</a></li>
        <li><a class="<?= $is("catalogo") ?>" href="catalogo.php">Catalogo</a></li>
        <li><a class="<?= $is("faq") ?>" href="faq.php">FAQ</a></li>
        <?php if ($role === "visitatore"): ?>
          <li><a class="<?= $is("register") ?>" href="register.php">Registrazione</a></li>
          <li><a class="<?= $is("login") ?>" href="login.php">Area clienti</a></li>
        <?php elseif ($role === "registrato"): ?>
          <li><a class="<?= $is("area") ?>" href="area_personale.php">Area personale</a></li>
          <li><a class="<?= $is("acquisti") ?>" href="acquisti.php">Acquisti</a></li>
          <li><a class="<?= $is("carrello") ?>" href="carrello.php">Carrello</a></li>
          <li><a href="logout.php">Logout</a></li>
        <?php elseif ($role === "amministratore"): ?>
          <li><a class="<?= $is("admin_utenti") ?>" href="admin_utenti.php">Gestione utenti</a></li>
          <li><a class="<?= $is("admin_promo") ?>" href="admin_promo.php">Sconti/Bonus</a></li>
          <li><a href="logout.php">Logout</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <div id="content">
<?php
}

function page_footer(): void {
?>
  </div>
  <div id="footer">
    <p>&copy; 2026 FD NAILS</p>
  </div>
</div>
</body>
</html>
<?php
}