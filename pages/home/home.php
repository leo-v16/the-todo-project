<?php
session_start();
require_once __DIR__ . '/../../database/database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login/login.php");
    exit();
}

$conn = new Connection();
$user_id = $_SESSION["user_id"];
$sql = "SELECT * FROM todos WHERE user_id = ? ORDER BY section, id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();


$colors = [
    'bg-violet-50', 'bg-gray-50', 'bg-red-50', 'bg-orange-50',
    'bg-yellow-50', 'bg-green-50', 'bg-teal-50', 'bg-blue-50',
    'bg-indigo-50', 'bg-purple-50', 'bg-pink-50'
];


$tasks_by_section = [];
$section_colors = [];
$color_index = 0;
while ($row = $result->fetch_assoc()) {
    $section = $row['section'] ?: 'General';
    if (!isset($section_colors[$section])) {
        $section_colors[$section] = $colors[$color_index % count($colors)];
        $color_index++;
    }
    $tasks_by_section[$section][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks: Your Todo App</title>

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

                <div id="section-selector" class="flex items-center space-x-2 mb-6 border-b pb-4 overflow-x-auto">
                    <button data-section="All" class="selector-button active-section-button px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">All</button>
                    <?php foreach (array_keys($tasks_by_section) as $section_name): ?>
                        <?php $button_color_class = 'bg-white';?>
                        <button data-section="<?php echo htmlspecialchars($section_name); ?>" class="selector-button px-4 py-2 text-sm font-medium text-gray-700 <?php echo $button_color_class; ?> border border-gray-300 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <?php echo htmlspecialchars($section_name); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <form id="add-task-form" action="../../router/router.php" method="POST"
                    class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                    <input type="text" id="task-input" name="title" required
                        class="md:col-span-2 w-full px-5 py-3 border border-gray-300 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base"
                        placeholder="Add a new task...">
                    <input type="text" id="section-input" name="section"
                        class="w-full px-5 py-3 border border-gray-300 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base"
                        placeholder="Section (optional)">
                    <button type="submit" name="create_todo"
                        class="md:col-span-3 w-full px-6 py-3 border border-transparent rounded-xl shadow-md text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 transform hover:scale-[1.02]">
                        Add Task
                    </button>
                </form>

                <div class="task-list-container max-h-[40vh] overflow-y-auto pr-2">
                    <ul class="space-y-4">
                        <?php
                        if (!empty($tasks_by_section)) {
                            foreach ($tasks_by_section as $section => $tasks) {
                                $color_class = $colors[$color_index % count($colors)];
                                echo '<li class="section-container ' . $color_class . ' p-4 rounded-xl" data-section="' . htmlspecialchars($section) . '">';
                                echo '<h2 class="text-xl font-bold text-gray-800 mb-3">' . htmlspecialchars($section) . '</h2>';
                                echo '<ul class="space-y-3">';
                                foreach ($tasks as $row) {
                                    $done = $row["done"];
                                    $task = $row["title"];
                                    $task_id = $row["id"];

                                    echo '<li id="' . $task_id . '" class="flex items-center justify-between p-4 bg-white hover:bg-gray-100 rounded-xl transition duration-150">';
                                    echo '<div class="flex items-center space-x-3 flex-1 min-w-0">';
                                    if ($done) {
                                        include("../components/checkmark.php");
                                        echo '<span class="text-sm sm:text-base font-medium text-gray-400 line-through truncate">' . htmlspecialchars($task) . '</span>';
                                    } else {
                                        include("../components/no-checkmark.php");
                                        echo '<span class="text-sm sm:text-base font-medium text-gray-800 truncate">' . htmlspecialchars($task) . '</span>';
                                    }
                                    echo '</div>';
                                    include("../components/delete.php");
                                    echo '</li>';
                                }
                                echo '</ul>';
                                echo '</li>';
                                $color_index++;
                            }
                        } else {
                            echo '<p class="text-center text-gray-500">No tasks yet!</p>';
                        }
                        ?>
                    </ul>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-100 flex justify-between items-center">
                    <a href="calendar.php" 
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-700 transition duration-150 flex items-center">
                        📅 View Google Calendar
                    </a>
                    <div class="flex items-center space-x-4">
        <span class="text-sm text-gray-500 font-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <button id="signout" name="logout_user"
            class="text-sm font-medium text-gray-500 hover:text-indigo-600 transition duration-150">
            Sign Out
        </button>
    </div>
                </div>
            </div>

        </main>

    </div>
</body>

</html>