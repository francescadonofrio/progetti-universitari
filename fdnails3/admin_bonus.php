<?php
session_start();
require_once __DIR__ . "/php/auth.php";
require_once __DIR__ . "/php/layout.php";

if (current_role() !== "amministratore") {
  header("Location: index.php");
  exit;
}

$XML_B = __DIR__ . "/xml/bonus.xml";

function load_bonus(string $path): DOMDocument {
  if (!file_exists($path)) {
    $doc = new DOMDocument("1.0","UTF-8");
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $root = $doc->createElement("bonus");
    $doc->appendChild($root);
    $doc->save($path);
  }
  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $doc->load($path, LIBXML_NONET);
  return $doc;
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $doc = load_bonus($XML_B);
  $xp = new DOMXPath($doc);

  $username = trim($_POST["username"] ?? "");
  $crediti = (int)($_POST["crediti"] ?? 0);
  $azione = $_POST["azione"] ?? "";

  if ($username !== "") {
    $nodo = $xp->query("//utente[@username='$username']")->item(0);

    if ($azione === "salva") {
      if (!$nodo) {
        $nodo = $doc->createElement("utente");
        $nodo->setAttribute("username", $username);
        $c = $doc->createElement("crediti", (string)$crediti);
        $nodo->appendChild($c);
        $doc->documentElement->appendChild($nodo);
      } else {
        $nodo->getElementsByTagName("crediti")->item(0)->nodeValue = (string)$crediti;
      }
      $msg = "Bonus salvato.";
    }

    if ($azione === "elimina" && $nodo) {
      $nodo->parentNode->removeChild($nodo);
      $msg = "Bonus eliminato.";
    }

    $doc->save($XML_B);
  }
}

$doc = load_bonus($XML_B);
$xp = new DOMXPath($doc);
$utenti = $xp->query("//utente");

page_header("FD NAILS - Gestione Bonus", "admin");
?>

<div class="page-title"><h1>Gestione bonus</h1></div>

<?php if ($msg !== ""): ?>
  <p style="color:green; font-weight:bold;"><?= htmlspecialchars($msg) ?></p>
<?php endif; ?>

<h3>Aggiungi / Modifica bonus</h3>
<form method="post" style="max-width:400px;">
  <label>Username</label>
  <input type="text" name="username" required>

  <label>Crediti</label>
  <input type="number" name="crediti" min="0" required>

  <input type="hidden" name="azione" value="salva">
  <button class="btn btn-primary" type="submit">Salva</button>
</form>

<hr>

<h3>Bonus esistenti</h3>

<table border="1" cellpadding="6" cellspacing="0">
  <tr>
    <th>Username</th>
    <th>Crediti</th>
    <th>Azioni</th>
  </tr>

  <?php foreach ($utenti as $u): ?>
    <tr>
      <td><?= htmlspecialchars($u->getAttribute("username")) ?></td>
      <td><?= htmlspecialchars($u->getElementsByTagName("crediti")->item(0)->nodeValue) ?></td>
      <td>
        <form method="post" style="display:inline;">
          <input type="hidden" name="username" value="<?= htmlspecialchars($u->getAttribute("username")) ?>">
          <input type="hidden" name="azione" value="elimina">
          <button class="btn btn-danger" type="submit">Elimina</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php page_footer(); ?>
