<?php
session_start();
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/db.php";

if (current_role() === "visitatore") { header("Location: login.php"); exit; }

$username = $_SESSION["username"] ?? "";

$msg = "";
$user = [
  "nome" => "",
  "cognome" => "",
  "email" => "",
  "crediti" => 0,
  "reputazione" => "",
  "data_registrazione" => ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($conn)) {
  $nome = trim($_POST["nome"] ?? "");
  $cognome = trim($_POST["cognome"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $vecchiaPassword = trim($_POST["vecchiaPassword"] ?? "");
  $nuovaPassword = trim($_POST["nuovaPassword"] ?? "");

  if ($nome === "" || $cognome === "" || $email === "") {
    $msg = "Compila nome, cognome ed email.";
  } else {
    $stmt = mysqli_prepare($conn, "SELECT password FROM utenti WHERE username=?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $row = ($res = mysqli_stmt_get_result($stmt)) ? mysqli_fetch_assoc($res) : null;

    if ($nuovaPassword !== "") {
      if (!$row || !password_verify($vecchiaPassword, $row["password"])) {
        $msg = "Password vecchia errata.";
      } else {
        $hash = password_hash($nuovaPassword, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE utenti SET nome=?, cognome=?, email=?, password=? WHERE username=?");
        mysqli_stmt_bind_param($stmt, "sssss", $nome, $cognome, $email, $hash, $username);
        mysqli_stmt_execute($stmt);
        $msg = "Dati aggiornati.";
      }
    } else {
      $stmt = mysqli_prepare($conn, "UPDATE utenti SET nome=?, cognome=?, email=? WHERE username=?");
      mysqli_stmt_bind_param($stmt, "ssss", $nome, $cognome, $email, $username);
      mysqli_stmt_execute($stmt);
      $msg = "Dati aggiornati.";
    }
  }
}

if (isset($conn)) {
  $stmt = mysqli_prepare($conn, "SELECT nome, cognome, email, crediti, reputazione, data_registrazione FROM utenti WHERE username=? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "s", $username);
  mysqli_stmt_execute($stmt);
  $row = ($res = mysqli_stmt_get_result($stmt)) ? mysqli_fetch_assoc($res) : null;
  if ($row) $user = $row;
}

page_header("FD NAILS - Area personale", "area");
?>

<div class="page-title"><h1>Area personale</h1></div>

<?php if ($msg !== ""): ?>
  <div style="padding:10px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <p>Benvenuta/o, <b><?= htmlspecialchars($username) ?></b></p>

  <form method="post">
    <div class="form-row">
      <label>Nome</label>
      <input type="text" name="nome" value="<?= htmlspecialchars($user["nome"]) ?>" required>
    </div>

    <div class="form-row">
      <label>Cognome</label>
      <input type="text" name="cognome" value="<?= htmlspecialchars($user["cognome"]) ?>" required>
    </div>

    <div class="form-row">
      <label>Email</label>
      <input type="email" name="email" value="<?= htmlspecialchars($user["email"]) ?>" required>
    </div>

    <div class="form-row">
      <label>Vecchia password</label>
      <input type="password" name="vecchiaPassword">
    </div>

    <div class="form-row">
      <label>Nuova password (opzionale)</label>
      <input type="password" name="nuovaPassword">
    </div>

    <p><b>Crediti disponibili:</b> <?= number_format((float)$user["crediti"], 2, ",", ".") ?></p>
    <p><b>Reputazione:</b> <?= htmlspecialchars((string)$user["reputazione"]) ?></p>
    <p><b>Registrazione:</b> <?= htmlspecialchars((string)$user["data_registrazione"]) ?></p>

    <div style="margin-top:10px;">
      <button class="btn btn-primary" type="submit">Salva</button>
    </div>
  </form>
</div>

<?php page_footer(); ?>