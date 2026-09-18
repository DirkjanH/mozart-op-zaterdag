<?php
require_once __DIR__ . '/csrf.php';

// Gebruik voor de beheersessie veilige cookie-instellingen en accepteer geen onbekende sessie-ID's.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
// Voorkom dat de serversessie al na ~24 minuten inactiviteit verloopt.
ini_set('session.gc_maxlifetime', '14400');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'lifetime' => 14400,
]);
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// Cookie-gebaseerd CSRF-token: blijft werken als GET/POST door verschillende servers worden afgehandeld.
$csrfToken = csrfTokenOphalen();

$foutmelding = '';

// Bewaar alleen recente mislukte pogingen om eenvoudig geautomatiseerd raden af te remmen.
$grensTijdstip = time() - 900;
$loginPogingen = array_values(array_filter(
    is_array($_SESSION['login_pogingen'] ?? null) ? $_SESSION['login_pogingen'] : [],
    static fn ($tijdstip): bool => is_int($tijdstip) && $tijdstip >= $grensTijdstip
));
$_SESSION['login_pogingen'] = $loginPogingen;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $ontvangenCsrfToken = $_POST['csrf_token'] ?? null;
    $wachtwoord = $_POST['wachtwoord'] ?? null;

    if (!is_string($ontvangenCsrfToken) || !hash_equals($csrfToken, $ontvangenCsrfToken)) {
        http_response_code(403);
        $foutmelding = 'Ongeldige of verlopen formulieraanvraag. Vernieuw de pagina.';
    } elseif (count($loginPogingen) >= 5) {
        http_response_code(429);
        $foutmelding = 'Te veel mislukte pogingen. Probeer het over vijftien minuten opnieuw.';
    } elseif (!is_string($wachtwoord) || strlen($wachtwoord) > 255) {
        http_response_code(400);
        $foutmelding = 'Ongeldige invoer.';
    } else {
        $correct_wachtwoord = 'WolfGang';

        if (hash_equals($correct_wachtwoord, $wachtwoord)) {
            // Voorkom dat een vooraf bekende sessie-ID na het inloggen bruikbaar blijft.
            session_regenerate_id(true);
            $_SESSION['ingelogd'] = true;
            unset($_SESSION['login_pogingen']);

            // Sta alleen redirects binnen deze website toe.
            $redirect_url = (string) ($_SESSION['redirect_na_inloggen'] ?? '');
            unset($_SESSION['redirect_na_inloggen']);
            if (preg_match('#^/[^\r\n]*$#', $redirect_url)) {
                header('Location: ' . $redirect_url);
                exit;
            }
            header('Location: /index.php');
            exit;
        } else {
            $_SESSION['login_pogingen'][] = time();
            $foutmelding = 'Ongeldige gebruikersnaam of wachtwoord.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
    <title>Login</title>
</head>

<body class="w3-grey">
<div class="w3-content w3-white w3-center w3-panel w3-card-4" style="max-width: 400px; margin-top: 50px; padding: 20px;">
    <h2>Inloggen</h2>

    <?php if ($foutmelding): ?>
        <p style="color: red;"><?php echo $foutmelding; ?></p>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label for="wachtwoord">Wachtwoord:</label>
        <input type="password" id="wachtwoord" name="wachtwoord" maxlength="255" autocomplete="current-password" required><br><br>
        <button type="submit">Inloggen</button>
    </form>
</div>
</body>

</html>