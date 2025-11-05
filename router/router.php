<?php
session_start();
require(__DIR__ . "/../database/database.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // LOGIN USER
    if (isset($_POST["login_user"])) {
        $username = $_POST["username"];
        $password = $_POST["password"];

        $conn = new Connection();
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // User exists, check password
            $user = $result->fetch_assoc();
            if (password_verify($password, $user["password"])) {
                // Password is correct, log in
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                header("Location: ../pages/home/home.php");
                exit();
            } else {
                // Incorrect password
                echo "Incorrect password";
            }
        } else {
            // User does not exist, create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $hashed_password);
            if ($stmt->execute()) {
                // Log in new user
                $user_id = $conn->insert_id;
                $_SESSION["user_id"] = $user_id;
                $_SESSION["username"] = $username;
                header("Location: ../pages/home/home.php");
                exit();
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        }
    }

    // CREATE TODO
    if (isset($_POST["create_todo"])) {
        $title = $_POST["title"];
        $user_id = $_SESSION["user_id"]; // Get user_id from session
    
        $conn = new Connection();
        $sql = "INSERT INTO todos (title, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $title, $user_id);

        if ($stmt->execute()) {
            header("Location: ../pages/home/home.php");
            exit();
        }
    }

    // MARK TODO AS DONE
    if (isset($_POST["mark_as_done"])) {
        $id = $_POST["id"];

        $conn = new Connection();
        $sql = "UPDATE todos SET done = FALSE WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: ../pages/home/home.php");
            exit();
        }
    }

    // MARK TODO AS UNDONE
    if (isset($_POST["mark_as_undone"])) {
        $id = $_POST["id"];

        $conn = new Connection();
        $sql = "UPDATE todos SET done = TRUE WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: ../pages/home/home.php");
            exit();
        }
    }

    // DELETE TODO
    if (isset($_POST["delete_todo"])) {
        $id = $_POST["id"];

        $conn = new Connection();
        $sql = "DELETE FROM todos WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: ../pages/home/home.php");
            exit();
        }
    }

    // LOGOUT USER
    if (isset($_POST["logout_user"])) {
        session_destroy();
        header("Location: ../pages/login/login.php");
        exit();
    }
}
