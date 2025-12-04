<?php
require_once 'config.php'; // Шинэ config.php файлыг дуудна

$book_id = null;
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$book_id = intval($_GET['id']);
$conn = db_connect_kids();

if (!$conn) {
    die("Сайт түр ачааллах боломжгүй байна. Түр хүлээгээд дахин оролдоно уу.");
}

// Номны мэдээллийг авах
$sql = "SELECT * FROM books WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header("Location: index.php");
    exit();
}
$book = $result->fetch_assoc();
$stmt->close();

// Үзсэн тоог нэмэгдүүлэх
$conn->query("UPDATE books SET view_count = COALESCE(view_count, 0) + 1 WHERE id = $book_id");

// Номны хуудаснуудыг авах
$sql = "SELECT * FROM book_pages WHERE book_id = ? ORDER BY page_number ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$pages_result = $stmt->get_result();
$pages = [];

if ($pages_result) {
    $pages = $pages_result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

// Дараагийн ижил төстэй номнуудыг авах
$similar_books_sql = "SELECT * FROM books WHERE type_id = ? AND id != ? ORDER BY view_count DESC LIMIT 4";
$similar_stmt = $conn->prepare($similar_books_sql);
$similar_stmt->bind_param("ii", $book['type_id'], $book_id);
$similar_stmt->execute();
$similar_books_result = $similar_stmt->get_result();
$similar_books = [];

if ($similar_books_result) {
    $similar_books = $similar_books_result->fetch_all(MYSQLI_ASSOC);
}
$similar_stmt->close();

// === УНШСАН ҮЙЛДЛИЙГ БҮРТГЭХ (ШИНЭЧЛЭГДСЭН) ===
// Энэ session-д тухайн номыг уншсан гэж бүртгэсэн бол дахин бүртгэхгүй
if (!isset($_SESSION['read_books']) || !in_array($book_id, $_SESSION['read_books'])) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $read_stmt = $conn->prepare("INSERT INTO visitor_activity (ip_address, user_agent, activity_type, book_id) VALUES (?, ?, 'read', ?)");
    $read_stmt->bind_param("ssi", $ip_address, $user_agent, $book_id);
    $read_stmt->execute();
    $read_stmt->close();

    // Уншсан тоог нэмэх
    $conn->query("UPDATE books SET read_count = COALESCE(read_count, 0) + 1 WHERE id = $book_id");

    // Session-д энэ номыг уншсан гэж тэмдэглэх
    $_SESSION['read_books'][] = $book_id;
}

// ===== FOOTER-т статистик авахын тулд холболтыг энд хаахгүй =====

?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> | Filezone Kids</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Mongolian&display=swap');

        .mongolian-font {
            font-family: 'Noto Sans Mongolian', sans-serif;
        }
        
        .book-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .story-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .page-image {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            max-width: 100%;
            height: auto;
        }

        .page-image:hover {
            transform: scale(1.02);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .story-text {
            line-height: 1.8;
            font-size: 1.1rem;
            text-align: justify;
        }
        
        .page-nav {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .fade-in-animation {
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0.5; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            height: 4px;
            border-radius: 2px;
            transition: width 0.3s ease;
        }
        
        .reading-stats {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .similar-book-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .similar-book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="book-page mongolian-font">
    <header class="bg-white/90 backdrop-blur-md shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center space-x-4 mb-4 md:mb-0">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors">
                        <i class="fas fa-home text-xl"></i>
                    </a>
                    <div class="border-l border-gray-300 h-6"></div>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($book['title']); ?></h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="reading-stats px-4 py-2">
                        <div class="flex items-center space-x-4 text-sm text-gray-700">
                            <span class="flex items-center">
                                <i class="fas fa-eye mr-1 text-purple-600"></i>
                                <?php echo $book['view_count'] ?? 0; ?> үзсэн
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-book-reader mr-1 text-green-600"></i>
                                <?php echo $book['read_count'] ?? 0; ?> уншсан
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-file-alt mr-1 text-blue-600"></i>
                                <?php echo count($pages); ?> хуудас
                            </span>
                        </div>
                    </div>
                    
                    <button onclick="shareBook()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
                        <i class="fas fa-share-alt mr-2"></i> Хуваалцах
                    </button>
                </div>
            </div>

            <?php if (!empty($pages)): ?>
            <div class="mt-4">
                <div class="flex justify-between items-center text-sm text-gray-600 mb-2">
                    <span>Уншилтын явц</span>
                    <span id="progress-text">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progress-bar" class="progress-bar h-2 rounded-full" style="width: 0%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <?php if (!empty($pages)): ?>
            <div class="max-w-6xl mx-auto">
                <?php foreach ($pages as $index => $page): ?>
                    <section id="page-<?php echo $page['page_number']; ?>" class="story-container p-8 mb-8">
                        <div class="flex flex-col lg:flex-row gap-8 items-start">
                            <div class="lg:w-1/2">
                                <?php if (!empty($page['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($page['image_url']); ?>" 
                                         alt="Хуудас <?php echo $page['page_number']; ?>" 
                                         class="page-image w-full h-auto max-h-96 object-contain mx-auto">
                                <?php else: ?>
                                    <div class="page-image w-full h-96 bg-gradient-to-br from-blue-100 to-purple-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-image text-6xl text-gray-400"></i>
                                        <span class="ml-2 text-gray-600">Зураг байхгүй</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-center mt-4">
                                    <span class="inline-block bg-blue-600 text-white px-4 py-1 rounded-full text-sm font-medium">
                                        Хуудас <?php echo $page['page_number']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="lg:w-1/2">
                                <div class="story-text text-gray-800 mongolian-font text-lg leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($page['content'])); ?>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($similar_books)): ?>
            <section class="mt-16">
                <div class="max-w-6xl mx-auto">
                    <h2 class="text-2xl font-bold text-white mb-8 text-center">Ижил төстэй номнууд</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php foreach ($similar_books as $similar_book): ?>
                            <a href="read.php?id=<?php echo $similar_book['id']; ?>" class="similar-book-card bg-white rounded-lg shadow-lg overflow-hidden block">
                                <div class="h-48 bg-gray-200 flex items-center justify-center">
                                    <img src="<?php echo htmlspecialchars($similar_book['cover_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($similar_book['title']); ?>" 
                                         class="w-full h-full object-cover"
                                         onerror="this.parentElement.innerHTML = '<i class=\'fas fa-book text-4xl text-gray-400\'></i>';">
                                </div>
                                <div class="p-4">
                                    <h3 class="font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($similar_book['title']); ?></h3>
                                    <div class="flex justify-between items-center text-sm text-gray-600">
                                        <span><?php echo $similar_book['view_count'] ?? 0; ?> үзсэн</span>
                                        <span><?php echo $similar_book['age_group'] ?? ''; ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-16">
                <div class="bg-white rounded-lg shadow-lg p-8 max-w-md mx-auto">
                    <i class="fas fa-book-open text-6xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Хуудаснууд байхгүй байна</h3>
                    <p class="text-gray-600 mb-4">Энэ номонд одоогоор хуудас нэмэгдээгүй байна.</p>
                    <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        Буцах
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php if (!empty($pages)): ?>
        <nav class="fixed bottom-8 left-1/2 transform -translate-x-1/2 z-40">
            <div class="page-nav px-6 py-3 flex items-center space-x-6">
                <button id="prev-page" class="bg-blue-600 hover:bg-blue-700 text-white w-12 h-12 rounded-full flex items-center justify-center transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="text-center">
                    <span id="current-page" class="text-lg font-bold text-gray-800">1</span>
                    <span class="text-gray-600"> / </span>
                    <span id="total-pages" class="text-lg font-bold text-gray-800"><?php echo count($pages); ?></span>
                </div>
                
                <button id="next-page" class="bg-blue-600 hover:bg-blue-700 text-white w-12 h-12 rounded-full flex items-center justify-center transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </nav>
    <?php endif; ?>

    <script>
        <?php if (!empty($pages)): ?>
        let currentPage = 1;
        const totalPages = <?php echo count($pages); ?>;
        const bookId = <?php echo $book_id; ?>;
        
        // UI (User Interface) шинэчлэх функц
        function updatePageUI(pageNumber) {
            // Хуудасны дугаар болон явцыг шинэчлэх
            document.getElementById('current-page').textContent = pageNumber;
            const progress = (pageNumber / totalPages) * 100;
            document.getElementById('progress-bar').style.width = progress + '%';
            document.getElementById('progress-text').textContent = Math.round(progress) + '%';
            
            // Товчнуудын идэвхтэй эсэхийг тохируулах
            document.getElementById('prev-page').disabled = pageNumber === 1;
            document.getElementById('next-page').disabled = pageNumber === totalPages;

            // Хуудас солигдох үеийн effect
            const pages = document.querySelectorAll('.story-container');
            pages.forEach(page => page.classList.remove('fade-in-animation'));
            const currentPageElement = document.getElementById('page-' + pageNumber);
            if (currentPageElement) {
                currentPageElement.classList.add('fade-in-animation');
            }
        }

        // Хуудас руу гүйлгэх функц
        function scrollToPage(pageNumber) {
            const pageElement = document.getElementById('page-' + pageNumber);
            if (pageElement) {
                pageElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Дараагийн хуудас руу шилжих (товч дарахад)
        function handleNextClick() {
            if (currentPage < totalPages) {
                currentPage++;
                scrollToPage(currentPage);
                updatePageUI(currentPage);
            }
        }

        // Өмнөх хуудас руу шилжих (товч дарахад)
        function handlePrevClick() {
            if (currentPage > 1) {
                currentPage--;
                scrollToPage(currentPage);
                updatePageUI(currentPage);
            }
        }
        
        // Гарын товчлуур дарахад
        function handleKeyDown(e) {
            if (e.key === 'ArrowLeft') {
                handlePrevClick();
            } else if (e.key === 'ArrowRight') {
                handleNextClick();
            }
        }
        
        // Хуваалцах функц
        function shareBook() {
            const shareUrl = window.location.href;
            const shareText = 'Энэ гоё номыг уншиж үзээрэй: ' + '<?php echo htmlspecialchars(addslashes($book['title'])); ?>';
            
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars(addslashes($book['title'])); ?>',
                    text: shareText,
                    url: shareUrl,
                });
            } else {
                navigator.clipboard.writeText(shareUrl).then(() => {
                    alert('Холбоосыг хуулав. Одоо найздаа хуваалцаж болно!');
                });
            }
        }

        // Хуудас ачаалагдахад ажиллах хэсэг
        document.addEventListener('DOMContentLoaded', function() {
            // Эхний байрлалыг тохируулах
            updatePageUI(currentPage);

            // Товч болон гарын event-үүдийг холбох
            document.getElementById('next-page').addEventListener('click', handleNextClick);
            document.getElementById('prev-page').addEventListener('click', handlePrevClick);
            document.addEventListener('keydown', handleKeyDown);

            // Scroll хийхэд хуудас мэдрэх Intersection Observer-г тохируулах
            const options = {
                root: null,
                rootMargin: '0px',
                threshold: 0.5 // Хуудасны 50% нь харагдахад мэдэрнэ
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const pageNum = parseInt(entry.target.id.split('-')[1]);
                        currentPage = pageNum;
                        updatePageUI(currentPage);
                    }
                });
            }, options);

            // Бүх хуудасны section-уудыг ажиглах
            const pageElements = document.querySelectorAll('section[id^="page-"]');
            pageElements.forEach(page => {
                observer.observe(page);
            });
        });
        <?php endif; ?>
    </script>

   <?php
    // ===== FOOTER-Т ХЭРЭГТЭЙ СТАТИСТИК МЭДЭЭЛЭЛ АВАХ =====
    $today_date = date('Y-m-d');
    $today_visitors = 0;
    $today_readers = 0;
    $total_visitors = 0;
    $total_readers = 0;

    if ($conn) {
        $result = $conn->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity WHERE DATE(activity_timestamp) = '$today_date'");
        $today_visitors = $result ? $result->fetch_row()[0] : 0;

        $result = $conn->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity WHERE activity_type = 'read' AND DATE(activity_timestamp) = '$today_date'");
        $today_readers = $result ? $result->fetch_row()[0] : 0;

        $result = $conn->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity");
        $total_visitors = $result ? $result->fetch_row()[0] : 0;
        
        $result = $conn->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity WHERE activity_type = 'read'");
        $total_readers = $result ? $result->fetch_row()[0] : 0;
        
        // Бүх ажил дууссаны дараа холболтыг хаах
        $conn->close();
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
