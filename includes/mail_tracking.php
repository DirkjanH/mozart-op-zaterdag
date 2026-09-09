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

function voegTrackingPixelToe(string $html, string $token): string
{
    $basisUrl = rtrim(getenv('MOZART_TRACKING_BASE_URL') ?: 'https://www.mozartopzaterdag.nl', '/');
    $pixelUrl = $basisUrl . '/mail_geopend.php?t=' . rawurlencode($token);

    return $html . '<img src="' . htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0" />';
}