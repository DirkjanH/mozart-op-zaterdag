<?php 
require_once($_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php');
$mollie = new \Mollie\Api\MollieApiClient();
$mollie->setApiKey("live_PreMvDQJjHmvRUg6jrbyk9djzJ6bwv"); // LIVE  voor Stichting La Pellegrina
//$mollie->setApiKey("test_DJjWgfbtFxJfMRmjuvrUjMhH6UAkqw"); // TEST voor Stichting La Pellegrina 
?>