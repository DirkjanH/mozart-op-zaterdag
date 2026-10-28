<?php
// Log technische fouten op de server, maar toon geen gevoelige details in de browser.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Beperk misbruik van de beheersessie via scripts en externe websites.
ini_set('session.use_strict_mode', '1');
// Voorkom dat de serversessie (en dus het CSRF-token) tijdens het bewerken al na ~24 minuten inactiviteit verloopt.
ini_set('session.gc_maxlifetime', '14400');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'lifetime' => 14400,
    ]);
    session_start();
}

if (!isset($_SESSION['ingelogd']) || $_SESSION['ingelogd'] !== true) {
    require_once __DIR__ . '/login_redirect.php';
    $aangevraagdePagina = geldigeRedirectNaInloggen($_SERVER['REQUEST_URI'] ?? '');
    $volgendeUrl = '/includes/login.php';
    // De bestemming gaat ook in de login-URL mee, zodat een ander verzoek de sessiewaarde niet kan overschrijven.
    if ($aangevraagdePagina !== null) {
        $_SESSION['redirect_na_inloggen'] = $aangevraagdePagina;
        $volgendeUrl .= '?terug=' . rawurlencode($aangevraagdePagina);
    }
    header('Location: ' . $volgendeUrl);
    exit;
}
