<?php
session_start();

// Vernietig alle sessievariabelen
$_SESSION = array();

// Vernietig de sessie zelf (ook de cookie)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Vernietig de sessie
session_destroy();

// Stuur de gebruiker door naar de loginpagina
header('Location: login.php');
exit;
