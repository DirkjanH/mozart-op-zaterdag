<?php
// build the form action
$editFormAction = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) ? "?" . $_SERVER['QUERY_STRING'] : "");

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php //PHP ADODB document - made with PHAkt 3.5.1?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<META HTTP-EQUIV="content-script-type" CONTENT="text/javascript">
<title>navigatie</title>
<link href="../bestellen/css/PO.css" rel="stylesheet" type="text/css">
</head>
<body>
<form name="form1" method="post" action="<?php echo $editFormAction; ?>">
  <table>
    <tr>
      <td><div align="center">
          <input type="button" value="Persoonlijk" 
				onClick="parent.mainFrame.location='update_persoonlijk.php'">
        </div></td>
      <td><div align="center">
          <input type="button" value="Inschrijving" 
				onClick="parent.mainFrame.location='update_inschrijving.php'">
        </div></td>
      <td><div align="center">
          <INPUT TYPE="button" VALUE="Voorl. bevestiging" 
				onClick="parent.mainFrame.location='update_financieel.php'">
        </div></td>
    </tr>
  </table>
</form>
</body>
</html>
