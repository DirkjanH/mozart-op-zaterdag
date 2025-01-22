<?php //Connection statement
require_once('/home/pellegrina.nl/www/ProjectOrkest/includes/aanmelden2009.php');
require_once("/home/pellegrina.nl/www/LP/includes/LPmailer.inc.php");

include "/home/pellegrina.nl/www/LP/login/level3_check.php";

$actiedatum = '2008-03-01';

/* echo '<pre>';
print_r($_POST);
echo '</pre>';
 */
if (empty($_GET['DlnmrId']) or $_GET['DlnmrId'] == "") $id = -1; else $id = $_GET['DlnmrId'];

if ((isset($_POST["update"])) && ($_POST["update"] == "Update aanmelding")) {
  $updateSQL = sprintf("UPDATE project_aanmelding SET storting_fonds=%s, donatie=%s, aanbetaling=%s, aangenomen=%s, 
  afgewezen=%s, info_korting=%s, korting=%s, extra=%s, aanbet_bedrag=%s, PayPal=%s, cursusgeld=%s WHERE InschId=%s",
                       GetSQLValueString(isset($_POST['storting_fonds']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString($_POST['donatie'], "int"),
                       GetSQLValueString(isset($_POST['aanbetaling']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString(isset($_POST['aangenomen']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString(isset($_POST['afgewezen']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString($_POST['info_korting'], "int"),
                       GetSQLValueString($_POST['korting'], "int"),
                       GetSQLValueString($_POST['extra'], "int"),
                       GetSQLValueString($_POST['aanbet_bedrag'], "int"),
                       GetSQLValueString(isset($_POST['PayPal']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString($_POST['cursusgeld'], "int"),
                       GetSQLValueString($_POST['InschId'], "int"));

/* echo '<pre>';
print_r($updateSQL);
echo '</pre>';
 */
  $Result1 = $aanmelden->Execute($updateSQL) or die($aanmelden->ErrorMsg());
} // update

//echo "Update: $Result1<br>";

if ((isset($_POST["bevestig"])) && ($_POST["bevestig"] == "Bevestig aanmelding")) {
  $updateSQL = sprintf("UPDATE project_aanmelding SET storting_fonds=%s, donatie=%s, aanbetaling=%s, aangenomen=%s, voorl_bev=CURDATE(), info_korting=%s, korting=%s, extra=%s, aanbet_bedrag=%s, PayPal=%s, cursusgeld=%s WHERE InschId=%s",
                       GetSQLValueString(isset($_POST['storting_fonds']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString($_POST['donatie'], "int"),
                       GetSQLValueString(isset($_POST['aanbetaling']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString(isset($_POST['aangenomen']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString($_POST['info_korting'], "int"),
                       GetSQLValueString($_POST['korting'], "int"),
                       GetSQLValueString($_POST['extra'], "int"),
                       GetSQLValueString($_POST['aanbet_bedrag'], "int"),
                       GetSQLValueString(isset($_POST['PayPal']) ? "true" : "", "defined","1","0"),
                       GetSQLValueString($_POST['cursusgeld'], "int"),
                       GetSQLValueString($_POST['InschId'], "int"));

  $Result1 = $aanmelden->Execute($updateSQL) or die($aanmelden->ErrorMsg());

// echo "Bevestig: $Result1<br>";

// lees de tekst-file

if ($actiedatum >= date('c')) {
//	echo 'voor of op 1 maart<br>';
	if ($_POST['taal'] == "NL") $mail_text_file = "../bevestiging/voorl. bev. NL_voor_1-3.htm";
	else $mail_text_file = "../bevestiging/voorl. bev. EN_voor_1-3.htm";
	}
else {
//	echo 'na 1 maart<br>';
	if ($_POST['taal'] == "NL") $mail_text_file = "../bevestiging/voorl. bev. NL_na_1-3.htm";
	else $mail_text_file = "../bevestiging/voorl. bev. EN_na_1-3.htm";
}
$fh = fopen($mail_text_file, 'r');
$mail_text = fread($fh, filesize($mail_text_file));
fclose($fh);
$mail_text = str_replace("{voornaam}", $_POST['voornaam'], $mail_text);
$mail_text = str_replace("{aanbet_bedrag}", $_POST['aanbet_bedrag'], $mail_text);
$mail_text = str_replace("{cursusgeld}", $_POST['cursusgeld'], $mail_text);
$mail_text = str_replace("{wensen}", stripslashes($_POST['wensen']), $mail_text);
$mail_text = str_replace("{donatie}", stripslashes($_POST['donatietxt']), $mail_text);
if (isset($_POST['opmerking']) and $_POST['opmerking'] != "") {
	$_POST['opmerking'] = stripslashes($_POST['opmerking']);
	$opmerking = "<p>{$_POST['opmerking']}</p>";
	}
else $opmerking = "";
$mail_text = str_replace("{opmerking}", $opmerking, $mail_text);

// echo "De mail-tekst is: {$mail_text}<br><br>";

// stuur een mail
$mail = new LPmailer();

$mail->AddAddress($_POST['email'], stripslashes($_POST['naam']));
if ($_POST['taal'] == "NL") $mail->Subject = "La Pellegrina bevestiging";
else $mail->Subject = "La Pellegrina confirmation";
$mail->From    = "info@pellegrina.net";
$mail->AddCC("info@pellegrina.net", "La Pellegrina PHP mailer");
$mail->Body    = $mail_text;

$mail->AltBody = strip_tags($mail_text);
if (!$mail->Send())
		{
			echo "Bericht kon niet verzonden worden.<br>";
			echo "Mailer Error: " . $mail->ErrorInfo;
			exit;
		}
		
		echo "<p class=\"klein\">Bericht verzonden.</p>";

} // update & verzend voorlopige bevestiging 

// begin Recordset
$colname__inschrijving = '-1';
if (isset($_GET['DlnmrId']) and !(isset($_POST["Leegmaken"]) and ($_POST['Leegmaken'] == "Leegmaken")))
	$colname__inschrijving = $_GET['DlnmrId'];
$query_inschrijving = "SELECT toehoorder, eenpersoons, kamperen, aangenomen, afgewezen, aanbet_bedrag, info_korting, voorl_bev, storting_fonds, donatie, PayPal, korting, extra, DATEDIFF(datum_inschr, \"{$actiedatum}\") AS tijdig, CursusId_FK, DlnmrId_FK, InschId FROM project_aanmelding WHERE DlnmrId_FK = {$colname__inschrijving} AND CursusId_FK > {$cursus_offset} AND CursusId_FK <= ({$aantal_cursussen} + {$cursus_offset}) ORDER BY CursusId_FK ASC";

// echo 'query_inschrijving: ' . $query_inschrijving . "<br>\n";

$inschrijving = $aanmelden->SelectLimit($query_inschrijving) or die($aanmelden->ErrorMsg());
$totalRows_inschrijving = $inschrijving->RecordCount();
// end Recordset

// begin Recordset Cursussen
$query_cursussen = "SELECT CursusId, cursusnaam_nl, cursusnaam_en, YEAR(datum_begin) as jaar FROM cursus WHERE CursusId > {$cursus_offset} ORDER BY CursusId ASC";

// echo 'query_cursussen : ' . $query_cursussen . "<br>\n";

$cursussen = $aanmelden->SelectLimit($query_cursussen) or die($aanmelden->ErrorMsg());
$totalRows_cursussen = $cursussen->RecordCount();

$cursussen->MoveFirst();
While(!$cursussen->EOF) {
	$cursusnaam[$cursussen->Fields('CursusId')][NL] = $cursussen->Fields('cursusnaam_nl').' '.$cursussen->Fields('jaar');
	$cursusnaam[$cursussen->Fields('CursusId')][EN] = $cursussen->Fields('cursusnaam_en').' '.$cursussen->Fields('jaar');
	$cursussen->MoveNext();
	}
// end Recordset Cursussen

// begin Recordset 'dlnmr' voor deelnemersnaam
$colname__dlnmr = '-1';
if (isset($_GET['DlnmrId'])) {
  $colname__dlnmr = $_GET['DlnmrId'];
}
$query_dlnmr = sprintf("SELECT naam, voornaam, taal, email, oost, student, (YEAR(CURDATE())-YEAR(geboortedatum))
 - (RIGHT(CURDATE(),5)<RIGHT(geboortedatum,5)) AS leeftijd FROM dlnmr WHERE DlnmrId = %s", GetSQLValueString($colname__dlnmr, "int"));

// echo 'query_dlnmr : ' . $query_dlnmr . "<br>\n";

$dlnmr = $aanmelden->SelectLimit($query_dlnmr) or die($aanmelden->ErrorMsg());
// end Recordset

function cursusgeld($ins) {

// tarievenlijst
$cursusprijs  = 695;
$student      = 445;
$oost	      = 425;
$ooststud     = 230;
$toehoorder   = 375;
$eenpers      = 180;
$tijdig       =  45;
$korting2     = 100;
$kamp	      =  50;
$paypal		  =  10;

// bereken het cursusgeld

$cursusgeld = 0;
$wensenNL = "";
$wensenEN = "";

if ($ins['student'])
		if ($ins['oost']) {
			$cursusgeld = $ooststud;
			$wensenNL .= "deelname als Oost-europese student/jongere aan cursus ???";
			$wensenEN .= "participation as Eastern European student/young participant in course ???"; }
		else {
			$cursusgeld = $student;
			$wensenNL .= "deelname als student/jongere aan cursus ???";
			$wensenEN .= "participation as student/young participant in course ???"; }
elseif ($ins['toehoorder']) {
		$cursusgeld = $toehoorder;
		$wensenNL .= "deelname als toehoorder aan cursus ???";
		$wensenEN .= "participation as auditor in course ???"; }
else		
	if ($ins['oost']) {
			$cursusgeld = $oost;
			$wensenNL .= "deelname als Oost-Europese deelnemer aan cursus ???";
			$wensenEN .= "participation as Eastern European participant in course ???"; }
		else {
			$cursusgeld = $cursusprijs;
			$wensenNL .= "deelname aan cursus ???";
			$wensenEN .= "participation in course ???"; }
if ($ins['eenpersoons']) {
	$cursusgeld += $eenpers;
	$wensenNL .= ", plus supplement voor eenpersoons kamer";
	$wensenEN .= ", plus supplement for single room"; }
if ($ins['kamperen']) {
	$cursusgeld -= $kamp;
	$wensenNL .= ", minus korting voor kamperen";
	$wensenEN .= ", minus reduction for camping"; }

// supplement voor betaling met PayPal:
if (isset($ins['PayPal']) AND $ins['PayPal'] == '1') {
	$cursusgeld += $paypal; 
	$wensenNL .= ", plus supplement voor betaling via PayPal";
	$wensenEN .= ", plus supplement for payment with PayPal"; }

// korting voor tijdig inschrijven:
if (isset($ins['tijdig']) AND $ins['tijdig'] <= 0 AND $ins['toehoorder'] != 1) {
	$cursusgeld -= ($tijdig);
	$wensenNL .= ", minus korting voor tijdige aanmelding";
	$wensenEN .= ", minus reduction for timely registration"; }

// korting voor het volgen van beide cursussen:
if ($GLOBALS['totalRows_inschrijving'] > 1) {
	$cursusgeld -= ($korting2 / $GLOBALS['totalRows_inschrijving']);
	$wensenNL .= ", minus korting voor deelname aan meer dan één cursus";
	$wensenEN .= ", minus reduction for participation in more than one course"; }

// extra toegekende korting:
if (isset($ins['korting']) AND $ins['korting'] > 0) {
	$cursusgeld -= ($ins['korting']);
	$wensenNL .= ", minus een extra toegekende korting van &#8364;&nbsp;{$ins['korting']}";
	$wensenEN .= ", minus an additionally granted reduction of EUR&nbsp;{$ins['korting']}"; }

// extra toegekende korting:
if (isset($ins['extra']) AND $ins['extra'] > 0) {
	$cursusgeld += ($ins['extra']);
	$wensenNL .= ", plus extra cursusgeld wegens speciale afspraak van &#8364;&nbsp;{$ins['extra']}";
	$wensenEN .= ", plus an additional fee for special requirements of EUR&nbsp;{$ins['extra']}"; }

$wensenNL = str_replace('???', "<b>{$GLOBALS['cursusnaam'][$ins['CursusId_FK']][NL]}</b>", $wensenNL);
$wensenEN = str_replace('???', "<b>{$GLOBALS['cursusnaam'][$ins['CursusId_FK']][EN]}</b>", $wensenEN);

// bedankje donatie:
if (isset($ins['storting_fonds']) AND $ins['storting_fonds'] > 0) {
	if ($ins['donatie'] != "" AND $ins['donatie'] > 0) {
		$donatieNL = "Hartelijk dank voor je toegezegde donatie van &#8364;&nbsp;{$ins['donatie']}
		voor het kortingsfonds.";
		$donatieEN = "Many thanks for your pledged contribution of EUR&nbsp;{$ins['donatie']} 
		into the reduction fund."; }
	else {
		$donatieNL = "Hartelijk dank voor je toegezegde donatie voor het kortingsfonds.";
		$donatieEN = "Many thanks for your pledged contribution into the reduction fund."; 
		}	
}
else
	{
	$donatieNL = "Mocht je alsnog hiervoor wat willen schenken, dan kan dat door overmaking	op onze girorekening 1544510 onder vermelding van 'kortingfonds'.";
	$donatieEN = "If you feel like donating to this fund, please include it in your next payment, mentioning 'reduction fund'.";
	}

$cursus[prijs] = $cursusgeld;
if ($ins['taal'] == 'NL') {
	$cursus[wensen] = $wensenNL;
	$cursus[donatie] = $donatieNL;
	}
else {
	$cursus[wensen] = $wensenEN;
	$cursus[donatie] = $donatieEN;
	}
return $cursus;

} // function cursusgeld

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<?php //PHP ADODB document - made with PHAkt 3.5.1?>
<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
<!--
function ConfirmMsg() {
  if (self.document.update.voorl_bev.checked)
  {document.MM_returnValue = confirm('Deze gegevens zijn al bevestigd. Wil je nog een bevestiging sturen?');}
  else if (self.document.update.aanbet_bedrag.value < 90)
  {document.MM_returnValue = confirm('Heb je het inschrijfgeld correct ingevuld? Het lijkt te laag');}
}
// -->
</SCRIPT>
<html>
<head>
<title>Update financieel</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../css/pellegrina_stijlen.css" type="text/css">
</head>
<body>
<table width="600" border="0" align="left">
  <tr>
    <td colspan="4"><form id="zoek" name="zoek" method="get" action="<?php echo $editFormAction; ?>">
        Id:
        <input name="DlnmrId" type="text" value="<?php if (isset($_GET['DlnmrId'])) 
				echo $_GET['DlnmrId']; ?>" size="5" />
        <input type="submit" name="Submit" value="Zoek">
      </form></td>
  </tr>
  <?php 
if ($totalRows_inschrijving > 1) {
		echo "<tr><td colspan=\"3\">";
		echo "<p><b>Kies één van de volgende inschrijvingen:</b></p>";	
		echo "<form action=\"{$editFormAction}\" method=\"get\" name=\"inschrijving\" id=\"inschrijving\"> \n <select name=\"cursus\" size=\"{$totalRows_inschrijving}\" >";
      while(!$inschrijving->EOF){
			echo "<option value=\"{$inschrijving->Fields('CursusId_FK')}\"";
			if (!(strcmp($inschrijving->Fields('CursusId_FK'), $_GET['cursus']))) {echo "SELECTED";}
			echo '>' . $cursusnaam[$inschrijving->Fields('CursusId_FK')][NL];
			$inschrijving->MoveNext();
			}
		echo "</option>\n</select>";
		echo '<input name="DlnmrId" type="hidden" value="';
		if (isset($_GET['DlnmrId'])) echo $_GET['DlnmrId'] . '" />';
		echo '<input type="submit" name="Submit" value="Zoek">';
		echo '</form></td></tr>';

  		$inschrijving->MoveFirst();
		while(!$inschrijving->EOF AND ($inschrijving->Fields('CursusId_FK') != $_GET['cursus'])) 
			$inschrijving->MoveNext();
	} 
?>
  <tr>
    <td height="50"><h2> Naam:</h2></td>
    <td><h2><?php echo $dlnmr->Fields('naam'); ?></h2></td>
    <td><h2> Taal:</h2></td>
    <td><h2><?php echo $dlnmr->Fields('taal'); ?></h2></td>
  </tr>
  <form action="<?php echo $editFormAction; ?>" method="POST" name="update" id="update">
    <tr>
      <td height="60" colspan="4"><?php if ($inschrijving->Fields('CursusId_FK') != "") echo "Inschrijving nr. 
			<input name=\"InschId\" type=\"text\" DISABLED value=\"{$inschrijving->Fields('InschId')}\"
			size=\"2\">&nbsp;voor cursus:&nbsp;<b>{$cursusnaam[$inschrijving->Fields('CursusId_FK')][NL]}</b>"; ?>&nbsp;
        <input name="CursusId_FK" type="hidden" value="<?php 
			echo $inschrijving->Fields('CursusId_FK'); ?>">
        <input name="InschId" type="hidden" value="<?php 
			echo $inschrijving->Fields('InschId'); ?>">      </td>
    </tr>
    <tr valign="baseline">
      <td nowrap align="right"><div align="right">Storting fonds:</div></td>
      <td><input type="checkbox" name="storting_fonds" value="1" <?php 
				if (!(strcmp($inschrijving->Fields('storting_fonds'),1))) {echo "checked";} ?> /></td>
      <td>Gedoneerd bedrag: </td>
      <td>&#8364;&nbsp;
        <input name="donatie" type="text" id="donatie" value="<?php 
				echo $inschrijving->Fields('donatie'); ?>" size="6" /></td>
    </tr>
    <tr valign="middle">
      <td align="right" valign="top" nowrap><div align="right">Info kortingen: </div></td>
      <td><input name="info_korting" type="checkbox" id="info_korting" value="1" <?php if (!(strcmp($inschrijving->Fields('info_korting'),
				1))) {echo "checked";} ?> /></td>
      <td>Toegekende korting:</td>
      <td>&#8364;&nbsp;
        <input name="korting" type="text" id="korting" value="<?php 
				echo $inschrijving->Fields('korting'); ?>" size="6" /></td>
    </tr>
    <tr valign="middle">
      <td align="right" valign="top" nowrap>&nbsp;</td>
      <td>&nbsp;</td>
      <td>Extra cursusgeld:</td>
      <td>&#8364;&nbsp;
          <input name="extra" type="text" id="extra" value="<?php 
				echo $inschrijving->Fields('extra'); ?>" size="6" /></td>
    </tr>
    <tr valign="baseline">
      <td nowrap align="right"><div align="right">Aangenomen:</div></td>
      <td><input name="aangenomen" type="checkbox" id="aangenomen" value="1" <?php if 
				(!(strcmp($inschrijving->Fields('aangenomen'),1))) {echo "checked";} ?> />
        <label>Leeftijd:&nbsp;
        <input name="geb_datum" type="text" id="geb_datum" size="2" value="<?php echo $dlnmr->Fields('leeftijd'); ?>">
        </label></td>
      <td height="30">Afgewezen:</td>
      <td height="30"><input name="afgewezen" type="checkbox" id="afgewezen" <?php 
				if (!(strcmp($inschrijving->Fields('afgewezen'),1))) {echo "checked";} ?> /></td>
    </tr>
    <tr valign="baseline">
      <td nowrap align="right"><?php // bereken cursusgeld
			$ins['oost'] = $dlnmr->Fields('oost');
			$ins['student'] = $dlnmr->Fields('student');
			$ins['taal'] = $dlnmr->Fields('taal');
			$ins['toehoorder'] = $inschrijving->Fields('toehoorder');
			$ins['eenpersoons'] = $inschrijving->Fields('eenpersoons');
			$ins['kamperen'] = $inschrijving->Fields('kamperen');
			$ins['storting_fonds'] = $inschrijving->Fields('storting_fonds');
			$ins['donatie'] = $inschrijving->Fields('donatie');
			$ins['PayPal'] = $inschrijving->Fields('PayPal');
			$ins['korting'] = $inschrijving->Fields('korting');
			$ins['extra'] = $inschrijving->Fields('extra');
			$ins['tijdig'] = $inschrijving->Fields('tijdig');
			$ins['CursusId_FK'] = $inschrijving->Fields('CursusId_FK');
			$cursus = cursusgeld($ins); ?>
        <div align="right">Cursusgeld:</div></td>
      <td>&#8364;&nbsp;
        <input type="text" name="cursusgeld" value="<?php if (isset($_GET['DlnmrId']) 
				AND $_GET['DlnmrId'] > 0) echo $cursus[prijs]; ?>" size="6" /></td>
      <td align="right" nowrap>Betaald inschrijfgeld:</td>
      <td>&#8364;&nbsp;
        <input type="text" name="aanbet_bedrag" id="aanbet_bedrag" value="<?php 
				echo $inschrijving->Fields('aanbet_bedrag'); ?>" size="6" />
        <INPUT TYPE="button" NAME="inschr" VALUE="€ 150" 
				onClick="self.document.update.aanbet_bedrag.value='150'"></td>
    </tr>
    <tr valign="baseline">
      <td><div align="right">Voorlopige bevestiging: </div></td>
      <td><input type="checkbox" name="voorl_bev" <?php if ($inschrijving->Fields('voorl_bev') != "") {echo "checked"; $voorl_bev = TRUE;} ?> /></td>
      <td>PayPal-betaling:</td>
      <td><input name="PayPal" type="checkbox" id="PayPal" <?php if ($inschrijving->Fields('PayPal') == 1) 
				echo "checked"; ?> value = "1"/></td>
    </tr>
    <tr valign="baseline">
      <td align="right" valign="top" nowrap><div align="right">Wensen</div></td>
      <td colspan="3"><textarea name="wensen" cols="80" rows="3" ><?php 
			if (isset($_GET['DlnmrId']) AND $_GET['DlnmrId'] > 0) echo $cursus[wensen]; ?></textarea></td>
    </tr>
    <tr valign="baseline">
      <td align="right" valign="top" nowrap><div align="right">Donatie </div></td>
      <td colspan="3"><textarea name="donatietxt" cols="80" rows="3" ><?php 
			if (isset($_GET['DlnmrId']) AND $_GET['DlnmrId'] > 0) echo $cursus[donatie]; ?></textarea></td>
    </tr>
    <tr valign="middle">
      <td align="right" valign="top" nowrap><div align="right">Opmerkingen:</div></td>
      <td colspan="3"><textarea name="opmerking" cols="80" rows="3" id="opmerking"><?php 
			if (isset($_POST['opmerking'])) echo stripslashes($_POST['opmerking']); ?></textarea></td>
    </tr>
    <tr valign="baseline">
      <td>&nbsp;</td>
      <td valign="baseline"><div class="rechts">
          <input name="update" type="submit" value="Update aanmelding" />
        </div></td>
      <td valign="baseline">&nbsp;</td>
      <td valign="baseline" onClick="ConfirmMsg();return self.document.MM_returnValue"><input type="submit" name="bevestig" value="Bevestig aanmelding" /></td>
    </tr>
    <input type="hidden" name="voornaam" value="<?php echo $dlnmr->Fields('voornaam'); ?>" />
    <input type="hidden" name="email" value="<?php echo $dlnmr->Fields('email'); ?>" />
    <input type="hidden" name="naam" value="<?php echo $dlnmr->Fields('naam'); ?>" />
    <input type="hidden" name="taal" value="<?php echo $dlnmr->Fields('taal'); ?>" />
    <input type="hidden" name="tijdig" value="<?php echo $inschrijving->Fields('tijdig'); ?>" />
  </form>
</table>
</td>
</tr>
</table>
</body>
</html>
<?php 
if (isset($inschrijving)) $inschrijving->Close();
?>
