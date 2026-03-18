<?php
session_start();

$_SESSION["carrello"] = (isset($_SESSION["carrello"]) && is_array($_SESSION["carrello"])) ? $_SESSION["carrello"] : [];

$action = (string)($_POST["action"] ?? "");
$id = (string)($_POST["id"] ?? "");

if ($action === "svuota") {
  unset($_SESSION["carrello"]);
  header("Location: carrello.php");
  exit;
}

if ($id === "" || !isset($_SESSION["carrello"][$id])) {
  header("Location: carrello.php");
  exit;
}

if ($action === "remove") {
  unset($_SESSION["carrello"][$id]);
} elseif ($action === "dec") {
  $q = (int)$_SESSION["carrello"][$id] - 1;
  if ($q <= 0) unset($_SESSION["carrello"][$id]);
  else $_SESSION["carrello"][$id] = $q;
}

header("Location: carrello.php");
exit;