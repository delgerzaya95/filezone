<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

// functions.php-г оруулахгүйгээр шууд холболт үүсгэх
function create_db_connection() {
    $host = 'localhost';
    $username = 'filezone_mn';
    $password = '099da7e85a2688';
    $dbname = 'filezone_mn';
    
    $conn = mysqli_connect($host, $username, $password, $dbname);
    
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    mysqli_set_charset($conn, "utf8mb4");
    return $conn;
}

$conn = create_db_connection();

// Include header
include 'includes/header.php';

// Include navigation
include 'includes/navigation.php';

// Get parameters from URL
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$subcategory_id = isset($_GET['subcategory_id']) ? intval($_GET['subcategory_id']) : 0;
$child_category_id = isset($_GET['child_category_id']) ? intval($_GET['child_category_id']) : 0;
$sort = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'upload_date_desc';
$file_type = isset($_GET['file_type']) ? mysqli_real_escape_string($conn, $_GET['file_type']) : '';
$price_range = isset($_GET['price_range']) ? mysqli_real_escape_string($conn, $_GET['price_range']) : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Pagination settings
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build base query for files
$query = "SELECT f.id, f.title, f.file_type, f.file_size, f.price, 
f.view_count, f.download_count, f.upload_date,
u.username,
AVG(r.rating) AS avg_rating,
COUNT(r.id) AS rating_count
FROM files f
JOIN users u ON f.user_id = u.id
LEFT JOIN ratings r ON f.id = r.file_id
LEFT JOIN file_categories fc ON f.id = fc.file_id
LEFT JOIN child_category cc ON fc.subcategory_id = cc.subcategory_id";

// Apply filters
$where = [];
$params = [];
$types = '';

// Ангиллын шүүлтүүрийг тохируулах
if ($child_category_id > 0) {
    // Хэрэв жижиг ангилал сонгосон бол түүгээр шүүнэ
    $where[] = "cc.id = ?";
    $params[] = $child_category_id;
    $types .= 'i';

} elseif ($subcategory_id > 0) {
    // Хэрэв дэд ангилал сонгосон бол түүгээр шүүнэ
    $where[] = "fc.subcategory_id = ?";
    $params[] = $subcategory_id;
    $types .= 'i';

} elseif ($category_id > 0) {
    // Хэрэв үндсэн ангилал сонгосон бол түүнд хамаарах бүх дэд ангиллын файлаар шүүнэ
    $where[] = "fc.subcategory_id IN (SELECT id FROM subcategories WHERE category_id = ?)";
    $params[] = $category_id;
    $types .= 'i';
}

// Файлын төрлийн шүүлтүүрийг нэмэх
if ($file_type && $file_type !== 'all') {
    $where[] = "f.file_type = ?";
    $params[] = $file_type;
    $types .= 's';
}

// Үнийн шүүлтүүрийг нэмэх
if ($price_range !== 'all') {
    switch ($price_range) {
        case 'free':
            $where[] = "f.price = 0";
            break;
        case '0-5000':
            $where[] = "f.price > 0 AND f.price <= 5000";
            break;
        case '5000-10000':
            $where[] = "f.price > 5000 AND f.price <= 10000";
            break;
        case '10000+':
            $where[] = "f.price > 10000";
            break;
    }
}

// Build WHERE clause
if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

// Group by file
$query .= " GROUP BY f.id";

// Apply sorting
switch ($sort) {
    case 'upload_date_desc':
        $query .= " ORDER BY f.upload_date DESC";
        break;
    case 'downloads_desc':
        $query .= " ORDER BY f.download_count DESC";
        break;
    case 'rating_desc':
        $query .= " ORDER BY avg_rating DESC";
        break;
    case 'price_asc':
        $query .= " ORDER BY f.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY f.price DESC";
        break;
    default:
        $query .= " ORDER BY f.upload_date DESC";
}

// Get total files count
$countQuery = "SELECT COUNT(DISTINCT f.id) AS total_files
FROM files f
JOIN users u ON f.user_id = u.id
LEFT JOIN ratings r ON f.id = r.file_id
LEFT JOIN file_categories fc ON f.id = fc.file_id
LEFT JOIN child_category cc ON fc.subcategory_id = cc.subcategory_id";

if (!empty($where)) {
    $countQuery .= " WHERE " . implode(" AND ", $where);
}

$countStmt = mysqli_prepare($conn, $countQuery);
if (!empty($params)) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalFilesRow = mysqli_fetch_assoc($countResult);
$totalFiles = $totalFilesRow['total_files'] ?? 0;
$totalPages = ceil($totalFiles / $perPage);

// Add pagination to main query
$query .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

// Prepare and execute query
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$files = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get file icon class based on file type
function getFileIcon($file_type) {
    $icons = [
        'pdf' => 'file-pdf',
        'doc' => 'file-word',
        'docx' => 'file-word',
        'xls' => 'file-excel',
        'xlsx' => 'file-excel',
        'ppt' => 'file-powerpoint',
        'pptx' => 'file-powerpoint',
        'jpg' => 'file-image',
        'jpeg' => 'file-image',
        'png' => 'file-image',
        'zip' => 'file-archive',
        'rar' => 'file-archive'
    ];
    
    return isset($icons[$file_type]) ? $icons[$file_type] : 'file';
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Build query string for pagination
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);
?>

<main class="container mx-auto px-4 py-8">
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Файлын Жагсаалт</h1>
        <p class="text-gray-600 max-w-2xl mx-auto">
            Сонгосон ангилал дахь бүх файлуудын жагсаалт
        </p>
    </div>
    
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Categories Sidebar - aside.php-с авна -->
            <?php include 'includes/aside.php'; ?>
        
        <!-- Files List -->
        <div class="w-full md:w-3/4">
            <!-- Filters and Sorting -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center">
                <div class="mb-4 md:mb-0">
                    <span class="font-semibold text-gray-700">Нийт: <?= $totalFiles ?> файл</span>
                </div>
                <form method="get" class="flex flex-wrap gap-3">
                    <input type="hidden" name="category_id" value="<?= $category_id ?>">
                    <input type="hidden" name="subcategory_id" value="<?= $subcategory_id ?>">
                    <input type="hidden" name="child_category_id" value="<?= $child_category_id ?>">
                    
                    <select name="sort" class="border border-gray-300 rounded-md py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="upload_date_desc" <?= $sort === 'upload_date_desc' ? 'selected' : '' ?>>Сүүлд нэмэгдсэн</option>
                        <option value="downloads_desc" <?= $sort === 'downloads_desc' ? 'selected' : '' ?>>Хамгийн их татагдсан</option>
                        <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Дээд үнэлгээтэй</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Үнээр (Өсөх)</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Үнээр (Буурах)</option>
                    </select>
                    
                    <select name="file_type" class="border border-gray-300 rounded-md py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="all" <?= $file_type === 'all' ? 'selected' : '' ?>>Бүх төрлийн файл</option>
                        <option value="pdf" <?= $file_type === 'pdf' ? 'selected' : '' ?>>PDF</option>
                        <option value="doc" <?= $file_type === 'doc' ? 'selected' : '' ?>>Word</option>
                        <option value="xls" <?= $file_type === 'xls' ? 'selected' : '' ?>>Excel</option>
                        <option value="ppt" <?= $file_type === 'ppt' ? 'selected' : '' ?>>PowerPoint</option>
                        <option value="jpg" <?= $file_type === 'jpg' ? 'selected' : '' ?>>Зураг</option>
                        <option value="zip" <?= $file_type === 'zip' ? 'selected' : '' ?>>Архив</option>
                    </select>
                    
                    <select name="price_range" class="border border-gray-300 rounded-md py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="all" <?= $price_range === 'all' ? 'selected' : '' ?>>Бүх үнэ</option>
                        <option value="free" <?= $price_range === 'free' ? 'selected' : '' ?>>Үнэгүй</option>
                        <option value="0-5000" <?= $price_range === '0-5000' ? 'selected' : '' ?>>5,000₮ хүртэл</option>
                        <option value="5000-10000" <?= $price_range === '5000-10000' ? 'selected' : '' ?>>5,000-10,000₮</option>
                        <option value="10000+" <?= $price_range === '10000+' ? 'selected' : '' ?>>10,000₮-с дээш</option>
                    </select>
                    
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Шүүх
                    </button>
                </form>
            </div>
            
            <!-- Files List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- File List Header -->
                <div class="hidden md:grid grid-cols-12 bg-gray-50 border-b text-gray-600 text-sm font-medium">
                    <div class="col-span-5 py-3 px-6">Файлын нэр</div>
                    <div class="col-span-1 py-3 px-6">Төрөл</div>
                    <div class="col-span-1 py-3 px-6">Хэмжээ</div>
                    <div class="col-span-1 py-3 px-6">Татаж авсан</div>
                    <div class="col-span-2 py-3 px-6">Үнэлгээ</div>
                    <div class="col-span-1 py-3 px-6">Үнэ</div>
                    <div class="col-span-1 py-3 px-6"></div>
                </div>
                
                <!-- File Items -->
                <div class="divide-y divide-gray-200">
                    <?php if (count($files) > 0): ?>
                        <?php foreach ($files as $file): ?>
                            <?php
                            $file_icon = getFileIcon($file['file_type']);
                            $icon_colors = [
                                'file-pdf' => 'bg-red-100 text-red-600',
                                'file-word' => 'bg-blue-100 text-blue-600',
                                'file-excel' => 'bg-green-100 text-green-600',
                                'file-powerpoint' => 'bg-yellow-100 text-yellow-600',
                                'file-image' => 'bg-pink-100 text-pink-600',
                                'file-archive' => 'bg-purple-100 text-purple-600',
                                'file' => 'bg-gray-100 text-gray-600'
                            ];
                            $icon_class = $icon_colors[$file_icon] ?? $icon_colors['file'];
                            ?>
                            <div class="file-card transition-all duration-200 hover:bg-gray-50">
                                <div class="grid grid-cols-1 md:grid-cols-12 py-4 px-6">
                                    <div class="col-span-5 flex items-center">
                                        <div class="<?= $icon_class ?> p-3 rounded-lg mr-4">
                                            <i class="fas fa-<?= $file_icon ?> text-xl"></i>
                                        </div>
                                        <div>
                                            <a href="file-details.php?id=<?= $file['id'] ?>" class="font-semibold text-gray-800 hover:text-purple-600 block">
                                                <?= htmlspecialchars($file['title']) ?>
                                            </a>
                                            <div class="text-sm text-gray-600"><?= htmlspecialchars($file['username']) ?></div>
                                            <div class="text-xs text-gray-500 mt-1">Нэмсэн: <?= date('Y-m-d', strtotime($file['upload_date'])) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-span-1 flex items-center mt-2 md:mt-0">
                                        <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">
                                            <?= strtoupper($file['file_type']) ?>
                                        </span>
                                    </div>
                                    <div class="col-span-1 flex items-center mt-2 md:mt-0">
                                        <span class="text-sm text-gray-600">
                                            <?= formatFileSize($file['file_size']) ?>
                                        </span>
                                    </div>
                                    <div class="col-span-1 flex items-center mt-2 md:mt-0">
                                        <span class="text-sm text-gray-600">
                                            <i class="fas fa-download mr-1"></i> <?= $file['download_count'] ?>
                                        </span>
                                    </div>
                                    <div class="col-span-2 flex items-center mt-2 md:mt-0">
                                        <div class="flex items-center">
                                            <div class="flex text-yellow-400">
                                                <?php
                                                $rating = round($file['avg_rating'] ?? 0);
                                                $empty = 5 - $rating;
                                                
                                                for ($i = 0; $i < $rating; $i++) {
                                                    echo '<i class="fas fa-star"></i>';
                                                }
                                                
                                                for ($i = 0; $i < $empty; $i++) {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                                ?>
                                            </div>
                                            <span class="ml-2 text-sm text-gray-600">
                                                <?= number_format($file['avg_rating'] ?? 0, 1) ?> (<?= $file['rating_count'] ?>)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-span-1 flex items-center mt-2 md:mt-0">
                                        <?php if ($file['price'] > 0): ?>
                                            <span class="bg-gradient-to-r from-yellow-500 to-red-500 text-white px-2 py-1 rounded text-xs font-bold">
                                                <?= number_format($file['price']) ?>₮
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">
                                                ҮНЭГҮЙ
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-span-1 flex items-center mt-4 md:mt-0 md:justify-end">
                                        <button onclick="window.location.href='file-details.php?id=<?= $file['id'] ?>'" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                            <i class="fas fa-download mr-2"></i> Татах
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="py-12 text-center">
                            <i class="fas fa-file-excel text-gray-400 text-5xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-700">Файл олдсонгүй</h3>
                            <p class="text-gray-500 mt-2">Сонгосон шүүлтүүрт тохирох файл байхгүй байна</p>
                            <a href="categories.php" class="mt-4 inline-block bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-md">
                                Бүх файлыг үзэх
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <nav class="inline-flex rounded-md shadow">
                        <?php if ($page > 1): ?>
                            <a href="?<?= $queryString ?>&page=<?= $page - 1 ?>" class="py-2 px-4 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="py-2 px-4 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="py-2 px-4 border border-gray-300 bg-blue-600 text-sm font-medium text-white">
                                    <?= $i ?>
                                </span>
                            <?php else: ?>
                                <a href="?<?= $queryString ?>&page=<?= $i ?>" class="py-2 px-4 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= $queryString ?>&page=<?= $page + 1 ?>" class="py-2 px-4 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="py-2 px-4 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main>

<?php
// Include footer
include 'includes/footer.php';
?>