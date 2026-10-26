<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/mail_tracking.php';
require_once __DIR__ . '/../includes/deelnemers_mail.inc.php';

zorgVoorMailTrackingTabel($pdo);

$melding = '';
$resultaten = [];
$actie = $_POST['actie'] ?? '';

if ($actie === 'json_laden') {
    $gevraagdeActiviteitId = (int) ($_POST['activiteit_id'] ?? 0);
    $gevraagdeDoelgroep = $_POST['doelgroep'] ?? 'toegelaten';
    $stmt = $pdo->prepare('SELECT id, datum FROM activiteiten WHERE id = ?');
    $stmt->execute([$gevraagdeActiviteitId]);
    $gevraagdeActiviteit = $stmt->fetch(PDO::FETCH_ASSOC);
    $conceptBestand = is_array($gevraagdeActiviteit)
        ? serverConceptBestand($gevraagdeActiviteit, $gevraagdeDoelgroep, (string) ($_POST['server_concept'] ?? ''))
        : null;

    if ($conceptBestand === null || !is_file($conceptBestand)) {
        $melding = 'Kies een geldig serverconcept om te laden.';
    } else {
        try {
            // Controleer eerst schema en veldtypen; pas daarna mogen conceptwaarden de formulierinvoer vervangen.
            $concept = json_decode((string) file_get_contents($conceptBestand), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($concept) || ($concept['versie'] ?? null) !== 1 || !is_string($concept['onderwerp'] ?? null) || !is_string($concept['bericht'] ?? null)) {
                throw new RuntimeException('Onbekend of onvolledig mailconcept.');
            }
            $_POST['activiteit_id'] = (int) ($concept['activiteit_id'] ?? 0);
            $_POST['doelgroep'] = $concept['doelgroep'] ?? 'toegelaten';
            $_POST['onderwerp'] = $concept['onderwerp'];
            $_POST['bericht'] = $concept['bericht'];
            $melding = 'Het geselecteerde serverconcept is geladen.';
        } catch (Throwable $e) {
            $melding = 'Mailconcept niet geladen: ' . $e->getMessage();
        }
    }
}

$activiteitId = (int) ($_POST['activiteit_id'] ?? $_GET['activiteit_id'] ?? 0);
$activiteiten = $pdo->query(
    'SELECT id, datum, plaats, omschrijving FROM activiteiten ORDER BY datum ASC'
)->fetchAll(PDO::FETCH_ASSOC);
if ($activiteitId === 0 && $activiteiten !== []) {
    $activiteitId = (int) $activiteiten[0]['id'];
}

$gekozenActiviteit = null;
foreach ($activiteiten as $activiteit) {
    if ((int) $activiteit['id'] === $activiteitId) {
        $gekozenActiviteit = $activiteit;
        break;
    }
}

// Activiteitsgebonden mails gebruiken de actuele indeling; overige doelgroepen komen uit deelnemersvoorkeuren.
$doelgroep = $_POST['doelgroep'] ?? $_GET['doelgroep'] ?? 'toegelaten';
$halfjaren = mailHalfjaren(new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')));
$doelgroepen = [
    'toegelaten' => 'Toegelaten deelnemers voor deze activiteit',
    'alle_deelnemers' => 'Alle deelnemers',
    'strijkers' => 'Strijkers',
    'houtblazers' => 'Houtblazers',
    'koperblazers' => 'Koperblazers',
    'overig' => 'Overig',
];
foreach ($halfjaren as $sleutel => $halfjaar) {
    $doelgroepen[$sleutel] = $halfjaar['label'];
}
if (!array_key_exists($doelgroep, $doelgroepen)) {
    $doelgroep = 'toegelaten';
}

$deelnemers = [];
$mailPeriode = $halfjaren[$doelgroep] ?? null;
if ($mailPeriode !== null) {
    $gekozenActiviteit = null;
    foreach ($activiteiten as $activiteit) {
        if ($activiteit['datum'] >= $mailPeriode['start'] && $activiteit['datum'] < $mailPeriode['einde']) {
            $gekozenActiviteit = $activiteit;
            $activiteitId = (int) $activiteit['id'];
            break;
        }
    }
    $deelnemers = laadHalfjaarMailDeelnemers($pdo, $mailPeriode);
} elseif ($doelgroep === 'toegelaten' && $gekozenActiviteit !== null) {
    // Deze query gebruikt de concrete activiteitspartij; daarmee krijgt elke deelnemer de juiste mailpersonalisatie.
    $stmt = $pdo->prepare(
        "SELECT d.id, d.voornaam, d.achternaam, d.email, i.naam AS instrument, ad.partij
         FROM activiteit_deelnemers ad
         JOIN deelnemers d ON d.id = ad.deelnemer_id
         LEFT JOIN instrumenten i ON i.id = ad.instrument_id
         WHERE ad.activiteit_id = ? AND ad.toegelaten = 1
         ORDER BY CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN COALESCE((SELECT MIN(i2.id) FROM instrumenten i2 WHERE LOWER(TRIM(i2.naam)) LIKE 'trompet%'), i.id) ELSE i.id END,
                  CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN 1 ELSE 0 END,
                  i.id, d.achternaam, d.voornaam"
    );
    $stmt->execute([$activiteitId]);
    $deelnemers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($doelgroep !== 'toegelaten') {
    $familieInstrumenten = [
        'strijkers' => ['viool', 'altviool', 'cello', 'contrabas'],
        'houtblazers' => ['dwarsfluit', 'piccolo', 'hobo', 'engelse hoorn', 'klarinet', 'basklarinet', 'fagot', 'contrafagot'],
        'koperblazers' => ['trompet', 'hoorn', 'trombone', 'tuba'],
    ];
    $alleFamilieInstrumenten = array_merge(...array_values($familieInstrumenten));
    // Bij algemene doelgroepen kunnen deelnemers meerdere voorkeuren hebben; GROUP_CONCAT houdt ze in één rij.
    $sql = "SELECT d.id, d.voornaam, d.achternaam, d.email,
                   GROUP_CONCAT(DISTINCT i.naam ORDER BY i.naam SEPARATOR ', ') AS instrument,
                   NULL AS partij
            FROM deelnemers d
            LEFT JOIN deelnemer_instrumenten di ON di.deelnemer_id = d.id
            LEFT JOIN instrumenten i ON i.id = di.instrument_id";
    $parameters = [];
    if ($doelgroep === 'alle_deelnemers') {
        $sql .= ' WHERE 1 = 1';
    } elseif ($doelgroep === 'overig') {
        $plaatshouders = implode(',', array_fill(0, count($alleFamilieInstrumenten), '?'));
        $sql .= " WHERE NOT EXISTS (SELECT 1 FROM deelnemer_instrumenten di2 JOIN instrumenten i2 ON i2.id = di2.instrument_id WHERE di2.deelnemer_id = d.id AND LOWER(TRIM(i2.naam)) IN ($plaatshouders))";
        $parameters = $alleFamilieInstrumenten;
    } else {
        $plaatshouders = implode(',', array_fill(0, count($familieInstrumenten[$doelgroep]), '?'));
        $sql .= " WHERE EXISTS (SELECT 1 FROM deelnemer_instrumenten di2 JOIN instrumenten i2 ON i2.id = di2.instrument_id WHERE di2.deelnemer_id = d.id AND LOWER(TRIM(i2.naam)) IN ($plaatshouders))";
        $parameters = $familieInstrumenten[$doelgroep];
    }
    $sql .= ' GROUP BY d.id, d.voornaam, d.achternaam, d.email ORDER BY d.achternaam, d.voornaam';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);
    $deelnemers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$instrumentKeuzes = [];
foreach ($deelnemers as $deelnemer) {
    // Splits samengevoegde voorkeuren, zodat elk instrument een eigen groepskeuze krijgt.
    $instrumentenVanDeelnemer = preg_split('/\s*,\s*/', trim((string) ($deelnemer['instrument'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($instrumentenVanDeelnemer as $instrument) {
        $instrumentKeuzes[$instrument] = ($instrumentKeuzes[$instrument] ?? 0) + 1;
    }
}
uksort($instrumentKeuzes, 'strnatcasecmp');

// Bij eerste weergave staat iedereen geselecteerd; na formulierinvoer is de meegestuurde selectie leidend.
$selectieIngediend = isset($_POST['selectie_ingediend']);
$alleenGeselecteerdenTonen = ($_POST['alleen_geselecteerden_tonen'] ?? '0') === '1';
$geselecteerdeDeelnemerIds = $selectieIngediend && is_array($_POST['deelnemer_ids'] ?? null)
    ? array_values(array_unique(array_filter(array_map('intval', $_POST['deelnemer_ids']), static fn (int $id): bool => $id > 0)))
    : array_map(static fn (array $deelnemer): int => (int) $deelnemer['id'], $deelnemers);
$geselecteerdeDeelnemers = array_values(array_filter(
    $deelnemers,
    static fn (array $deelnemer): bool => in_array((int) $deelnemer['id'], $geselecteerdeDeelnemerIds, true)
));

$standaardOnderwerp = 'Mozart op Zaterdag';
$standaardBericht = <<<'HTML'
<p>Beste {{voornaam}},</p>
<p>Je bent ingedeeld als <strong>{{instrument_partij}}</strong>.</p>
<p>Met muzikale groet,<br>Dirkjan Horringa</p>
HTML;
if ($mailPeriode !== null) {
    $standaardOnderwerp .= ': ' . $mailPeriode['label'];
    $standaardBericht = '<p>Beste {{voornaam}},</p><p>Hieronder vind je je plaatsingen voor de komende periode.</p>'
        . '{{plaatsing}}<p>Met muzikale groet,<br>Dirkjan Horringa</p>';
}
$onderwerp = trim($_POST['onderwerp'] ?? $standaardOnderwerp);
$bericht = trim($_POST['bericht'] ?? $standaardBericht);

function conceptMapVoorDoelgroep(array $activiteit, string $doelgroep): string
{
    return $doelgroep === 'toegelaten'
        ? dirname(__DIR__) . '/' . $activiteit['datum']
        : dirname(__DIR__) . '/JSON';
}

function serverConceptBestand(array $activiteit, string $doelgroep, string $keuze): ?string
{
    [$bron, $bestandsnaam] = array_pad(explode(':', $keuze, 2), 2, '');
    // Beperk bron en bestandsnaam voordat een pad wordt opgebouwd; een formulierwaarde mag geen willekeurig bestand kiezen.
    if (!in_array($bron, ['json', 'activiteit'], true) || !preg_match('/^mozart-mailconcept(?:[ -].*)?\.json$/', $bestandsnaam)) {
        return null;
    }
    if ($doelgroep === 'toegelaten' && $bron !== 'activiteit') {
        return null;
    }

    $map = $bron === 'activiteit'
        ? dirname(__DIR__) . '/' . $activiteit['datum']
        : dirname(__DIR__) . '/JSON';

    return $map . '/' . $bestandsnaam;
}

function verwijderOudeMailconcepten(string $webroot, string $onderwerp, string $nieuwBestand): int
{
    $mappen = array_merge(
        [$webroot . '/JSON'],
        glob($webroot . '/20??-??-??', GLOB_ONLYDIR) ?: []
    );
    $concepten = [];
    $nieuwBestand = realpath($nieuwBestand) ?: $nieuwBestand;

    foreach ($mappen as $map) {
        foreach (glob($map . '/mozart-mailconcept*.json') ?: [] as $bestand) {
            $inhoud = file_get_contents($bestand);
            if ($inhoud === false) {
                continue;
            }
            try {
                $concept = json_decode($inhoud, true, 8, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
            if (!is_array($concept) || ($concept['onderwerp'] ?? null) !== $onderwerp) {
                continue;
            }

            $pad = realpath($bestand) ?: $bestand;
            $concepten[] = [
                'pad' => $pad,
                'gewijzigd' => filemtime($pad) ?: 0,
                'nieuw' => $pad === $nieuwBestand,
            ];
        }
    }

    usort($concepten, static fn (array $eerste, array $tweede): int =>
        [$tweede['nieuw'], $tweede['gewijzigd'], $tweede['pad']]
        <=> [$eerste['nieuw'], $eerste['gewijzigd'], $eerste['pad']]
    );

    $verwijderd = 0;
    foreach (array_slice($concepten, 3) as $concept) {
        if (unlink($concept['pad'])) {
            $verwijderd++;
        }
    }

    return $verwijderd;
}

if ($actie === 'json_downloaden') {
    try {
        $conceptBestand = serverConceptBestand($gekozenActiviteit, $doelgroep, (string) ($_POST['server_concept'] ?? ''));
        if ($conceptBestand === null || !is_file($conceptBestand)) {
            throw new RuntimeException('Kies een geldig serverconcept om te downloaden.');
        }
        $inhoud = file_get_contents($conceptBestand);
        if ($inhoud === false) {
            throw new RuntimeException('Het serverconcept kon niet worden gelezen.');
        }
        // Na de downloadheaders is de actie klaar; HTML-output zou het JSON-bestand onbruikbaar maken.
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($conceptBestand) . '"');
        header('Content-Length: ' . strlen($inhoud));
        echo $inhoud;
        exit;
    } catch (Throwable $e) {
        $melding = 'Serverconcept niet gedownload: ' . $e->getMessage();
    }
}

if ($actie === 'json_opslaan') {
    $conceptActiviteitId = (int) ($_POST['concept_activiteit_id'] ?? $activiteitId);
    $conceptActiviteit = null;
    foreach ($activiteiten as $activiteit) {
        if ((int) $activiteit['id'] === $conceptActiviteitId) {
            $conceptActiviteit = $activiteit;
            break;
        }
    }
    if ($conceptActiviteit === null) {
        $melding = 'Kies eerst een geldige activiteit.';
    } else {
        try {
            // Een vaste versie en verplichte velden houden opgeslagen concepten uitwisselbaar met de laadactie.
            $inhoud = json_encode([
                'versie' => 1,
                'activiteit_id' => $conceptActiviteitId,
                'doelgroep' => $doelgroep,
                'onderwerp' => $onderwerp,
                'bericht' => $bericht,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $bestandsOnderwerp = preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $onderwerp) ?: 'zonder-onderwerp');
            $bestandsOnderwerp = trim($bestandsOnderwerp, '-');
            $bestandsOnderwerp = substr($bestandsOnderwerp !== '' ? $bestandsOnderwerp : 'zonder-onderwerp', 0, 80);
            $bestandsnaam = 'mozart-mailconcept-' . $bestandsOnderwerp . '-' . date('Ymd-His') . '.json';
            $conceptMap = conceptMapVoorDoelgroep($conceptActiviteit, $doelgroep);
            if (!is_dir($conceptMap) && !mkdir($conceptMap, 0775, true) && !is_dir($conceptMap)) {
                throw new RuntimeException('De conceptmap kon niet worden aangemaakt.');
            }
            $conceptBestand = $conceptMap . '/' . $bestandsnaam;
            if (file_put_contents($conceptBestand, $inhoud . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Het concept kon niet op de server worden opgeslagen.');
            }
            $aantalVerwijderd = verwijderOudeMailconcepten(dirname(__DIR__), $onderwerp, $conceptBestand);
            $relatiefPad = $doelgroep === 'toegelaten' ? $conceptActiviteit['datum'] . '/' . $bestandsnaam : 'JSON/' . $bestandsnaam;
            $melding = 'Mailconcept opgeslagen in ' . $relatiefPad . '.';
            if ($aantalVerwijderd > 0) {
                $melding .= ' ' . $aantalVerwijderd . ' oudere versie(s) verwijderd.';
            }
        } catch (Throwable $e) {
            $melding = 'Mailconcept niet opgeslagen: ' . $e->getMessage();
        }
    }
}

function sluitLokaleAfbeeldingenIn(string $html, PHPMailer\PHPMailer\PHPMailer $mailer): string
{
    $webroot = realpath(__DIR__ . '/..');
    if ($webroot === false) {
        return $html;
    }

    $ingeslotenAfbeeldingen = [];
    return preg_replace_callback(
        '/(<img\b[^>]*\bsrc\s*=\s*)(["\'])(.*?)(\2)/i',
        static function (array $match) use ($mailer, $webroot, &$ingeslotenAfbeeldingen): string {
            $url = html_entity_decode($match[3], ENT_QUOTES, 'UTF-8');
            $urlDelen = parse_url($url);
            $host = strtolower((string) ($urlDelen['host'] ?? ''));
            if (!in_array($host, ['', 'mozartopzaterdag.nl', 'www.mozartopzaterdag.nl'], true)) {
                return $match[0];
            }

            $urlPad = (string) ($urlDelen['path'] ?? '');
            $lokaalPad = rawurldecode(str_replace('+', '%20', $urlPad));
            $bestand = realpath($webroot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($lokaalPad, '/')));
            // Alleen bestanden onder de webroot mogen mee; realpath voorkomt dat een URL via .. daarbuiten komt.
            if ($bestand === false || !is_file($bestand) || !str_starts_with(strtolower($bestand), strtolower($webroot . DIRECTORY_SEPARATOR))) {
                return $match[0];
            }

            $cid = 'mozart-' . sha1($bestand);
            if (!isset($ingeslotenAfbeeldingen[$cid])) {
                $mimeType = function_exists('mime_content_type') ? mime_content_type($bestand) : false;
                $mailer->addEmbeddedImage($bestand, $cid, basename($bestand), 'base64', $mimeType ?: 'application/octet-stream');
                $ingeslotenAfbeeldingen[$cid] = true;
            }

            return $match[1] . $match[2] . 'cid:' . $cid . $match[4];
        },
        $html
    ) ?? $html;
}

function leesMailInstellingen(): array
{
    $gebruikersnaam = 'info@mozartopzaterdag.nl';
    $wachtwoord = '';
    $bestand = __DIR__ . '/../includes/_tst/MOZART_GMAIL_USERNAME.txt';
    if (is_readable($bestand)) {
        $ongelabeldeRegels = [];
        foreach (preg_split('/\r\n|\r|\n/', trim((string) file_get_contents($bestand))) as $regel) {
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
                    $wachtwoord = preg_replace('/\s+/', '', $waarde);
                }
            } else {
                $ongelabeldeRegels[] = $regel;
            }
        }
        if ($wachtwoord === '' && isset($ongelabeldeRegels[1])) {
            $gebruikersnaam = $ongelabeldeRegels[0];
            $wachtwoord = preg_replace('/\s+/', '', $ongelabeldeRegels[1]);
        }
    }

    // Omgevingsvariabelen overschrijven het lokale bestand, zodat productie-instellingen buiten de broncode blijven.
    $gebruikersnaam = getenv('MOZART_GMAIL_USERNAME') ?: $gebruikersnaam;
    $omgevingsWachtwoord = getenv('MOZART_GMAIL_APP_PASSWORD');
    if ($omgevingsWachtwoord !== false && $omgevingsWachtwoord !== '') {
        $wachtwoord = preg_replace('/\s+/', '', $omgevingsWachtwoord);
    }

    return [$gebruikersnaam, $wachtwoord];
}

$wachtrijSleutel = 'mozart_deelnemers_mail_wachtrij';
$wachtrij = $_SESSION[$wachtrijSleutel] ?? null;
if ($actie === 'versturen') {
    if ($gekozenActiviteit === null) {
        $melding = 'Kies eerst een geldige activiteit.';
    } elseif ($onderwerp === '' || $bericht === '') {
        $melding = 'Onderwerp en bericht zijn verplicht.';
    } elseif ($geselecteerdeDeelnemers === []) {
        $melding = 'Selecteer ten minste één deelnemer voor verzending.';
    } else {
        // Leg de ontvangers vast in de sessie; latere batches hangen zo niet af van gewijzigde formulierselecties.
        $wachtrij = [
            'activiteit' => $gekozenActiviteit,
            'doelgroep_label' => $mailPeriode['label'] ?? (date('d-m-Y', strtotime($gekozenActiviteit['datum'])) . ' - ' . $gekozenActiviteit['plaats']),
            'onderwerp' => $onderwerp,
            'bericht' => $bericht,
            'aangemaakt_op' => date(DATE_ATOM),
            'volgende_pluk_op' => time(),
            'ontvangers' => array_map(static function (array $deelnemer): array {
                $deelnemer['status'] = 'wachtend';
                $deelnemer['pogingen'] = 0;
                $deelnemer['fout'] = '';
                $deelnemer['verzonden_op'] = null;
                $deelnemer['tracking_token'] = bin2hex(random_bytes(32));
                return $deelnemer;
            }, $geselecteerdeDeelnemers),
        ];
        $_SESSION[$wachtrijSleutel] = $wachtrij;
        $actie = 'verwerk_pluk';
    }
}

if ($actie === 'opnieuw_proberen' && is_array($wachtrij)) {
    // Heropen alleen mislukte ontvangers; al verzonden mails blijven buiten een herstart.
    foreach ($wachtrij['ontvangers'] as &$ontvanger) {
        if ($ontvanger['status'] === 'mislukt') {
            $ontvanger['status'] = 'wachtend';
            $ontvanger['fout'] = '';
        }
    }
    unset($ontvanger);
    $wachtrij['volgende_pluk_op'] = time();
    $_SESSION[$wachtrijSleutel] = $wachtrij;
    $actie = 'verwerk_pluk';
}

if ($actie === 'wachtrij_wissen') {
    // Dit wist alleen de lopende sessiewachtrij, niet de aparte trackinghistorie in de database.
    unset($_SESSION[$wachtrijSleutel]);
    $wachtrij = null;
    $melding = 'De verzendwachtrij is gewist.';
}

if ($actie === 'verwerk_pluk' && is_array($wachtrij)) {
    // Beperk iedere aanvraag tot één pluk, zodat verzending over meerdere requests verdeeld blijft.
    $wachtendeIndexen = [];
    foreach ($wachtrij['ontvangers'] as $index => $ontvanger) {
        if ($ontvanger['status'] === 'wachtend') {
            $wachtendeIndexen[] = $index;
        }
        if (count($wachtendeIndexen) === 20) {
            break;
        }
    }

    if ($wachtendeIndexen === []) {
        $melding = 'Er staan geen onverzonden mails meer in de wachtrij.';
    } elseif (time() < (int) $wachtrij['volgende_pluk_op']) {
        $melding = 'De volgende pluk kan vanaf ' . date('H:i:s', (int) $wachtrij['volgende_pluk_op']) . ' worden verstuurd.';
    } else {
        [$gebruikersnaam, $wachtwoord] = leesMailInstellingen();
        if ($wachtwoord === '') {
            $melding = 'Mail niet verstuurd: het app-wachtwoord ontbreekt in de configuratie.';
        } else {
            set_time_limit(0);
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mailer->isSMTP();
                $mailer->Host = 'send.one.com';
                $mailer->SMTPAuth = true;
                $mailer->Username = $gebruikersnaam;
                $mailer->Password = $wachtwoord;
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mailer->Port = 465;
                $mailer->Timeout = 15;
                $mailer->SMTPKeepAlive = true;
                $mailer->CharSet = 'UTF-8';
                $mailer->setFrom($gebruikersnaam, 'Mozart op Zaterdag');
                $mailer->addReplyTo($gebruikersnaam, 'Mozart op Zaterdag');
                $mailer->isHTML(true);

                foreach ($wachtendeIndexen as $index) {
                    $ontvanger = &$wachtrij['ontvangers'][$index];
                    $naam = trim($ontvanger['voornaam'] . ' ' . $ontvanger['achternaam']);
                    $ontvanger['pogingen']++;
                    try {
                        if (!filter_var($ontvanger['email'], FILTER_VALIDATE_EMAIL)) {
                            throw new RuntimeException('Ongeldig e-mailadres');
                        }
                        $mailer->clearAddresses();
                        $mailer->clearAttachments();
                        $mailer->addAddress($ontvanger['email'], $naam);
                        $mailer->Subject = str_replace(["\r", "\n"], '', html_entity_decode(vulMailTemplate($wachtrij['onderwerp'], $ontvanger, $wachtrij['activiteit'], false), ENT_QUOTES, 'UTF-8'));
                        $ontvanger['tracking_token'] ??= bin2hex(random_bytes(32));
                        $mailer->Body = voegTrackingPixelToe(
                            sluitLokaleAfbeeldingenIn(vulMailTemplate($wachtrij['bericht'], $ontvanger, $wachtrij['activiteit']), $mailer),
                            $ontvanger['tracking_token']
                        );
                        $mailer->AltBody = mailHtmlNaarTekst($mailer->Body);
                        // Reserveer vóór SMTP-verzending om dubbele mails binnen het ingestelde tijdvenster te blokkeren.
                        if (!reserveerMailVerzending($pdo, $ontvanger['tracking_token'], (int) $wachtrij['activiteit']['id'], (int) $ontvanger['id'], $ontvanger['email'])) {
                            $ontvanger['status'] = 'overgeslagen';
                            $ontvanger['fout'] = 'Binnen vijf minuten is al een mail naar dit adres verzonden.';
                            $resultaten[] = ['gelukt' => false, 'naam' => $naam, 'bericht' => $ontvanger['fout']];
                        } else {
                            $mailer->send();
                            $ontvanger['status'] = 'verzonden';
                            $ontvanger['verzonden_op'] = date(DATE_ATOM);
                            $resultaten[] = ['gelukt' => true, 'naam' => $naam, 'bericht' => $ontvanger['email']];
                        }
                    } catch (Throwable $e) {
                        $ontvanger['status'] = 'mislukt';
                        $ontvanger['fout'] = $e->getMessage();
                        $resultaten[] = ['gelukt' => false, 'naam' => $naam, 'bericht' => $e->getMessage()];
                    }
                    // Bewaar na elke ontvanger de voortgang; een volgende aanvraag kan veilig verdergaan na een fout.
                    unset($ontvanger);
                    $_SESSION[$wachtrijSleutel] = $wachtrij;
                }
                $mailer->smtpClose();
                $nogWachtend = count(array_filter($wachtrij['ontvangers'], static fn (array $ontvanger): bool => $ontvanger['status'] === 'wachtend'));
                $wachtrij['volgende_pluk_op'] = $nogWachtend > 0 ? time() + 600 : null;
                $_SESSION[$wachtrijSleutel] = $wachtrij;
                $aantalGelukt = count(array_filter($resultaten, static fn (array $resultaat): bool => $resultaat['gelukt']));
                $melding = $aantalGelukt . ' mails verstuurd in deze pluk; ' . $nogWachtend . ' wachten nog.';
            } catch (Throwable $e) {
                $melding = 'Mail niet verstuurd: ' . $e->getMessage();
            }
        }
    }
}

if ($actie === 'test') {
    if ($gekozenActiviteit === null) {
        $melding = 'Kies eerst een geldige activiteit.';
    } elseif ($onderwerp === '' || $bericht === '') {
        $melding = 'Onderwerp en bericht zijn verplicht.';
    } elseif ($geselecteerdeDeelnemers === []) {
        $melding = 'Selecteer ten minste één deelnemer voor de testmail.';
    } else {
        [$gebruikersnaam, $wachtwoord] = leesMailInstellingen();
        if ($wachtwoord === '') {
            $melding = 'Mail niet verstuurd: het app-wachtwoord ontbreekt in de configuratie.';
        } else {
            // Een test gebruikt één geselecteerde ontvanger als voorbeeld, maar gaat uitsluitend naar het beheeradres.
            $teVersturenDeelnemers = [$geselecteerdeDeelnemers[0]];
            set_time_limit(0);
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mailer->isSMTP();
                $mailer->Host = 'send.one.com';
                $mailer->SMTPAuth = true;
                $mailer->Username = $gebruikersnaam;
                $mailer->Password = $wachtwoord;
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mailer->Port = 465;
                $mailer->Timeout = 15;
                $mailer->SMTPKeepAlive = true;
                $mailer->CharSet = 'UTF-8';
                $mailer->setFrom($gebruikersnaam, 'Mozart op Zaterdag');
                $mailer->addReplyTo($gebruikersnaam, 'Mozart op Zaterdag');
                $mailer->isHTML(true);

                foreach ($teVersturenDeelnemers as $deelnemer) {
                    $naam = trim($deelnemer['voornaam'] . ' ' . $deelnemer['achternaam']);
                    $ontvangerEmail = 'dirkjan@pellegrina.net';
                    $ontvangerNaam = 'Dirkjan Horringa';
                    if (!filter_var($ontvangerEmail, FILTER_VALIDATE_EMAIL)) {
                        $resultaten[] = ['gelukt' => false, 'naam' => $naam, 'bericht' => 'ongeldig e-mailadres'];
                        continue;
                    }
                    try {
                        $mailer->clearAddresses();
                        $mailer->clearAttachments();
                        $mailer->addAddress($ontvangerEmail, $ontvangerNaam);
                        $ingevuldOnderwerp = str_replace(["\r", "\n"], '', html_entity_decode(vulMailTemplate($onderwerp, $deelnemer, $gekozenActiviteit, false), ENT_QUOTES, 'UTF-8'));
                        $mailer->Subject = '[TEST] ' . $ingevuldOnderwerp;
                        $mailer->Body = sluitLokaleAfbeeldingenIn(vulMailTemplate($bericht, $deelnemer, $gekozenActiviteit), $mailer);
                        $mailer->AltBody = mailHtmlNaarTekst($mailer->Body);
                        // Testmails registreren geen echte verzending en blokkeren dus geen latere deelnemersmail.
                        $mailer->send();
                        $resultaten[] = ['gelukt' => true, 'naam' => $naam, 'bericht' => $ontvangerEmail];
                    } catch (Throwable $e) {
                        $resultaten[] = ['gelukt' => false, 'naam' => $naam, 'bericht' => $e->getMessage()];
                    }
                }
                $mailer->smtpClose();
                $aantalGelukt = count(array_filter($resultaten, static fn (array $resultaat): bool => $resultaat['gelukt']));
                $melding = $aantalGelukt === 1
                    ? 'Testmail verstuurd naar dirkjan@pellegrina.net met de gegevens van ' . $geselecteerdeDeelnemers[0]['voornaam'] . '.'
                    : 'Testmail niet verstuurd.';
            } catch (Throwable $e) {
                $melding = 'Mail niet verstuurd: ' . $e->getMessage();
            }
        }
    }
}

$geopendPerToken = [];
if (is_array($wachtrij)) {
    // Eén query voor alle tokens voorkomt een databaseaanvraag per ontvanger in het wachtrijoverzicht.
    $trackingTokens = array_values(array_filter(array_column($wachtrij['ontvangers'], 'tracking_token')));
    if ($trackingTokens !== []) {
        $placeholders = implode(',', array_fill(0, count($trackingTokens), '?'));
        $stmt = $pdo->prepare("SELECT token, geopend_op FROM mail_tracking WHERE token IN ($placeholders)");
        $stmt->execute($trackingTokens);
        $geopendPerToken = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

// Houd alleen de statussen bij die in het wachtrijoverzicht zichtbaar zijn.
$wachtrijTellingen = ['wachtend' => 0, 'verzonden' => 0, 'mislukt' => 0, 'overgeslagen' => 0];
if (is_array($wachtrij)) {
    foreach ($wachtrij['ontvangers'] as $ontvanger) {
        // Onbekende statussen tellen niet mee en maken geen extra categorie aan.
        if (isset($wachtrijTellingen[$ontvanger['status']])) {
            $wachtrijTellingen[$ontvanger['status']]++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deelnemers mailen</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>
    <style>
        @media (max-width: 600px) {
            body {
                margin: 0;
                font-size: 16px;
                line-height: 1.45;
                -webkit-text-size-adjust: 100%;
            }

            main.w3-content.w3-panel {
                width: 100%;
                max-width: none !important;
                margin: 0;
                padding: 0 10px 16px;
                box-sizing: border-box;
            }

            main h3 {
                font-size: 1.35rem;
            }

            main h4 {
                font-size: 1.1rem;
            }

            main input,
            main select,
            main textarea,
            main button {
                box-sizing: border-box;
                font-size: 16px;
            }

            main input,
            main select,
            main textarea {
                width: 100%;
                max-width: 100%;
            }

            main button {
                width: 100%;
                min-height: 44px;
            }

            main form {
                display: block !important;
            }

            main .w3-small {
                font-size: 1rem !important;
            }

            main .w3-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
    <main class="w3-content w3-mobile w3-white w3-panel" style="max-width:900px;">
        <h3>Deelnemers mailen</h3>

        <?php if ($melding !== ''): ?>
            <p class="w3-panel <?= $resultaten !== [] && count(array_filter($resultaten, static fn (array $resultaat): bool => !$resultaat['gelukt'])) > 0 ? 'w3-pale-yellow w3-border-orange' : 'w3-pale-green w3-border-green' ?> w3-leftbar">
                <?= htmlspecialchars($melding) ?>
            </p>
        <?php endif; ?>

        <form method="get" class="w3-margin-bottom">
            <input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>">
            <strong>Plaatsingen volgend jaar mailen</strong><br>
            <?php foreach ($halfjaren as $sleutel => $halfjaar): ?>
                <button class="w3-button <?= $doelgroep === $sleutel ? 'w3-blue' : 'w3-light-grey' ?> w3-margin-top" type="submit" name="doelgroep" value="<?= htmlspecialchars($sleutel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($halfjaar['label'], ENT_QUOTES, 'UTF-8') ?></button>
            <?php endforeach; ?>
        </form>

        <form method="get" class="w3-margin-bottom">
            <?php if ($mailPeriode !== null): ?>
                <input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>">
            <?php endif; ?>
            <label for="activiteit_id"><strong>Activiteit</strong></label>
            <select class="w3-select w3-border" id="activiteit_id" name="activiteit_id" onchange="this.form.submit()" <?= $mailPeriode !== null ? 'disabled' : '' ?>>
                <?php foreach ($activiteiten as $activiteit): ?>
                    <option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>>
                        <?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label class="w3-margin-top" for="doelgroep"><strong>Doelgroep</strong></label>
            <select class="w3-select w3-border" id="doelgroep" name="doelgroep" onchange="this.form.submit()">
                <?php foreach ($doelgroepen as $waarde => $label): ?>
                    <option value="<?= htmlspecialchars($waarde, ENT_QUOTES, 'UTF-8') ?>" <?= $doelgroep === $waarde ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($gekozenActiviteit !== null): ?>
        <form method="post" enctype="multipart/form-data" class="w3-margin-bottom">
            <input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>">
            <input type="hidden" name="doelgroep" value="<?= htmlspecialchars($doelgroep, ENT_QUOTES, 'UTF-8') ?>">
            <?php $conceptMap = conceptMapVoorDoelgroep($gekozenActiviteit, $doelgroep); $serverConcepten = []; foreach (glob($conceptMap . '/mozart-mailconcept*.json') ?: [] as $concept) $serverConcepten['json:' . basename($concept)] = $concept; if ($doelgroep === 'toegelaten') { $serverConcepten = []; foreach (glob(dirname(__DIR__) . '/' . $gekozenActiviteit['datum'] . '/mozart-mailconcept*.json') ?: [] as $concept) $serverConcepten['activiteit:' . basename($concept)] = $concept; } else { foreach (glob(dirname(__DIR__) . '/' . $gekozenActiviteit['datum'] . '/mozart-mailconcept*.json') ?: [] as $concept) $serverConcepten['activiteit:' . basename($concept)] = $concept; } uksort($serverConcepten, static fn (string $eerste, string $tweede): int => strnatcmp($tweede, $eerste)); ?>
            <label for="server_concept"><strong>Serverconcept kiezen</strong></label>
            <select class="w3-select w3-border" id="server_concept" name="server_concept" <?= $serverConcepten === [] ? 'disabled' : '' ?>>
                <?php if ($serverConcepten === []): ?><option>Geen serverconcepten gevonden</option><?php endif; ?>
                <?php foreach ($serverConcepten as $serverConceptSleutel => $serverConcept): ?><option value="<?= htmlspecialchars($serverConceptSleutel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(($serverConceptSleutel === 'json:' . basename($serverConcept) ? 'JSON' : $gekozenActiviteit['datum']) . ': ' . basename($serverConcept), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
            <button class="w3-button w3-light-grey w3-margin-top" type="submit" name="actie" value="json_laden" <?= $serverConcepten === [] ? 'disabled' : '' ?>>Serverconcept laden</button>
            <button class="w3-button w3-light-grey w3-margin-top" type="submit" name="actie" value="json_downloaden" <?= $serverConcepten === [] ? 'disabled' : '' ?>>Serverconcept downloaden</button>
        </form>
        <?php endif; ?>

        <?php if (is_array($wachtrij)): ?>
            <section class="w3-panel w3-pale-blue w3-leftbar w3-border-blue">
                <h4>Verzendwachtrij</h4>
                <p>
                    <strong><?= htmlspecialchars($wachtrij['doelgroep_label'] ?? (date('d-m-Y', strtotime($wachtrij['activiteit']['datum'])) . ' - ' . $wachtrij['activiteit']['plaats'])) ?></strong><br>
                    <?= $wachtrijTellingen['verzonden'] ?> verzonden,
                    <?= $wachtrijTellingen['wachtend'] ?> wachtend,
                    <?= $wachtrijTellingen['mislukt'] ?> mislukt,
                    <?= $wachtrijTellingen['overgeslagen'] ?> binnen vijf minuten overgeslagen.
                </p>
                <?php if ($wachtrijTellingen['wachtend'] > 0): ?>
                    <p id="volgende-pluk-melding"></p>
                    <form method="post" id="volgende-pluk-formulier" style="display:inline-block">
                        <input type="hidden" name="actie" value="verwerk_pluk">
                        <button class="w3-button w3-blue" type="submit">Volgende pluk van maximaal 20 versturen</button>
                    </form>
                <?php endif; ?>
                <?php if ($wachtrijTellingen['mislukt'] > 0): ?>
                    <form method="post" style="display:inline-block">
                        <button class="w3-button w3-orange" type="submit" name="actie" value="opnieuw_proberen">Mislukte mails opnieuw proberen</button>
                    </form>
                <?php endif; ?>
                <form method="post" style="display:inline-block" onsubmit="return confirm('De volledige verzendwachtrij wissen?');">
                    <button class="w3-button w3-light-grey" type="submit" name="actie" value="wachtrij_wissen">Wachtrij wissen</button>
                </form>

                <details class="w3-margin-top">
                    <summary>Status per ontvanger</summary>
                    <div class="w3-responsive">
                        <table class="w3-table w3-bordered w3-small">
                            <tr><th>Naam</th><th>E-mail</th><th>Status</th><th>Geopend</th><th>Pogingen</th><th>Fout</th></tr>
                            <?php foreach ($wachtrij['ontvangers'] as $ontvanger): ?>
                                <?php $geopendOp = $geopendPerToken[$ontvanger['tracking_token'] ?? ''] ?? null; ?>
                                <tr>
                                    <td><?= htmlspecialchars($ontvanger['voornaam'] . ' ' . $ontvanger['achternaam']) ?></td>
                                    <td><?= htmlspecialchars($ontvanger['email']) ?></td>
                                    <td><?= htmlspecialchars($ontvanger['status']) ?></td>
                                    <td>
                                        <?php if ($geopendOp !== null): ?>
                                            <span class="w3-text-green" title="Geopend op <?= htmlspecialchars(date('d-m-Y H:i', strtotime($geopendOp))) ?>" aria-label="Geopend">&#10004;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int) $ontvanger['pogingen'] ?></td>
                                    <td><?= htmlspecialchars($ontvanger['fout']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </details>
            </section>
        <?php endif; ?>

        <?php if ($mailPeriode !== null): ?>
            <p class="w3-panel w3-pale-blue">
                Deelnemers met minstens één ‘ja’ of ‘misschien’ van <?= htmlspecialchars(date('d-m-Y', strtotime($mailPeriode['start']))) ?>
                tot en met <?= htmlspecialchars(date('d-m-Y', strtotime($mailPeriode['einde'] . ' -1 day'))) ?>.
                <code>{{plaatsing}}</code> toont per activiteit plaatsing in groen of afwijzing in rood; ‘nee’ wordt weggelaten.
                Controleer de indeling vóór verzending: niet toegelaten geldt als afgewezen.
                <?php if ($gekozenActiviteit === null): ?>Er zijn nog geen activiteiten in dit halfjaar.<?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($gekozenActiviteit !== null): ?>
            <p>
            <strong><?= count($deelnemers) ?> deelnemers: <?= htmlspecialchars($doelgroepen[$doelgroep], ENT_QUOTES, 'UTF-8') ?></strong><br>
                <span id="selectie-aantal"><?= count($geselecteerdeDeelnemers) ?> geselecteerd</span>
            </p>
            <label class="w3-margin-bottom">
                <input class="w3-check" id="alleen-geselecteerden-tonen" type="checkbox" <?= $alleenGeselecteerdenTonen ? 'checked' : '' ?>>
                Alleen geselecteerde deelnemers tonen
            </label>
            <?php if ($instrumentKeuzes !== []): ?>
                <!-- De groepskeuze voegt ontvangers toe aan de huidige selectie; handmatige keuzes blijven behouden. -->
                <div class="w3-margin-bottom">
                    <label for="instrument-keuze"><strong>Selecteer per instrument</strong></label>
                    <select class="w3-select w3-border" id="instrument-keuze">
                        <option value="">Kies een instrument</option>
                        <?php foreach ($instrumentKeuzes as $instrument => $aantal): ?>
                            <option value="<?= htmlspecialchars($instrument, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($instrument . ' (' . $aantal . ')', ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="w3-button w3-light-grey w3-margin-top" id="selecteer-instrument" type="button" disabled>Selecteer alle deelnemers met dit instrument</button>
                </div>
            <?php endif; ?>
            <?php if ($deelnemers !== []): ?>
                <div class="w3-responsive w3-margin-bottom" style="max-height:250px; overflow:auto;">
                    <table class="w3-table w3-bordered w3-striped w3-small">
                        <tr>
                            <th>
                                <input id="selecteer-alles" type="checkbox" aria-label="Alle deelnemers selecteren" <?= count($geselecteerdeDeelnemers) === count($deelnemers) ? 'checked' : '' ?>>
                            </th>
                            <th>Naam</th><th>E-mail</th><th>Instrument + partij</th>
                        </tr>
                        <?php foreach ($deelnemers as $deelnemer): ?>
                            <?php $instrumentenVanDeelnemer = preg_split('/\s*,\s*/', trim((string) ($deelnemer['instrument'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: []; ?>
                            <tr data-deelnemer-rij>
                                <td>
                                    <input
                                        class="deelnemer-selectie"
                                        type="checkbox"
                                        name="deelnemer_ids[]"
                                        value="<?= (int) $deelnemer['id'] ?>"
                                        data-instrumenten="<?= htmlspecialchars(json_encode($instrumentenVanDeelnemer, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8') ?>"
                                        form="mail-formulier"
                                        aria-label="<?= htmlspecialchars($deelnemer['voornaam'] . ' ' . $deelnemer['achternaam']) ?> selecteren"
                                        <?= in_array((int) $deelnemer['id'], $geselecteerdeDeelnemerIds, true) ? 'checked' : '' ?>
                                    >
                                </td>
                                <td><?= htmlspecialchars($deelnemer['voornaam'] . ' ' . $deelnemer['achternaam']) ?></td>
                                <td><?= htmlspecialchars($deelnemer['email']) ?></td>
                                <td><?= htmlspecialchars(trim(($deelnemer['instrument'] ?? '') . ' ' . ($deelnemer['partij'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>

            <form method="post" id="mail-formulier" onsubmit="return bevestigVerzending(event);">
                <input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>">
                <input type="hidden" name="doelgroep" value="<?= htmlspecialchars($doelgroep, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="selectie_ingediend" value="1">
                <input type="hidden" id="alleen-geselecteerden-invoer" name="alleen_geselecteerden_tonen" value="<?= $alleenGeselecteerdenTonen ? '1' : '0' ?>">

                <label for="onderwerp"><strong>Onderwerp</strong></label>
                <input class="w3-input w3-border w3-margin-bottom" id="onderwerp" name="onderwerp" value="<?= htmlspecialchars($onderwerp) ?>" required>

                <p class="w3-small">
                    Invoegcodes: <code>{{voornaam}}</code>, <code>{{achternaam}}</code>, <code>{{instrument_partij}}</code>,
                    <code>{{instrument}}</code>, <code>{{partij}}</code>, <code>{{datum}}</code>, <code>{{plaats}}</code>, <code>{{omschrijving}}</code>, <code>{{aanmeldlink}}</code>
                    en <code>{{plaatsing}}</code> (plaatsingsoverzicht bij een halfjaardoelgroep).
                    <?php if ($mailPeriode !== null): ?>De codes voor datum, plaats en omschrijving verwijzen naar de eerste activiteit van dit halfjaar.<?php endif; ?>
                </p>
                <label for="bericht"><strong>Bericht</strong></label>
                <textarea id="bericht" name="bericht" required><?= htmlspecialchars($bericht) ?></textarea>

                <label class="w3-margin-top" for="concept_activiteit_id"><strong>Concept opslaan voor activiteit</strong></label>
                <select class="w3-select w3-border" id="concept_activiteit_id" name="concept_activiteit_id">
                    <?php foreach ($activiteiten as $activiteit): ?>
                        <option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>><?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="w3-button w3-green w3-margin-top" type="submit" name="actie" value="test" data-selectie-vereist <?= $geselecteerdeDeelnemers === [] ? 'disabled' : '' ?>>Testmail naar Dirkjan</button>
                <button class="w3-button w3-blue w3-margin-top" type="submit" name="actie" value="versturen" data-selectie-vereist <?= $geselecteerdeDeelnemers === [] ? 'disabled' : '' ?>>Verzending in plukjes starten</button>
                <button class="w3-button w3-light-grey w3-margin-top" type="submit" name="actie" value="json_opslaan">Concept in JSON opslaan</button>
                <input type="hidden" name="server_concept" value="">
            </form>
        <?php endif; ?>

        <?php if ($resultaten !== []): ?>
            <h4>Verzendresultaat</h4>
            <ul class="w3-ul w3-small">
                <?php foreach ($resultaten as $resultaat): ?>
                    <li class="<?= $resultaat['gelukt'] ? 'w3-pale-green' : 'w3-pale-red' ?>">
                        <?= $resultaat['gelukt'] ? '&#10003;' : '&#10005;' ?>
                        <?= htmlspecialchars($resultaat['naam'] . ': ' . $resultaat['bericht']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>

    <script>
        function bevestigVerzending(event) {
            const aantalGeselecteerd = document.querySelectorAll('.deelnemer-selectie:checked').length;
            if (event.submitter && event.submitter.value === 'test') {
                return confirm('Testmail naar dirkjan@pellegrina.net versturen met de gegevens van de eerste geselecteerde deelnemer?');
            }
            if (event.submitter && event.submitter.value === 'json_opslaan') {
                return true;
            }
            return confirm(`Een nieuwe wachtrij voor ${aantalGeselecteerd} geselecteerde deelnemers starten en de eerste pluk van maximaal 20 nu versturen?`);
        }

        const deelnemerSelecties = Array.from(document.querySelectorAll('.deelnemer-selectie'));
        const selecteerAlles = document.getElementById('selecteer-alles');
        const selectieAantal = document.getElementById('selectie-aantal');
        const instrumentKeuze = document.getElementById('instrument-keuze');
        const selecteerInstrument = document.getElementById('selecteer-instrument');
        const alleenGeselecteerdenTonen = document.getElementById('alleen-geselecteerden-tonen');
        const alleenGeselecteerdenInvoer = document.getElementById('alleen-geselecteerden-invoer');
        const deelnemerRijen = Array.from(document.querySelectorAll('[data-deelnemer-rij]'));
        const selectieOpslagSleutel = 'mozart-deelnemers-mail:' + <?= json_encode($activiteitId . ':' . $doelgroep, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const opgeslagenInstrument = sessionStorage.getItem(`${selectieOpslagSleutel}:instrument`);

        if (instrumentKeuze && opgeslagenInstrument && Array.from(instrumentKeuze.options).some((optie) => optie.value === opgeslagenInstrument)) {
            instrumentKeuze.value = opgeslagenInstrument;
        }

        const opgeslagenSelectie = sessionStorage.getItem(`${selectieOpslagSleutel}:deelnemers`);
        if (opgeslagenSelectie !== null) {
            try {
                const opgeslagenIds = JSON.parse(opgeslagenSelectie);
                if (Array.isArray(opgeslagenIds)) {
                    const geselecteerdeIds = new Set(opgeslagenIds.map(String));
                    deelnemerSelecties.forEach((checkbox) => {
                        checkbox.checked = geselecteerdeIds.has(checkbox.value);
                    });
                }
            } catch {
                sessionStorage.removeItem(`${selectieOpslagSleutel}:deelnemers`);
            }
        }

        function werkSelectieBij() {
            // Houd teller, selecteer-alles-status en verzendknoppen gelijk aan de actuele checkboxstatus.
            const aantalGeselecteerd = deelnemerSelecties.filter((checkbox) => checkbox.checked).length;
            if (selectieAantal) {
                selectieAantal.textContent = `${aantalGeselecteerd} geselecteerd`;
            }
            if (selecteerAlles) {
                selecteerAlles.checked = deelnemerSelecties.length > 0 && aantalGeselecteerd === deelnemerSelecties.length;
                selecteerAlles.indeterminate = aantalGeselecteerd > 0 && aantalGeselecteerd < deelnemerSelecties.length;
            }
            document.querySelectorAll('[data-selectie-vereist]').forEach((knop) => {
                knop.disabled = aantalGeselecteerd === 0;
            });
            deelnemerRijen.forEach((rij) => {
                const checkbox = rij.querySelector('.deelnemer-selectie');
                rij.hidden = Boolean(alleenGeselecteerdenTonen?.checked && checkbox && !checkbox.checked);
            });
            sessionStorage.setItem(
                `${selectieOpslagSleutel}:deelnemers`,
                JSON.stringify(deelnemerSelecties.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value))
            );
        }

        selecteerAlles?.addEventListener('change', () => {
            deelnemerSelecties.forEach((checkbox) => {
                checkbox.checked = selecteerAlles.checked;
            });
            werkSelectieBij();
        });
        instrumentKeuze?.addEventListener('change', () => {
            if (selecteerInstrument) {
                selecteerInstrument.disabled = instrumentKeuze.value === '';
            }
            sessionStorage.setItem(`${selectieOpslagSleutel}:instrument`, instrumentKeuze.value);
        });
        alleenGeselecteerdenTonen?.addEventListener('change', () => {
            if (alleenGeselecteerdenInvoer) {
                alleenGeselecteerdenInvoer.value = alleenGeselecteerdenTonen.checked ? '1' : '0';
            }
            werkSelectieBij();
        });
        selecteerInstrument?.addEventListener('click', () => {
            const gekozenInstrument = instrumentKeuze?.value.trim().toLowerCase();
            if (!gekozenInstrument) {
                return;
            }
            // Een nieuwe instrumentkeuze vervangt de vorige groep; werkSelectieBij bewaart deze set voor herladen.
            deelnemerSelecties.forEach((checkbox) => {
                const instrumenten = JSON.parse(checkbox.dataset.instrumenten || '[]');
                checkbox.checked = instrumenten.some((instrument) => instrument.trim().toLowerCase() === gekozenInstrument);
            });
            sessionStorage.setItem(`${selectieOpslagSleutel}:instrument`, instrumentKeuze.value);
            werkSelectieBij();
        });
        deelnemerSelecties.forEach((checkbox) => checkbox.addEventListener('change', werkSelectieBij));
        werkSelectieBij();

        <?php if (is_array($wachtrij) && $wachtrijTellingen['wachtend'] > 0): ?>
            const volgendePlukOp = <?= (int) $wachtrij['volgende_pluk_op'] ?> * 1000;
            const plukMelding = document.getElementById('volgende-pluk-melding');
            const plukFormulier = document.getElementById('volgende-pluk-formulier');
            let plukWordtVerstuurd = false;

            function werkAftellerBij() {
                const resterendeSeconden = Math.max(0, Math.ceil((volgendePlukOp - Date.now()) / 1000));
                const minuten = Math.floor(resterendeSeconden / 60);
                const seconden = String(resterendeSeconden % 60).padStart(2, '0');
                plukMelding.textContent = resterendeSeconden > 0
                    ? `Volgende pluk automatisch over ${minuten}:${seconden}, zolang deze pagina openstaat.`
                    : 'De volgende pluk wordt nu verstuurd.';
                if (resterendeSeconden === 0 && !plukWordtVerstuurd) {
                    plukWordtVerstuurd = true;
                    // Alleen de volgende pluk wordt aangevraagd; de server blijft verantwoordelijk voor status en limieten.
                    plukFormulier.requestSubmit();
                }
            }

            werkAftellerBij();
            window.setInterval(werkAftellerBij, 1000);
        <?php endif; ?>

        if (document.getElementById('bericht')) {
            CKEDITOR.replace('bericht', {
                versionCheck: false,
                height: 360,
                language: 'nl',
                allowedContent: true,
                versionCheck: false,
                extraPlugins: 'autogrow,autolink,codesnippet,emoji,placeholder,tableresize,uicolor',
                removePlugins: 'a11ychecker,ckfinder,cloudservices,easyimage,exportpdf',
                toolbarGroups: [
                    { name: 'document', groups: ['mode', 'document', 'doctools'] },
                    { name: 'clipboard', groups: ['clipboard', 'undo'] },
                    { name: 'editing', groups: ['find', 'selection', 'spellchecker'] },
                    { name: 'forms' },
                    '/',
                    { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
                    { name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align', 'bidi'] },
                    { name: 'links' },
                    { name: 'insert' },
                    '/',
                    { name: 'styles' },
                    { name: 'colors' },
                    { name: 'tools' },
                    { name: 'others' },
                    { name: 'about' }
                ]
            });
        }

        // Onthoud de scrollpositie zodat je na een actie terugkomt waar je bezig was.
        (function () {
            var scrollSleutel = 'onderhoud-scroll-' + window.location.pathname;
            var opgeslagenPositie = sessionStorage.getItem(scrollSleutel);
            if (opgeslagenPositie !== null) {
                sessionStorage.removeItem(scrollSleutel);
                requestAnimationFrame(function () {
                    var positie = JSON.parse(opgeslagenPositie);
                    window.scrollTo(0, positie.windowY || 0);
                    var tabelScroll = document.querySelector('.tabel-scroll');
                    if (tabelScroll) {
                        tabelScroll.scrollTop = positie.tabelY || 0;
                        tabelScroll.scrollLeft = positie.tabelX || 0;
                    }
                });
            }
            var bewaarScrollPositie = function () {
                var tabelScroll = document.querySelector('.tabel-scroll');
                sessionStorage.setItem(scrollSleutel, JSON.stringify({
                    windowY: window.scrollY,
                    tabelY: tabelScroll ? tabelScroll.scrollTop : 0,
                    tabelX: tabelScroll ? tabelScroll.scrollLeft : 0
                }));
            };
            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', bewaarScrollPositie);
            });
        })();
    </script>
</body>

</html>