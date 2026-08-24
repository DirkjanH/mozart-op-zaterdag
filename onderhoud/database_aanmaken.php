<?php
// Eenmalig script: maakt de database + tabellen aan uit "Mozart op Zaterdag.sql".
// Zet de MySQL-credentials als omgevingsvariabelen voordat je dit script uitvoert.

$hostname = getenv('MYSQL_HOST') ?: 'localhost';
$username = getenv('MYSQL_USERNAME') ?: 'mozartopzaterdag';
$password = getenv('MYSQL_PASSWORD');

$sqlBestand = __DIR__ . '/../.github/workflows/Mozart op Zaterdag.sql';

header('Content-Type: text/plain; charset=utf-8');

if ($password === false || $password === '') {
    exit("Zet MYSQL_PASSWORD om de database te kunnen aanmaken.\n");
}

if (!is_readable($sqlBestand)) {
    exit("Kan het SQL-bestand niet lezen: $sqlBestand\n");
}

try {
    // Nog geen dbname: die wordt door CREATE DATABASE / USE in het script zelf geregeld.
    $pdo = new PDO("mysql:host=$hostname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = file_get_contents($sqlBestand);

    // Statements splitsen op ; aan het einde van een regel (geen ; in de schema-inhoud zelf).
    $statements = array_filter(array_map('trim', preg_split('/;\s*\R/', $sql)));

    foreach ($statements as $statement) {
        if ($statement === '' || strncmp($statement, '--', 2) === 0) {
            continue;
        }
        $pdo->exec($statement);
        echo "OK: " . strtok($statement, "\n") . "\n";
    }

    echo "\nDatabase en tabellen zijn aangemaakt.\n";
} catch (PDOException $e) {
    echo "Fout: " . $e->getMessage() . "\n";
}
