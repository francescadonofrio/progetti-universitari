<?php
$DB_HOST = "127.0.0.1";
$DB_PORT = 3307;
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "francesca_donofrio_php_mysql";

$conn = mysqli_connect(
    $DB_HOST,
    $DB_USER,
    $DB_PASS,
    $DB_NAME,
    $DB_PORT
);

if (!$conn) {
    die("Errore connessione DB: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");
?>
