<?php

function mailHalfjaren(DateTimeImmutable $vandaag): array
{
    $jaar = (int) $vandaag->format('Y') + 1;
    return [
        'plaatsing_' . $jaar . '_1' => [
            'label' => 'Plaatsingen 1e helft ' . $jaar,
            'start' => $jaar . '-01-01',
            'einde' => $jaar . '-07-01',
        ],
        'plaatsing_' . $jaar . '_2' => [
            'label' => 'Plaatsingen 2e helft ' . $jaar,
            'start' => $jaar . '-07-01',
            'einde' => ($jaar + 1) . '-01-01',
        ],
    ];
}

function laadHalfjaarMailDeelnemers(PDO $pdo, array $periode): array
{
    $stmt = $pdo->prepare(
        "SELECT d.id, d.voornaam, d.achternaam, d.email,
                a.datum, a.plaats, a.omschrijving, ad.toegelaten, ad.partij, i.naam AS instrument
         FROM activiteit_deelnemers ad
         JOIN deelnemers d ON d.id = ad.deelnemer_id
         JOIN activiteiten a ON a.id = ad.activiteit_id
         LEFT JOIN instrumenten i ON i.id = ad.instrument_id
         WHERE a.datum >= ? AND a.datum < ? AND ad.status IN ('ja', 'misschien')
         ORDER BY d.achternaam, d.voornaam, d.id, a.datum, a.id"
    );
    $stmt->execute([$periode['start'], $periode['einde']]);
    $deelnemers = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rij) {
        $id = (int) $rij['id'];
        if (!isset($deelnemers[$id])) {
            $deelnemers[$id] = [
                'id' => $id,
                'voornaam' => $rij['voornaam'],
                'achternaam' => $rij['achternaam'],
                'email' => $rij['email'],
                'instrument' => '',
                'partij' => null,
                'plaatsingen' => [],
            ];
        }
        $deelnemers[$id]['plaatsingen'][] = $rij;
    }
    foreach ($deelnemers as &$deelnemer) {
        $deelnemer['instrument'] = implode(', ', array_unique(array_filter(
            array_column($deelnemer['plaatsingen'], 'instrument'),
            static fn ($instrument): bool => $instrument !== null && $instrument !== ''
        )));
    }
    unset($deelnemer);
    return array_values($deelnemers);
}

function plaatsingMailOverzicht(array $plaatsingen, bool $html = true): string
{
    if ($plaatsingen === []) {
        return '';
    }
    $rijen = [];
    foreach ($plaatsingen as $plaatsing) {
        $geplaatst = (int) $plaatsing['toegelaten'] === 1;
        $status = $geplaatst ? 'Geplaatst' : 'Afgewezen';
        $partij = trim(($plaatsing['instrument'] ?? '') . ' ' . ($plaatsing['partij'] ?? ''));
        if ($geplaatst && $partij !== '') {
            $status .= ' als ' . $partij;
        }
        $waarden = [
            date('d-m-Y', strtotime($plaatsing['datum'])),
            trim(($plaatsing['omschrijving'] ?? '') . ' - ' . ($plaatsing['plaats'] ?? ''), ' -'),
            $status,
        ];
        if (!$html) {
            $rijen[] = implode(' | ', $waarden);
            continue;
        }
        $cellen = array_map(static fn (string $waarde): string => htmlspecialchars($waarde, ENT_QUOTES, 'UTF-8'), $waarden);
        $kleur = $geplaatst ? '#15803d' : '#b91c1c';
        $stijl = 'padding:8px 10px;border-bottom:1px solid #e5e7eb;';
        $rijen[] = '<tr><td style="' . $stijl . '">' . $cellen[0] . '</td>'
            . '<td style="' . $stijl . '">' . $cellen[1] . '</td>'
            . '<td style="' . $stijl . 'color:' . $kleur . ';font-weight:bold;">' . $cellen[2] . '</td></tr>';
    }
    if (!$html) {
        return implode("\n", $rijen);
    }
    return '<table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">'
        . '<tr><th scope="col" style="padding:8px 10px;text-align:left;">Datum</th>'
        . '<th scope="col" style="padding:8px 10px;text-align:left;">Activiteit</th>'
        . '<th scope="col" style="padding:8px 10px;text-align:left;">Plaatsing</th></tr>'
        . implode('', $rijen) . '</table>';
}

function vulMailTemplate(string $template, array $deelnemer, array $activiteit, bool $html = true): string
{
    $instrument = trim((string) ($deelnemer['instrument'] ?? ''));
    $partij = trim((string) ($deelnemer['partij'] ?? ''));
    $plaats = match ($activiteit['plaats'] ?? '') {
        'Marnixzaal' => 'de Marnixzaal aan het Domplein',
        'Stadsklooster' => 'het Stadsklooster',
        default => $activiteit['plaats'] ?? '',
    };
    $waarden = [
        '{{voornaam}}' => $deelnemer['voornaam'] ?? '',
        '{{achternaam}}' => $deelnemer['achternaam'] ?? '',
        '{{instrument}}' => $instrument,
        '{{partij}}' => $partij,
        '{{instrument_partij}}' => trim($instrument . ' ' . $partij),
        '{{datum}}' => date('d-m-Y', strtotime($activiteit['datum'])),
        '{{plaats}}' => $plaats,
        '{{omschrijving}}' => $activiteit['omschrijving'] ?? '',
        '{{aanmeldlink}}' => 'https://mozartopzaterdag.nl/deelnemers_aanmelden.php?email=' . rawurlencode((string) ($deelnemer['email'] ?? '')),
    ];
    $waarden = array_map(static fn ($waarde): string => htmlspecialchars((string) $waarde, ENT_QUOTES, 'UTF-8'), $waarden);
    // Alleen het zelf opgebouwde overzicht mag HTML bevatten; persoonsgegevens blijven geescapet.
    $overzicht = plaatsingMailOverzicht($deelnemer['plaatsingen'] ?? [], $html);
    $waarden['{{plaatsing}}'] = $html ? $overzicht : htmlspecialchars($overzicht, ENT_QUOTES, 'UTF-8');
    return strtr($template, $waarden);
}

function vulMailTemplateGeneriek(string $template): string
{
    return strtr($template, [
        '{{voornaam}}' => '...',
        '{{achternaam}}' => '',
        '{{instrument}}' => '',
        '{{partij}}' => '',
        '{{instrument_partij}}' => '',
        '{{datum}}' => '',
        '{{plaats}}' => '',
        '{{omschrijving}}' => '',
        '{{aanmeldlink}}' => '',
        '{{plaatsing}}' => '',
    ]);
}

function mailHtmlNaarTekst(string $html): string
{
    $html = preg_replace('/<\/(?:p|tr|table)>|<br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<\/(?:td|th)>/i', ' | ', $html);
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
}
