<?php
require_once '../../config/google_auth.php';

// Generate a URL to request access from Google's OAuth 2.0 server
$authUrl = $client->createAuthUrl();

// Redirect to Google's authentication page
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit();
?>
