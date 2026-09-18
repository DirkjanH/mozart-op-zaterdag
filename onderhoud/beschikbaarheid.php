<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/mail_tracking.php';
require_once __DIR__ . '/../includes/csrf.php';

zorgVoorMailTrackingTabel($pdo);

// Beheerpagina's bevatten persoonsgegevens en mogen niet worden gecachet of ingesloten.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// Accepteer wijzigingen alleen vanuit een formulier met een geldig CSRF-cookietoken.
$csrfToken = csrfTokenOphalen();
csrfValiderenOfAfwijzen();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $toegestaneActies = [
        'mailteksten_opslaan',
        'status_opslaan',
        'toelating_intrekken',
        'toelaten',
        'uitnodigen',
        'afwijzen_met_mail',
        'toelaten_met_mail',
    ];
    if (!in_array($_POST['actie'] ?? '', $toegestaneActies, true)) {
        http_response_code(400);
        exit('Onbekende actie.');
    }
}

$melding = is_string($_SESSION['beschikbaarheid_melding'] ?? null)
    ? $_SESSION['beschikbaarheid_melding']
    : '';
unset($_SESSION['beschikbaarheid_melding']);
$mailtekstenZojuistOpgeslagen = ($_SESSION['beschikbaarheid_mailteksten_opgeslagen'] ?? false) === true;
unset($_SESSION['beschikbaarheid_mailteksten_opgeslagen']);
set_time_limit(15);
$activiteitId = (int) ($_GET['activiteit_id'] ?? $_POST['activiteit_id'] ?? 0);
$toonParameter = $_GET['toon'] ?? null;
$toonModus = in_array($toonParameter, ['toegelaten', 'ja_misschien'], true) ? $toonParameter : 'ja_misschien';
// Toon alleen activiteiten die nog moeten plaatsvinden.
$activiteiten = $pdo->query('SELECT id, datum, plaats, omschrijving FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum')->fetchAll(PDO::FETCH_ASSOC);
if ($activiteitId === 0 && $activiteiten !== []) $activiteitId = (int) $activiteiten[0]['id'];
$instrumenten = $pdo->query("SELECT id, naam FROM instrumenten ORDER BY CASE WHEN LOWER(TRIM(naam)) = 'pauken' THEN COALESCE((SELECT MIN(i2.id) FROM instrumenten i2 WHERE LOWER(TRIM(i2.naam)) LIKE 'trompet%'), id) ELSE id END, CASE WHEN LOWER(TRIM(naam)) = 'pauken' THEN 1 ELSE 0 END, id")->fetchAll(PDO::FETCH_ASSOC);

$standaardOnderwerp = 'Bevestiging deelname Mozart op Zaterdag';
$standaardAfwijzingsOnderwerp = 'Mozart op Zaterdag - deze keer geen plaats';
$gmailGebruikersnaam = 'info@mozartopzaterdag.nl';
$gmailGebruikersnaamBestand = __DIR__ . '/../includes/_tst/MOZART_GMAIL_USERNAME.txt';
$gmailAppWachtwoord = '';
$gmailWachtwoordBron = 'niet gevonden';
$gmailBestandBestaat = file_exists($gmailGebruikersnaamBestand);
$gmailBestandLeesbaar = is_readable($gmailGebruikersnaamBestand);
$gmailCredentialRegels = 0;
if ($gmailBestandLeesbaar) {
    $credentialRegels = preg_split('/\r\n|\r|\n/', trim((string) file_get_contents($gmailGebruikersnaamBestand)));
    $gmailCredentialRegels = count(array_filter($credentialRegels, static fn (string $regel): bool => trim($regel) !== ''));
    $ongelabeldeRegels = [];
    foreach ($credentialRegels as $regel) {
        $regel = trim($regel);
        if ($regel === '' || str_starts_with($regel, '#')) {
            continue;
        }
        if (preg_match('/^([^:=]+)\s*[:=]\s*(.*)$/', $regel, $delen)) {
            $naam = strtolower(trim($delen[1]));
            $waarde = trim($delen[2]);
            if (in_array($naam, ['username', 'gebruikersnaam', 'gmail_username', 'mozart_gmail_username'], true)) {
                $gmailGebruikersnaam = $waarde;
            } elseif (in_array($naam, ['password', 'wachtwoord', 'app_password', 'gmail_app_password', 'mozart_gmail_app_password'], true)) {
                $gmailAppWachtwoord = preg_replace('/\s+/', '', $waarde);
                $gmailWachtwoordBron = 'bestand';
            }
        } else {
            $ongelabeldeRegels[] = $regel;
        }
    }
    if ($gmailAppWachtwoord === '' && isset($ongelabeldeRegels[1])) {
        $gmailGebruikersnaam = $ongelabeldeRegels[0];
        $gmailAppWachtwoord = preg_replace('/\s+/', '', $ongelabeldeRegels[1]);
        $gmailWachtwoordBron = 'bestand';
    }
}
$gmailGebruikersnaam = getenv('MOZART_GMAIL_USERNAME') ?: $gmailGebruikersnaam;
$gmailOmgevingsWachtwoord = getenv('MOZART_GMAIL_APP_PASSWORD');
if ($gmailOmgevingsWachtwoord !== false && $gmailOmgevingsWachtwoord !== '') {
    $gmailAppWachtwoord = preg_replace('/\s+/', '', $gmailOmgevingsWachtwoord);
    $gmailWachtwoordBron = 'omgeving';
}
$standaardMail = <<<'HTML'
Beste {{voornaam}},<br><br>
Leuk dat je je hebt aangemeld voor Mozart op Zaterdag! We zijn blij om je te kunnen plaatsen als {{instrument}}{{partij_tekst}} voor zaterdag {{datum}} in de {{plaats}}. We spelen dan {{omschrijving}}.<br><br>
De bezetting vind je op de <a href="https://mozartopzaterdag.nl">website van Mozart op Zaterdag</a>. Je kunt inloggen op de pagina voor deelnemers met <strong>WolfGang</strong> (let op de hoofdletters).<br><br>
Mocht je voor het concert moeten afzeggen, dan stellen we het op prijs als je een vervanger aandraagt.<br><br>
Alle partijen staan ook op de <a href="https://mozartopzaterdag.nl">website</a>.<br><br>
Nog even wat aanvullende opmerkingen over Mozart op Zaterdag:<br>
<ul>
<li>De Marnixzaal ligt aan het Domplein 4, op nog geen kwartier lopen van het station.</li>
<li>Dress code: kleurige vrije-tijdskleren.</li>
<li>Je hoeft geen lessenaar mee te nemen; die zijn aanwezig in de zaal.</li>
</ul>
Met enthousiaste groet,<br><br>
Dirkjan Horringa<br><br>
P.S. Mocht je ook in de toekomst weer willen meespelen, <a href="{{aanmeldlink}}">vul dan dit formulier in</a>. Je bestaande gegevens staan daar alvast ingevuld.
HTML;
$standaardAfwijzingsMail = 'Beste {{voornaam}},<br><br>Een tijdje terug heb je in het aanmeldingsformulier voor Mozart op Zaterdag aangegeven dat je (misschien) wilde meespelen op {{datum}} in {{omschrijving}}. De belangstelling voor deze aflevering van Mozart op Zaterdag is echter groot. Helaas kan ik je voor die datum niet plaatsen. Ik hoop je bij een van de volgende afleveringen of in andere projecten weer te zien.<br><br>Hartelijke groet<br><br>Dirkjan Horringa<br><br>P.S. Mocht je ook in de toekomst weer willen meespelen, <a href="{{aanmeldlink}}">vul dan dit formulier in</a>. Je bestaande gegevens staan daar alvast ingevuld.';
$standaardAfwijzingsMail = (string) ($standaardAfwijzingsMail ?? '');
$toelatingsOnderwerp = $standaardOnderwerp;
$toelatingsMail = $standaardMail;
$mailteksten = [
    'toelaten' => ['onderwerp' => $toelatingsOnderwerp, 'tekst' => $toelatingsMail],
    'uitnodigen' => ['onderwerp' => $standaardOnderwerp, 'tekst' => $standaardMail],
    'afwijzen' => ['onderwerp' => $standaardAfwijzingsOnderwerp, 'tekst' => $standaardAfwijzingsMail],
];
$mailtekstenMap = __DIR__ . '/../JSON';
// Eén vast bestand voor laden én opslaan; een losse "nieuwste van meerdere bestanden"-selectie kan per ongeluk een stray kopie kiezen.
$mailtekstenBestand = $mailtekstenMap . '/mailteksten.json';
$mailtekstenBestandsnaam = basename($mailtekstenBestand);
// PHP-FPM-workers bewaren stat-/realpath-cache tussen requests; wis die zodat we altijd de actuele bestandsinformatie van de server lezen.
clearstatcache(true, $mailtekstenBestand);
$mailtekstenGewijzigdOp = is_file($mailtekstenBestand)
    ? (new DateTimeImmutable('@' . filemtime($mailtekstenBestand)))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i:s')
    : 'nog niet opgeslagen';
try {
    if (!is_readable($mailtekstenBestand)) {
        throw new RuntimeException('Bestand niet gevonden: JSON/' . $mailtekstenBestandsnaam . '.');
    }
    // Lees de inhoud rechtstreeks van schijf; nooit een eerder in dit proces gebufferde versie gebruiken.
    clearstatcache(true, $mailtekstenBestand);
    $geladenMailteksten = json_decode((string) file_get_contents($mailtekstenBestand), true, 16, JSON_THROW_ON_ERROR);
    foreach (['toelaten', 'uitnodigen', 'afwijzen'] as $mailtype) {
        if (!is_string($geladenMailteksten[$mailtype]['onderwerp'] ?? null) || !is_string($geladenMailteksten[$mailtype]['tekst'] ?? null)) {
            throw new RuntimeException('Mailtekst ' . $mailtype . ' ontbreekt of is ongeldig.');
        }
    }
    $mailteksten = $geladenMailteksten;
    $toelatingsOnderwerp = $mailteksten['toelaten']['onderwerp'];
    $toelatingsMail = $mailteksten['toelaten']['tekst'];
    $standaardOnderwerp = $mailteksten['uitnodigen']['onderwerp'];
    $standaardMail = $mailteksten['uitnodigen']['tekst'];
    $standaardAfwijzingsOnderwerp = $mailteksten['afwijzen']['onderwerp'];
    $standaardAfwijzingsMail = $mailteksten['afwijzen']['tekst'];
} catch (Throwable $e) {
    $melding = 'De JSON-mailteksten konden niet worden geladen; de standaardteksten worden gebruikt. ' . $e->getMessage();
}

$mailtekstenOpslaanGelukt = false;
if (($_POST['actie'] ?? '') === 'mailteksten_opslaan') {
    try {
        $backupBestandsnaam = null;
        $nieuweMailteksten = [];
        $ingediendeMailteksten = $_POST['mailteksten'] ?? null;
        if (!is_array($ingediendeMailteksten)) {
            throw new RuntimeException('De mailteksten ontbreken of hebben een ongeldig formaat.');
        }
        foreach (['toelaten', 'uitnodigen', 'afwijzen'] as $mailtype) {
            $ingediendeMailtekst = $ingediendeMailteksten[$mailtype] ?? null;
            if (!is_array($ingediendeMailtekst) || !is_string($ingediendeMailtekst['onderwerp'] ?? null) || !is_string($ingediendeMailtekst['tekst'] ?? null)) {
                throw new RuntimeException('Mailtekst ' . $mailtype . ' heeft een ongeldig formaat.');
            }
            $onderwerp = trim($ingediendeMailtekst['onderwerp']);
            $tekst = trim($ingediendeMailtekst['tekst']);
            if ($onderwerp === '' || $tekst === '') {
                throw new RuntimeException('Onderwerp en tekst zijn verplicht voor ' . $mailtype . '.');
            }
            if (strlen($onderwerp) > 255 || preg_match('/[\r\n]/', $onderwerp)) {
                throw new RuntimeException('Het onderwerp voor ' . $mailtype . ' is te lang of bevat een regeleinde.');
            }
            if (strlen($tekst) > 200000) {
                throw new RuntimeException('De tekst voor ' . $mailtype . ' is te groot.');
            }
            $nieuweMailteksten[$mailtype] = ['onderwerp' => $onderwerp, 'tekst' => $tekst];
        }
        $json = json_encode($nieuweMailteksten, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        // Bewaar eerst de laatst geldige versie; een mislukte write kan zo geen tekst vernietigen.
        clearstatcache(true, $mailtekstenBestand);
        if (is_file($mailtekstenBestand)) {
            $backupTijdstip = date('Ymd-His');
            $backupBestand = $mailtekstenMap . '/mailteksten-backup-' . $backupTijdstip . '.json';
            $backupVolgnummer = 1;
            while (file_exists($backupBestand)) {
                $backupBestand = $mailtekstenMap . '/mailteksten-backup-' . $backupTijdstip . '-' . $backupVolgnummer . '.json';
                $backupVolgnummer++;
            }
            if (!copy($mailtekstenBestand, $backupBestand)) {
                throw new RuntimeException('Backup van JSON/' . $mailtekstenBestandsnaam . ' kon niet worden gemaakt.');
            }
            $backupBestandsnaam = basename($backupBestand);
        }
        if (file_put_contents($mailtekstenBestand, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('JSON/' . $mailtekstenBestandsnaam . ' kon niet worden geschreven.');
        }
        $mailtekstenOpslaanGelukt = true;
        clearstatcache(true, $mailtekstenBestand);
        $mailtekstenGewijzigdOp = (new DateTimeImmutable('@' . filemtime($mailtekstenBestand)))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i:s');
        $mailteksten = $nieuweMailteksten;
        $toelatingsOnderwerp = $mailteksten['toelaten']['onderwerp'];
        $toelatingsMail = $mailteksten['toelaten']['tekst'];
        $standaardOnderwerp = $mailteksten['uitnodigen']['onderwerp'];
        $standaardMail = $mailteksten['uitnodigen']['tekst'];
        $standaardAfwijzingsOnderwerp = $mailteksten['afwijzen']['onderwerp'];
        $standaardAfwijzingsMail = $mailteksten['afwijzen']['tekst'];
        $melding = 'De drie mailteksten zijn opgeslagen in JSON/' . $mailtekstenBestandsnaam . '.';
        if ($backupBestandsnaam !== null) {
            $melding .= ' De vorige versie staat in JSON/' . $backupBestandsnaam . '.';
        }
    } catch (Throwable $e) {
        $melding = 'Mailteksten niet opgeslagen: ' . $e->getMessage();
    }
}

// Verwerk wijzigingen en verstuur alleen na expliciete keuze een mail.
if (isset($_POST['actie'], $_POST['deelnemer_id'], $_POST['activiteit_id'])) {
    $deelnemerId = (int) $_POST['deelnemer_id'];
    $activiteitId = (int) $_POST['activiteit_id'];
    $actie = (string) $_POST['actie'];
    $testModus = ($_POST['testmodus'] ?? '') === '1';
    $status = $_POST['status'] ?? null;
    $partijWaarde = $_POST['partij'] ?? '';
    $instrumentWaarde = $_POST['instrument_id'] ?? '0';

    // Controleer alle identifiers tegen de gegevens die deze beheerpagina zelf aanbiedt.
    $geldigeActiviteitIds = array_map('intval', array_column($activiteiten, 'id'));
    $geldigeInstrumentIds = array_map('intval', array_column($instrumenten, 'id'));
    if ($deelnemerId < 1 || !in_array($activiteitId, $geldigeActiviteitIds, true)) {
        http_response_code(400);
        exit('Ongeldige deelnemer of activiteit.');
    }
    if (!is_string($status) || !in_array($status, ['ja', 'nee', 'misschien'], true)) {
        http_response_code(400);
        exit('Ongeldige deelnemersstatus.');
    }
    if (!is_string($partijWaarde) || strlen($partijWaarde) > 100) {
        http_response_code(400);
        exit('Ongeldige of te lange partij-aanduiding.');
    }
    $partij = trim($partijWaarde) ?: null;
    if (!is_scalar($instrumentWaarde) || !preg_match('/^\d+$/', (string) $instrumentWaarde)) {
        http_response_code(400);
        exit('Ongeldig instrument.');
    }
    $instrumentId = (int) $instrumentWaarde ?: null;
    if ($instrumentId !== null && !in_array($instrumentId, $geldigeInstrumentIds, true)) {
        http_response_code(400);
        exit('Onbekend instrument.');
    }
    $stmt = $pdo->prepare('SELECT 1 FROM activiteit_deelnemers WHERE activiteit_id = ? AND deelnemer_id = ?');
    $stmt->execute([$activiteitId, $deelnemerId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        exit('Deze deelnemer is niet aan de gekozen activiteit gekoppeld.');
    }

    if ($actie === 'status_opslaan') {
        $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET instrument_id = ?, partij = ?, status = ? WHERE activiteit_id = ? AND deelnemer_id = ?');
        $stmt->execute([$instrumentId, $partij, $status, $activiteitId, $deelnemerId]);
        $melding = 'Status van deelnemer opgeslagen.';
    } elseif ($actie === 'toelating_intrekken') {
        $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET instrument_id = ?, partij = ?, status = ?, toegelaten = NULL WHERE activiteit_id = ? AND deelnemer_id = ?');
        $stmt->execute([$instrumentId, $partij, $status, $activiteitId, $deelnemerId]);
        $melding = 'Toelating van deelnemer ingetrokken; de deelnemer is weer onbeoordeeld.';
    } elseif ($actie === 'toelaten' && $status === 'ja') {
        $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET instrument_id = ?, partij = ?, status = ?, toegelaten = 1 WHERE activiteit_id = ? AND deelnemer_id = ?');
        $stmt->execute([$instrumentId, $partij, $status, $activiteitId, $deelnemerId]);
        $melding = 'Deelnemer toegelaten zonder e-mail.';
    } elseif (($actie === 'uitnodigen' && $status === 'misschien') || ($actie === 'afwijzen_met_mail' && in_array($status, ['ja', 'misschien'], true)) || ($actie === 'toelaten_met_mail' && in_array($status, ['ja', 'misschien'], true))) {
        $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET instrument_id = ?, partij = ?, status = ? WHERE activiteit_id = ? AND deelnemer_id = ?');
        $stmt->execute([$instrumentId, $partij, $status, $activiteitId, $deelnemerId]);
    }

    // Vul de gekozen mail in en pas de persoonlijke velden toe.
    if (($actie === 'uitnodigen' && $status === 'misschien') || ($actie === 'afwijzen_met_mail' && in_array($status, ['ja', 'misschien'], true)) || ($actie === 'toelaten_met_mail' && in_array($status, ['ja', 'misschien'], true))) {
        $stmt = $pdo->prepare('SELECT d.voornaam, d.achternaam, d.email, a.datum, a.plaats, a.omschrijving, ad.partij, i.naam AS instrument FROM activiteit_deelnemers ad JOIN deelnemers d ON d.id = ad.deelnemer_id JOIN activiteiten a ON a.id = ad.activiteit_id LEFT JOIN instrumenten i ON i.id = ad.instrument_id WHERE ad.activiteit_id = ? AND ad.deelnemer_id = ?');
        $stmt->execute([$activiteitId, $deelnemerId]);
        $speler = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$speler || !filter_var($speler['email'], FILTER_VALIDATE_EMAIL)) {
            $melding = 'Geen geldig e-mailadres voor deze speler.';
        } else {
            $mailtype = match ($actie) {
                'toelaten_met_mail' => 'toelating',
                'uitnodigen' => 'bevestiging',
                default => 'afwijzing',
            };
            $naam = htmlspecialchars($speler['voornaam'], ENT_QUOTES, 'UTF-8');
            $datum = date('d-m-Y', strtotime($speler['datum']));
            $partij = $speler['partij'] ? ' Je speelt partij ' . htmlspecialchars($speler['partij'], ENT_QUOTES, 'UTF-8') . '.' : '';
            $partijTekst = $speler['partij'] ? '&nbsp;' . htmlspecialchars($speler['partij'], ENT_QUOTES, 'UTF-8') : '';
            $smtpDebug = [];
            $mailer = null;
            try {
                if ($gmailAppWachtwoord === '') {
                    throw new RuntimeException('Gmail-app-wachtwoord ontbreekt in de configuratie.');
                }
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = 'send.one.com';
                $mailer->SMTPAuth = true;
                $mailer->Username = $gmailGebruikersnaam;
                $mailer->Password = $gmailAppWachtwoord;
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mailer->Port = 465;
                $mailer->Timeout = 5;
                $mailer->SMTPKeepAlive = false;
                $mailer->SMTPDebug = 0;
                $mailer->Debugoutput = static function (string $bericht) use (&$smtpDebug): void {
                    $smtpDebug[] = trim($bericht);
                };
                $mailer->CharSet = 'UTF-8';
                $mailer->setFrom($mailer->Username, 'Mozart op Zaterdag');
                $mailer->addReplyTo($mailer->Username, 'Mozart op Zaterdag');
                if ($testModus) {
                    $ontvangerEmail = 'dirkjan@pellegrina.net';
                    $mailer->addAddress($ontvangerEmail, 'Dirkjan Horringa');
                } else {
                    $ontvangerEmail = $speler['email'];
                    $mailer->addAddress($ontvangerEmail, trim($speler['voornaam'] . ' ' . $speler['achternaam']));
                    if (strcasecmp($speler['email'], 'dirkjan@pellegrina.net') !== 0) {
                        $mailer->addCC('dirkjan@pellegrina.net', 'Dirkjan Horringa');
                    }
                }
                $mailer->isHTML(true);
                $onderwerpWaarde = $_POST['mail_' . $mailtype . '_onderwerp'] ?? '';
                $mailWaarde = $_POST['mail_' . $mailtype . '_tekst'] ?? '';
                if (!is_string($onderwerpWaarde) || !is_string($mailWaarde)) {
                    throw new RuntimeException('De mailinhoud heeft een ongeldig formaat.');
                }
                $ingevuldOnderwerp = trim($onderwerpWaarde);
                $ingevuldeMail = trim($mailWaarde);
                if (strlen($ingevuldOnderwerp) > 255 || preg_match('/[\r\n]/', $ingevuldOnderwerp) || strlen($ingevuldeMail) > 200000) {
                    throw new RuntimeException('De mailinhoud is te lang of het onderwerp bevat een regeleinde.');
                }
                $standaardTekstVoorType = match ($mailtype) {
                    'toelating' => $toelatingsMail,
                    'bevestiging' => $standaardMail,
                    default => $standaardAfwijzingsMail,
                };
                $standaardOnderwerpVoorType = match ($mailtype) {
                    'toelating' => $toelatingsOnderwerp,
                    'bevestiging' => $standaardOnderwerp,
                    default => $standaardAfwijzingsOnderwerp,
                };
                $mailTekst = $ingevuldeMail ?: $standaardTekstVoorType;
                $mailOnderwerp = $ingevuldOnderwerp ?: $standaardOnderwerpVoorType;
                $plaats = $speler['plaats'] === 'Marnixzaal' ? 'Marnixzaal aan het Domplein' : $speler['plaats'];
                $aanmeldlink = 'https://mozartopzaterdag.nl/deelnemers_aanmelden.php?email=' . rawurlencode($speler['email']);
                $activiteitUrl = 'https://mozartopzaterdag.nl/' . date('Y-m-d', strtotime($speler['datum'])) . '/';
                $invoegcodes = ['{{voornaam}}', '{{achternaam}}', '{{datum}}', '{{plaats}}', '{{instrument}}', '{{partij_tekst}}', '{{omschrijving}}', '{{aanmeldlink}}', '{{activiteit_url}}', '{voornaam}', '{achternaam}', '{datum}', '{plaats}', '{instrument}', '{partij}'];
                $invoegwaarden = [$naam, htmlspecialchars($speler['achternaam'], ENT_QUOTES, 'UTF-8'), $datum, htmlspecialchars($plaats, ENT_QUOTES, 'UTF-8'), htmlspecialchars($speler['instrument'] ?? '', ENT_QUOTES, 'UTF-8'), $partijTekst, htmlspecialchars($speler['omschrijving'] ?? '', ENT_QUOTES, 'UTF-8'), $aanmeldlink, $activiteitUrl, $naam, htmlspecialchars($speler['achternaam'], ENT_QUOTES, 'UTF-8'), $datum, htmlspecialchars($plaats, ENT_QUOTES, 'UTF-8'), htmlspecialchars($speler['instrument'] ?? '', ENT_QUOTES, 'UTF-8'), $partij];
                $mailTekst = str_replace($invoegcodes, $invoegwaarden, $mailTekst);
                $mailOnderwerp = str_replace($invoegcodes, $invoegwaarden, $mailOnderwerp);
                $mailer->Subject = ($testModus ? '[TEST] ' : '') . html_entity_decode(strip_tags($mailOnderwerp), ENT_QUOTES, 'UTF-8');
                $mailer->Body = $mailTekst;
                $plainMailTekst = preg_replace('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $mailTekst);
                $mailer->AltBody = trim(html_entity_decode(strip_tags($plainMailTekst), ENT_QUOTES, 'UTF-8'));
                $trackingToken = bin2hex(random_bytes(32));
                if (!reserveerMailVerzending($pdo, $trackingToken, $activiteitId, $deelnemerId, $ontvangerEmail)) {
                    throw new RuntimeException('Aan ' . $ontvangerEmail . ' is in de afgelopen vijf minuten al een mail verzonden.');
                }
                $mailer->send();
                if ($actie === 'afwijzen_met_mail') {
                    $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET toegelaten = 0 WHERE activiteit_id = ? AND deelnemer_id = ?');
                    $stmt->execute([$activiteitId, $deelnemerId]);
                } elseif ($actie === 'toelaten_met_mail') {
                    $stmt = $pdo->prepare("UPDATE activiteit_deelnemers SET status = 'ja', toegelaten = 1 WHERE activiteit_id = ? AND deelnemer_id = ?");
                    $stmt->execute([$activiteitId, $deelnemerId]);
                }
                if ($testModus) {
                    $melding = 'Testmail alleen naar dirkjan@pellegrina.net verstuurd.';
                } elseif ($actie === 'toelaten_met_mail') {
                    $melding = 'Bevestigingsmail verstuurd met CC aan Dirkjan; deelnemer is toegelaten.';
                } elseif ($actie === 'uitnodigen') {
                    $melding = 'Uitnodiging verstuurd met CC aan Dirkjan; toelating blijft onbeoordeeld.';
                } else {
                    $melding = 'Afwijzingsmail verstuurd met CC aan Dirkjan; deelnemer is afgewezen.';
                }
            } catch (PHPMailer\PHPMailer\Exception $e) {
                $smtpFout = $mailer instanceof PHPMailer\PHPMailer\PHPMailer ? $mailer->ErrorInfo : implode(' | ', $smtpDebug);
                error_log('Beschikbaarheid: PHPMailer-fout: ' . $e->getMessage() . ' SMTP: ' . $smtpFout);
                $melding = 'Mail niet verstuurd. De technische details zijn vastgelegd in het serverlog.';
            } catch (RuntimeException $e) {
                $melding = 'Mail niet verstuurd: ' . $e->getMessage();
            } catch (Throwable $e) {
                error_log('Beschikbaarheid: onverwachte mailfout: ' . $e->getMessage() . ' SMTP: ' . implode(' | ', $smtpDebug));
                $melding = 'Mail niet verstuurd. De technische details zijn vastgelegd in het serverlog.';
            }
        }
    }
}

// Voorkom dat vernieuwen van de pagina dezelfde mutatie of mail nogmaals uitvoert.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $_SESSION['beschikbaarheid_melding'] = $melding;
    if ($mailtekstenOpslaanGelukt) {
        $_SESSION['beschikbaarheid_mailteksten_opgeslagen'] = true;
    }
    $redirectParameters = ['activiteit_id' => $activiteitId, 'toon' => $toonModus];
    header('Location: beschikbaarheid.php?' . http_build_query($redirectParameters));
    exit;
}

$gekozenActiviteit = null;
foreach ($activiteiten as $activiteit) if ((int) $activiteit['id'] === $activiteitId) $gekozenActiviteit = $activiteit;
// Laat beschikbare spelers zien en sorteer ze volgens de instrumentvolgorde.
$spelers = [];
if ($gekozenActiviteit) {
    if ($toonModus === 'toegelaten') {
        $stmt = $pdo->prepare("SELECT d.id, d.voornaam, d.achternaam, d.email, ad.status, ad.toegelaten, COALESCE(ad.instrument_id, di.instrument_id) AS instrument_id, ad.partij, i.naam AS instrument FROM activiteit_deelnemers ad JOIN deelnemers d ON d.id = ad.deelnemer_id LEFT JOIN (SELECT deelnemer_id, MIN(instrument_id) AS instrument_id FROM deelnemer_instrumenten GROUP BY deelnemer_id) di ON di.deelnemer_id = d.id LEFT JOIN instrumenten i ON i.id = COALESCE(ad.instrument_id, di.instrument_id) WHERE ad.activiteit_id = ? AND ad.status <> 'nee' AND ad.toegelaten = 1 ORDER BY CASE WHEN i.id IS NULL THEN 1 ELSE 0 END, CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN COALESCE((SELECT MIN(i2.id) FROM instrumenten i2 WHERE LOWER(TRIM(i2.naam)) LIKE 'trompet%'), i.id) ELSE i.id END, CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN 1 ELSE 0 END, CASE WHEN LOWER(COALESCE(ad.partij, '')) REGEXP 'concertmeester|aanvoerder' THEN 0 ELSE 1 END, CASE WHEN TRIM(COALESCE(ad.partij, '')) = '' THEN 1 ELSE 0 END, ad.partij, d.achternaam, d.voornaam");
    } else {
        $stmt = $pdo->prepare("SELECT d.id, d.voornaam, d.achternaam, d.email, ad.status, ad.toegelaten, COALESCE(ad.instrument_id, di.instrument_id) AS instrument_id, ad.partij, i.naam AS instrument FROM activiteit_deelnemers ad JOIN deelnemers d ON d.id = ad.deelnemer_id LEFT JOIN (SELECT deelnemer_id, MIN(instrument_id) AS instrument_id FROM deelnemer_instrumenten GROUP BY deelnemer_id) di ON di.deelnemer_id = d.id LEFT JOIN instrumenten i ON i.id = COALESCE(ad.instrument_id, di.instrument_id) WHERE ad.activiteit_id = ? AND ad.status <> 'nee' AND ad.status IN ('ja', 'misschien') ORDER BY CASE WHEN i.id IS NULL THEN 1 ELSE 0 END, CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN COALESCE((SELECT MIN(i2.id) FROM instrumenten i2 WHERE LOWER(TRIM(i2.naam)) LIKE 'trompet%'), i.id) ELSE i.id END, CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN 1 ELSE 0 END, CASE WHEN LOWER(COALESCE(ad.partij, '')) REGEXP 'concertmeester|aanvoerder' THEN 0 ELSE 1 END, CASE WHEN TRIM(COALESCE(ad.partij, '')) = '' THEN 1 ELSE 0 END, ad.partij, d.achternaam, d.voornaam");
    }
    $stmt->execute([$activiteitId]);
    $spelers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$onbeoordeeldeDeelnemers = array_map('intval', array_column(array_filter($spelers, static fn (array $speler): bool => $speler['toegelaten'] === null), 'id'));
$countToegeilaten = count(array_filter($spelers, static fn (array $speler): bool => (int) $speler['toegelaten'] === 1));
$countJaMisschien = count($spelers);
$vulMailTemplate = static function (string $template, array $speler, array $activiteit): string {
    $voornaam = htmlspecialchars($speler['voornaam'] ?? '', ENT_QUOTES, 'UTF-8');
    $achternaam = htmlspecialchars($speler['achternaam'] ?? '', ENT_QUOTES, 'UTF-8');
    $datum = date('d-m-Y', strtotime($activiteit['datum']));
    $plaats = ($activiteit['plaats'] ?? '') === 'Marnixzaal' ? 'Marnixzaal aan het Domplein' : ($activiteit['plaats'] ?? '');
    $plaats = htmlspecialchars($plaats, ENT_QUOTES, 'UTF-8');
    $instrument = htmlspecialchars($speler['instrument'] ?? '', ENT_QUOTES, 'UTF-8');
    $partij = $speler['partij'] ?? '';
    $partijTekst = $partij !== '' ? '&nbsp;' . htmlspecialchars($partij, ENT_QUOTES, 'UTF-8') : '';
    $partijVolzin = $partij !== '' ? ' Je speelt partij ' . htmlspecialchars($partij, ENT_QUOTES, 'UTF-8') . '.' : '';
    $omschrijving = htmlspecialchars($activiteit['omschrijving'] ?? '', ENT_QUOTES, 'UTF-8');
    $aanmeldlink = 'https://mozartopzaterdag.nl/deelnemers_aanmelden.php?email=' . rawurlencode($speler['email'] ?? '');
    $activiteitUrl = 'https://mozartopzaterdag.nl/' . date('Y-m-d', strtotime($activiteit['datum'])) . '/';

    return str_replace(
        ['{{voornaam}}', '{{achternaam}}', '{{datum}}', '{{plaats}}', '{{instrument}}', '{{partij_tekst}}', '{{omschrijving}}', '{{aanmeldlink}}', '{{activiteit_url}}', '{{partij}}', '{voornaam}', '{achternaam}', '{datum}', '{plaats}', '{instrument}', '{partij}'],
        [$voornaam, $achternaam, $datum, $plaats, $instrument, $partijTekst, $omschrijving, $aanmeldlink, $activiteitUrl, $partijVolzin, $voornaam, $achternaam, $datum, $plaats, $instrument, $partijVolzin],
        $template
    );
};
?>
<!DOCTYPE html>
<html lang="nl"><head><meta charset="UTF-8"><title>Beschikbaarheid</title><link href="/css/moz.css" rel="stylesheet" type="text/css"><style>
.tabel-scroll{max-height:90vh;overflow:auto}.tabel-scroll th{position:sticky;top:0;z-index:2;background:#fff}.tabel-scroll th:first-child{position:sticky;left:0;z-index:3;background:#fff}.tabel-scroll td:first-child{position:sticky;left:0;z-index:1;background:#fff}.mail-knop{margin:.15em}.toegelaten-vinkje{color:#198754;font-size:1.25em;font-weight:bold;margin-left:.35em}details.mail-editor{display:inline-block;margin:.15em 0;vertical-align:middle}details.mail-editor summary{display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;height:2.2em;min-width:4.5em;padding:0 .6em;border-radius:2px;list-style:none;box-sizing:border-box}details.mail-editor summary::-webkit-details-marker{display:none}.mail-bewerk-groen summary{background:#198754}.mail-bewerk-rood summary{background:#dc3545}details.mail-editor[open] > :not(summary){position:fixed;left:50%;transform:translateX(-50%);z-index:10;width:min(90vw,700px);box-sizing:border-box}details.mail-editor[open] > input{top:12vh;padding:.6em;background:#fff;border:1px solid #777}details.mail-editor[open] > textarea{top:calc(12vh + 3.6em);height:65vh;padding:.6em;background:#fff;border:1px solid #777;resize:vertical}
.mail-modal { display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
.mail-modal.active { display: flex; }
.mail-modal-content { background: #fff; border-radius: 4px; box-sizing: border-box; width: min(90vw, 700px); max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
.mail-modal-header { padding: 1em; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background: #f9f9f9; }
.mail-modal-header h3 { margin: 0; font-size: 1.1em; }
.mail-modal-close { background: #dc3545; color: white; border: none; width: 2.2em; height: 2.2em; border-radius: 50%; cursor: pointer; font-size: 1.2em; display: flex; align-items: center; justify-content: center; }
.mail-modal-close:hover { background: #c82333; }
.mail-modal-body { padding: 1em; overflow-y: auto; flex: 1; }
.mail-modal-body > input { width: 100%; margin-bottom: 1em; padding: 0.6em; border: 1px solid #777; box-sizing: border-box; }
.mail-modal-body > textarea { width: 100%; padding: 0.6em; border: 1px solid #777; box-sizing: border-box; resize: vertical; min-height: 300px; }
.mail-modal-footer { padding: 1em; border-top: 1px solid #ddd; display: flex; gap: 0.5em; justify-content: flex-end; background: #f9f9f9; }
.mail-modal-footer button { padding: 0.6em 1.2em; cursor: pointer; border-radius: 2px; border: none; font-size: 0.95em; }
.mail-modal-footer .mail-submit { background: #198754; color: white; }
.mail-modal-footer .mail-submit:hover { background: #157347; }
.mail-modal-footer .mail-cancel { background: #6c757d; color: white; }
.mail-modal-footer .mail-cancel:hover { background: #5c636a; }
td.acties { min-width: 32em; white-space: nowrap; }
td.acties > .mail-knop, td.acties > details { display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; height: 2.2em; padding: 0; line-height: 1; box-sizing: border-box; }
.tabel-scroll tr > td:last-child > .mail-knop { padding-left: 8px; padding-right: 8px; }
.tabel-scroll tr > td:last-child { min-width: 32em; white-space: nowrap; }
.tabel-scroll tr > td:last-child > .mail-knop, .tabel-scroll tr > td:last-child > details { display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; height: 2.2em; padding: 0 8px; line-height: 1; box-sizing: border-box; }
select[name="instrument_id"], select[name="status"], input[name="partij"] { background-color: transparent; }
</style>
<style>.deelnemer-id{display:inline-block;margin-right:.4em;color:#777;font-size:.75em}.toegelaten-vinkje,.afgewezen-kruis,.onbeoordeeld-vraagteken{display:inline-flex;align-items:center;justify-content:center;width:1.35em;height:1.35em;margin-left:.35em;border-radius:50%;color:#fff;font-size:1em;font-weight:bold;line-height:1}.toegelaten-vinkje{background:#198754}.afgewezen-kruis{background:#dc3545}.onbeoordeeld-vraagteken{background:#ff9800}.mailteksten-beheer{margin:0 0 1em;border:1px solid #bbb}.mailteksten-beheer>summary{padding:.75em;cursor:pointer;font-weight:bold;background:#f1f1f1}.mailteksten-formulier{padding:1em}.mailtekst-sectie+ .mailtekst-sectie{margin-top:1.5em;padding-top:1.5em;border-top:1px solid #ccc}.mailtekst-sectie h4{margin:0 0 .75em}.mailtekst-sectie label{display:block;margin:.5em 0 .25em;font-weight:bold}.mailtekst-sectie input{width:100%;box-sizing:border-box}.mailtekst-hulp{margin:.5em 0 1em;color:#555}</style>
<script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var scrollSleutel = 'beschikbaarheid-scroll-' + window.location.pathname;
    var opgeslagenScrollPositie = sessionStorage.getItem(scrollSleutel);
    if (opgeslagenScrollPositie !== null) {
        sessionStorage.removeItem(scrollSleutel);
        requestAnimationFrame(function () {
            var positie = JSON.parse(opgeslagenScrollPositie);
            window.scrollTo(0, positie.windowY || 0);
            var tabelScroll = document.querySelector('.tabel-scroll');
            if (tabelScroll) {
                tabelScroll.scrollTop = positie.tabelY || 0;
                tabelScroll.scrollLeft = positie.tabelX || 0;
                var bewerkteInvoer = Array.from(tabelScroll.querySelectorAll('input[name="deelnemer_id"]')).find(function (invoer) {
                    return invoer.value === String(positie.deelnemerId);
                });
                var bewerkteRij = bewerkteInvoer?.closest('tr');
                if (bewerkteRij && typeof positie.rijOffset === 'number') {
                    tabelScroll.scrollTop += bewerkteRij.getBoundingClientRect().top - tabelScroll.getBoundingClientRect().top - positie.rijOffset;
                }
            }
        });
    }
    var bewaarScrollPositie = function (bewerkteRij) {
        var tabelScroll = document.querySelector('.tabel-scroll');
        var deelnemer = bewerkteRij?.querySelector('input[name="deelnemer_id"]');
        sessionStorage.setItem(scrollSleutel, JSON.stringify({
            windowY: window.scrollY,
            tabelY: tabelScroll ? tabelScroll.scrollTop : 0,
            tabelX: tabelScroll ? tabelScroll.scrollLeft : 0,
            deelnemerId: deelnemer?.value || null,
            rijOffset: tabelScroll && bewerkteRij ? bewerkteRij.getBoundingClientRect().top - tabelScroll.getBoundingClientRect().top : null
        }));
    };
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            bewaarScrollPositie(form.closest('tr'));
        });
    });

    var mailtekstenBeheer = document.getElementById('mailteksten-beheer');
    var mailtekstenEditorsGestart = false;
    var startMailtekstenEditors = function () {
        if (mailtekstenEditorsGestart || typeof CKEDITOR === 'undefined') return;
        mailtekstenEditorsGestart = true;
        document.querySelectorAll('.mailtekst-editor').forEach(function (editor) {
            CKEDITOR.replace(editor.id, {
                toolbar: 'Full',
                height: 260,
                versionCheck: false,
                allowedContent: true,
                removePlugins: 'cloudservices,easyimage,exportpdf'
            });
        });
    };
    if (mailtekstenBeheer?.open) startMailtekstenEditors();
    mailtekstenBeheer?.addEventListener('toggle', function () {
        if (mailtekstenBeheer.open) startMailtekstenEditors();
    });
    document.getElementById('mailteksten-formulier')?.addEventListener('submit', function () {
        if (typeof CKEDITOR === 'undefined') return;
        Object.keys(CKEDITOR.instances).forEach(function (naam) {
            CKEDITOR.instances[naam].updateElement();
        });
    });

    var testModusKnop = document.getElementById('testmodus-knop');
    testModusKnop?.addEventListener('click', function () {
        var actief = testModusKnop.dataset.actief !== '1';
        testModusKnop.dataset.actief = actief ? '1' : '0';
        testModusKnop.setAttribute('aria-pressed', actief ? 'true' : 'false');
        testModusKnop.textContent = actief
            ? 'Testmodus aan: mails alleen naar Dirkjan'
            : 'Testmodus uit';
        testModusKnop.classList.toggle('w3-orange', actief);
        testModusKnop.classList.toggle('w3-light-grey', !actief);
    });

    document.querySelectorAll('.tabel-scroll tr').forEach(function (rij) {
        var naam = rij.querySelector('td:first-child');
        var deelnemer = rij.querySelector('input[name="deelnemer_id"]');
            var modalKnop = rij.querySelector('.mail-modal-btn');
            if (modalKnop && naam) {
                var modalOnderdelen = modalKnop.dataset.modal.split('-');
                var deelnemerId = Number(modalOnderdelen[modalOnderdelen.length - 1]);
            // Markeer onbeoordeelde deelnemers met oranje vraagteken
            if (<?= json_encode($onbeoordeeldeDeelnemers) ?>.includes(deelnemerId) && !naam.querySelector('.onbeoordeeld-vraagteken')) {
                var vraagteken = document.createElement('span');
                vraagteken.className = 'onbeoordeeld-vraagteken';
                vraagteken.title = 'Nog niet beoordeeld';
                vraagteken.setAttribute('aria-label', 'Nog niet beoordeeld');
                vraagteken.textContent = '?';
                naam.appendChild(vraagteken);
            }
        }
    });
    
    // Open het juiste mailvenster voor de gekozen deelnemer.
    document.querySelectorAll('.mail-modal-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var rij = btn.closest('tr');
            var form = rij.querySelector('form');
            var modalId = btn.dataset.modal;
            var modal = document.getElementById(modalId);
            var mailType = btn.dataset.type;
            
            if (!modal) return;
            modal.mailRow = rij;
            
            // Bewaar de rij zodat alleen de bijbehorende deelnemergegevens worden verstuurd.
            modal.classList.add('active');
        });
    });

    // Verstuur de mailtekst uit de modal via het formulier van de deelnemer.
    document.querySelectorAll('.mail-modal-submit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = btn.closest('.mail-modal');
            var rij = modal.mailRow;
            if (!rij) return;

            var form = document.createElement('form');
            form.method = 'post';
            form.action = window.location.href;
            var velden = {
                activiteit_id: rij.querySelector('input[name="activiteit_id"]')?.value || '',
                deelnemer_id: rij.querySelector('input[name="deelnemer_id"]')?.value || '',
                instrument_id: rij.querySelector('[name="instrument_id"]')?.value || '',
                status: rij.querySelector('[name="status"]')?.value || '',
                partij: rij.querySelector('[name="partij"]')?.value || '',
                actie: btn.dataset.action,
                testmodus: testModusKnop?.dataset.actief === '1' ? '1' : '0',
                csrf_token: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            };
            velden['mail_' + btn.dataset.type + '_onderwerp'] = modal.querySelector('.mail-modal-onderwerp').value;
            velden['mail_' + btn.dataset.type + '_tekst'] = modal.querySelector('.mail-modal-tekst').value;
            Object.keys(velden).forEach(function (naam) {
                var veld = document.createElement('input');
                veld.type = 'hidden';
                veld.name = naam;
                veld.value = velden[naam];
                form.appendChild(veld);
            });
            document.body.appendChild(form);
            bewaarScrollPositie(rij);
            form.submit();
        });
    });
    
    // Sluit een mailvenster met de sluit- of annuleerknop.
    document.querySelectorAll('.mail-modal-close, .mail-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = btn.closest('.mail-modal');
            modal.classList.remove('active');
        });
    });
    
    // Sluit een mailvenster ook bij een klik op de verduisterde achtergrond.
    document.querySelectorAll('.mail-modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.mail-modal.active').forEach(function (modal) {
            modal.classList.remove('active');
        });
    }
});
</script>
 </head><body><div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1400px"><h3>Beschikbaarheid</h3>
<p class="mailtekst-hulp">Mailteksten <?= $mailtekstenZojuistOpgeslagen ? 'opgeslagen' : 'geladen' ?>: <strong>JSON/<?= htmlspecialchars($mailtekstenBestandsnaam, ENT_QUOTES, 'UTF-8') ?></strong> (versie <?= htmlspecialchars($mailtekstenGewijzigdOp, ENT_QUOTES, 'UTF-8') ?>)</p>
<details id="mailteksten-beheer" class="mailteksten-beheer">
<summary>Mailteksten bewerken</summary>
<form id="mailteksten-formulier" class="mailteksten-formulier" method="post">
<input type="hidden" name="actie" value="mailteksten_opslaan">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<p class="mailtekst-hulp">In onderwerp en mailtekst beschikbare invoegcodes: {{voornaam}}, {{achternaam}}, {{datum}}, {{plaats}}, {{instrument}}, {{partij_tekst}}, {{omschrijving}}, {{activiteit_url}} en {{aanmeldlink}}.</p>
<?php foreach (['toelaten' => 'Toelaten', 'uitnodigen' => 'Uitnodigen', 'afwijzen' => 'Afwijzen'] as $mailtype => $mailtypeLabel): ?>
<section class="mailtekst-sectie">
<h4><?= $mailtypeLabel ?></h4>
<label for="mailtekst-<?= $mailtype ?>-onderwerp">Onderwerp</label>
<input class="w3-input w3-border" id="mailtekst-<?= $mailtype ?>-onderwerp" name="mailteksten[<?= $mailtype ?>][onderwerp]" value="<?= htmlspecialchars($mailteksten[$mailtype]['onderwerp'], ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required>
<label for="mailtekst-<?= $mailtype ?>">Mailtekst</label>
<textarea class="mailtekst-editor" id="mailtekst-<?= $mailtype ?>" name="mailteksten[<?= $mailtype ?>][tekst]" required><?= htmlspecialchars($mailteksten[$mailtype]['tekst'], ENT_QUOTES, 'UTF-8') ?></textarea>
</section>
<?php endforeach; ?>
<button class="w3-button w3-blue w3-margin-top" type="submit">Alle mailteksten opslaan</button>
</form>
</details>
<button id="testmodus-knop" class="w3-button w3-light-grey w3-margin-bottom" type="button" data-actief="0" aria-pressed="false">Testmodus uit</button>
<?php if ($melding !== ''): ?><p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p><?php endif; ?>
<form method="get"><label for="activiteit_id">Activiteit:</label><select class="w3-select" id="activiteit_id" name="activiteit_id" onchange="this.form.submit()" style="max-width:32em;display:inline-block"><?php foreach ($activiteiten as $activiteit): ?><option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>><?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?></option><?php endforeach; ?></select></form>
<?php if ($gekozenActiviteit): ?><p><?= htmlspecialchars($gekozenActiviteit['omschrijving'] ?? '') ?></p>
<?php $toggleWaarde = $toonModus === 'toegelaten' ? 'ja_misschien' : 'toegelaten'; $toggleTekst = $toonModus === 'toegelaten' ? 'Toon: ja/misschien (alle) (' . $countJaMisschien . ')' : 'Toon: alleen toegelaten (' . $countToegeilaten . ')'; $toggleButtonClass = $toonModus === 'toegelaten' ? 'w3-button w3-small w3-border w3-green' : 'w3-button w3-small w3-border w3-light-grey'; ?>
<form method="get" style="margin:0 0 12px"><input type="hidden" name="activiteit_id" value="<?= (int) $activiteitId ?>"><button class="<?= $toggleButtonClass ?>" type="submit" name="toon" value="<?= htmlspecialchars($toggleWaarde, ENT_QUOTES, 'UTF-8') ?>" title="<?= $toonModus === 'toegelaten' ? 'Klik om alle ja/misschien deelnemers te tonen' : 'Klik om alleen toegelaten deelnemers te tonen' ?>"><?= htmlspecialchars($toggleTekst) ?></button></form>
<div class="tabel-scroll"><table class="w3-table w3-bordered w3-striped w3-small"><tr><th>Speler</th><th>Instrument</th><th>Status</th><th>Partij</th><th>Acties</th></tr>
<?php foreach ($spelers as $speler): ?><tr><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>"><input type="hidden" name="deelnemer_id" value="<?= (int) $speler['id'] ?>"><td><span class="deelnemer-id"><?= (int) $speler['id'] ?></span><?= htmlspecialchars($speler['voornaam'] . ' ' . $speler['achternaam']) ?><?php if ((int) $speler['toegelaten'] === 1): ?><span class="toegelaten-vinkje" title="Toegelaten" aria-label="Toegelaten">&#10003;</span><?php elseif (in_array($speler['toegelaten'], [0, '0'], true)): ?><span class="afgewezen-kruis" title="Afgewezen" aria-label="Afgewezen">&#10005;</span><?php endif; ?></td><td><select class="w3-select" name="instrument_id"><option value="0">(onbekend)</option><?php foreach ($instrumenten as $instrument): ?><option value="<?= (int) $instrument['id'] ?>" <?= (int) $speler['instrument_id'] === (int) $instrument['id'] ? 'selected' : '' ?>><?= htmlspecialchars($instrument['naam']) ?></option><?php endforeach; ?></select></td><td><select class="w3-select" name="status" onchange="this.form.querySelector('.status-opslaan-knop').click()"><?php foreach (['ja', 'misschien', 'nee'] as $status): ?><option value="<?= $status ?>" <?= $speler['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></td><td><input class="w3-input" type="text" name="partij" value="<?= htmlspecialchars($speler['partij'] ?? '') ?>" maxlength="100" placeholder="bijv. 1" style="width:8em"></td><td><button class="status-opslaan-knop" type="submit" name="actie" value="status_opslaan" hidden></button><?php if ($speler['status'] === 'ja'): ?><button class="w3-button w3-green w3-small mail-knop" type="submit" name="actie" value="toelaten" formnovalidate>Toelaten</button><?php elseif ($speler['status'] === 'misschien'): ?><button class="w3-button w3-small mail-knop mail-modal-btn" type="button" data-modal="modal-bevestiging-<?= (int) $speler['id'] ?>" data-type="bevestiging" style="background:#198754;color:white">Uitnodigen</button><?php endif; ?><button class="w3-button w3-small mail-knop mail-modal-btn" type="button" data-modal="modal-toelating-<?= (int) $speler['id'] ?>" data-type="toelating" style="background:#198754;color:white">Toelaten met mail</button><button class="w3-button w3-small mail-knop mail-modal-btn" type="button" data-modal="modal-afwijzing-<?= (int) $speler['id'] ?>" data-type="afwijzing" style="background:#dc3545;color:white">Afwijzen</button><?php if ((int) $speler['toegelaten'] === 1): ?><button class="w3-button w3-orange w3-small mail-knop" type="submit" name="actie" value="toelating_intrekken" formnovalidate onclick="return confirm('De toelating van deze deelnemer intrekken zonder e-mail?')">Toelating intrekken</button><?php endif; ?><input type="hidden" name="mail_bevestiging_onderwerp" value="<?= htmlspecialchars($standaardOnderwerp) ?>"><textarea style="display:none" name="mail_bevestiging_tekst"><?= htmlspecialchars($standaardMail) ?></textarea><input type="hidden" name="mail_afwijzing_onderwerp" value="<?= htmlspecialchars($standaardAfwijzingsOnderwerp) ?>"><textarea style="display:none" name="mail_afwijzing_tekst"><?= htmlspecialchars($standaardAfwijzingsMail) ?></textarea></td></form></tr><?php endforeach; ?></table></div>

<!-- Mail modals -->
<?php foreach ($spelers as $speler): ?>
<?php $popupToelating = $vulMailTemplate($toelatingsMail, $speler, $gekozenActiviteit); $popupBevestiging = $vulMailTemplate($standaardMail, $speler, $gekozenActiviteit); $popupAfwijzing = $vulMailTemplate($standaardAfwijzingsMail, $speler, $gekozenActiviteit); ?>
<div id="modal-toelating-<?= (int) $speler['id'] ?>" class="mail-modal">
    <div class="mail-modal-content">
        <div class="mail-modal-header">
            <h3>Bevestigingsmail voor <?= htmlspecialchars($speler['voornaam'] . ' ' . $speler['achternaam']) ?></h3>
            <button class="mail-modal-close" type="button" title="Sluiten">✕</button>
        </div>
        <div class="mail-modal-body">
            <input class="mail-modal-onderwerp" type="text" value="<?= htmlspecialchars($toelatingsOnderwerp) ?>" placeholder="Onderwerp">
            <textarea class="mail-modal-tekst" placeholder="Mailtekst"><?= htmlspecialchars($popupToelating) ?></textarea>
        </div>
        <div class="mail-modal-footer">
            <button class="mail-modal-submit" type="button" data-type="toelating" data-action="toelaten_met_mail">Toelaten en bevestiging versturen</button>
            <button class="mail-cancel" type="button">Annuleren</button>
        </div>
    </div>
</div>

<div id="modal-bevestiging-<?= (int) $speler['id'] ?>" class="mail-modal">
  <div class="mail-modal-content">
    <div class="mail-modal-header">
    <h3>Uitnodigingsmail voor <?= htmlspecialchars($speler['voornaam'] . ' ' . $speler['achternaam']) ?></h3>
      <button class="mail-modal-close" type="button" title="Sluiten">✕</button>
    </div>
    <div class="mail-modal-body">
      <input class="mail-modal-onderwerp" type="text" value="<?= htmlspecialchars($standaardOnderwerp) ?>" placeholder="Onderwerp">
    <textarea class="mail-modal-tekst" placeholder="Mailtekst"><?= htmlspecialchars($popupBevestiging) ?></textarea>
    </div>
    <div class="mail-modal-footer">
            <button class="mail-modal-submit" type="button" data-type="bevestiging" data-action="uitnodigen">Uitnodiging versturen</button>
      <button class="mail-cancel" type="button">Annuleren</button>
    </div>
  </div>
</div>

<div id="modal-afwijzing-<?= (int) $speler['id'] ?>" class="mail-modal">
  <div class="mail-modal-content">
    <div class="mail-modal-header">
      <h3>Afwijzingsmail voor <?= htmlspecialchars($speler['voornaam'] . ' ' . $speler['achternaam']) ?></h3>
      <button class="mail-modal-close" type="button" title="Sluiten">✕</button>
    </div>
    <div class="mail-modal-body">
      <input class="mail-modal-onderwerp" type="text" value="<?= htmlspecialchars($standaardAfwijzingsOnderwerp) ?>" placeholder="Onderwerp">
    <textarea class="mail-modal-tekst" placeholder="Mailtekst"><?= htmlspecialchars($popupAfwijzing) ?></textarea>
    </div>
    <div class="mail-modal-footer">
            <button class="mail-modal-submit" type="button" data-type="afwijzing" data-action="afwijzen_met_mail">Afwijzing versturen</button>
      <button class="mail-cancel" type="button">Annuleren</button>
    </div>
  </div>
</div>
<?php endforeach; ?><?php else: ?><p>Er zijn geen toekomstige activiteiten.</p><?php endif; ?></div></body></html>
