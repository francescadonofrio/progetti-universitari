<?php
session_start();
require_once __DIR__ . "/php/auth.php";

$id = isset($_GET["id"]) ? $_GET["id"] : "";
if ($id === "") { header("Location: catalogo.php"); exit; }

if (current_role() === "visitatore") { header("Location: login.php"); exit; }

if (!isset($_SESSION["carrello"]) || !is_array($_SESSION["carrello"])) {
  $_SESSION["carrello"] = [];
}

$_SESSION["carrello"][$id] = (isset($_SESSION["carrello"][$id]) ? (int)$_SESSION["carrello"][$id] : 0) + 1;

header("Location: carrello.php");
exit;