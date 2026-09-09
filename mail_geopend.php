<?php

header('Content-Type: image/gif');
header('Content-Length: 34');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$token = strtolower((string) ($_GET['t'] ?? ''));
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        require_once __DIR__ . '/connections/MozartopZaterdag.php';
        $stmt = $pdo->prepare('UPDATE mail_tracking SET geopend_op = COALESCE(geopend_op, NOW()) WHERE token = ? AND verzonden_op IS NOT NULL');
        $stmt->execute([$token]);
    } catch (Throwable $e) {
        error_log('Mailtracking kon niet worden bijgewerkt: ' . $e->getMessage());
    }
}

echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');