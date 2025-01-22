<?php //Connection statement
require_once('/home/pellegrina.nl/www/ProjectOrkest/includes/aanmelden2009.php');
include "../login/level3_check.php";

$i = 1;
$cur[] = $i;

/* echo '<pre>';
print_r($cur);
echo '</pre>';
 */
if ((isset($_POST["zoek"])) AND ($_POST["zoek"] == "zoek") AND ($_POST["oude_dlnrs"] == 1)) {

		// begin Recordset
		$colname__Inschr = '-1';
		if (isset($_POST['zoeknaam'])) {
		  	$colname__Inschr = $_POST['zoeknaam'];
			} // colname__Inschr
		$query_Inschr = sprintf("SELECT DISTINCT DlnmrId, naam FROM dlnmr WHERE naam LIKE \"%%%s%%\" 
		ORDER BY achternaam ASC", $colname__Inschr);
		$Inschr = $aanmelden->SelectLimit($query_Inschr) or die($aanmelden->ErrorMsg());
		$totalRows_Inschr = $Inschr->RecordCount();
} // if zoek

if ((isset($_POST["zoek"])) && ($_POST["zoek"] == "zoek") AND ($_POST["oude_dlnrs"] != 1)) {

		// begin Recordset
		$colname__Inschr = '-1';
		if (isset($_POST['zoeknaam'])) {
		  	$colname__Inschr = $_POST['zoeknaam'];
			} // colname__Inschr
		if (empty($_POST['cursus']) OR $_POST['cursus'] == 'alles') {
			$query_Inschr = sprintf("SELECT DISTINCT DlnmrId, naam FROM dlnmr, project_aanmelding WHERE naam LIKE \"%%%s%%\" 
			AND DlnmrId = DlnmrId_FK AND NOT (afgewezen <=> 1)
			AND CursusId_FK = {$i}
			ORDER BY achternaam ASC", $colname__Inschr);
			}
		else {
			$query_Inschr = sprintf("SELECT DISTINCT DlnmrId, naam FROM dlnmr, project_aanmelding WHERE naam LIKE \"%%%s%%\" 
			AND DlnmrId = DlnmrId_FK AND CursusId_FK=%s AND NOT (afgewezen <=> 1) ORDER BY achternaam ASC", 
			$colname__Inschr, ($_POST['cursus'] + $cursus_offset));
			}
		$Inschr = $aanmelden->SelectLimit($query_Inschr) or die($aanmelden->ErrorMsg());
		$totalRows_Inschr = $Inschr->RecordCount();
} // if zoek

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php //PHP ADODB document - made with PHAkt 3.5.1?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Aanbetaling bevestigen</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<script type="text/javascript">
//<!--
function ToonId(Id){
	parent.mainFrame.document.zoek.DlnmrId.value = Id;
	parent.mainFrame.document.zoek.Submit.click();
}
-->
</script>
<link href="../PO.css" rel="stylesheet" type="text/css">
</head>
<body>
<form id="vinden" method="post" action="<?php echo $_SERVER['../PHP_SELF']; ?>">
   <table width="100%" border="0" align="left" cellpadding="0" cellspacing="0">
      <tr>
         <td><br>
         Naam:
            <input name="zoeknaam" type="text" id="zoeknaam" size="15" value="<?php echo $_POST['zoeknaam']; ?>">
           <label><br>
            Alle deelnemers van ooit:
            <input name="oude_dlnrs" type="checkbox" id="oude_dlnrs" value="1" <?php if (isset($_POST['oude_dlnrs']) AND $_POST['oude_dlnrs'] != "") echo 'checked'; ?>>
           <br>
           </label>
           <table width="200">
             <tr>
               <td><label>
                 <input type="radio" name="cursus" value="alles">
                 Alles:</label></td>
             </tr>
             <tr>
               <td><label>
                 <input type="radio" name="cursus" value="1">
                  Rybovka 2008</label></td>
             </tr>
             <tr>
               <td><label>
                 <input type="radio" name="cursus" value="2">
                  Boheemse kerst 2009</label></td>
             </tr>
           </table>
            <p>
               <input name="zoek" type="submit" id="zoek" value="zoek">
            </p>
         </td>
      </tr>
            	<?php if (isset($Inschr)) { ?>
      <tr>
         <td valign="top">Kies een naam uit:
			<div id="navcontainer">
				<ul id="navlist">
					<?php 
					$Inschr->MoveFirst(); 
					while (!$Inschr->EOF) {?>
               <li id="active"><a href="javascript:ToonId(<?php if (isset($Inschr)) echo $Inschr->Fields('DlnmrId'); ?>)"; ><?php 
					if (isset($grijs[$Inschr->Fields('DlnmrId')]) AND $grijs[$Inschr->Fields('DlnmrId')]) echo '<span class="grijs">';
					if (isset($Inschr)) echo "{$Inschr->Fields('naam')} <span class=\"klein\">({$Inschr->Fields('DlnmrId')}) </span>"; 
					if (isset($grijs[$Inschr->Fields('DlnmrId')]) AND $grijs[$Inschr->Fields('DlnmrId')]) echo '</span>'; ?></a></li>
               <?php $Inschr->MoveNext(); 
					}?>
         </ul></div>			</td>       </tr>
      <?php }?>
   </table>
</form>
</body>
</html>
<?php
if (isset($Inschr)) $Inschr->Close();
?>