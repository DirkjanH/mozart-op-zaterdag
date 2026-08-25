<?php
require_once __DIR__ . '/../includes/inloggen.php';

$bestandsnaam = 'users_tmp/cfb5wd2sc_ssh/mc-cfb5wd2sc_ssh/MOZART_GMAIL_USERNAME.txt';
$paden = [
    'vanaf onderhoud: ../' => __DIR__ . '/../' . $bestandsnaam,
    'vanaf onderhoud: ../../' => __DIR__ . '/../../' . $bestandsnaam,
    'vanaf onderhoud: ../../../' => __DIR__ . '/../../../' . $bestandsnaam,
    'absolute one.com-pad' => '/customers/e/5/3/cfb5wd2sc/users_tmp/cfb5wd2sc_ssh/mc-cfb5wd2sc_ssh/MOZART_GMAIL_USERNAME.txt',
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Test Gmail-configuratiebestand</title>
    <link href="/css/moz.css" rel="stylesheet" type="text/css">
</head>
<body>
    <div class="w3-content w3-white w3-panel">
        <h3>Test Gmail-configuratiebestand</h3>
        <p>Deze test toont alleen of een pad bestaat en leesbaar is. De inhoud van het bestand wordt niet getoond.</p>
        <table class="w3-table w3-bordered w3-striped">
            <tr>
                <th>Variant</th>
                <th>Pad</th>
                <th>Bestaat</th>
                <th>Leesbaar</th>
                <th>Grootte</th>
            </tr>
            <?php foreach ($paden as $label => $pad): ?>
                <tr>
                    <td><?= htmlspecialchars($label) ?></td>
                    <td><?= htmlspecialchars($pad) ?></td>
                    <td><?= file_exists($pad) ? 'ja' : 'nee' ?></td>
                    <td><?= is_readable($pad) ? 'ja' : 'nee' ?></td>
                    <td><?= file_exists($pad) ? (int) filesize($pad) . ' bytes' : '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p>Verwijder dit testscript nadat de controle klaar is.</p>
    </div>
</body>
</html>
