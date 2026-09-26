<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../includes/csrf.php';

$csrfToken = csrfTokenOphalen();
csrfValiderenOfAfwijzen();
$bestand = __DIR__ . '/../JSON/aanmeldbevestiging.json';
$map = dirname($bestand);
$melding = '';
$standaardMailtekst = [
    'onderwerp' => 'Bevestiging aanmelding Mozart op Zaterdag',
    'tekst' => '<p>Beste {{voornaam}},</p><p>Dank voor je aanmelding bij Mozart op Zaterdag. We hebben je gegevens goed ontvangen.</p><p>Naam: {{naam}}<br>Instrument(en): {{instrumenten}}<br>Beschikbaarheid:<br>{{beschikbaarheid}}</p><p>Hartelijke groet,<br>Dirkjan Horringa</p><p>P.S. Als je je gegevens of je beschikbaarheid wilt wijzigen, <a href="{{aanmeldlink}}">vul dan even dit formulier in</a>. Je bestaande gegevens staan daar alvast ingevuld.</p>',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $onderwerp = trim((string) ($_POST['onderwerp'] ?? ''));
        $tekst = trim((string) ($_POST['tekst'] ?? ''));
        if ($onderwerp === '' || $tekst === '') {
            throw new RuntimeException('Onderwerp en tekst zijn verplicht.');
        }
        if (strlen($onderwerp) > 255 || preg_match('/[\r\n]/', $onderwerp)) {
            throw new RuntimeException('Het onderwerp is te lang of bevat een regeleinde.');
        }
        if (strlen($tekst) > 200000) {
            throw new RuntimeException('De mailtekst is te groot.');
        }
        $nieuweMailtekst = ['onderwerp' => $onderwerp, 'tekst' => $tekst];
        $json = json_encode($nieuweMailtekst, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (is_file($bestand)) {
            $tijdstip = date('Ymd-His');
            $backup = $map . '/aanmeldbevestiging-backup-' . $tijdstip . '.json';
            $volgnummer = 1;
            while (file_exists($backup)) {
                $backup = $map . '/aanmeldbevestiging-backup-' . $tijdstip . '-' . $volgnummer . '.json';
                $volgnummer++;
            }
            if (!copy($bestand, $backup)) {
                throw new RuntimeException('De backup kon niet worden gemaakt.');
            }
        }
        if (file_put_contents($bestand, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('De JSON-mailtekst kon niet worden geschreven.');
        }
        $backups = glob($map . '/aanmeldbevestiging-backup-*.json') ?: [];
        usort($backups, static fn (string $eerste, string $tweede): int => strnatcmp(basename($tweede), basename($eerste)));
        foreach (array_slice($backups, 3) as $oudeBackup) {
            if (!unlink($oudeBackup)) {
                throw new RuntimeException('Oude backup ' . basename($oudeBackup) . ' kon niet worden verwijderd.');
            }
        }
        $melding = 'Bevestigingsmail opgeslagen.';
    } catch (Throwable $e) {
        $melding = 'Bevestigingsmail niet opgeslagen: ' . $e->getMessage();
    }
}

try {
    $mailtekst = json_decode((string) file_get_contents($bestand), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($mailtekst) || !is_string($mailtekst['onderwerp'] ?? null) || !is_string($mailtekst['tekst'] ?? null)) {
        throw new RuntimeException('Ongeldig mailtekstformaat.');
    }
} catch (Throwable $e) {
    $mailtekst = $standaardMailtekst;
    $melding = $melding ?: 'De opgeslagen mailtekst kon niet worden geladen; de standaardtekst wordt getoond.';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aanmeldbevestiging bewerken</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>
</head>
<body>
    <main class="w3-content w3-mobile w3-white w3-panel" style="max-width:900px">
        <h3>Aanmeldbevestiging bewerken</h3>
        <?php if ($melding !== ''): ?>
            <p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form id="aanmeldbevestiging-formulier" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="onderwerp"><strong>Onderwerp</strong></label>
            <input class="w3-input w3-border w3-margin-bottom" id="onderwerp" name="onderwerp" value="<?= htmlspecialchars($mailtekst['onderwerp'], ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required>
            <label for="tekst"><strong>Mailtekst</strong></label>
            <textarea id="tekst" name="tekst" required><?= htmlspecialchars($mailtekst['tekst'], ENT_QUOTES, 'UTF-8') ?></textarea>
            <p class="w3-small">Beschikbare velden: {{voornaam}}, {{achternaam}}, {{naam}}, {{email}}, {{telefoon}}, {{postcode}}, {{plaats}}, {{instrumenten}}, {{beschikbaarheid}} en {{aanmeldlink}}.</p>
            <button class="w3-button w3-blue w3-margin-bottom" type="submit">Opslaan in JSON</button>
        </form>
    </main>
    <script>
    CKEDITOR.replace('tekst', {
        toolbar: 'Full',
        height: 320,
        versionCheck: false,
        allowedContent: true,
        removePlugins: 'cloudservices,easyimage,exportpdf'
    });
    document.getElementById('aanmeldbevestiging-formulier').addEventListener('submit', function () {
        CKEDITOR.instances.tekst.updateElement();
    });
    </script>
</body>
</html>