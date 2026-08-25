<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

$soorten = ['symfonie', 'concert', 'ander'];
$melding = '';

// Toevoegen of bewerken
if (isset($_POST['actie']) && $_POST['actie'] === 'opslaan') {
    $titel = trim($_POST['titel'] ?? '');
    $kv_nummer = $_POST['kv_nummer'] ?? '';
    $kv_toevoeging = trim($_POST['kv_toevoeging'] ?? '');
    $jaar = $_POST['jaar'] ?? '';
    $soort = in_array($_POST['soort'] ?? '', $soorten, true) ? $_POST['soort'] : 'ander';
    $bezetting = trim($_POST['bezetting'] ?? '');
    $solo = trim($_POST['solo'] ?? '');
    $solo = $solo === '' ? null : $solo;
    $id = $_POST['id'] ?? '';

    if ($titel === '' || $kv_nummer === '' || $jaar === '') {
        $melding = 'Titel, KV-nummer en jaar zijn verplicht.';
    } elseif ($id !== '') {
        $stmt = $pdo->prepare(
            'UPDATE werken SET titel = ?, kv_nummer = ?, kv_toevoeging = ?, jaar = ?, soort = ?, bezetting = ?, solo = ? WHERE id = ?'
        );
        $stmt->execute([$titel, $kv_nummer, $kv_toevoeging, $jaar, $soort, $bezetting, $solo, $id]);
        $melding = 'Werk bijgewerkt.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO werken (titel, kv_nummer, kv_toevoeging, jaar, soort, bezetting, solo) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$titel, $kv_nummer, $kv_toevoeging, $jaar, $soort, $bezetting, $solo]);
        $melding = 'Werk toegevoegd.';
    }
}

$werken = $pdo->query('SELECT * FROM werken ORDER BY kv_nummer, kv_toevoeging')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Werken bewerken</title>
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
    </style>
</head>

<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1100px;">
        <h3>Werken bewerken</h3>
        <?php if ($melding !== ''): ?>
            <p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p>
        <?php endif; ?>

        <div class="tabel-scroll">
        <table class="w3-table w3-bordered w3-striped w3-small">
            <tr>
                <th>Titel</th>
                <th>KV</th>
                <th>Toev.</th>
                <th>Jaar</th>
                <th>Soort</th>
                <th>Bezetting</th>
                <th>Solo</th>
                <th></th>
            </tr>
            <?php foreach ($werken as $werk): ?>
                <form method="post">
                    <input type="hidden" name="actie" value="opslaan">
                    <input type="hidden" name="id" value="<?= (int) $werk['id'] ?>">
                    <tr>
                        <td><input class="w3-input" type="text" name="titel" value="<?= htmlspecialchars($werk['titel']) ?>" style="min-width:20em;" required></td>
                        <td><input class="w3-input" type="number" name="kv_nummer" value="<?= htmlspecialchars($werk['kv_nummer']) ?>" style="width:6em;" required></td>
                        <td><input class="w3-input" type="text" name="kv_toevoeging" value="<?= htmlspecialchars($werk['kv_toevoeging'] ?? '') ?>" style="width:4em;"></td>
                        <td><input class="w3-input" type="number" name="jaar" value="<?= htmlspecialchars($werk['jaar']) ?>" style="width:6em;" required></td>
                        <td>
                            <select class="w3-select" name="soort">
                                <?php foreach ($soorten as $soort): ?>
                                    <option value="<?= $soort ?>" <?= $werk['soort'] === $soort ? 'selected' : '' ?>><?= $soort ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="w3-input" type="text" name="bezetting" value="<?= htmlspecialchars($werk['bezetting']) ?>"></td>
                        <td><input class="w3-input" type="text" name="solo" value="<?= htmlspecialchars($werk['solo'] ?? '') ?>"></td>
                        <td>
                            <button class="w3-button w3-blue actie-knop" type="submit" title="Werk opslaan" aria-label="Werk opslaan">&#10003;</button>
                        </td>
                    </tr>
                </form>
            <?php endforeach; ?>

            <form method="post">
                <input type="hidden" name="actie" value="opslaan">
                <tr>
                    <td><input class="w3-input" type="text" name="titel" placeholder="Nieuw werk" style="min-width:20em;" required></td>
                    <td><input class="w3-input" type="number" name="kv_nummer" style="width:6em;" required></td>
                    <td><input class="w3-input" type="text" name="kv_toevoeging" style="width:4em;"></td>
                    <td><input class="w3-input" type="number" name="jaar" style="width:6em;" required></td>
                    <td>
                        <select class="w3-select" name="soort">
                            <?php foreach ($soorten as $soort): ?>
                                <option value="<?= $soort ?>"><?= $soort ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input class="w3-input" type="text" name="bezetting"></td>
                    <td><input class="w3-input" type="text" name="solo"></td>
                    <td>
                        <button class="w3-button w3-green w3-small" type="submit">Toevoegen</button>
                    </td>
                </tr>
            </form>
        </table>
        </div>
    </div>
</body>

</html>
