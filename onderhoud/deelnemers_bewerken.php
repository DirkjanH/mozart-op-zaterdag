<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

$statussen = ['' => '(onbekend)', 'ja' => 'ja', 'nee' => 'nee', 'misschien' => 'misschien'];
$melding = '';

$activiteiten = $pdo->query(
    'SELECT id, datum, plaats FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum'
)->fetchAll(PDO::FETCH_ASSOC);

$instrumenten = $pdo->query('SELECT id, naam FROM instrumenten ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

// Toevoegen of bewerken van een deelnemer, met instrumenten en beschikbaarheid in dezelfde submit.
if (isset($_POST['actie']) && $_POST['actie'] === 'opslaan') {
    $voornaam = trim($_POST['voornaam'] ?? '');
    $achternaam = trim($_POST['achternaam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefoon = trim($_POST['telefoon'] ?? '');
    $telefoon = $telefoon === '' ? null : $telefoon;
    $plaats = trim($_POST['plaats'] ?? '');
    $plaats = $plaats === '' ? null : $plaats;
    $instrument_ids = array_map('intval', $_POST['instrumenten'] ?? []);
    $voorkeuren = trim($_POST['voorkeuren'] ?? '');
    $voorkeuren = $voorkeuren === '' ? null : $voorkeuren;
    $id = $_POST['id'] ?? '';

    if ($voornaam === '' || $achternaam === '' || $email === '') {
        $melding = 'Voornaam, achternaam en e-mail zijn verplicht.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare(
                'UPDATE deelnemers SET voornaam = ?, achternaam = ?, email = ?, telefoon = ?, plaats = ? WHERE id = ?'
            );
            $stmt->execute([$voornaam, $achternaam, $email, $telefoon, $plaats, $id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO deelnemers (voornaam, achternaam, email, telefoon, plaats) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$voornaam, $achternaam, $email, $telefoon, $plaats]);
            $id = $pdo->lastInsertId();
        }

        // Instrumenten: bestaande koppelingen vervangen door de nu aangevinkte selectie.
        // Dezelfde voorkeurstekst geldt voor alle aangevinkte instrumenten van deze deelnemer.
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

$deelnemers = $pdo->query('SELECT * FROM deelnemers ORDER BY achternaam, voornaam')->fetchAll(PDO::FETCH_ASSOC);

// Per deelnemer de gekoppelde instrumenten, voorkeurstekst en beschikbaarheid alvast opzoeken.
$instrumentenPerDeelnemer = [];
$voorkeurenPerDeelnemer = [];
foreach ($pdo->query('SELECT deelnemer_id, instrument_id, voorkeuren FROM deelnemer_instrumenten') as $rij) {
    $instrumentenPerDeelnemer[$rij['deelnemer_id']][] = (int) $rij['instrument_id'];
    // Eén voorkeursveld per deelnemer in de UI; pak de eerste niet-lege waarde als die er is.
    if (!isset($voorkeurenPerDeelnemer[$rij['deelnemer_id']]) && $rij['voorkeuren'] !== null) {
        $voorkeurenPerDeelnemer[$rij['deelnemer_id']] = $rij['voorkeuren'];
    }
}
$statusPerDeelnemer = [];
foreach ($pdo->query('SELECT activiteit_id, deelnemer_id, status FROM activiteit_deelnemers') as $rij) {
    $statusPerDeelnemer[$rij['deelnemer_id']][$rij['activiteit_id']] = $rij['status'];
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Deelnemers bewerken</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <style>
        .tabel-scroll {
            max-height: 75vh;
            overflow: auto;
        }

        .tabel-scroll th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: white;
        }

        .tabel-scroll td:first-child,
        .tabel-scroll th:first-child {
            position: sticky;
            left: 0;
            z-index: 1;
            background: white;
        }

        .tabel-scroll th:first-child {
            z-index: 3;
        }

        .naam-velden {
            display: flex;
            gap: 0.25em;
        }

        .kolom-details[hidden] {
            display: none !important;
        }
    </style>
    <script>
        function toggleDetails() {
            var details = document.querySelectorAll('.kolom-details');
            var tonen = details.length > 0 && details[0].hidden;
            details.forEach(function (kolom) {
                kolom.hidden = !tonen;
            });
            document.querySelector('#details-knop').textContent = tonen
                ? 'E-mail / telefoon / plaats / voorkeur verbergen'
                : 'E-mail / telefoon / plaats / voorkeur tonen';
        }
    </script>
</head>

<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1400px;">
        <h3>Deelnemers bewerken</h3>
        <?php if ($melding !== ''): ?>
            <p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p>
        <?php endif; ?>

        <button id="details-knop" type="button" class="w3-button w3-blue w3-margin-bottom" onclick="toggleDetails()">E-mail / telefoon / plaats / voorkeur tonen</button>

        <div class="tabel-scroll">
            <table class="w3-table w3-bordered w3-striped w3-small">
                <tr>
                    <th>Naam</th>
                    <th class="kolom-details" hidden>E-mail</th>
                    <th class="kolom-details" hidden>Telefoon</th>
                    <th class="kolom-details" hidden>Plaats</th>
                    <th>Instrumenten</th>
                    <th class="kolom-details" hidden>Voorkeur</th>
                    <?php foreach ($activiteiten as $activiteit): ?>
                        <th><?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum']))) ?></th>
                    <?php endforeach; ?>
                    <th></th>
                </tr>
                <?php foreach ($deelnemers as $deelnemer): ?>
                    <?php $gekozenInstrumenten = $instrumentenPerDeelnemer[$deelnemer['id']] ?? []; ?>
                    <form method="post">
                        <input type="hidden" name="actie" value="opslaan">
                        <input type="hidden" name="id" value="<?= (int) $deelnemer['id'] ?>">
                        <tr>
                            <td style="min-width:20em;">
                                <div class="naam-velden">
                                    <input class="w3-input" type="text" name="voornaam" value="<?= htmlspecialchars($deelnemer['voornaam']) ?>" placeholder="Voornaam" required>
                                    <input class="w3-input" type="text" name="achternaam" value="<?= htmlspecialchars($deelnemer['achternaam']) ?>" placeholder="Achternaam" required>
                                </div>
                            </td>
                            <td class="kolom-details" hidden><input class="w3-input" type="email" name="email" value="<?= htmlspecialchars($deelnemer['email']) ?>" style="min-width:14em;" required></td>
                            <td class="kolom-details" hidden><input class="w3-input" type="text" name="telefoon" value="<?= htmlspecialchars($deelnemer['telefoon'] ?? '') ?>" style="width:9em;"></td>
                            <td class="kolom-details" hidden><input class="w3-input" type="text" name="plaats" value="<?= htmlspecialchars($deelnemer['plaats'] ?? '') ?>" style="width:10em;"></td>
                            <td>
                                <select class="w3-select" name="instrumenten[]" multiple size="1" style="min-width:12em;">
                                    <?php foreach ($instrumenten as $instrument): ?>
                                        <option value="<?= (int) $instrument['id'] ?>" <?= in_array((int) $instrument['id'], $gekozenInstrumenten, true) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($instrument['naam']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="kolom-details" hidden><input class="w3-input" type="text" name="voorkeuren" value="<?= htmlspecialchars($voorkeurenPerDeelnemer[$deelnemer['id']] ?? '') ?>" style="min-width:12em;"></td>
                            <?php foreach ($activiteiten as $activiteit): ?>
                                <td>
                                    <select class="w3-select" name="status_<?= (int) $activiteit['id'] ?>">
                                        <?php foreach ($statussen as $waarde => $label): ?>
                                            <option value="<?= $waarde ?>" <?= ($statusPerDeelnemer[$deelnemer['id']][$activiteit['id']] ?? '') === $waarde ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            <?php endforeach; ?>
                            <td><button class="w3-button w3-blue w3-small" type="submit">Opslaan</button></td>
                        </tr>
                    </form>
                <?php endforeach; ?>

                <form method="post">
                    <input type="hidden" name="actie" value="opslaan">
                    <tr>
                        <td style="min-width:20em;">
                            <div class="naam-velden">
                                <input class="w3-input" type="text" name="voornaam" placeholder="Voornaam" required>
                                <input class="w3-input" type="text" name="achternaam" placeholder="Achternaam" required>
                            </div>
                        </td>
                        <td class="kolom-details" hidden><input class="w3-input" type="email" name="email" style="min-width:14em;" required></td>
                        <td class="kolom-details" hidden><input class="w3-input" type="text" name="telefoon" style="width:9em;"></td>
                        <td class="kolom-details" hidden><input class="w3-input" type="text" name="plaats" style="width:10em;"></td>
                        <td>
                            <select class="w3-select" name="instrumenten[]" multiple size="1" style="min-width:12em;">
                                <?php foreach ($instrumenten as $instrument): ?>
                                    <option value="<?= (int) $instrument['id'] ?>"><?= htmlspecialchars($instrument['naam']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="kolom-details" hidden><input class="w3-input" type="text" name="voorkeuren" style="min-width:12em;"></td>
                        <?php foreach ($activiteiten as $activiteit): ?>
                            <td>
                                <select class="w3-select" name="status_<?= (int) $activiteit['id'] ?>">
                                    <?php foreach ($statussen as $waarde => $label): ?>
                                        <option value="<?= $waarde ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        <?php endforeach; ?>
                        <td><button class="w3-button w3-green w3-small" type="submit">Toevoegen</button></td>
                    </tr>
                </form>
            </table>
        </div>
    </div>
</body>

</html>
