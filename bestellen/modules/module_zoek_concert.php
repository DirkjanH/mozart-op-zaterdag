<?php
// stel php in dat deze fouten weergeeft
//ini_set('display_errors', 1);
//echo 'display_errors = ' . ini_get('display_errors') . "\n";

error_reporting(E_ALL);

require_once $_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php';

d($_GET);
d($_POST);

require_once('../bestelsysteem/bestelsysteem/bestelfuncties.php'); 

if ((isset($_POST["zoek"])) && ($_POST["zoek"] == "zoek")) {

	// begin Recordset
	$zk = '-1';
	if (isset($_POST['zoeknaam'])) {
		$zk = $_POST['zoeknaam'];
		}
	$query_zoek = "SELECT * FROM {$tabel_concerten} WHERE datum LIKE '%%$zk%%' OR concerttitel LIKE '%%$zk%%' OR plaats LIKE '%%$zk%%' 
	ORDER BY datum ASC";
	//echo($query_zoek);
	$zoek_tabel = mysqli_query($KAARTEN_DB, $query_zoek) or die(mysqli_error($KAARTEN_DB));
	// end Recordset
	}

$editFormAction = $_SERVER['PHP_SELF'];
if (isset($_SERVER['QUERY_STRING'])) {
  $editFormAction .= "?" . htmlentities($_SERVER['QUERY_STRING']);
  }

?>
<!DOCTYPE HTML>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="utf-8">
<title>zoek concert</title>
<script type="text/javascript">
//<!--
function ToonId(Id){
	parent.mainframe.document.zoek.concertId.value = Id;
	parent.mainframe.document.zoek.Submit.click();
}
-->
</script>
<link href="<?php echo $css; ?>" rel="stylesheet" type="text/css">
<link href="<?php echo $bestellijst_css; ?>" rel="stylesheet" type="text/css">
</head>
<body>
<div id="main">
  <form id="vinden" method="post" action="<?php echo $editFormAction; ?>">
    <table width="100%" border="0" align="center" cellpadding="5" cellspacing="0">
      <tr>
        <td><label><br>
          Zoekterm:
          <input name="zoeknaam" type="text" id="zoeknaam" value="<?php echo $_POST['zoeknaam']; ?>" size="10">
          </label>
          <input name="zoek" type="submit" id="zoek" value="zoek"></td>
      </tr>
      <?php if (isset($zoek_tabel)) { ?>
      <tr>
        <td valign="top"><p><?php echo mysqli_num_rows($zoek_tabel); ?> resultaten. Klik
          een item aan: </p>
          <div id="navcontainer">
            <ul id="navlist">
              <?php	  		while ($zoek = mysqli_fetch_assoc($zoek_tabel)) {
					$datum = strftime("%a %e %B %Y", strtotime($zoek['datum'])); 
					$z = $zoek['concerttitel']; ?>
              <li id="active"><a href="javascript:ToonId(<?php if (isset($zoek)) echo $zoek['concertId']; ?>)"; >
                <?php 
					if (isset($zoek)) echo "$z<br><span class='klein'>($datum)</span>"; ?>
                </a></li>
              <?php } ?>
            </ul>
          </div>
          </td>
      </tr>
      <?php } ?>
    </table>
  </form>
</div>
</body>
</html>
