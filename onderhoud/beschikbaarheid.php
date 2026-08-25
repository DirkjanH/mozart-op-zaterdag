<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../vendor/autoload.php';

$melding = '';
$testMailOntvanger = 'dirkjan@pellegrina.net';
$activiteitId = (int) ($_GET['activiteit_id'] ?? $_POST['activiteit_id'] ?? 0);
$activiteiten = $pdo->query('SELECT id, datum, plaats, omschrijving FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum')->fetchAll(PDO::FETCH_ASSOC);
if ($activiteitId === 0 && $activiteiten !== []) $activiteitId = (int) $activiteiten[0]['id'];
$instrumenten = $pdo->query('SELECT id, naam FROM instrumenten ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$standaardOnderwerp = 'Bevestiging deelname Mozart op Zaterdag';
$standaardAfwijzingsOnderwerp = 'Mozart op Zaterdag - deze keer geen plaats';
$standaardMail = <<<'HTML'
Beste {{voornaam}},<br><br>
Leuk dat je je hebt aangemeld voor Mozart op Zaterdag! We zijn blij om je te kunnen plaatsen als {{instrument}}{{partij_tekst}} voor zaterdag {{datum}} in de {{plaats}} aan het Domplein. We spelen dan {{omschrijving}}.<br><br>
De bezetting vind je op de <a href="https://mozartopzaterdag.nl">website</a>. Je kunt inloggen op de pagina voor deelnemers met <strong>WolfGang</strong> (let op de hoofdletters).<br><br>
Mocht je voor het concert moeten afzeggen, dan stellen we het op prijs als je een vervanger aandraagt.<br><br>
Alle partijen staan ook op de <a href="https://mozartopzaterdag.nl">website</a>.<br><br>
Nog even wat aanvullende opmerkingen over Mozart op Zaterdag:<br>
<ul>
<li>De Marnixzaal ligt aan het Domplein 4, op nog geen kwartier lopen van het station. Parkeren is er niet mogelijk; de dichtstbijzijnde parkeergarage is <a href="https://www.p1.nl/parkeerplaats/parkeergarage-springweg/">Springweg</a>.</li>
<li>Dress code: kleurige vrije-tijdskleren.</li>
<li>Je hoeft geen lessenaar mee te nemen; die zijn aanwezig in de zaal.</li>
<li>Maak vooral reclame voor het korte concertje van dit project.</li>
<li>Voor deelnemers die niet leven van muziek maken of een studenteninkomen kan een financiële bijdrage worden gevraagd.</li>
</ul>
Met enthousiaste groet,<br><br>
Dirkjan Horringa<br><br>
P.S. Mocht je ook in de toekomst weer willen meespelen, <a href="https://forms.gle/49YfM2dSn8AYpzfE8">vul dan dit formulier in</a>.
HTML;
$standaardAfwijzingsMail = 'Beste {{voornaam}},<br><br>Een tijdje terug heb je in het aanmeldingsformulier voor Mozart op Zaterdag aangegeven dat je (misschien) wilde meespelen op {{datum}} in {{omschrijving}}. De belangstelling voor deze aflevering van Mozart op Zaterdag is echter groot, met name bij de blazers en de celli. Helaas kan ik je voor die datum niet plaatsen. Ik hoop je bij een van de volgende afleveringen of in andere projecten weer te zien.<br><br>Hartelijke groet<br><br>Dirkjan Horringa';

if (isset($_POST['actie'], $_POST['deelnemer_id'], $_POST['activiteit_id'])) {
    $deelnemerId = (int) $_POST['deelnemer_id'];
    $activiteitId = (int) $_POST['activiteit_id'];
    $actie = $_POST['actie'];
    if (in_array($actie, ['opslaan_bevestigen', 'bevestigen', 'afwijzen'], true)) {
        $status = in_array($_POST['status'] ?? '', ['ja', 'nee', 'misschien'], true) ? $_POST['status'] : 'misschien';
        $partij = trim($_POST['partij'] ?? '') ?: null;
        $instrumentId = (int) ($_POST['instrument_id'] ?? 0) ?: null;
        $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET instrument_id = ?, partij = ?, status = ? WHERE activiteit_id = ? AND deelnemer_id = ?');
        $stmt->execute([$instrumentId, $partij, $status, $activiteitId, $deelnemerId]);
    }
    if (in_array($actie, ['opslaan_bevestigen', 'bevestigen', 'afwijzen'], true)) {
        $stmt = $pdo->prepare('SELECT d.voornaam, d.achternaam, d.email, a.datum, a.plaats, a.omschrijving, ad.partij, i.naam AS instrument FROM activiteit_deelnemers ad JOIN deelnemers d ON d.id = ad.deelnemer_id JOIN activiteiten a ON a.id = ad.activiteit_id LEFT JOIN instrumenten i ON i.id = ad.instrument_id WHERE ad.activiteit_id = ? AND ad.deelnemer_id = ?');
        $stmt->execute([$activiteitId, $deelnemerId]);
        $speler = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$speler || !filter_var($speler['email'], FILTER_VALIDATE_EMAIL)) {
            $melding = 'Geen geldig e-mailadres voor deze speler.';
        } else {
            $bevestigen = $actie !== 'afwijzen';
            $naam = htmlspecialchars($speler['voornaam'], ENT_QUOTES, 'UTF-8');
            $datum = date('d-m-Y', strtotime($speler['datum']));
            $partij = $speler['partij'] ? ' Je speelt partij ' . htmlspecialchars($speler['partij'], ENT_QUOTES, 'UTF-8') . '.' : '';
            $partijTekst = $speler['partij'] ? ' (partij ' . htmlspecialchars($speler['partij'], ENT_QUOTES, 'UTF-8') . ')' : '';
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isMail();
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom('php@pellegrina.net', 'Mozart op Zaterdag');
            $mailer->addAddress($testMailOntvanger, 'Dirkjan Horringa');
            $mailer->isHTML(true);
            $ingevuldOnderwerp = trim($_POST[$bevestigen ? 'mail_bevestiging_onderwerp' : 'mail_afwijzing_onderwerp'] ?? '');
            $ingevuldeMail = trim($_POST[$bevestigen ? 'mail_bevestiging_tekst' : 'mail_afwijzing_tekst'] ?? '');
            $mailTekst = $ingevuldeMail ?: ($bevestigen ? $standaardMail : $standaardAfwijzingsMail);
            $plaats = $speler['plaats'] === 'Marnixzaal' ? 'Marnixzaal aan het Domplein' : $speler['plaats'];
            $mailTekst = str_replace(
                ['{{voornaam}}', '{{achternaam}}', '{{datum}}', '{{plaats}}', '{{instrument}}', '{{partij_tekst}}', '{{omschrijving}}', '{voornaam}', '{achternaam}', '{datum}', '{plaats}', '{instrument}', '{partij}'],
                [$naam, htmlspecialchars($speler['achternaam'], ENT_QUOTES, 'UTF-8'), $datum, htmlspecialchars($plaats, ENT_QUOTES, 'UTF-8'), htmlspecialchars($speler['instrument'] ?? '', ENT_QUOTES, 'UTF-8'), $partijTekst, htmlspecialchars($speler['omschrijving'] ?? '', ENT_QUOTES, 'UTF-8'), $naam, htmlspecialchars($speler['achternaam'], ENT_QUOTES, 'UTF-8'), $datum, htmlspecialchars($plaats, ENT_QUOTES, 'UTF-8'), htmlspecialchars($speler['instrument'] ?? '', ENT_QUOTES, 'UTF-8'), $partij],
                $mailTekst
            );
            $mailer->Subject = $ingevuldOnderwerp ?: ($bevestigen ? $standaardOnderwerp : $standaardAfwijzingsOnderwerp);
            $mailer->Body = $mailTekst;
            $mailer->send();
            $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET toegelaten = ? WHERE activiteit_id = ? AND deelnemer_id = ?');
            $stmt->execute([$bevestigen ? 1 : 0, $activiteitId, $deelnemerId]);
            $melding = ($bevestigen ? 'Bevestigingsmail' : 'Afwijzingsmail') . ' verstuurd; gegevens en toelating bijgewerkt.';
        }
    }
}

$gekozenActiviteit = null;
foreach ($activiteiten as $activiteit) if ((int) $activiteit['id'] === $activiteitId) $gekozenActiviteit = $activiteit;
$spelers = [];
if ($gekozenActiviteit) {
    $stmt = $pdo->prepare("SELECT d.id, d.voornaam, d.achternaam, d.email, ad.status, ad.toegelaten, ad.instrument_id, ad.partij, i.naam AS instrument FROM activiteit_deelnemers ad JOIN deelnemers d ON d.id = ad.deelnemer_id LEFT JOIN instrumenten i ON i.id = ad.instrument_id WHERE ad.activiteit_id = ? AND ad.status IN ('ja', 'misschien') ORDER BY COALESCE(i.id, 999999), d.achternaam, d.voornaam");
    $stmt->execute([$activiteitId]);
    $spelers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="nl"><head><meta charset="UTF-8"><title>Beschikbaarheid</title><link href="/css/moz.css" rel="stylesheet" type="text/css"><style>
.tabel-scroll{max-height:75vh;overflow:auto}.tabel-scroll th{position:sticky;top:0;z-index:2;background:#fff}.tabel-scroll th:first-child{position:sticky;left:0;z-index:3;background:#fff}.tabel-scroll td:first-child{position:sticky;left:0;z-index:1;background:#fff}.mail-knop{margin:.15em}.toegelaten-vinkje{color:#198754;font-size:1.25em;font-weight:bold;margin-left:.35em}
</style>
 </head><body><div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1400px"><h3>Beschikbaarheid</h3>
<?php if ($melding !== ''): ?><p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p><?php endif; ?>
<form method="get"><label for="activiteit_id">Activiteit:</label><select class="w3-select" id="activiteit_id" name="activiteit_id" onchange="this.form.submit()" style="max-width:32em;display:inline-block"><?php foreach ($activiteiten as $activiteit): ?><option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>><?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?></option><?php endforeach; ?></select></form>
<?php if ($gekozenActiviteit): ?><p><?= htmlspecialchars($gekozenActiviteit['omschrijving'] ?? '') ?></p>
<p>De mailvelden worden per ontvanger bewerkt. Beschikbare invulvelden: <code>{{voornaam}}</code>, <code>{{achternaam}}</code>, <code>{{datum}}</code>, <code>{{plaats}}</code>, <code>{{instrument}}</code>, <code>{{partij_tekst}}</code>, <code>{{omschrijving}}</code>.</p>
<div class="tabel-scroll"><table class="w3-table w3-bordered w3-striped w3-small"><tr><th>Speler</th><th>Status</th><th>Instrument</th><th>Partij</th><th>Acties</th></tr>
<?php foreach ($spelers as $speler): ?><tr><form method="post"><input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>"><input type="hidden" name="deelnemer_id" value="<?= (int) $speler['id'] ?>"><td><?= htmlspecialchars($speler['voornaam'] . ' ' . $speler['achternaam']) ?><?php if ((int) $speler['toegelaten'] === 1): ?><span class="toegelaten-vinkje" title="Toegelaten" aria-label="Toegelaten">&#10003;</span><?php endif; ?></td><td><select class="w3-select" name="status"><?php foreach (['ja', 'misschien', 'nee'] as $status): ?><option value="<?= $status ?>" <?= $speler['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></td><td><select class="w3-select" name="instrument_id"><option value="0">(onbekend)</option><?php foreach ($instrumenten as $instrument): ?><option value="<?= (int) $instrument['id'] ?>" <?= (int) $speler['instrument_id'] === (int) $instrument['id'] ? 'selected' : '' ?>><?= htmlspecialchars($instrument['naam']) ?></option><?php endforeach; ?></select></td><td><input class="w3-input" type="text" name="partij" value="<?= htmlspecialchars($speler['partij'] ?? '') ?>" placeholder="bijv. 1" style="width:8em"></td><td><details><summary>Bevestigingsmail</summary><input class="w3-input" type="text" name="mail_bevestiging_onderwerp" value="<?= htmlspecialchars($standaardOnderwerp) ?>"><textarea class="w3-input" name="mail_bevestiging_tekst" rows="8"><?= htmlspecialchars($standaardMail) ?></textarea></details><details><summary>Afwijzingsmail</summary><input class="w3-input" type="text" name="mail_afwijzing_onderwerp" value="<?= htmlspecialchars($standaardAfwijzingsOnderwerp) ?>"><textarea class="w3-input" name="mail_afwijzing_tekst" rows="8"><?= htmlspecialchars($standaardAfwijzingsMail) ?></textarea></details><button class="w3-button w3-green w3-small mail-knop" type="submit" name="actie" value="opslaan_bevestigen" formnovalidate onclick="return confirm('Bevestigingsmail versturen?')">Sla op &amp; bevestig</button><button class="w3-button w3-red w3-small mail-knop" type="submit" name="actie" value="afwijzen" formnovalidate onclick="return confirm('Afwijzingsmail versturen?')">Wijs af</button></td></form></tr><?php endforeach; ?></table></div><?php else: ?><p>Er zijn geen toekomstige activiteiten.</p><?php endif; ?></div></body></html>
