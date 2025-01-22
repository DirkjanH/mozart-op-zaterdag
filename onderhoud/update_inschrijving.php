<?php //Connection statement
require_once('/home/pellegrina.nl/www/ProjectOrkest/includes/aanmelden2008.php');
include "../login/level3_check.php";

$cursusnr = 1; // Rybovka

/* echo '<pre>';
print_r($_GET);
print_r($_POST);
echo '</pre>';
 */
if (isset($_POST['InschId']) and ($_POST['InschId'] != "") and isset($_POST["Wissen"]) and ($_POST['Wissen'] == "Wissen")) {

  $deleteSQL = sprintf("DELETE FROM project_aanmelding WHERE InschId=%s",
                       GetSQLValueString($_POST['InschId'], "int"));

  $Result1 = $aanmelden->Execute($deleteSQL) or die($aanmelden->ErrorMsg());
}

if (isset($_POST['InschId']) and ($_POST['InschId'] != "") and isset($_POST["Wijzigen"]) and ($_POST['Wijzigen'] == "Wijzigen")) {
  $updateSQL = sprintf("UPDATE project_aanmelding SET aangenomen=%s, instrumentalist=%s, instrumenten=%s, instr=%s, niveau_i=%s, ervaring_i=%s, stukken_i=%s, groot_ensemble1=%s, groot_ensemble2=%s, zanger=%s, zangstem=%s, niveau_z=%s, ervaring_z=%s, stukken_z=%s, toehoorder=%s, vervoer=%s, busheen=%s, busterug=%s, korting=%s, acc_wens=%s, eenpersoons=%s, kamperen=%s, particulier=%s, storting_fonds=%s, donatie=%s, opmerkingen=%s, voorwaarden=%s, aanbetaling=%s, aanbet_bedrag=%s, cursusgeld=%s, voorl_bev=%s, inzeepdag=%s, afgewezen=%s, datum_inschr=%s, voorl_bev=%s, aanmaning_inschr=%s, DlnmrId_FK=%s, CursusId_FK=%s WHERE InschId=%s",
                       GetSQLValueString($_POST['aangenomen'], "int"),
                       GetSQLValueString($_POST['instrumentalist'], "int"),
                       GetSQLValueString($_POST['instrumenten'], "text"),
                       GetSQLValueString($_POST['instr'], "text"),
                       GetSQLValueString($_POST['niveau_i'], "text"),
                       GetSQLValueString($_POST['ervaring_i'], "text"),
                       GetSQLValueString($_POST['stukken_i'], "text"),
                       GetSQLValueString($_POST['groot_ensemble1'], "int"),
                       GetSQLValueString($_POST['groot_ensemble2'], "int"),
                       GetSQLValueString($_POST['zanger'], "int"),
                       GetSQLValueString($_POST['zangstem'], "text"),
                       GetSQLValueString($_POST['niveau_z'], "text"),
                       GetSQLValueString($_POST['ervaring_z'], "text"),
                       GetSQLValueString($_POST['stukken_z'], "text"),
                       GetSQLValueString($_POST['toehoorder'], "int"),
                       GetSQLValueString($_POST['vervoer'], "text"),
					   GetSQLValueString(isset($_POST['busheen']) ? "true" : "", "defined","'1'","'0'"),
 					   GetSQLValueString(isset($_POST['busterug']) ? "true" : "", "defined","'1'","'0'"),
                       GetSQLValueString($_POST['korting'], "text"),
                       GetSQLValueString($_POST['acc_wens'], "text"),
                       GetSQLValueString($_POST['eenpersoons'], "int"),
                       GetSQLValueString($_POST['kamperen'], "int"),
                       GetSQLValueString($_POST['particulier'], "int"),
                       GetSQLValueString($_POST['storting_fonds'], "int"),
                       GetSQLValueString($_POST['donatie'], "text"),
                       GetSQLValueString($_POST['opmerkingen'], "text"),
                       GetSQLValueString($_POST['voorwaarden'], "int"),
                       GetSQLValueString($_POST['aanbetaling'], "int"),
                       GetSQLValueString($_POST['aanbet_bedrag'], "int"),
                       GetSQLValueString($_POST['cursusgeld'], "int"),
                       GetSQLValueString($_POST['voorl_bev'], "int"),
                       GetSQLValueString($_POST['inzeepdag'], "text"),
                       GetSQLValueString($_POST['afgewezen'], "int"),
                       GetSQLValueString($_POST['datum_inschr'], "date"),
                       GetSQLValueString($_POST['voorl_bev'], "date"),
                       GetSQLValueString($_POST['aanmaning_inschr'], "date"),
                       GetSQLValueString($_POST['DlnmrId_FK'], "int"),
                       GetSQLValueString($_POST['CursusId_FK'], "int"),
                       GetSQLValueString($_POST['InschId'], "int"));

  $Result1 = $aanmelden->Execute($updateSQL) or die($aanmelden->ErrorMsg());
}

// begin Recordset Inschrijvingen van een deelnemer
$colname__inschrijving = '-1';
if (isset($_GET['DlnmrId']) and !(isset($_POST["Leegmaken"]) and ($_POST['Leegmaken'] == "Leegmaken"))) {
	$colname__inschrijving = $_GET['DlnmrId'];
	}
if (isset($_GET['alles']) and $_GET['alles'] == 'on')
	$query_inschrijving = "SELECT * FROM project_aanmelding WHERE DlnmrId_FK = {$colname__inschrijving} 
	ORDER BY CursusId_FK ASC";
else
	$query_inschrijving = "SELECT * FROM project_aanmelding WHERE DlnmrId_FK = {$colname__inschrijving} AND CursusId_FK = {$cursusnr} ORDER BY CursusId_FK ASC";

$inschrijving = $aanmelden->SelectLimit($query_inschrijving) or die($aanmelden->ErrorMsg());
$totalRows_inschrijving = $inschrijving->RecordCount();
// end Recordset Inschrijvingen van een deelnemer

$cursus[$cursusnr] = 'Rybovka 2008';

/* echo '<pre>';
print_r($cursus);
echo '</pre>';
 */
// begin Recordset Dlnmr voor deelnemersnaam
$colname__Dlnmr = '-1';
if (isset($_GET['DlnmrId'])) {
  $colname__Dlnmr = $_GET['DlnmrId'];
}
$query_Dlnmr = sprintf("SELECT naam FROM dlnmr WHERE DlnmrId = %s", GetSQLValueString($colname__Dlnmr, "int"));
$Dlnmr = $aanmelden->SelectLimit($query_Dlnmr) or die($aanmelden->ErrorMsg());
// end Recordset

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<?php //PHP ADODB document - made with PHAkt 3.5.1?>
<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
<!--
function GP_popupConfirmMsg(msg) { //v1.0
  document.MM_returnValue = confirm(msg);
}
// -->
</SCRIPT>
<html>
<head>
<title>Update inschrijvingen</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link href="../bestellen/css/PO.css" rel="stylesheet" type="text/css">
</head>
<body>
<div id="main">
  <table width="600" border="0" align="left">
    <tr>
      <td colspan="3"><form id="zoek" name="zoek" method="get" action="<?php echo $editFormAction; ?>">
        <input name="DlnmrId" type="hidden" value="<?php if (isset($_GET['DlnmrId'])) echo $_GET['DlnmrId']; ?>" size="5" />
        <input type="submit" name="Submit" value="Zoek">
        (alle inschrijvingen van de afgelopen jaren: 
        <input name="alles" type="checkbox" <?php
if (isset($_GET['alles']) and stristr($_GET['alles'], 'on') !== false) echo 'checked'; ?>>
        )
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
			echo '>' . $cursus[$inschrijving->Fields('CursusId_FK')];
			$inschrijving->MoveNext();
			}
		echo "</option>\n</select>";
		echo '<input name="DlnmrId" type="hidden" value="';
		if (isset($_GET['DlnmrId'])) echo $_GET['DlnmrId'] . '" />';
		if (isset($_GET['alles'])) echo "<input name=\"alles\" type=\"hidden\" value=\"on\">";	
		echo '<input type="submit" name="Submit" value="Zoek" />';
		echo '</form></td></tr>';

  		$inschrijving->MoveFirst();
		while(!$inschrijving->EOF AND ($inschrijving->Fields('CursusId_FK') != $_GET['cursus'])) 
			$inschrijving->MoveNext();
	} 
?>
  </table>
  <p>&nbsp;</p>
  <form id="inschrijf" name="inschrijf" method="post" action="<?php echo $editFormAction; ?>">
    <table width="600" border="0" align="left">
      <tr valign="baseline">
        <td width="119" align="right" nowrap><p>Naam:</p></td>
        <td colspan="2"><b><?php echo $Dlnmr->Fields('naam'); ?>&nbsp;</b></td>
        <td width="247">Deelnemersnummer:
          <input name="DlnmrId_FK" type="text" value="<?php echo $inschrijving->Fields('DlnmrId_FK'); ?>" size="3"></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Cursus:</td>
        <td colspan="3"><?php if ($inschrijving->Fields('CursusId_FK') != "") echo "Inschrijving nr. <input name=\"InschId\" type=\"text\" class=\"uit\" value=\"{$inschrijving->Fields('InschId')}\" size=\"2\">&nbsp;<b>{$cursus[$inschrijving->Fields('CursusId_FK')]}</b>"; ?>
          <input name="CursusId_FK" type="hidden" value="<?php echo $inschrijving->Fields('CursusId_FK'); ?>"></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Instrumentalist:</td>
        <td colspan="3"><input type="checkbox" name="instrumentalist" value="1" <?php if (!(strcmp($inschrijving->Fields('instrumentalist'),"1"))) {echo "checked";} ?> /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Instrumenten:</td>
        <td colspan="3"><input type="text" name="instrumenten" value="<?php echo $inschrijving->Fields('instrumenten'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Instr. &amp; zangstem:</td>
        <td colspan="3"><input type="text" name="instr" value="<?php echo $inschrijving->Fields('instr'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Niveau_i:</td>
        <td colspan="3"><input type="text" name="niveau_i" value="<?php echo $inschrijving->Fields('niveau_i'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Ervaring_i:</td>
        <td colspan="3"><input type="text" name="ervaring_i" value="<?php echo $inschrijving->Fields('ervaring_i'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right" valign="top">Stukken_i:</td>
        <td colspan="3"><textarea name="stukken_i" cols="80" rows="5"><?php echo $inschrijving->Fields('stukken_i'); ?></textarea>      </td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Groot_ensemble1:</td>
        <td width="119"><input type="checkbox" name="groot_ensemble1" value="1" <?php if (!(strcmp($inschrijving->Fields('groot_ensemble1'),"1"))) {echo "checked";} ?> /></td>
        <td width="123" align="right" nowrap>Groot_ensemble2:</td>
        <td><input type="checkbox" name="groot_ensemble2" value="1" <?php if (!(strcmp($inschrijving->Fields('groot_ensemble2'),"1"))) {echo "checked";} ?> /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Zanger:</td>
        <td colspan="3"><input type="checkbox" name="zanger" value="1" <?php if (!(strcmp($inschrijving->Fields('zanger'),"1"))) {echo "checked";} ?> /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Zangstem:</td>
        <td colspan="3"><input type="text" name="zangstem" value="<?php echo $inschrijving->Fields('zangstem'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Niveau_z:</td>
        <td colspan="3"><input type="text" name="niveau_z" value="<?php echo $inschrijving->Fields('niveau_z'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Ervaring_z:</td>
        <td colspan="3"><input type="text" name="ervaring_z" value="<?php echo $inschrijving->Fields('ervaring_z'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right" valign="top">Stukken_z:</td>
        <td colspan="3"><textarea name="stukken_z" cols="80" rows="5"><?php echo $inschrijving->Fields('stukken_z'); ?></textarea>      </td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Toehoorder:</td>
        <td colspan="3"><input type="checkbox" name="toehoorder" value="1" <?php if (!(strcmp($inschrijving->Fields('toehoorder'),"1"))) {echo "checked";} ?> /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Vervoer:</td>
        <td colspan="3"><input type="text" name="vervoer" value="<?php echo $inschrijving->Fields('vervoer'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Korting:</td>
        <td colspan="3"><input type="text" name="korting" value="<?php echo $inschrijving->Fields('korting'); ?>" size="32" /></td>
      </tr>
      <tr>
        <td nowrap align="right">Acc_wens:</td>
        <td colspan="3"><textarea name="acc_wens" cols="80" rows="5"><?php echo $inschrijving->Fields('acc_wens'); ?></textarea></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Eenpersoons:</td>
        <td colspan="3"><input type="checkbox" name="eenpersoons" value="1" <?php if (!(strcmp($inschrijving->Fields('eenpersoons'),"1"))) {echo "checked";} ?>" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Kamperen:</td>
        <td colspan="3"><input type="checkbox" name="kamperen" value="1" <?php if (!(strcmp($inschrijving->Fields('kamperen'),"1"))) {echo "checked";} ?>" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Particulier:</td>
        <td colspan="3"><input type="checkbox" name="particulier" value="1" <?php if (!(strcmp($inschrijving->Fields('particulier'),"1"))) {echo "checked";} ?> /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Storting_fonds:</td>
        <td><input type="checkbox" name="storting_fonds" value="1" <?php if (!(strcmp($inschrijving->Fields('storting_fonds'),"1"))) {echo "checked";} ?> /></td>
        <td>Donatie:</td>
        <td>&euro;&nbsp;
          <input type="text" name="donatie" value="<?php echo $inschrijving->Fields('donatie'); ?>" size="6" /></td>
      <input name="storting_fonds" type="hidden" value="<?php if ($inschrijving->Fields('donatie') > 0) echo 1; ?>"></tr>
      <tr valign="baseline">
        <td nowrap align="right" valign="top">Opmerkingen:</td>
        <td colspan="3"><textarea name="opmerkingen" cols="80" rows="5"><?php echo $inschrijving->Fields('opmerkingen'); ?></textarea>      </td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Aanbetaald bedr.:</td>
        <td>&euro;&nbsp;
          <input type="text" name="aanbet_bedrag" value="<?php echo $inschrijving->Fields('aanbet_bedrag'); ?>" size="5" /></td>
        <td>Cursusgeld:</td>
        <td>&euro;&nbsp;
          <input name="cursusgeld" type="text" value="<?php echo $inschrijving->Fields('cursusgeld'); ?>" size="6"/></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Datum inschr.:</td>
        <td><input type="text" name="datum_inschr" value="<?php echo $inschrijving->Fields('datum_inschr'); ?>" size="10" /></td>
        <td>datum aanmaning:</td>
        <td><input type="text" name="aanmaning_inschr" value="<?php echo $inschrijving->Fields('aanmaning_inschr'); ?>" size="10" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Voorlopige bev.: </td>
        <td><input name="voorl_bev" type="text" id="voorl_bev" value="<?php echo $inschrijving->Fields('voorl_bev'); ?>" size="10" /></td>
        <td>bus Praag: </td>
        <td>heen: 
          <input name="busheen" type="checkbox" id="busheen" value="1" <?php if (!(strcmp($inschrijving->Fields('busheen'),"1"))) {echo "checked";} ?> /> 
          terug: 
        <input name="busterug" type="checkbox" id="busterug" value="1" <?php if (!(strcmp($inschrijving->Fields('busterug'),"1"))) {echo "checked";} ?> /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Aangenomen:</td>
        <td><input <?php if (!(strcmp($inschrijving->Fields('aangenomen'),1))) {echo "checked";} ?> name="aangenomen" type="checkbox" id="aangenomen" value="1"></td>
        <td>Inzeepdag:</td>
        <td><input type="text" name="inzeepdag" value="<?php echo $inschrijving->Fields('inzeepdag'); ?>" size="32" /></td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">Afgewezen:</td>
        <td><input <?php if (!(strcmp($inschrijving->Fields('afgewezen'),1))) {echo "checked";} ?> name="afgewezen" type="checkbox" id="afgewezen" value="1"></td>
        <td onClick="GP_popupConfirmMsg('Moeten deze gegevens werkelijk gewist worden?');return document.MM_returnValue">&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <tr valign="baseline">
        <td nowrap align="right">&nbsp;</td>
        <td><input name="Wijzigen" type="submit" id="Wijzigen" value="Wijzigen" /></td>
        <td onClick="GP_popupConfirmMsg('Moeten deze gegevens werkelijk gewist worden?');return document.MM_returnValue"><input name="Wissen" type="submit" id="Wissen" value="Wissen"></td>
        <td><input name="Leegmaken" type="submit" id="Leegmaken" value="Leegmaken" /></td>
      </tr>
    </table>
  </form>
  </td>
  </tr>
  </table>
</div>
</body>
</html>
<?php 
if (isset($inschrijving)) $inschrijving->Close();

if (isset($Dlnmr)) $Dlnmr->Close();
?>
