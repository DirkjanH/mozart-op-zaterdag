<?php
function zorgVoorDeelnemerWijzigingsdatum(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS deelnemer_wijzigingen (deelnemer_id INT NOT NULL PRIMARY KEY, gemarkeerd_op DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $kolom = $pdo->query("SHOW COLUMNS FROM deelnemers LIKE 'laatst_gewijzigd_op'")->fetch();
    if ($kolom !== false) {
        return;
    }

    $pdo->exec('ALTER TABLE deelnemers ADD COLUMN laatst_gewijzigd_op DATETIME NULL');
    $pdo->exec('UPDATE deelnemers d INNER JOIN deelnemer_wijzigingen dw ON dw.deelnemer_id = d.id SET d.laatst_gewijzigd_op = dw.gemarkeerd_op');
}