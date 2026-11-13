<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../includes/deelnemer_wijzigingsdatum.php';
require_once __DIR__ . '/../vendor/autoload.php';

zorgVoorDeelnemerWijzigingsdatum($pdo);

$statussen = ['' => '(onbekend)', 'ja' => 'ja', 'nee' => 'nee', 'misschien' => 'misschien'];
$melding = '';
$foutmelding = '';

function leesDeelnemerBewerkMailInstellingen(): array
{
    $gebruikersnaam = getenv('MOZART_GMAIL_USERNAME') ?: 'info@mozartopzaterdag.nl';
    $wachtwoord = getenv('MOZART_GMAIL_APP_PASSWORD') ?: '';
    $bestand = __DIR__ . '/../includes/_tst/MOZART_GMAIL_USERNAME.txt';
    if ($wachtwoord !== '' || !is_readable($bestand)) {
        return [$gebruikersnaam, preg_replace('/\s+/', '', $wachtwoord)];
    }

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

    return [$gebruikersnaam, $wachtwoord];
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: deelnemers_bewerken.php');
    exit;
}

$activiteiten = $pdo->query(
    'SELECT id, datum, plaats FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum'
)->fetchAll(PDO::FETCH_ASSOC);

$instrumenten = $pdo->query("SELECT id, naam FROM instrumenten ORDER BY CASE WHEN LOWER(TRIM(naam)) = 'pauken' THEN COALESCE((SELECT MIN(i2.id) FROM instrumenten i2 WHERE LOWER(TRIM(i2.naam)) LIKE 'trompet%'), id) ELSE id END, CASE WHEN LOWER(TRIM(naam)) = 'pauken' THEN 1 ELSE 0 END, id")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['actie']) && $_POST['actie'] === 'verwijderen') {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM mail_tracking WHERE deelnemer_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM activiteit_deelnemers WHERE deelnemer_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM deelnemer_instrumenten WHERE deelnemer_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM deelnemer_wijzigingen WHERE deelnemer_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM deelnemers WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    header('Location: deelnemers_bewerken.php?melding=' . rawurlencode('Deelnemer volledig verwijderd.'));
    exit;
}

if (isset($_POST['actie']) && $_POST['actie'] === 'bevestiging_mail') {
    try {
        $stmt = $pdo->prepare('SELECT * FROM deelnemers WHERE id = ?');
        $stmt->execute([$id]);
        $deelnemerVoorMail = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($deelnemerVoorMail === false || !filter_var($deelnemerVoorMail['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('De deelnemer heeft geen geldig e-mailadres.');
        }

        [$mailGebruikersnaam, $mailWachtwoord] = leesDeelnemerBewerkMailInstellingen();
        if ($mailWachtwoord === '') {
            throw new RuntimeException('Het Gmail-app-wachtwoord ontbreekt in de configuratie.');
        }

        $mailbestand = __DIR__ . '/../JSON/aanmeldbevestiging.json';
        $mailTemplate = json_decode((string) file_get_contents($mailbestand), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($mailTemplate) || !is_string($mailTemplate['onderwerp'] ?? null) || !is_string($mailTemplate['tekst'] ?? null)) {
            throw new RuntimeException('De bevestigingsmail heeft een ongeldig formaat.');
        }

        $stmt = $pdo->prepare(
            'SELECT i.naam FROM deelnemer_instrumenten di
             JOIN instrumenten i ON i.id = di.instrument_id
             WHERE di.deelnemer_id = ? ORDER BY i.id'
        );
        $stmt->execute([$id]);
        $instrumentNamen = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare(
            'SELECT a.datum, a.omschrijving, ad.status
             FROM activiteiten a
             LEFT JOIN activiteit_deelnemers ad ON ad.activiteit_id = a.id AND ad.deelnemer_id = ?
             WHERE a.datum >= CURDATE() ORDER BY a.datum'
        );
        $stmt->execute([$id]);
        $beschikbaarheidRijen = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $activiteit) {
            $status = (string) ($activiteit['status'] ?? '');
            $statusLabel = $statussen[$status] ?? '(onbekend)';
            $statusKleur = match ($status) {
                'ja' => '#198754',
                'misschien' => '#ff9800',
                'nee' => '#dc3545',
                default => '#6c757d',
            };
            $beschikbaarheidRijen[] = '<tr><td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;">'
                . htmlspecialchars(date('j-n-Y', strtotime($activiteit['datum'])), ENT_QUOTES, 'UTF-8')
                . '</td><td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;">'
                . htmlspecialchars($activiteit['omschrijving'] ?? '', ENT_QUOTES, 'UTF-8')
                . '</td><td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right;"><strong style="color:'
                . $statusKleur . '">' . htmlspecialchars(ucfirst($statusLabel), ENT_QUOTES, 'UTF-8')
                . '</strong></td></tr>';
        }
        $beschikbaarheidInhoud = $beschikbaarheidRijen !== []
            ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px 10px;border-bottom:2px solid #cbd5e1;font-weight:bold;">Datum</td><td style="padding:8px 10px;border-bottom:2px solid #cbd5e1;font-weight:bold;">Activiteit</td><td style="padding:8px 10px;border-bottom:2px solid #cbd5e1;font-weight:bold;text-align:right;">Status</td></tr>' . implode('', $beschikbaarheidRijen) . '</table>'
            : '<p style="margin:0;">Geen toekomstige activiteiten opgegeven.</p>';
        $opDeHoogteGehouden = (int) ($deelnemerVoorMail['op_de_hoogte_houden'] ?? 0) === 1;
        $beschikbaarheidBlok = $opDeHoogteGehouden
            ? '<tr><td style="padding:0 24px 24px;"><p style="margin:0 0 10px;font-size:16px;font-weight:bold;color:#273746;">Beschikbaarheid</p>' . $beschikbaarheidInhoud . '</td></tr>'
            : '';
        $waarden = [
            '{{voornaam}}' => (string) $deelnemerVoorMail['voornaam'],
            '{{achternaam}}' => (string) $deelnemerVoorMail['achternaam'],
            '{{naam}}' => trim($deelnemerVoorMail['voornaam'] . ' ' . $deelnemerVoorMail['achternaam']),
            '{{email}}' => (string) $deelnemerVoorMail['email'],
            '{{telefoon}}' => (string) ($deelnemerVoorMail['telefoon'] ?? ''),
            '{{postcode}}' => (string) ($deelnemerVoorMail['postcode'] ?? ''),
            '{{plaats}}' => (string) ($deelnemerVoorMail['plaats'] ?? ''),
            '{{instrumenten}}' => $instrumentNamen !== [] ? implode(', ', $instrumentNamen) : 'geen instrument opgegeven',
            '{{beschikbaarheid}}' => $beschikbaarheidInhoud,
            '{{beschikbaarheid_blok}}' => $beschikbaarheidBlok,
            '{{op_de_hoogte_gehouden_antwoord}}' => $opDeHoogteGehouden
                ? 'Ja'
                : '<strong style="color:#d00">Nee. We zullen je gegevens uit ons bestand verwijderen.</strong>',
            '{{aanmeldlink}}' => 'https://mozartopzaterdag.nl/deelnemers_aanmelden.php?email=' . rawurlencode($deelnemerVoorMail['email']),
        ];
        $renderTemplate = static function (string $template) use ($waarden): string {
            $vervanging = [];
            foreach ($waarden as $placeholder => $waarde) {
                $vervanging[$placeholder] = in_array(
                    $placeholder,
                    ['{{beschikbaarheid}}', '{{beschikbaarheid_blok}}', '{{op_de_hoogte_gehouden_antwoord}}'],
                    true
                )
                    ? $waarde
                    : htmlspecialchars($waarde, ENT_QUOTES, 'UTF-8');
            }

            return str_replace(array_keys($vervanging), array_values($vervanging), $template);
        };

        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = 'send.one.com';
        $mailer->SMTPAuth = true;
        $mailer->Username = $mailGebruikersnaam;
        $mailer->Password = $mailWachtwoord;
        $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mailer->Port = 465;
        $mailer->Timeout = 5;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($mailGebruikersnaam, 'Mozart op Zaterdag');
        $mailer->addReplyTo($mailGebruikersnaam, 'Mozart op Zaterdag');
        $mailer->addAddress($deelnemerVoorMail['email'], trim($deelnemerVoorMail['voornaam'] . ' ' . $deelnemerVoorMail['achternaam']));
        if (strcasecmp($deelnemerVoorMail['email'], 'info@mozartopzaterdag.nl') !== 0) {
            $mailer->addCC('info@mozartopzaterdag.nl', 'Mozart op Zaterdag');
        }
        $mailer->isHTML(true);
        $mailer->Subject = str_replace(["\r", "\n"], '', html_entity_decode(strip_tags($renderTemplate($mailTemplate['onderwerp'])), ENT_QUOTES, 'UTF-8'));
        $mailer->Body = $renderTemplate($mailTemplate['tekst']);
        $mailer->AltBody = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $mailer->Body)), ENT_QUOTES, 'UTF-8'));
        $mailer->send();
        $melding = 'De bevestigingsmail is verstuurd aan de deelnemer met info@mozartopzaterdag.nl in cc.';
    } catch (Throwable $e) {
        error_log('Deelnemer bewerken: bevestigingsmail niet verstuurd: ' . $e->getMessage());
        $foutmelding = 'De bevestigingsmail kon niet worden verstuurd: ' . $e->getMessage();
    }
}

if (isset($_POST['actie']) && $_POST['actie'] === 'opslaan') {
    $voornaam = trim($_POST['voornaam'] ?? '');
    $achternaam = trim($_POST['achternaam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefoon = trim($_POST['telefoon'] ?? '');
    $telefoon = $telefoon === '' ? null : $telefoon;
    $postcode = trim($_POST['postcode'] ?? '');
    $postcode = $postcode === '' ? null : $postcode;
    $plaats = trim($_POST['plaats'] ?? '');
    $plaats = $plaats === '' ? null : $plaats;
    $muzikaleErvaring = trim($_POST['muzikale_ervaring'] ?? '');
    $muzikaleErvaring = $muzikaleErvaring === '' ? null : $muzikaleErvaring;
    $opmerkingen = trim($_POST['opmerkingen'] ?? '');
    $opmerkingen = $opmerkingen === '' ? null : $opmerkingen;
    $opDeHoogteHouden = $_POST['op_de_hoogte_houden'] ?? '';
    $opDeHoogteHouden = in_array($opDeHoogteHouden, ['0', '1'], true) ? (int) $opDeHoogteHouden : null;
    $instrument_ids = array_map('intval', $_POST['instrumenten'] ?? []);
    $voorkeuren = trim($_POST['voorkeuren'] ?? '');
    $voorkeuren = $voorkeuren === '' ? null : $voorkeuren;

    $stmt = $pdo->prepare('SELECT 1 FROM deelnemers WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $id]);
    $emailInGebruik = (bool) $stmt->fetchColumn();

    if ($voornaam === '' || $achternaam === '' || $email === '') {
        $foutmelding = 'Voornaam, achternaam en e-mail zijn verplicht.';
    } elseif ($emailInGebruik) {
        $foutmelding = 'Dit e-mailadres is al in gebruik bij een andere deelnemer.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE deelnemers SET voornaam = ?, achternaam = ?, email = ?, telefoon = ?, postcode = ?, plaats = ?, muzikale_ervaring = ?, opmerkingen = ?, op_de_hoogte_houden = ?, laatst_gewijzigd_op = NOW() WHERE id = ?'
        );
        $stmt->execute([$voornaam, $achternaam, $email, $telefoon, $postcode, $plaats, $muzikaleErvaring, $opmerkingen, $opDeHoogteHouden, $id]);
        $pdo->prepare('DELETE FROM deelnemer_wijzigingen WHERE deelnemer_id = ?')->execute([$id]);

        // Instrumenten: bestaande koppelingen vervangen door de nu aangevinkte selectie.
        $pdo->prepare('DELETE FROM deelnemer_instrumenten WHERE deelnemer_id = ?')->execute([$id]);
        if ($instrument_ids !== []) {
            $stmt = $pdo->prepare('INSERT INTO deelnemer_instrumenten (deelnemer_id, instrument_id, voorkeuren) VALUES (?, ?, ?)');
            foreach ($instrument_ids as $instrument_id) {
                $stmt->execute([$id, $instrument_id, $voorkeuren]);
            }
        }

        // Beschikbaarheid per komende activiteit; leeg = geen opgave (rij wordt verwijderd).
        foreach ($activiteiten as $activiteit) {
            $veld = 'status_' . $activiteit['id'];
            $status = $_POST[$veld] ?? '';
            if ($status === '') {
                $pdo->prepare('DELETE FROM activiteit_deelnemers WHERE activiteit_id = ? AND deelnemer_id = ?')
                    ->execute([$activiteit['id'], $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO activiteit_deelnemers (activiteit_id, deelnemer_id, status) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE status = VALUES(status)'
                )->execute([$activiteit['id'], $id, $status]);
            }
        }

        $melding = 'Deelnemer opgeslagen.';
    }
}

$stmt = $pdo->prepare('SELECT * FROM deelnemers WHERE id = ?');
$stmt->execute([$id]);
$deelnemer = $stmt->fetch(PDO::FETCH_ASSOC);
if ($deelnemer === false) {
    header('Location: deelnemers_bewerken.php');
    exit;
}

$stmt = $pdo->prepare('SELECT instrument_id, voorkeuren FROM deelnemer_instrumenten WHERE deelnemer_id = ?');
$stmt->execute([$id]);
$gekozenInstrumenten = [];
$voorkeuren = '';
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rij) {
    $gekozenInstrumenten[] = (int) $rij['instrument_id'];
    if ($voorkeuren === '' && $rij['voorkeuren'] !== null) {
        $voorkeuren = $rij['voorkeuren'];
    }
}

$stmt = $pdo->prepare('SELECT activiteit_id, status FROM activiteit_deelnemers WHERE deelnemer_id = ?');
$stmt->execute([$id]);
$statusPerActiviteit = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'status', 'activiteit_id');
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deelnemer bewerken</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <style>
        .form-group { margin-bottom: 1.2em; }
        .form-group label { display: block; margin-bottom: 0.3em; font-weight: bold; }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1em; }
        .naam-niet-op-hoogte { color: #d00; }
        @media (max-width: 600px) {
            body {
                margin: 0;
                font-size: 16px;
                line-height: 1.45;
            }

            .w3-content.w3-panel {
                width: 100%;
                max-width: none !important;
                margin: 0;
                padding: 0 10px 16px;
                box-sizing: border-box;
            }

            .w3-content > .w3-panel {
                margin-left: 0;
                margin-right: 0;
            }

            h3 {
                font-size: 1.35rem;
            }

            h4 {
                font-size: 1.1rem;
                margin-top: 1.5em;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-group {
                margin-bottom: 1.4em;
            }

            .form-group label {
                margin-bottom: 0.45em;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                box-sizing: border-box;
                width: 100%;
                font-size: 16px;
                line-height: 1.4;
            }

            .knoppen {
                flex-direction: column;
                gap: 0.75em;
            }

            .knoppen .w3-button {
                min-height: 44px;
                font-size: 1rem;
            }
        }
        .knoppen { display: flex; gap: 1em; margin-top: 1.5em; }
    </style>
    <script>
        function bevestigOpslaan() {
            return confirm('Weet je zeker dat je de wijzigingen wilt opslaan?');
        }

        function bevestigVerwijderen() {
            return confirm('Weet je zeker dat je deze deelnemer helemaal wilt verwijderen? Dit kan niet ongedaan worden gemaakt.');
        }

        function bevestigMailVersturen() {
            return confirm('De opgeslagen inschrijving per e-mail bevestigen aan de deelnemer, met info@mozartopzaterdag.nl in cc?');
        }
    </script>
</head>

<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:800px;">
        <p><a href="deelnemers_bewerken.php">&larr; Terug naar overzicht</a></p>
        <h3>Deelnemer bewerken:
            <span class="<?= (int) ($deelnemer['op_de_hoogte_houden'] ?? 1) === 0 ? 'naam-niet-op-hoogte' : '' ?>"><?= htmlspecialchars(trim($deelnemer['voornaam'] . ' ' . $deelnemer['achternaam'])) ?></span>
        </h3>
        <p>Laatste wijziging:
            <?= $deelnemer['laatst_gewijzigd_op'] !== null
                ? htmlspecialchars(date('d-m-Y H:i', strtotime($deelnemer['laatst_gewijzigd_op'])))
                : 'Onbekend' ?>
        </p>

        <?php if ($melding !== ''): ?>
            <p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p>
        <?php endif; ?>
        <?php if ($foutmelding !== ''): ?>
            <p class="w3-panel w3-pale-red w3-leftbar w3-border-red"><?= htmlspecialchars($foutmelding) ?></p>
        <?php endif; ?>

        <form id="deelnemer-formulier" method="post" onsubmit="return bevestigOpslaan();">
            <input type="hidden" name="actie" value="opslaan">
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <h4>NAW-gegevens</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="voornaam">Voornaam *</label>
                    <input class="w3-input" type="text" id="voornaam" name="voornaam" value="<?= htmlspecialchars($deelnemer['voornaam']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="achternaam">Achternaam *</label>
                    <input class="w3-input" type="text" id="achternaam" name="achternaam" value="<?= htmlspecialchars($deelnemer['achternaam']) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="email">E-mailadres *</label>
                <input class="w3-input" type="email" id="email" name="email" value="<?= htmlspecialchars($deelnemer['email']) ?>" required>
            </div>
            <div class="form-group">
                <label for="telefoon">Telefoon</label>
                <input class="w3-input" type="text" id="telefoon" name="telefoon" value="<?= htmlspecialchars($deelnemer['telefoon'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="postcode">Postcode</label>
                    <input class="w3-input" type="text" id="postcode" name="postcode" value="<?= htmlspecialchars($deelnemer['postcode'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="plaats">Plaats</label>
                    <input class="w3-input" type="text" id="plaats" name="plaats" value="<?= htmlspecialchars($deelnemer['plaats'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Op de hoogte houden?</label>
                <select class="w3-select" name="op_de_hoogte_houden">
                    <option value="1" <?= (int) ($deelnemer['op_de_hoogte_houden'] ?? 0) === 1 ? 'selected' : '' ?>>Ja</option>
                    <option value="0" <?= (int) ($deelnemer['op_de_hoogte_houden'] ?? 0) === 0 ? 'selected' : '' ?>>Nee</option>
                </select>
            </div>
            <div class="form-group">
                <label for="muzikale_ervaring">Muzikale ervaring</label>
                <textarea class="w3-input" id="muzikale_ervaring" name="muzikale_ervaring" maxlength="500" rows="4"><?= htmlspecialchars($deelnemer['muzikale_ervaring'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="opmerkingen">Opmerkingen</label>
                <textarea class="w3-input" id="opmerkingen" name="opmerkingen" maxlength="1000" rows="4"><?= htmlspecialchars($deelnemer['opmerkingen'] ?? '') ?></textarea>
            </div>

            <h4>Instrumenten</h4>
            <div class="form-group">
                <select class="w3-select" name="instrumenten[]" multiple size="8">
                    <?php foreach ($instrumenten as $instrument): ?>
                        <option value="<?= (int) $instrument['id'] ?>" <?= in_array((int) $instrument['id'], $gekozenInstrumenten, true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($instrument['naam']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="voorkeuren">Voorkeur</label>
                <input class="w3-input" type="text" id="voorkeuren" name="voorkeuren" value="<?= htmlspecialchars($voorkeuren) ?>">
            </div>

            <h4>Beschikbaarheid</h4>
            <?php foreach ($activiteiten as $activiteit): ?>
                <div class="form-group">
                    <label><?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?></label>
                    <select class="w3-select" name="status_<?= (int) $activiteit['id'] ?>">
                        <?php foreach ($statussen as $waarde => $label): ?>
                            <option value="<?= $waarde ?>" <?= ($statusPerActiviteit[$activiteit['id']] ?? '') === $waarde ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

        </form>

        <div class="knoppen">
            <button class="w3-button w3-blue" type="submit" form="deelnemer-formulier">Wijzigingen opslaan</button>
            <form method="post" onsubmit="return bevestigVerwijderen();">
                <input type="hidden" name="actie" value="verwijderen">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button class="w3-button w3-red" type="submit">Deelnemer verwijderen</button>
            </form>
            <form method="post" onsubmit="return bevestigMailVersturen();">
                <input type="hidden" name="actie" value="bevestiging_mail">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button class="w3-button w3-green" type="submit">Inschrijving per e-mail bevestigen</button>
            </form>
        </div>
    </div>
</body>

</html>
