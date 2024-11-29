<?php
require_once 'config/config.php';

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build search query
$where_clause = 'WHERE is_public = 1';
if (!empty($search)) {
    $where_clause .= " AND (title LIKE :search OR content LIKE :search)";
}

// Get total count
$count_sql = "SELECT COUNT(*) as count FROM pastes $where_clause";
$stmt = $db->prepare($count_sql);
if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $stmt->bindValue(':search', $search_param, SQLITE3_TEXT);
}
$result = $stmt->execute();
$count = $result->fetchArray(SQLITE3_ASSOC)['count'];
$total_pages = ceil($count / $per_page);

// Get pastes
$sql = "
    SELECT p.*, u.username 
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    $where_clause 
    ORDER BY p.created_at DESC 
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);
if (!empty($search)) {
    $stmt->bindValue(':search', $search_param, SQLITE3_TEXT);
}
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
    <title>Recent Pastes</title>
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
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold dark:text-white">Recent Pastes</h1>
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

                <!-- Search Form -->
                <form method="GET" class="mb-6">
                    <div class="flex gap-4">
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search pastes..." 
                               class="flex-1 border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <button type="submit" 
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Search
                        </button>
                    </div>
                </form>

                <!-- Pastes Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700">
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Title</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Author</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Language</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Created</th>
                                <th class="px-6 py-3 text-left text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pastes as $paste): ?>
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-6 py-4">
                                    <a href="view_paste.php?id=<?php echo $paste['id']; ?>" 
                                       class="text-blue-500 hover:underline">
                                        <?php echo htmlspecialchars($paste['title']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="profile.php?username=<?php echo urlencode($paste['username'] ?? 'Anonymous'); ?>" 
                                       class="text-blue-500 hover:underline">
                                        <?php echo htmlspecialchars($paste['username'] ?? 'Anonymous'); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 dark:text-gray-300">
                                    <?php echo htmlspecialchars($paste['language']); ?>
                                </td>
                                <td class="px-6 py-4 dark:text-gray-300">
                                    <?php echo date('Y-m-d H:i', strtotime($paste['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="raw_paste.php?id=<?php echo $paste['id']; ?>" 
                                       class="text-gray-500 hover:underline">Raw</a>
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
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-1 rounded <?php echo $page === $i ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 