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
