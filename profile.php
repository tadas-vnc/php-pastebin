<?php
require_once 'config/config.php';

$username = isset($_GET['username']) ? $_GET['username'] : null;
if (!$username) {
    die('Username not specified');
}

// Get user info
$stmt = $db->prepare('SELECT id, username, created_at FROM users WHERE username = :username');
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    die('User not found');
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count of public pastes
$stmt = $db->prepare('SELECT COUNT(*) as count FROM pastes WHERE user_id = :user_id AND is_public = 1');
$stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$count = $result->fetchArray(SQLITE3_ASSOC)['count'];
$total_pages = ceil($count / $per_page);

// Get public pastes
$stmt = $db->prepare('
    SELECT id, title, language, created_at 
    FROM pastes 
    WHERE user_id = :user_id AND is_public = 1 
    ORDER BY created_at DESC 
    LIMIT :limit OFFSET :offset
');
$stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':limit', $per_page, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$pastes = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $pastes[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $isDark ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($username); ?></title>
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
        <div class="max-w-6xl mx-auto">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                <!-- Profile Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold dark:text-white">
                            <?php echo htmlspecialchars($username); ?>'s Profile
                        </h1>
                        <p class="text-gray-600 dark:text-gray-400">
                            Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                        </p>
                    </div>
                    <div class="flex gap-4">
                        <a href="index.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            New Paste
                        </a>
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

                <!-- Pastes Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700">
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Title</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Language</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Created</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pastes as $paste): ?>
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-6 py-4 dark:text-gray-300">
                                    <a href="view_paste.php?id=<?php echo $paste['id']; ?>" 
                                       class="text-blue-500 hover:underline">
                                        <?php echo htmlspecialchars($paste['title']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 dark:text-gray-300">
                                    <?php echo htmlspecialchars($paste['language']); ?>
                                </td>
                                <td class="px-6 py-4 dark:text-gray-300">
                                    <?php echo date('Y-m-d H:i', strtotime($paste['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button onclick="copyPasteUrl(<?php echo $paste['id']; ?>)" 
                                                class="text-blue-500 hover:underline">Copy URL</button>
                                        <a href="download_paste.php?id=<?php echo $paste['id']; ?>" 
                                           class="text-green-500 hover:underline">Download</a>
                                        <a href="raw_paste.php?id=<?php echo $paste['id']; ?>" 
                                           class="text-gray-500 hover:underline">Raw</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-4 flex justify-center gap-2">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?username=<?php echo urlencode($username); ?>&page=<?php echo $i; ?>" 
                           class="px-3 py-1 rounded <?php echo $page === $i ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function copyPasteUrl(id) {
        const url = `${window.location.origin}/view_paste.php?id=${id}`;
        navigator.clipboard.writeText(url).then(() => {
            alert('URL copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy URL:', err);
        });
    }
    </script>
</body>
</html> 