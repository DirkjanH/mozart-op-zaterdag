<?php
# FileName="Connection_php_mysql.htm"
# Type="MYSQL"
# HTTP="true"
$hostname_LPPO = "localhost";
$database_LPPO = "pellegri-8";
$username_LPPO = "pellegri";
$password_LPPO = "fbPdiJDk";
$LPPO = mysql_pconnect($hostname_LPPO, $username_LPPO, $password_LPPO) or trigger_error(mysql_error(),E_USER_ERROR); 

/* Stel de character set in */
mysql_query("SET NAMES UTF8;");
setlocale(LC_ALL, 'nl_NL');

$rekeningnummer	= 'bankrekening 36.46.55.852';
$organisator 	= 'Stichting La Pellegrina';
$plaats 		= 'Valkenburg ZH';
$afzender 		= 'reserveren@pellegrina.nl';
?>