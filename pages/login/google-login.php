<?php
require_once 'vendor/autoload.php';

session_start();

$client = new Google_Client();
$client->setClientId(getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
$client->addScope("email");
$client->addScope("profile");

$authUrl = $client->createAuthUrl();

header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
