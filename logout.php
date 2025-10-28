<?php
session_start();

// Rensa allt sessionsdata
$_SESSION = [];
session_destroy();

// Förhindra cache (så man inte kan backa in igen)
header("Cache-Control: no-cache, must-revalidate");
header("Expires: 0");

// Skicka användaren till login
header("Location: index.html");
exit;
