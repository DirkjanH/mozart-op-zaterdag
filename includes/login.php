<?php
// Start de sessie op elke pagina waar je wilt inloggen of controleren
session_start();

$foutmelding = '';

// Controleer of het formulier is verzonden
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gebruikersnaam = $_POST['gebruikersnaam'] ?? '';
    $wachtwoord = $_POST['wachtwoord'] ?? '';

    // **Simpele authenticatie logica (VERVANG DIT DOOR ECHTE DATABASE CHECK!)**
    $correct_wachtwoord = 'WolfGang'; // Gebruik in het echt nooit plain text wachtwoorden!

    if ($wachtwoord === $correct_wachtwoord) {
        // Succesvol ingelogd: Zet de sessievariabele
        $_SESSION['ingelogd'] = true;

        // Stuur de gebruiker door naar de beveiligde pagina
        if (isset($_SESSION['redirect_na_inloggen']) AND ) {
            $redirect_url = $_SESSION['redirect_na_inloggen'];
            unset($_SESSION['redirect_na_inloggen']); // Verwijder de redirect variabele na gebruik
            header('Location: ' . $redirect_url);
        } else {
            header('Location: /index.php');
        }
        header('Location: '.$_SESSION['redirect_na_inloggen'] ?? 'Location: /index.php');
        exit;
    } else {
        $foutmelding = 'Ongeldige gebruikersnaam of wachtwoord.';
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
        <label for="wachtwoord">Wachtwoord:</label>
        <input type="password" id="wachtwoord" name="wachtwoord" required><br><br>
        <button type="submit">Inloggen</button>
    </form>
</div>
</body>

</html>