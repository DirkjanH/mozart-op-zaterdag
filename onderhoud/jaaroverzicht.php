<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../includes/bezetting.inc.php';

// Toont de activiteiten van een gekozen jaar (standaard 2027) als bulletlijst,
// met de gewenste bezetting uitgeschreven in woorden.

$jaar = isset($_GET['jaar']) && preg_match('/^\d{4}$/', (string) $_GET['jaar']) ? (int) $_GET['jaar'] : 2027;

$stmt = $pdo->prepare('SELECT datum, plaats, omschrijving, gewenste_bezetting FROM activiteiten WHERE YEAR(datum) = ? ORDER BY datum');
$stmt->execute([$jaar]);
$activiteiten = $stmt->fetchAll(PDO::FETCH_ASSOC);

$maandNamen = [1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
function nederlandseDatumLang(string $datum, array $maandNamen): string
{
    $tijdstip = new DateTimeImmutable($datum);
    return $tijdstip->format('j') . ' ' . $maandNamen[(int) $tijdstip->format('n')] . ' ' . $tijdstip->format('Y');
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Jaaroverzicht <?= (int) $jaar ?></title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
</head>

<body>
    <div class="w3-panel w3-white">
        <h3>Activiteiten <?= (int) $jaar ?></h3>
        <?php if ($activiteiten === []): ?>
            <p>Geen activiteiten gevonden voor <?= (int) $jaar ?>.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($activiteiten as $activiteit): ?>
                    <li>
                        <strong><?= htmlspecialchars(nederlandseDatumLang($activiteit['datum'], $maandNamen)) ?>
                            (<?= htmlspecialchars($activiteit['plaats']) ?>)</strong><?= $activiteit['omschrijving'] ? ': ' . htmlspecialchars($activiteit['omschrijving']) : '' ?>.
                        Bezetting: <?= htmlspecialchars(bezettingInWoorden($activiteit['gewenste_bezetting'])) ?>.
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>

</html>
