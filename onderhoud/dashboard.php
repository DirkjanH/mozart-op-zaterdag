<?php
require_once __DIR__ . '/../includes/inloggen.php';
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoZ dashboard</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
</head>

<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:700px;">
        <header style="display:flex; align-items:center; gap:24px; margin-bottom:20px;">
            <img src="/images/Mozart.jpg" alt="Mozart op Zaterdag" class="geenlijn" style="width:180px; max-width:35%; height:auto;">
            <h3 style="margin:0;">Mozart op Zaterdag - Dashboard</h3>
        </header>
        <div class="w3-bar-block">
            <a href="deelnemers_bewerken.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Deelnemers bewerken</a>
            <a href="werken_bewerken.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Werken bewerken</a>
            <a href="activiteiten_bewerken.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Activiteiten bewerken</a>
            <a href="beschikbaarheid.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Beschikbaarheid overzicht</a>
            <a href="deelnemers_mailen.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Deelnemers mailen</a>
            <a href="genereer_webpaginas.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Genereer webpagina's</a>
            <a href="/deelnemers_aanmelden.php" target="_blank" class="w3-bar-item w3-button w3-green w3-margin-bottom" style="margin-top:24px;">Aanmeldingsformulier</a>
        </div>
    </div>
</body>

</html>
