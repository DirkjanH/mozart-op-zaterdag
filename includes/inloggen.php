<?php
// stel php in dat deze fouten weergeeft
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['ingelogd']) || $_SESSION['ingelogd'] !== true) {
    $_SESSION['redirect_na_inloggen'] = $_SERVER['REQUEST_URI'];
    header('Location: /includes/login.php');
    exit; // Stop de scriptuitvoering
}
?>
