<?php
// Kids/index.php

// ===== CONFIGURATION AND DATABASE CONNECTION =====
// Энэ хэсэг нь config.php болон functions.php-ийн үүргийг гүйцэтгэнэ
define('DB_HOST', 'localhost');
define('DB_USER', 'filezone_kids');
define('DB_PASS', 'Filezone.mn@2025');
define('DB_NAME', 'filezone_kids');
define('MAIN_DB_NAME', 'filezone_kids');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to connect to the main database
function db_connect_main() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, MAIN_DB_NAME);
    if (!$conn) { die("Main DB Connection Failed: " . mysqli_connect_error()); }
    mysqli_set_charset($conn, "utf8mb4");
    return $conn;
}

// Function to connect to the kids database
function db_connect_kids() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) { die("Kids DB Connection Failed: " . $conn->connect_error); }
    $conn->set_charset("utf8mb4");
    return $conn;
}


// ===== KIDS/INDEX.PHP PAGE LOGIC =====
$pageTitle = "Filezone Үлгэр - Хүүхдийн номын сан";

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$age_group = isset($_GET['age_group']) ? $_GET['age_group'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$books = [];
$categories = [];
$age_groups = [];

$kids_conn = db_connect_kids();
if ($kids_conn) {
    $sql = "SELECT id, title, cover_image, description, age_group, type_id, type_name, view_count, read_count FROM books WHERE 1=1";
    $params = [];
    $types = "";

    if ($category_id > 0) { $sql .= " AND type_id = ?"; $params[] = $category_id; $types .= "i"; }
    if (!empty($age_group)) { $sql .= " AND age_group = ?"; $params[] = $age_group; $types .= "s"; }
    if (!empty($search_query)) { $sql .= " AND title LIKE ?"; $search_param = "%" . $search_query . "%"; $params[] = $search_param; $types .= "s"; }
    $sql .= " ORDER BY created_at DESC LIMIT 24";

    $stmt = $kids_conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) { while ($row = $result->fetch_assoc()) { $books[] = $row; } }
    $stmt->close();

    $category_result = $kids_conn->query("SELECT DISTINCT type_id, type_name FROM books WHERE type_id IS NOT NULL AND type_name IS NOT NULL AND type_name != '' ORDER BY type_name");
    if ($category_result->num_rows > 0) { while ($row = $category_result->fetch_assoc()) { $categories[] = $row; } }

    $age_result = $kids_conn->query("SELECT DISTINCT age_group FROM books WHERE age_group IS NOT NULL AND age_group != '' ORDER BY age_group");
    if ($age_result->num_rows > 0) { while ($row = $age_result->fetch_assoc()) { $age_groups[] = $row['age_group']; } }
    $kids_conn->close();
}
?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="facebook-domain-verification" content="4kh4ejzqppikytzs9zt0be6x2aop85" />
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ===== ӨӨРЧЛӨЛТ: Smooth scroll эффект нэмсэн ===== */
        html {
            scroll-behavior: smooth;
        }
        body {
            font-family: 'Fredoka', sans-serif;
        }
        .kids-header {
            background: linear-gradient(135deg, #FFD54F 0%, #FFAB00 100%);
            border-bottom: 4px solid #F57F17;
        }
        .kids-logo-text {
            font-size: 2rem;
            font-weight: 700;
            color: #4E342E; /* Brown color */
            text-shadow: 2px 2px 0px rgba(255,255,255,0.5);
        }
        .kids-logo-icon {
            color: #F9A825;
            text-shadow: 1px 1px 0px #C67C00;
        }
        .kids-nav a {
            color: #6D4C41; /* Brownish text */
            font-weight: 600;
            position: relative;
            transition: color 0.3s;
        }
        .kids-nav a:hover {
            color: #FFFFFF;
        }
        .kids-nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #FFFFFF;
            border-radius: 2px;
            transition: width 0.3s ease-in-out;
        }
        .kids-nav a:hover::after {
            width: 80%;
        }
        .kids-search-bar {
            background-color: rgba(255, 255, 255, 0.8);
            border: 2px solid #A1887F;
            border-radius: 50px;
            transition: all 0.3s;
        }
        .kids-search-bar:focus-within {
            box-shadow: 0 0 0 3px #FFC107;
            background-color: white;
        }
        .kids-login-btn {
            background-color: #29B6F6;
            color: white;
            border-bottom: 3px solid #0288D1;
            transition: all 0.2s;
        }
        .kids-login-btn:hover {
            background-color: #03A9F4;
            transform: translateY(-2px);
        }
        .user-menu-kids {
            background-color: #81D4FA;
        }
    </style>
</head>
<body class="bg-blue-50 font-sans">
    <div class="bg-gray-900 text-white py-2 px-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4"><a href="tel:97655145313" class="text-sm hover:text-purple-300"><i class="fas fa-phone-alt mr-1"></i> (976) 5514-5313</a><a href="mailto:info@filezone.mn" class="text-sm hover:text-purple-300"><i class="fas fa-envelope mr-1"></i> info@filezone.mn</a></div>
            <div class="flex items-center space-x-4"><a href="https://www.facebook.com/filezonemn" target="_blank" class="text-white hover:text-purple-300"><i class="fab fa-facebook-f"></i></a><a href="/contact.php" class="text-sm hover:text-purple-300">Холбоо барих</a></div>
        </div>
    </div>

    <header class="kids-header shadow-lg">
        <div class="container mx-auto px-4 py-4 flex flex-col md:flex-row justify-between items-center">
            <a href="/Kids/" class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-sun fa-3x kids-logo-icon mr-2"></i>
                <span class="kids-logo-text">Filezone Үлгэр</span>
            </a>

            <nav class="kids-nav flex items-center space-x-6 mb-4 md:mb-0">
                <a href="/Kids/">Нүүр</a>
                <a href="#categories">Ангилал</a>
                <a href="#age-groups">Насны ангилал</a>
            </nav>
            
            <div class="flex items-center space-x-4">
                <form action="index.php" method="get" class="relative">
                    <div class="kids-search-bar flex items-center px-4 py-1">
                        <input type="text" name="search" placeholder="Үлгэр хайх..." class="bg-transparent border-none focus:ring-0 w-48 mr-2 text-gray-700 placeholder-gray-500" value="<?= htmlspecialchars($search_query) ?>">
                        <button type="submit" aria-label="Хайх" class="text-gray-500 hover:text-yellow-600 transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </header>
    <main class="container mx-auto px-4 py-8">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-bold text-gray-800 mb-4" style="font-family: 'Fredoka', sans-serif;">FILEZONE ҮЛГЭР</h1>
            <p class="text-gray-600 max-w-2xl mx-auto" style="font-family: 'Fredoka', sans-serif;">Хүүхэд багачуудын оюуныг тэлэх сонирхолтой үлгэр, домог, түүхүүдийн санд тавтай морил!</p>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <aside class="w-full lg:w-1/4">
                <div class="sticky top-6">
                    <div id="categories" class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Ангилал</h3>
                        <div class="space-y-2">
                            <a href="index.php" class="block px-4 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition <?= ($category_id == 0) ? 'bg-purple-100 text-purple-700 font-semibold' : '' ?>">Бүгд</a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="index.php?category_id=<?= $cat['type_id'] ?>" class="block px-4 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition <?= ($category_id == $cat['type_id']) ? 'bg-purple-100 text-purple-700 font-semibold' : '' ?>"><?= htmlspecialchars($cat['type_name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="age-groups" class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Насны ангилал</h3>
                        <div class="space-y-2">
                            <a href="index.php?category_id=<?= $category_id ?>" class="block px-4 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition <?= (empty($age_group)) ? 'bg-purple-100 text-purple-700 font-semibold' : '' ?>">Бүгд</a>
                            <?php foreach ($age_groups as $age): ?>
                                <a href="index.php?age_group=<?= urlencode($age) ?>" class="block px-4 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition <?= ($age_group == $age) ? 'bg-purple-100 text-purple-700 font-semibold' : '' ?>"><?= htmlspecialchars($age) ?> нас</a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="w-full lg:w-3/4">
                <?php if (!empty($books)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($books as $book): ?>
                            <div class="bg-white rounded-lg shadow-md overflow-hidden transform hover:-translate-y-1 transition-all duration-300 group">
                                <a href="read.php?id=<?= $book['id'] ?>" class="block">
                                    <div class="h-56 bg-gray-100 overflow-hidden">
                                        <img src="<?= htmlspecialchars(ltrim($book['cover_image'], '/')) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    </div>
                                    <div class="p-4">
                                        <h3 class="font-bold text-gray-800 text-md truncate" title="<?= htmlspecialchars($book['title']) ?>"><?= htmlspecialchars($book['title']) ?></h3>
                                        <div class="flex items-center justify-between mt-2"><span class="text-xs px-2 py-1 rounded-full bg-purple-100 text-purple-800"><?= htmlspecialchars($book['type_name']) ?></span><span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800"><?= htmlspecialchars($book['age_group']) ?> нас</span></div>
                                        <div class="flex items-center text-sm text-gray-500 mt-3 border-t pt-3"><span class="flex items-center"><i class="fas fa-eye mr-1.5"></i> <?= number_format($book['view_count'] ?? 0) ?></span><span class="mx-auto"></span><span class="flex items-center"><i class="fas fa-book-reader mr-1.5"></i> <?= number_format($book['read_count'] ?? 0) ?></span></div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-md p-12 text-center"><i class="fas fa-book-open text-4xl text-gray-400 mb-4"></i><h3 class="text-xl font-bold text-gray-800 mb-2">Ном олдсонгүй</h3><p class="text-gray-600 mb-6">Таны сонгосон шүүлтүүрт тохирох ном олдсонгүй.</p><a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-full transition-colors">Бүх номнуудыг харах</a></div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php
// ===== FOOTER-Т ХЭРЭГТЭЙ СТАТИСТИК МЭДЭЭЛЭЛ АВАХ (ШИНЭЧЛЭГДСЭН) =====
    $stats_conn = db_connect_kids();
    $today_date = date('Y-m-d');
    $today_visitors = 0;
    $today_readers = 0;
    $total_visitors = 0;
    $total_readers = 0;

    if ($stats_conn) {
    // --- Өнөөдрийн статистик ---
        $result = $stats_conn->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity WHERE DATE(activity_timestamp) = '$today_date'");
        $today_visitors = $result ? $result->fetch_row()[0] : 0;

        $result = $stats_conn->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity WHERE activity_type = 'read' AND DATE(activity_timestamp) = '$today_date'");
        $today_readers = $result ? $result->fetch_row()[0] : 0;

    // --- Нийт статистик ---
        $result = $stats_conn->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity");
        $total_visitors = $result ? $result->fetch_row()[0] : 0;

        $result = $stats_conn->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity WHERE activity_type = 'read'");
        $total_readers = $result ? $result->fetch_row()[0] : 0;

        $stats_conn->close();
    }
    ?>

    <footer class="bg-gray-800 text-white py-10 mt-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1">
                    <h3 class="font-bold text-lg mb-4 text-center md:text-left">Сайтын бодит статистик</h3>
                    <div class="bg-gray-700/50 p-4 rounded-lg space-y-3">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <p class="flex items-center"><i class="fas fa-users fa-fw mr-2 text-blue-400"></i><span>Өнөөдөр зочилсон:</span></p>
                            <p class="font-bold text-lg text-right"><?php echo number_format($today_visitors); ?></p>

                            <p class="flex items-center"><i class="fas fa-book-reader fa-fw mr-2 text-green-400"></i><span>Өнөөдөр уншсан:</span></p>
                            <p class="font-bold text-lg text-right"><?php echo number_format($today_readers); ?></p>
                        </div>
                        <div class="border-t border-gray-600 my-2"></div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <p class="flex items-center"><i class="fas fa-globe-asia fa-fw mr-2 text-blue-400"></i><span>Нийт зочид:</span></p>
                            <p class="font-bold text-lg text-right"><?php echo number_format($total_visitors); ?></p>

                            <p class="flex items-center"><i class="fas fa-heart fa-fw mr-2 text-green-400"></i><span>Нийт уншигчид:</span></p>
                            <p class="font-bold text-lg text-right"><?php echo number_format($total_readers); ?></p>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-2 text-center md:text-left">
                    <h3 class="font-bold text-lg mb-4">Биднийг дэмжээрэй</h3>
                    <p class="text-gray-300 mb-4 max-w-lg mx-auto md:mx-0">Таны өгсөн хандив бидэнд илүү олон сонирхолтой үлгэрийг үнэгүй хүргэхэд туслах болно. Баярлалаа!</p>
                    <div class="bg-gray-700 p-4 rounded-lg inline-block text-left">
                        <p class="text-sm text-gray-400 mb-1">Хандив хүлээн авах:</p>
                        <div class="space-y-1">
                            <p class="font-mono text-md tracking-wider">
                                <span class="text-gray-300">Хаан банк:</span>
                                <span class="font-bold text-yellow-300 ml-2">5076767189</span>
                            </p>
                            <p class="font-mono text-md">
                                <span class="text-gray-300">Хүлээн авагч:</span>
                                <span class="font-bold text-yellow-300 ml-2">Дэлгэрзаяа</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-6 text-center text-gray-400 text-sm">
                <p>Copyright © <?php echo date('Y'); ?> "FILEZONE"</p>
            </div>
        </div>
    </footer>
</body>
</html>