<?php 
	# PHP ADODB document - made with PHAkt
	# FileName="Connection_php_adodb.htm"
	# Type="ADODB"
	# HTTP="true"
	# DBTYPE="mysql"
	
	$MM_LPPO_HOSTNAME = 'localhost';
	$MM_LPPO_DATABASE = 'mysql:pellegri-8';
	$MM_LPPO_DBTYPE   = preg_replace('/:.*$/', '', $MM_LPPO_DATABASE);
	$MM_LPPO_DATABASE = preg_replace('/^[^:]*?:/', '', $MM_LPPO_DATABASE);
	$MM_LPPO_USERNAME = 'pellegri';
	$MM_LPPO_PASSWORD = 'fbPdiJDk';
	$MM_LPPO_LOCALE = 'NL';
	$MM_LPPO_MSGLOCALE = 'NL';
	$MM_LPPO_CTYPE = 'P';
	$KT_locale = $MM_LPPO_MSGLOCALE;
	$KT_dlocale = $MM_LPPO_LOCALE;
	$KT_serverFormat = '%Y-%m-%d %H:%M:%S';
	$QUB_Caching = 'false';

	$KT_localFormat = $KT_serverFormat;
	
	if (!defined('CONN_DIR')) define('CONN_DIR',dirname(__FILE__));
	require_once(CONN_DIR.'/../adodb/adodb.inc.php');
	$MM_LPPO=&KTNewConnection($MM_LPPO_DBTYPE);

	if($MM_LPPO_DBTYPE == 'access' || $MM_LPPO_DBTYPE == 'odbc'){
		if($MM_LPPO_CTYPE == 'P'){
			$MM_LPPO->PConnect($MM_LPPO_DATABASE, $MM_LPPO_USERNAME,$MM_LPPO_PASSWORD);
		} else $MM_LPPO->Connect($MM_LPPO_DATABASE, $MM_LPPO_USERNAME,$MM_LPPO_PASSWORD);
	} else if (($MM_LPPO_DBTYPE == 'ibase') or ($MM_LPPO_DBTYPE == 'firebird')) {
		if($MM_LPPO_CTYPE == 'P'){
			$MM_LPPO->PConnect($MM_LPPO_HOSTNAME.':'.$MM_LPPO_DATABASE,$MM_LPPO_USERNAME,$MM_LPPO_PASSWORD);
		} else $MM_LPPO->Connect($MM_LPPO_HOSTNAME.':'.$MM_LPPO_DATABASE,$MM_LPPO_USERNAME,$MM_LPPO_PASSWORD);
	}else {
		if($MM_LPPO_CTYPE == 'P'){
			$MM_LPPO->PConnect($MM_LPPO_HOSTNAME,$MM_LPPO_USERNAME,$MM_LPPO_PASSWORD, $MM_LPPO_DATABASE);
		} else $MM_LPPO->Connect($MM_LPPO_HOSTNAME,$MM_LPPO_USERNAME,$MM_LPPO_PASSWORD, $MM_LPPO_DATABASE);
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