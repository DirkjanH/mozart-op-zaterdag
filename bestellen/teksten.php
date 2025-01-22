<?php 
$t['aanhef'] 		= 'Reserveer kaarten door onderstaand formulier in te vullen. Verzend het door op de knop \'verzenden\' te klikken <span class="commentaar">(* = verplicht veld)</span>.';
$t['voornaam'] 	= 'Voornaam:';
$t['tussenv'] 		= 'Tussenvoegsel:';
$t['achternaam'] 	= 'Achternaam:';
$t['telefoon'] 	= 'Telefoon:';
$t['email'] 		= 'E-mail:';
$t['plaats'] 		= 'Plaats:';
$t['wens'] 			= 'wil graag kaarten bestellen voor het concert:';
$t['uitverkocht'] = 'NB: de voorverkoop voor dit concert is nog gesloten.';
//$t['uitverkocht'] = 'NB: de voorverkoop voor dit concert is reeds gesloten, maar er zijn nog plaatsen aan de kerk.';
//$t['uitverkocht']= 'NB: dit concert is uitverkocht. Er komt een intekenlijst voor openvallende plaatsen aan de deur van de kerk. Kom SVP ca. een uur voor de voorstelling begint om je naam op de lijst te zetten.';
$t['kiezen'] 		= '* SVP concert kiezen';
$t['invullen'] 	= '(SVP het gewenste aantal invullen)';
$t['aantal_k']		= 'aantal kaarten';
$t['volle_prijs']	= ' (volle prijs)';
$t['CJP']			= 'aantal kaarten CJP/studenten:';
$t['12_jaar']		= 'aantal kaarten kinderen tot 12 jaar:';
$t['geen_concert']= 'Momenteel geen voorverkoop voor concerten';
$t['niet_prijs']	= '(SVP de gewenste <strong>aantallen</strong> per soort invullen, <strong>niet </strong>de prijs)';
$t['huisgenoten'] = '';
$t['werkwijze'] 	= 'Je ontvangt een bevestiging van de registratie per email, met het verzoek het verschuldigde bedrag over te maken.';
$t['aankondiging'] = 'Ik wil voortaan graag concert-aankondigingen per e-mail ontvangen en ga akkoord met het <a href="'.$privacybeleid.'" target="_blank">privacybeleid</a>';
$t['vraag_hoe'] 	= 'Hoe wist je van dit concert?';
$t['kennis'] 		= 'via een kennis';
$t['deelnemer'] 	= 'via een deelnemer aan het concert, n.l.';
$t['krant'] 		= 'uit de krant';
$t['flyer'] 		= 'ik zag een flyer';
$t['affiche'] 		= 'ik zag een affiche';
$t['internet'] 	= 'ik zag het op internet';
$t['radio'] 		= 'ik hoorde erover op de radio';
$t['anders'] 		= 'anders, n.l.:';
$t['verzenden'] 	= 'verzenden';

// module_kaartverkoop
$t['entree_gratis'] 	= 'toegang gratis (collecte na afloop)';
$t['scheiding'] 		= ' | ';
$t['entree'] 			= 'entree ';
$t['CJP/studenten']	= ' | CJP/studenten ';
$t['kinderen']			= ' | kinderen tot 12 jaar ';

//fout-detectie
$t['fout']				= 'Het formulier is nog niet volledig ingevuld. De volgende gegevens ontbreken of de volgende fouten zijn opgetreden:';
$t['fout_voornaam']	= 'Je voornaam is niet ingevuld';
$t['fout_achternaam']= 'Je achternaam is niet ingevuld';
$t['fout_plaatsnaam']= 'Je plaatsnaam is niet ingevuld';
$t['fout_tel']			= 'Je telefoonnummer is niet ingevuld';
$t['fout_email']		= 'Je emailadres is niet of niet correct ingevuld';
$t['fout_kaarten']	= 'Je hebt niet ingevuld hoeveel kaarten je wilt bestellen';
$t['fout_concert']	= 'Je hebt niet ingevuld voor welk concert je kaarten wilt';
$t['fout_slot']		= 'Ga naar de desbetreffende velden op het formulier en vul de gegevens aan. Druk daarna nogmaals op \'verzenden\'.</p>
<p>Mocht het niet lukken het formulier te verzenden, dan kun je de gevraagde gegevens 
ook in een normale email versturen aan <a href=\"mailto:{$afzender}\">
{$afzender}</a>.';
$t['fout_tijdslot'] 	= 'Je hebt zojuist al een bestelling gedaan. Wacht minimaal een kwartier met de volgende bestelling!';
$t['nb']					= '';

$tekst['NL']['jij'] = $t;

if (isset($aanspreekwijze) AND $aanspreekwijze == 'u') {
	$txt = $tekst['NL']['u'];
	}
else {
	$txt = $tekst['NL']['jij'];	
	}
?>