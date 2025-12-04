<?php
// Database connection (using the same connection as aside.php)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'narkhan_db';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>

<div class="w-full lg:w-1/4">
    <!-- Popular Subcategories -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Өнөөдрийн эрэлттэй дэд ангилалууд</h3>
            <span class="trending-badge text-xs font-bold px-2 py-1 rounded-full">
                <i class="fas fa-fire mr-1"></i> Trending
            </span>
        </div>

        <div class="space-y-4">
            <?php
            // Get top 3 popular subcategories with download counts
            $popular_query = "SELECT sc.id, sc.name, COUNT(f.id) as file_count, SUM(f.download_count) as total_downloads
                            FROM subcategories sc
                            LEFT JOIN file_categories fc ON sc.id = fc.subcategory_id
                            LEFT JOIN files f ON fc.file_id = f.id
                            GROUP BY sc.id
                            ORDER BY total_downloads DESC
                            LIMIT 3";
            $popular_result = mysqli_query($conn, $popular_query);
            
            if (mysqli_num_rows($popular_result) > 0) {
                // Get max downloads for percentage calculation
                $max_downloads = 0;
                $downloads = [];
                while ($row = mysqli_fetch_assoc($popular_result)) {
                    $downloads[] = $row;
                    if ($row['total_downloads'] > $max_downloads) {
                        $max_downloads = $row['total_downloads'];
                    }
                }
                mysqli_data_seek($popular_result, 0); // Reset pointer

                while ($subcat = mysqli_fetch_assoc($popular_result)) {
                    $percentage = $max_downloads > 0 ? round(($subcat['total_downloads'] / $max_downloads) * 100) : 0;
                    echo '<div class="popular-category bg-white p-4 border-l-4 border-purple-600 rounded-r-md">';
                    echo '<div class="flex justify-between items-start">';
                    echo '<div>';
                    echo '<h4 class="font-semibold text-gray-800 mb-1">' . htmlspecialchars($subcat['name']) . '</h4>';
                    echo '<p class="text-sm text-gray-600">' . $subcat['file_count'] . ' файл байна</p>';
                    echo '</div>';
                    echo '<div class="text-right">';
                    echo '<div class="text-2xl font-bold text-purple-600">' . $subcat['total_downloads'] . '</div>';
                    echo '<p class="text-xs text-gray-500">татагдсан</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '<div class="mt-2 flex items-center">';
                    echo '<div class="w-full bg-gray-200 rounded-full h-2">';
                    echo '<div class="bg-purple-600 h-2 rounded-full" style="width: ' . $percentage . '%"></div>';
                    echo '</div>';
                    echo '<span class="ml-2 text-xs text-gray-500">' . $percentage . '%</span>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<p class="text-sm text-gray-500">No popular subcategories found</p>';
            }
            ?>
        </div>
    </div>

    <!-- Top Uploaders Today -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Өнөөдрийн шилдэг нийтлэгчид</h3>
        <div class="space-y-4">
            <?php
            // Get top uploaders today
            $uploaders_query = "SELECT u.id, u.username, COUNT(f.id) as file_count, SUM(f.download_count) as total_downloads
                              FROM users u
                              JOIN files f ON u.id = f.user_id
                              WHERE DATE(f.upload_date) = CURDATE()
                              GROUP BY u.id
                              ORDER BY file_count DESC
                              LIMIT 3";
            $uploaders_result = mysqli_query($conn, $uploaders_query);
            
            if (mysqli_num_rows($uploaders_result) > 0) {
                $rank = 1;
                while ($uploader = mysqli_fetch_assoc($uploaders_result)) {
                    $badge_class = '';
                    if ($rank == 1) $badge_class = 'bg-yellow-400 text-white';
                    elseif ($rank == 2) $badge_class = 'bg-gray-300 text-gray-700';
                    else $badge_class = 'bg-amber-800 text-white';

                    echo '<div class="flex items-center">';
                    echo '<div class="relative">';
                    echo '<img src="http://kok.mn/member/photo/avatar.png" alt="' . htmlspecialchars($uploader['username']) . '" class="w-10 h-10 rounded-full">';
                    echo '<span class="absolute -top-1 -right-1 ' . $badge_class . ' rounded-full w-5 h-5 flex items-center justify-center text-xs">' . $rank . '</span>';
                    echo '</div>';
                    echo '<div class="ml-3">';
                    echo '<a href="user-profile.php?id=' . $uploader['id'] . '" class="text-sm font-medium text-gray-800 hover:text-purple-600">' . 
                         htmlspecialchars($uploader['username']) . '</a>';
                    echo '<p class="text-xs text-gray-500">' . $uploader['file_count'] . ' файл нэмсэн, ' . $uploader['total_downloads'] . ' татагдсан</p>';
                    echo '</div>';
                    echo '</div>';

                    $rank++;
                }
            } else {
                echo '<p class="text-sm text-gray-500">No uploaders today</p>';
            }
            ?>
        </div>
    </div>

    <!-- Premium Files -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Premium файлууд</h3>
        <div class="space-y-3">
            <?php
            // Get premium files (price > 0)
            $premium_query = "SELECT id, title, price FROM files WHERE price > 0 ORDER BY download_count DESC LIMIT 3";
            $premium_result = mysqli_query($conn, $premium_query);

            if (mysqli_num_rows($premium_result) > 0) {
                while ($file = mysqli_fetch_assoc($premium_result)) {
                    echo '<div class="flex items-start">';
                    echo '<div class="flex-shrink-0 bg-purple-100 text-purple-600 p-2 rounded-md">';
                    echo '<i class="fas fa-crown text-yellow-500"></i>';
                    echo '</div>';
                    echo '<div class="ml-3">';
                    echo '<a href="file-details.php?id=' . $file['id'] . '" class="text-sm font-medium text-gray-800 hover:text-purple-600">' . 
                         htmlspecialchars($file['title']) . '</a>';
                    echo '<p class="text-xs text-gray-500">' . number_format($file['price'], 2) . '₮</p>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<p class="text-sm text-gray-500">No premium files available</p>';
            }
            
            // Close database connection
            mysqli_close($conn);
            ?>
        </div>
    </div>
</div>