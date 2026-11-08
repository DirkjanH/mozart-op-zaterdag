<?php

function aanmeldTurnstileConfiguratie(string $naam): string
{
    $waarde = getenv($naam);
    if ($waarde === false || trim($waarde) === '') {
        $waarde = $_SERVER[$naam] ?? $_ENV[$naam] ?? '';
    }

    return is_string($waarde) ? trim($waarde) : '';
}

function isAanmeldTurnstileResultaatGeldig(array $resultaat, string $hostnaam): bool
{
    $geverifieerdeHostnaam = $resultaat['hostname'] ?? null;

    return ($resultaat['success'] ?? false) === true
        && is_string($geverifieerdeHostnaam)
        && hash_equals(strtolower($hostnaam), strtolower($geverifieerdeHostnaam));
}

function controleerAanmeldTurnstile(mixed $token, string $geheimeSleutel, string $hostnaam, ?string $ipAdres = null): bool
{
    if (!is_string($token) || $token === '' || strlen($token) > 2048) {
        return false;
    }
    if ($geheimeSleutel === '' || $hostnaam === '') {
        throw new RuntimeException('Cloudflare Turnstile is niet volledig geconfigureerd.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('De PHP cURL-extensie is nodig voor Cloudflare Turnstile.');
    }

    $velden = [
        'secret' => $geheimeSleutel,
        'response' => $token,
    ];
    if ($ipAdres !== null && $ipAdres !== '') {
        $velden['remoteip'] = $ipAdres;
    }

    $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($curl === false) {
        throw new RuntimeException('Cloudflare Turnstile-verificatie kon niet worden gestart.');
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($velden),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);

    $antwoord = curl_exec($curl);
    $curlFout = curl_error($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($antwoord === false || $curlFout !== '') {
        throw new RuntimeException('Cloudflare Turnstile-verificatie is niet bereikbaar.');
    }
    if ($httpStatus !== 200) {
        throw new RuntimeException('Cloudflare Turnstile gaf een onverwachte HTTP-status terug.');
    }

    try {
        $resultaat = json_decode($antwoord, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Cloudflare Turnstile gaf een ongeldig antwoord terug.', 0, $e);
    }

    return is_array($resultaat) && isAanmeldTurnstileResultaatGeldig($resultaat, $hostnaam);
}

function herstelAanmeldFormulier(
    array $gegevens,
    array $post,
    array $geldigeInstrumentIds,
    array $activiteitIds
): array {
    foreach (['voornaam', 'achternaam', 'email', 'telefoon', 'postcode', 'plaats', 'muzikale_ervaring', 'opmerkingen'] as $veld) {
        if (is_string($post[$veld] ?? null)) {
            $gegevens[$veld] = $post[$veld];
        }
    }
    if (in_array($post['op_de_hoogte_houden'] ?? null, ['0', '1'], true)) {
        $gegevens['op_de_hoogte_houden'] = (int) $post['op_de_hoogte_houden'];
    }

    $instrumenten = [];
    if (is_array($post['instrumenten'] ?? null)) {
        foreach ($post['instrumenten'] as $instrumentId) {
            if (is_scalar($instrumentId) && ctype_digit((string) $instrumentId)) {
                $instrumentId = (int) $instrumentId;
                if (in_array($instrumentId, $geldigeInstrumentIds, true)) {
                    $instrumenten[] = $instrumentId;
                }
            }
        }
    }

    $statussen = [];
    foreach ($activiteitIds as $activiteitId) {
        $veld = 'status_' . $activiteitId;
        if (in_array($post[$veld] ?? null, ['ja', 'misschien', 'nee'], true)) {
            $statussen[$activiteitId] = $post[$veld];
        }
    }

    return [
        'gegevens' => $gegevens,
        'instrumenten' => array_values(array_unique($instrumenten)),
        'statussen' => $statussen,
    ];
}

function verwerkAanmelding(
    PDO $pdo,
    array $gegevens,
    array $instrumentIds,
    array $beschikbaarheden
): array {
    $pdo->beginTransaction();

    try {
        $genormaliseerdEmail = strtolower(trim((string) $gegevens['email']));
        $lockToevoeging = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT id FROM deelnemers WHERE LOWER(TRIM(email)) = ? ORDER BY id' . $lockToevoeging);
        $stmt->execute([$genormaliseerdEmail]);
        $deelnemerIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($deelnemerIds === []) {
            $stmt = $pdo->prepare(
                'INSERT INTO deelnemers
                    (voornaam, achternaam, email, telefoon, postcode, plaats, muzikale_ervaring, opmerkingen, op_de_hoogte_houden, laatst_gewijzigd_op)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([
                $gegevens['voornaam'],
                $gegevens['achternaam'],
                $gegevens['email'],
                $gegevens['telefoon'],
                $gegevens['postcode'],
                $gegevens['plaats'],
                $gegevens['muzikale_ervaring'],
                $gegevens['opmerkingen'],
                $gegevens['op_de_hoogte_houden'],
            ]);
            $deelnemerId = (int) $pdo->lastInsertId();
        } else {
            $deelnemerId = array_shift($deelnemerIds);
            $stmt = $pdo->prepare(
                'UPDATE deelnemers
                 SET voornaam = ?, achternaam = ?, email = ?, telefoon = ?, postcode = ?, plaats = ?,
                     muzikale_ervaring = ?, opmerkingen = ?, op_de_hoogte_houden = ?, laatst_gewijzigd_op = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $stmt->execute([
                $gegevens['voornaam'],
                $gegevens['achternaam'],
                $gegevens['email'],
                $gegevens['telefoon'],
                $gegevens['postcode'],
                $gegevens['plaats'],
                $gegevens['muzikale_ervaring'],
                $gegevens['opmerkingen'],
                $gegevens['op_de_hoogte_houden'],
                $deelnemerId,
            ]);

            $haalActiviteitenOp = $pdo->prepare('SELECT * FROM activiteit_deelnemers WHERE deelnemer_id = ?');
            $controleActiviteit = $pdo->prepare(
                'SELECT status, toegelaten FROM activiteit_deelnemers WHERE activiteit_id = ? AND deelnemer_id = ?'
            );
            $voegActiviteitToe = $pdo->prepare(
                'INSERT INTO activiteit_deelnemers
                    (activiteit_id, deelnemer_id, status, toegelaten, instrument_id, partij, uitnodiging_verstuurd_op)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $werkToelatingBij = $pdo->prepare(
                'UPDATE activiteit_deelnemers
                 SET status = ?, toegelaten = ?, instrument_id = ?, partij = ?,
                     uitnodiging_verstuurd_op = COALESCE(?, uitnodiging_verstuurd_op)
                 WHERE activiteit_id = ? AND deelnemer_id = ?'
            );
            $verplaatsTracking = $pdo->prepare('UPDATE mail_tracking SET deelnemer_id = ? WHERE deelnemer_id = ?');
            $controleWijziging = $pdo->prepare('SELECT gemarkeerd_op FROM deelnemer_wijzigingen WHERE deelnemer_id = ?');
            $voegWijzigingToe = $pdo->prepare(
                'INSERT INTO deelnemer_wijzigingen (deelnemer_id, gemarkeerd_op) VALUES (?, ?)'
            );
            $werkWijzigingBij = $pdo->prepare(
                'UPDATE deelnemer_wijzigingen SET gemarkeerd_op = ? WHERE deelnemer_id = ?'
            );
            $verwijderWijziging = $pdo->prepare('DELETE FROM deelnemer_wijzigingen WHERE deelnemer_id = ?');
            $verwijderInstrumenten = $pdo->prepare('DELETE FROM deelnemer_instrumenten WHERE deelnemer_id = ?');
            $verwijderDeelnemer = $pdo->prepare('DELETE FROM deelnemers WHERE id = ?');

            foreach ($deelnemerIds as $dubbelDeelnemerId) {
                $haalActiviteitenOp->execute([$dubbelDeelnemerId]);
                foreach ($haalActiviteitenOp->fetchAll(PDO::FETCH_ASSOC) as $activiteit) {
                    $controleActiviteit->execute([(int) $activiteit['activiteit_id'], $deelnemerId]);
                    $bestaandeActiviteit = $controleActiviteit->fetch(PDO::FETCH_ASSOC);
                    if ($bestaandeActiviteit === false) {
                        $voegActiviteitToe->execute([
                            (int) $activiteit['activiteit_id'],
                            $deelnemerId,
                            $activiteit['status'],
                            $activiteit['toegelaten'],
                            $activiteit['instrument_id'],
                            $activiteit['partij'],
                            $activiteit['uitnodiging_verstuurd_op'],
                        ]);
                    } elseif (
                        (int) $activiteit['toegelaten'] === 1
                        && (int) $bestaandeActiviteit['toegelaten'] !== 1
                    ) {
                        $werkToelatingBij->execute([
                            $activiteit['status'],
                            $activiteit['toegelaten'],
                            $activiteit['instrument_id'],
                            $activiteit['partij'],
                            $activiteit['uitnodiging_verstuurd_op'],
                            (int) $activiteit['activiteit_id'],
                            $deelnemerId,
                        ]);
                    }
                }

                $verplaatsTracking->execute([$deelnemerId, $dubbelDeelnemerId]);

                $controleWijziging->execute([$dubbelDeelnemerId]);
                $dubbeleWijziging = $controleWijziging->fetchColumn();
                if ($dubbeleWijziging !== false) {
                    $controleWijziging->execute([$deelnemerId]);
                    $bestaandeWijziging = $controleWijziging->fetchColumn();
                    if ($bestaandeWijziging === false) {
                        $voegWijzigingToe->execute([$deelnemerId, $dubbeleWijziging]);
                    } elseif ((string) $dubbeleWijziging > (string) $bestaandeWijziging) {
                        $werkWijzigingBij->execute([$dubbeleWijziging, $deelnemerId]);
                    }
                }

                $verwijderWijziging->execute([$dubbelDeelnemerId]);
                $verwijderInstrumenten->execute([$dubbelDeelnemerId]);
                $verwijderDeelnemer->execute([$dubbelDeelnemerId]);
            }
        }

        $pdo->prepare('DELETE FROM deelnemer_wijzigingen WHERE deelnemer_id = ?')->execute([$deelnemerId]);
        $pdo->prepare(
            'INSERT INTO deelnemer_wijzigingen (deelnemer_id, gemarkeerd_op) VALUES (?, CURRENT_TIMESTAMP)'
        )->execute([$deelnemerId]);

        $pdo->prepare('DELETE FROM deelnemer_instrumenten WHERE deelnemer_id = ?')->execute([$deelnemerId]);
        $voegInstrumentToe = $pdo->prepare(
            'INSERT INTO deelnemer_instrumenten (deelnemer_id, instrument_id) VALUES (?, ?)'
        );
        foreach (array_unique(array_map('intval', $instrumentIds)) as $instrumentId) {
            $voegInstrumentToe->execute([$deelnemerId, $instrumentId]);
        }

        $waarschuwingen = [];
        $haalHuidigeStatusOp = $pdo->prepare(
            'SELECT ad.status, ad.toegelaten, a.datum, a.plaats, a.omschrijving
             FROM activiteit_deelnemers ad
             JOIN activiteiten a ON a.id = ad.activiteit_id
             WHERE ad.activiteit_id = ? AND ad.deelnemer_id = ?'
        );
        $werkStatusBij = $pdo->prepare(
            'UPDATE activiteit_deelnemers SET status = ? WHERE activiteit_id = ? AND deelnemer_id = ?'
        );
        $voegStatusToe = $pdo->prepare(
            'INSERT INTO activiteit_deelnemers (activiteit_id, deelnemer_id, status) VALUES (?, ?, ?)'
        );
        foreach ($beschikbaarheden as $activiteitId => $status) {
            $activiteitId = (int) $activiteitId;
            $haalHuidigeStatusOp->execute([$activiteitId, $deelnemerId]);
            $huidigeInschrijving = $haalHuidigeStatusOp->fetch(PDO::FETCH_ASSOC);

            if (
                $huidigeInschrijving !== false
                && $huidigeInschrijving['status'] === 'ja'
                && (int) $huidigeInschrijving['toegelaten'] === 1
                && in_array($status, ['misschien', 'nee'], true)
            ) {
                $waarschuwingen[] = [
                    'datum' => $huidigeInschrijving['datum'],
                    'plaats' => $huidigeInschrijving['plaats'],
                    'omschrijving' => $huidigeInschrijving['omschrijving'],
                    'oude_status' => $huidigeInschrijving['status'],
                    'nieuwe_status' => $status,
                ];
            }

            if ($huidigeInschrijving === false) {
                $voegStatusToe->execute([$activiteitId, $deelnemerId, $status]);
            } else {
                $werkStatusBij->execute([$status, $activiteitId, $deelnemerId]);
            }
        }

        $pdo->commit();
        return ['deelnemer_id' => $deelnemerId, 'waarschuwingen' => $waarschuwingen];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
