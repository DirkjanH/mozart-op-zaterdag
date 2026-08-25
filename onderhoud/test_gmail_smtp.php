<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Credentials staan buiten de openbare webmap.
$credentialBestand = __DIR__ . '/../includes/_tst/MOZART_GMAIL_USERNAME.txt';
$gebruikersnaam = 'info@mozartopzaterdag.nl';
$appWachtwoord = '';
$bron = 'niet gevonden';
$debug = [];

// Lees zowel gelabelde als twee ongelabelde regels in.
if (is_readable($credentialBestand)) {
    $regels = preg_split('/\r\n|\r|\n/', trim((string) file_get_contents($credentialBestand)));
    $onbenoemdeRegels = [];

    foreach ($regels as $regel) {
        $regel = trim($regel);
        if ($regel === '' || str_starts_with($regel, '#')) {
            continue;
        }

        if (preg_match('/^([^:=]+)\s*[:=]\s*(.*)$/', $regel, $delen)) {
            $naam = strtolower(trim($delen[1]));
            $waarde = trim($delen[2]);
            if (in_array($naam, ['username', 'gebruikersnaam', 'gmail_username', 'mozart_gmail_username'], true)) {
                $gebruikersnaam = $waarde;
            } elseif (in_array($naam, ['password', 'wachtwoord', 'app_password', 'gmail_app_password', 'mozart_gmail_app_password'], true)) {
                $appWachtwoord = preg_replace('/\s+/', '', $waarde);
                $bron = 'bestand';
            }
        } else {
            $onbenoemdeRegels[] = $regel;
        }
    }

    if ($appWachtwoord === '' && isset($onbenoemdeRegels[1])) {
        $gebruikersnaam = $onbenoemdeRegels[0];
        $appWachtwoord = preg_replace('/\s+/', '', $onbenoemdeRegels[1]);
        $bron = 'bestand';
    }
}

$gebruikersnaam = getenv('MOZART_GMAIL_USERNAME') ?: $gebruikersnaam;
$omgevingWachtwoord = getenv('MOZART_GMAIL_APP_PASSWORD');
if ($omgevingWachtwoord !== false && $omgevingWachtwoord !== '') {
    $appWachtwoord = preg_replace('/\s+/', '', $omgevingWachtwoord);
    $bron = 'omgeving';
}

// Zonder beide waarden heeft een SMTP-test geen zin.
if ($gebruikersnaam === '' || $appWachtwoord === '') {
    $debug[] = 'Gebruikersnaam of app-wachtwoord ontbreekt.';
} else {
    try {
        // Alleen verbinding en authenticatie testen; er wordt niets verzonden.
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = 'mailout.one.com';
        $mailer->SMTPAuth = true;
        $mailer->Username = $gebruikersnaam;
        $mailer->Password = $appWachtwoord;
        $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = 587;
        $mailer->Timeout = 8;
        $mailer->SMTPDebug = 2;
        $mailer->Debugoutput = static function (string $bericht) use (&$debug): void {
            $debug[] = trim($bericht);
        };
        $mailer->smtpConnect();
        $mailer->smtpClose();
        $resultaat = 'SMTP-verbinding en authenticatie geslaagd.';
    } catch (Throwable $e) {
        $resultaat = 'SMTP-test mislukt: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Gmail SMTP testen</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
</head>
<body>
    <div class="w3-content w3-white w3-panel">
        <h3>Gmail SMTP-test</h3>
        <p><strong><?= htmlspecialchars($resultaat ?? 'Test niet uitgevoerd') ?></strong></p>
        <p>Credentialbestand bestaat: <?= file_exists($credentialBestand) ? 'ja' : 'nee' ?>; leesbaar: <?= is_readable($credentialBestand) ? 'ja' : 'nee' ?>; bron: <?= htmlspecialchars($bron) ?>; wachtwoordlengte: <?= strlen($appWachtwoord) ?> tekens.</p>
        <pre><?= htmlspecialchars(implode("\n", $debug)) ?></pre>
        <p>Deze test opent en sluit alleen de SMTP-verbinding; er wordt geen bericht verstuurd.</p>
        <p>Verwijder dit testbestand na gebruik.</p>
    </div>
</body>
</html>
