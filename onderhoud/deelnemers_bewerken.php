<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

$pdo->exec('CREATE TABLE IF NOT EXISTS deelnemer_wijzigingen (deelnemer_id INT NOT NULL PRIMARY KEY, gemarkeerd_op DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$statussen = ['' => '(onbekend)', 'ja' => 'ja', 'nee' => 'nee', 'misschien' => 'misschien'];
$melding = '';

$activiteiten = $pdo->query(
    'SELECT id, datum, plaats FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum'
)->fetchAll(PDO::FETCH_ASSOC);

$instrumenten = $pdo->query("SELECT id, naam FROM instrumenten ORDER BY CASE WHEN LOWER(TRIM(naam)) = 'pauken' THEN COALESCE((SELECT MIN(i2.id) FROM instrumenten i2 WHERE LOWER(TRIM(i2.naam)) LIKE 'trompet%'), id) ELSE id END, CASE WHEN LOWER(TRIM(naam)) = 'pauken' THEN 1 ELSE 0 END, id")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['actie']) && $_POST['actie'] === 'verwijderen') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM mail_tracking WHERE deelnemer_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM activiteit_deelnemers WHERE deelnemer_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM deelnemer_instrumenten WHERE deelnemer_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM deelnemer_wijzigingen WHERE deelnemer_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM deelnemers WHERE id = ?')->execute([$id]);
            $pdo->commit();
            $melding = 'Deelnemer volledig verwijderd.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

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
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT 1 FROM deelnemers WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $id]);
    $emailInGebruik = (bool) $stmt->fetchColumn();

    if ($voornaam === '' || $achternaam === '' || $email === '') {
        $melding = 'Voornaam, achternaam en e-mail zijn verplicht.';
    } elseif ($emailInGebruik) {
        $melding = 'Dit e-mailadres is al in gebruik bij een andere deelnemer.';
    } else {
        if ($id > 0) {
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
        $pdo->prepare('DELETE FROM deelnemer_wijzigingen WHERE deelnemer_id = ?')->execute([$id]);

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
$gemarkeerdeDeelnemerIds = array_map('intval', $pdo->query('SELECT deelnemer_id FROM deelnemer_wijzigingen')->fetchAll(PDO::FETCH_COLUMN));

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
$instrumentNaamPerId = array_column($instrumenten, 'naam', 'id');
$instrumentFamilies = [
    'strijkers' => ['viool', 'altviool', 'cello', 'contrabas'],
    'houtblazers' => ['dwarsfluit', 'piccolo', 'hobo', 'engelse hoorn', 'klarinet', 'basklarinet', 'fagot', 'contrafagot'],
    'koperblazers' => ['trompet', 'hoorn', 'trombone', 'tuba'],
];
$deelnemerTellingen = ['strijkers' => 0, 'houtblazers' => 0, 'koperblazers' => 0, 'overig' => 0];
foreach ($deelnemers as $deelnemer) {
    $familiesVanDeelnemer = [];
    foreach ($instrumentenPerDeelnemer[$deelnemer['id']] ?? [] as $instrumentId) {
        $instrumentNaam = strtolower(trim($instrumentNaamPerId[$instrumentId] ?? ''));
        foreach ($instrumentFamilies as $familie => $instrumentNamen) {
            if (in_array($instrumentNaam, $instrumentNamen, true)) {
                $familiesVanDeelnemer[$familie] = true;
            }
        }
    }
    if ($familiesVanDeelnemer === []) {
        $deelnemerTellingen['overig']++;
    } else {
        foreach (array_keys($familiesVanDeelnemer) as $familie) {
            $deelnemerTellingen[$familie]++;
        }
    }
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

            .actie-knop {
                border-radius: 50%;
                width: 2.2em;
                height: 2.2em;
                padding: 0;
                margin: 0.1em;
                font-size: 1.1em;
                line-height: 2.2em;
                text-align: center;
            }

            .actie-kolom {
                width: 5.2em;
                white-space: nowrap;
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

        function bevestigVerwijderen(event) {
            if (event.submitter && event.submitter.value === 'verwijderen') {
                return confirm('Weet je zeker dat je deze deelnemer helemaal wilt verwijderen?');
            }

            return true;
        }

        // Onthoud de scrollpositie zodat je na opslaan/verwijderen terugkomt waar je bezig was.
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
</head>

<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1400px;">
        <h3>Deelnemers bewerken</h3>
        <?php if ($melding !== ''): ?>
            <p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p>
        <?php endif; ?>

        <p><strong><?= count($deelnemers) ?> deelnemers</strong> | Strijkers: <?= $deelnemerTellingen['strijkers'] ?> | Houtblazers: <?= $deelnemerTellingen['houtblazers'] ?> | Koperblazers: <?= $deelnemerTellingen['koperblazers'] ?> | Overig: <?= $deelnemerTellingen['overig'] ?></p>

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
                        <th class="actie-kolom"></th>
                </tr>
                <?php foreach ($deelnemers as $deelnemer): ?>
                    <?php $gekozenInstrumenten = $instrumentenPerDeelnemer[$deelnemer['id']] ?? []; ?>
                    <form method="post" onsubmit="return bevestigVerwijderen(event);">
                        <input type="hidden" name="actie" value="opslaan">
                        <input type="hidden" name="id" value="<?= (int) $deelnemer['id'] ?>">
                        <tr>
                            <td style="min-width:20em;">
                                <div class="naam-velden">
                                    <input class="w3-input" type="text" name="voornaam" value="<?= htmlspecialchars($deelnemer['voornaam']) ?>" placeholder="Voornaam" required>
                                    <input class="w3-input" type="text" name="achternaam" value="<?= htmlspecialchars($deelnemer['achternaam']) ?>" placeholder="Achternaam" required>
                                    <?php if (in_array((int) $deelnemer['id'], $gemarkeerdeDeelnemerIds, true)): ?><span class="nieuwe-deelnemer-markering" title="Nieuwe of gewijzigde aanmelding; verdwijnt na opslaan" aria-label="Nieuwe of gewijzigde aanmelding" style="color:#198754;font-size:1.3em;line-height:1">&#9752;</span><?php endif; ?>
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
                                <td class="actie-kolom">
                                    <button class="w3-button w3-blue actie-knop" type="submit" title="Deelnemer opslaan" aria-label="Deelnemer opslaan">&#10003;</button>
                                    <button class="w3-button w3-red actie-knop" type="submit" name="actie" value="verwijderen" formnovalidate title="Deelnemer verwijderen" aria-label="Deelnemer verwijderen">&#10005;</button>
                                </td>
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
                            <td class="actie-kolom"><button class="w3-button w3-blue actie-knop" type="submit" title="Deelnemer toevoegen" aria-label="Deelnemer toevoegen">&#10003;</button></td>
                    </tr>
                </form>
            </table>
        </div>
    </div>
</body>

</html>
