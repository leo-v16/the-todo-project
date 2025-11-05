<?php

class Connection extends mysqli
{
    private $servername = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "todo_db";

    public function __construct()
    {
        $conn = new mysqli($this->servername, $this->username, $this->password);

        $sql = "CREATE DATABASE IF NOT EXISTS todo_db";
        $conn->query($sql);

        parent::__construct(
            $this->servername,
            $this->username,
            $this->password,
            $this->database
        );

        if ($this->connect_error) {
            die("Connection failed: " . $this->connect_error);
        }

        // Create users table
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL
        )";
        $this->query($sql);

        // Create todos table
        $sql = "CREATE TABLE IF NOT EXISTS todos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            done BOOLEAN NOT NULL DEFAULT FALSE,
            user_id INT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $this->query($sql);


        $conn->close();
    }

    public function __destruct() {
        $this->close();
    }
}