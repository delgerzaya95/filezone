<?php
/**
 * Main navigation menu - dynamically generated from database
 */
require_once __DIR__ . '/functions.php';

// Connect to database
$conn = db_connect();

// Query to get all main categories
$query = "SELECT id, name, slug FROM categories ORDER BY name ASC";
$result = mysqli_query($conn, $query);

// Store categories in an array
$categories = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
}

?>

<nav class="bg-white shadow-sm">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center py-3">
            <div class="flex space-x-1 overflow-x-auto py-2 md:py-0 w-full md:w-auto">
                <a href="/index.php" class="nav-link px-3 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 whitespace-nowrap">Нүүр</a>
                <a href="guides.php" class="nav-link px-3 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 whitespace-nowrap">Заавар</a>
                
                <?php foreach ($categories as $category): ?>
                    <a href="/categories.php?category_id=<?= $category['id'] ?>" 
                       class="nav-link px-3 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 whitespace-nowrap">
                       <?= htmlspecialchars($category['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="mt-2 md:mt-0">
                <a href="/upload.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center glowing-button">
                    <i class="fas fa-upload mr-2"></i> Файл оруулах
                </a>
            </div>
        </div>
    </div>
</nav>