<?php
// stel php in dat deze fouten weergeeft
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php');

Kint::$enabled_mode = false; //($_SERVER['REMOTE_ADDR'] === '83.85.191.103');
Kint\Renderer\RichRenderer::$folder = false;
require_once( 'bestelfuncties.php' );

// begin Recordset
$reserveringen = select_query( "SELECT DISTINCT CONCAT_WS(' ', voornaam, tussenvoegsel, achternaam) as naam, voornaam, plaats, telefoon, email, aanbrenger, opmerkingen FROM {$tabel_reserveringen} WHERE flyers = 1 GROUP BY email ORDER BY achternaam ASC" );
// end Recordset

$aantal = count( $reserveringen );
?>
<!doctype html>
<html>
<head>
	<title>SVP op de hoogte houden:</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="utf-8">
	<link href="<?php echo $css; ?>" rel="stylesheet" type="text/css">
	<link rel="icon" type="image/png" href=<?php echo $favicon; ?>>
</head>

<body>
	<div class="w3-white w3-panel w3-margin-top">
		<h3>De volgende <?php echo $aantal; ?> mensen willen op de hoogte gehouden worden van concerten van <?php echo $organisator; ?>:</h3>
		<table id="res" class="w3-table-all">
			<tr>
				<th>Email:</th>
				<th>Naam:</th>
				<th>Voornaam:</th>
				<th>Plaats:</th>
				<th>Telefoon:</th>
				<th>Aanbrenger:</th>
				<th>Opmerkingen:</th>
			</tr>
			<?php foreach($reserveringen as $res) {?>
			<tr>
				<td><?php echo $res['email']; ?>&nbsp;</td>
				<td><?php echo $res['naam']; ?>&nbsp;</td>
				<td><?php echo $res['voornaam']; ?>&nbsp;</td>
				<td><?php echo $res['plaats']; ?>&nbsp;</td>
				<td><?php echo $res['telefoon']; ?>&nbsp;</td>
				<td><?php echo $res['aanbrenger']; ?>&nbsp;</td>
				<td><?php echo $res['opmerkingen']; ?>&nbsp;</td>
			</tr>
			<?php
			}
			?>
		</table>
		<p>&nbsp;</p>
		<?php	foreach($reserveringen as $res) {?> "
		<?php echo $res['email']; ?>";"
		<?php echo $res['naam']; ?>";"
		<?php echo $res['voornaam']; ?>"<br>
		<?php } ?>
	</div>
</body>
</html>