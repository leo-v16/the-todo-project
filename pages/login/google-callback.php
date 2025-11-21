<?php
require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['credential'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$id_token = $_POST['credential'];

$client = new Google_Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
$payload = $client->verifyIdToken($id_token);

if ($payload) {
    $email = $payload['email'];
    $name = $payload['name'];

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
            $_SESSION['user_id'] = $userid;
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