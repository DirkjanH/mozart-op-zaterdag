<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../vendor/autoload.php';

$melding = '';
$activiteitId = (int) ($_GET['activiteit_id'] ?? $_POST['activiteit_id'] ?? 0);
$activiteiten = $pdo->query('SELECT id, datum, plaats, omschrijving FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum')->fetchAll(PDO::FETCH_ASSOC);
if ($activiteitId === 0 && $activiteiten !== []) $activiteitId = (int) $activiteiten[0]['id'];
$instrumenten = $pdo->query('SELECT id, naam FROM instrumenten ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['actie'], $_POST['deelnemer_id'], $_POST['activiteit_id'])) {
    $deelnemerId = (int) $_POST['deelnemer_id'];
    $activiteitId = (int) $_POST['activiteit_id'];
    if ($_POST['actie'] === 'opslaan') {
        $status = in_array($_POST['status'] ?? '', ['ja', 'nee', 'misschien'], true) ? $_POST['status'] : 'misschien';
        $partij = trim($_POST['partij'] ?? '') ?: null;
        $instrumentId = (int) ($_POST['instrument_id'] ?? 0) ?: null;
        $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET instrument_id = ?, partij = ?, status = ? WHERE activiteit_id = ? AND deelnemer_id = ?');
        $stmt->execute([$instrumentId, $partij, $status, $activiteitId, $deelnemerId]);
        $melding = 'Spelergegevens opgeslagen.';
    } elseif (in_array($_POST['actie'], ['bevestigen', 'afwijzen'], true)) {
        $stmt = $pdo->prepare('SELECT d.voornaam, d.achternaam, d.email, a.datum, a.plaats, ad.partij FROM activiteit_deelnemers ad JOIN deelnemers d ON d.id = ad.deelnemer_id JOIN activiteiten a ON a.id = ad.activiteit_id WHERE ad.activiteit_id = ? AND ad.deelnemer_id = ?');
        $stmt->execute([$activiteitId, $deelnemerId]);
        $speler = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$speler || !filter_var($speler['email'], FILTER_VALIDATE_EMAIL)) {
            $melding = 'Geen geldig e-mailadres voor deze speler.';
        } else {
            $bevestigen = $_POST['actie'] === 'bevestigen';
            $naam = htmlspecialchars($speler['voornaam'], ENT_QUOTES, 'UTF-8');
            $datum = date('d-m-Y', strtotime($speler['datum']));
            $partij = $speler['partij'] ? ' Je speelt partij ' . htmlspecialchars($speler['partij'], ENT_QUOTES, 'UTF-8') . '.' : '';
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isMail();
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom('php@pellegrina.net', 'Mozart op Zaterdag');
            $mailer->addAddress($speler['email'], $speler['voornaam'] . ' ' . $speler['achternaam']);
            $mailer->isHTML(true);
            $mailer->Subject = $bevestigen ? 'Bevestiging deelname Mozart op Zaterdag' : 'Mozart op Zaterdag - deze keer geen plaats';
            $mailer->Body = $bevestigen
                ? "Beste {$naam},<br><br>Je bent bevestigd voor Mozart op Zaterdag op {$datum} in de " . htmlspecialchars($speler['plaats'], ENT_QUOTES, 'UTF-8') . ".{$partij}<br><br>Met vriendelijke groet,<br>Mozart op Zaterdag"
                : "Beste {$naam},<br><br>Helaas is er voor Mozart op Zaterdag op {$datum} geen plaats voor je beschikbaar. We hopen je bij een volgende activiteit te kunnen verwelkomen.<br><br>Met vriendelijke groet,<br>Mozart op Zaterdag";
            $mailer->send();
            $melding = ($bevestigen ? 'Bevestigingsmail' : 'Afwijzingsmail') . ' verstuurd.';
        }
    }
}

$gekozenActiviteit = null;
foreach ($activiteiten as $activiteit) if ((int) $activiteit['id'] === $activiteitId) $gekozenActiviteit = $activiteit;
$spelers = [];
if ($gekozenActiviteit) {
    $stmt = $pdo->prepare("SELECT d.id, d.voornaam, d.achternaam, d.email, ad.status, ad.instrument_id, ad.partij, i.naam AS instrument FROM activiteit_deelnemers ad JOIN deelnemers d ON d.id = ad.deelnemer_id LEFT JOIN instrumenten i ON i.id = ad.instrument_id WHERE ad.activiteit_id = ? AND ad.status IN ('ja', 'misschien') ORDER BY COALESCE(i.id, 999999), d.achternaam, d.voornaam");
    $stmt->execute([$activiteitId]);
    $spelers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="nl"><head><meta charset="UTF-8"><title>Beschikbaarheid</title><link href="/css/moz.css" rel="stylesheet" type="text/css"><style>
.tabel-scroll{max-height:75vh;overflow:auto}.tabel-scroll th{position:sticky;top:0;z-index:2;background:#fff}.tabel-scroll th:first-child{position:sticky;left:0;z-index:3;background:#fff}.tabel-scroll td:first-child{position:sticky;left:0;z-index:1;background:#fff}.mail-knop{margin:.15em}
</style></head><body><div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1400px"><h3>Beschikbaarheid</h3>
<?php if ($melding !== ''): ?><p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p><?php endif; ?>
<form method="get"><label for="activiteit_id">Activiteit:</label><select class="w3-select" id="activiteit_id" name="activiteit_id" onchange="this.form.submit()" style="max-width:32em;display:inline-block"><?php foreach ($activiteiten as $activiteit): ?><option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>><?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?></option><?php endforeach; ?></select></form>
<?php if ($gekozenActiviteit): ?><p><?= htmlspecialchars($gekozenActiviteit['omschrijving'] ?? '') ?></p><div class="tabel-scroll"><table class="w3-table w3-bordered w3-striped w3-small"><tr><th>Speler</th><th>E-mail</th><th>Status</th><th>Instrument</th><th>Partij</th><th>Acties</th></tr>
<?php foreach ($spelers as $speler): ?><tr><form method="post"><input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>"><input type="hidden" name="deelnemer_id" value="<?= (int) $speler['id'] ?>"><td><?= htmlspecialchars($speler['voornaam'] . ' ' . $speler['achternaam']) ?></td><td><?= htmlspecialchars($speler['email']) ?></td><td><select class="w3-select" name="status"><?php foreach (['ja', 'misschien', 'nee'] as $status): ?><option value="<?= $status ?>" <?= $speler['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></td><td><select class="w3-select" name="instrument_id"><option value="0">(onbekend)</option><?php foreach ($instrumenten as $instrument): ?><option value="<?= (int) $instrument['id'] ?>" <?= (int) $speler['instrument_id'] === (int) $instrument['id'] ? 'selected' : '' ?>><?= htmlspecialchars($instrument['naam']) ?></option><?php endforeach; ?></select></td><td><input class="w3-input" type="text" name="partij" value="<?= htmlspecialchars($speler['partij'] ?? '') ?>" placeholder="bijv. 1" style="width:8em"></td><td><button class="w3-button w3-blue w3-small mail-knop" type="submit" name="actie" value="opslaan">Opslaan</button><button class="w3-button w3-green w3-small mail-knop" type="submit" name="actie" value="bevestigen" formnovalidate onclick="return confirm('Bevestigingsmail versturen?')">Bevestig mail</button><button class="w3-button w3-red w3-small mail-knop" type="submit" name="actie" value="afwijzen" formnovalidate onclick="return confirm('Afwijzingsmail versturen?')">Afwijzingsmail</button></td></form></tr><?php endforeach; ?></table></div><?php else: ?><p>Er zijn geen toekomstige activiteiten.</p><?php endif; ?></div></body></html>
