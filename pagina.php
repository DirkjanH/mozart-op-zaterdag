<?php
// 1. Start de sessie
session_start();

// 2. De Beveiligingscontrole:
// Controleer of de sessievariabele 'ingelogd' NIET is gezet of FALSE is.
if (!isset($_SESSION['ingelogd']) || $_SESSION['ingelogd'] !== true) {
    // Gebruiker is niet ingelogd: stuur door naar login.php
    header('Location: login.php');
    exit; // Stop de scriptuitvoering
}

// Als de code hier komt, is de gebruiker ingelogd!
?>

<!DOCTYPE html>
<html lang="nl">

<head>
    <title>Beveiligde Pagina</title>
</head>

<body>

    <h2>Welkom!</h2>
    <p>Dit is de geheime inhoud die alleen ingelogde gebruikers kunnen zien.</p>

    <p><a href="uitloggen.php">Uitloggen</a></p>

</body>

</html>