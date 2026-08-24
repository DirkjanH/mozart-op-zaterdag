CREATE DATABASE IF NOT EXISTS MozartopZaterdag
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE MozartopZaterdag;

-- Tabel instrumenten
CREATE TABLE instrumenten (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  naam VARCHAR(100) NOT NULL UNIQUE,
  categorie ENUM('strijk', 'hout', 'koper', 'slagwerk', 'toets', 'zang') NOT NULL,
  actief TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Tabel deelnemers
CREATE TABLE deelnemers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voornaam VARCHAR(100) NOT NULL,
  achternaam VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  telefoon VARCHAR(30) NULL,
  postcode VARCHAR(20) NULL,
  plaats VARCHAR(100) NULL,
  aangemaakt_op TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Koppeling deelnemers <-> instrumenten
CREATE TABLE deelnemer_instrumenten (
  deelnemer_id INT UNSIGNED NOT NULL,
  instrument_id INT UNSIGNED NOT NULL,
  voorkeuren TEXT NULL,
  PRIMARY KEY (deelnemer_id, instrument_id),
  CONSTRAINT fk_di_deelnemer
    FOREIGN KEY (deelnemer_id) REFERENCES deelnemers(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_di_instrument
    FOREIGN KEY (instrument_id) REFERENCES instrumenten(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tabel werken
CREATE TABLE werken (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  componist VARCHAR(100) NOT NULL DEFAULT 'W.A. Mozart',
  titel VARCHAR(255) NOT NULL,
  kv_nummer VARCHAR(20) NOT NULL,
  soort ENUM('symfonie', 'concert', 'ander') NOT NULL DEFAULT 'ander',
  bezetting TEXT NOT NULL,
  UNIQUE KEY uk_werk (kv_nummer, titel)
) ENGINE=InnoDB;

-- Tabel activiteiten
CREATE TABLE activiteiten (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  datum DATE NOT NULL,
  plaats VARCHAR(100) NOT NULL,
  omschrijving VARCHAR(255) NULL
) ENGINE=InnoDB;

-- Koppeling activiteiten <-> werken
CREATE TABLE activiteit_werken (
  activiteit_id INT UNSIGNED NOT NULL,
  werk_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (activiteit_id, werk_id),
  CONSTRAINT fk_aw_activiteit
    FOREIGN KEY (activiteit_id) REFERENCES activiteiten(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_aw_werk
    FOREIGN KEY (werk_id) REFERENCES werken(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Koppeling activiteiten <-> deelnemers
-- bevat beschikbaarheid, bevestiging en gekozen instrument voor die activiteit
CREATE TABLE activiteit_deelnemers (
  activiteit_id INT UNSIGNED NOT NULL,
  deelnemer_id INT UNSIGNED NOT NULL,
  instrument_id INT UNSIGNED NULL,
  status ENUM('ja', 'nee', 'misschien') NOT NULL DEFAULT 'nee',
  datum_bevestiging DATETIME NULL,
  aangemeld_op TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (activiteit_id, deelnemer_id),
  CONSTRAINT fk_ad_activiteit
    FOREIGN KEY (activiteit_id) REFERENCES activiteiten(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_ad_deelnemer
    FOREIGN KEY (deelnemer_id) REFERENCES deelnemers(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_ad_instrument
    FOREIGN KEY (instrument_id) REFERENCES instrumenten(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

