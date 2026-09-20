<?php
// config/google_auth.php
require_once __DIR__ . '/../vendor/autoload.php';

// REPLACE THESE WITH YOUR ACTUAL CREDENTIALS FROM GOOGLE CLOUD CONSOLE
$googleClientId = 'YOUR_GOOGLE_CLIENT_ID';
$googleClientSecret = 'YOUR_GOOGLE_CLIENT_SECRET';

// Your local callback URL
$redirectUri = 'http://localhost/Expense-Tracker-Project/api/auth/google_callback.php';

$client = new Google_Client();
$client->setClientId($googleClientId);
$client->setClientSecret($googleClientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

?>
