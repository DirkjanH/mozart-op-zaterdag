<?php
// stel php in dat deze fouten weergeeft
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER["DOCUMENT_ROOT"] . '/vendor/autoload.php';

use function PHP81_BC\strftime;

Kint::$enabled_mode = true; //($_SERVER['REMOTE_ADDR'] === '83.87.12.163');

session_start();

// Zet hier je gewenste wachtwoord
$wachtwoord = "WolfGang";

d($wachtwoord, $_REQUEST);

// Controleer of het wachtwoord reeds is verzonden en correct is
if (isset($_POST['password']) && $_POST['password'] === $wachtwoord) {
    $_SESSION['ingelogd'] = true;
}
if (isset($_POST['password']) && $_POST['password'] !== $wachtwoord) {
    echo ('<p class="w3-red">Onjuist wachtwoord. Probeer het opnieuw.</p>');
}
if (isset($_POST['loguit']) && $_POST['loguit'] === 'Loguit') {
    session_destroy();
    //header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
// Toegang weigeren als niet ingelogd
if (!isset($_SESSION['ingelogd']) || $_SESSION['ingelogd'] !== true) {
    echo <<<XXX
    <body class="w3-lightgrey">
        <div class="w3-content w3-white w3-panel w3-center" style="max-width: 800px; margin-top:20px;">
            <h2>Inloggen vereist</h2>
            <p>Deze pagina is beveiligd. Voer het wachtwoord in om toegang te krijgen.</p>
            <form method="post">
                Wachtwoord: <input type="password" name="password" />
                <input type="submit" value="Login" class="w3-button"/>
                <input type="submit" value="Loguit" class="w3-button w3-red"/>
            </form>
        </div>
    </body>
    XXX;
    exit;
} else echo ('<p class="w3-green">Ingelogd</p>');
// Als we hier komen, is de gebruiker ingelogd
print_r($_SESSION);

// Einde includes/inloggen.php
