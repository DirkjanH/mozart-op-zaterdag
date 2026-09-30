<?php
require_once '../includes/inloggen.php';
require_once '../connections/MozartopZaterdag.php';

$stmt = $pdo->prepare("SELECT d.voornaam, d.achternaam, ad.partij, i.naam AS instrument
    FROM activiteit_deelnemers ad
    JOIN deelnemers d ON d.id = ad.deelnemer_id
    JOIN activiteiten a ON a.id = ad.activiteit_id
    LEFT JOIN instrumenten i ON i.id = ad.instrument_id
    WHERE a.datum = ? AND ad.status <> 'nee' AND ad.toegelaten = 1
    ORDER BY CASE WHEN i.id IS NULL THEN 1 ELSE 0 END,
        CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN COALESCE((SELECT MIN(i2.id) FROM instrumenten i2 WHERE LOWER(TRIM(i2.naam)) LIKE 'trompet%'), i.id) ELSE i.id END,
        CASE WHEN LOWER(TRIM(i.naam)) = 'pauken' THEN 1 ELSE 0 END,
        CASE WHEN LOWER(TRIM(i.naam)) = 'viool' AND LOWER(COALESCE(ad.partij, '')) REGEXP '2' THEN 2 WHEN LOWER(TRIM(i.naam)) = 'viool' THEN 1 ELSE 0 END,
        CASE WHEN LOWER(COALESCE(ad.partij, '')) REGEXP 'concertmeester|aanvoerder' THEN 0 ELSE 1 END,
        CASE WHEN TRIM(COALESCE(ad.partij, '')) = '' THEN 1 ELSE 0 END,
        ad.partij, d.achternaam, d.voornaam");
$stmt->execute(['2026-09-26']);
$deelnemers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concert voor fluit en harp in C KV 299</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
</head>

<body>
    <div class="w3-content w3-white w3-panel">
        <?php require_once '../navigatie.htm'; ?> <h3>Mozart op Zaterdag op 26
            september 2026:</h3>
        <h1>Concert voor fluit en harp in C KV 299</h1>
        <p> Op zaterdagochtend 26 september speelt <i>Mozart op Zaterdag</i>
            zijn <strong>Concert voor fluit en harp in C KV 299</strong>.
            Mozarts Concert voor fluit en harp is een licht, elegant en
            bijzonder melodieus werk. De fluit en harp voeren daarin een
            sprankelend muzikaal gesprek, soms speels, soms juist heel lyrisch,
            terwijl het orkest de verfijnde kleuren subtiel ondersteunt. Vooral
            het langzame middendeel ademt een zachte, intieme schoonheid,
            terwijl de finale uitblinkt in vrolijke vaart en charme. </p>
        <p>Het concert duurt ca. 27 minuten. De bezetting is 2 hobo's, 1 fagot,
            2 hoorns en strijkers. De partijen vind je hieronder. We doen alle
            herhalingen. De tempi worden:</p>
        <ol>
            <li>Allegro: kwart = 120</li>
            <li>Andantino: kwart = ca. 56</li>
            <li>Rondo Allegro: halve noot = 88</li>
        </ol>
        <h2>De solisten</h2>
        <h4>Elisa Bartolomé Gómez (fluit)</h4>
        <div class="w3-clear">
            <div class="w3-left w3-margin-right">
                <img src="Elisa_BG.jpg" alt="Elisa Bartolomé Gómez" width="200">
            </div>
            <p style="margin-top: 0;">Elisa Bartolomé Gómez is een uit Tenerife
                afkomstige fluitiste en piccoloïste, gevestigd in Nederland. In
                2024 behaalde zij haar master aan het Koninklijk Conservatorium
                Den Haag, waar zij studeerde bij Alena Walentin. Elisa werkte
                met diverse professionele orkesten, waaronder Het Balletorkest,
                het Tenerife Symphony Orchestra en het Amsterdam Chamber
                Orchestra. Naast haar klassieke werk is zij actief in de
                hedendaagse muziek en zoekt zij graag de grenzen van haar
                instrument op in samenwerkingen rond improvisatie en
                elektronica.</p>
        </div>
        <h4>Maria Palma (harp)</h4>
        <div class="w3-clear">
            <div class="w3-left w3-margin-right w3-margin-top-0">
                <img src="Maria_P.jpg" alt="Maria Palma" width="200">
            </div>
            <p style="margin-top: 0;">Maria Palma is een Italiaanse harpiste,
                gevestigd in Den Haag, die sinds haar zesde harp speelt. Ze
                werkt graag in uiteenlopende contexten, zoals orkesten,
                soloconcerten en kamermuziek, en trad op in verschillende zalen
                in Italië en Nederland, waaronder het Concertgebouw in
                Amsterdam, het Teatro San Carlo in Napels, het Gaudeamus
                Festival en het Dutch Harp Festival in Utrecht. Ze heeft altijd
                belangstelling gehad voor het combineren van verschillende
                stijlen: naast haar klassieke activiteiten speelt ze in een
                groep voor hedendaagse muziek, in een girl band waarmee ze
                jazz-, pop- en fusionmuziek maakt, en in haar projecten zoekt ze
                graag verbindingen tussen muziek en andere kunstvormen, zoals
                poëzie en beeldende kunst.</p>
        </div>
        <h3>Partijen</h3>
        <ul style="column-count: 3;">
            <li>
                <a href="\2026-09-26\K299.Oboe1.pdf" target="_blank">Hobo 1</a>
            </li>
            <li>
                <a href="\2026-09-26\K299.Oboe2.pdf" target="_blank">Hobo 2</a>
            </li>
            <li>
                <a href="\2026-09-26\K299.Corno1.pdf" target="_blank">Hoorn
                    1</a>
            </li>
            <li>
                <a href="\2026-09-26\K299.Corno2.pdf" target="_blank">Hoorn
                    2</a>
            </li>
            <li>
                <a href="\2026-09-26\Mozart K299 Violin 1 betekend.pdf"
                    target="_blank">Viool 1 (betekend)</a>
            </li>
            <li>
                <a href="\2026-09-26\Mozart K299 Violin 2 betekend.pdf"
                    target="_blank">Viool 2 (betekend)</a>
            </li>
            <li>
                <a href="\2026-09-26\K299.Viola betekend.pdf"
                    target="_blank">Altviool (betekend)</a>
            </li>
            <li>
                <a href="\2026-09-26\K299.Cello betekend.pdf"
                    target="_blank">Cello, contrabas, fagot (betekend)</a>
            </li>
            <li>
                <a href="\2026-09-26\Mozart_KV_299_score.pdf"
                    target="_blank">Partituur</a>
            </li>
        </ul>
        <p class="onzichtbaar">Binnenkort plaatsen we hier de lijst van alle
            deelnemers.</p>
        <div class="">
            <h2>Bezetting</h2>
            <p>Er zijn <?= count($deelnemers) ?> toegelaten deelnemers.</p>
            <table class="w3-table w3-striped w3-bordered" id="deelnemers">
                <thead>
                    <tr>
                        <th>voornaam</th>
                        <th>achternaam</th>
                        <th>instrument</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($deelnemers === []): ?>
                        <tr><td colspan="3">Er zijn nog geen deelnemers toegelaten.</td></tr>
                    <?php else: ?>
                        <?php foreach ($deelnemers as $deelnemer): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $deelnemer['voornaam'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $deelnemer['achternaam'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(trim(($deelnemer['instrument'] ?? '') . ' ' . ($deelnemer['partij'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p>&nbsp;</p>
</body>

</html>