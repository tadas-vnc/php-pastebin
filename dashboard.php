<?php
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$username = $_SESSION['username'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count = $db->querySingle("SELECT COUNT(*) FROM pastes WHERE user_id = {$_SESSION['user_id']}");
$total_pages = ceil($count / $per_page);

// Get user's pastes
$stmt = $db->prepare('
    SELECT id, title, created_at, is_public 
    FROM pastes 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC 
    LIMIT :limit OFFSET :offset
');
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
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
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold dark:text-white">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                    <div class="flex gap-4">
                        <a href="index.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
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
                        <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                            Logout
                        </a>
                    </div>
                </div>

                <!-- Pastes Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700">
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Title</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Created</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Visibility</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pastes as $paste): ?>
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-6 py-4 dark:text-gray-300">
                                    <?php echo htmlspecialchars($paste['title']); ?>
                                </td>
                                <td class="px-6 py-4 dark:text-gray-300">
                                    <?php echo date('Y-m-d H:i', strtotime($paste['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo $paste['is_public'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?> px-2 py-1 rounded-full text-sm">
                                        <?php echo $paste['is_public'] ? 'Public' : 'Private'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <a href="view_paste.php?id=<?php echo $paste['id']; ?>" 
                                           class="text-blue-500 hover:underline">View</a>
                                        <a href="raw_paste.php?id=<?php echo $paste['id']; ?>" 
                                           class="text-blue-500 hover:underline">Raw</a>
                                        <a href="edit_paste.php?id=<?php echo $paste['id']; ?>" 
                                           class="text-green-500 hover:underline">Edit</a>
                                        <button onclick="deletePaste(<?php echo $paste['id']; ?>)" 
                                                class="text-red-500 hover:underline">Delete</button>
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
                        <a href="?page=<?php echo $i; ?>" 
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
                    window.location.reload();
                } else {
                    alert('Error deleting paste');
                }
            });
        }
    }
    </script>
</body>
</html> 