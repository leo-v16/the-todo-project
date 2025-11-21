<?php
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

session_start();

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
$client->addScope('email');
$client->addScope('profile');
$client->addScope('https://www.googleapis.com/auth/calendar.events');

if (isset($_POST['credential'])) {
    $id_token = $_POST['credential'];
    $client = new Google_Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
    $payload = $client->verifyIdToken($id_token);

    if ($payload) {
        $email = $payload['email'];
        
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
                $user_id = $conn->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $email;
            } else {
                echo json_encode(['success' => false, 'error' => 'Error creating user account']);
                exit;
            }
        }
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID token']);
        exit;
    }
}

if (!isset($_GET['code'])) {
    header('Location: login.php');
    exit;
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
$_SESSION['access_token'] = $token;

if (isset($token['error'])) {
    echo "Error: " . $token['error'];
    exit;
}
$access_token = $token['access_token'];

if (isset($_SESSION['task_id']) && isset($_SESSION['task_name'])) {
    $client->setAccessToken($access_token);

    $service = new Google_Service_Calendar($client);

    $event = new Google_Service_Calendar_Event(array(
        'summary' => $_SESSION['task_name'],
        'start' => array(
            'dateTime' => date('Y-m-d\TH:i:s'),
            'timeZone' => 'Asia/Kolkata',
        ),
        'end' => array(
            'dateTime' => date('Y-m-d\TH:i:s', strtotime('+1 hour')),
            'timeZone' => 'Asia/Kolkata',
        ),
    ));

    $calendarId = 'primary';
    $event = $service->events->insert($calendarId, $event);

    unset($_SESSION['task_id']);
    unset($_SESSION['task_name']);
}

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
    // User does not exist, create new user
    $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $password);
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $email;
    } else {
        echo "Error creating user account";
        exit;
    }
}

header('Location: ../home/home.php');
exit;