<?php
session_destroy();
echo "Session destroyed. You have been logged out.";
header("Location: " . $_SERVER['PHP_SELF']);
// Redirect to the login page or home page after logout
exit;
