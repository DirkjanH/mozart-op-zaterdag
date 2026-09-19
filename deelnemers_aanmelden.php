<?php
require_once __DIR__ . '/connections/MozartopZaterdag.php';
require_once __DIR__ . '/vendor/autoload.php';

function laadAanmeldbevestigingMail(string $bestand): array
{
    $mailtekst = json_decode((string) file_get_contents($bestand), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($mailtekst) || !is_string($mailtekst['onderwerp'] ?? null) || !is_string($mailtekst['tekst'] ?? null)) {
        throw new RuntimeException('De bevestigingsmail heeft een ongeldig formaat.');
    }

    return $mailtekst;
}

function vulAanmeldbevestigingTemplate(string $template, array $waarden): string
{
    $beschikbaarheid = $waarden['{{beschikbaarheid}}'] ?? '';
    unset($waarden['{{beschikbaarheid}}']);

    return str_replace(
        '{{beschikbaarheid}}',
        $beschikbaarheid,
        str_replace(
            array_keys($waarden),
            array_map(static fn (string $waarde): string => htmlspecialchars($waarde, ENT_QUOTES, 'UTF-8'), array_values($waarden)),
            $template
        )
    );
}

function nederlandseDatum(DateTimeInterface $datum): string
{
    $maanden = [
        1 => 'januari',
        2 => 'februari',
        3 => 'maart',
        4 => 'april',
        5 => 'mei',
        6 => 'juni',
        7 => 'juli',
        8 => 'augustus',
        9 => 'september',
        10 => 'oktober',
        11 => 'november',
        12 => 'december',
    ];

    return $datum->format('j') . ' ' . $maanden[(int) $datum->format('n')] . ' ' . $datum->format('Y');
}

function leesAanmeldMailInstellingen(): array
{
    $gebruikersnaam = getenv('MOZART_GMAIL_USERNAME') ?: 'info@mozartopzaterdag.nl';
    $wachtwoord = getenv('MOZART_GMAIL_APP_PASSWORD') ?: '';
    $bestand = __DIR__ . '/includes/_tst/MOZART_GMAIL_USERNAME.txt';
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

$melding = '';
$foutmelding = '';
$aanmeldbevestigingBestand = __DIR__ . '/JSON/aanmeldbevestiging.json';
try {
    $aanmeldbevestigingMail = laadAanmeldbevestigingMail($aanmeldbevestigingBestand);
} catch (Throwable $e) {
    error_log('Aanmelding: bevestigingsmail kon niet worden geladen: ' . $e->getMessage());
    $aanmeldbevestigingMail = [
        'onderwerp' => 'Bevestiging aanmelding Mozart op Zaterdag',
        'tekst' => '<p>Beste {{voornaam}},</p><p>Dank voor je aanmelding bij Mozart op Zaterdag.</p><p>Hartelijke groet,<br>Dirkjan Horringa</p>',
    ];
}

// Haal instrumenten op in de volgorde van de instrumententabel, zonder zangstemmen.
$uitgeslotenInstrumenten = ['sopraan', 'alt', 'tenor', 'bas', 'countertenor', 'mezzosopraan', 'bariton', 'basklarinet', 'tuba', 'contrafagot', 'piano', 'clavecimbel', 'slagwerk', 'orgel', 'piccolo', 'engelse hoorn'];
$instrumenten = $pdo->query('SELECT * FROM instrumenten')->fetchAll(PDO::FETCH_ASSOC);
$instrumenten = array_values(array_filter($instrumenten, static function (array $instrument) use ($uitgeslotenInstrumenten): bool {
    $naam = strtolower(trim((string) ($instrument['naam'] ?? '')));
    return $naam !== '' && !str_contains($naam, 'zang') && !in_array($naam, $uitgeslotenInstrumenten, true);
}));
usort($instrumenten, static function (array $eerste, array $tweede): int {
    return ((int) ($eerste['id'] ?? $eerste['instrument_id'] ?? 0)) <=> ((int) ($tweede['id'] ?? $tweede['instrument_id'] ?? 0));
});
foreach ($instrumenten as &$instrument) {
    $instrument['id'] = (int) ($instrument['id'] ?? $instrument['instrument_id'] ?? 0);
}
unset($instrument);
$pauken = null;
foreach ($instrumenten as $index => $instrument) {
    if (strtolower(trim($instrument['naam'])) === 'pauken') {
        $pauken = $instrument;
        unset($instrumenten[$index]);
        break;
    }
}
if ($pauken !== null) {
    $instrumenten = array_values($instrumenten);
    $paukenIngevoegd = false;
    foreach ($instrumenten as $index => $instrument) {
        if (str_starts_with(strtolower(trim($instrument['naam'])), 'trompet')) {
            array_splice($instrumenten, $index + 1, 0, [$pauken]);
            $paukenIngevoegd = true;
            break;
        }
    }
    if (!$paukenIngevoegd) {
        $instrumenten[] = $pauken;
    }
}

// Haal toekomstige activiteiten op
$activiteiten = $pdo->query('SELECT id, datum, plaats, omschrijving FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum')->fetchAll(PDO::FETCH_ASSOC);

// Verwerk formulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validatie
        $voornaam = trim($_POST['voornaam'] ?? '');
        $achternaam = trim($_POST['achternaam'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefoon = trim($_POST['telefoon'] ?? '');
        $postcode = trim($_POST['postcode'] ?? '');
        $plaats = trim($_POST['plaats'] ?? '');
        $muzikaleErvaring = trim($_POST['muzikale_ervaring'] ?? '');
        $opDeHoogteHouden = $_POST['op_de_hoogte_houden'] ?? '';
        $instrumenten_gekozen = array_filter($_POST['instrumenten'] ?? []);

        if (empty($voornaam) || empty($achternaam) || empty($email)) {
            throw new Exception('Voornaam, achternaam en e-mailadres zijn verplicht.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Ongeldig e-mailadres.');
        }

        if (!in_array($opDeHoogteHouden, ['0', '1'], true)) {
            throw new Exception('Geef aan of je op de hoogte gehouden wilt worden.');
        }

        if (mb_strlen($muzikaleErvaring) > 500) {
            throw new Exception('Muzikale ervaring mag maximaal 500 tekens bevatten.');
        }

        // Zoek bestaande deelnemer
        $stmt = $pdo->prepare('SELECT id FROM deelnemers WHERE email = ?');
        $stmt->execute([$email]);
        $bestaande = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bestaande) {
            $deelnemerId = (int) $bestaande['id'];
            // Update bestaande deelnemer
            $stmt = $pdo->prepare('UPDATE deelnemers SET voornaam = ?, achternaam = ?, telefoon = ?, postcode = ?, plaats = ?, muzikale_ervaring = ?, op_de_hoogte_houden = ? WHERE id = ?');
            $stmt->execute([$voornaam, $achternaam, $telefoon, $postcode, $plaats, $muzikaleErvaring, (int) $opDeHoogteHouden, $deelnemerId]);
        } else {
            // Maak nieuwe deelnemer
            $stmt = $pdo->prepare('INSERT INTO deelnemers (voornaam, achternaam, email, telefoon, postcode, plaats, muzikale_ervaring, op_de_hoogte_houden) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$voornaam, $achternaam, $email, $telefoon, $postcode, $plaats, $muzikaleErvaring, (int) $opDeHoogteHouden]);
            $deelnemerId = (int) $pdo->lastInsertId();
        }

        // Verwijder oude instrumenten voor deze deelnemer
        $stmt = $pdo->prepare('DELETE FROM deelnemer_instrumenten WHERE deelnemer_id = ?');
        $stmt->execute([$deelnemerId]);

        // Voeg nieuwe instrumenten toe
        if (!empty($instrumenten_gekozen)) {
            $stmt = $pdo->prepare('INSERT INTO deelnemer_instrumenten (deelnemer_id, instrument_id) VALUES (?, ?)');
            foreach ($instrumenten_gekozen as $instrument_id) {
                $stmt->execute([$deelnemerId, (int) $instrument_id]);
            }
        }

        // Verwerk activiteitenstatussen
        foreach ($activiteiten as $activiteit) {
            $status = trim($_POST['status_' . (int) $activiteit['id']] ?? 'nee');
            if (!in_array($status, ['ja', 'misschien', 'nee'], true)) {
                $status = 'nee';
            }

            // Zoek bestaande inschrijving
            $stmt = $pdo->prepare('SELECT 1 FROM activiteit_deelnemers WHERE activiteit_id = ? AND deelnemer_id = ?');
            $stmt->execute([(int) $activiteit['id'], $deelnemerId]);
            $bestaande_inschrijving = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($bestaande_inschrijving) {
                // Update alleen status; de toelatingsbeslissing blijft ongewijzigd.
                $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET status = ? WHERE activiteit_id = ? AND deelnemer_id = ?');
                $stmt->execute([$status, (int) $activiteit['id'], $deelnemerId]);
            } else {
                // Maak nieuwe inschrijving
                $stmt = $pdo->prepare('INSERT INTO activiteit_deelnemers (activiteit_id, deelnemer_id, status) VALUES (?, ?, ?)');
                $stmt->execute([(int) $activiteit['id'], $deelnemerId, $status]);
            }
        }

        $instrumentNamen = [];
        foreach ($instrumenten as $instrument) {
            if (in_array((string) $instrument['id'], $instrumenten_gekozen, true)) {
                $instrumentNamen[] = $instrument['naam'];
            }
        }
        $beschikbaarheid = [];
        foreach ($activiteiten as $activiteit) {
            $status = trim($_POST['status_' . (int) $activiteit['id']] ?? 'nee');
            $datum = nederlandseDatum(new DateTimeImmutable($activiteit['datum']));
            $statusKleur = match ($status) {
                'ja' => '#198754',
                'misschien' => '#ff9800',
                default => '#dc3545',
            };
            $beschikbaarheid[] = '<li>' . $datum . ' - ' . htmlspecialchars($activiteit['omschrijving'] ?? '', ENT_QUOTES, 'UTF-8') . ': <strong style="color:' . $statusKleur . '">' . htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') . '</strong></li>';
        }
        $templateWaarden = [
            '{{voornaam}}' => $voornaam,
            '{{achternaam}}' => $achternaam,
            '{{naam}}' => trim($voornaam . ' ' . $achternaam),
            '{{email}}' => $email,
            '{{telefoon}}' => $telefoon,
            '{{postcode}}' => $postcode,
            '{{plaats}}' => $plaats,
            '{{instrumenten}}' => $instrumentNamen !== [] ? implode(', ', $instrumentNamen) : 'geen instrument opgegeven',
            '{{beschikbaarheid}}' => $beschikbaarheid !== [] ? '<ul>' . implode('', $beschikbaarheid) . '</ul>' : 'geen toekomstige activiteiten',
        ];
        try {
            [$gmailGebruikersnaam, $gmailAppWachtwoord] = leesAanmeldMailInstellingen();
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
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($gmailGebruikersnaam, 'Mozart op Zaterdag');
            $mailer->addReplyTo($gmailGebruikersnaam, 'Mozart op Zaterdag');
            // Alleen de aanmelder ontvangt de mail; Dirkjan ontvangt uitsluitend een kopie.
            $mailer->addAddress($email, trim($voornaam . ' ' . $achternaam));
            if (strcasecmp($email, 'dirkjan@pellegrina.net') !== 0) {
                $mailer->addCC('dirkjan@pellegrina.net', 'Dirkjan Horringa');
            }
            $mailer->isHTML(true);
            $mailer->Subject = str_replace(["\r", "\n"], '', html_entity_decode(strip_tags(vulAanmeldbevestigingTemplate($aanmeldbevestigingMail['onderwerp'], $templateWaarden)), ENT_QUOTES, 'UTF-8'));
            $mailer->Body = vulAanmeldbevestigingTemplate($aanmeldbevestigingMail['tekst'], $templateWaarden);
            $mailer->AltBody = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $mailer->Body)), ENT_QUOTES, 'UTF-8'));
            $mailer->send();
            $melding = 'Gegevens succesvol opgeslagen. Er is een bevestiging per e-mail verstuurd.';
        } catch (Throwable $e) {
            error_log('Aanmelding: bevestigingsmail niet verstuurd: ' . $e->getMessage());
            $melding = 'Gegevens succesvol opgeslagen, maar de bevestigingsmail kon niet worden verstuurd.';
        }

    } catch (Exception $e) {
        $foutmelding = 'Fout: ' . $e->getMessage();
    }
}

// Haal bestaande gegevens op als deelnemer via email wordt herkend
$gegevens = [];
$geselecteerde_instrumenten = [];
$activiteit_statussen = [];

if (!empty($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    $stmt = $pdo->prepare('SELECT id, voornaam, achternaam, email, telefoon, postcode, plaats, muzikale_ervaring, op_de_hoogte_houden FROM deelnemers WHERE email = ?');
    $stmt->execute([$_GET['email']]);
    $gegevens = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($gegevens) {
        $deelnemerId = (int) $gegevens['id'];

        // Haal instrumenten op
        $stmt = $pdo->prepare('SELECT instrument_id FROM deelnemer_instrumenten WHERE deelnemer_id = ?');
        $stmt->execute([$deelnemerId]);
        $geselecteerde_instrumenten = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'instrument_id'));

        // Haal activiteit statussen op
        $stmt = $pdo->prepare('SELECT activiteit_id, status FROM activiteit_deelnemers WHERE deelnemer_id = ?');
        $stmt->execute([$deelnemerId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $activiteit_statussen[(int) $row['activiteit_id']] = $row['status'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aanmelden Mozart op Zaterdag</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <link href="/css/w3.css" rel="stylesheet" type="text/css">
    <style>
        .form-group { margin-bottom: 1.5em; }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1em; }
        .form-group label { display: block; margin-bottom: 0.5em; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group select,
        .form-group textarea { width: 100%; padding: 0.6em; border: 1px solid #ccc; border-radius: 2px; box-sizing: border-box; font-size: 1em; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .checkboxes { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1em; }
        .checkbox-item { display: flex; align-items: center; }
        .checkbox-item input[type="checkbox"] { margin-right: 0.5em; }
        .radio-group { display: flex; flex-direction: column; gap: 0.5em; }
        .radio-item { display: flex; align-items: center; }
        .radio-item input[type="radio"] { margin-right: 0.5em; }
        .button-group { display: flex; gap: 1em; margin-top: 2em; }
        .w3-button { padding: 0.6em 1.2em; cursor: pointer; border: none; border-radius: 2px; font-size: 1em; }
        .melding { padding: 1em; border-radius: 2px; margin-bottom: 1em; }
        .succes { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .fout { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .form-section { margin-top: 2em; padding-top: 1.5em; border-top: 2px solid #ddd; }
        .form-section h3 { margin-top: 0; color: #333; }
        .pagina-kop { display: flex; align-items: center; gap: 1.5em; margin-bottom: 1em; }
        .pagina-kop { margin-top: 1em; }
        .pagina-kop img { display: block; width: 100%; max-width: 250px; height: auto; }
        .pagina-kop h2 { margin: 0; }
        .instrument-separator { grid-column: 1 / -1; border-top: 1px solid #bbb; margin-top: 0.25em; padding-top: 0.75em; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; gap: 0; } }
        @media (max-width: 600px) { .pagina-kop { align-items: flex-start; gap: 1em; } .pagina-kop img { max-width: 160px; } }
    </style>
</head>
<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:800px">
        <div class="pagina-kop">
            <img src="/images/Mozart.jpg" alt="Mozart op Zaterdag">
            <h2>Aanmelden Mozart op Zaterdag</h2>
        </div>

        <?php if ($melding): ?>
            <div class="melding succes"><?= htmlspecialchars($melding) ?></div>
        <?php endif; ?>

        <?php if ($foutmelding): ?>
            <div class="melding fout"><?= htmlspecialchars($foutmelding) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Persoonlijke gegevens -->
            <div class="form-section">
                <h3>Persoonlijke gegevens</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="voornaam">Voornaam *</label>
                        <input type="text" id="voornaam" name="voornaam" value="<?= htmlspecialchars($gegevens['voornaam'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="achternaam">Achternaam *</label>
                        <input type="text" id="achternaam" name="achternaam" value="<?= htmlspecialchars($gegevens['achternaam'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postcode">Postcode</label>
                        <input type="text" id="postcode" name="postcode" value="<?= htmlspecialchars($gegevens['postcode'] ?? '') ?>" placeholder="bijv. 3511 AB">
                    </div>

                    <div class="form-group">
                        <label for="plaats">Plaats</label>
                        <input type="text" id="plaats" name="plaats" value="<?= htmlspecialchars($gegevens['plaats'] ?? '') ?>" placeholder="bijv. Utrecht">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">E-mailadres *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($gegevens['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="telefoon">Telefoonnummer</label>
                    <input type="tel" id="telefoon" name="telefoon" value="<?= htmlspecialchars($gegevens['telefoon'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Ik wil graag op de hoogte gehouden worden van de activiteiten van Mozart op Zaterdag</label>
                    <div class="radio-group">
                        <div class="radio-item">
                            <input type="radio" id="op_de_hoogte_ja" name="op_de_hoogte_houden" value="1" <?= isset($gegevens['op_de_hoogte_houden']) && (int) $gegevens['op_de_hoogte_houden'] === 1 ? 'checked' : '' ?> required>
                            <label for="op_de_hoogte_ja" style="margin:0;font-weight:normal">Ja</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" id="op_de_hoogte_nee" name="op_de_hoogte_houden" value="0" <?= isset($gegevens['op_de_hoogte_houden']) && (int) $gegevens['op_de_hoogte_houden'] === 0 ? 'checked' : '' ?> required>
                            <label for="op_de_hoogte_nee" style="margin:0;font-weight:normal">Nee</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instrumenten -->
            <div class="form-section">
                <h3>Instrumenten</h3>
                <p>Welke instrumenten speel je? (meerdere selecties mogelijk)</p>
                <div class="checkboxes">
                    <?php foreach ($instrumenten as $instrument): ?>
                        <?php if (strtolower(trim($instrument['naam'])) === 'viool'): ?><div class="instrument-separator"></div><?php endif; ?>
                        <div class="checkbox-item<?= strtolower(trim($instrument['naam'])) === 'viool' ? ' instrument-na-separator' : '' ?>">
                            <input type="checkbox" id="instr_<?= (int) $instrument['id'] ?>" name="instrumenten[]" value="<?= (int) $instrument['id'] ?>" <?= in_array((int) $instrument['id'], $geselecteerde_instrumenten) ? 'checked' : '' ?>>
                            <label for="instr_<?= (int) $instrument['id'] ?>" style="margin: 0; font-weight: normal;"><?= htmlspecialchars($instrument['naam']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-group" style="margin-top:1.5em">
                    <label for="muzikale_ervaring">Muzikale ervaring</label>
                    <p style="margin:0 0 0.5em 0; font-size:0.9em; color:#666">Omschrijf je niveau: in welk orkest speel je, heb je les, met welke stukken ben je bezig? <em>*NB. alleen nodig voor mensen die niet eerder met La Pellegrina cursussen of orkestprojecten van Dirkjan Horringa hebben meegedaan</em></p>
                    <textarea id="muzikale_ervaring" name="muzikale_ervaring" maxlength="500" rows="5"><?= htmlspecialchars($gegevens['muzikale_ervaring'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Beschikbaarheid -->
            <div class="form-section">
                <h3>Beschikbaarheid voor toekomstige activiteiten</h3>
                <p>Geef aan of je beschikbaar bent voor de volgende activiteiten:</p>

                <?php foreach ($activiteiten as $activiteit): ?>
                    <div class="form-group">
                        <label style="font-weight: bold;"><?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?></label>
                        <p style="margin: 0 0 0.5em 0; font-size: 0.9em; color: #666;"><?= htmlspecialchars($activiteit['omschrijving'] ?? '') ?></p>
                        <div class="radio-group">
                            <?php $status_geselecteerd = $activiteit_statussen[(int) $activiteit['id']] ?? 'nee'; ?>
                            <div class="radio-item">
                                <input type="radio" id="status_ja_<?= (int) $activiteit['id'] ?>" name="status_<?= (int) $activiteit['id'] ?>" value="ja" <?= $status_geselecteerd === 'ja' ? 'checked' : '' ?>>
                                <label for="status_ja_<?= (int) $activiteit['id'] ?>" style="margin: 0; font-weight: normal;">Ja, ik ben beschikbaar</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="status_misschien_<?= (int) $activiteit['id'] ?>" name="status_<?= (int) $activiteit['id'] ?>" value="misschien" <?= $status_geselecteerd === 'misschien' ? 'checked' : '' ?>>
                                <label for="status_misschien_<?= (int) $activiteit['id'] ?>" style="margin: 0; font-weight: normal;">Misschien, nog onzeker</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="status_nee_<?= (int) $activiteit['id'] ?>" name="status_<?= (int) $activiteit['id'] ?>" value="nee" <?= $status_geselecteerd === 'nee' ? 'checked' : '' ?>>
                                <label for="status_nee_<?= (int) $activiteit['id'] ?>" style="margin: 0; font-weight: normal;">Nee, niet beschikbaar</label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($activiteiten)): ?>
                    <p style="color: #666;">Er zijn geen toekomstige activiteiten gepland.</p>
                <?php endif; ?>
            </div>

            <!-- Buttons -->
            <div class="button-group">
                <button type="submit" class="w3-button w3-green">Opslaan</button>
                <a href="/" class="w3-button w3-light-grey" style="text-decoration: none;">Annuleren</a>
            </div>

            <p style="margin-top: 2em; font-size: 0.9em; color: #666;">
                * verplichte velden
            </p>
        </form>
    </div>
</body>
</html>
