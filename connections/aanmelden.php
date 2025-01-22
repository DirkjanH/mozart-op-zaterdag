<?php 
	# PHP ADODB document - made with PHAkt
	# FileName="Connection_php_adodb.htm"
	# Type="ADODB"
	# HTTP="true"
	# DBTYPE="mysql"
	
	$aanmelden_HOSTNAME = 'localhost';
	$aanmelden_DATABASE = 'mysql:pellegri';
	$aanmelden_DBTYPE   = preg_replace('/:.*$/', '', $aanmelden_DATABASE);
	$aanmelden_DATABASE = preg_replace('/^[^:]*?:/', '', $aanmelden_DATABASE);
	$aanmelden_USERNAME = 'pellegri';
	$aanmelden_PASSWORD = 'fbPdiJDk';
	$aanmelden_LOCALE = 'NL';
	$aanmelden_MSGLOCALE = 'NL';
	$aanmelden_CTYPE = 'P';
	$KT_locale = $aanmelden_MSGLOCALE;
	$KT_dlocale = $aanmelden_LOCALE;
	$KT_serverFormat = '%Y-%m-%d %H:%M:%S';
	$QUB_Caching = 'false';

	$KT_localFormat = $KT_serverFormat;
	
	if (!defined('CONN_DIR')) define('CONN_DIR',dirname(__FILE__));
	require_once(CONN_DIR.'/../adodb/adodb.inc.php');
	$aanmelden=&KTNewConnection($aanmelden_DBTYPE);

	if($aanmelden_DBTYPE == 'access' || $aanmelden_DBTYPE == 'odbc'){
		if($aanmelden_CTYPE == 'P'){
			$aanmelden->PConnect($aanmelden_DATABASE, $aanmelden_USERNAME,$aanmelden_PASSWORD);
		} else $aanmelden->Connect($aanmelden_DATABASE, $aanmelden_USERNAME,$aanmelden_PASSWORD);
	} else if (($aanmelden_DBTYPE == 'ibase') or ($aanmelden_DBTYPE == 'firebird')) {
		if($aanmelden_CTYPE == 'P'){
			$aanmelden->PConnect($aanmelden_HOSTNAME.':'.$aanmelden_DATABASE,$aanmelden_USERNAME,$aanmelden_PASSWORD);
		} else $aanmelden->Connect($aanmelden_HOSTNAME.':'.$aanmelden_DATABASE,$aanmelden_USERNAME,$aanmelden_PASSWORD);
	}else {
		if($aanmelden_CTYPE == 'P'){
			$aanmelden->PConnect($aanmelden_HOSTNAME,$aanmelden_USERNAME,$aanmelden_PASSWORD, $aanmelden_DATABASE);
		} else $aanmelden->Connect($aanmelden_HOSTNAME,$aanmelden_USERNAME,$aanmelden_PASSWORD, $aanmelden_DATABASE);
   }

	if (!function_exists('updateMagicQuotes')) {
		function updateMagicQuotes($HTTP_VARS){
			if (is_array($HTTP_VARS)) {
				foreach ($HTTP_VARS as $name=>$value) {
					if (!is_array($value)) {
						$HTTP_VARS[$name] = addslashes($value);
					} else {
						foreach ($value as $name1=>$value1) {
							if (!is_array($value1)) {
								$HTTP_VARS[$name1][$value1] = addslashes($value1);
							}
						}
					}
				}
			}
			return $HTTP_VARS;
		}
		
		if (!get_magic_quotes_gpc()) {
			$_GET = updateMagicQuotes($_GET);
			$_POST = updateMagicQuotes($_POST);
			$_COOKIE = updateMagicQuotes($_COOKIE);
		}
	}
	if (!isset($_SERVER['REQUEST_URI']) && isset($_ENV['REQUEST_URI'])) {
		$_SERVER['REQUEST_URI'] = $_ENV['REQUEST_URI'];
	}
	if (!isset($_SERVER['REQUEST_URI'])) {
		$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'].(isset($_SERVER['QUERY_STRING'])?"?".$_SERVER['QUERY_STRING']:"");
	}
?>