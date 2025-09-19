<?php
// stel php in dat deze fouten weergeeft
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER["DOCUMENT_ROOT"] . '/vendor/autoload.php';

use function PHP81_BC\strftime;

Kint::$enabled_mode = false; //($_SERVER['REMOTE_ADDR'] === '83.87.12.163');

session_start();

// Zet hier je gewenste wachtwoord
$wachtwoord = "Mozart123.";

// Controleer of het wachtwoord reeds is verzonden en correct is
if (isset($_POST['password']) && $_POST['password'] === $wachtwoord) {
    $_SESSION['ingelogd'] = true;
}

// Toegang weigeren als niet ingelogd
if (!isset($_SESSION['ingelogd']) || $_SESSION['ingelogd'] !== true) {
    echo
    <<<XXX
    <body class="w3-lightgrey">
    <div class="w3-content w3-white w3-panel" style="max-width: 800px; margin-top:20px;">
        <h2>Inloggen vereist</h2>
        <p>Deze pagina is beveiligd. Voer het wachtwoord in om toegang te krijgen.</p>
    <form method="post">
            Wachtwoord: <input type="password" name="password" />
            <input type="submit" value="Login" />
            <input type="submit" value="Loguit" />
            if (isset($_POST['password']) && \$_POST['password'] !== '$wachtwoord') {
                echo "<p class="w3-red">Onjuist wachtwoord. Probeer het opnieuw.</p>";
              if (isset($_POST['loguit']) && $_POST['loguit'] === 'Loguit') {
                session_destroy();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
          </form>
          </div>
    </body>
    XXX;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mozart op Zaterdag op 25 oktober 2025</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
</head>

<body>
    <div class="w3-content w3-white w3-panel">
        <?php include_once '../navigatie.htm'; ?>
        <h3>25 oktober 2025:</h3>
        <h2>'Haffner' symfonie nr. 35 in D KV 385</h2>
        <p>
            De vijfde aflevering van <i>Mozart op Zaterdag</i> vindt plaats op zaterdagochtend 25 oktober We werken dan
            aan
            Mozarts 'Haffner' symfonie nr. 35 in D KV 385 voor uitgebreid orkest met dubbel hout, hoorns, trompetten en
            pauken.
            De symfonie bestaat uit vier delen, een Allegro con spirito, een Andante, een Menuetto en Trio en een
            Presto, en
            duurt ongeveer 20 minuten.
        </p>
        <h4>De Oorsprong van de 'Haffner'</h4>
        <p>De symfonie dankt zijn naam aan de familie Haffner, een prominente familie uit Salzburg waar Mozart bevriend
            mee was.
            Oorspronkelijk componeerde Mozart het werk in 1782 als een serenade ter gelegenheid van de verheffing in de
            adelstand van Siegmund Haffner. Later bewerkte Mozart de serenade tot een symfonie door onder andere een
            mars en
            twee menuetten te verwijderen en een nieuw openingsdeel toe te voegen, waardoor het de vorm kreeg die we nu
            kennen.
            De 'Haffner' symfonie is een prachtig voorbeeld van Mozarts meesterschap en zijn vermogen om zowel
            feestelijke
            grandeur als diepgaande emotie in zijn muziek te leggen.</p>
        <h4>Deelnemers</h4>
        <p>De bezetting is bijna compleet. We hebben nu 30 mensen kunnen plaatsen, een mengeling van goede amateurs en
            professionals. Er is nog plaats voor een trompet, twee 2e violen en een altviool.</p>
        <table class="w3-table w3-striped w3-bordered" id="deelnemers">
            <thead>
                <tr>
                    <th>voornaam</th>
                    <th>achternaam</th>
                    <th>instrument</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Yolande </td>
                    <td>Krooshof </td>
                    <td>fluit 1</td>
                </tr>
                <tr>
                    <td>Liesbeth</td>
                    <td>van Elburg</td>
                    <td>fluit 2</td>
                </tr>
                <tr>
                    <td>Mirella</td>
                    <td>Brakkee-Overbeek</td>
                    <td>hobo 1</td>
                </tr>
                <tr>
                    <td>Erik</td>
                    <td>van Kassel </td>
                    <td>hobo 2</td>
                </tr>
                <tr>
                    <td>Ella</td>
                    <td>Broekstra</td>
                    <td>klarinet 1</td>
                </tr>
                <tr>
                    <td>Rosaline</td>
                    <td>Mentink</td>
                    <td>klarinet 2</td>
                </tr>
                <tr>
                    <td>Nicole</td>
                    <td>Freling</td>
                    <td>fagot 1</td>
                </tr>
                <tr>
                    <td>Boukje</td>
                    <td>Jonkheer</td>
                    <td>fagot 2</td>
                </tr>
                <tr>
                    <td>Richard</td>
                    <td>van Hoorn</td>
                    <td>hoorn 1</td>
                </tr>
                <tr>
                    <td>Piet </td>
                    <td>van den Heuvel</td>
                    <td>hoorn 2</td>
                </tr>
                <tr>
                    <td>Michiel</td>
                    <td>Plomp</td>
                    <td>trompet 1</td>
                </tr>
                <tr>
                    <td></td>
                    <td><span class="nadruk">vacature</span></td>
                    <td>trompet 2</td>
                </tr>
                <tr>
                    <td>Agnes</td>
                    <td>Hoeksma</td>
                    <td>pauken</td>
                </tr>
                <tr>
                    <td>Carla</td>
                    <td>Westhof</td>
                    <td>viool 1</td>
                </tr>
                <tr>
                    <td>Saskia</td>
                    <td>Wieberdink</td>
                    <td>viool 1</td>
                </tr>
                <tr>
                    <td>Corinne</td>
                    <td>Britzel</td>
                    <td>viool 1</td>
                </tr>
                <tr>
                    <td>Marieke</td>
                    <td>van der Haar</td>
                    <td>viool 1</td>
                </tr>
                <tr>
                    <td>Janne</td>
                    <td>den Blaauwen</td>
                    <td>viool 1</td>
                </tr>
                <tr>
                    <td>Daniela</td>
                    <td>Roeland</td>
                    <td>viool 2</td>
                </tr>
                <tr>
                    <td>Tonnie</td>
                    <td>Mols</td>
                    <td>viool 2</td>
                </tr>
                <tr>
                    <td>Peter</td>
                    <td>Klusener</td>
                    <td>viool 2</td>
                </tr>
                <tr>
                    <td></td>
                    <td><span class="nadruk">vacature</span></td>
                    <td>viool 2</td>
                <tr>
                    <td></td>
                    <td><span class="nadruk">vacature</span></td>
                    <td>viool 2</td>
                </tr>
                <tr>
                    <td></td>
                    <td><span class="nadruk">vacature</span></td>
                    <td>altviool</td>
                </tr>
                <tr>
                    <td>Maaike</td>
                    <td>Gerlsma</td>
                    <td>altviool</td>
                </tr>
                <tr>
                    <td>Martin</td>
                    <td>Spanjaard</td>
                    <td>altviool</td>
                </tr>
                <tr>
                    <td>Eline</td>
                    <td>Snoek</td>
                    <td>altviool</td>
                </tr>
                <tr>
                    <td>Harm</td>
                    <td>van Wijnen</td>
                    <td>cello</td>
                </tr>
                <tr>
                    <td>Robin</td>
                    <td>Gerretsen</td>
                    <td>cello</td>
                </tr>
                <tr>
                    <td>Friso</td>
                    <td>van der Veen</td>
                    <td>cello</td>
                </tr>
                <tr>
                    <td>Anke</td>
                    <td>Meester</td>
                    <td>cello</td>
                </tr>
                <tr>
                    <td>Dorine</td>
                    <td>van Lammeren</td>
                    <td>contrabas</td>
                </tr>
                <tr>
                    <td>Yo</td>
                    <td>van Dijk</td>
                    <td>contrabas</td>
                </tr>
            </tbody>
        </table>
        <p class="onzichtbaar">De partijen zijn binnenkort beschikbaar</p>
        <div class="" style="clear: both;">
            <h4>Partijen & partituur</h4>
            <ul style="column-count: 3;">
                <li>
                    <a href="/2025-10-25/K385.Flute.pdf" target="_blank">Fluit 1-2</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Oboe.pdf" target="_blank">Hobo 1-2</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Clarinet.pdf" target="_blank">Klarinet 1-2 in A</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Bassoon.pdf" target="_blank">Fagot 1-2</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Horn.pdf" target="_blank">Hoorn 1-2</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Trumpet.pdf" target="_blank">Trompet 1-2</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Timpani.pdf" target="_blank">Pauken</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Violin1.pdf" target="_blank">Viool 1 (nog niet betekend)</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Violin2.pdf" target="_blank">Viool 2 (nog niet betekend)</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Viola.pdf" target="_blank">Altviool (nog niet betekend)</a>
                </li>
                <li>
                    <a href="/2025-10-25/K385.Cello.pdf" target="_blank">Cello, contrabas (nog niet betekend)</a>
                </li>
                <li>
                    <a href="/2025-10-25/NMA_04_11_Band_06_04_KV_385_scan.pdf" target="_blank">
                        <b>nog niet betekende partituur</b>
                    </a>
                </li>
            </ul>
            <p class="onzichtbaar">
                NB.
                <b>
                    Strijkers, neem SVP de streken uit de betekende partituur over in je eigen partij.
            </p>
        </div>
    </div>
</body>

</html>