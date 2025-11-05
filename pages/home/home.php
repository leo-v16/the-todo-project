<?php
session_start();
require_once __DIR__ . '/../../database/database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login/login.php");
    exit();
}

$conn = new Connection();
$user_id = $_SESSION["user_id"];
$sql = "SELECT * FROM todos WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks: Your Todo App</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="home.js"></script>

    <link rel="stylesheet" href="home.css">
</head>

<body class="min-h-screen antialiased flex flex-col items-center">

    <div class="max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8">

        <?php include("../components/header.php") ?>

        <main id="app-container" class="bg-white p-4 sm:p-6 rounded-xl shadow-lg border border-gray-100 min-h-[300px]">

            <div class="w-full">
                <form id="add-task-form" action="../../router/router.php" method="POST"
                    class="flex items-center space-x-3 mb-6">
                    <input type="text" id="task-input" name="title" required
                        class="flex-1 w-full px-5 py-3 border border-gray-300 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base"
                        placeholder="Add a new task...">
                    <button type="submit" name="create_todo"
                        class="flex-shrink-0 px-6 py-3 border border-transparent rounded-xl shadow-md text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 transform hover:scale-[1.02]">
                        Add
                    </button>
                </form>

                <div class="task-list-container max-h-[40vh] overflow-y-auto pr-2">
                    <ul class="space-y-3">
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $done = $row["done"];
                                $task = $row["title"];
                                $task_id = $row["id"];

                                echo '<li id="' . $task_id . '" class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-xl transition duration-150">';
                                echo '<div class="flex items-center space-x-3 flex-1 min-w-0">';
                                if ($done) {
                                    include("../components/checkmark.php");
                                    echo '<span class="text-sm sm:text-base font-medium text-gray-400 line-through truncate">' . $task . '</span>';
                                } else {
                                    include("../components/no-checkmark.php");
                                    echo '<span class="text-sm sm:text-base font-medium text-gray-800 truncate">' . $task . '</span>';
                                }
                                echo '</div>';
                                include("../components/delete.php");
                                echo '</li>';
                            }
                        } else {
                            echo '<p class="text-center text-gray-500">No tasks yet!</p>';
                        }
                        ?>
                    </ul>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                    <button id="signout" name="logout_user"
                        class="text-sm font-medium text-gray-500 hover:text-indigo-600 transition duration-150">
                        Sign Out
                    </button>
                </div>
            </div>

        </main>

    </div>

</body>

</html>