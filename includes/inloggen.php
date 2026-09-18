<?php
// Log technische fouten op de server, maar toon geen gevoelige details in de browser.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Beperk misbruik van de beheersessie via scripts en externe websites.
ini_set('session.use_strict_mode', '1');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['ingelogd']) || $_SESSION['ingelogd'] !== true) {
    $aangevraagdePagina = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $_SESSION['redirect_na_inloggen'] = preg_match('#^/[^\r\n]*$#', $aangevraagdePagina)
        ? $aangevraagdePagina
        : '/';
    header('Location: /includes/login.php');
    exit;
}
