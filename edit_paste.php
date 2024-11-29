<?php
require_once 'config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get paste
$stmt = $db->prepare('SELECT * FROM pastes WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$result = $stmt->execute();
$paste = $result->fetchArray(SQLITE3_ASSOC);

if (!$paste) {
    die('Paste not found');
}

// Check if user has permission to edit
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $paste['user_id']) {
    die('Permission denied');
}

// Handle password verification for encrypted pastes
if ($paste['is_encrypted']) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decrypt_password'])) {
        $key = hash('sha256', $_POST['decrypt_password'], true);
        $content = file_get_contents(__DIR__ . '/' . $paste['file_path']);
        $decrypted = openssl_decrypt($content, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        
        if ($decrypted === false) {
            $error = 'Invalid password';
            // Clear any existing session data for this paste
            unset($_SESSION['paste_edit_' . $id]);
            // Show password form again
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
                        <?php if (isset($error)): ?>
                            <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-gray-700 dark:text-gray-300">Password</label>
                                <input type="password" name="decrypt_password" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
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
        } else {
            $_SESSION['paste_edit_' . $id] = $_POST['decrypt_password'];
            $content = $decrypted;
        }
    } elseif (!isset($_SESSION['paste_edit_' . $id])) {
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
                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300">Password</label>
                            <input type="password" name="decrypt_password" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
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
    } else {
        // Decrypt content with stored password
        $key = hash('sha256', $_SESSION['paste_edit_' . $id], true);
        $content = file_get_contents(__DIR__ . '/' . $paste['file_path']);
        $decrypted = openssl_decrypt($content, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        
        if ($decrypted === false) {
            // If stored password is invalid, clear it and ask for password again
            unset($_SESSION['paste_edit_' . $id]);
            redirect("edit_paste.php?id=$id");
        }
        $content = $decrypted;
    }
}

// Handle form submission for updating paste
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['decrypt_password'])) {
    $title = htmlspecialchars(trim($_POST['title']));
    $content = $_POST['content'];
    $language = $_POST['language'] ?? 'plaintext';
    $theme = $_POST['theme'] ?? 'default';
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        $error = 'Title and content are required';
    } else {
        // Re-encrypt content if paste is encrypted
        if ($paste['is_encrypted']) {
            $key = hash('sha256', $_SESSION['paste_edit_' . $id], true);
            $encrypted_content = openssl_encrypt($content, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
            file_put_contents(__DIR__ . '/' . $paste['file_path'], $encrypted_content);
        } else {
            file_put_contents(__DIR__ . '/' . $paste['file_path'], $content);
        }

        // Update database
        $stmt = $db->prepare('
            UPDATE pastes 
            SET title = :title, 
                content = :content, 
                language = :language, 
                theme = :theme, 
                is_public = :is_public 
            WHERE id = :id
        ');

        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':language', $language, SQLITE3_TEXT);
        $stmt->bindValue(':theme', $theme, SQLITE3_TEXT);
        $stmt->bindValue(':is_public', $is_public, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

        if ($stmt->execute()) {
            // Clear the edit session variable
            unset($_SESSION['paste_edit_' . $id]);
            redirect("view_paste.php?id=$id");
        } else {
            $error = 'Error updating paste';
        }
    }
}

// If not encrypted or already decrypted, read current content
if (!isset($content)) {
    $content = file_get_contents(__DIR__ . '/' . $paste['file_path']);
    if ($paste['is_encrypted']) {
        $key = hash('sha256', $_SESSION['paste_edit_' . $id], true);
        $content = openssl_decrypt($content, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $isDark ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Paste</title>
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
    <div class="min-h-screen p-6">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                <h1 class="text-2xl font-bold mb-6 dark:text-white">Edit Paste</h1>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2">Title</label>
                        <input type="text" 
                               name="title" 
                               value="<?php echo htmlspecialchars($paste['title']); ?>" 
                               class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white" 
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2">Content</label>
                        <textarea name="content" 
                                  rows="15" 
                                  class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white font-mono" 
                                  required><?php echo htmlspecialchars($content); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2">Syntax Language</label>
                        <select name="language" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <?php
                            $languages = ['plaintext', 'javascript', 'python', 'php', 'html', 'css', 'sql', 'java', 'cpp'];
                            foreach ($languages as $lang) {
                                $selected = $lang === $paste['language'] ? 'selected' : '';
                                echo "<option value=\"$lang\" $selected>" . ucfirst($lang) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 mb-2">Syntax Theme</label>
                        <select name="theme" class="w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <?php
                            $themes = ['default', 'monokai', 'github', 'dracula'];
                            foreach ($themes as $t) {
                                $selected = $t === $paste['theme'] ? 'selected' : '';
                                echo "<option value=\"$t\" $selected>" . ucfirst($t) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" 
                               name="is_public" 
                               id="is_public" 
                               class="mr-2" 
                               <?php echo $paste['is_public'] ? 'checked' : ''; ?>>
                        <label for="is_public" class="text-gray-700 dark:text-gray-300">Make paste public</label>
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Save Changes
                        </button>
                        <a href="view_paste.php?id=<?php echo $id; ?>" 
                           class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 