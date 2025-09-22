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
        header('Location: pagina.php');
        exit;
    } else {
        $foutmelding = 'Ongeldige gebruikersnaam of wachtwoord.';
    }
}
?>

<!DOCTYPE html>
<html lang="nl">

<head>
    <title>Login</title>
</head>

<body>

    <h2>Inloggen</h2>

    <?php if ($foutmelding): ?>
        <p style="color: red;"><?php echo $foutmelding; ?></p>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label for="wachtwoord">Wachtwoord:</label>
        <input type="password" id="wachtwoord" name="wachtwoord" required><br><br>
        <button type="submit">Inloggen</button>
    </form>

</body>

</html>