<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/mail_tracking.php';

zorgVoorMailTrackingTabel($pdo);

$melding = '';
$resultaten = [];
$actie = $_POST['actie'] ?? '';

if ($actie === 'json_laden') {
    $upload = $_FILES['mailconcept'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $melding = 'Kies een geldig JSON-bestand om te laden.';
    } elseif (($upload['size'] ?? 0) > 2 * 1024 * 1024) {
        $melding = 'Het JSON-bestand mag maximaal 2 MB groot zijn.';
    } else {
        try {
            $concept = json_decode((string) file_get_contents($upload['tmp_name']), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($concept) || ($concept['versie'] ?? null) !== 1 || !is_string($concept['onderwerp'] ?? null) || !is_string($concept['bericht'] ?? null)) {
                throw new RuntimeException('Onbekend of onvolledig mailconcept.');
            }
            $_POST['activiteit_id'] = (int) ($concept['activiteit_id'] ?? 0);
            $_POST['onderwerp'] = $concept['onderwerp'];
            $_POST['bericht'] = $concept['bericht'];
            $melding = 'Het mailconcept is geladen.';
        } catch (Throwable $e) {
            $melding = 'Mailconcept niet geladen: ' . $e->getMessage();
        }
    }
}

$activiteitId = (int) ($_GET['activiteit_id'] ?? $_POST['activiteit_id'] ?? 0);
$activiteiten = $pdo->query(
    'SELECT id, datum, plaats, omschrijving FROM activiteiten ORDER BY datum DESC'
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

$deelnemers = [];
if ($gekozenActiviteit !== null) {
    $stmt = $pdo->prepare(
        "SELECT d.id, d.voornaam, d.achternaam, d.email, i.naam AS instrument, ad.partij
         FROM activiteit_deelnemers ad
         JOIN deelnemers d ON d.id = ad.deelnemer_id
         LEFT JOIN instrumenten i ON i.id = ad.instrument_id
         WHERE ad.activiteit_id = ? AND ad.toegelaten = 1
         ORDER BY i.id, d.achternaam, d.voornaam"
    );
    $stmt->execute([$activiteitId]);
    $deelnemers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$selectieIngediend = isset($_POST['selectie_ingediend']);
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
$onderwerp = trim($_POST['onderwerp'] ?? $standaardOnderwerp);
$bericht = trim($_POST['bericht'] ?? $standaardBericht);

if ($actie === 'json_downloaden') {
    $bestandsnaam = 'mozart-mailconcept-' . date('Y-m-d-His') . '.json';
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $bestandsnaam . '"');
    echo json_encode([
        'versie' => 1,
        'activiteit_id' => $activiteitId,
        'onderwerp' => $onderwerp,
        'bericht' => $bericht,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function vulMailTemplate(string $template, array $deelnemer, array $activiteit): string
{
    $instrument = trim((string) ($deelnemer['instrument'] ?? ''));
    $partij = trim((string) ($deelnemer['partij'] ?? ''));
    $waarden = [
        '{{voornaam}}' => $deelnemer['voornaam'] ?? '',
        '{{achternaam}}' => $deelnemer['achternaam'] ?? '',
        '{{instrument}}' => $instrument,
        '{{partij}}' => $partij,
        '{{instrument_partij}}' => trim($instrument . ' ' . $partij),
        '{{datum}}' => date('d-m-Y', strtotime($activiteit['datum'])),
        '{{plaats}}' => $activiteit['plaats'] ?? '',
        '{{omschrijving}}' => $activiteit['omschrijving'] ?? '',
    ];

    return str_replace(
        array_keys($waarden),
        array_map(static fn ($waarde): string => htmlspecialchars((string) $waarde, ENT_QUOTES, 'UTF-8'), $waarden),
        $template
    );
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
        $wachtrij = [
            'activiteit' => $gekozenActiviteit,
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
    unset($_SESSION[$wachtrijSleutel]);
    $wachtrij = null;
    $melding = 'De verzendwachtrij is gewist.';
}

if ($actie === 'verwerk_pluk' && is_array($wachtrij)) {
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
                        $mailer->Subject = str_replace(["\r", "\n"], '', html_entity_decode(vulMailTemplate($wachtrij['onderwerp'], $ontvanger, $wachtrij['activiteit']), ENT_QUOTES, 'UTF-8'));
                        $ontvanger['tracking_token'] ??= bin2hex(random_bytes(32));
                        registreerMailTracking($pdo, $ontvanger['tracking_token'], (int) $wachtrij['activiteit']['id'], (int) $ontvanger['id'], $ontvanger['email']);
                        $mailer->Body = voegTrackingPixelToe(
                            sluitLokaleAfbeeldingenIn(vulMailTemplate($wachtrij['bericht'], $ontvanger, $wachtrij['activiteit']), $mailer),
                            $ontvanger['tracking_token']
                        );
                        $mailer->AltBody = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $mailer->Body)), ENT_QUOTES, 'UTF-8'));
                        $mailer->send();
                        markeerMailVerzonden($pdo, $ontvanger['tracking_token']);
                        $ontvanger['status'] = 'verzonden';
                        $ontvanger['verzonden_op'] = date(DATE_ATOM);
                        $resultaten[] = ['gelukt' => true, 'naam' => $naam, 'bericht' => $ontvanger['email']];
                    } catch (Throwable $e) {
                        $ontvanger['status'] = 'mislukt';
                        $ontvanger['fout'] = $e->getMessage();
                        $resultaten[] = ['gelukt' => false, 'naam' => $naam, 'bericht' => $e->getMessage()];
                    }
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
                        $ingevuldOnderwerp = str_replace(["\r", "\n"], '', html_entity_decode(vulMailTemplate($onderwerp, $deelnemer, $gekozenActiviteit), ENT_QUOTES, 'UTF-8'));
                        $mailer->Subject = '[TEST] ' . $ingevuldOnderwerp;
                        $mailer->Body = sluitLokaleAfbeeldingenIn(vulMailTemplate($bericht, $deelnemer, $gekozenActiviteit), $mailer);
                        $mailer->AltBody = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $mailer->Body)), ENT_QUOTES, 'UTF-8'));
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
    $trackingTokens = array_values(array_filter(array_column($wachtrij['ontvangers'], 'tracking_token')));
    if ($trackingTokens !== []) {
        $placeholders = implode(',', array_fill(0, count($trackingTokens), '?'));
        $stmt = $pdo->prepare("SELECT token, geopend_op FROM mail_tracking WHERE token IN ($placeholders)");
        $stmt->execute($trackingTokens);
        $geopendPerToken = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

$wachtrijTellingen = ['wachtend' => 0, 'verzonden' => 0, 'mislukt' => 0];
if (is_array($wachtrij)) {
    foreach ($wachtrij['ontvangers'] as $ontvanger) {
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
            <label for="activiteit_id"><strong>Activiteit</strong></label>
            <select class="w3-select w3-border" id="activiteit_id" name="activiteit_id" onchange="this.form.submit()">
                <?php foreach ($activiteiten as $activiteit): ?>
                    <option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>>
                        <?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <form method="post" enctype="multipart/form-data" class="w3-margin-bottom">
            <label for="mailconcept"><strong>Mailconcept laden</strong></label>
            <input class="w3-input w3-border" id="mailconcept" name="mailconcept" type="file" accept="application/json,.json" required>
            <button class="w3-button w3-light-grey w3-margin-top" type="submit" name="actie" value="json_laden">JSON laden</button>
        </form>

        <?php if (is_array($wachtrij)): ?>
            <section class="w3-panel w3-pale-blue w3-leftbar w3-border-blue">
                <h4>Verzendwachtrij</h4>
                <p>
                    <strong><?= htmlspecialchars(date('d-m-Y', strtotime($wachtrij['activiteit']['datum'])) . ' - ' . $wachtrij['activiteit']['plaats']) ?></strong><br>
                    <?= $wachtrijTellingen['verzonden'] ?> verzonden,
                    <?= $wachtrijTellingen['wachtend'] ?> wachtend,
                    <?= $wachtrijTellingen['mislukt'] ?> mislukt.
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

        <?php if ($gekozenActiviteit !== null): ?>
            <p>
                <strong><?= count($deelnemers) ?> toegelaten deelnemers</strong><br>
                <span id="selectie-aantal"><?= count($geselecteerdeDeelnemers) ?> geselecteerd</span>
            </p>
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
                            <tr>
                                <td>
                                    <input
                                        class="deelnemer-selectie"
                                        type="checkbox"
                                        name="deelnemer_ids[]"
                                        value="<?= (int) $deelnemer['id'] ?>"
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
                <input type="hidden" name="selectie_ingediend" value="1">

                <label for="onderwerp"><strong>Onderwerp</strong></label>
                <input class="w3-input w3-border w3-margin-bottom" id="onderwerp" name="onderwerp" value="<?= htmlspecialchars($onderwerp) ?>" required>

                <p class="w3-small">
                    Invoegcodes: <code>{{voornaam}}</code>, <code>{{achternaam}}</code>, <code>{{instrument_partij}}</code>,
                    <code>{{instrument}}</code>, <code>{{partij}}</code>, <code>{{datum}}</code>, <code>{{plaats}}</code> en <code>{{omschrijving}}</code>.
                </p>
                <label for="bericht"><strong>Bericht</strong></label>
                <textarea id="bericht" name="bericht" required><?= htmlspecialchars($bericht) ?></textarea>

                <button class="w3-button w3-green w3-margin-top" type="submit" name="actie" value="test" data-selectie-vereist <?= $geselecteerdeDeelnemers === [] ? 'disabled' : '' ?>>Testmail naar Dirkjan</button>
                <button class="w3-button w3-blue w3-margin-top" type="submit" name="actie" value="versturen" data-selectie-vereist <?= $geselecteerdeDeelnemers === [] ? 'disabled' : '' ?>>Verzending in plukjes starten</button>
                <button class="w3-button w3-light-grey w3-margin-top" type="submit" name="actie" value="json_downloaden">Concept als JSON opslaan</button>
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
            if (event.submitter && event.submitter.value === 'json_downloaden') {
                return true;
            }
            return confirm(`Een nieuwe wachtrij voor ${aantalGeselecteerd} geselecteerde deelnemers starten en de eerste pluk van maximaal 20 nu versturen?`);
        }

        const deelnemerSelecties = Array.from(document.querySelectorAll('.deelnemer-selectie'));
        const selecteerAlles = document.getElementById('selecteer-alles');
        const selectieAantal = document.getElementById('selectie-aantal');

        function werkSelectieBij() {
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
        }

        selecteerAlles?.addEventListener('change', () => {
            deelnemerSelecties.forEach((checkbox) => {
                checkbox.checked = selecteerAlles.checked;
            });
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
                    plukFormulier.requestSubmit();
                }
            }

            werkAftellerBij();
            window.setInterval(werkAftellerBij, 1000);
        <?php endif; ?>

        if (document.getElementById('bericht')) {
            CKEDITOR.replace('bericht', {
                height: 360,
                language: 'nl',
                versionCheck: false,
                extraPlugins: 'autogrow,autolink,codesnippet,emoji,placeholder,tableresize,uicolor',
                removePlugins: 'a11ychecker,ckfinder,cloudservices,easyimage,exportpdf'
            });
        }
    </script>
</body>

</html>