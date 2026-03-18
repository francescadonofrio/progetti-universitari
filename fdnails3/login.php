<?php
session_start();
require_once __DIR__ . "/php/db.php";
require_once __DIR__ . "/php/layout.php";

$errore = "";
$username = (string)($_POST["username"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $password = (string)($_POST["password"] ?? "");

  $q = mysqli_prepare($conn, "SELECT username, tipo_utente, password FROM utenti WHERE username=? AND ban=0");
  mysqli_stmt_bind_param($q, "s", $username);
  mysqli_stmt_execute($q);
  $row = ($res = mysqli_stmt_get_result($q)) ? mysqli_fetch_assoc($res) : null;

  if ($row && password_verify($password, $row["password"])) {
    $_SESSION["username"] = $row["username"];
    $_SESSION["tipo_utente"] = $row["tipo_utente"];
    header("Location: index.php");
    exit;
  }

  $errore = "Credenziali non valide";
}

page_header("FD NAILS - Login", "login");
?>

<div class="page-title">
  <h1>Area Clienti</h1>
</div>

<div id="login-layout" class="auth-single">
  <div id="login-box">

    <h2>Login</h2>

    <?php if ($errore !== ""): ?>
      <div style="margin: 0 0 1rem 0; padding: .75rem 1rem; border: 1px solid #c00; border-radius: 8px; background: #fff5f5;">
        <strong style="color:#c00;"><?= htmlspecialchars($errore) ?></strong>
      </div>
    <?php endif; ?>

    <form method="post">
      <div class="form-row">
        <label>Username</label>
        <input name="username" required value="<?= htmlspecialchars($username) ?>">
      </div>

      <div class="form-row">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>

      <div style="display:flex; gap:.5rem; margin-top:.75rem; flex-wrap:wrap;">
        <button class="btn btn-primary" type="submit">Accedi</button>
        <a class="btn btn-secondary" href="register.php">Registrati</a>
      </div>
    </form>

  </div>
</div>

<?php page_footer(); ?>