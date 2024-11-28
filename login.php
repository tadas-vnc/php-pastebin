<?php
require_once 'config/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'All fields are required';
    } else {
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
        if ($stmt === false) {
            $error = 'Database error: ' . $db->lastErrorMsg();
        } else {
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                redirect('dashboard.php');
            } else {
                $error = 'Invalid username or password';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h1 class="text-2xl font-bold mb-6 text-center">Login</h1>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700">Username</label>
                    <input type="text" name="username" class="w-full border rounded px-3 py-2" required>
                </div>
                
                <div>
                    <label class="block text-gray-700">Password</label>
                    <input type="password" name="password" class="w-full border rounded px-3 py-2" required>
                </div>
                
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                    Login
                </button>
            </form>
            
            <p class="mt-4 text-center">
                Don't have an account? 
                <a href="register.php" class="text-blue-500 hover:underline">Register</a>
            </p>
        </div>
    </div>
</body>
</html> 