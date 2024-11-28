<?php
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen p-6">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        Logout
                    </a>
                </div>
                <p class="text-gray-600">
                    This is your dashboard. Add your content here.
                </p>
            </div>
        </div>
    </div>
</body>
</html> 