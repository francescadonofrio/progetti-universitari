CREATE TABLE IF NOT EXISTS acquisti_righe (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_acquisto INT NOT NULL,
  id_smalto VARCHAR(8) NOT NULL,
  quantita INT UNSIGNED NOT NULL DEFAULT 1,
  prezzo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (id_acquisto) REFERENCES acquisti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
