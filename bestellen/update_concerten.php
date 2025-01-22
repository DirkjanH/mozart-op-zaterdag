<?php
// stel php in dat deze fouten weergeeft
//ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('modules/bestelfuncties.php');

$_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php';
use function PHP81_BC\strftime;

require_once($_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php');

Kint::$enabled_mode = false; // ($_SERVER['REMOTE_ADDR'] === '83.87.12.163');

ob_start();
session_start();

if (isset($_POST['zoeknaam'])AND $_POST['zoeknaam'] != '')$_SESSION['zoeknaam'] = $_POST['zoeknaam'];
if (isset($_POST['wis'])AND $_POST['wis'] == 'wis')unset($_SESSION['zoeknaam']);

d($_REQUEST, $_SESSION);

$concerten = select_query("SELECT * FROM {$tabel_concerten} WHERE datum LIKE '%%{$_SESSION['zoeknaam']}%%' OR concerttitel LIKE '%%{$_SESSION['zoeknaam']}%%' OR plaats LIKE '%%{$_SESSION['zoeknaam']}%%' ORDER BY datum ASC");

if ((isset($_POST["Toevoegen"])) && ($_POST["Toevoegen"] == "Toevoegen")) {
	$insertSQL = sprintf("INSERT INTO {$tabel_concerten} (concerttitel, details, opmerking_intern, datum, tijd, plaats, prijs_vol,
  prijs_red, prijs_kind, txt_red, txt_kind, online, aantal_plaatsen, uitverkocht) VALUES (%s, %s, %s, %s, %s, %s, %F, %F, %F, %s, %s, %s, %s, %s)",
		quote($_POST['concerttitel'], "text"),
		quote($_POST['details'], "text"),
		quote($_POST['opmerking_intern'], "text"),
		quote($_POST['datum'], "date"),
		quote($_POST['tijd'], "time"),
		quote($_POST['plaats'], "text"),
		round($_POST['prijs_vol'], 2),
		round($_POST['prijs_red'], 2),
		round($_POST['prijs_kind'], 2),
		quote($_POST['txt_red'], "text"),
		quote($_POST['txt_kind'], "text"),
		quote($_POST['online'], "int"),
		quote($_POST['aantal_plaatsen'], "int"),
		quote($_POST['uitverkocht'], "int"));

	d($insertSQL);

	exec_query($insertSQL);
	// echo ('concert is toegevoegd');
}

if ((isset($_POST["Wijzigen"])) && ($_POST["Wijzigen"] == "Wijzigen")) {
	$updateSQL = sprintf("UPDATE {$tabel_concerten} SET concerttitel=%s, details=%s, opmerking_intern=%s, datum=%s, tijd=%s, plaats=%s, 
  prijs_vol=%F, prijs_red=%F, prijs_kind=%F, txt_red=%s, txt_kind=%s, online=%s, aantal_plaatsen=%s, uitverkocht=%s WHERE concertId=%s",
		quote($_POST['concerttitel'], "text"),
		quote($_POST['details'], "text"),
		quote($_POST['opmerking_intern'], "text"),
		quote($_POST['datum'], "date"),
		quote($_POST['tijd'], "time"),
		quote($_POST['plaats'], "text"),
		round($_POST['prijs_vol'], 2),
		round($_POST['prijs_red'], 2),
		round($_POST['prijs_kind'], 2),
		quote($_POST['txt_red'], "text"),
		quote($_POST['txt_kind'], "text"),
		quote($_POST['online'], "int"),
		quote($_POST['aantal_plaatsen'], "int"),
		quote($_POST['uitverkocht'], "int"),
		quote($_POST['concertId'], "int"));

	d($updateSQL);

	exec_query($updateSQL);
}

if ((isset($_POST["Wissen"])) && ($_POST["Wissen"] == "Wissen")and(isset($_POST['concertId']))and($_POST['concertId'] != "")) {
	$deleteSQL = sprintf("DELETE FROM {$tabel_concerten} WHERE concertId=%s",
		quote($_POST['concertId'], "int"));

	exec_query($deleteSQL);
}

$concertId = "-1";
if (isset($_GET['concertId'])) {
	if (!isset($_POST["leegmaken"]))$concertId = $_GET['concertId'];
	$concert = select_query("SELECT * FROM {$tabel_concerten} WHERE concertId = {$concertId}", 1);
}

d($concerten);
?> 

<!DOCTYPE HTML>
<html>
<head>
	<title>Concertonderhoud <?php echo($organisator); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="utf-8">
	<link href="<?php echo $css; ?>" rel="stylesheet" type="text/css">
	<link href="<?php echo $bestellijst_css; ?>" rel="stylesheet" type="text/css">
    <link rel="icon" href="favicon.ico" type="image/x-icon" />
	<script>
		function w3_open() {
			document.getElementById("main").style.marginLeft = "260px";
			document.getElementById("navcontainer").style.width = "250px";
			document.getElementById("navcontainer").style.display = "block";
			document.getElementById("openNav").style.display = "none";
		}

		function w3_close() {
			document.getElementById("main").style.marginLeft = "0%";
			document.getElementById("navcontainer").style.display = "none";
			document.getElementById("openNav").style.display = "inline-block";
		}
	</script>
</head>

<body>
   <div id="nav" class="w3-sidebar w3-bar-block w3-collapse w3-card w3-animate-left">
		<form id="vinden" method="post" action="<?php echo $editFormAction; ?>">
			<div class="w3-panel">
				<label>Zoekterm: <br><input name="zoeknaam" type="text" id="zoeknaam" value="<?php echo $_SESSION['zoeknaam']; ?>" size="5"></label>
				<input name="zoek" type="submit" id="zoek" value="zoek">
				<input name="wis" type="submit" id="wis" value="wis">
				<?php  if (isset($concerten) AND is_array($concerten)) $aantal_concerten = count($concerten); else $aantal_concerten = 0;
				if ($aantal_concerten > 0) { 
					echo <<<XXX
					<p>$aantal_concerten resultaten. Klik een item aan:</p>
					</div>
						<div id="navcontainer">
							<ul id="navlist">
								<li><a href="#" onclick="w3_close()" class="w3-closenav w3-large w3-hide-large">Close &times;</a></li>
					XXX;		
					foreach($concerten AS $conc) {
						$datum = strftime("%a %e %B %Y", strtotime($conc['datum'])); 
						$c = $conc['concerttitel'];
						$href = $_SERVER['PHP_SELF'].'?concertId='.$conc['concertId'];
						echo <<<XXX
						<li id="active">
							<a href="$href">$c<br><span class='klein'>($datum)</span></a></li>
						XXX;
					}
					?>
					</ul>
				<?php } ?>	
			</div>
		</form>
	</div>

	<div id="main" class="w3-main w3-white w3-container w3-padding">
		<form name="concert" method="POST" id="concert" action="<?php echo $editFormAction; ?>">
			<table width="100%" align="left">
				<tr valign="baseline">
					<td width="10%" align="right" nowrap>concertId:</td>
					<td colspan="2"><input type="text" name="concertId" value="<?php echo $concert['concertId']; ?>" size="32"/>
					</td>
					<td width="100" align="right" nowrap>Prijs vol:<br/>
						<span class="style1">NB: gebruik '.'</span>
					</td>
					<td width="70%"><input type="text" name="prijs_vol" value="<?php echo $concert['prijs_vol']; ?>" size="40"/>
						<br/>
					</td>
				</tr>
				<tr valign="baseline">
					<td width="10%" align="right" nowrap>Concerttitel:</td>
					<td><input type="text" name="concerttitel" value="<?php echo htmlspecialchars($concert['concerttitel']); ?>" size="32"/>
					</td>
					<td width="3%" align="right" nowrap="nowrap">Online:
						<input type="checkbox" name="online" value="1" <?php if ($concert['online']==1) echo 'checked'; ?> /></td>
					<td width="100" align="right" nowrap>Prijs red.:</td>
					<td width="70%"><input type="text" name="prijs_red" value="<?php echo $concert['prijs_red']; ?>" size="10"/> ; korting voor
						<input type="text" name="txt_red" value="<?php echo $concert['txt_red']; ?>" size="40"/>
					</td>
				</tr>
				<tr valign="baseline">
					<td width="10%" align="right" nowrap>Datum (yyyy-mm-dd):</td>
					<td width="300" colspan="2"><input type="text" name="datum" value="<?php echo $concert['datum']; ?>" size="40"/>
					</td>
					<td align="right" nowrap="nowrap">Prijs kind:</td>
					<td width="70%"><input name="prijs_kind" type="text" id="prijs_kind" value="<?php echo $concert['prijs_kind']; ?>" size="10"/> ; korting voor
						<input type="text" name="txt_kind" value="<?php echo $concert['txt_kind']; ?>" size="40"/>
					</td>
				</tr>
				<tr valign="baseline">
					<td width="10%" align="right" nowrap>Tijd (hh:mm):</td>
					<td width="300" colspan="2"><input type="text" name="tijd" value="<?php echo $concert['tijd']; ?>" size="40"/>
					</td>
					<td width="100" align="right" nowrap>Aantal plaatsen: </td>
					<td width="70%"><input name="aantal_plaatsen" type="text" id="aantal_plaatsen" value="<?php 
		 echo $concert['aantal_plaatsen']; ?>" size="10"/>
					</td>
				</tr>
				<tr valign="baseline">
					<td width="10%" align="right" nowrap>Plaats:</td>
					<td width="300" colspan="2"><input type="text" name="plaats" value="<?php echo $concert['plaats']; ?>" size="40"/>
					</td>
					<td width="100" align="right" nowrap>Uitverkocht:</td>
					<td width="70%"><input name="uitverkocht" type="checkbox" id="uitverkocht" value="1" <?php if ($concert['uitverkocht']==1) echo 'checked'; ?>></td>
				</tr>
				<tr valign="baseline">
					<td width="10%" align="right" valign="top" nowrap>Publieke details:</td>
					<td colspan="2"><textarea name="details" cols="40" rows="3"><?php echo $concert['details']; ?></textarea>
					</td>
					<td width="100" align="right" valign="top">Interne opmerking:&nbsp;</td>
					<td width="70%" align="left" valign="top"><textarea name="opmerking_intern" cols="40" rows="3" id="opmerking_intern"><?php echo $concert['opmerking_intern']; ?>&nbsp;</textarea>
					</td>
				</tr>
				<tr valign="baseline">
					<td width="10%" align="right" nowrap>&nbsp;</td>
					<td colspan="2"><input name="Toevoegen" type="submit" id="Toevoegen" value="Toevoegen"/>
						<input name="Wijzigen" type="submit" id="Wijzigen" value="Wijzigen"/>
						<input name="Wissen" type="submit" id="Wissen" value="Wissen"/>
					</td>
					<td width="100" align="right"><input name="leegmaken" type="submit" id="leegmaken" value="Leegmaken"/>
					</td>
					<td width="70%">&nbsp;</td>
				</tr>
			</table>
		</form>
	</div>
<?php ob_end_flush(); ?>
	</body>
</html>