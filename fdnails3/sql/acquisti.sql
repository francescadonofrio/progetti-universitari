CREATE TABLE IF NOT EXISTS acquisti (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_utente INT NOT NULL,
  data_acquisto DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  totale DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  sconto_applicato DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  crediti_usati DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  totale_finale DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stato ENUM('in_corso','completato','annullato') NOT NULL DEFAULT 'in_corso',
  FOREIGN KEY (id_utente) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
