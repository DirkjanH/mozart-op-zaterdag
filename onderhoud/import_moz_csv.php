<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../includes/csrf.php';

$csrfToken = csrfTokenOphalen();
csrfValiderenOfAfwijzen();
$sessieSleutel = 'moz_csv_import_kandidaten';
$melding = '';
$voorvertoning = [];

function csvWaarde(array $rij, string $kolom): string
{
    return trim((string) ($rij[$kolom] ?? ''));
}

function instrumentSleutel(string $naam): string
{
    return preg_replace('/[^a-z]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $naam))) ?? '';
}

function koppelInstrumenten(string $organisatieTitel, array $instrumenten): array
{
    $instrumentenOpNaam = [];
    foreach ($instrumenten as $instrument) {
        $instrumentenOpNaam[instrumentSleutel($instrument['naam'])] = $instrument;
    }
    $synoniemen = [
        'barokviool' => 'viool',
        'barokaltviool' => 'altviool',
        'barokcello' => 'cello',
        'althobo' => 'hobo',
        'barokhobo' => 'hobo',
    ];
    $delen = preg_split('/,|\ben\b|\/|\+/', $organisatieTitel) ?: [];
    $gevonden = [];
    $onbekend = [];
    foreach ($delen as $deel) {
        $deel = trim(preg_replace('/\([^)]*\)|\b[12](?:e|ste)?\b/i', '', $deel));
        if ($deel === '') {
            continue;
        }
        $sleutel = instrumentSleutel($deel);
        $sleutel = $synoniemen[$sleutel] ?? $sleutel;
        if (isset($instrumentenOpNaam[$sleutel])) {
            $gevonden[(int) $instrumentenOpNaam[$sleutel]['id']] = $instrumentenOpNaam[$sleutel];
        } else {
            $onbekend[] = $deel;
        }
    }

    return [array_values($gevonden), array_values(array_unique($onbekend))];
}

function leesMozContacten(string $bestand, array $instrumenten): array
{
    $handle = fopen($bestand, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Het CSV-bestand kon niet worden gelezen.');
    }

    $koppen = fgetcsv($handle);
    if (!is_array($koppen)) {
        throw new RuntimeException('Het CSV-bestand bevat geen kopregel.');
    }
    $koppen[0] = preg_replace('/^\xEF\xBB\xBF/', '', $koppen[0]);
    $verplicht = ['First Name', 'Last Name', 'E-mail 1 - Value'];
    foreach ($verplicht as $kolom) {
        if (!in_array($kolom, $koppen, true)) {
            throw new RuntimeException('Kolom ' . $kolom . ' ontbreekt in het CSV-bestand.');
        }
    }

    $contacten = [];
    $emails = [];
    while (($waarden = fgetcsv($handle)) !== false) {
        $rij = array_combine($koppen, array_pad($waarden, count($koppen), ''));
        if ($rij === false) {
            continue;
        }
        $email = '';
        foreach (['E-mail 1 - Value', 'E-mail 2 - Value', 'E-mail 3 - Value'] as $emailKolom) {
            $kandidaat = strtolower(csvWaarde($rij, $emailKolom));
            if (filter_var($kandidaat, FILTER_VALIDATE_EMAIL)) {
                $email = $kandidaat;
                break;
            }
        }
        if ($email === '' || isset($emails[$email])) {
            continue;
        }
        $emails[$email] = true;
        $voornaam = trim(csvWaarde($rij, 'First Name') . ' ' . csvWaarde($rij, 'Middle Name'));
        $achternaam = trim(csvWaarde($rij, 'Name Prefix') . ' ' . csvWaarde($rij, 'Last Name') . ' ' . csvWaarde($rij, 'Name Suffix'));
        if ($voornaam === '' && $achternaam === '') {
            continue;
        }
        [$gekoppeldeInstrumenten, $onbekendeInstrumenten] = koppelInstrumenten(csvWaarde($rij, 'Organization Title'), $instrumenten);
        $contacten[] = [
            'voornaam' => $voornaam !== '' ? $voornaam : $achternaam,
            'achternaam' => $achternaam !== '' ? $achternaam : '-',
            'email' => $email,
            'telefoon' => csvWaarde($rij, 'Phone 1 - Value'),
            'adres' => trim(csvWaarde($rij, 'Address 1 - Street') . ' ' . csvWaarde($rij, 'Address 1 - Extended Address')),
            'postcode' => csvWaarde($rij, 'Address 1 - Postal Code'),
            'plaats' => csvWaarde($rij, 'Address 1 - City'),
            'instrumenten' => $gekoppeldeInstrumenten,
            'onbekende_instrumenten' => $onbekendeInstrumenten,
        ];
    }
    fclose($handle);

    return $contacten;
}

if (($_POST['actie'] ?? '') === 'voorvertonen') {
    try {
        $upload = $_FILES['moz_csv'] ?? null;
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Kies een geldig CSV-bestand.');
        }
        if (($upload['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new RuntimeException('Het CSV-bestand mag maximaal 2 MB groot zijn.');
        }
        $instrumenten = $pdo->query('SELECT id, naam FROM instrumenten')->fetchAll(PDO::FETCH_ASSOC);
        $kandidaten = leesMozContacten($upload['tmp_name'], $instrumenten);
        $bestaatStmt = $pdo->prepare('SELECT 1 FROM deelnemers WHERE LOWER(TRIM(email)) = ? LIMIT 1');
        foreach ($kandidaten as $kandidaat) {
            $bestaatStmt->execute([$kandidaat['email']]);
            $kandidaat['bestaat'] = (bool) $bestaatStmt->fetchColumn();
            $voorvertoning[] = $kandidaat;
        }
        $_SESSION[$sessieSleutel] = $voorvertoning;
        $nieuw = count(array_filter($voorvertoning, static fn (array $kandidaat): bool => !$kandidaat['bestaat']));
        $melding = count($voorvertoning) . ' contacten gelezen; ' . $nieuw . ' worden toegevoegd na bevestiging.';
    } catch (Throwable $e) {
        $melding = 'CSV niet ingelezen: ' . $e->getMessage();
    }
} elseif (($_POST['actie'] ?? '') === 'importeren') {
    $kandidaten = $_SESSION[$sessieSleutel] ?? null;
    if (!is_array($kandidaten)) {
        $melding = 'Laad eerst een CSV-bestand voor de voorvertoning.';
    } else {
        try {
            $pdo->beginTransaction();
            $bestaatStmt = $pdo->prepare('SELECT 1 FROM deelnemers WHERE LOWER(TRIM(email)) = ? LIMIT 1');
            $insertStmt = $pdo->prepare('INSERT INTO deelnemers (voornaam, achternaam, email, telefoon, adres, postcode, plaats) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $instrumentStmt = $pdo->prepare('INSERT INTO deelnemer_instrumenten (deelnemer_id, instrument_id) VALUES (?, ?)');
            $toegevoegd = 0;
            $overgeslagen = 0;
            foreach ($kandidaten as $kandidaat) {
                $bestaatStmt->execute([$kandidaat['email']]);
                if ($bestaatStmt->fetchColumn()) {
                    $overgeslagen++;
                    continue;
                }
                $insertStmt->execute([
                    $kandidaat['voornaam'],
                    $kandidaat['achternaam'],
                    $kandidaat['email'],
                    $kandidaat['telefoon'] ?: null,
                    $kandidaat['adres'] ?: null,
                    $kandidaat['postcode'] ?: null,
                    $kandidaat['plaats'] ?: null,
                ]);
                $deelnemerId = (int) $pdo->lastInsertId();
                foreach ($kandidaat['instrumenten'] as $instrument) {
                    $instrumentStmt->execute([$deelnemerId, (int) $instrument['id']]);
                }
                $toegevoegd++;
            }
            $pdo->commit();
            unset($_SESSION[$sessieSleutel]);
            $voorvertoning = [];
            $melding = $toegevoegd . ' deelnemers toegevoegd; ' . $overgeslagen . ' bestaande e-mailadressen overgeslagen.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $melding = 'Import niet uitgevoerd: ' . $e->getMessage();
        }
    }
}

if ($voorvertoning === [] && is_array($_SESSION[$sessieSleutel] ?? null)) {
    $voorvertoning = $_SESSION[$sessieSleutel];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MoZ-contacten importeren</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
</head>
<body>
    <main class="w3-content w3-mobile w3-white w3-panel" style="max-width:1000px">
        <h3>MoZ-contacten importeren</h3>
        <?php if ($melding !== ''): ?><p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="w3-margin-bottom">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="moz_csv"><strong>Google Contacts CSV</strong></label>
            <input class="w3-input w3-border" id="moz_csv" name="moz_csv" type="file" accept=".csv,text/csv" required>
            <button class="w3-button w3-light-grey w3-margin-top" type="submit" name="actie" value="voorvertonen">Voorvertoning laden</button>
        </form>
        <?php if ($voorvertoning !== []): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button class="w3-button w3-blue w3-margin-bottom" type="submit" name="actie" value="importeren" onclick="return confirm('Alleen ontbrekende e-mailadressen worden toegevoegd. Doorgaan?')">Ontbrekende deelnemers importeren</button>
            </form>
            <div style="max-height:60vh;overflow:auto">
                <table class="w3-table w3-bordered w3-striped w3-small">
                    <tr><th>Naam</th><th>E-mail</th><th>Telefoon</th><th>Adres</th><th>Postcode</th><th>Plaats</th><th>Instrumenten</th><th>Status</th></tr>
                    <?php foreach ($voorvertoning as $kandidaat): ?><tr>
                        <td><?= htmlspecialchars(trim($kandidaat['voornaam'] . ' ' . $kandidaat['achternaam']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($kandidaat['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($kandidaat['telefoon'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($kandidaat['adres'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($kandidaat['postcode'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($kandidaat['plaats'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(implode(', ', array_column($kandidaat['instrumenten'], 'naam')), ENT_QUOTES, 'UTF-8') ?><?= $kandidaat['onbekende_instrumenten'] !== [] ? ' (niet gekoppeld: ' . htmlspecialchars(implode(', ', $kandidaat['onbekende_instrumenten']), ENT_QUOTES, 'UTF-8') . ')' : '' ?></td>
                        <td><?= $kandidaat['bestaat'] ? 'bestaat al' : 'toevoegen' ?></td>
                    </tr><?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>