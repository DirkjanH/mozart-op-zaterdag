<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

function zorgVoorUitvoeringskolommen(PDO $pdo): void
{
    if ($pdo->query("SHOW COLUMNS FROM werken LIKE 'uitgevoerd_op'")->fetch() === false) {
        $pdo->exec('ALTER TABLE werken ADD COLUMN uitgevoerd_op DATE NULL');
    }
    $metSolistKolom = $pdo->query("SHOW COLUMNS FROM werken LIKE 'met_solist'")->fetch(PDO::FETCH_ASSOC);
    if ($metSolistKolom === false) {
        $pdo->exec('ALTER TABLE werken ADD COLUMN met_solist VARCHAR(100) NULL');
    } elseif (preg_match('/^varchar\((\d+)\)$/i', $metSolistKolom['Type'], $delen) && (int) $delen[1] < 100) {
        $pdo->exec('ALTER TABLE werken MODIFY COLUMN met_solist VARCHAR(100) NULL');
    }
}

zorgVoorUitvoeringskolommen($pdo);

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
    $uitgevoerd_op = trim($_POST['uitgevoerd_op'] ?? '');
    $uitgevoerd_op = $uitgevoerd_op === '' ? null : $uitgevoerd_op;
    $met_solist = trim($_POST['met_solist'] ?? '');
    $met_solist = $met_solist === '' ? null : $met_solist;
    $id = $_POST['id'] ?? '';

    if ($titel === '' || $kv_nummer === '' || $jaar === '') {
        $melding = 'Titel, KV-nummer en jaar zijn verplicht.';
    } elseif ($met_solist !== null && mb_strlen($met_solist) > 100) {
        $melding = 'Met solist mag maximaal 100 tekens bevatten.';
    } elseif ($id !== '') {
        $stmt = $pdo->prepare(
            'UPDATE werken SET titel = ?, kv_nummer = ?, kv_toevoeging = ?, jaar = ?, soort = ?, bezetting = ?, solo = ?, uitgevoerd_op = ?, met_solist = ? WHERE id = ?'
        );
        $stmt->execute([$titel, $kv_nummer, $kv_toevoeging, $jaar, $soort, $bezetting, $solo, $uitgevoerd_op, $met_solist, $id]);
        $melding = 'Werk bijgewerkt.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO werken (titel, kv_nummer, kv_toevoeging, jaar, soort, bezetting, solo, uitgevoerd_op, met_solist) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$titel, $kv_nummer, $kv_toevoeging, $jaar, $soort, $bezetting, $solo, $uitgevoerd_op, $met_solist]);
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
        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
        }

        .pagina {
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100vh;
            margin: 0 !important;
            padding: 8px 16px !important;
        }

        .pagina-kop {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1em;
            flex-wrap: wrap;
        }

        .pagina-kop h3 {
            margin: 8px 0;
        }

        .filterknoppen {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .tabel-scroll {
            flex: 1;
            min-height: 0;
            width: 100%;
            overflow: auto;
        }

        .tabel-scroll table {
            width: 100%;
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
            width: 2.2em;
            padding: 0.2em !important;
            white-space: nowrap;
        }

        .veld-kv {
            width: 4.5em;
        }

        .veld-jaar {
            width: 5em;
        }

        .veld-soort {
            min-width: 8em;
        }

        .veld-solo {
            min-width: 12em;
        }

        .uitgevoerd-titel {
            color: #c00;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="pagina w3-mobile w3-white w3-panel">
        <div class="pagina-kop">
            <h3>Werken bewerken</h3>
            <div class="filterknoppen" role="group" aria-label="Werken filteren">
                <button class="w3-button w3-blue filterknop" type="button" data-filter="alle" aria-pressed="true">Alle werken</button>
                <button class="w3-button w3-light-grey filterknop" type="button" data-filter="symfonie" aria-pressed="false">Symfonieën</button>
                <button class="w3-button w3-light-grey filterknop" type="button" data-filter="concert" aria-pressed="false">Concerten</button>
                <button class="w3-button w3-light-grey filterknop" type="button" data-filter="ander" aria-pressed="false">Overig</button>
            </div>
        </div>
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
                <th>Uitgevoerd op</th>
                <th>Met solist</th>
                <th class="actie-kolom"></th>
            </tr>
            <?php foreach ($werken as $werk): ?>
                <form method="post">
                    <input type="hidden" name="actie" value="opslaan">
                    <input type="hidden" name="id" value="<?= (int) $werk['id'] ?>">
                    <tr class="werk-rij" data-soort="<?= htmlspecialchars($werk['soort'], ENT_QUOTES, 'UTF-8') ?>">
                        <td><input class="w3-input<?= !empty($werk['uitgevoerd_op']) ? ' uitgevoerd-titel' : '' ?>" type="text" name="titel" value="<?= htmlspecialchars($werk['titel']) ?>" style="min-width:20em;" required></td>
                        <td><input class="w3-input veld-kv" type="number" name="kv_nummer" value="<?= htmlspecialchars($werk['kv_nummer']) ?>" required></td>
                        <td><input class="w3-input" type="text" name="kv_toevoeging" value="<?= htmlspecialchars($werk['kv_toevoeging'] ?? '') ?>" style="width:4em;"></td>
                        <td><input class="w3-input veld-jaar" type="number" name="jaar" value="<?= htmlspecialchars($werk['jaar']) ?>" required></td>
                        <td>
                            <select class="w3-select veld-soort" name="soort">
                                <?php foreach ($soorten as $soort): ?>
                                    <option value="<?= $soort ?>" <?= $werk['soort'] === $soort ? 'selected' : '' ?>><?= $soort ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="w3-input" type="text" name="bezetting" value="<?= htmlspecialchars($werk['bezetting']) ?>"></td>
                        <td><input class="w3-input veld-solo" type="text" name="solo" value="<?= htmlspecialchars($werk['solo'] ?? '') ?>"></td>
                        <td><input class="w3-input" type="date" name="uitgevoerd_op" value="<?= htmlspecialchars($werk['uitgevoerd_op'] ?? '') ?>"></td>
                        <td><input class="w3-input" type="text" name="met_solist" value="<?= htmlspecialchars($werk['met_solist'] ?? '') ?>" maxlength="100" style="width:14em;"></td>
                        <td class="actie-kolom">
                            <button class="w3-button w3-blue actie-knop" type="submit" title="Werk opslaan" aria-label="Werk opslaan">&#10003;</button>
                        </td>
                    </tr>
                </form>
            <?php endforeach; ?>

            <form method="post">
                <input type="hidden" name="actie" value="opslaan">
                <tr>
                    <td><input class="w3-input" type="text" name="titel" placeholder="Nieuw werk" style="min-width:20em;" required></td>
                    <td><input class="w3-input veld-kv" type="number" name="kv_nummer" required></td>
                    <td><input class="w3-input" type="text" name="kv_toevoeging" style="width:4em;"></td>
                    <td><input class="w3-input veld-jaar" type="number" name="jaar" required></td>
                    <td>
                        <select class="w3-select veld-soort" name="soort">
                            <?php foreach ($soorten as $soort): ?>
                                <option value="<?= $soort ?>"><?= $soort ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input class="w3-input" type="text" name="bezetting"></td>
                    <td><input class="w3-input veld-solo" type="text" name="solo"></td>
                    <td><input class="w3-input" type="date" name="uitgevoerd_op"></td>
                    <td><input class="w3-input" type="text" name="met_solist" maxlength="100" style="width:14em;"></td>
                    <td class="actie-kolom">
                        <button class="w3-button w3-green w3-small" type="submit">Toevoegen</button>
                    </td>
                </tr>
            </form>
        </table>
        </div>
    </div>
    <script>
        const filterknoppen = document.querySelectorAll('.filterknop');
        const werkRijen = document.querySelectorAll('.werk-rij');

        filterknoppen.forEach((knop) => {
            knop.addEventListener('click', () => {
                const filter = knop.dataset.filter;

                werkRijen.forEach((rij) => {
                    rij.hidden = filter !== 'alle' && rij.dataset.soort !== filter;
                });

                filterknoppen.forEach((filterknop) => {
                    const isActief = filterknop === knop;
                    filterknop.classList.toggle('w3-blue', isActief);
                    filterknop.classList.toggle('w3-light-grey', !isActief);
                    filterknop.setAttribute('aria-pressed', isActief ? 'true' : 'false');
                });
            });
        });
    </script>
</body>

</html>
