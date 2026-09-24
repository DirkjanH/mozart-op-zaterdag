<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

// Toont de activiteiten van een gekozen jaar (standaard 2027) als bulletlijst,
// met de gewenste bezetting uitgeschreven in woorden.

$jaar = isset($_GET['jaar']) && preg_match('/^\d{4}$/', (string) $_GET['jaar']) ? (int) $_GET['jaar'] : 2027;

function getalInWoorden(int $getal): string
{
    $woorden = ['nul', 'een', 'twee', 'drie', 'vier', 'vijf', 'zes', 'zeven', 'acht', 'negen'];
    return $woorden[$getal] ?? (string) $getal;
}

// Zet een notatie als "0201-2000-timp-56442" om in een leesbare, in woorden
// uitgeschreven bezetting, bijv. "twee hobo's, een fagot, twee hoorns en strijkers".
function bezettingInWoorden(?string $notatie): string
{
    $notatie = trim((string) $notatie);
    if ($notatie === '') {
        return 'geen bezetting opgegeven';
    }
    if (!preg_match('/^(\d{4})-(\d{4})(-timp)?-(\d{5})$/i', $notatie, $delen)) {
        return 'ongeldig bezettingsformaat';
    }

    $hout = array_map('intval', str_split($delen[1]));
    $koper = array_map('intval', str_split($delen[2]));
    $pauken = $delen[3] !== '';
    $strijkers = array_map('intval', str_split($delen[4]));

    $enkelvoud = [
        'fluit' => 'fluit', 'hobo' => 'hobo', 'klarinet' => 'klarinet', 'fagot' => 'fagot',
        'hoorn' => 'hoorn', 'trompet' => 'trompet', 'trombone' => 'trombone', 'tuba' => 'tuba',
        'eerste viool' => 'eerste viool', 'tweede viool' => 'tweede viool',
        'altviool' => 'altviool', 'cello' => 'cello', 'contrabas' => 'contrabas',
    ];
    $meervoud = [
        'fluit' => 'fluiten', 'hobo' => "hobo's", 'klarinet' => 'klarinetten', 'fagot' => 'fagotten',
        'hoorn' => 'hoorns', 'trompet' => 'trompetten', 'trombone' => 'trombones', 'tuba' => "tuba's",
        'eerste viool' => 'eerste violen', 'tweede viool' => 'tweede violen',
        'altviool' => 'altviolen', 'cello' => "cello's", 'contrabas' => 'contrabassen',
    ];

    $aantallen = array_combine(
        ['fluit', 'hobo', 'klarinet', 'fagot', 'hoorn', 'trompet', 'trombone', 'tuba', 'eerste viool', 'tweede viool', 'altviool', 'cello', 'contrabas'],
        [$hout[0], $hout[1], $hout[2], $hout[3], $koper[0], $koper[1], $koper[2], $koper[3], $strijkers[0], $strijkers[1], $strijkers[2], $strijkers[3], $strijkers[4]]
    );

    $delenInWoorden = [];
    foreach ($aantallen as $instrument => $aantal) {
        if ($aantal < 1) {
            continue;
        }
        $label = $aantal === 1 ? $enkelvoud[$instrument] : $meervoud[$instrument];
        $delenInWoorden[] = getalInWoorden($aantal) . ' ' . $label;
    }
    if ($pauken) {
        $delenInWoorden[] = 'pauken';
    }

    if ($delenInWoorden === []) {
        return 'geen bezetting opgegeven';
    }
    if (count($delenInWoorden) === 1) {
        return $delenInWoorden[0];
    }
    $laatste = array_pop($delenInWoorden);
    return implode(', ', $delenInWoorden) . ' en ' . $laatste;
}

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
