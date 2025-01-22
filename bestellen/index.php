<?php 
// stel php in dat deze fouten weergeeft
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

Kint::$enabled_mode = false; //($_SERVER['REMOTE_ADDR'] === '83.85.191.103');

require_once( 'modules/module_kaartverkoop.php' );

d($txt);

?>
<!DOCTYPE HTML>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="utf-8">
	<title>Reserveer kaarten</title>
	<link href="<?php echo $css; ?>" rel="stylesheet">
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
</head>

<body>
	<div class="TekstpaginaAchtergrond">
		<form name="bestelformulier" id="bestelformulier" method="POST" action="<?php echo $editFormAction; ?>" class="w3-container">
			<h5><?php echo $txt['aanhef']; ?></h5>
			<div class="w3-row-padding">
				<div class="w3-third">
					<label class="w3-label w3-validate"><?php echo $txt['voornaam']; ?><span class="commentaar">*</span></label>
					<input class="w3-input w3-border" name="voornaam" type="text" required value="<?php echo $_POST['voornaam']; ?>">
				</div>
				<div class="w3-third">
					<label class="w3-label w3-validate"><?php echo $txt['tussenv']; ?></label>
					<input class="w3-input w3-border" name="tussenvoegsel" type="text" id="tussenvoegsel" size=30 value="<?php echo $_POST['tussenvoegsel']; ?>">
				</div>
				<div class="w3-third">
					<label class="w3-label w3-validate"><?php echo $txt['achternaam']; ?><span class="commentaar">*</span></label>
					<input class="w3-input w3-border" name="achternaam" type="text" id="achternaam" size=30 value="<?php echo $_POST['achternaam']; ?>" required>
				</div>
			</div>
			<div class="w3-row-padding">
				<div class="w3-third">
					<label class="w3-label w3-validate"><?php echo $txt['telefoon']; ?><span class="commentaar">*</span></label>
					<input class="w3-input w3-border" name="telefoon" type="tel" id="telefoon" size=30 value="<?php echo $_POST['telefoon']; ?>" required>
				</div>
				<div class="w3-third">
					<label class="w3-label w3-validate"><?php echo $txt['email']; ?><span class="commentaar">*</span></label>
					<input class="w3-input w3-border" type="email" name="email" id="email" size="30" value="<?php echo $_POST['email']; ?>" required>
				</div>
				<div class="w3-third">
					<label class="w3-label w3-validate"><?php echo $txt['plaats']; ?><span class="commentaar">*</span></label>
					<input class="w3-input w3-border" name="plaats" type="text" id="plaats" size=30 value="<?php 
			echo $_POST['plaats']; ?>" required>
				</div>
			</div>
			<div class="w3-container">
<?php 
		$allesuitverkocht = false;
		foreach ($concert as $u) if ($u['uitverkocht'] == 1) $allesuitverkocht = true;
	  	if (is_array($concert) AND count($concert) > 0) {
			echo '<h5 class="w3-padding-0 w3-margin-0 w3-margin-top">'. $txt['wens'];
			if (count($concert) > 1) echo ' <span class="w3-tag w3-red w3-small">' . $txt['kiezen'] . '</span>';
			echo '</h5>';
			foreach ($concert as $c) {
				echo '<p class="w3-margin-0">'; ?>
				<input type="radio" class="w3-radio" name="concertId" value="<?php echo $c['concertId']; ?>" 
				<?php if (count($concert) == 1) echo 'checked'; 
				if (isset($_POST[ 'concertId']) and ($_POST[ 'concertId'] == $c['concertId'])) echo 'checked'; 
				if (isset($c[ 'uitverkocht']) and ($c[ 'uitverkocht']==1 )) echo ' disabled'; ?> >
				
				<?php echo stripslashes($c['concert']) . '; ';
			 if (isset($c['uitverkocht']) and ($c['uitverkocht'] == 1)) 
			 	echo '<span class="w3-tag w3-red w3-small">'. $txt['uitverkocht'] . '</span>'; 
			 else echo $c['entree']; ?><br>
				<?php if (isset($c['details'])) echo '<p class="details">' . stripslashes($c['details']) . '</p>';
			 }
			 ?>
			</div>
			<div class="w3-container w3-margin-top">
				<div class="w3-row-padding">
					<div class="w3-third">
						<label class="w3-label w3-validate"><?php echo $txt['aantal_k']; ?><?php if ($c['prijs_vol'] != '0.00') echo $txt['volle_prijs']; ?>:</label>
						<input name="aantal_vol" type="text" required class="w3-input w3-border aantal" id="aantal_vol" value="<?php 
				if (isset($aantal_vol)) echo $aantal_vol; ?>">
					</div>
					<div class="w3-third" <?php if (!(isset($c[ 'prijs_red']) AND $c[ 'prijs_red']> 0)) echo 'style="display: none;"'; ?>>
						<div <?php if (!(isset($c[ 'prijs_red']) AND $c[ 'prijs_red']> 0)) echo 'class="w3-grayscale w3-grey"'; ?>>
							<?php if (isset($c['txt_red']) AND $c['txt_red'] != '') echo '<label class="w3-label">aantal kaarten '. $c['txt_red'].':</label>'; else echo '<label class="w3-label">' . $txt['CJP'] . '</label>';?>
							<input class="w3-input w3-border aantal" name="aantal_red" type="text" id="aantal_red" value="<?php if (isset($aantal_red)) echo $aantal_red;?>" <?php if (!(isset($c[ 'prijs_red']) AND $c[ 'prijs_red']> 0)) echo 'disabled';?>>
						</div>
					</div>
					<div class="w3-third" <?php if (!(isset($c[ 'prijs_kind']) AND $c[ 'prijs_kind']> 0)) echo 'style="display: none;"'; ?>>
						<div <?php if (!(isset($c[ 'prijs_kind']) AND $c[ 'prijs_kind']> 0)) echo 'class="w3-grayscale w3-grey"'; ?>>
							<?php if (isset($c['txt_kind']) AND $c['txt_kind'] != '') echo '<label class="w3-label>">kaarten '. $c['txt_kind'].':</label>'; else echo '<label class="w3-label w3-validate">' . $txt['12_jaar'] . '</label>'; ?>
							<input class="w3-input w3-border aantal <?php if (!(isset($c['prijs_kind']) AND $c['prijs_kind'] > 0)) echo ' w3-grey'; ?>" name="aantal_kind" type="text" id="aantal_kind" value="<?php if (isset($aantal_kind)) echo $aantal_kind;?>" <?php if (!(isset($c[ 'prijs_kind']) AND $c[ 'prijs_kind']> 0)) echo 'disabled'; ?>>
						</div>
					</div>
					<?php
					}
					if (is_array($concert) AND count( $concert ) == 0 )echo '<h5 class="w3-btn-block w3-white w3-text-red">' . $txt['geen_concert'] . '</h5>';
					else echo '<h5 class="w3-btn-block w3-white w3-text-red w3-small">' . $txt['niet_prijs'] . '</h5>';
					?>
				</div>
			</div>
			<div class="w3-panel onzichtbaar">
	<label class="w3-label"><?php echo $txt['huisgenoten']; ?>
		<input class="w3-input w3-border onzichtbaar" name="huisgenoten" type="text" value="<?php 
			if (isset($_POST['huisgenoten'])) echo stripslashes($_POST['huisgenoten']); ?>" size="50">
	</label>
			</div>
<div class="<?php if ($allesuitverkocht) echo 'onzichtbaar'; ?> w3-label w3-panel panelkleur"><?php echo $txt['werkwijze']; ?></div>
			<div class="w3-panel panelkleur">
				<h5>
        <input class="w3-checkbox" type="checkbox" name="flyers" <?php if (isset($_POST['flyers'])) echo 'checked'; ?> >
        <label><?php echo $txt['aankondiging']; ?></label>
      </h5>
			
			</div>
			<div class="w3-panel panelkleur">
				<h5><?php echo $txt['vraag_hoe']; ?></h5>
				<div class="w3-row">
					<div class="w3-third w3-panel w3-leftbar w3-border-green">
						<p>
							<input type="radio" name="publiciteit" value="viavia" <?php if (isset($_POST[ 'publiciteit']) and ($_POST[ 'publiciteit']=="viavia" )) echo 'checked'; ?>>
							<label class="w3-label w3-validate"><?php echo $txt['kennis']; ?></label>
							<br>
							<input type="radio" name="publiciteit" value="deelnemer" <?php if (isset($_POST[ 'publiciteit']) and ($_POST[ 'publiciteit']=="deelnemer" )) echo 'checked'; ?>>
							<label class="w3-label w3-validate"><?php echo $txt['deelnemer']; ?></label>
							<input class="blokje w3-input w3-border" name="aanbrenger" type="text" id="aanbrenger" size="25" value="<?php 
				if (isset($_POST['aanbrenger'])) echo $_POST['aanbrenger']; ?>">
						</p>
					</div>
					<div class="w3-third w3-panel w3-leftbar w3-border-green">
						<p>
							<input type="radio" name="publiciteit" value="krant" <?php if (isset($_POST[ 'publiciteit']) and ($_POST[ 'publiciteit']=="krant" )) echo 'checked'; ?>>
							<label class="w3-label w3-validate"><?php echo $txt['krant']; ?></label>
							<br>
							<input type="radio" name="publiciteit" value="flyer" <?php if (isset($_POST[ 'publiciteit']) and ($_POST[ 'publiciteit']=="flyer" )) echo 'checked'; ?>>
							<label class="w3-label w3-validate"><?php echo $txt['flyer']; ?></label>
							<br>
							<input type="radio" name="publiciteit" value="affiche" <?php if (isset($_POST[ 'publiciteit']) and ($_POST[ 'publiciteit']=="affiche" )) echo 'checked'; ?>>
							<label class="w3-label w3-validate"><?php echo $txt['affiche']; ?></label>
						</p>
					</div>
					<div class="w3-third w3-panel w3-leftbar w3-border-green">
						<p>
							<input type="radio" name="publiciteit" value="internet" <?php if (isset($_POST[ 'publiciteit']) and ($_POST[ 'publiciteit']=="internet" )) echo 'checked'; ?>>
							<label class="w3-label w3-validate"><?php echo $txt['internet']; ?></label>
							<br>
							<input type="radio" name="publiciteit" value="radio" <?php if (isset($_POST[ 'publiciteit']) and ($_POST[ 'publiciteit']=="radio" )) echo 'checked'; ?>>
							<label class="w3-label w3-validate"><?php echo $txt['radio']; ?></label>
							<br>
							<input type="radio" name="publiciteit" value="anders" <?php if (isset($_POST[ 'publiciteit']) and ($_POST[ 'publiciteit']=="anders" )) echo 'checked'; ?>>
							<label class="w3-label w3-validate"><?php echo $txt['anders']; ?>
              <input class="w3-input w3-border" name="opmerkingen" type="text" value="<?php 
			if (isset($_POST['opmerkingen'])) echo stripslashes($_POST['opmerkingen']); ?>" size="20">
            </label>
						</p>
					</div>
				</div>
			</div>
			<div class="w3-container">
				<input class="w3-btn w3-green" <?php if (isset($c[ 'uitverkocht']) and ($c[ 'uitverkocht']==1 )) echo 'DISABLED '; ?>name="submit" type="submit" value="<?php echo $txt['verzenden']; ?>">
			</div>
		</FORM>
	</div>
</body>
</html>