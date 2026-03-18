<?php
require_once __DIR__ . "/php/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

printf(
  "<pre>username: %s\ntipo_utente session: %s\ncurrent_role(): %s\n</pre>",
  $_SESSION["username"] ?? "(none)",
  $_SESSION["tipo_utente"] ?? "(none)",
  current_role()
);