<?php
// stel php in dat deze fouten weergeeft
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php');

require_once('modules/bestelfuncties.php');

Kint::$enabled_mode = false;

d($_REQUEST);

if (isset($_GET['res']) & is_string($_GET['res'])) $bestaat_boeking = select_query("SELECT count(*) FROM {$tabel_reserveringen} WHERE `random_id` = '{$_GET['res']}'", 0);

$res = $_GET['res'];

d($res, $bestaat_boeking);

if ($bestaat_boeking) {    
  $reservering_query = "SELECT * FROM {$tabel_reserveringen} WHERE `random_id` = '$res'";
  $reservering = select_query($reservering_query, 1);
  d($reservering_query, $reservering);

  $naam = str_replace('  ', ' ', ($reservering['voornaam'].' '.$reservering['tussenvoegsel'].
  ' '.$reservering['achternaam']));

  $concert = select_query("SELECT * FROM {$tabel_concerten} WHERE `concertId` = {$reservering['concertId']}", 1);
    
  $nu = date('Y-m-d');
  $datumentijd = strftime("%A %e %B %Y, aanvang %H:%M", strtotime($concert['datum'].' '.$concert['tijd']));
  d($datumentijd, $concert['datum'], $concert['tijd']);
  $al_afgevinkt = $reservering['verschenen'];
  d($nu, $concert['datum']);

    if ($concert['datum'] == $nu) {
      if (!$al_afgevinkt) {
        exec_query("UPDATE {$tabel_reserveringen} SET verschenen = 1 WHERE `random_id` = '$res'");
      $message = 
<<<MESSAGE
        <body class="w3-green w3-center">
            <div class="w3-content w3-panel">
              <h3>Ticketcontrole middels QR codes</h3>
              <p>Het kaartje is afgevinkt</p>
              <p>Naam: $naam | Concert: {$concert['concerttitel']} - {$datumentijd}</p>
			  <h4>Aantal plaatsen:<br>
			  gewoon {$reservering['aantal_vol']}<br>
              reductie {$reservering['aantal_red']}<br>
              kinderen {$reservering['aantal_kind']} </h4>
            </div>
        </body>
MESSAGE;
      }
      else {
      $message = 
<<<MESSAGE
        <body class="w3-red w3-center">
            <div class="w3-content w3-panel">
              <h3>Ticketcontrole middels QR codes</h3>
              <p><b>HET KAARTJE IS AL AFGEVINKT</b></p>
              <p>Naam: $naam | Concert: {$concert['concerttitel']} - {$datumentijd} </p>
              <p>Aantal plaatsen: gewoon {$reservering['aantal_vol']} | reductie {$reservering['aantal_red']} | kinderen {$reservering['aantal_kind']}</p>
            </div>
        </body>
MESSAGE;        
      }
  }
elseif ($concert['datum'] > $nu) {
        $message = 
<<<MESSAGE
        <body class="w3-pale-green w3-center">
            <div class="w3-content w3-panel">
              <h3>Het concert is binnenkort!</h3>
              <p>Naam: $naam | Concert: {$concert['concerttitel']} - {$datumentijd} </p>
              <p>Aantal plaatsen: gewoon {$reservering['aantal_vol']} | reductie {$reservering['aantal_red']} | kinderen {$reservering['aantal_kind']}</p>
              <p>Het kaartje is nog niet afgevinkt</p>
          </div>
      </body>
MESSAGE;
    }
    elseif ($concert['datum'] < $nu) {
        $message = 
<<<MESSAGE
        <body class="w3-orange w3-center">
            <div class="w3-content w3-panel">
                <h3>Het concert is al geweest!</h3>
                <p>Naam: $naam | Concert: {$concert['concerttitel']} - {$datumentijd} </p>
                <p>Aantal plaatsen: gewoon {$reservering['aantal_vol']} | reductie {$reservering['aantal_red']} | kinderen {$reservering['aantal_kind']}</p>
            </div>
        </body>
MESSAGE;
    }

}
else {
$message = <<<MESSAGE
           <body class="w3-red w3-center">
            <div class="w3-content w3-panel">
              <h3>Deze boeking bestaat niet</h3>
            </div>
           </body>
MESSAGE; 
}
?>

<!doctype html>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="utf-8">
	<link href="<?php echo $css; ?>" rel="stylesheet" type="text/css">
	<link rel="icon" type="image/png" href=<?php echo $favicon; ?>>
    <title>Controle QR codes</title>
</head>
    <?php echo $message;?>
</html>