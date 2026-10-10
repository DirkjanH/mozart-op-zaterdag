<?php
// Zet een bezettingsnotatie zoals "0202-2200-timp-str" of "0201-2000-timp-56442" om in
// leesbare, in woorden uitgeschreven tekst, bijv. "twee hobo's, een fagot, twee hoorns,
// pauken en strijkers".

function getalInWoorden(int $getal): string
{
    $woorden = ['nul', 'een', 'twee', 'drie', 'vier', 'vijf', 'zes', 'zeven', 'acht', 'negen'];
    return $woorden[$getal] ?? (string) $getal;
}

function bezettingInWoorden(?string $notatie): string
{
    $notatie = trim((string) $notatie);
    if ($notatie === '') {
        return 'geen bezetting opgegeven';
    }
    if (!preg_match('/^(\d{4})-(\d{4})(-timp)?-(str|\d{5})$/i', $notatie, $delen)) {
        // Geen herkende notatie: laat de oorspronkelijke (vrije) tekst ongewijzigd staan.
        return $notatie;
    }

    $hout = array_map('intval', str_split($delen[1]));
    $koper = array_map('intval', str_split($delen[2]));
    $pauken = $delen[3] !== '';
    $strijkersLetterlijk = strcasecmp($delen[4], 'str') === 0;
    $strijkers = $strijkersLetterlijk ? [] : array_map('intval', str_split($delen[4]));

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
        ['fluit', 'hobo', 'klarinet', 'fagot', 'hoorn', 'trompet', 'trombone', 'tuba'],
        [$hout[0], $hout[1], $hout[2], $hout[3], $koper[0], $koper[1], $koper[2], $koper[3]]
    );
    if (!$strijkersLetterlijk) {
        $aantallen += array_combine(
            ['eerste viool', 'tweede viool', 'altviool', 'cello', 'contrabas'],
            [$strijkers[0], $strijkers[1], $strijkers[2], $strijkers[3], $strijkers[4]]
        );
    }

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
    if ($strijkersLetterlijk) {
        $delenInWoorden[] = 'strijkers';
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

function ontleedGewensteBezetting(string $notatie): ?array
{
    $notatie = trim($notatie);
    if ($notatie === '') {
        return [];
    }
    if (!preg_match('/^(\d{4})-(\d{4})(-timp)?-(\d{5})$/i', $notatie, $delen)) {
        return null;
    }

    $hout = array_map('intval', str_split($delen[1]));
    $koper = array_map('intval', str_split($delen[2]));
    $strijkers = array_map('intval', str_split($delen[4]));
    return array_combine(
        ['fluit', 'hobo', 'klarinet', 'fagot', 'hoorn', 'trompet', 'pauken', 'trombone', 'tuba', 'viool 1', 'viool 2', 'altviool', 'cello', 'contrabas'],
        [...$hout, $koper[0], $koper[1], $delen[3] === '' ? 0 : 1, $koper[2], $koper[3], ...$strijkers]
    );
}

function bepaalStemgroep(string $instrument, string $partij): string
{
    $instrument = strtolower(trim($instrument));
    $partij = strtolower(trim($partij));
    if (str_contains($instrument, 'altviool')) {
        return 'altviool';
    }
    if (str_contains($instrument, 'viool')) {
        return preg_match('/\b2\b/', $partij) ? 'viool 2' : 'viool 1';
    }
    foreach (['contrabas', 'cello', 'fluit', 'hobo', 'klarinet', 'fagot', 'hoorn', 'trompet', 'pauken', 'trombone', 'tuba'] as $stemgroep) {
        if (str_contains($instrument, $stemgroep)) {
            return $stemgroep;
        }
    }
    return $instrument;
}

function vergelijkSpelersBinnenStemgroep(array $eerste, array $tweede): int
{
    $stemgroep = bepaalStemgroep((string) ($eerste['instrument'] ?? ''), (string) ($eerste['partij'] ?? ''));
    if (in_array($stemgroep, ['viool 1', 'viool 2', 'altviool', 'cello', 'contrabas'], true)) {
        $eersteIsAanvoerder = preg_match('/concertmeester|aanvoerder/i', (string) ($eerste['partij'] ?? '')) ? 0 : 1;
        $tweedeIsAanvoerder = preg_match('/concertmeester|aanvoerder/i', (string) ($tweede['partij'] ?? '')) ? 0 : 1;
        return [$eersteIsAanvoerder, strtolower(trim((string) $eerste['achternaam'])), strtolower(trim((string) $eerste['voornaam']))]
            <=> [$tweedeIsAanvoerder, strtolower(trim((string) $tweede['achternaam'])), strtolower(trim((string) $tweede['voornaam']))];
    }

    $partijVergelijking = strnatcasecmp((string) ($eerste['partij'] ?? ''), (string) ($tweede['partij'] ?? ''));
    if ($partijVergelijking !== 0) {
        return $partijVergelijking;
    }
    return [strtolower(trim((string) $eerste['achternaam'])), strtolower(trim((string) $eerste['voornaam']))]
        <=> [strtolower(trim((string) $tweede['achternaam'])), strtolower(trim((string) $tweede['voornaam']))];
}

function maakBezettingsRijen(array $deelnemers, array $instrumenten, array $gewensteBezetting): array
{
    $groepen = [];
    foreach ($deelnemers as $deelnemer) {
        $stemgroep = bepaalStemgroep((string) ($deelnemer['instrument'] ?? ''), (string) ($deelnemer['partij'] ?? ''));
        $groepen[$stemgroep][] = $deelnemer;
    }

    $volgorde = [];
    foreach ($instrumenten as $instrument) {
        $instrumentNaam = strtolower(trim((string) $instrument['naam']));
        $stemgroepen = str_contains($instrumentNaam, 'viool') && !str_contains($instrumentNaam, 'altviool')
            ? ['viool 1', 'viool 2']
            : [bepaalStemgroep($instrumentNaam, '')];
        foreach ($stemgroepen as $stemgroep) {
            if ($stemgroep !== '' && !in_array($stemgroep, $volgorde, true)) {
                $volgorde[] = $stemgroep;
            }
        }
    }
    foreach (array_keys($gewensteBezetting + $groepen) as $stemgroep) {
        if (!in_array($stemgroep, $volgorde, true)) {
            $volgorde[] = $stemgroep;
        }
    }

    $rijen = [];
    $strijkers = ['viool 1', 'viool 2', 'altviool', 'cello', 'contrabas'];
    foreach ($volgorde as $stemgroep) {
        $spelers = $groepen[$stemgroep] ?? [];
        usort($spelers, 'vergelijkSpelersBinnenStemgroep');
        $groepRijen = [];
        foreach ($spelers as $speler) {
            $groepRijen[] = ['speler' => $speler, 'stemgroep' => $stemgroep, 'vacature' => false];
        }

        $gewenstAantal = (int) ($gewensteBezetting[$stemgroep] ?? 0);
        $aantalVacatures = max(0, $gewenstAantal - count($spelers));
        if ($aantalVacatures === 0) {
            array_push($rijen, ...$groepRijen);
            continue;
        }

        $vacatureLabels = array_fill(0, $aantalVacatures, $stemgroep);
        if (!in_array($stemgroep, $strijkers, true) && $stemgroep !== 'pauken' && $gewenstAantal > 1) {
            $bezetteNummers = [];
            $spelersZonderNummer = 0;
            foreach ($spelers as $speler) {
                if (preg_match('/\b(\d+)\b/', (string) ($speler['partij'] ?? ''), $nummer)) {
                    $bezetteNummers[(int) $nummer[1]] = true;
                } else {
                    $spelersZonderNummer++;
                }
            }
            $ontbrekendeNummers = array_values(array_filter(
                range(1, $gewenstAantal),
                static fn (int $nummer): bool => !isset($bezetteNummers[$nummer])
            ));
            $ontbrekendeNummers = array_slice($ontbrekendeNummers, min($spelersZonderNummer, count($ontbrekendeNummers)));
            $vacatureLabels = array_map(static fn (int $nummer): string => $stemgroep . ' ' . $nummer, array_slice($ontbrekendeNummers, 0, $aantalVacatures));
        }
        foreach ($vacatureLabels as $label) {
            $groepRijen[] = ['speler' => null, 'stemgroep' => $label, 'vacature' => true];
        }
        if (!in_array($stemgroep, $strijkers, true) && $stemgroep !== 'pauken' && $gewenstAantal > 1) {
            usort($groepRijen, static function (array $eerste, array $tweede): int {
                $eerstePartij = $eerste['vacature'] ? $eerste['stemgroep'] : (string) ($eerste['speler']['partij'] ?? '');
                $tweedePartij = $tweede['vacature'] ? $tweede['stemgroep'] : (string) ($tweede['speler']['partij'] ?? '');
                preg_match('/\b(\d+)\b/', $eerstePartij, $eersteNummer);
                preg_match('/\b(\d+)\b/', $tweedePartij, $tweedeNummer);
                return ((int) ($eersteNummer[1] ?? PHP_INT_MAX)) <=> ((int) ($tweedeNummer[1] ?? PHP_INT_MAX));
            });
        }
        array_push($rijen, ...$groepRijen);
    }

    return $rijen;
}
