<?php

function maakAanmeldCaptcha(): string
{
    $vragen = [
        ['vraag' => 'Welke componist schreef De vier jaargetijden?', 'antwoord' => 'vivaldi'],
        ['vraag' => 'Wie componeerde de opera Die Zauberflöte (De toverfluit)?', 'antwoord' => 'mozart'],
        ['vraag' => 'Welke componist schreef Für Elise?', 'antwoord' => 'beethoven'],
        ['vraag' => 'Welk instrument bespeelde Frédéric Chopin vooral?', 'antwoord' => 'piano'],
        ['vraag' => 'Hoeveel strijkers spelen er in een strijkkwartet?', 'antwoord' => '4'],
    ];
    $vraag = $vragen[random_int(0, count($vragen) - 1)];
    $_SESSION['aanmeld_captcha_antwoord'] = $vraag['antwoord'];

    return $vraag['vraag'];
}

function controleerAanmeldCaptcha(mixed $antwoord): bool
{
    $verwachtAntwoord = $_SESSION['aanmeld_captcha_antwoord'] ?? null;
    unset($_SESSION['aanmeld_captcha_antwoord']);

    if (!is_string($verwachtAntwoord) || !is_string($antwoord) || strlen($antwoord) > 80) {
        return false;
    }

    return hash_equals($verwachtAntwoord, strtolower(trim($antwoord)));
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
