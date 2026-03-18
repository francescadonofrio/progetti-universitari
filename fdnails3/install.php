<?php
require_once "php/db.php";

mysqli_query($conn, "DROP TABLE IF EXISTS utenti");

$sql = "
CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(32),
    cognome VARCHAR(32),
    username VARCHAR(16) UNIQUE,
    email VARCHAR(64),
    password VARCHAR(255),
    tipo_utente ENUM('registrato','amministratore'),
    crediti INT DEFAULT 0,
    reputazione INT DEFAULT 0,
    data_registrazione DATETIME,
    ban BOOLEAN DEFAULT 0
) ENGINE=InnoDB;
";

mysqli_query($conn, $sql) or die("Errore creazione tabella utenti");

$now = date("Y-m-d H:i:s");

$utenti = [
    ["Federica","Donofrio","admin","admin@fdnails.it","Admin123!","amministratore",0,0],
    ["Francesca","Donofrio","francidono02","francydono02@email.it","User123!","registrato",100,10],
    ["Vanessa","Dimanno","vanedim73","vanessa73@email.it","User456!","registrato",50,5]
];

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO utenti (nome,cognome,username,email,password,tipo_utente,crediti,reputazione,data_registrazione)
     VALUES (?,?,?,?,?,?,?,?,?)"
);

foreach ($utenti as [$nome,$cognome,$username,$email,$pwd,$ruolo,$crediti,$rep]) {
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssiss",
        $nome, $cognome, $username, $email, $hash, $ruolo, $crediti, $rep, $now
    );
    mysqli_stmt_execute($stmt);
}

echo "INSTALLAZIONE COMPLETATA";