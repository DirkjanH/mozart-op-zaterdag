<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

// Beheerpagina; niet cachen of insluiten en geen technische foutdetails tonen aan de browser.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// Accepteer wijzigingen alleen vanuit een formulier uit deze beheersessie.
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $ontvangenCsrfToken = $_POST['csrf_token'] ?? null;
    if (!is_string($ontvangenCsrfToken) || !hash_equals($csrfToken, $ontvangenCsrfToken)) {
        http_response_code(403);
        exit('Ongeldige of verlopen formulieraanvraag. Vernieuw de pagina en probeer opnieuw.');
    }
    if (($_POST['actie'] ?? '') !== 'opslaan') {
        http_response_code(400);
        exit('Onbekende actie.');
    }
}

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
    if ($pdo->query("SHOW COLUMNS FROM werken LIKE 'duur_minuten'")->fetch() === false) {
        $pdo->exec('ALTER TABLE werken ADD COLUMN duur_minuten SMALLINT UNSIGNED NULL');
    }
}

zorgVoorUitvoeringskolommen($pdo);

$soorten = ['symfonie', 'concert', 'ander'];
$melding = '';

// Toevoegen of bewerken van een werk.
if (isset($_POST['actie']) && $_POST['actie'] === 'opslaan') {
    $titel = trim((string) ($_POST['titel'] ?? ''));
    $kv_nummer = trim((string) ($_POST['kv_nummer'] ?? ''));
    $kv_toevoeging = trim((string) ($_POST['kv_toevoeging'] ?? ''));
    $jaar = trim((string) ($_POST['jaar'] ?? ''));
    $soort = in_array($_POST['soort'] ?? '', $soorten, true) ? $_POST['soort'] : 'ander';
    $bezetting = trim((string) ($_POST['bezetting'] ?? ''));
    $solo = trim((string) ($_POST['solo'] ?? ''));
    $solo = $solo === '' ? null : $solo;
    $uitgevoerd_op = trim((string) ($_POST['uitgevoerd_op'] ?? ''));
    $uitgevoerd_op = $uitgevoerd_op === '' ? null : $uitgevoerd_op;
    $met_solist = trim((string) ($_POST['met_solist'] ?? ''));
    $met_solist = $met_solist === '' ? null : $met_solist;
    $duur_minuten = trim((string) ($_POST['duur_minuten'] ?? ''));
    $duur_minuten = $duur_minuten === '' ? null : $duur_minuten;
    $id = trim((string) ($_POST['id'] ?? ''));

    if ($titel === '' || $kv_nummer === '' || $jaar === '') {
        $melding = 'Titel, KV-nummer en jaar zijn verplicht.';
    } elseif (mb_strlen($titel) > 255) {
        $melding = 'Titel mag maximaal 255 tekens bevatten.';
    } elseif (!ctype_digit($kv_nummer) || (int) $kv_nummer < 1 || (int) $kv_nummer > 9999) {
        $melding = 'KV-nummer moet een geheel getal zijn tussen 1 en 9999.';
    } elseif (mb_strlen($kv_toevoeging) > 10) {
        $melding = 'KV-toevoeging mag maximaal 10 tekens bevatten.';
    } elseif (!ctype_digit($jaar) || (int) $jaar < 1700 || (int) $jaar > 2100) {
        $melding = 'Jaar moet een geheel getal zijn tussen 1700 en 2100.';
    } elseif (mb_strlen($bezetting) > 255) {
        $melding = 'Bezetting mag maximaal 255 tekens bevatten.';
    } elseif ($solo !== null && mb_strlen($solo) > 255) {
        $melding = 'Solo mag maximaal 255 tekens bevatten.';
    } elseif ($uitgevoerd_op !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $uitgevoerd_op)) {
        $melding = 'Uitgevoerd op moet een geldige datum zijn.';
    } elseif ($met_solist !== null && mb_strlen($met_solist) > 100) {
        $melding = 'Met solist mag maximaal 100 tekens bevatten.';
    } elseif ($duur_minuten !== null && (!ctype_digit($duur_minuten) || (int) $duur_minuten < 1 || (int) $duur_minuten > 999)) {
        $melding = 'Duur moet een geheel aantal minuten zijn tussen 1 en 999.';
    } elseif ($id !== '' && !ctype_digit($id)) {
        $melding = 'Ongeldig werk-id.';
    } elseif ($id !== '') {
        // Werk moet echt bestaan; voorkomt dat een gemanipuleerd id een update in het niets doet.
        $bestaatStmt = $pdo->prepare('SELECT 1 FROM werken WHERE id = ?');
        $bestaatStmt->execute([$id]);
        if (!$bestaatStmt->fetchColumn()) {
            $melding = 'Dit werk bestaat niet meer.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE werken SET titel = ?, kv_nummer = ?, kv_toevoeging = ?, jaar = ?, soort = ?, bezetting = ?, solo = ?, uitgevoerd_op = ?, met_solist = ?, duur_minuten = ? WHERE id = ?'
            );
            $stmt->execute([$titel, $kv_nummer, $kv_toevoeging, $jaar, $soort, $bezetting, $solo, $uitgevoerd_op, $met_solist, $duur_minuten, $id]);
            $melding = 'Werk bijgewerkt.';
        }
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO werken (titel, kv_nummer, kv_toevoeging, jaar, soort, bezetting, solo, uitgevoerd_op, met_solist, duur_minuten) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$titel, $kv_nummer, $kv_toevoeging, $jaar, $soort, $bezetting, $solo, $uitgevoerd_op, $met_solist, $duur_minuten]);
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

        .veld-duur {
            width: 5em;
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
                <th>Duur (min.)</th>
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
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" value="<?= (int) $werk['id'] ?>">
                    <tr class="werk-rij" data-soort="<?= htmlspecialchars($werk['soort'], ENT_QUOTES, 'UTF-8') ?>">
                        <td><input class="w3-input<?= !empty($werk['uitgevoerd_op']) ? ' uitgevoerd-titel' : '' ?>" type="text" name="titel" value="<?= htmlspecialchars($werk['titel']) ?>" style="min-width:20em;" maxlength="255" required></td>
                        <td><input class="w3-input veld-kv" type="number" name="kv_nummer" value="<?= htmlspecialchars($werk['kv_nummer']) ?>" min="1" max="9999" required></td>
                        <td><input class="w3-input" type="text" name="kv_toevoeging" value="<?= htmlspecialchars($werk['kv_toevoeging'] ?? '') ?>" style="width:4em;" maxlength="10"></td>
                        <td><input class="w3-input veld-duur" type="number" name="duur_minuten" value="<?= htmlspecialchars($werk['duur_minuten'] ?? '') ?>" min="1" max="999"></td>
                        <td><input class="w3-input veld-jaar" type="number" name="jaar" value="<?= htmlspecialchars($werk['jaar']) ?>" min="1700" max="2100" required></td>
                        <td>
                            <select class="w3-select veld-soort" name="soort">
                                <?php foreach ($soorten as $soort): ?>
                                    <option value="<?= $soort ?>" <?= $werk['soort'] === $soort ? 'selected' : '' ?>><?= $soort ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="w3-input" type="text" name="bezetting" value="<?= htmlspecialchars($werk['bezetting']) ?>" maxlength="255"></td>
                        <td><input class="w3-input veld-solo" type="text" name="solo" value="<?= htmlspecialchars($werk['solo'] ?? '') ?>" maxlength="255"></td>
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <tr>
                    <td><input class="w3-input" type="text" name="titel" placeholder="Nieuw werk" style="min-width:20em;" maxlength="255" required></td>
                    <td><input class="w3-input veld-kv" type="number" name="kv_nummer" min="1" max="9999" required></td>
                    <td><input class="w3-input" type="text" name="kv_toevoeging" style="width:4em;" maxlength="10"></td>
                    <td><input class="w3-input veld-duur" type="number" name="duur_minuten" min="1" max="999"></td>
                    <td><input class="w3-input veld-jaar" type="number" name="jaar" min="1700" max="2100" required></td>
                    <td>
                        <select class="w3-select veld-soort" name="soort">
                            <?php foreach ($soorten as $soort): ?>
                                <option value="<?= $soort ?>"><?= $soort ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input class="w3-input" type="text" name="bezetting" maxlength="255"></td>
                    <td><input class="w3-input veld-solo" type="text" name="solo" maxlength="255"></td>
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
