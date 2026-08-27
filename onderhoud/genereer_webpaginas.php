<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';

$melding = '';
$voorbeeldPartijen = [];

$activiteiten = $pdo->query(
    'SELECT id, datum, plaats, omschrijving FROM activiteiten ORDER BY datum'
)->fetchAll(PDO::FETCH_ASSOC);

$activiteitId = (int) ($_POST['activiteit_id'] ?? $_GET['activiteit_id'] ?? 0);
$betekendeBestanden = array_map('basename', $_POST['betekend'] ?? []);
if ($activiteitId === 0 && $activiteiten !== []) {
    $activiteitId = (int) $activiteiten[0]['id'];
}

function partijLabel(string $bestand): string
{
    $naam = pathinfo($bestand, PATHINFO_FILENAME);
    $naam = preg_replace('/[_-]+/', ' ', $naam);
    $naam = preg_replace('/\s+/', ' ', $naam);
    return trim($naam);
}

function isStrijkerPartij(string $bestand): bool
{
    return (bool) preg_match('/viool|violin|altviool|viola|cello|contrabas|double.?bass|strijk/i', $bestand);
}

function leesPartijen(string $map): array
{
    if (!is_dir($map)) {
        return [];
    }

    $partijen = [];
    foreach (scandir($map) as $bestand) {
        if ($bestand === '.' || $bestand === '..' || !is_file($map . DIRECTORY_SEPARATOR . $bestand)) {
            continue;
        }
        if (strtolower(pathinfo($bestand, PATHINFO_EXTENSION)) !== 'pdf') {
            continue;
        }
        $partijen[] = [
            'bestand' => $bestand,
            'label' => partijLabel($bestand),
            'strijker' => isStrijkerPartij($bestand),
        ];
    }

    usort($partijen, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
    return $partijen;
}

function html(string $waarde): string
{
    return htmlspecialchars($waarde, ENT_QUOTES, 'UTF-8');
}

function leesPaginaConfiguratie(string $map): array
{
    $bestand = $map . '/.mozart-webpagina.json';
    if (!is_file($bestand)) {
        return [];
    }

    $inhoud = json_decode((string) file_get_contents($bestand), true);
    return is_array($inhoud) ? $inhoud : [];
}

function bewaarPaginaConfiguratie(string $map, array $configuratie): bool
{
    $inhoud = json_encode($configuratie, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $inhoud !== false && file_put_contents($map . '/.mozart-webpagina.json', $inhoud . PHP_EOL) !== false;
}

$paginaConfiguratie = [];
$gekozenActiviteit = null;
foreach ($activiteiten as $activiteit) {
    if ((int) $activiteit['id'] === $activiteitId) {
        $gekozenActiviteit = $activiteit;
        $paginaConfiguratie = leesPaginaConfiguratie(dirname(__DIR__) . '/' . $activiteit['datum']);
        break;
    }
}

if (isset($_POST['actie']) && in_array($_POST['actie'], ['opslaan', 'genereer'], true)) {
    $genereerPagina = $_POST['actie'] === 'genereer';
    $activiteitId = (int) ($_POST['activiteit_id'] ?? 0);
    $toelichting = trim($_POST['toelichting'] ?? '');
    $solisten = trim($_POST['solisten'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM activiteiten WHERE id = ?');
    $stmt->execute([$activiteitId]);
    $activiteit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activiteit) {
        $melding = 'Activiteit niet gevonden.';
    } else {
        $datum = $activiteit['datum'];
        $map = dirname(__DIR__) . '/' . $datum;
        if (!is_dir($map) && !mkdir($map, 0755, true)) {
            $melding = 'De datummap kon niet worden aangemaakt.';
        } else {
            $partijen = leesPartijen($map);
            $partijConfiguratie = [];
            foreach ($partijen as $index => $partij) {
                $ingevoerdBestand = basename((string) ($_POST['partijen'][$index]['bestand'] ?? ''));
                if ($ingevoerdBestand !== $partij['bestand']) {
                    continue;
                }
                $partijConfiguratie[$partij['bestand']] = [
                    'label' => trim((string) ($_POST['partijen'][$index]['label'] ?? '')),
                    'link' => trim((string) ($_POST['partijen'][$index]['link'] ?? '')),
                    'betekend' => isset($_POST['partijen'][$index]['betekend']),
                ];
            }
            $versie = max(0, (int) ($paginaConfiguratie['versie'] ?? 0)) + ($genereerPagina ? 1 : 0);
            $paginaConfiguratie = [
                'versie' => $versie,
                'toelichting' => $toelichting,
                'solisten' => $solisten,
                'partijen' => $partijConfiguratie,
            ];
            if (!bewaarPaginaConfiguratie($map, $paginaConfiguratie)) {
                $melding = 'De pagina-inhoud kon niet worden opgeslagen.';
            } elseif (!$genereerPagina) {
                $melding = 'Pagina-inhoud opgeslagen.';
            } else {
            $stmt = $pdo->prepare(
                'SELECT d.voornaam, d.achternaam, ad.partij, i.naam AS instrument
                 FROM activiteit_deelnemers ad
                 JOIN deelnemers d ON d.id = ad.deelnemer_id
                 LEFT JOIN instrumenten i ON i.id = ad.instrument_id
                 WHERE ad.activiteit_id = ? AND ad.toegelaten = 1
                 ORDER BY COALESCE(i.id, 999999), ad.partij, d.achternaam, d.voornaam'
            );
            $stmt->execute([$activiteitId]);
            $deelnemers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $partijenHtml = '';
            foreach ($partijen as $partij) {
                $instellingen = $partijConfiguratie[$partij['bestand']] ?? [];
                $label = $instellingen['label'] ?: $partij['label'];
                $link = $instellingen['link'] ?: $partij['bestand'];
                if ($partij['strijker'] && !empty($instellingen['betekend'])) {
                    $label .= ' (betekend)';
                }
                $partijenHtml .= '            <li><a href="' . html($link) . '" target="_blank">' . html($label) . "</a></li>\n";
            }
            if ($partijenHtml === '') {
                $partijenHtml = "            <li>Er zijn nog geen PDF-partijen in deze map.</li>\n";
            }

            $deelnemersHtml = '';
            foreach ($deelnemers as $deelnemer) {
                $instrument = $deelnemer['instrument'] ?? 'onbekend instrument';
                $partij = trim($deelnemer['partij'] ?? '');
                $instrumentWeergave = $partij === '' ? $instrument : $instrument . ' ' . $partij;
                $deelnemersHtml .= '                <tr><td>' . html($deelnemer['voornaam']) . '</td><td>' . html($deelnemer['achternaam']) . '</td><td>' . html($instrumentWeergave) . "</td></tr>\n";
            }
            if ($deelnemersHtml === '') {
                $deelnemersHtml = "                <tr><td colspan=\"3\">Er zijn nog geen deelnemers toegelaten.</td></tr>\n";
            }

            $titel = $activiteit['omschrijving'] ?: 'Mozart op Zaterdag';
            $omschrijvingHtml = $toelichting === '' ? '' : "        <div>\n" . $toelichting . "\n        </div>\n";
            $solistenHtml = $solisten === '' ? '' : "        <h2>De solisten</h2>\n        <div>\n" . $solisten . "\n        </div>\n";
            $gegenereerd = '<?php require_once \'../includes/inloggen.php\'; ?>' . "\n";
            $gegenereerd .= '<!DOCTYPE html>' . "\n<html lang=\"nl\">\n<head>\n";
            $gegenereerd .= '    <meta charset="UTF-8">' . "\n";
            $gegenereerd .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
            $gegenereerd .= '    <title>' . html($titel) . "</title>\n    <link href=\"/css/moz.css\" rel=\"stylesheet\" type=\"text/css\">\n</head>\n<body>\n";
            $gegenereerd .= "    <div class=\"w3-content w3-white w3-panel\">\n";
            $gegenereerd .= "        <?php require_once '../navigatie.htm'; ?>\n";
            $gegenereerd .= '        <h3>Mozart op Zaterdag op ' . date('d F Y', strtotime($datum)) . ":</h3>\n";
            $gegenereerd .= '        <h1>' . html($titel) . "</h1>\n";
            $gegenereerd .= '        <p><small>Versie ' . $versie . ', gegenereerd op ' . date('d-m-Y H:i') . "</small></p>\n";
            $gegenereerd .= $omschrijvingHtml;
            $gegenereerd .= $solistenHtml;
            $gegenereerd .= "        <h3>Partijen</h3>\n        <ul style=\"column-count: 3;\">\n" . $partijenHtml . "        </ul>\n";
            $gegenereerd .= "        <h2>Bezetting</h2>\n";
            $gegenereerd .= '        <p>Er zijn ' . count($deelnemers) . " toegelaten deelnemers.</p>\n";
            $gegenereerd .= "        <table class=\"w3-table w3-striped w3-bordered\" id=\"deelnemers\">\n            <thead><tr><th>voornaam</th><th>achternaam</th><th>instrument</th></tr></thead>\n            <tbody>\n" . $deelnemersHtml . "            </tbody>\n        </table>\n    </div>\n</body>\n</html>\n";

            if (file_put_contents($map . '/index.php', $gegenereerd) === false) {
                $melding = 'De webpagina kon niet worden geschreven.';
            } else {
                $voorbeeldPartijen = $partijen;
                $melding = 'Webpagina gegenereerd in ' . $datum . '/index.php.';
            }
            }
        }
    }
}

if ($gekozenActiviteit !== null && $voorbeeldPartijen === []) {
    $voorbeeldPartijen = leesPartijen(dirname(__DIR__) . '/' . $gekozenActiviteit['datum']);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Genereer webpagina's</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <script src="/vendor/tinymce/tinymce/tinymce.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.tinymce) {
                tinymce.init({
                    selector: '.rijke-editor',
                    license_key: 'gpl',
                    height: 220,
                    menubar: false,
                    plugins: 'lists link',
                    toolbar: 'bold italic | bullist numlist | link | undo redo'
                });
            }
        });
    </script>
</head>
<body>
    <div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1100px;">
        <h3>Genereer webpagina's</h3>
        <?php if ($melding !== ''): ?>
            <p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= html($melding) ?></p>
        <?php endif; ?>

        <form method="get">
            <p>
                <label for="activiteit_id">Activiteit</label>
                <select class="w3-select" id="activiteit_id" name="activiteit_id" onchange="this.form.submit()" required>
                    <?php foreach ($activiteiten as $activiteit): ?>
                        <option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>>
                            <?= html(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
        </form>

        <form method="post">
            <input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>">
            <?php if ($gekozenActiviteit !== null): ?>
                <h4><?= html($gekozenActiviteit['omschrijving'] ?: 'Mozart op Zaterdag') ?></h4>
            <?php endif; ?>
            <p>
                <label for="toelichting">Toelichting</label>
                <textarea class="rijke-editor" id="toelichting" name="toelichting" rows="8" placeholder="Schrijf hier de toelichting voor de webpagina..."><?= html((string) ($paginaConfiguratie['toelichting'] ?? '')) ?></textarea>
            </p>
            <p>
                <label for="solisten">Solist(en), facultatief</label>
                <textarea class="rijke-editor" id="solisten" name="solisten" rows="5" placeholder="Bijvoorbeeld: &lt;h4&gt;Naam (instrument)&lt;/h4&gt;&lt;p&gt;Biografie...&lt;/p&gt;"><?= html((string) ($paginaConfiguratie['solisten'] ?? '')) ?></textarea>
            </p>
            <h4>Partijen in de datummap</h4>
            <?php if ($voorbeeldPartijen === []): ?>
                <p>Geen PDF-partijen gevonden in de map.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($voorbeeldPartijen as $index => $partij): ?>
                        <?php $instellingen = $paginaConfiguratie['partijen'][$partij['bestand']] ?? []; ?>
                        <li>
                            <input type="hidden" name="partijen[<?= $index ?>][bestand]" value="<?= html($partij['bestand']) ?>">
                            <label>
                                Tekst
                                <input class="w3-input" type="text" name="partijen[<?= $index ?>][label]" value="<?= html((string) ($instellingen['label'] ?? $partij['label'])) ?>" style="display:inline-block; width:18em;">
                            </label>
                            <label>
                                Link
                                <input class="w3-input" type="text" name="partijen[<?= $index ?>][link]" value="<?= html((string) ($instellingen['link'] ?? $partij['bestand'])) ?>" style="display:inline-block; width:24em;">
                            </label>
                            <?php if ($partij['strijker']): ?>
                                <label>
                                    <input type="checkbox" name="partijen[<?= $index ?>][betekend]" <?= !empty($instellingen['betekend']) || (!isset($paginaConfiguratie['partijen'][$partij['bestand']]) && preg_match('/betekend/i', $partij['bestand'])) ? 'checked' : '' ?>>
                                    betekend
                                </label>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p>De bezetting wordt automatisch opgebouwd uit bevestigde deelnemers, inclusief hun toegewezen partij. De volgende generatie wordt versie <?= max(0, (int) ($paginaConfiguratie['versie'] ?? 0)) + 1 ?>.</p>
            <button class="w3-button w3-light-grey" type="submit" name="actie" value="opslaan">Opslaan</button>
            <button class="w3-button w3-blue" type="submit" name="actie" value="genereer">Webpagina genereren</button>
        </form>
    </div>
</body>
</html>
