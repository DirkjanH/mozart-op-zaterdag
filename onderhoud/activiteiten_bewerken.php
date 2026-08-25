<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

$melding = '';

// Toevoegen of bewerken
if (isset($_POST['actie']) && $_POST['actie'] === 'opslaan') {
    $datum = trim($_POST['datum'] ?? '');
    $plaats = trim($_POST['plaats'] ?? '');
    $omschrijving = trim($_POST['omschrijving'] ?? '');
    $omschrijving = $omschrijving === '' ? null : $omschrijving;
    $id = $_POST['id'] ?? '';

    if ($datum === '' || $plaats === '') {
        $melding = 'Datum en plaats zijn verplicht.';
    } elseif ($id !== '') {
        $stmt = $pdo->prepare('UPDATE activiteiten SET datum = ?, plaats = ?, omschrijving = ? WHERE id = ?');
        $stmt->execute([$datum, $plaats, $omschrijving, $id]);
        $melding = 'Activiteit bijgewerkt.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO activiteiten (datum, plaats, omschrijving) VALUES (?, ?, ?)');
        $stmt->execute([$datum, $plaats, $omschrijving]);
        $melding = 'Activiteit toegevoegd.';
    }
}

$activiteiten = $pdo->query('SELECT * FROM activiteiten ORDER BY datum')->fetchAll(PDO::FETCH_ASSOC);

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
                    <th>Omschrijving</th>
                    <th></th>
                </tr>
                <?php foreach ($activiteiten as $activiteit): ?>
                    <form method="post">
                        <input type="hidden" name="actie" value="opslaan">
                        <input type="hidden" name="id" value="<?= (int) $activiteit['id'] ?>">
                        <tr>
                            <td><input class="w3-input" type="date" name="datum" value="<?= htmlspecialchars($activiteit['datum']) ?>" required></td>
                            <td><input class="w3-input" type="text" name="plaats" value="<?= htmlspecialchars($activiteit['plaats']) ?>" style="width:12em;" required></td>
                            <td><input class="w3-input" type="text" name="omschrijving" value="<?= htmlspecialchars($activiteit['omschrijving'] ?? '') ?>" style="min-width:24em;"></td>
                            <td><button class="w3-button w3-blue w3-small" type="submit">Opslaan</button></td>
                        </tr>
                    </form>
                <?php endforeach; ?>

                <form method="post">
                    <input type="hidden" name="actie" value="opslaan">
                    <tr>
                        <td><input class="w3-input" type="date" name="datum" value="<?= htmlspecialchars($voorgesteldeDatum) ?>" required></td>
                        <td><input class="w3-input" type="text" name="plaats" value="Marnixzaal" style="width:12em;" required></td>
                        <td><input class="w3-input" type="text" name="omschrijving" placeholder="Nieuwe activiteit" style="min-width:24em;"></td>
                        <td><button class="w3-button w3-green w3-small" type="submit">Toevoegen</button></td>
                    </tr>
                </form>
            </table>
        </div>
    </div>
</body>

</html>
