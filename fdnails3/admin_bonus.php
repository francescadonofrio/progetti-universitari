<?php
session_start();
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/db.php";
require_role("amministratore");

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($conn)) {
  $action = $_POST["action"] ?? "";
  $username = trim((string)($_POST["username"] ?? ""));
  $crediti = (float)($_POST["crediti"] ?? 0);

  if ($username === "") {
    $msg = "Inserisci uno username.";
  } else {
    $stmt = mysqli_prepare($conn, "SELECT id, crediti FROM utenti WHERE username=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $utente = ($res = mysqli_stmt_get_result($stmt)) ? mysqli_fetch_assoc($res) : null;

    if (!$utente) {
      $msg = "Utente non trovato.";
    } else {
      if ($action === "add") {
        $nuovi_crediti = (float)$utente["crediti"] + max(0, $crediti);
      } elseif ($action === "set") {
        $nuovi_crediti = max(0, $crediti);
      } elseif ($action === "delete") {
        $nuovi_crediti = 0;
      } else {
        $nuovi_crediti = (float)$utente["crediti"];
      }

      $stmt = mysqli_prepare($conn, "UPDATE utenti SET crediti=? WHERE username=?");
      mysqli_stmt_bind_param($stmt, "ds", $nuovi_crediti, $username);
      mysqli_stmt_execute($stmt);
      $msg = "Crediti aggiornati.";
    }
  }
}

$users = [];
if (isset($conn)) {
  $res = mysqli_query($conn, "SELECT username, crediti FROM utenti ORDER BY username ASC");
  $users = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

page_header("FD NAILS - Gestione bonus", "admin_bonus");
?>

<div class="page-title"><h1>Gestione bonus</h1></div>

<?php if ($msg !== ""): ?>
  <div style="padding:10px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h3>Aggiungi crediti</h3>
  <form method="post" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
    <input type="hidden" name="action" value="add">
    <div>
      <label>Username</label><br>
      <input type="text" name="username" required>
    </div>
    <div>
      <label>Crediti</label><br>
      <input type="number" name="crediti" min="0" step="0.01" required>
    </div>
    <div>
      <button class="btn btn-primary" type="submit">Aggiungi</button>
    </div>
  </form>
</div>

<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h3>Imposta saldo crediti</h3>
  <form method="post" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
    <input type="hidden" name="action" value="set">
    <div>
      <label>Username</label><br>
      <input type="text" name="username" required>
    </div>
    <div>
      <label>Crediti</label><br>
      <input type="number" name="crediti" min="0" step="0.01" required>
    </div>
    <div>
      <button class="btn btn-primary" type="submit">Salva</button>
    </div>
  </form>
</div>

<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h3>Azzera crediti</h3>
  <form method="post" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
    <input type="hidden" name="action" value="delete">
    <div>
      <label>Username</label><br>
      <input type="text" name="username" required>
    </div>
    <div>
      <button class="btn btn-secondary" type="submit">Azzera</button>
    </div>
  </form>
</div>

<div style="padding:20px; border:1px solid #ccc; border-radius:18px; margin:15px 0;">
  <h3>Elenco utenti</h3>
  <?php if (!$users): ?>
    <p>Nessun utente.</p>
  <?php else: ?>
    <table cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse;">
      <tr style="background:#fafafa;">
        <th style="text-align:left; border-bottom:1px solid #eee;">Username</th>
        <th style="text-align:left; border-bottom:1px solid #eee;">Crediti</th>
      </tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td style="border-bottom:1px solid #f0f0f0;"><?= htmlspecialchars($u["username"]) ?></td>
          <td style="border-bottom:1px solid #f0f0f0;"><?= number_format((float)$u["crediti"], 2, ",", ".") ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<?php page_footer(); ?>