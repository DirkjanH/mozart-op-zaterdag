<?php //Connection statement
require_once('/home/pellegrina.nl/www/ProjectOrkest/includes/aanmelden2008.php');
include "../login/level3_check.php";

// build the form action
$editFormAction = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) ? "?" . $_SERVER['QUERY_STRING'] : "");

/* echo '<pre>';
print_r($_POST);
echo '</pre>';*/

// gegevens wissen
if (isset($_GET['DlnmrId']) and ($_GET['DlnmrId'] != "") and isset($_POST["Wissen"]) and ($_POST['Wissen'] == "Wissen")) {

	// begin Recordset
	$colname__Adressen = '-1';
	if (isset($_GET['DlnmrId'])) $colname__Adressen = $_GET['DlnmrId'];
	$query_Adressen = sprintf("SELECT COUNT(*) as aantal FROM dlnmr WHERE AdresId_FK = (SELECT AdresId_FK FROM dlnmr WHERE DlnmrId = %s)", GetSQLValueString($colname__Adressen, "int"));
	$Adressen = $aanmelden->SelectLimit($query_Adressen) or die($aanmelden->ErrorMsg());
	$aantal = $Adressen->Fields('aantal');
	// end Recordset
	
	if ($aantal == 1) {
	  $deleteadresSQL = sprintf("DELETE FROM adres WHERE AdresId=%s",
								  GetSQLValueString($_POST['AdresId'], "int"));
	  $Result1 = $aanmelden->Execute($deleteadresSQL) or die($aanmelden->ErrorMsg());
	}
	else echo "Er zijn meer personen met dit adres. Het adres kan niet gewist worden<br>";
	
  $deleteSQL = sprintf("DELETE FROM dlnmr WHERE DlnmrId=%s",
                       GetSQLValueString($_GET['DlnmrId'], "int"));
  $Result1 = $aanmelden->Execute($deleteSQL) or die($aanmelden->ErrorMsg());
}

// Update gegevens	
if (isset($_GET['DlnmrId']) and ($_GET['DlnmrId'] != "") and isset($_POST["Wijzigdlnmr"]) and ($_POST['Wijzigdlnmr'] == "Wijzig dlnmr")) {
  $updateSQL = sprintf("UPDATE dlnmr SET voornaam=%s, tussenvoegsels=%s, achternaam=%s, naam=%s, geboortedatum=%s, geslacht=%s, student=%s, oost=%s, taal=%s, telefoon=%s, mobiel=%s, email=%s, dieet=%s, eerste_inschrijving=%s, AdresId_FK=%s WHERE DlnmrId=%s",
                       GetSQLValueString($_POST['voornaam'], "text"),
                       GetSQLValueString($_POST['tussenvoegsels'], "text"),
                       GetSQLValueString($_POST['achternaam'], "text"),
                       GetSQLValueString($_POST['naam'], "text"),
                       GetSQLValueString($_POST['geboortedatum'], "text"),
                       GetSQLValueString($_POST['geslacht'], "text"),
					   GetSQLValueString(isset($_POST['student']) ? "true" : "", "defined","'1'","'0'"),
					   GetSQLValueString(isset($_POST['oost']) ? "true" : "", "defined","'1'","'0'"),
                       GetSQLValueString($_POST['taal'], "text"),
                       GetSQLValueString($_POST['telefoon'], "text"),
                       GetSQLValueString($_POST['mobiel'], "text"),
                       GetSQLValueString($_POST['email'], "text"),
                       GetSQLValueString($_POST['dieet'], "text"),
                       GetSQLValueString($_POST['eerste_inschrijving'], "text"),
                       GetSQLValueString($_POST['AdresId'], "int"),
                       GetSQLValueString($_GET['DlnmrId'], "int"));

  $Result1 = $aanmelden->Execute($updateSQL) or die($aanmelden->ErrorMsg());
}

if (isset($_GET['DlnmrId']) and ($_GET['DlnmrId'] != "") and isset($_POST["Wijzigadres"]) and ($_POST['Wijzigadres'] == "Wijzig adres")) {
  $updateSQL = sprintf("UPDATE adres SET adres=%s, postcode=%s, plaats=%s, land=%s WHERE AdresId=%s",
                       GetSQLValueString($_POST['adres'], "text"),
                       GetSQLValueString($_POST['postcode'], "text"),
                       GetSQLValueString($_POST['plaats'], "text"),
                       GetSQLValueString($_POST['land'], "text"),
                       GetSQLValueString($_POST['AdresId'], "int"));

  $Result1 = $aanmelden->Execute($updateSQL) or die($aanmelden->ErrorMsg());
}

// begin Recordset
$id = '-1';
if (isset($_GET['DlnmrId']) and !(isset($_POST["Leegmaken"]) and ($_POST['Leegmaken'] == "Leegmaken"))) $id = $_GET['DlnmrId'];
$query_inschrijving = "SELECT * FROM dlnmr, adres WHERE DlnmrId = {$id} AND AdresId_FK = AdresId";
$inschrijving = $aanmelden->SelectLimit($query_inschrijving) or die($aanmelden->ErrorMsg());
// end Recordset

if ((isset($_POST["bewerk"])) && ($_POST["bewerk"] == "bewerk")) {
	if (isset($_POST['telefoon']) and stristr($_POST['telefoon'], "+") === false) $tel = '+31 (' . ltrim($_POST['telefoon'], '0');
	$oud = array("-", " ", ".", "/");
	$tel = str_replace("-", ") ", $tel);
	if (!strpos($tel, ") ")) $tel = substr_replace($tel, ") ", strpos($tel, " ", 5), 1);
	if (isset($_POST['mobiel']) and $_POST['mobiel'] !== "" and stristr($_POST['mobiel'], "+") === false) {
		$mobiel = '+31' . ltrim($_POST['mobiel'], '0');
		$mobiel = str_replace($oud, "", $mobiel);
		$mobiel = chunk_split($mobiel, 3, " ");
		}
}

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
<title>Update persoonlijke gegevens</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link href="../bestellen/css/PO.css" rel="stylesheet" type="text/css">
</head>
<body>
<div id="main">
  <table width="600" border="0" align="left">
    <td><form id="zoek" name="zoek" method="get" action="<?php echo $editFormAction; ?>">
      <input name="DlnmrId" type="hidden" value="<?php if (isset($_GET['DlnmrId'])) echo $_GET['DlnmrId']; ?>" size="5" />
      <input type="submit" name="Submit" value="Zoek">
      </form>
        <form name="inschrijvingsform" method="POST" id="inschrijvingsform" action="<?php echo $editFormAction; ?>">
          <table border="1" align="left">
            <tr valign="baseline">
              <td nowrap align="right">Voornaam:</td>
               <td colspan="3"><input type="text" name="voornaam" value="<?php echo $inschrijving->Fields('voornaam'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Tussenvoegsels:</td>
               <td colspan="3"><input type="text" name="tussenvoegsels" value="<?php echo $inschrijving->Fields('tussenvoegsels'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Achternaam:</td>
               <td colspan="3"><input type="text" name="achternaam" value="<?php echo $inschrijving->Fields('achternaam'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Naam:</td>
               <td colspan="3"><input type="text" name="naam" value="<?php echo $inschrijving->Fields('naam'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Geboortedatum:</td>
              <td colspan="3"><input type="text" name="geboortedatum" value="<?php echo $inschrijving->Fields('geboortedatum'); ?>" size="10" />
                  &nbsp;Password:&nbsp;
                  <input name="password" type="text" id="password" size="4" value="<?php 
						echo $inschrijving->Fields('password'); ?>" maxlength="4"> 
                 &nbsp;student:&nbsp;<input name="student" type="checkbox" id="student" value="1" 
				  <?php if (!(strcmp($inschrijving->Fields('student'),"1"))) {echo "checked";} ?>>
                 &nbsp;oost:&nbsp;<input name="oost" type="checkbox" id="student" value="1" 
				  <?php if (!(strcmp($inschrijving->Fields('oost'),"1"))) {echo "checked";} ?>></td>
             </tr>
            <tr valign="baseline">
              <td align="right" valign="bottom" nowrap>Geslacht:</td>
               <td valign="bottom"><table>
                   <tr>
                     <td><input type="radio" name="geslacht" value="M" <?php if (!(strcmp($inschrijving->Fields('geslacht'),"M"))) {echo "CHECKED";} ?> />
                       Man</td>
                      <td><input type="radio" name="geslacht" value="V" <?php if (!(strcmp($inschrijving->Fields('geslacht'),"V"))) {echo "CHECKED";} ?> />
                         Vrouw</td>
                      </tr>
                  </table>                           
               <td align="right" valign="bottom" nowrap>Taal:</td>
               <td valign="bottom"><input type="text" name="taal" value="<?php echo $inschrijving->Fields('taal'); ?>" size="4" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Kent cursus via: </td>
              <td colspan="3"><?php echo $inschrijving->Fields('publiciteit'); ?>&nbsp;</td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Aanbrenger:&nbsp;</td>
              <td colspan="3"><?php echo $inschrijving->Fields('naam_aanbrenger'); ?>&nbsp;</td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Opm. over publ. </td>
              <td colspan="3"><?php echo $inschrijving->Fields('publiciteit_tx'); ?>&nbsp;</td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Telefoon:</td>
               <td colspan="2"><input type="text" name="telefoon" value="<?php if (!isset($tel)) echo $inschrijving->Fields('telefoon'); else echo $tel;
?>" size="45" /></td>
               <td rowspan="2" valign="middle"><input name="bewerk" type="submit" id="bewerk" value="bewerk" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">Mobiel:</td>
               <td colspan="2"><input type="text" name="mobiel" value="<?php if (!isset($mobiel)) echo $inschrijving->Fields('mobiel'); else echo $mobiel; ?>" size="45" /></td>
                </tr>
            <tr valign="baseline">
              <td nowrap align="right">Email:</td>
               <td colspan="3"><input type="text" name="email" value="<?php if (!isset($email)) echo $inschrijving->Fields('email'); else echo $email; ?>" size="45" /></td>
                </tr>
            <tr valign="baseline">
              <td nowrap align="right">Dieet:</td>
              <td colspan="3"><input type="text" name="dieet" value="<?php echo $inschrijving->Fields('dieet'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">1e inschrijving :</td>
              <td colspan="3"><input type="text" name="eerste_inschrijving" value="<?php 
			  echo $inschrijving->Fields('eerste_inschrijving'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right">AdresId:</td>
               <td colspan="3"><input type="text" name="AdresId" value="<?php echo $inschrijving->Fields('AdresId'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td colspan="4" align="right" nowrap class="nadruk">De gegevens
                hieronder veranderen met &quot;Wijzig adres&quot;</td>
             </tr>
            <tr valign="baseline">
              <td align="right" nowrap><span class="grijs">Adres:</span></td>
               <td colspan="3"><input name="adres" type="text" id="adres" value="<?php echo $inschrijving->Fields('adres'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td align="right" nowrap><span class="grijs">Postcode:</span></td>
               <td colspan="3"><input name="postcode" type="text" value="<?php echo $inschrijving->Fields('postcode'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td align="right" nowrap><span class="grijs">Plaats:</span></td>
               <td colspan="3"><input name="plaats" type="text" value="<?php echo $inschrijving->Fields('plaats'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td align="right" nowrap><span class="grijs">Land:</span></td>
               <td colspan="3"><input name="land" type="text" value="<?php echo $inschrijving->Fields('land'); ?>" size="45" /></td>
             </tr>
            <tr valign="baseline">
              <td nowrap align="right"><input name="Wijzigdlnmr" type="submit" id="Wijzigdlnmr" value="Wijzig dlnmr" /></td>
               <td><input name="Wijzigadres" type="submit" id="Wijzigadres" value="Wijzig adres" /></td>
               <td onClick="GP_popupConfirmMsg('Moeten deze gegevens werkelijk gewist worden?');return document.MM_returnValue"><input name="Wissen" type="submit" id="Wissen" value="Wissen"></td>
               <td><input name="Leegmaken" type="submit" id="Leegmaken" value="Leegmaken" /></td>
             </tr>
           </table>
        </form>
       <?php $inschrijving->Close();?></td>
     </tr>
  </table>
</div>
</body>
</html>
<?php
if (isset($Adressen)) $Adressen->Close();
?>
