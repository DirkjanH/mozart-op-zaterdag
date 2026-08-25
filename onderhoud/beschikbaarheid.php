<?php
require_once __DIR__ . '/../includes/inloggen.php';
require_once __DIR__ . '/../connections/MozartopZaterdag.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Tijdelijke testinstelling: alle berichten gaan naar dit adres.
$melding = '';
$testMailOntvanger = 'info@mozartopzaterdag.nl';
set_time_limit(15);
$activiteitId = (int) ($_GET['activiteit_id'] ?? $_POST['activiteit_id'] ?? 0);
// Toon alleen activiteiten die nog moeten plaatsvinden.
$activiteiten = $pdo->query('SELECT id, datum, plaats, omschrijving FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum')->fetchAll(PDO::FETCH_ASSOC);
if ($activiteitId === 0 && $activiteiten !== []) $activiteitId = (int) $activiteiten[0]['id'];
$instrumenten = $pdo->query('SELECT id, naam FROM instrumenten ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$standaardOnderwerp = 'Bevestiging deelname Mozart op Zaterdag';
$standaardAfwijzingsOnderwerp = 'Mozart op Zaterdag - deze keer geen plaats';
$gmailGebruikersnaam = 'info@mozartopzaterdag.nl';
$gmailGebruikersnaamBestand = __DIR__ . '/../includes/_tst/MOZART_GMAIL_USERNAME.txt';
$gmailAppWachtwoord = '';
$gmailWachtwoordBron = 'niet gevonden';
$gmailBestandBestaat = file_exists($gmailGebruikersnaamBestand);
$gmailBestandLeesbaar = is_readable($gmailGebruikersnaamBestand);
$gmailCredentialRegels = 0;
if ($gmailBestandLeesbaar) {
    $credentialRegels = preg_split('/\r\n|\r|\n/', trim((string) file_get_contents($gmailGebruikersnaamBestand)));
    $gmailCredentialRegels = count(array_filter($credentialRegels, static fn (string $regel): bool => trim($regel) !== ''));
    $ongelabeldeRegels = [];
    foreach ($credentialRegels as $regel) {
        $regel = trim($regel);
        if ($regel === '' || str_starts_with($regel, '#')) {
            continue;
        }
        if (preg_match('/^([^:=]+)\s*[:=]\s*(.*)$/', $regel, $delen)) {
            $naam = strtolower(trim($delen[1]));
            $waarde = trim($delen[2]);
            if (in_array($naam, ['username', 'gebruikersnaam', 'gmail_username', 'mozart_gmail_username'], true)) {
                $gmailGebruikersnaam = $waarde;
            } elseif (in_array($naam, ['password', 'wachtwoord', 'app_password', 'gmail_app_password', 'mozart_gmail_app_password'], true)) {
                $gmailAppWachtwoord = preg_replace('/\s+/', '', $waarde);
                $gmailWachtwoordBron = 'bestand';
            }
        } else {
            $ongelabeldeRegels[] = $regel;
        }
    }
    if ($gmailAppWachtwoord === '' && isset($ongelabeldeRegels[1])) {
        $gmailGebruikersnaam = $ongelabeldeRegels[0];
        $gmailAppWachtwoord = preg_replace('/\s+/', '', $ongelabeldeRegels[1]);
        $gmailWachtwoordBron = 'bestand';
    }
}
$gmailGebruikersnaam = getenv('MOZART_GMAIL_USERNAME') ?: $gmailGebruikersnaam;
$gmailOmgevingsWachtwoord = getenv('MOZART_GMAIL_APP_PASSWORD');
if ($gmailOmgevingsWachtwoord !== false && $gmailOmgevingsWachtwoord !== '') {
    $gmailAppWachtwoord = preg_replace('/\s+/', '', $gmailOmgevingsWachtwoord);
    $gmailWachtwoordBron = 'omgeving';
}
$standaardMail = <<<'HTML'
Beste {{voornaam}},<br><br>
Leuk dat je je hebt aangemeld voor Mozart op Zaterdag! We zijn blij om je te kunnen plaatsen als {{instrument}}{{partij_tekst}} voor zaterdag {{datum}} in de {{plaats}}. We spelen dan {{omschrijving}}.<br><br>
De bezetting vind je op de <a href="https://mozartopzaterdag.nl">website</a>. Je kunt inloggen op de pagina voor deelnemers met <strong>WolfGang</strong> (let op de hoofdletters).<br><br>
Mocht je voor het concert moeten afzeggen, dan stellen we het op prijs als je een vervanger aandraagt.<br><br>
Alle partijen staan ook op de <a href="https://mozartopzaterdag.nl">website</a>.<br><br>
Nog even wat aanvullende opmerkingen over Mozart op Zaterdag:<br>
<ul>
<li>De Marnixzaal ligt aan het Domplein 4, op nog geen kwartier lopen van het station.</li>
<li>Dress code: kleurige vrije-tijdskleren.</li>
<li>Je hoeft geen lessenaar mee te nemen; die zijn aanwezig in de zaal.</li>
</ul>
Met enthousiaste groet,<br><br>
Dirkjan Horringa<br><br>
P.S. Mocht je ook in de toekomst weer willen meespelen, <a href="https://forms.gle/49YfM2dSn8AYpzfE8">vul dan dit formulier in</a>.
HTML;

// Verwerk wijzigingen en verstuur alleen na expliciete keuze een mail.
if (isset($_POST['actie'], $_POST['deelnemer_id'], $_POST['activiteit_id'])) {
    $deelnemerId = (int) $_POST['deelnemer_id'];
    $activiteitId = (int) $_POST['activiteit_id'];
    $actie = $_POST['actie'];
    // Sla instrument, partij en beschikbaarheid op vóór het versturen.
    if (in_array($actie, ['opslaan_bevestigen', 'bevestigen', 'afwijzen'], true)) {
        $status = in_array($_POST['status'] ?? '', ['ja', 'nee', 'misschien'], true) ? $_POST['status'] : 'misschien';
        $partij = trim($_POST['partij'] ?? '') ?: null;
        $instrumentId = (int) ($_POST['instrument_id'] ?? 0) ?: null;
        $status = $actie === 'afwijzen' ? 'nee' : $status;
        $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET instrument_id = ?, partij = ?, status = ? WHERE activiteit_id = ? AND deelnemer_id = ?');
        $stmt->execute([$instrumentId, $partij, $status, $activiteitId, $deelnemerId]);
    }
    // Vul de gekozen mail in en pas de persoonlijke velden toe.
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
            $smtpDebug = [];
            try {
                if ($gmailAppWachtwoord === '') {
                    throw new RuntimeException('Gmail-app-wachtwoord ontbreekt in de configuratie.');
                }
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = 'mailout.one.com';
                $mailer->SMTPAuth = true;
                $mailer->Username = $gmailGebruikersnaam;
                $mailer->Password = $gmailAppWachtwoord;
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->Port = 587;
                $mailer->Timeout = 8;
                $mailer->SMTPKeepAlive = false;
                $mailer->SMTPDebug = 2;
                $mailer->Debugoutput = static function (string $bericht) use (&$smtpDebug): void {
                    $smtpDebug[] = trim($bericht);
                };
                $mailer->CharSet = 'UTF-8';
                $mailer->setFrom($mailer->Username, 'Mozart op Zaterdag');
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
                // Toelating wordt pas vastgelegd nadat de mail is verzonden.
                $stmt = $pdo->prepare('UPDATE activiteit_deelnemers SET toegelaten = ?, afgewezen = ? WHERE activiteit_id = ? AND deelnemer_id = ?');
                $stmt->execute([$bevestigen ? 1 : 0, $bevestigen ? 0 : 1, $activiteitId, $deelnemerId]);
                $melding = ($bevestigen ? 'Bevestigingsmail' : 'Afwijzingsmail') . ' verstuurd; gegevens en toelating bijgewerkt.';
            } catch (PHPMailer\PHPMailer\Exception $e) {
                $melding = 'Mail niet verstuurd: ' . $e->getMessage() . ' SMTP: ' . implode(' | ', $smtpDebug);
            } catch (RuntimeException $e) {
                $melding = 'Mail niet verstuurd: ' . $e->getMessage();
            } catch (Throwable $e) {
                $melding = 'Mail niet verstuurd: ' . $e->getMessage() . ' SMTP: ' . implode(' | ', $smtpDebug);
            }
        }
    }
}

$gekozenActiviteit = null;
foreach ($activiteiten as $activiteit) if ((int) $activiteit['id'] === $activiteitId) $gekozenActiviteit = $activiteit;
// Laat beschikbare spelers zien en sorteer ze volgens de instrumentvolgorde.
$spelers = [];
if ($gekozenActiviteit) {
    $stmt = $pdo->prepare("SELECT d.id, d.voornaam, d.achternaam, d.email, ad.status, ad.toegelaten, ad.afgewezen, COALESCE(ad.instrument_id, di.instrument_id) AS instrument_id, ad.partij, i.naam AS instrument FROM activiteit_deelnemers ad JOIN deelnemers d ON d.id = ad.deelnemer_id LEFT JOIN (SELECT deelnemer_id, MIN(instrument_id) AS instrument_id FROM deelnemer_instrumenten GROUP BY deelnemer_id) di ON di.deelnemer_id = d.id LEFT JOIN instrumenten i ON i.id = COALESCE(ad.instrument_id, di.instrument_id) WHERE ad.activiteit_id = ? ORDER BY CASE WHEN i.id IS NULL THEN 1 ELSE 0 END, i.id, d.achternaam, d.voornaam");
    $stmt->execute([$activiteitId]);
    $spelers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="nl"><head><meta charset="UTF-8"><title>Beschikbaarheid</title><link href="/css/moz.css" rel="stylesheet" type="text/css"><style>
.tabel-scroll{max-height:90vh;overflow:auto}.tabel-scroll th{position:sticky;top:0;z-index:2;background:#fff}.tabel-scroll th:first-child{position:sticky;left:0;z-index:3;background:#fff}.tabel-scroll td:first-child{position:sticky;left:0;z-index:1;background:#fff}.mail-knop{margin:.15em}.toegelaten-vinkje{color:#198754;font-size:1.25em;font-weight:bold;margin-left:.35em}details.mail-editor{display:inline-block;margin:.15em 0;vertical-align:middle}details.mail-editor summary{display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;height:2.2em;min-width:4.5em;padding:0 .6em;border-radius:2px;list-style:none;box-sizing:border-box}details.mail-editor summary::-webkit-details-marker{display:none}.mail-bewerk-groen summary{background:#198754}.mail-bewerk-rood summary{background:#dc3545}details.mail-editor[open] > :not(summary){position:fixed;left:50%;transform:translateX(-50%);z-index:10;width:min(90vw,700px);box-sizing:border-box}details.mail-editor[open] > input{top:12vh;padding:.6em;background:#fff;border:1px solid #777}details.mail-editor[open] > textarea{top:calc(12vh + 3.6em);height:65vh;padding:.6em;background:#fff;border:1px solid #777;resize:vertical}
details.mail-bewerk-groen, details.mail-bewerk-rood { display: inline-block; margin: .15em 0; vertical-align: middle; }
details.mail-bewerk-groen summary, details.mail-bewerk-rood summary { display: inline-flex; align-items: center; justify-content: center; height: 2.2em; min-width: 4.5em; padding: 0.6em; box-sizing: border-box; }
details.mail-bewerk-groen[open] > input, details.mail-bewerk-rood[open] > input { position: fixed; top: 12vh; left: 50%; z-index: 10; width: min(90vw, 700px); transform: translateX(-50%); box-sizing: border-box; padding: .6em; background: #fff; border: 1px solid #777; }
details.mail-bewerk-groen[open] > textarea, details.mail-bewerk-rood[open] > textarea { position: fixed; top: calc(12vh + 3.6em); left: 50%; z-index: 10; width: min(90vw, 700px); height: 65vh; transform: translateX(-50%); box-sizing: border-box; padding: .6em; background: #fff; border: 1px solid #777; resize: vertical; }
td.acties { min-width: 32em; white-space: nowrap; }
td.acties > .mail-knop, td.acties > details { display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; height: 2.2em; padding: 0.6em; box-sizing: border-box; line-height: 1; }
.tabel-scroll tr > td:last-child { min-width: 32em; white-space: nowrap; }
.tabel-scroll tr > td:last-child > .mail-knop, .tabel-scroll tr > td:last-child > details { display: inline-flex; vertical-align: middle; height: 2.2em; padding: 0.6em; box-sizing: border-box; }
</style>
<style>.afgewezen-kruis{color:#dc3545;font-size:1.25em;font-weight:bold;margin-left:.35em}</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.tabel-scroll tr').forEach(function (rij) {
        var status = rij.querySelector('select[name="status"]');
        var naam = rij.querySelector('td:first-child');
        if (status && naam && status.value === 'nee' && !naam.querySelector('.afgewezen-kruis')) {
            var kruis = document.createElement('span');
            kruis.className = 'afgewezen-kruis';
            kruis.title = 'Afgewezen';
            kruis.setAttribute('aria-label', 'Afgewezen');
            kruis.textContent = '✕';
            naam.appendChild(kruis);
        }
    });
});
</script>
 </head><body><div class="w3-content w3-mobile w3-white w3-panel" style="max-width:1400px"><h3>Beschikbaarheid</h3>
<?php if ($melding !== ''): ?><p class="w3-panel w3-pale-green w3-leftbar w3-border-green"><?= htmlspecialchars($melding) ?></p><?php endif; ?>
<form method="get"><label for="activiteit_id">Activiteit:</label><select class="w3-select" id="activiteit_id" name="activiteit_id" onchange="this.form.submit()" style="max-width:32em;display:inline-block"><?php foreach ($activiteiten as $activiteit): ?><option value="<?= (int) $activiteit['id'] ?>" <?= (int) $activiteit['id'] === $activiteitId ? 'selected' : '' ?>><?= htmlspecialchars(date('d-m-Y', strtotime($activiteit['datum'])) . ' - ' . $activiteit['plaats']) ?></option><?php endforeach; ?></select></form>
<?php if ($gekozenActiviteit): ?><p><?= htmlspecialchars($gekozenActiviteit['omschrijving'] ?? '') ?></p>
<div class="tabel-scroll"><table class="w3-table w3-bordered w3-striped w3-small"><tr><th>Speler</th><th>Instrument</th><th>Status</th><th>Partij</th><th>Acties</th></tr>
<?php foreach ($spelers as $speler): ?><tr><form method="post"><input type="hidden" name="activiteit_id" value="<?= $activiteitId ?>"><input type="hidden" name="deelnemer_id" value="<?= (int) $speler['id'] ?>"><td><?= htmlspecialchars($speler['voornaam'] . ' ' . $speler['achternaam']) ?><?php if ((int) $speler['toegelaten'] === 1): ?><span class="toegelaten-vinkje" title="Toegelaten" aria-label="Toegelaten">&#10003;</span><?php endif; ?></td><td><select class="w3-select" name="instrument_id"><option value="0">(onbekend)</option><?php foreach ($instrumenten as $instrument): ?><option value="<?= (int) $instrument['id'] ?>" <?= (int) $speler['instrument_id'] === (int) $instrument['id'] ? 'selected' : '' ?>><?= htmlspecialchars($instrument['naam']) ?></option><?php endforeach; ?></select></td><td><select class="w3-select" name="status"><?php foreach (['ja', 'misschien', 'nee'] as $status): ?><option value="<?= $status ?>" <?= $speler['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></td><td><input class="w3-input" type="text" name="partij" value="<?= htmlspecialchars($speler['partij'] ?? '') ?>" placeholder="bijv. 1" style="width:8em"></td><td><button class="w3-button w3-green w3-small mail-knop" type="submit" name="actie" value="opslaan_bevestigen" formnovalidate onclick="return confirm('Bevestigingsmail versturen?')">Sla op &amp; bevestig</button><details class="mail-bewerk-groen"><summary>Bev.ml</summary><input class="w3-input" type="text" name="mail_bevestiging_onderwerp" value="<?= htmlspecialchars($standaardOnderwerp) ?>"><textarea class="w3-input" name="mail_bevestiging_tekst" rows="8"><?= htmlspecialchars($standaardMail) ?></textarea></details><button class="w3-button w3-red w3-small mail-knop" type="submit" name="actie" value="afwijzen" formnovalidate onclick="return confirm('Afwijzingsmail versturen?')">Wijs af</button><details class="mail-bewerk-rood"><summary>Afw.ml</summary><input class="w3-input" type="text" name="mail_afwijzing_onderwerp" value="<?= htmlspecialchars($standaardAfwijzingsOnderwerp) ?>"><textarea class="w3-input" name="mail_afwijzing_tekst" rows="8"><?= htmlspecialchars($standaardAfwijzingsMail) ?></textarea></details></td></form></tr><?php endforeach; ?></table></div><?php else: ?><p>Er zijn geen toekomstige activiteiten.</p><?php endif; ?></div></body></html>
