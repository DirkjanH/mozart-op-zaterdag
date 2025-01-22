<?php
// stel php in dat deze fouten weergeeft
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once( 'bestelfuncties.php' );

$concerten = select_query("SELECT * FROM {$tabel_concerten} WHERE online = 1 AND `datum` >= date(NOW()) ORDER BY datum");
d( $concerten );
foreach ( $concerten AS $row ) {
  $datum = strftime( "%A %e %B %Y", strtotime( $row[ 'datum' ] ) );
  $tijd = strftime( "%H:%M", strtotime( $row[ 'tijd' ] ) );
  $row[ 'dag' ] = ( strtotime( $row[ 'datum' ] ) - time() ) / ( 60 * 60 * 24 );
  $row[ 'euro_vol' ] = euro2( $row[ 'prijs_vol' ] );
  $row[ 'euro_red' ] = euro2( $row[ 'prijs_red' ] );
  $row[ 'euro_kind' ] = euro2( $row[ 'prijs_kind' ] );
  if ( $row[ 'prijs_kind' ] == -1 )$row[ 'euro_kind' ] = 'gratis';
  $row[ 'concert' ] = "<b>{$row['concerttitel']}</b>, te {$row['plaats']}, op {$datum}";
  if ( $tijd != '00:00' )$row[ 'concert' ] .= ", {$tijd} uur";
  if ( !( $row[ 'prijs_vol' ] > 0 or $row[ 'prijs_red' ] > 0 ) )
    $row[ 'entree' ] = $txt[ 'entree_gratis' ];
  else
    $row[ 'entree' ] = $txt[ 'entree' ] . $row[ 'euro_vol' ];
  if ( $row[ 'prijs_red' ] > 0 )
    if ( isset( $row[ 'txt_red' ] )AND $row[ 'txt_red' ] != '' )$row[ 'entree' ] .= $txt[ 'scheiding' ] . $row[ 'txt_red' ] . " {$row['euro_red']}";
    else $row[ 'entree' ] .= $txt[ 'CJP/studenten' ] . $row[ 'euro_red' ];
  if ( $row[ 'prijs_kind' ] > 0 OR $row[ 'prijs_kind' ] == -1 )
    if ( isset( $row[ 'euro_kind' ] )AND $row[ 'euro_kind' ] != '' )$row[ 'entree' ] .= $txt[ 'scheiding' ] . $row[ 'txt_kind' ] . " {$row['euro_kind']}";
    else $row[ 'entree' ] .= $txt[ 'kinderen' ] . $row[ 'euro_kind' ];
  $concert[ $row[ 'concertId' ] ] = $row;
}

$res = select_query( "SELECT MAX(reserveringnr) FROM {$tabel_reserveringen}", 0 ) + 1;

d( $concert );

$editFormAction = $_SERVER[ 'PHP_SELF' ];
if ( isset( $_SERVER[ 'QUERY_STRING' ] ) ) {
  $editFormAction .= "?" . htmlentities( $_SERVER[ 'QUERY_STRING' ] );
}

d( $_POST );

if ( isset( $_POST[ "submit" ] ) && $_POST[ "submit" ] == "formulier leegmaken" )unset( $_POST );

if ( isset( $_POST[ "submit" ] ) && $_POST[ "submit" ] == "verzenden" ) {

  // corrigeer hoofdlettergebruik
  if ( isset( $_POST[ 'voornaam' ] ) )$_POST[ 'voornaam' ] = stripslashes( rtrim( ucfirst( $_POST[ 'voornaam' ] ) ) );
  if ( isset( $_POST[ 'tussenvoegsel' ] ) )$_POST[ 'tussenvoegsel' ] = stripslashes( rtrim( $_POST[ 'tussenvoegsel' ] ) );
  if ( isset( $_POST[ 'achternaam' ] ) )$_POST[ 'achternaam' ] = stripslashes( rtrim( ucfirst( $_POST[ 'achternaam' ] ) ) );
  if ( isset( $_POST[ 'plaats' ] ) )$_POST[ 'plaats' ] = stripslashes( rtrim( ucfirst( $_POST[ 'plaats' ] ) ) );

  $voornaam = $_POST[ 'voornaam' ];
  $naam = str_replace( '  ', ' ', $_POST[ 'voornaam' ] . ' ' . $_POST[ 'tussenvoegsel' ] .
    ' ' . $_POST[ 'achternaam' ] );

  $error = false;

  $fout = "<table cellpadding=\"5\" border=\"1\"><tr><td><h2 class=\"rood\">{$txt['fout']}</h2>\n<ul>\n";

  // check voornaam:
  if ( empty( $_POST[ 'voornaam' ] ) OR strlen($_POST[ 'voornaam' ]) > 20) {
    $error = true;
    $fout .= "   <li>{$txt['fout_voornaam']}</li>\n";
  }

  // check achternaam:
  if ( empty( $_POST[ 'achternaam' ]) OR strlen($_POST[ 'achternaam' ]) > 30 ) {
    $error = true;
    $fout .= "   <li>{$txt['fout_achternaam']}</li>\n";
  }

  // check plaatsnaam:
  if ( empty( $_POST[ 'plaats' ] ) OR strlen($_POST[ 'achternaam' ]) > 30 ) {
    $error = true;
    $fout .= "   <li>{$txt['fout_plaatsnaam']}</li>\n";
  }

  // check telefoon:
  if ( empty( $_POST[ 'telefoon' ] ) OR strlen($_POST[ 'telefoon' ]) > 15) {
    $error = true;
    $fout .= "   <li>{$txt['fout_tel']}</li>\n";
  }

  // check email:
  if ( empty( $_POST[ 'email' ] ) or !strstr( $_POST[ 'email' ], "@" ) or !strstr( $_POST[ 'email' ], "." ) or strstr( $_POST[ 'email' ], " " ) ) {
    $error = true;
    $fout .= "   <li>{$txt['fout_email']}</li>\n";
  } else {
    $_POST[ 'email' ] = strtolower( $_POST[ 'email' ] );
  }

  // check kaartenbestelling:
  $aantal_vol = ( int )$_POST[ 'aantal_vol' ];
  $aantal_red = ( int )$_POST[ 'aantal_red' ];
  $aantal_kind = ( int )$_POST[ 'aantal_kind' ];
  
    if ($aantal_vol > 20 OR $aantal_red > 20 OR $aantal_kind > 20) {
    $error = true;
    $fout .= "   <li>{$txt['fout_aantal']}</li>\n";
  }
  
  $prijs_vol = $concert[ $_POST[ 'concertId' ] ][ 'prijs_vol' ];
  $prijs_red = $concert[ $_POST[ 'concertId' ] ][ 'prijs_red' ];
  $prijs_kind = $concert[ $_POST[ 'concertId' ] ][ 'prijs_kind' ];
  $euro_vol = $concert[ $_POST[ 'concertId' ] ][ 'euro_vol' ];
  $euro_red = $concert[ $_POST[ 'concertId' ] ][ 'euro_red' ];
  $euro_kind = $concert[ $_POST[ 'concertId' ] ][ 'euro_kind' ];
  $dag = $concert[ $_POST[ 'concertId' ] ][ 'dag' ];
  if ( !( $aantal_vol > 0 or $aantal_red > 0 ) ) {
    $error = true;
    $fout .= "   <li>{$txt['fout_kaarten']}</li>\n";
  }

  // check concertkeuze:
  if ( empty( $_POST[ 'concertId' ] ) ) {
    $error = true;
    $fout .= "   <li>{$txt['fout_concert']}</li>\n";
  }

  $fout .= "</ul>\n<p></p></td></tr></table><p>{$txt['fout_slot']}</p>\n";

  if ( $error )echo $fout;
  else {
    $totaal = ( $aantal_vol * $prijs_vol ) + ( $aantal_red * $prijs_red ) + ( $aantal_kind * $prijs_kind );
    $euro_totaal = euro2( $totaal );
    $totaal_string = number_format( ( float )$totaal, 2, '.', '' );
    d( $totaal, $euro_totaal, $totaal_string );

    $query_eerdere_bestelling = "SELECT count(*) FROM {$tabel_reserveringen} WHERE `email` = '{$_POST['email']}' AND `timestamp` IS NOT NULL AND `timestamp` > date_sub(NOW(), INTERVAL {$tijdslot} MINUTE);";
    d( $query_eerdere_bestelling );
    $eerdere_bestelling = select_query( $query_eerdere_bestelling, 0 );
    d( $eerdere_bestelling );
    if ( isset( $eerdere_bestelling )AND $eerdere_bestelling > 0 ) {
      exit( "Je hebt zojuist al een bestelling gedaan. Wacht minimaal {$tijdslot} minuten met de volgende bestelling!" );
    } else {
      $random_id = bin2hex( random_bytes( 4 ) );
      $insertSQL = sprintf( "INSERT INTO {$tabel_reserveringen} (reserveringnr, voornaam, tussenvoegsel, achternaam, plaats, telefoon, email, concertId, aantal_vol, aantal_red, aantal_kind, totaal, publiciteit, aanbrenger, flyers, anders, opmerkingen, random_id) 
	  VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
        quote( $res ),
        quote( $_POST[ 'voornaam' ] ),
        quote( $_POST[ 'tussenvoegsel' ] ),
        quote( $_POST[ 'achternaam' ] ),
        quote( $_POST[ 'plaats' ] ),
        quote( $_POST[ 'telefoon' ] ),
        quote( $_POST[ 'email' ] ),
        $_POST[ 'concertId' ],
        quote( $aantal_vol ),
        quote( $aantal_red ),
        quote( $aantal_kind ),
        quote( $totaal_string ),
        quote( $_POST[ 'publiciteit' ] ),
        quote( $_POST[ 'aanbrenger' ] ),
        boolval( $_POST[ 'flyers' ] ) ? '1' : '0',
        quote( $_POST[ 'anders' ] ),
        quote( $_POST[ 'opmerkingen' ] ),
        quote( $random_id ) );

      $succes = exec_query( $insertSQL );
      d( $insertSQL, $succes);

    }

$naam = str_replace('  ', ' ', "{$_POST[ 'voornaam' ]} {$_POST[ 'tussenvoegsel' ]} {$_POST[ 'achternaam' ]} ");
	  
    if ($succes) try {
      if ( isset( $totaal )AND $totaal > 0 ) {
        $payment = $mollie->payments->create( [ "amount" => [ "currency" => "EUR", "value" => $totaal_string ],
          "description" => "Bestelling {$res} - {$naam}",
          "redirectUrl" => $url . $dank_pagina . '?res=' . $random_id,
          "webhookUrl" => $url . $webhook,
          "metadata" => [ "order_id" => $res, "random_id" => $random_id ]
        ] );

        $payment = $payment->update();

        d( $payment );
        $status = quote( $payment->status );
        $Mollie_ID = quote( $payment->id );

        $insertSQL = sprintf( "UPDATE $tabel_reserveringen SET betaalstatus = $status, Mollie_ID = $Mollie_ID, `timestamp` = NOW() WHERE reserveringnr = $res" );
        d( $insertSQL );

        $succes = exec_query( $insertSQL );
        d( $succes, $payment->getCheckoutUrl() );

        if ( $succes )header( "Location: " . $payment->getCheckoutUrl(), true, 303 );
      }
    } catch (\Mollie\Api\Exceptions\ApiException $e) {
      echo "API call failed: " . htmlspecialchars( $e->getMessage() );
    }
  }
}
?>