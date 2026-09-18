<?php

function zorgVoorMailTrackingTabel(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mail_tracking (
            token CHAR(64) NOT NULL PRIMARY KEY,
            activiteit_id INT NOT NULL,
            deelnemer_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            verzonden_op DATETIME NULL,
            geopend_op DATETIME NULL,
            INDEX idx_mail_tracking_activiteit (activiteit_id),
            INDEX idx_mail_tracking_deelnemer (deelnemer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function registreerMailTracking(PDO $pdo, string $token, int $activiteitId, int $deelnemerId, string $email): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO mail_tracking (token, activiteit_id, deelnemer_id, email)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE activiteit_id = VALUES(activiteit_id), deelnemer_id = VALUES(deelnemer_id), email = VALUES(email)'
    );
    $stmt->execute([$token, $activiteitId, $deelnemerId, $email]);
}

function markeerMailVerzonden(PDO $pdo, string $token): void
{
    $stmt = $pdo->prepare('UPDATE mail_tracking SET verzonden_op = NOW() WHERE token = ?');
    $stmt->execute([$token]);
}

function reserveerMailVerzending(PDO $pdo, string $token, int $activiteitId, int $deelnemerId, string $email): bool
{
    $genormaliseerdEmail = strtolower(trim($email));
    $slotNaam = 'mozart-mail-' . substr(hash('sha256', $genormaliseerdEmail), 0, 48);
    $slotStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $slotStmt->execute([$slotNaam]);
    if ((int) $slotStmt->fetchColumn() !== 1) {
        throw new RuntimeException('De controle op dubbele verzending is tijdelijk bezet. Probeer het opnieuw.');
    }

    try {
        // De database-lock voorkomt dat twee gelijktijdige verzoeken hetzelfde adres reserveren.
        $controleStmt = $pdo->prepare(
            'SELECT 1 FROM mail_tracking
             WHERE LOWER(TRIM(email)) = ?
               AND verzonden_op >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             LIMIT 1'
        );
        $controleStmt->execute([$genormaliseerdEmail]);
        if ($controleStmt->fetchColumn()) {
            return false;
        }

        registreerMailTracking($pdo, $token, $activiteitId, $deelnemerId, $genormaliseerdEmail);
        markeerMailVerzonden($pdo, $token);
        return true;
    } finally {
        $vrijgevenStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $vrijgevenStmt->execute([$slotNaam]);
    }
}

function voegTrackingPixelToe(string $html, string $token): string
{
    $basisUrl = rtrim(getenv('MOZART_TRACKING_BASE_URL') ?: 'https://www.mozartopzaterdag.nl', '/');
    $pixelUrl = $basisUrl . '/mail_geopend.php?t=' . rawurlencode($token);

    return $html . '<img src="' . htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0" />';
}