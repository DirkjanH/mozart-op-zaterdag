<?php
// stel php in dat deze fouten weergeeft
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php');
require_once( 'modules/bestelfuncties.php' );

Kint::$enabled_mode = false; //($_SERVER['REMOTE_ADDR'] === '45.82.191.170');

d($_REQUEST);

if (isset($_GET['res']) & is_string($_GET['res'])) {
            $reservering_query = "SELECT * FROM {$tabel_reserveringen} WHERE random_id = '{$_GET['res']}'";
            $reservering = select_query($reservering_query, 1);
            d($reservering_query, $reservering);
}
else exit('Dit is geen geldige reserveringscode.');

if (isset($reservering)) {
    if ($reservering['betaalstatus'] == 'paid') {
    $concert = select_query("SELECT * FROM {$tabel_concerten} WHERE concertId = {$reservering['concertId']};", 1);

    $datumentijd = strftime("%A %e %B %Y, aanvang %H:%M", strtotime($concert['datum'].' '.$concert['tijd']));

    $concert['euro_vol'] = euro2($concert['prijs_vol']);
    $concert['euro_red'] = euro2($concert['prijs_red']);
    $concert['euro_kind'] = euro2($concert['prijs_kind']);

    if ($reservering['aantal_vol'] == 1) $kaartjes_vol = '1 kaartje à';
    elseif ($reservering['aantal_vol'] > 1) $kaartjes_vol = "{$reservering['aantal_vol']} kaartjes à";
    if ($reservering['aantal_red'] == 1) $kaartjes_red = '1 kaartje à';
    elseif ($reservering['aantal_red'] > 1) $kaartjes_red = "{$reservering['aantal_red']} kaartjes à";
    if ($reservering['aantal_kind'] == 1) $kaartjes_kind = '1 kaartje à';
    elseif ($reservering['aantal_kind'] > 1) $kaartjes_kind = "{$reservering['aantal_kind']} kaartjes à";

    $reservering['euro_totaal'] = euro2($reservering['totaal']);

    d($reservering, $concert);

    $naam = str_replace('  ', ' ', $reservering['voornaam'].' '.$reservering['tussenvoegsel'].' '.$reservering['achternaam']);

    if (isset($logo_url) AND $logo_url != '') {$logo=$url.rawurlencode($logo_url); $message = 
    <<<MESSAGE
    <header class="w3-panel-0"><img src="{$logo}" alt="logo" style="width: 100%; max-width: 600px; height: auto; border: none;"></header>\n
    MESSAGE;
    }
    else $message = '';
    
$message .= 
<<<MESSAGE
    <p>Beste {$reservering['voornaam']},</p>\n
    </p>Hartelijk dank voor je bestelling nr. {$reservering['reserveringnr']} van concertkaartjes. De volgende gegevens zijn geregistreerd:\n\n</p>
    <ul>
        <li>Naam: {$naam}</li>\n\n
        <li>Gereserveerd voor het concert "<b>{$concert['concerttitel']}</b>" op <b>{$datumentijd}</b>:</li>\n\n
        <ul>
            <li>$kaartjes_vol {$concert['euro_vol']}</li>\n
MESSAGE;
    if ($reservering['aantal_red'] > 0) $message .= "<li>{$kaartjes_red} {$concert['euro_red']}</li>\n";
    if ($reservering['aantal_kind'] > 0) $message .= "<li>$kaartjes_kind {$concert['euro_kind']}</li>\n";
    if (isset($reservering['flyers']) & $reservering['flyers'] == 1) {
                $message .= "<li>Je hebt aangegeven dat je op de hoogte gehouden wilt worden van toekomstige concerten. We zullen je hierover van tijd tot tijd emails sturen.</li>\n";
            }
    $message .= 
<<<MESSAGE
    </ul><li>\nHet totale verschuldigde bedrag is {$reservering['euro_totaal']}. Dit bedrag heb je reeds betaald via iDeal. In je email ontvang je een concertkaartje. Neem dit SVP mee naar het concert, geprint op papier of op je telefoon. Op dat kaartje staat een QR code. Bij het concert wordt die gescand.</li></ul>
MESSAGE;
    if (isset($reservering['opmerkingen']) & $reservering['opmerkingen'] != "") {
                $reservering['opmerkingen'] = htmlentities(stripslashes($reservering['opmerkingen']));
                $message .= "<p>Je opmerkingen waren: {$reservering['opmerkingen']}</p>\n";
            }
    $message .= "<p>Met muzikale groet,\n\n<br><br>{$organisator}</p>\n<br>";
            d($message);
  }
else {
    if (isset($logo_url) AND $logo_url != '') {$logo=$url.rawurlencode($logo_url); $message = 
<<<MESSAGE
    <header class="w3-panel-0"><img src="{$logo}" alt="logo" style="width: 100%; max-width: 600px; height: auto; border: none;"></header>\n
MESSAGE;
    }
    else $message = '';
    
    $message .= 
<<<MESSAGE
    <p>Beste {$reservering['voornaam']},</p>\n
    </p>Hartelijk dank voor je bestelling van concertkaartjes. Helaas is de betaling niet gelukt. De kaartjes zijn dus nog niet gereserveerd. We raden je aan om de bestelling en betaling nogmaals uit te voeren. <a href="javascript:history.go(-3)">Klik op deze link om terug te gaan naar je bestelling</a>.</p>
MESSAGE;
    $message .= "<p>Met muzikale groet,\n\n<br><br>{$organisator}</p>\n<br>";
            d($message);
 }
}
else exit('Deze boeking bestaat niet');
?>

<!doctype html>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="utf-8">
	<link href="<?php echo $css; ?>" rel="stylesheet" type="text/css">
	<link rel="icon" type="image/png" href=<?php echo $favicon; ?>>
    <title>Dank voor je kaartbestelling</title>
</head>

<body class="w3-gray">
    <div class="w3-content w3-white w3-panel w3-padding-bottom">
        <?php echo $message; ?>
    </div>
</body>
</html>