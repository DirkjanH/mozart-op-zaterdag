<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../vendor/autoload.php';

$melding = '';
$resultaten = [];
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

$standaardOnderwerp = 'Mozart op Zaterdag';
$standaardBericht = <<<'HTML'
<p>Beste {{voornaam}},</p>
<p>Je bent ingedeeld als <strong>{{instrument_partij}}</strong>.</p>
<p>Met muzikale groet,<br>Dirkjan Horringa</p>
HTML;
$onderwerp = trim($_POST['onderwerp'] ?? $standaardOnderwerp);
$bericht = trim($_POST['bericht'] ?? $standaardBericht);

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

$actie = $_POST['actie'] ?? '';
if (in_array($actie, ['versturen', 'test'], true)) {
    if ($gekozenActiviteit === null) {
        $melding = 'Kies eerst een geldige activiteit.';
    } elseif ($onderwerp === '' || $bericht === '') {
        $melding = 'Onderwerp en bericht zijn verplicht.';
    } elseif ($deelnemers === []) {
        $melding = 'Deze activiteit heeft geen toegelaten deelnemers.';
    } else {
        [$gebruikersnaam, $wachtwoord] = leesMailInstellingen();
        if ($wachtwoord === '') {
            $melding = 'Mail niet verstuurd: het app-wachtwoord ontbreekt in de configuratie.';
        } else {
            $isTest = $actie === 'test';
            $teVersturenDeelnemers = $isTest ? [$deelnemers[0]] : $deelnemers;
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
                    $ontvangerEmail = $isTest ? 'dirkjan@pellegrina.net' : $deelnemer['email'];
                    $ontvangerNaam = $isTest ? 'Dirkjan Horringa' : $naam;
                    if (!filter_var($ontvangerEmail, FILTER_VALIDATE_EMAIL)) {
                        $resultaten[] = ['gelukt' => false, 'naam' => $naam, 'bericht' => 'ongeldig e-mailadres'];
                        continue;
                    }
                    try {
                        $mailer->clearAddresses();
                        $mailer->addAddress($ontvangerEmail, $ontvangerNaam);
                        $ingevuldOnderwerp = str_replace(["\r", "\n"], '', html_entity_decode(vulMailTemplate($onderwerp, $deelnemer, $gekozenActiviteit), ENT_QUOTES, 'UTF-8'));
                        $mailer->Subject = ($isTest ? '[TEST] ' : '') . $ingevuldOnderwerp;
                        $mailer->Body = vulMailTemplate($bericht, $deelnemer, $gekozenActiviteit);
                        $mailer->AltBody = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $mailer->Body)), ENT_QUOTES, 'UTF-8'));
                        $mailer->send();
                        $resultaten[] = ['gelukt' => true, 'naam' => $naam, 'bericht' => $ontvangerEmail];
                    } catch (Throwable $e) {
                        $resultaten[] = ['gelukt' => false, 'naam' => $naam, 'bericht' => $e->getMessage()];
                    }
                }
                $mailer->smtpClose();
                $aantalGelukt = count(array_filter($resultaten, static fn (array $resultaat): bool => $resultaat['gelukt']));
                $melding = $isTest
                    ? ($aantalGelukt === 1 ? 'Testmail verstuurd naar dirkjan@pellegrina.net met de gegevens van ' . $deelnemers[0]['voornaam'] . '.' : 'Testmail niet verstuurd.')
                    : $aantalGelukt . ' van ' . count($deelnemers) . ' mails verstuurd.';
            } catch (Throwable $e) {
                $melding = 'Mail niet verstuurd: ' . $e->getMessage();
            }
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

        <?php if ($gekozenActiviteit !== null): ?>
            <p><strong><?= count($deelnemers) ?> toegelaten deelnemers</strong></p>
            <?php if ($deelnemers !== []): ?>
                <div class="w3-responsive w3-margin-bottom" style="max-height:250px; overflow:auto;">
                    <table class="w3-table w3-bordered w3-striped w3-small">
                        <tr><th>Naam</th><th>E-mail</th><th>Instrument + partij</th></tr>
                        <?php foreach ($deelnemers as $deelnemer): ?>
                            <tr>
                                <td><?= htmlspecialchars($deelnemer['voornaam'] . ' ' . $deelnemer['achternaam']) ?></td>
                                <td><?= htmlspecialchars($deelnemer['email']) ?></td>
                                <td><?= htmlspecialchars(trim(($deelnemer['instrument'] ?? '') . ' ' . ($deelnemer['partij'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>

            <form method="post" onsubmit="return bevestigVerzending(event);">
                <input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>">

                <label for="onderwerp"><strong>Onderwerp</strong></label>
                <input class="w3-input w3-border w3-margin-bottom" id="onderwerp" name="onderwerp" value="<?= htmlspecialchars($onderwerp) ?>" required>

                <p class="w3-small">
                    Invoegcodes: <code>{{voornaam}}</code>, <code>{{achternaam}}</code>, <code>{{instrument_partij}}</code>,
                    <code>{{instrument}}</code>, <code>{{partij}}</code>, <code>{{datum}}</code>, <code>{{plaats}}</code> en <code>{{omschrijving}}</code>.
                </p>
                <label for="bericht"><strong>Bericht</strong></label>
                <textarea id="bericht" name="bericht" required><?= htmlspecialchars($bericht) ?></textarea>

                <button class="w3-button w3-green w3-margin-top" type="submit" name="actie" value="test" <?= $deelnemers === [] ? 'disabled' : '' ?>>Testmail naar Dirkjan</button>
                <button class="w3-button w3-blue w3-margin-top" type="submit" name="actie" value="versturen" <?= $deelnemers === [] ? 'disabled' : '' ?>>Mail aan alle deelnemers versturen</button>
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
            if (event.submitter && event.submitter.value === 'test') {
                return confirm('Testmail naar dirkjan@pellegrina.net versturen met de gegevens van de eerste deelnemer?');
            }
            return confirm('Deze mail nu afzonderlijk naar alle <?= count($deelnemers) ?> toegelaten deelnemers versturen?');
        }

        CKEDITOR.replace('bericht', {
            height: 360,
            language: 'nl',
            versionCheck: false,
            extraPlugins: 'autogrow,autolink,codesnippet,emoji,placeholder,tableresize,uicolor',
            removePlugins: 'a11ychecker,ckfinder,cloudservices,easyimage,exportpdf'
        });
    </script>
</body>

</html>