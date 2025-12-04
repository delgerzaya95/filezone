<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';
$conn = db_connect();

// Get file ID from URL
$file_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Update view count if file exists
if ($file_id > 0) {
    try {
        // Increment view count
        $update_view_sql = "UPDATE files SET view_count = view_count + 1 WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_view_sql);
        
        if ($update_stmt === false) {
            throw new Exception("Failed to prepare view count statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($update_stmt, "i", $file_id);
        $update_result = mysqli_stmt_execute($update_stmt);
        
        if ($update_result === false) {
            throw new Exception("Failed to update view count: " . mysqli_stmt_error($update_stmt));
        }
    } catch (Exception $e) {
        // Log the error but don't show it to users
        error_log("View count update error: " . $e->getMessage());
        // Continue execution even if view count update fails
    }
}

// Fetch main file details
$file = [];
$user = [];
$tags = [];
$similar_files = [];

if ($file_id > 0) {
    // Main file data
    $sql = "SELECT f.*, u.username, u.avatar_url, u.join_date,
               c.name AS category_name, 
               sc.name AS subcategory_name
    FROM files f 
    JOIN users u ON f.user_id = u.id
    LEFT JOIN file_categories fc ON f.id = fc.file_id
    LEFT JOIN subcategories sc ON fc.subcategory_id = sc.id
    LEFT JOIN categories c ON sc.category_id = c.id
    WHERE f.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $file_id);
    mysqli_stmt_execute($stmt);
    $file = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // Fetch tags
    $tag_sql = "SELECT t.name FROM tags t
    JOIN file_tags ft ON t.id = ft.tag_id
    WHERE ft.file_id = ?";
    $tag_stmt = mysqli_prepare($conn, $tag_sql);
    mysqli_stmt_bind_param($tag_stmt, "i", $file_id);
    mysqli_stmt_execute($tag_stmt);
    $tag_result = mysqli_stmt_get_result($tag_stmt);
    while ($tag = mysqli_fetch_assoc($tag_result)) {
        $tags[] = $tag['name'];
    }

    // Get similar files (same category)
    $similar_sql = "SELECT f.id, f.title, f.file_type, f.file_size, f.price 
    FROM files f
    JOIN file_categories fc ON f.id = fc.file_id
    WHERE fc.subcategory_id IN (
        SELECT subcategory_id FROM file_categories WHERE file_id = ?
        ) AND f.id != ? 
    LIMIT 3";
    $similar_stmt = mysqli_prepare($conn, $similar_sql);
    mysqli_stmt_bind_param($similar_stmt, "ii", $file_id, $file_id);
    mysqli_stmt_execute($similar_stmt);
    $similar_files = mysqli_fetch_all(mysqli_stmt_get_result($similar_stmt), MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filezone - Файлын дэлгэрэнгүй</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/styles.css">
</head>
<body class="bg-gray-50 font-sans">
    <!-- Top Bar -->
    <?php include 'includes/header.php'; ?>

    <!-- Navigation -->
    <?php include 'includes/navigation.php'; ?>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Main Content Area -->
            <div class="w-full lg:w-3/4">
                <!-- File Preview Section -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex items-center text-xs text-gray-500 mb-2">
                        <?php if (!empty($file['category_name'])): ?>
                            <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded-full mr-2">
                                <i class="fas fa-folder-open mr-1"></i>
                                <?= sanitize($file['category_name']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($file['subcategory_name'])): ?>
                            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
                                <i class="fas fa-angle-right mr-1"></i>
                                <?= sanitize($file['subcategory_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800"><?= sanitize($file['title'] ?? 'Untitled File') ?></h2>
                        <div class="flex items-center space-x-2">
                            <?php
                // Calculate average rating
                            $avg_rating = 0;
                            if ($file_id > 0) {
                                $rating_sql = "SELECT AVG(rating) as avg_rating FROM ratings WHERE file_id = ?";
                                $rating_stmt = mysqli_prepare($conn, $rating_sql);
                                mysqli_stmt_bind_param($rating_stmt, "i", $file_id);
                                mysqli_stmt_execute($rating_stmt);
                                $rating_result = mysqli_fetch_assoc(mysqli_stmt_get_result($rating_stmt));
                                $avg_rating = round($rating_result['avg_rating'] ?? 0, 1);
                            }
                            ?>
                            <span class="text-yellow-500"><i class="fas fa-star"></i> <?= $avg_rating ?></span>
                            <span class="text-gray-400">|</span>
                            <span class="text-gray-500 text-sm">Сүүлд шинэчлэгдсэн: <?= date('Y-m-d', strtotime($file['last_updated'] ?? 'now')) ?></span>
                        </div>
                    </div>

                    <!-- File Preview Content -->
                    <div class="mb-6">
                        <?php
            // Get file preview images
                        $previews = [];
                        if ($file_id > 0) {
                            $preview_sql = "SELECT preview_url FROM file_previews WHERE file_id = ? ORDER BY order_index";
                            $preview_stmt = mysqli_prepare($conn, $preview_sql);
                            mysqli_stmt_bind_param($preview_stmt, "i", $file_id);
                            mysqli_stmt_execute($preview_stmt);
                            $preview_result = mysqli_stmt_get_result($preview_stmt);
                            while ($row = mysqli_fetch_assoc($preview_result)) {
                                $previews[] = $row['preview_url'];
                            }
                        }
                        ?>
                        <div class="mb-8">
                            <div class="bg-gray-100 rounded-lg p-4 flex justify-center">
                                <?php if (!empty($previews)): ?>
                                    <img src="<?= sanitize($previews[0]) ?>" alt="<?= sanitize($file['title'] ?? '') ?>" class="preview-image">
                                <?php else: ?>
                                    <div class="text-gray-500 py-8">Урьдчилан харах зураг байхгүй</div>
                                <?php endif; ?>
                            </div>

                            <?php if (count($previews) > 1): ?>
                                <div class="flex justify-center mt-4 space-x-4">
                                    <button class="text-gray-500 hover:text-purple-600 prev-preview">
                                        <i class="fas fa-chevron-left mr-1"></i> Өмнөх
                                    </button>
                                    <span class="text-gray-500" id="preview-counter">1 / <?= count($previews) ?></span>
                                    <button class="text-gray-500 hover:text-purple-600 next-preview">
                                        Дараах <i class="fas fa-chevron-right ml-1"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- File Details -->
                        <div class="space-y-4">
                            <div class="text-gray-600 description-content">
                                <?= ($file['description'] ?? 'No description available') ?>
                            </div>

                            <div>
                                <h3 class="font-semibold text-gray-800 mb-2">Техникийн мэдээлэл</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">Файлын төрөл</p>
                                        <p class="text-gray-800"><?= strtoupper($file['file_type'] ?? 'N/A') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Хэмжээ</p>
                                        <p class="text-gray-800"><?= format_file_size($file['file_size'] ?? 0) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Үнэ</p>
                                        <p class="text-gray-800"><?= number_format($file['price'] ?? 0, 0) ?>₮</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Хандалт</p>
                                        <p class="text-gray-800"><?= ucfirst($file['access_level'] ?? 'public') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Discussion Tab Content -->
                    <div class="mb-6">
                        <?php
            // Get comments for this file
                        $comments = [];
                        if ($file_id > 0) {
                            $comment_sql = "SELECT c.*, u.username, u.avatar_url 
                            FROM comments c
                            JOIN users u ON c.user_id = u.id
                            WHERE c.file_id = ? AND c.parent_comment_id IS NULL
                            ORDER BY c.comment_date DESC";
                            $comment_stmt = mysqli_prepare($conn, $comment_sql);
                            mysqli_stmt_bind_param($comment_stmt, "i", $file_id);
                            mysqli_stmt_execute($comment_stmt);
                            $comment_result = mysqli_stmt_get_result($comment_stmt);
                            while ($comment = mysqli_fetch_assoc($comment_result)) {
                                $comments[$comment['id']] = $comment;

                    // Get replies for this comment
                                $reply_sql = "SELECT c.*, u.username, u.avatar_url 
                                FROM comments c
                                JOIN users u ON c.user_id = u.id
                                WHERE c.parent_comment_id = ?
                                ORDER BY c.comment_date ASC";
                                $reply_stmt = mysqli_prepare($conn, $reply_sql);
                                mysqli_stmt_bind_param($reply_stmt, "i", $comment['id']);
                                mysqli_stmt_execute($reply_stmt);
                                $reply_result = mysqli_stmt_get_result($reply_stmt);
                                while ($reply = mysqli_fetch_assoc($reply_result)) {
                                    $comments[$comment['id']]['replies'][] = $reply;
                                }
                            }
                        }

                        $comment_count = count($comments);
                        ?>
                        <h3 class="font-semibold text-gray-800 mb-4">Сэтгэгдэл (<?= $comment_count ?>)</h3>

                        <!-- Comment Form -->
                        <div class="mb-6">
                            <form method="POST" action="add_comment.php">
                                <input type="hidden" name="file_id" value="<?= $file_id ?>">
                                <textarea name="comment" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-purple-500" rows="3" placeholder="Сэтгэгдэл үлдээх..." required></textarea>
                                <div class="flex justify-end mt-2">
                                    <button type="submit" class="gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700">
                                        Илгээх
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Comments List -->
                        <div class="space-y-4">
                            <?php foreach ($comments as $comment): ?>
                                <!-- Main Comment -->
                                <div class="flex">
                                    <img src="<?= sanitize($comment['avatar_url'] ?? 'http://kok.mn/member/photo/avatar.png') ?>" 
                                    alt="<?= sanitize($comment['username']) ?>" class="w-10 h-10 rounded-full mr-3">
                                    <div class="flex-1">
                                        <div class="bg-gray-50 p-3 rounded-lg">
                                            <div class="flex justify-between items-start mb-1">
                                                <a href="user-profile.php?user=<?= $comment['user_id'] ?>" class="font-medium text-gray-800 hover:text-purple-600">
                                                    <?= sanitize($comment['username']) ?>
                                                </a>
                                                <span class="text-xs text-gray-500">
                                                    <?= date('Y-m-d H:i', strtotime($comment['comment_date'])) ?>
                                                </span>
                                            </div>
                                            <p class="text-gray-600"><?= sanitize($comment['comment']) ?></p>
                                            <div class="flex items-center mt-2 space-x-4">
                                                <button class="text-xs text-gray-500 hover:text-purple-600 reply-btn" data-comment-id="<?= $comment['id'] ?>">
                                                    Хариулах
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Replies -->
                                <?php if (!empty($comment['replies'])): ?>
                                    <?php foreach ($comment['replies'] as $reply): ?>
                                        <div class="flex ml-12">
                                            <img src="<?= sanitize($reply['avatar_url'] ?? 'http://kok.mn/member/photo/avatar.png') ?>" 
                                            alt="<?= sanitize($reply['username']) ?>" class="w-8 h-8 rounded-full mr-3">
                                            <div class="flex-1">
                                                <div class="bg-gray-50 p-3 rounded-lg">
                                                    <div class="flex justify-between items-start mb-1">
                                                        <a href="user-profile.php?user=<?= $reply['user_id'] ?>" class="font-medium text-gray-800 hover:text-purple-600">
                                                            <?= sanitize($reply['username']) ?>
                                                        </a>
                                                        <span class="text-xs text-gray-500">
                                                            <?= date('Y-m-d H:i', strtotime($reply['comment_date'])) ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-gray-600"><?= sanitize($reply['comment']) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if (empty($comments)): ?>
                                <p class="text-gray-500 text-center py-4">Сэтгэгдэл байхгүй байна</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- History Tab Content -->
                    <div>
                        <h3 class="font-semibold text-gray-800 mb-4">Файлын хувилбарын түүх</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Хувилбар</th>
                                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Огноо</th>
                                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Хэмжээ</th>
                                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Өөрчлөлт</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                        // Get file versions
                                    $versions = [];
                                    if ($file_id > 0) {
                                        $version_sql = "SELECT * FROM file_versions WHERE file_id = ? ORDER BY upload_date DESC";
                                        $version_stmt = mysqli_prepare($conn, $version_sql);
                                        mysqli_stmt_bind_param($version_stmt, "i", $file_id);
                                        mysqli_stmt_execute($version_stmt);
                                        $version_result = mysqli_stmt_get_result($version_stmt);
                                        while ($version = mysqli_fetch_assoc($version_result)) {
                                            $versions[] = $version;
                                        }
                                    }

                                    if (!empty($versions)): ?>
                                        <?php foreach ($versions as $version): ?>
                                            <tr>
                                                <td class="py-2 px-4 border-b border-gray-200"><?= sanitize($version['version']) ?></td>
                                                <td class="py-2 px-4 border-b border-gray-200"><?= date('Y-m-d', strtotime($version['upload_date'])) ?></td>
                                                <td class="py-2 px-4 border-b border-gray-200"><?= format_file_size($version['file_size']) ?></td>
                                                <td class="py-2 px-4 border-b border-gray-200"><?= sanitize($version['change_log']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="py-4 text-center text-gray-500">Хувилбарын түүх байхгүй</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <h3 class="font-semibold text-gray-800 mt-6 mb-4">Үнэлгээний түүх</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <?php
        // Get rating statistics
                            $rating_stats = [
                                'total' => 0,
                                'average' => 0,
                                'counts' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
                                'user_rating' => null
                            ];

                            if ($file_id > 0) {
            // Get average rating
                                $avg_sql = "SELECT AVG(rating) as avg_rating FROM ratings WHERE file_id = ?";
                                $avg_stmt = mysqli_prepare($conn, $avg_sql);
                                mysqli_stmt_bind_param($avg_stmt, "i", $file_id);
                                mysqli_stmt_execute($avg_stmt);
                                $avg_result = mysqli_fetch_assoc(mysqli_stmt_get_result($avg_stmt));
                                $rating_stats['average'] = round($avg_result['avg_rating'] ?? 0, 1);

            // Get rating counts
                                $count_sql = "SELECT rating, COUNT(*) as count FROM ratings WHERE file_id = ? GROUP BY rating";
                                $count_stmt = mysqli_prepare($conn, $count_sql);
                                mysqli_stmt_bind_param($count_stmt, "i", $file_id);
                                mysqli_stmt_execute($count_stmt);
                                $count_result = mysqli_stmt_get_result($count_stmt);

                                while ($row = mysqli_fetch_assoc($count_result)) {
                                    $rating_stats['counts'][$row['rating']] = $row['count'];
                                    $rating_stats['total'] += $row['count'];
                                }

            // Check if current user has rated this file
                                if (isset($_SESSION['user_id'])) {
                                    $user_rating_sql = "SELECT rating FROM ratings WHERE file_id = ? AND user_id = ?";
                                    $user_rating_stmt = mysqli_prepare($conn, $user_rating_sql);
                                    mysqli_stmt_bind_param($user_rating_stmt, "ii", $file_id, $_SESSION['user_id']);
                                    mysqli_stmt_execute($user_rating_stmt);
                                    $user_rating_result = mysqli_fetch_assoc(mysqli_stmt_get_result($user_rating_stmt));
                                    $rating_stats['user_rating'] = $user_rating_result['rating'] ?? null;
                                }
                            }
                            ?>

                            <!-- Rating Form (only for logged in users who haven't rated yet) -->
                            <?php if (isset($_SESSION['user_id']) && $rating_stats['user_rating'] === null): ?>
                                <form id="rating-form" method="POST" action="rate_file.php" class="mb-6">
                                    <input type="hidden" name="file_id" value="<?= $file_id ?>">
                                    <div class="flex items-center mb-2">
                                        <span class="mr-3 text-gray-700">Үнэлгээ өгөх:</span>
                                        <div class="star-rating flex">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <button type="button" class="star-btn text-2xl text-gray-300 hover:text-yellow-500 focus:outline-none" 
                                                data-rating="<?= $i ?>" 
                                                aria-label="Rate <?= $i ?> star">
                                                <i class="far fa-star"></i>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="rating" id="selected-rating" value="0">
                                    <button type="submit" class="ml-4 gradient-bg text-white px-3 py-1 rounded-md text-sm font-medium hover:bg-purple-700 transition">
                                        Илгээх
                                    </button>
                                </div>
                            </form>
                        <?php elseif (isset($_SESSION['user_id'])): ?>
                            <div class="mb-4 text-purple-600">
                                <i class="fas fa-check-circle mr-1"></i> Та энэ файлыг <?= $rating_stats['user_rating'] ?> одоор үнэлсэн байна.
                            </div>
                        <?php else: ?>
                            <div class="mb-4 text-gray-500">
                                Үнэлгээ өгөхийн тулд <a href="login.php" class="text-purple-600 hover:underline">нэвтэрнэ үү</a>.
                            </div>
                        <?php endif; ?>

                        <!-- Rating Statistics -->
                        <div class="flex items-center mb-4">
                            <div class="mr-4">
                                <div class="text-3xl font-bold text-gray-800"><?= $rating_stats['average'] ?></div>
                                <div class="flex">
                                    <?php
                                    $full_stars = floor($rating_stats['average']);
                                    $half_star = ($rating_stats['average'] - $full_stars) >= 0.5;

                                    for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $full_stars): ?>
                                            <i class="fas fa-star text-yellow-500"></i>
                                        <?php elseif ($half_star && $i == $full_stars + 1): ?>
                                            <i class="fas fa-star-half-alt text-yellow-500"></i>
                                        <?php else: ?>
                                            <i class="far fa-star text-yellow-500"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="text-sm text-gray-500"><?= $rating_stats['total'] ?> үнэлгээ</div>
                            </div>
                            <div class="flex-1">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <div class="flex items-center mb-1">
                                        <span class="w-10 text-sm text-gray-600"><?= $i ?> од</span>
                                        <div class="flex-1 mx-2 h-2 bg-gray-200 rounded-full">
                                            <div class="h-2 bg-yellow-500 rounded-full" style="width: <?= $rating_stats['total'] > 0 ? ($rating_stats['counts'][$i] / $rating_stats['total'] * 100) : 0 ?>%"></div>
                                        </div>
                                        <span class="w-8 text-sm text-gray-600"><?= $rating_stats['counts'][$i] ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Left Sidebar -->
            <aside class="w-full lg:w-1/4">
                <!-- File Actions Card -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6 file-info-card">
                    <div class="flex justify-between items-center mb-4">
                        <span class="bg-purple-100 text-purple-600 px-3 py-1 rounded-full text-xs font-medium">
                            <?= strtoupper($file['file_type'] ?? 'PDF') ?>
                        </span>
                        <div class="flex space-x-2">
                            <span class="text-gray-500 text-sm">
                                <i class="fas fa-eye mr-1"></i> <?= $file['view_count'] ?? 0 ?>
                            </span>
                            <span class="text-gray-500 text-sm">
                                <i class="fas fa-download mr-1"></i> <?= $file['download_count'] ?? 0 ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col items-center text-center mb-4">
                        <div class="bg-purple-100 text-purple-600 p-4 rounded-full mb-3">
                            <i class="fas fa-file-<?= get_file_icon($file['file_type']) ?> text-3xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($file['title'] ?? 'Untitled') ?></h3>
                        <p class="text-gray-600 text-sm">
                            <?= strtoupper($file['file_type']) ?>, 
                            <!-- Page count logic would require additional database field -->
                            15.4MB
                        </p>
                        <?php if (($file['price'] ?? 0) == 0): ?>
                            <div class="price-tag mt-2 bg-green-100 text-green-800">Үнэгүй</div>
                        <?php else: ?>
                            <div class="price-tag mt-2"><?= number_format($file['price'] ?? 0, 0) ?>₮</div>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-3 mb-6">
                        <a href="download.php?id=<?= $file_id ?>" class="block gradient-bg text-white text-center py-2 rounded-md font-medium hover:bg-purple-700 transition">
                            <i class="fas fa-download mr-2"></i> Файл татах
                        </a>

                        <!-- Share button with dropdown -->
                        <div class="relative">
                            <button class="w-full bg-gray-100 text-gray-800 py-2 rounded-md font-medium hover:bg-gray-200 transition share-toggle">
                                <i class="fas fa-share-alt mr-2"></i> Шэйр хийх
                            </button>
                            <div class="absolute hidden share-dropdown bg-white shadow-lg rounded-md p-2 w-full z-10">
                                <a href="#" class="block text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="shareOnFacebook()">Facebook</a>
                                <a href="#" class="block text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="shareOnTwitter()">Twitter</a>
                                <a href="#" class="block text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="copyLink()">Холбоос хуулах</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Similar Files -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Ижил төстэй файлууд</h3>
                    <div class="space-y-4">
                        <?php foreach ($similar_files as $similar): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 bg-purple-100 text-purple-600 p-2 rounded-md mr-3">
                                    <i class="fas fa-file-<?= get_file_icon($similar['file_type']) ?>"></i>
                                </div>
                                <div>
                                    <a href="file-details.php?id=<?= $similar['id'] ?>" class="text-sm font-medium text-gray-800 hover:text-purple-600">
                                        <?= htmlspecialchars($similar['title']) ?>
                                    </a>
                                    <p class="text-xs text-gray-500">
                                        <?= strtoupper($similar['file_type']) ?>, 
                                        <?= format_file_size($similar['file_size']) ?>
                                    </p>
                                    <div class="price-tag mt-1 inline-block">
                                        <?= number_format($similar['price'], 0) ?>₮
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>
    </div>
</main>

<!-- Footer -->
<?php include 'includes/footer.php' ?>

<script>
        // Tab switching functionality
    document.addEventListener('DOMContentLoaded', function() {

            // Rating stars functionality
        const stars = document.querySelectorAll('.star-rating i');
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                stars.forEach((s, i) => {
                    if(i <= index) {
                        s.classList.add('text-yellow-500');
                        s.classList.remove('text-gray-300');
                    } else {
                        s.classList.add('text-gray-300');
                        s.classList.remove('text-yellow-500');
                    }
                });
            });
        });

        const starButtons = document.querySelectorAll('.star-btn');
        const selectedRatingInput = document.getElementById('selected-rating');
        
        if (starButtons.length > 0) {
            starButtons.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    
                // Update star display
                    starButtons.forEach((s, i) => {
                        if (i < rating) {
                            s.innerHTML = '<i class="fas fa-star"></i>';
                            s.classList.remove('text-gray-300');
                            s.classList.add('text-yellow-500');
                        } else {
                            s.innerHTML = '<i class="far fa-star"></i>';
                            s.classList.add('text-gray-300');
                            s.classList.remove('text-yellow-500');
                        }
                    });
                    
                // Update hidden input value
                    selectedRatingInput.value = rating;
                });
            });
        }
    });
    let currentPreviewIndex = 0;
    const previewImages = <?= json_encode($previews) ?>;

    document.querySelector('.prev-preview')?.addEventListener('click', function() {
        if (currentPreviewIndex > 0) {
            currentPreviewIndex--;
            updatePreview();
        }
    });

    document.querySelector('.next-preview')?.addEventListener('click', function() {
        if (currentPreviewIndex < previewImages.length - 1) {
            currentPreviewIndex++;
            updatePreview();
        }
    });

    function updatePreview() {
        if (previewImages.length > 0) {
            document.querySelector('.preview-image').src = previewImages[currentPreviewIndex];
            document.querySelector('#preview-counter').textContent = `${currentPreviewIndex + 1} / ${previewImages.length}`;
        }
    }
    // Share dropdown functionality
    document.querySelector('.share-toggle')?.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelector('.share-dropdown').classList.toggle('hidden');
    });

// Close the dropdown when clicking outside
    document.addEventListener('click', function() {
        document.querySelector('.share-dropdown').classList.add('hidden');
    });

// Share functions
    function shareOnFacebook() {
        const url = encodeURIComponent(window.location.href);
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank');
    }

    function shareOnTwitter() {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent('Check out this file!');
        window.open(`https://twitter.com/intent/tweet?text=${text}&url=${url}`, '_blank');
    }

    function copyLink() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            alert('Холбоосыг хуулав!');
        });
    }
</script>
</body>
</html>