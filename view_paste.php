<?php
require_once 'config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$password_error = false;
$content = null;

// Get paste metadata
$stmt = $db->prepare('
    SELECT p.*, u.username 
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.id = :id
');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$result = $stmt->execute();
$paste = $result->fetchArray(SQLITE3_ASSOC);

if (!$paste) {
    die('Paste not found');
}

// Check if paste is private and user has access
if (!$paste['is_public'] && 
    (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $paste['user_id']) &&
    $_SERVER['REMOTE_ADDR'] != $paste['ip_address']) {
    die('Access denied');
}

// Handle password protection
if ($paste['password_hash']) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (password_verify($_POST['password'], $paste['password_hash'])) {
            $_SESSION['paste_access_' . $id] = true;
        } else {
            $password_error = true;
        }
    }
    
    if (!isset($_SESSION['paste_access_' . $id])) {
        ?>
        <!DOCTYPE html>
        <html lang="en" class="<?php echo $isDark ? 'dark' : ''; ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Required</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    darkMode: 'class',
                    theme: { extend: {} }
                }
            </script>
            <?php require_once 'theme.php'; ?>
        </head>
        <body class="bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
            <div class="min-h-screen flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md w-96">
                    <h1 class="text-2xl font-bold mb-4 dark:text-white">Password Required</h1>
                    <?php if ($password_error): ?>
                        <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                            Incorrect password
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300">Password</label>
                            <input type="password" name="password" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        </div>
                        <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                            Submit
                        </button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Read and decrypt content if necessary
$content = file_get_contents(__DIR__ . '/' . $paste['file_path']);
if ($paste['is_encrypted']) {
    if (!isset($_POST['password']) && !isset($_SESSION['paste_access_' . $id])) {
        // Show password form
        ?>
        <!DOCTYPE html>
        <html lang="en" class="<?php echo $isDark ? 'dark' : ''; ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Required</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    darkMode: 'class',
                    theme: { extend: {} }
                }
            </script>
            <?php require_once 'theme.php'; ?>
        </head>
        <body class="bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
            <div class="min-h-screen flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md w-96">
                    <h1 class="text-2xl font-bold mb-4 dark:text-white">Password Required</h1>
                    <?php if ($password_error): ?>
                        <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                            Incorrect password
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300">Password</label>
                            <input type="password" name="password" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        </div>
                        <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                            Submit
                        </button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    $password = $_POST['password'] ?? $_SESSION['paste_access_' . $id] ?? null;
    if ($password === null) {
        die('Password required');
    }
    
    $key = hash('sha256', $password, true);
    $decrypted = openssl_decrypt($content, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    
    if ($decrypted === false) {
        // Clear invalid session access if it exists
        unset($_SESSION['paste_access_' . $id]);
        redirect("view_paste.php?id=$id");
    }
    
    // Store valid password in session
    if (isset($_POST['password'])) {
        $_SESSION['paste_access_' . $id] = $_POST['password'];
    }
    
    $content = $decrypted;
}

// Get recent pastes
$recent_stmt = $db->prepare('
    SELECT p.id, p.title, p.created_at, u.username 
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.is_public = 1 
    ORDER BY p.created_at DESC 
    LIMIT 5
');
$result = $recent_stmt->execute();

$recent_pastes = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $recent_pastes[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $isDark ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paste['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/<?php echo htmlspecialchars($paste['theme']); ?>.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/plugins/highlightjs-line-numbers.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: {} }
        }
    </script>
    <?php require_once 'theme.php'; ?>
    <style>
        /* Line numbers styles */
        .hljs-ln-numbers {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            text-align: right;
            color: #ccc;
            border-right: 1px solid #CCC;
            vertical-align: top;
            padding-right: 8px !important;
            padding-left: 8px !important;
        }

        .dark .hljs-ln-numbers {
            border-right-color: #444;
            color: #666;
        }

        .hljs-ln-code {
            padding-left: 8px !important;
        }

        /* Dark theme adjustments */
        .dark pre {
            background-color: #1a1a1a !important;
        }

        .dark .hljs {
            background-color: #1a1a1a !important;
        }

        /* Code block container */
        .code-container {
            position: relative;
            margin-top: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .dark .code-container {
            border: 1px solid #333;
        }

        /* Language badge */
        .language-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-family: monospace;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
        }

        .dark .language-badge {
            background-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
    <div class="min-h-screen p-6">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-3">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h1 class="text-2xl font-bold dark:text-white"><?php echo htmlspecialchars($paste['title']); ?></h1>
                                <p class="text-gray-600 dark:text-gray-400">
                                    by <?php if (isset($paste['username'])): ?>
                                        <a href="profile.php?username=<?php echo urlencode($paste['username']); ?>" 
                                           class="text-blue-500 hover:underline">
                                            <?php echo htmlspecialchars($paste['username']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-600 dark:text-gray-400">Anonymous</span>
                                    <?php endif; ?> •
                                    <?php echo date('Y-m-d H:i', strtotime($paste['created_at'])); ?>
                                </p>
                            </div>
                            <div class="flex gap-4">
                                <a href="index.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">New Paste</a>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="dashboard.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Dashboard</a>
                                <?php else: ?>
                                    <a href="login.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Login</a>
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

                        <!-- Content -->
                        <div class="code-container">
                            <div class="language-badge"><?php echo htmlspecialchars($paste['language']); ?></div>
                            <pre><code class="language-<?php echo htmlspecialchars($paste['language']); ?>"><?php echo htmlspecialchars($content); ?></code></pre>
                        </div>

                        <!-- Actions -->
                        <div class="mt-6 flex flex-wrap gap-4">
                            <button onclick="copyPasteContent()" 
                                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Copy Contents
                            </button>
                            <a href="download_paste.php?id=<?php echo $paste['id']; ?>" 
                               class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                Download
                            </a>
                            <a href="raw_paste.php?id=<?php echo $paste['id']; ?>" 
                               class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                Raw
                            </a>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $paste['user_id']): ?>
                                <a href="edit_paste.php?id=<?php echo $paste['id']; ?>" 
                                   class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                                    Edit
                                </a>
                                <button onclick="deletePaste(<?php echo $paste['id']; ?>)"
                                        class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                                    Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h2 class="text-xl font-bold mb-4 dark:text-white">Recent Pastes</h2>
                        <div class="space-y-3">
                            <?php foreach ($recent_pastes as $recent): ?>
                                <div class="border-b dark:border-gray-700 pb-2">
                                    <a href="view_paste.php?id=<?php echo $recent['id']; ?>" 
                                       class="text-blue-500 hover:underline block">
                                        <?php echo htmlspecialchars($recent['title']); ?>
                                    </a>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        by <?php if (isset($recent['username'])): ?>
                                            <a href="profile.php?username=<?php echo urlencode($recent['username']); ?>" 
                                               class="text-blue-500 hover:underline">
                                                <?php echo htmlspecialchars($recent['username']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-600 dark:text-gray-400">Anonymous</span>
                                        <?php endif; ?>
                                        <br>
                                        <?php echo date('Y-m-d H:i', strtotime($recent['created_at'])); ?>
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

    <script>
        function copyPasteContent() {
            const content = document.querySelector('pre code').textContent;
            navigator.clipboard.writeText(content).then(() => {
                alert('Paste content copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy content:', err);
            });
        }

        function deletePaste(id) {
            if (confirm('Are you sure you want to delete this paste?')) {
                fetch('delete_paste.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'index.php';
                    } else {
                        alert('Error deleting paste');
                    }
                });
            }
        }

        hljs.highlightAll();
        hljs.initLineNumbersOnLoad();
    </script>
</body>
</html> 