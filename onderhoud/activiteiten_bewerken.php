<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

$melding = '';

// Zet de gekozen werken om in een leesbare omschrijving, bijv.
// "Symfonie nr. 25 (KV 183) voor 0201-0200-str & Hoornconcert nr. 2 (KV 417) voor 0201-0200-str, solist: hoorn".
function genereerOmschrijving(array $werken): string
{
    $delen = [];
    foreach ($werken as $werk) {
        $deel = $werk['titel'] . ' (KV ' . $werk['kv_nummer'] . $werk['kv_toevoeging'] . ') voor ' . $werk['bezetting'];
        if (!empty($werk['solo'])) {
            $deel .= ', solist: ' . $werk['solo'];
        }
        $delen[] = $deel;
    }
    return implode(' & ', $delen);
}

if (isset($_POST['actie']) && $_POST['actie'] === 'verwijderen' && isset($_POST['id'])) {
    $stmt = $pdo->prepare('DELETE FROM activiteiten WHERE id = ?');
    $stmt->execute([$_POST['id']]);
    $melding = 'Activiteit verwijderd.';
}

// Toevoegen of bewerken
if (isset($_POST['actie']) && $_POST['actie'] === 'opslaan') {
    $datum = trim($_POST['datum'] ?? '');
    $plaats = trim($_POST['plaats'] ?? '');
    $werk_ids = array_map('intval', $_POST['werken'] ?? []);
    $id = $_POST['id'] ?? '';

    if ($datum === '' || $plaats === '') {
        $melding = 'Datum en plaats zijn verplicht.';
    } else {
        // Omschrijving: gegenereerd uit de gekozen werken, anders het handmatig ingevulde veld.
        if ($werk_ids !== []) {
            $placeholders = implode(',', array_fill(0, count($werk_ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM werken WHERE id IN ($placeholders) ORDER BY kv_nummer, kv_toevoeging");
            $stmt->execute($werk_ids);
            $omschrijving = genereerOmschrijving($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $omschrijving = trim($_POST['omschrijving'] ?? '');
        }
        $omschrijving = $omschrijving === '' ? null : $omschrijving;

        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE activiteiten SET datum = ?, plaats = ?, omschrijving = ? WHERE id = ?');
            $stmt->execute([$datum, $plaats, $omschrijving, $id]);
            $melding = 'Activiteit bijgewerkt.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO activiteiten (datum, plaats, omschrijving) VALUES (?, ?, ?)');
            $stmt->execute([$datum, $plaats, $omschrijving]);
            $id = $pdo->lastInsertId();
            $melding = 'Activiteit toegevoegd.';
        }

        // Gekoppelde werken vervangen door de nu aangevinkte selectie.
        $pdo->prepare('DELETE FROM activiteit_werken WHERE activiteit_id = ?')->execute([$id]);
        if ($werk_ids !== []) {
            $stmt = $pdo->prepare('INSERT INTO activiteit_werken (activiteit_id, werk_id) VALUES (?, ?)');
            foreach ($werk_ids as $werk_id) {
                $stmt->execute([$id, $werk_id]);
            }
        }
    }
}

$activiteiten = $pdo->query('SELECT * FROM activiteiten ORDER BY datum')->fetchAll(PDO::FETCH_ASSOC);
$werken = $pdo->query('SELECT id, titel, kv_nummer, kv_toevoeging FROM werken ORDER BY kv_nummer, kv_toevoeging')->fetchAll(PDO::FETCH_ASSOC);

$werkenPerActiviteit = [];
foreach ($pdo->query('SELECT activiteit_id, werk_id FROM activiteit_werken') as $rij) {
    $werkenPerActiviteit[$rij['activiteit_id']][] = (int) $rij['werk_id'];
}

// Vierde zaterdag van een maand berekenen.
function vierdeZaterdag(int $jaar, int $maand): string
{
    $eersteDagVanMaand = new DateTime(sprintf('%04d-%02d-01', $jaar, $maand));
    $verschilTotZaterdag = (6 - (int) $eersteDagVanMaand->format('N') + 7) % 7;
    $eersteZaterdag = 1 + $verschilTotZaterdag;
    return sprintf('%04d-%02d-%02d', $jaar, $maand, $eersteZaterdag + 21);
}

// Voorgestelde datum voor een nieuwe activiteit: de vierde zaterdag van de maand ná de laatst
// geplande activiteit (of, als er nog geen activiteiten zijn, ná vandaag). Juni, juli, augustus
// en december worden overgeslagen.
$overgeslagenMaanden = [6, 7, 8, 12];
$laatsteDatum = $activiteiten === [] ? date('Y-m-d') : end($activiteiten)['datum'];
$jaar = (int) date('Y', strtotime($laatsteDatum));
$maand = (int) date('n', strtotime($laatsteDatum)) + 1;
if ($maand > 12) {
    $maand = 1;
    $jaar++;
}
while (in_array($maand, $overgeslagenMaanden, true)) {
    $maand++;
    if ($maand > 12) {
        $maand = 1;
        $jaar++;
    }
}
$voorgesteldeDatum = vierdeZaterdag($jaar, $maand);
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Activiteiten bewerken</title>
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
            min-width: 6em;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1000px;">
        <h3>Activiteiten bewerken</h3>
        <?php if ($melding !== ''): ?>
            <p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p>
        <?php endif; ?>

        <div class="tabel-scroll">
            <table class="w3-table w3-bordered w3-striped w3-small">
                <tr>
                    <th>Datum</th>
                    <th>Plaats</th>
                    <th>Werken</th>
                    <th>Omschrijving</th>
                    <th class="actie-kolom"></th>
                </tr>
                <?php foreach ($activiteiten as $activiteit): ?>
                    <?php $gekozenWerken = $werkenPerActiviteit[$activiteit['id']] ?? []; ?>
                    <form method="post">
                        <input type="hidden" name="actie" value="opslaan">
                        <input type="hidden" name="id" value="<?= (int) $activiteit['id'] ?>">
                        <tr>
                            <td><input class="w3-input" type="date" name="datum" value="<?= htmlspecialchars($activiteit['datum']) ?>" required></td>
                            <td><input class="w3-input" type="text" name="plaats" value="<?= htmlspecialchars($activiteit['plaats']) ?>" style="width:12em;" required></td>
                            <td class="actie-kolom">
                                <select class="w3-select" name="werken[]" multiple size="1" style="min-width:16em;">
                                    <?php foreach ($werken as $werk): ?>
                                        <option value="<?= (int) $werk['id'] ?>" <?= in_array((int) $werk['id'], $gekozenWerken, true) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($werk['titel']) ?> (KV <?= htmlspecialchars($werk['kv_nummer'] . $werk['kv_toevoeging']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="w3-input" type="text" name="omschrijving" value="<?= htmlspecialchars($activiteit['omschrijving'] ?? '') ?>" style="min-width:24em;"></td>
                            <td>
                                <button class="w3-button w3-blue actie-knop" type="submit" title="Activiteit opslaan" aria-label="Activiteit opslaan">&#10003;</button>
                                <button class="w3-button w3-red actie-knop" type="submit" name="actie" value="verwijderen" formnovalidate title="Activiteit wissen" aria-label="Activiteit wissen" onclick="return confirm('Deze activiteit echt verwijderen?');">&#10005;</button>
                            </td>
                        </tr>
                    </form>
                <?php endforeach; ?>

                <form method="post">
                    <input type="hidden" name="actie" value="opslaan">
                    <tr>
                        <td><input class="w3-input" type="date" name="datum" value="<?= htmlspecialchars($voorgesteldeDatum) ?>" required></td>
                        <td><input class="w3-input" type="text" name="plaats" value="Marnixzaal" style="width:12em;" required></td>
                        <td>
                            <select class="w3-select" name="werken[]" multiple size="1" style="min-width:16em;">
                                <?php foreach ($werken as $werk): ?>
                                    <option value="<?= (int) $werk['id'] ?>"><?= htmlspecialchars($werk['titel']) ?> (KV <?= htmlspecialchars($werk['kv_nummer'] . $werk['kv_toevoeging']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="w3-input" type="text" name="omschrijving" placeholder="Nieuwe activiteit, of kies werken hiernaast" style="min-width:24em;"></td>
                        <td><button class="w3-button w3-blue actie-knop" type="submit" title="Activiteit toevoegen" aria-label="Activiteit toevoegen">&#10003;</button></td>
                    </tr>
                </form>
            </table>
        </div>
    </div>
</body>

</html>
