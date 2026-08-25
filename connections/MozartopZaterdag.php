<?php
// Verbinding met de MozartopZaterdag-database.
// Vul de echte inloggegevens in en beveilig dit bestand (buiten de webroot of via .htaccess).
$hostname = 'localhost';
$database = 'cfb5wd2sc_mozartopzaterdag';
$username = 'cfb5wd2sc_mozartopzaterdag';
$password = '%6riUHs##SQ!4aQGaW^Qhq';

try {
    $pdo = new PDO(
        "mysql:host=$hostname;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    trigger_error('Kan geen verbinding maken met de database: ' . $e->getMessage(), E_USER_ERROR);
}
