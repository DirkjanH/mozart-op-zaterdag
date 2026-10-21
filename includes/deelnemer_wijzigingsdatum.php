<?php
function zorgVoorDeelnemerWijzigingsdatum(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS deelnemer_wijzigingen (deelnemer_id INT NOT NULL PRIMARY KEY, gemarkeerd_op DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}