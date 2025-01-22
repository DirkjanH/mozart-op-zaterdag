<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Untitled Document</title>
</head>
<?php 
require("../../phpmailer/class.phpmailer.php");

class LPmailer extends PHPMailer {

	var $Mailer = "smtp";         			// set mailer to use SMTP
	var $Host = "localhost";  				// specify main and backup server
	var $SMTPAuth = false;     				// turn on SMTP authentication
	
	var $Body =	"html";                     // set email format to HTML
	var $From = "php@pellegrina.net";
	var $FromName = "La Pellegrina automatic PHP mailer";
	var $CharSet = "utf-8";
	var $Language = "en";
	var $WordWrap = 80;                    // set word wrap to 50 characters
	//var $AddAttachment("/var/tmp/file.tar.gz");         // add attachments
	//var $AddAttachment("/tmp/image.jpg", "new.jpg");    // optional name

}
?>
<body>
</body>
</html>
