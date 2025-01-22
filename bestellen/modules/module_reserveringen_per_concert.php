<?php
// stel php in dat deze fouten weergeeft
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php');

session_start();

Kint::$enabled_mode = false;

d($_REQUEST);

setlocale(LC_ALL, 'nl_NL');

$concerten = select_query("SELECT * FROM {$tabel_concerten} WHERE online = 1 ORDER BY datum");
d($concerten);
foreach ($concerten AS $row) {
	$datum = strftime("%A %e %B %Y", strtotime($row['datum']));
	$tijd = strftime("%H:%M", strtotime($row['tijd']));
	$row['dag'] = (strtotime($row['datum']) - time()) / (60 * 60 * 24);
	$row['euro_vol'] = euro2($row['prijs_vol']);
	$row['euro_red'] = euro2($row['prijs_red']);
	$row['euro_kind'] = euro2($row['prijs_kind']);
	$row['concert'] = "<b>{$row['concerttitel']}</b>, te {$row['plaats']}, op {$datum}";
	$row[ 'concert_kort' ] = '<b>' . $row[ 'concerttitel' ] . '</b> (' . $row[ 'plaats' ] . ', op <b>' . strftime( "%e %B %Y", strtotime( $row[ 'datum' ] ) ) . '</b>)';
    if ($tijd != '00:00')$row['concert'] .= ", {$tijd} uur";
	if (!($row['prijs_vol'] > 0 or $row['prijs_red'] > 0))
		$row['entree'] = "toegang gratis (collecte na afloop)";
	else
		$row['entree'] = "entree {$row['euro_vol']}";
	if ($row['prijs_red'] > 0)
		if (isset($row['txt_red'])AND $row['txt_red'] != '')$row['entree'] .= ' | ' . $row['txt_red'] . " {$row['euro_red']}";
		else $row['entree'] .= " | CJP/studenten {$row['euro_red']}";
	if ($row['prijs_kind'] > 0)
		if (isset($row['txt_kind'])AND $row['txt_kind'] != '')$row['entree'] .= ' | ' . $row['txt_kind'] . " {$row['euro_kind']}";
		else $row['entree'] .= " | kinderen tot 12 jaar {$row['euro_kind']}";
	$concert[$row['concertId']] = $row;
}

$_SESSION['sort'] = 'reserveringnr';
if (empty($_SESSION['sort'])OR $_SESSION['sort'] == 'reserveringnr')$_SESSION['sort'] = 'reserveringnr';
if (isset($_POST['sort'])AND $_POST['sort'] == 'naam')$_SESSION['sort'] = 'achternaam';
if (isset($_POST['sort'])AND $_POST['sort'] == 'via')$_SESSION['sort'] = 'publiciteit, aanbrenger';
?>

<!DOCTYPE HTML>
<html>
<head>
	<title>Reserveringen per concert</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="utf-8">
	<meta http-equiv="refresh" content="500; url=<?php echo $_SERVER['../bestelsysteem/PHP_SELF']; // refresh iedere 500 seconden ?>">
	<link href="<?php echo $css; ?>" rel="stylesheet" type="text/css">
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
	<style type="text/css">
	tr.verschenen td {
    background: palegreen;
}
    </style>
	<SCRIPT TYPE="text/javascript">
			<!--
			function toggleCheckbox( id ) {
				var Checkbox = document.getElementById( "aanw_" + id );
				document.formulier.toggle.value = id;
				document.formulier.submit();
			if ( Checkbox.checked == true ) {
					Checkbox.checked = true;
				} else {
					Checkbox.checked = false;
				}
			}

			function selecteer( waarde ) {
				document.formulier.select.value = waarde;
				document.formulier.submit();
			}

			function sorteer( waarde ) {
				document.formulier.sort.value = waarde;
				document.formulier.submit();
			}
			-->
	</SCRIPT>
</head>
<body>
	<div class="w3-content w3-margin-top" style="width: 100%;">
		<div class="w3-white w3-panel w3-card-4">
			<form action="<?php echo $editFormAction; ?>" method="post" name="formulier" id="formulier">
				<p>Sorteer op:
					<label>
					<input name="s" type="radio" id="res" OnClick="sorteer('reserveringnr')" value="reserveringnr" <?php if ($_SESSION['sort'] == 'reserveringnr') echo 'checked'; ?>>
				reserveringsnummer</label>
					&nbsp;&nbsp;|
					<label>
					<input OnClick="sorteer('naam')" type="radio" name="s" value="naam" id="naam" <?php if ($_SESSION['sort'] == 'achternaam') echo 'checked'; ?>>
				achternaam</label>
					&nbsp;&nbsp;|
					<label>
					<input OnClick="sorteer('via')" type="radio" name="s" value="via" id="via" <?php if ($_SESSION['sort'] == 'publiciteit, aanbrenger') echo 'checked'; ?>>
				aanbrenger</label>
				
				</p>
				<input name="sort" id="sort" type="hidden" value="">
				<?php if (is_array($concert) AND count($concert) > 0) {
foreach ($concert as $pl) {
	$query_aantal = "SELECT sum(aantal_vol) as aantal_vol, sum(aantal_red) as aantal_red, sum(aantal_kind) as aantal_kind FROM {$tabel_reserveringen} 
	WHERE concertId={$pl['concertId']} AND {$pl['online']} = 1 AND (betaalstatus = 'paid' OR betaalstatus IS NULL)";
	$aantal = select_query($query_aantal, 1);
	//d($query_aantal, $aantal, $pl);
	// begin Recordset
	$query_reserveringen = "SELECT reserveringnr, CONCAT_WS(' ', voornaam, tussenvoegsel, achternaam) as naam, plaats,
	telefoon, email, concertId, aantal_vol, aantal_red, aantal_kind, publiciteit, aanbrenger, verschenen, betaalstatus, opmerkingen, flyers, timestamp FROM {$tabel_reserveringen} WHERE concertId={$pl['concertId']} AND {$pl['online']} = 1 AND (`betaalstatus` IS NULL OR `betaalstatus` = 'paid') ORDER BY {$_SESSION['sort']} ASC";
	$reserveringen = select_query($query_reserveringen);
    d($query_reserveringen, $reserveringen);
	// end Recordset

	// Pas aanwezigheid aan:
	if ( isset( $_POST[ 'toggle' ] )AND $_POST[ 'toggle' ] > 0 ) {
	  d( $_POST[ 'toggle' ] );
	  $aanwezigheid = select_query( "SELECT verschenen FROM {$tabel_reserveringen} WHERE reserveringnr = {$_POST['toggle']}", 0);

	  d($aanwezigheid);

	  if ($aanwezigheid == 1 )
		$verschijnquery = "UPDATE {$tabel_reserveringen} SET `verschenen` = NULL WHERE `reserveringnr` = {$_POST['toggle']}";
	  else $verschijnquery = "UPDATE {$tabel_reserveringen} SET `verschenen` = 1 WHERE `reserveringnr` = {$_POST['toggle']}";
		d( $verschijnquery );
	exec_query($verschijnquery);
	unset($_POST['toggle']);
	}

	$vol = $aantal['aantal_vol']; 
	$bedrag_vol = $aantal['aantal_vol'] * $pl['prijs_vol']; 
	if ($pl['prijs_red'] > 0) {
		$red = $aantal['aantal_red']; 
		$bedrag_red = $aantal['aantal_red'] * $pl['prijs_red']; 
	}
	else {
		$red = 0;
		$bedrag_red = 0;
	}
	if ($pl['prijs_kind'] > 0) {
		$kind = $aantal['aantal_kind']; 
		$bedrag_kind = $aantal['aantal_kind'] * $pl['prijs_kind']; 
	}
	else {
		$kind = 0;
		$bedrag_kind = 0;
	}
	$som = $vol + $red + $kind;
	$totaal['vol'] += $vol;
	$totaal['red'] += $red;
	$totaal['kind'] += $kind;
	$totaal['totaal'] += $som;	
	$bedrag_som = $bedrag_vol + $bedrag_red + $bedrag_kind;
	$bedrag_totaal['vol'] += $bedrag_vol;
	$bedrag_totaal['red'] += $bedrag_red;
	$bedrag_totaal['kind'] += $bedrag_kind;
	$bedrag_totaal['totaal'] += $bedrag_som;	
	$euro_vol = euro2($bedrag_vol);
	$euro_red = euro2($bedrag_red);
	$euro_kind = euro2($bedrag_kind);
	$euro_som = euro2($bedrag_som);
	$euro_totaal['vol'] = euro2($bedrag_totaal['vol']);
	$euro_totaal['red'] = euro2($bedrag_totaal['red']);
	$euro_totaal['kind'] = euro2($bedrag_totaal['kind']);
	$euro_totaal['som'] = euro2($bedrag_totaal['totaal']);
// end Recordset

$output .= 	"<h4>{$pl['concert_kort']}</h4>";
$output .= "<p><b>Totaal aantal verkochte kaarten: {$som} ({$euro_som});</b> ";
if ($pl['prijs_vol'] > 0) $output .= "Aantal volle kaarten: {$vol} ({$euro_vol}); ";
if ($pl['prijs_red'] > 0) $output .= "aantal red. kaarten: {$red} ({$euro_red}); ";
if ($pl['prijs_kind'] > 0) $output .= "aantal kaarten {$pl['txt_kind']}: {$kind} ({$euro_kind})</p>";


$output .= "<table id=\"res\" class=\"w3-table-all\">
    <tr>
      <th width=\"1%\">Res. nr.:</th>
      <th width=\"140px\">Datum/tijd:</th>
      <th width=\"1%\">Verschenen:</th>
      <th width=\"140px\">Naam:</th>";
if ($GDPR) $output .= "<th>Plaats:</th>
		<th>Telefoon:</th>
      <th>Email:</th>";
      $output .= "<th width=\"1%\">Aantal kaarten vol:</th>";
if ($pl['prijs_red'] > 0) $output .= "<th width=\"1%\">Aantal kaarten red.:</th>";
if ($pl['prijs_kind'] > 0) $output .= "<th width=\"1%\">Aantal kaarten kind:</th>";
$output .= "<th>Weet ervan via:</th>
      <th>Opmerkingen:</th>
      <th>Wil digi-<br>flyers:</th>
    </tr>";

	foreach($reserveringen as $res) {
		$c = $res['concertId'];
		$res['naam'] = stripslashes($res['naam']);
		$res['plaats'] = stripslashes($res['plaats']);
		$concertnaam = $concert[$c]['concert_kort'];
        $Nr = $res[ 'reserveringnr' ];
		$verschenen = '';
		if (isset($res['verschenen']) AND $res['verschenen'] > 0) $verschenen = ' class="verschenen"';
		//d($verschenen);
		if ($res['aanbrenger'] != '')
			$via = $res['aanbrenger'];
		else
			$via = $res['publiciteit'];
$output .= "<tr{$verschenen}>
        <td><div align=\"right\">{$res['reserveringnr']}</div></td>
        <td>{$res['timestamp']}&nbsp;</td>
		<td class=\"w3-center\"><input name=\"aanw_$Nr\" id=\"aanw_$Nr\" onClick=\"toggleCheckbox($Nr)\" type=\"checkbox\" ";
		if ($res['verschenen']) $output .=  'checked ';
		$output .= "></td>
        <td>{$res['naam']}&nbsp;</td>";
if ($GDPR) $output .= "<td>{$res['plaats']}&nbsp;</td>
        <td>{$res['telefoon']}&nbsp;</td>
        <td>{$res['email']}&nbsp;</td>";
$output .= "<td width=\"1%\"><div align=\"center\">{$res['aantal_vol']}</div></td>";     
if ($pl['prijs_red'] > 0) $output .= "<td><div align=\"center\">{$res['aantal_red']}</div></td>";
if ($pl['prijs_kind'] > 0) $output .= "<td><div align=\"center\">{$res['aantal_kind']}</div></td>";
$output .= '<td>'.$via.'&nbsp;</td>';
$output .= '<td>'.$res['opmerkingen'].'&nbsp;</td>
<td class="w3-center">';
if ($res['flyers'] == 1) $output .= 'ja';
$output .= '&nbsp;</div></td>
      </tr>';
  }
  $output .= '</table>';
}
echo "<p><b>Totaal verkochte kaarten: {$totaal['totaal']} ({$euro_totaal['som']});</b><br>
{$totaal['vol']} x vol ({$euro_totaal['vol']});  {$totaal['red']} x red. ({$euro_totaal['red']}); {$totaal['kind']} x 3e tarief ({$euro_totaal['kind']})</p>";
echo $output;
  } 
  else echo 'Momenteel geen concerten in de verkoop.<br>'
  ?>
				<p>laatste verversing: <?php echo strftime("%c"); ?> </p>
    			<input name="toggle" id="toggle" type="hidden" value="">
				<input name="bestelnummer" type="hidden" id="bestelnummer">
				<input name="bestelling_bewerken" type="hidden" id="bestelling_bewerken">
				<input name="aantal_vol_bewerken" type="hidden" id="aantal_vol_bewerken">
				<input name="aantal_red_bewerken" type="hidden" id="aantal_red_bewerken">
				<input name="aantal_kind_bewerken" type="hidden" id="aantal_kind_bewerken">
			</form>
		</div>
	</div>
</body>
</html>