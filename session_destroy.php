<?php
// stel php in dat deze fouten weergeeft
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER["DOCUMENT_ROOT"] . '/vendor/autoload.php';

use function PHP81_BC\strftime;

Kint::$enabled_mode = false; //($_SERVER['REMOTE_ADDR'] === '83.87.12.163');

session_start();
print_r($_SESSION);
session_destroy();
echo "Session destroyed. You have been logged out.";
exit;
// Einde session_destroy.php 
?>