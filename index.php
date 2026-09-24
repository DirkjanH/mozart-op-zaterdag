<?php
require_once __DIR__ . '/connections/MozartopZaterdag.php';
require_once __DIR__ . '/includes/bezetting.inc.php';

function activiteitIsVerleden(string $datum): bool
{
    return strtotime($datum) < strtotime('today');
}

// Linkt naar de gegenereerde pagina in de datummap, of toont anders een 'binnenkort'-tekst.
function activiteitInfoZin(string $datumMap): string
{
    if (is_file(__DIR__ . '/' . $datumMap . '/index.php')) {
        return '<a href="/' . htmlspecialchars($datumMap, ENT_QUOTES, 'UTF-8') . '/index.php" target="_blank">Meer info &amp; partijen vind je hier</a>.';
    }

    return 'Meer info &amp; partijen vind je binnenkort hier.';
}

// Zet een eventueel in de omschrijving opgenomen ruwe bezettingsnotatie (bijv. "0202-2200-timp-str") om in woorden.
function omschrijvingInWoorden(?string $omschrijving): string
{
    $omschrijving = (string) $omschrijving;
    return preg_replace_callback(
        '/\d{4}-\d{4}(?:-timp)?-(?:str|\d{5})/i',
        static fn (array $overeenkomst): string => bezettingInWoorden($overeenkomst[0]),
        $omschrijving
    );
}

$maandNamen = [1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];

// Deze data staan al met een handgeschreven toelichting hieronder; overige toekomstige activiteiten uit de database worden er automatisch aan toegevoegd.
$reedsBeschrevenData = ['2026-01-24', '2026-02-28', '2026-03-28', '2026-04-25', '2026-05-23', '2026-09-26', '2026-10-24', '2026-11-21'];
$stmt = $pdo->query('SELECT datum, omschrijving FROM activiteiten WHERE datum >= CURDATE() ORDER BY datum');
$aanvullendeActiviteiten = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), static function (array $activiteit) use ($reedsBeschrevenData): bool {
    return !in_array(date('Y-m-d', strtotime($activiteit['datum'])), $reedsBeschrevenData, true);
}));
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mozart op Zaterdag</title>
    <link href="css/moz.css" rel="stylesheet" type="text/css">
    <script>
        function toonVerbergTekst(elementId) {
            var tekstElement = document.getElementById(elementId);
            if (tekstElement) {
                tekstElement.classList.toggle('zichtbaar');
            }
        }
    </script>
</head>

<body>
    <div class="w3-content w3-mobile w3-white w3-panel">
        <?php include_once 'navigatie.htm'; ?>
        <p><img src="images/Mozart.jpg" alt="Mozart"
                class="w3-display-container w3-left w3-margin-right w3-margin-bottom"
                style="width: 100%; max-width: 250px; ">Er is een nieuw orkest-inititatief in Utrecht:
            <i>Mozart op Zaterdag</i>! Iedere maand komt een orkest van professionals en goede amateurs bijeen op een
            zaterdagochtend om een symfonie en/of een (solo)concert van Mozart te repeteren en meteen uit te voeren.
            Dirigent en initiatiefnemer is
            <a href="https://www.horringa.net/index.php" target="_blank">Dirkjan Horringa</a>. Er hebben inmiddels <a
                href="/vorige_afleveringen.php" target="_blank">vanaf februari 2025 tal van afleveringen</a> plaatsgevonden. Alle keren waren zowel qua muzikaal niveau als qua sfeer een groot succes. De deelnemers waren enthousiast en de muziek klonk prachtig. We hebben veel positieve reacties gekregen van het publiek.
        </p>
        <p>We werken als volgt: binnenkomen vanaf 9:15, repeteren van 10:00 tot 12:30 met een korte pauze. Om 13:00 sluiten
            we af met een concertje van 30-45 minuten.
        </p>
        <h4 class="clear">Voor de komende afleveringen, die allemaal plaatsvinden in de <a href="/marnixzaal.php" target="_blank">Marnixzaal</a>, staan deze stukken op het programma:</h4>
        <ul class="programma">
            <li<?= activiteitIsVerleden('2026-01-24') ? ' class="onzichtbaar"' : '' ?>>
                <b>24 januari:</b> Pianoconcert nr. 23 in A KV 488 voor 1 fluit,
                2 klarinetten, 2 fagotten, 2 hoorns en strijkers. De solisten Annette Middelbeek, Brit van Manen en Yumi
                Toyama spelen ieder een deel. Er is nog plaats voor een altviool. <?= activiteitInfoZin('2026-01-24') ?>
            </li>
            <li<?= activiteitIsVerleden('2026-02-28') ? ' class="onzichtbaar"' : '' ?>>
                <b>28 februari:</b> Symfonie nr. 40 in g klein KV 550 voor 1 fluit,
                2 hobo's, 2 klarinetten, 2 fagotten, 2 hoorns en strijkers. Er is nog plaats voor een tweede viool. <?= activiteitInfoZin('2026-02-28') ?>
            </li>
            <li<?= activiteitIsVerleden('2026-03-28') ? ' class="onzichtbaar"' : '' ?>>
                <b>28 maart:</b> Symfonie nr. 13 in F KV 112 & Hoornconcert nr. 2 in Es KV 417 voor 2 hobo's, 1 fagot, 2 hoorns en strijkers. Hoornist Maarten Theulen treedt op als solist. <?= activiteitInfoZin('2026-03-28') ?>
            </li>
            <li<?= activiteitIsVerleden('2026-04-25') ? ' class="onzichtbaar"' : '' ?>>
                <b>25 april:</b> Symfonie nr. 29 in A KV 201 voor 2 hobo's, 1 fagot, 2 hoorns en strijkers. <?= activiteitInfoZin('2026-04-25') ?>
            </li>
            <li<?= activiteitIsVerleden('2026-05-23') ? ' class="onzichtbaar"' : '' ?>>
                <b>23 mei:</b> Pianoconcert nr. 24 in c klein KV 491 voor 1 fluit,
                2 hobo's, 2 klarinetten, 2 fagotten, 2 hoorns, 2 trompetten, pauken en strijkers. Solist is de pianist Hans-Erik Dijkstra. Er is nog plaats voor een of twee 1e violen en <b>twee trompetten</b>. <?= activiteitInfoZin('2026-05-23') ?>
            </li>
            <li<?= activiteitIsVerleden('2026-09-26') ? ' class="onzichtbaar"' : '' ?>>
                <b>26 september:</b> Concert voor fluit en harp in C KV 299 voor 2 hobo's, 1 fagot, 2 hoorns en strijkers. Solisten zijn: Elisa Bartolomé Gómez, dwarsfluit, en Maria Palma, harp. <?= activiteitInfoZin('2026-09-26') ?> Er zijn nu 27 deelnemers. De bezetting is compleet.
            </li>
            <li<?= activiteitIsVerleden('2026-10-24') ? ' class="onzichtbaar"' : '' ?>>
                <b>24 oktober:</b> “Parijse” Ouverture in Bes KV 311a & “Parijse” Symfonie nr. 31 in D KV 297 voor 2 fluiten,
                2 hobo's, 2 klarinetten, 2 fagotten, 2 hoorns, 2 trompetten, pauken en strijkers. <?= activiteitInfoZin('2026-10-24') ?>
            </li>
            <li<?= activiteitIsVerleden('2026-11-21') ? ' class="onzichtbaar"' : '' ?>>
                <b>21 november:</b> concertaria’s voor sopraan, bas en orkest en het duet <i>Per queste tue manine</i> KV 540b voor 2 fluiten, 2 hobo's, 2 klarinetten, 2 fagotten, 2 hoorns en strijkers. De solisten zijn: Ingrid Nugteren (sopraan) en Mitchell Sandler (bas). <?= activiteitInfoZin('2026-11-21') ?>
            </li>
            <?php foreach ($aanvullendeActiviteiten as $activiteit): ?>
                <?php $tijdstip = strtotime($activiteit['datum']); ?>
                <li<?= activiteitIsVerleden($activiteit['datum']) ? ' class="onzichtbaar"' : '' ?>>
                    <b><?= (int) date('j', $tijdstip) ?> <?= $maandNamen[(int) date('n', $tijdstip)] ?>:</b>
                    <?php if ($activiteit['omschrijving']): ?><?= htmlspecialchars(omschrijvingInWoorden($activiteit['omschrijving']), ENT_QUOTES, 'UTF-8') ?>. <?php endif; ?>
                    <?= activiteitInfoZin(date('Y-m-d', $tijdstip)) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <h4>Meespelen?</h4>
        <p>Heb je belangstelling om eens mee te doen met Mozart op Zaterdag? Het is mogelijk je op te geven voor de
            komende afleveringen, die steeds plaatsvinden op de vierde zaterdag van de maand. We werken in de
            <a href="/marnixzaal.php" target="_blank">Marnixzaal</a> van de vroegere Utrechtse Muziekschool aan het
            Domplein of soms in het <a href="/stadsklooster.php" target="_blank">Stadsklooster</a> in de wijk Lombok.
        </p>
        <h4>Meld je aan met het formulier</h4>
        <p>We hopen dat je deze geweldige muziek met ons wilt spelen! We formeren een bezetting van serieuze en ervaren
            spelers die zich natuurlijk goed voorbereiden. We leveren op tijd (betekende) partijen als PDF's aan.
            <a href="https://mozartopzaterdag.nl/deelnemers_aanmelden.php" target="_blank">In dit formulier kun je aangeven of en wanneer
                je wilt meedoen</a>. Je krijgt dan uiterlijk twee maanden voor de speeldatum bericht of je geplaatst bent.
        </p>
        <p>Voor sommige instrumenten, zoals cello en blazers, geldt dat er vaak meer gegadigden zijn dan plaatsen. We zullen dan een selectie maken op basis van moment van aanmelden en ervaring. Mocht je niet geplaatst worden, dan kom je automatisch op de reservelijst. Als je wel geplaatst wordt en toch moet afzeggen, dan stellen we het op prijs als je voor een vervanger zorgt. Dat geldt uiteraard niet bij last-minute afzegging wegens ziekte of andere onvoorziene omstandigheden. </p>
        <p>Deelname is gratis; wel worden de deelnemers aan het eind om een vrijwillige bijdrage in de kosten van de zaal e.d. gevraagd. T.z.t. hopen we de professionele deelnemers ook wat te kunnen betalen.</p>

    </div>
</body>

</html>