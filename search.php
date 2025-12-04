<?php
// search.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== ҮНДСЭН ТОХИРГОО БА ФУНКЦ =====
if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
}

// ===== ДАТАБАЗТАЙ ХОЛБОГДОХ =====
$conn = db_connect();

// ===== ХАЙЛТЫН ПАРАМЕТРҮҮДИЙГ АВАХ =====
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// ===== ХУУДСАНЫ ГАРЧИГ ТОХИРУУЛАХ =====
$pageTitle = "Хайлтын үр дүн: " . htmlspecialchars($query);

// ===== HEADER-Г ДУУДАХ =====
include 'includes/header.php';

// ===== ХУУДАСЛАЛТЫН ТОХИРГОО =====
$perPage = 12; // Нэг хуудсанд харуулах файлын тоо
$offset = ($page - 1) * $perPage;

// ===== ХАЙЛТЫН ҮР ДҮНГ АВАХ ЛОГИК =====
$files = [];
$totalFiles = 0;
$totalPages = 0;

if (!empty($query)) {
    // 1. Нийт тоог олох (хуудаслалтад зориулсан)
    $countSql = "SELECT COUNT(DISTINCT f.id) 
                 FROM files f 
                 LEFT JOIN users u ON f.user_id = u.id
                 LEFT JOIN file_tags ft ON f.id = ft.file_id
                 LEFT JOIN tags t ON ft.tag_id = t.id
                 WHERE (f.title LIKE ? OR f.description LIKE ? OR u.username LIKE ? OR t.name LIKE ?)";
    
    $stmt_count = mysqli_prepare($conn, $countSql);
    $searchTerm = "%{$query}%";
    mysqli_stmt_bind_param($stmt_count, "ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    mysqli_stmt_execute($stmt_count);
    $countResult = mysqli_stmt_get_result($stmt_count);
    $totalFiles = mysqli_fetch_array($countResult)[0] ?? 0;
    $totalPages = ceil($totalFiles / $perPage);
    mysqli_stmt_close($stmt_count);

    // 2. Тухайн хуудасны файлуудыг авах (SQL засагдсан хэсэг)
    $sql = "SELECT f.id, f.title, f.file_type, f.file_size, f.price, 
                   f.view_count, f.download_count, f.upload_date,
                   u.username,
                   MAX(fp.preview_url) as preview_url, -- ЭНЭ МӨРӨНД ЗАСВАР ОРСОН
                   AVG(r.rating) AS avg_rating
            FROM files f
            LEFT JOIN users u ON f.user_id = u.id
            LEFT JOIN file_tags ft ON f.id = ft.file_id
            LEFT JOIN tags t ON ft.tag_id = t.id
            LEFT JOIN file_previews fp ON f.id = fp.file_id
            LEFT JOIN ratings r ON f.id = r.file_id
            WHERE (f.title LIKE ? OR f.description LIKE ? OR u.username LIKE ? OR t.name LIKE ?)
            GROUP BY f.id, f.title, f.file_type, f.file_size, f.price, f.view_count, f.download_count, f.upload_date, u.username -- GROUP BY-д багана нэмсэн
            ORDER BY f.upload_date DESC
            LIMIT ? OFFSET ?";
            
    $stmt_files = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt_files, "ssssii", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $perPage, $offset);
    mysqli_stmt_execute($stmt_files);
    $result = mysqli_stmt_get_result($stmt_files);
    while($row = mysqli_fetch_assoc($result)) {
        $files[] = $row;
    }
    mysqli_stmt_close($stmt_files);
}
?>

<main class="container mx-auto px-4 py-8">
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Хайлтын үр дүн</h1>
        <?php if (!empty($query)): ?>
            <p class="text-gray-600 max-w-2xl mx-auto">
                "<?= htmlspecialchars($query) ?>" гэсэн түлхүүр үгээр <span class="font-bold text-purple-600"><?= $totalFiles ?></span> ширхэг файл олдлоо.
            </p>
        <?php else: ?>
            <p class="text-gray-600 max-w-2xl mx-auto">Хайх утгаа оруулна уу.</p>
        <?php endif; ?>
    </div>
    
    <div class="flex flex-col lg:flex-row gap-8">
            <?php include 'includes/aside.php'; ?>

        <div class="w-full lg:w-2/3">
            <?php if (!empty($files)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($files as $file): ?>
                        <div class="file-card bg-white rounded-lg shadow-md overflow-hidden transform hover:-translate-y-1 transition-all duration-300">
                            <a href="file-details.php?id=<?= $file['id'] ?>" class="block">
                                <div class="h-40 bg-gray-200 flex items-center justify-center">
                                    <img src="<?= htmlspecialchars($file['preview_url'] ?? 'assets/images/default_image.jpg') ?>" alt="<?= htmlspecialchars($file['title']) ?>" class="h-full w-full object-cover">
                                </div>
                                <div class="p-4">
                                    <h3 class="font-bold text-gray-800 text-md truncate" title="<?= htmlspecialchars($file['title']) ?>"><?= htmlspecialchars($file['title']) ?></h3>
                                    <p class="text-gray-600 text-sm mt-1">Нэмсэн: <?= htmlspecialchars($file['username']) ?></p>
                                    <div class="flex justify-between items-center mt-3">
                                        <span class="text-blue-600 font-bold"><?= ($file['price'] == 0) ? 'Үнэгүй' : number_format($file['price']).'₮' ?></span>
                                        <div class="flex items-center text-sm text-gray-500">
                                            <i class="fas fa-download mr-1"></i>
                                            <span><?= number_format($file['download_count']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-center">
                        <nav class="inline-flex rounded-md shadow">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?q=<?= urlencode($query) ?>&page=<?= $i ?>" class="py-2 px-4 border border-gray-300 text-sm font-medium <?= ($i == $page) ? 'bg-purple-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php elseif(!empty($query)): ?>
                <div class="bg-white rounded-lg shadow-md p-12 text-center">
                    <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700">Илэрц олдсонгүй</h3>
                    <p class="text-gray-500 mt-2">Таны хайлтад тохирох файл олдсонгүй. Өөр түлхүүр үгээр хайгаад үзнэ үү.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
// Footer-г дуудах
include 'includes/footer.php';

// Хуудасны төгсгөлд датабаазын холболтыг хаах
if (isset($conn)) {
    mysqli_close($conn);
}
?>