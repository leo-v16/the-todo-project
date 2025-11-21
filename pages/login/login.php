<?php
    require_once __DIR__ . '/../../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks: Fully Persistent Todo App</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <script src="login.js"></script>

    <link rel="stylesheet" href="login.css">
</head>

<body class="min-h-screen antialiased flex flex-col items-center">

    <div class="max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8">

        <?php include("../components/header.php") ?>

        <main id="app-container" class="bg-white p-6 sm:p-10 rounded-xl shadow-lg border border-gray-100 min-h-[300px]">

            <div class="max-w-md mx-auto">
                <form class="space-y-6" action="../../router/router.php" method="POST">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">
                            Username
                        </label>
                        <div class="mt-1">
                            <input id="username" name="username" type="text" required
                                class="appearance-none block w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <div class="mt-1">
                            <input id="password" name="password" type="password" autocomplete="current-password"
                                required
                                class="appearance-none block w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <button type="submit" name="login_user"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 transform hover:scale-[1.02]">
                            Sign in
                        </button>
                    </div>
                </form>

                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">
                            Or
                        </span>
                    </div>
                </div>

                    <div class="mt-6 w-full flex justify-center">
                        <div id="g_id_onload" data-client_id="<?php echo $_ENV['GOOGLE_CLIENT_ID']; ?>"
                            data-callback="handleCredentialResponse">
                        </div>
                        <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
                            data-text="continue_with" data-size="large" data-logo_alignment="left"></div>
                    </div>
            </div>
        </main>

    </div>



</body>

</html>