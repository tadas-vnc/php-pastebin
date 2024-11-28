<?php
require_once 'config/config.php';

// At the top of the file, after require_once
$debug_log = [];  // Store debug messages in array

// Get recent public pastes
$stmt = $db->prepare('
    SELECT p.id, p.title, p.created_at, u.username 
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.is_public = 1 
    ORDER BY p.created_at DESC 
    LIMIT 5
');
$result = $stmt->execute();

$recent_pastes = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $recent_pastes[] = $row;
}

// Handle paste creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = htmlspecialchars(trim($_POST['title']));
        $content = $_POST['content'];
        $language = $_POST['language'] ?? 'plaintext';
        $theme = $_POST['theme'] ?? 'default';
        $password = $_POST['password'] ?? '';
        $encrypt_content = isset($_POST['encrypt_content']);
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        
        $debug_log[] = "Received data:";
        $debug_log[] = "Title: " . $title;
        $debug_log[] = "Content length: " . strlen($content);
        
        if (empty($title) || empty($content)) {
            throw new Exception('Title and content are required');
        } elseif ($encrypt_content && empty($password)) {
            throw new Exception('Password is required for encryption');
        }

        // Generate unique filename
        $filename = 'pastes/' . uniqid() . '.txt';
        $debug_log[] = "Generated filename: " . $filename;
        
        // Store original content for database
        $db_content = $content;
        
        // Encrypt content if requested
        if ($encrypt_content) {
            $key = hash('sha256', $password, true);
            $content = openssl_encrypt($content, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        }
        
        // Save content to file
        if (file_put_contents(__DIR__ . '/' . $filename, $content) === false) {
            throw new Exception('Error saving paste content to file');
        }
        
        // Insert into database
        $stmt = $db->prepare('
            INSERT INTO pastes 
            (title, content, file_path, user_id, ip_address, is_public, language, theme, is_encrypted, password_hash) 
            VALUES 
            (:title, :content, :file_path, :user_id, :ip_address, :is_public, :language, :theme, :is_encrypted, :password_hash)
        ');
        
        if ($stmt === false) {
            throw new Exception('Database prepare error: ' . $db->lastErrorMsg());
        }
        
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':content', $db_content, SQLITE3_TEXT);
        $stmt->bindValue(':file_path', $filename, SQLITE3_TEXT);
        $stmt->bindValue(':user_id', isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null, SQLITE3_INTEGER);
        $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
        $stmt->bindValue(':is_public', $is_public, SQLITE3_INTEGER);
        $stmt->bindValue(':language', $language, SQLITE3_TEXT);
        $stmt->bindValue(':theme', $theme, SQLITE3_TEXT);
        $stmt->bindValue(':is_encrypted', $encrypt_content ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':password_hash', $password ? password_hash($password, PASSWORD_DEFAULT) : null, SQLITE3_TEXT);
        
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception('Database execute error: ' . $db->lastErrorMsg());
        }
        
        $paste_id = $db->lastInsertRowID();
        if (!$paste_id) {
            throw new Exception('Error getting paste ID: ' . $db->lastErrorMsg());
        }
        
        // Redirect to view page
        redirect("view_paste.php?id=$paste_id");
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        // Clean up file if it was created
        if (isset($filename) && file_exists(__DIR__ . '/' . $filename)) {
            unlink(__DIR__ . '/' . $filename);
        }
        $debug_log[] = "Error occurred: " . $error;
    }
}

// Only display debug log if there was an error
if (isset($error)) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>";
    echo "<p class='font-bold'>Error:</p>";
    echo "<p>" . htmlspecialchars($error) . "</p>";
    echo "<pre class='mt-2 text-sm'>" . implode("\n", $debug_log) . "</pre>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $isDark ? 'dark' : ''; ?>">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {}
            }
        }
    </script>
    <?php require_once 'theme.php'; ?>
</head>
<body class="bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
    <div class="min-h-screen p-6">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold dark:text-white">Create New Paste</h1>
                <div class="flex gap-4">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Login</a>
                        <a href="register.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Register</a>
                    <?php endif; ?>
                    <button onclick="toggleTheme()" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                    <svg class="w-6 h-6 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/>
                    </svg>
                    <svg class="w-6 h-6 block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                    </svg>
                </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-3">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <?php if (isset($error)): ?>
                            <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-gray-700 dark:text-gray-300 mb-2">Title</label>
                                <input type="text" name="title" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 dark:text-gray-300 mb-2">Content</label>
                                <textarea name="content" rows="15" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white font-mono" required></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 dark:text-gray-300 mb-2">Syntax Language</label>
                                <select name="language" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="plaintext">Plain Text</option>
                                    <option value="javascript">JavaScript</option>
                                    <option value="python">Python</option>
                                    <option value="php">PHP</option>
                                    <option value="html">HTML</option>
                                    <option value="css">CSS</option>
                                    <option value="sql">SQL</option>
                                    <option value="java">Java</option>
                                    <option value="cpp">C++</option>
                                    <!-- Add more languages as needed -->
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 dark:text-gray-300 mb-2">Syntax Theme</label>
                                <select name="theme" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="default">Default</option>
                                    <option value="monokai">Monokai</option>
                                    <option value="github">GitHub</option>
                                    <option value="dracula">Dracula</option>
                                    <!-- Add more themes as needed -->
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 dark:text-gray-300 mb-2">Password Protection (optional)</label>
                                <input type="password" name="password" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Leave empty for no password protection</p>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" name="encrypt_content" id="encrypt_content" class="mr-2">
                                <label for="encrypt_content" class="text-gray-700 dark:text-gray-300">Encrypt paste content (requires password)</label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="is_public" id="is_public" class="mr-2" checked>
                                <label for="is_public" class="text-gray-700 dark:text-gray-300">Make paste public</label>
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                                Create Paste
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h2 class="text-xl font-bold mb-4 dark:text-white">Recent Pastes</h2>
                        <div class="space-y-3">
                            <?php foreach ($recent_pastes as $paste): ?>
                                <div class="border-b dark:border-gray-700 pb-2">
                                    <a href="view_paste.php?id=<?php echo $paste['id']; ?>" 
                                       class="text-blue-500 hover:underline block">
                                        <?php echo htmlspecialchars($paste['title']); ?>
                                    </a>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        by <a href="profile.php?username=<?php echo urlencode($paste['username'] ?? 'Anonymous'); ?>" 
                                              class="text-blue-500 hover:underline">
                                            <?php echo $paste['username'] ?? 'Anonymous'; ?>
                                        </a>
                                        <br>
                                        <?php echo date('Y-m-d H:i', strtotime($paste['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="recent_pastes.php" class="mt-4 block text-center bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded hover:bg-gray-300 dark:hover:bg-gray-600">
                            Show More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>