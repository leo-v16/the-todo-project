<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

session_start();

$client = new Google_Client();
$client->setClientId(getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
$client->addScope('email');
$client->addScope('profile');

if (!isset($_GET['code'])) {
    header('Location: login.php');
    exit;
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    echo "Error: " . $token['error'];
    exit;
}
$access_token = $token['access_token'];

$userInfoEndpoint = "https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=" . $access_token;
$response = file_get_contents($userInfoEndpoint);

if ($response === FALSE) {
    echo "Error fetching user info";
    exit;
}
$userInfo = json_decode($response);

$email = $userInfo->email ?? null;
$name = $userInfo->name ?? null;
if (!$email) {
    echo "Error: Email not available from Google account";
    exit;
}

require_once '../../database/database.php';

$conn = new Connection();

$sql = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
} else {
    $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $password);
    if ($stmt->execute()) {
        $userid = $conn->insert_id;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $email;
    } else {
        echo "Error creating user account";
        exit;
    }
}

header('Location: ../home/home.php');
exit;
