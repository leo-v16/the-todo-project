<?php
session_start();

// Clear all session variables including Google tokens
unset($_SESSION['google_access_token']);
unset($_SESSION['google_refresh_token']);
unset($_SESSION['user_id']);
unset($_SESSION['username']);
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - Tasks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="login.css">
</head>

<body class="min-h-screen antialiased flex flex-col items-center">

    <div class="max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8">

        <?php include("../components/header.php") ?>

        <main id="app-container" class="bg-white p-6 sm:p-10 rounded-xl shadow-lg border border-gray-100 min-h-[300px]">

            <div class="max-w-md mx-auto text-center">
                <div class="mb-6">
                    <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    You have been logged out successfully
                </h2>
                <p class="text-gray-600 mb-8">
                    Thank you for using Tasks. You can sign in again anytime.
                </p>
                <div class="space-y-4">
                    <a href="login.php" 
                        class="inline-block w-full px-6 py-3 border border-transparent rounded-xl shadow-md text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 transform hover:scale-[1.02]">
                        Sign In Again
                    </a>
                    <p class="text-sm text-gray-500">
                        Redirecting to login page in <span id="countdown">5</span> seconds...
                    </p>
                </div>
            </div>

        </main>

    </div>

    <script>
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>

</body>

</html>

