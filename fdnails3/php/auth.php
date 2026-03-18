<?php
declare(strict_types=1);

function current_role(): string {
  if (session_status() === PHP_SESSION_NONE) session_start();
  return $_SESSION["username"] ?? null
    ? ($_SESSION["tipo_utente"] === "amministratore" ? "amministratore" : "registrato")
    : "visitatore";
}

function require_role(string $role): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (current_role() !== $role) {
    header("Location: login.php");
    exit;
  }
}
