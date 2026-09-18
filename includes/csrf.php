<?php
// Dubbele-cookie CSRF-bescherming: onafhankelijk van serverzijdige sessieopslag,
// zodat het ook werkt als GET en POST door verschillende PHP-workers/servers worden afgehandeld.

function csrfTokenOphalen(): string
{
    $bestaandeWaarde = $_COOKIE['mozart_csrf'] ?? null;
    if (is_string($bestaandeWaarde) && preg_match('/^[a-f0-9]{64}$/', $bestaandeWaarde)) {
        return $bestaandeWaarde;
    }

    $nieuweWaarde = bin2hex(random_bytes(32));
    setcookie('mozart_csrf', $nieuweWaarde, [
        'expires' => time() + 14400,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['mozart_csrf'] = $nieuweWaarde;

    return $nieuweWaarde;
}

function csrfValiderenOfAfwijzen(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $ontvangenToken = $_POST['csrf_token'] ?? null;
    $verwachteToken = $_COOKIE['mozart_csrf'] ?? null;
    if (!is_string($ontvangenToken) || !is_string($verwachteToken) || !hash_equals($verwachteToken, $ontvangenToken)) {
        http_response_code(403);
        exit('Ongeldige of verlopen formulieraanvraag. Vernieuw de pagina en probeer opnieuw.');
    }
}
