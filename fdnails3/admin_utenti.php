<?php
session_start();
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/layout.php";
require_once __DIR__ . "/php/db.php";
require_role("amministratore");

$msg = "";
$action = $_POST["action"] ?? "";
$id = (int)($_POST["id"] ?? 0);
$self_username = $_SESSION["username"] ?? "";

if ($action !== "" && $id > 0) {
  if ($action === "toggle_ban") {
    $stmt = mysqli_prepare($conn, "SELECT username, ban FROM utenti WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($row && $row["username"] !== $self_username) {
      $newBan = $row["ban"] ? 0 : 1;
      $stmt2 = mysqli_prepare($conn, "UPDATE utenti SET ban=? WHERE id=?");
      mysqli_stmt_bind_param($stmt2, "ii", $newBan, $id);
      mysqli_stmt_execute($stmt2);
      $msg = $newBan ? "Utente bannato." : "Ban rimosso.";
    } else {
      $msg = "Operazione non consentita.";
    }
  } elseif ($action === "set_role") {
    $role = $_POST["role"] ?? "registrato";
    if ($role !== "registrato" && $role !== "amministratore") $role = "registrato";

    $stmt = mysqli_prepare($conn, "SELECT username FROM utenti WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($row && $row["username"] !== $self_username) {
      $stmt2 = mysqli_prepare($conn, "UPDATE utenti SET tipo_utente=? WHERE id=?");
      mysqli_stmt_bind_param($stmt2, "si", $role, $id);
      mysqli_stmt_execute($stmt2);
      $msg = "Ruolo aggiornato.";
    } else {
      $msg = "Operazione non consentita.";
    }
  } elseif ($action === "update_anagrafica") {
    $nome = trim((string)($_POST["nome"] ?? ""));
    $cognome = trim((string)($_POST["cognome"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));

    $stmt = mysqli_prepare($conn, "SELECT username FROM utenti WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$row || $row["username"] === $self_username) {
      $msg = "Operazione non consentita.";
    } elseif ($nome === "" || $cognome === "" || $email === "") {
      $msg = "Compila tutti i campi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $msg = "Email non valida.";
    } else {
      $stmt2 = mysqli_prepare($conn, "UPDATE utenti SET nome=?, cognome=?, email=? WHERE id=?");
      mysqli_stmt_bind_param($stmt2, "sssi", $nome, $cognome, $email, $id);
      mysqli_stmt_execute($stmt2);
      $msg = "Dati anagrafici aggiornati.";
    }
  }
}

$users = [];
$res = mysqli_query($conn, "SELECT id, nome, cognome, username, email, tipo_utente, crediti, reputazione, data_registrazione, ban FROM utenti ORDER BY data_registrazione DESC");
$users = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

page_header("FD NAILS - Gestione utenti", "admin_utenti");
?>
<div class="page-title"><h1>Gestione utenti</h1></div>

<?php if ($msg !== ""): ?>
  <div style="margin: 15px 0; padding: 10px; border: 1px solid #ccc;">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<div style="padding:20px; border: 1px solid #ccc; margin: 15px 0; overflow-x:auto;">
  <?php if (!$users): ?>
    <p>Nessun utente.</p>
  <?php else: ?>
    <table style="width:100%; border-collapse:collapse;">
      <tr>
        <th style="text-align:left; border-bottom:1px solid #eee; padding:8px;">Username</th>
        <th style="text-align:left; border-bottom:1px solid #eee; padding:8px;">Nome</th>
        <th style="text-align:left; border-bottom:1px solid #eee; padding:8px;">Email</th>
        <th style="text-align:left; border-bottom:1px solid #eee; padding:8px;">Ruolo</th>
        <th style="text-align:left; border-bottom:1px solid #eee; padding:8px;">Crediti</th>
        <th style="text-align:left; border-bottom:1px solid #eee; padding:8px;">Reputazione</th>
        <th style="text-align:left; border-bottom:1px solid #eee; padding:8px;">Ban</th>
        <th style="text-align:left; border-bottom:1px solid #eee; padding:8px;">Azioni</th>
      </tr>

      <?php foreach ($users as $u): ?>
        <tr>
          <td style="padding:8px; border-bottom:1px solid #f2f2f2;">
            <?= htmlspecialchars($u["username"]) ?>
            <?php if ($u["username"] === $self_username): ?>
              <span style="color:#777;">(tu)</span>
            <?php endif; ?>
          </td>
          <td style="padding:8px; border-bottom:1px solid #f2f2f2;"><?= htmlspecialchars($u["nome"] . " " . $u["cognome"]) ?></td>
          <td style="padding:8px; border-bottom:1px solid #f2f2f2;"><?= htmlspecialchars($u["email"]) ?></td>
          <td style="padding:8px; border-bottom:1px solid #f2f2f2;"><?= htmlspecialchars($u["tipo_utente"]) ?></td>
          <td style="padding:8px; border-bottom:1px solid #f2f2f2;"><?= (int)$u["crediti"] ?></td>
          <td style="padding:8px; border-bottom:1px solid #f2f2f2;"><?= (int)$u["reputazione"] ?></td>
          <td style="padding:8px; border-bottom:1px solid #f2f2f2;"><?= $u["ban"] ? "Sì" : "No" ?></td>
          <td style="padding:8px; border-bottom:1px solid #f2f2f2; white-space:nowrap;">

            <form method="post" action="admin_utenti.php" style="display:inline;">
              <input type="hidden" name="action" value="toggle_ban">
              <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">
              <input class="btn btn-secondary" type="submit" value="<?= $u["ban"] ? "Unban" : "Ban" ?>" <?= ($u["username"] === $self_username) ? "disabled" : "" ?>>
            </form>

            <form method="post" action="admin_utenti.php" style="display:inline; margin-left:6px;">
              <input type="hidden" name="action" value="set_role">
              <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">
              <select name="role" style="padding:6px; border:1px solid #ccc; border-radius:6px;" <?= ($u["username"] === $self_username) ? "disabled" : "" ?>>
                <option value="registrato" <?= ($u["tipo_utente"] === "registrato") ? "selected" : "" ?>>registrato</option>
                <option value="amministratore" <?= ($u["tipo_utente"] === "amministratore") ? "selected" : "" ?>>amministratore</option>
              </select>
              <input class="btn btn-secondary" type="submit" value="Salva" <?= ($u["username"] === $self_username) ? "disabled" : "" ?>>
            </form>

            <details style="display:inline; margin-left:10px;" <?= ($u["username"] === $self_username) ? "title=\"Operazione non consentita\"" : "" ?>>
              <summary style="cursor:pointer; display:inline-block; color:#444; <?= ($u["username"] === $self_username) ? "opacity:.5; pointer-events:none;" : "" ?>">
                Modifica dati
              </summary>

              <form method="post" action="admin_utenti.php" style="margin-top:8px;">
                <input type="hidden" name="action" value="update_anagrafica">
                <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">

                <div style="display:flex; gap:6px; flex-wrap:wrap; margin:6px 0;">
                  <input type="text" name="nome" value="<?= htmlspecialchars($u["nome"]) ?>" placeholder="Nome" style="padding:6px; border:1px solid #ccc; border-radius:6px;">
                  <input type="text" name="cognome" value="<?= htmlspecialchars($u["cognome"]) ?>" placeholder="Cognome" style="padding:6px; border:1px solid #ccc; border-radius:6px;">
                  <input type="email" name="email" value="<?= htmlspecialchars($u["email"]) ?>" placeholder="Email" style="padding:6px; border:1px solid #ccc; border-radius:6px; min-width:220px;">
                  <input class="btn btn-secondary" type="submit" value="Aggiorna" <?= ($u["username"] === $self_username) ? "disabled" : "" ?>>
                </div>
              </form>
            </details>

          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<?php page_footer(); ?>