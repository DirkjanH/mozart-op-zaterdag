<?php
require_once __DIR__ . '/../includes/inloggen.php';
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MoZ dashboard</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <style>
        .dashboard-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 20px;
        }

        .dashboard {
            max-width: 700px;
        }

        .dashboard-logo {
            width: 180px;
            max-width: 35%;
            height: auto;
        }

        .dashboard-title {
            margin: 0;
        }

        .dashboard-links .w3-bar-item {
            box-sizing: border-box;
            width: 100%;
            white-space: normal;
        }

        .dashboard-registration {
            margin-top: 24px;
        }

        @media (max-width: 600px) {
            body {
                margin: 0;
                font-size: 16px;
                line-height: 1.45;
                -webkit-text-size-adjust: 100%;
            }

            .dashboard {
                width: 100%;
                max-width: none !important;
                margin: 0;
                padding: 12px;
                box-sizing: border-box;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .dashboard-logo {
                width: 140px;
                max-width: 100%;
            }

            .dashboard-title {
                font-size: 1.3rem;
                line-height: 1.25;
            }

            .dashboard-links .w3-bar-item {
                min-height: 48px;
                padding: 14px 12px;
                font-size: 1.05rem;
                line-height: 1.3;
            }

            .dashboard-registration {
                margin-top: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard w3-content w3-mobile w3-white w3-panel">
        <header class="dashboard-header">
            <img src="/images/Mozart.jpg" alt="Mozart op Zaterdag" class="dashboard-logo geenlijn">
            <h3 class="dashboard-title">Mozart op Zaterdag - Dashboard</h3>
        </header>
        <div class="dashboard-links w3-bar-block">
            <a href="deelnemers_bewerken.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Deelnemers bewerken</a>
            <a href="werken_bewerken.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Werken bewerken</a>
            <a href="activiteiten_bewerken.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Activiteiten bewerken</a>
            <a href="beschikbaarheid.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Beschikbaarheid overzicht</a>
            <a href="aanmeldbevestiging_bewerken.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Aanmeldbevestiging bewerken</a>
            <a href="deelnemers_mailen.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Deelnemers mailen</a>
            <a href="genereer_webpaginas.php" target="_blank" class="w3-bar-item w3-button w3-blue w3-margin-bottom">Genereer webpagina's</a>
            <a href="/deelnemers_aanmelden.php" target="_blank" class="dashboard-registration w3-bar-item w3-button w3-green w3-margin-bottom">Aanmeldingsformulier</a>
        </div>
    </div>
</body>

</html>
