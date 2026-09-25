<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

$pdo->exec('CREATE TABLE IF NOT EXISTS deelnemer_wijzigingen (deelnemer_id INT NOT NULL PRIMARY KEY, gemarkeerd_op DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$statussen = ['' => '(onbekend)', 'ja' => 'ja', 'nee' => 'nee', 'misschien' => 'misschien'];
$melding = '';
$foutmelding = '';

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
            'UPDATE deelnemers SET voornaam = ?, achternaam = ?, email = ?, telefoon = ?, postcode = ?, plaats = ?, muzikale_ervaring = ?, op_de_hoogte_houden = ? WHERE id = ?'
        );
        $stmt->execute([$voornaam, $achternaam, $email, $telefoon, $postcode, $plaats, $muzikaleErvaring, $opDeHoogteHouden, $id]);
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
    <title>Deelnemer bewerken</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <style>
        .form-group { margin-bottom: 1.2em; }
        .form-group label { display: block; margin-bottom: 0.3em; font-weight: bold; }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1em; }
        @media (max-width: 600px) {
            body {
                margin: 0;
            }

            .w3-content.w3-panel {
                width: 100%;
                max-width: none !important;
                margin: 0;
                box-sizing: border-box;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                box-sizing: border-box;
                width: 100%;
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
    </script>
</head>

<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:800px;">
        <p><a href="deelnemers_bewerken.php">&larr; Terug naar overzicht</a></p>
        <h3>Deelnemer bewerken: <?= htmlspecialchars(trim($deelnemer['voornaam'] . ' ' . $deelnemer['achternaam'])) ?></h3>

        <?php if ($melding !== ''): ?>
            <p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p>
        <?php endif; ?>
        <?php if ($foutmelding !== ''): ?>
            <p class="w3-panel w3-pale-red w3-leftbar w3-border-red"><?= htmlspecialchars($foutmelding) ?></p>
        <?php endif; ?>

        <form method="post" onsubmit="return bevestigOpslaan();">
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

            <div class="knoppen">
                <button class="w3-button w3-blue" type="submit">Wijzigingen opslaan</button>
            </div>
        </form>

        <form method="post" onsubmit="return bevestigVerwijderen();" style="margin-top:1em;">
            <input type="hidden" name="actie" value="verwijderen">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button class="w3-button w3-red" type="submit">Deelnemer verwijderen</button>
        </form>
    </div>
</body>

</html>
