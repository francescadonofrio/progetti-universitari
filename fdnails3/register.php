<?php
require_once __DIR__ . "/php/db.php";
require_once __DIR__ . "/php/layout.php";

$errore = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nome = trim((string)($_POST["nome"] ?? ""));
  $cognome = trim((string)($_POST["cognome"] ?? ""));
  $username = trim((string)($_POST["username"] ?? ""));
  $email = trim((string)($_POST["email"] ?? ""));
  $password_raw = (string)($_POST["password"] ?? "");

  $checks = [
    [$nome !== "" && $cognome !== "" && $username !== "" && $email !== "" && $password_raw !== "", "Compila tutti i campi."],
    [filter_var($email, FILTER_VALIDATE_EMAIL) !== false, "Email non valida."],
    [strlen($username) >= 3, "Username troppo corto (min 3 caratteri)."],
    [strlen($password_raw) >= 6, "Password troppo corta (min 6 caratteri)."],
  ];

  foreach ($checks as [$ok, $msg]) {
    if (!$ok) { $errore = $msg; break; }
  }

  if ($errore === "") {
    $chk = mysqli_prepare($conn, "SELECT 1 FROM utenti WHERE username=? OR email=? LIMIT 1");
    mysqli_stmt_bind_param($chk, "ss", $username, $email);
    mysqli_stmt_execute($chk);
    $res = mysqli_stmt_get_result($chk);

    if ($res && mysqli_fetch_assoc($res)) {
      $errore = "Username o email già utilizzati.";
    } else {
      $password = password_hash($password_raw, PASSWORD_DEFAULT);

      $q = mysqli_prepare(
        $conn,
        "INSERT INTO utenti (nome,cognome,username,email,password,tipo_utente,data_registrazione,ban,crediti,reputazione)
         VALUES (?,?,?,?,?,'registrato',NOW(),0,0,0)"
      );
      mysqli_stmt_bind_param($q, "sssss", $nome, $cognome, $username, $email, $password);

      if (mysqli_stmt_execute($q)) {
        header("Location: login.php");
        exit;
      }
      $errore = "Errore durante la registrazione.";
    }
  }
}

page_header("FD NAILS - Registrazione", "register");
?>

<div class="page-title">
  <h1>Registrazione</h1>
</div>

<div id="login-layout" class="auth-single">
  <div id="register-box">

    <h2>Crea account</h2>

    <?php if ($errore !== ""): ?>
      <div class="auth-error">
        <strong><?= htmlspecialchars($errore) ?></strong>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="form-row">
        <label for="nome">Nome</label>
        <input id="nome" name="nome" required value="<?= htmlspecialchars((string)($_POST["nome"] ?? "")) ?>">
      </div>

      <div class="form-row">
        <label for="cognome">Cognome</label>
        <input id="cognome" name="cognome" required value="<?= htmlspecialchars((string)($_POST["cognome"] ?? "")) ?>">
      </div>

      <div class="form-row">
        <label for="username">Username</label>
        <input id="username" name="username" required value="<?= htmlspecialchars((string)($_POST["username"] ?? "")) ?>">
      </div>

      <div class="form-row">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars((string)($_POST["email"] ?? "")) ?>">
      </div>

      <div class="form-row">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
      </div>

      <div class="auth-actions">
        <button class="btn btn-primary" type="submit">Registrati</button>
        <a class="btn btn-secondary" href="login.php">Torna al login</a>
      </div>
    </form>

  </div>
</div>

<?php page_footer(); ?>