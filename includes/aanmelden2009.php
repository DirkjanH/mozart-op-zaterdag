<?php //Connection statement
require_once('/home/pellegrina.nl/www/ProjectOrkest/connections/aanmelden.php');

//Aditional Functions
require_once('../includes/functions.inc.php');

//Stel utf8 (uitgebreide character set) in:
mysql_query("SET NAMES UTF8");

// zet de localiteit op Nederland
setlocale (LC_ALL, 'nl_NL');

function euro($bedrag) {
	return '&euro;&nbsp;' . number_format($bedrag, 0, ',', '.');
}

function euro2($bedrag) {
	return '&euro;&nbsp;' . number_format($bedrag, 2, ',', '.');
}

// constanten:
$jaar = 2009;

session_start();

// build the form action
$_SERVER['QUERY_STRING'] .= strip_tags(SID);
$editFormAction = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) ? "?" . $_SERVER['QUERY_STRING'] : "");

$project['titel'] = 'Boheemse Kerst 2009';
$project['nr'] = 2; // Boheemse Kerst 2009 is project 2

?>