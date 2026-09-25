<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

$activiteiten = $pdo->query(
    'SELECT id, datum, plaats, omschrijving FROM activiteiten ORDER BY datum DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$activiteitId = (int) ($_GET['activiteit_id'] ?? 0);
if ($activiteitId === 0) {
    foreach (array_reverse($activiteiten) as $activiteit) {
        if ($activiteit['datum'] >= date('Y-m-d')) {
            $activiteitId = (int) $activiteit['id'];
            break;
        }
    }
    $activiteitId = $activiteitId ?: (int) ($activiteiten[0]['id'] ?? 0);
}

$gekozenActiviteit = null;
foreach ($activiteiten as $activiteit) {
    if ((int) $activiteit['id'] === $activiteitId) {
        $gekozenActiviteit = $activiteit;
        break;
    }
}

$deelnemers = [];
if ($gekozenActiviteit !== null) {
    $stmt = $pdo->prepare("SELECT d.voornaam, d.achternaam, ad.partij, i.naam AS instrument
        FROM activiteit_deelnemers ad
        JOIN deelnemers d ON d.id = ad.deelnemer_id
        LEFT JOIN (
            SELECT deelnemer_id, MIN(instrument_id) AS instrument_id
            FROM deelnemer_instrumenten
            GROUP BY deelnemer_id
        ) di ON di.deelnemer_id = d.id
        LEFT JOIN instrumenten i ON i.id = COALESCE(ad.instrument_id, di.instrument_id)
        WHERE ad.activiteit_id = ? AND ad.status <> 'nee' AND ad.toegelaten = 1
        ORDER BY CASE WHEN i.id IS NULL THEN 1 ELSE 0 END,
            CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN COALESCE((SELECT MIN(i2.id) FROM instrumenten i2 WHERE LOWER(TRIM(i2.naam)) LIKE 'trompet%'), i.id) ELSE i.id END,
            CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN 1 ELSE 0 END,
            CASE WHEN LOWER(TRIM(i.naam)) = 'viool' AND LOWER(COALESCE(ad.partij, '')) REGEXP '2' THEN 2 WHEN LOWER(TRIM(i.naam)) = 'viool' THEN 1 ELSE 0 END,
            CASE WHEN LOWER(COALESCE(ad.partij, '')) REGEXP 'concertmeester|aanvoerder' THEN 0 ELSE 1 END,
            CASE WHEN TRIM(COALESCE(ad.partij, '')) = '' THEN 1 ELSE 0 END,
            ad.partij, d.achternaam, d.voornaam");
    $stmt->execute([$activiteitId]);
    $deelnemers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deelnemerslijst</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <style>
        .keuze-formulier select { width: min(100%, 32em); }
        .deelnemerslijst { width: 100%; }
        @media (max-width: 600px) {
            body { margin: 0; font-size: 16px; }
            .pagina { width: 100%; max-width: none !important; margin: 0; box-sizing: border-box; }
            .keuze-formulier select { width: 100%; font-size: 16px; }
            .deelnemerslijst th, .deelnemerslijst td { font-size: 1rem; }
        }
    </style>
</head>

<body>
    <main class="pagina w3-content w3-mobile w3-white w3-panel" style="max-width:800px;">
        <p><a href="dashboard.php">&larr; Terug naar dashboard</a></p>
        <h3>Deelnemerslijst</h3>

        <form method="get" class="keuze-formulier w3-margin-bottom">
            <label for="activiteit_id"><strong>Activiteit</strong></label>
            <select class="w3-select w3-border" id="activiteit_id" name="activiteit_id" onchange="this.form.submit()">
                <?php foreach ($activiteiten as $activiteit): ?>
                    <option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>>
                        <?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($gekozenActiviteit === null): ?>
            <p>Er zijn geen activiteiten gevonden.</p>
        <?php else: ?>
            <h4><?= htmlspecialchars(date('d-m-Y', strtotime($gekozenActiviteit['datum'])) . ' - ' . $gekozenActiviteit['plaats']) ?></h4>
            <p><?= count($deelnemers) ?> toegelaten deelnemers</p>
            <div class="w3-responsive">
                <table class="deelnemerslijst w3-table w3-bordered w3-striped">
                    <thead>
                        <tr><th>Naam</th><th>Instrument + rol</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deelnemers as $deelnemer): ?>
                            <tr>
                                <td><?= htmlspecialchars($deelnemer['voornaam'] . ' ' . $deelnemer['achternaam']) ?></td>
                                <td><?= htmlspecialchars(trim(($deelnemer['instrument'] ?? '') . ' ' . ($deelnemer['partij'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>