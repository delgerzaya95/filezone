<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'includes/functions.php';
$conn = db_connect();

// Initialize user data
$user_avatar = '../assets/images/avatar.png';
$username = '';

// Fetch user data if logged in
if (isset($_SESSION['user_id'])) {
    $sql = "SELECT username, avatar_url FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user_data = mysqli_fetch_assoc($result)) {
        $username = $user_data['username'];
        // Use custom avatar if exists, otherwise default
        // file_exists()-г хассан зөв хувилбар
        $user_avatar = !empty($user_data['avatar_url']) 
        ? $user_data['avatar_url'] 
        : 'assets/images/default-avatar.png';
        
        // Update session data
        $_SESSION['username'] = $username;
        $_SESSION['avatar'] = $user_avatar;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="YO_MaBo2Yl2Ne2o0QM2GCMPz2vxWtmrOC25mN2pSc20" />
    <title>Filezone.mn - Файлын дэлгүүр</title>
    <link rel="icon" type="image/png" href="../icons/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/resumablejs@1.1.0/resumable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/styles.css">

</head>
<body class="bg-gray-50 font-sans">
    <div id="loader-wrapper">
        <div class="loader"></div>
            <p class="loading-text">Ачааллаж байна...</p>
        </div>
    <!-- Top Bar -->
    <div class="bg-gray-900 text-white py-2 px-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="tel:97699815313" class="text-sm hover:text-purple-300"><i class="fas fa-phone-alt mr-1"></i> (976) 5514-5313</a>
                <a href="info@filezone.mn" class="text-sm hover:text-purple-300"><i class="fas fa-envelope mr-1"></i> info@filezone.mn</a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="https://www.facebook.com/filezonemn" target="_blank" class="text-white hover:text-purple-300">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="contact.php" class="text-sm hover:text-purple-300">Холбоо барих</a>
            </div>
        </div>
    </div>
    
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4 flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center mb-4 md:mb-0">
                <div class="logo-container">
                    <div class="logo-image">
                    </div>
                    <a href="index.php" class="logo-text">FILEZONE</a>
                </div>
                <span class="tagline">Файл байршуулах/татах вэбсайт</span>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Search form -->
                <form action="search.php" method="get" class="relative">
                    <input type="text" name="q" placeholder="Файл хайх..." 
                    class="border border-gray-300 rounded-full py-2 px-4 pl-10 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                    <button type="submit" class="absolute left-3 top-3 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                <div class="flex space-x-2">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- User is logged in - show profile dropdown -->
                        <div class="relative group">
                            <button class="flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-md transition-colors duration-200">
                                <span class="text-gray-800 font-medium">
                                    <?= htmlspecialchars($username) ?>
                                </span>
                                <img src="<?= htmlspecialchars($user_avatar) ?>" 
                                    alt="<?= htmlspecialchars($username) ?>'s profile" 
                                    class="w-8 h-8 rounded-full object-cover"
                                    onerror="this.onerror=null; this.src='assets/images/default-avatar.png';">
                            </button>

                            <div class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl py-1 z-50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform translate-y-1 group-hover:translate-y-0">
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($username) ?></p>
                                    <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></p>
                                </div>

                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-800 transition-colors duration-150">
                                    <i class="fas fa-user-circle mr-2 text-purple-600"></i> Профайл
                                </a>
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-800 transition-colors duration-150">
                                    <i class="fas fa-file-alt mr-2 text-purple-600"></i> Миний файлууд
                                </a>
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-800 transition-colors duration-150">
                                    <i class="fas fa-cog mr-2 text-purple-600"></i> Тохиргоо
                                </a>

                                <div class="border-t border-gray-100"></div>

                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors duration-150">
                                    <i class="fas fa-sign-out-alt mr-2 text-red-500"></i> Гарах
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- User is not logged in - show login/register buttons -->
                        <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">Нэвтрэх</a>
                        <a href="register.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-md text-sm font-medium">Бүртгүүлэх</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- SECONDARY NAVIGATION BAR -->
    <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 py-3 shadow-lg">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-6">

                <a href="Kids/" class="bg-white/90 hover:bg-white transition-all duration-300 rounded-lg p-3 flex flex-col items-center text-center shadow-md transform hover:scale-105">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-400 to-green-600 flex items-center justify-center mb-2">
                        <i class="fas fa-book-open text-white text-xl"></i>
                    </div>
                    <span class="font-bold text-gray-800">FILEZONE ҮЛГЭР</span>
                    <span class="text-xs text-gray-600 mt-1">Хүүхдийн номын сан</span>
                </a>

                <?php
                // Энэ хэсэг нь датабаазаас хамгийн сүүлд нэмэгдсэн файлыг автоматаар авч харуулна
                $latest_file_sql = "SELECT id, title FROM files WHERE status = 'approved' ORDER BY upload_date DESC LIMIT 1";
                $latest_file_result = mysqli_query($conn, $latest_file_sql);
                $latest_file = mysqli_fetch_assoc($latest_file_result);
                ?>
                <a href="file-details.php?id=<?= $latest_file['id'] ?? '#' ?>" class="bg-white/90 hover:bg-white transition-all duration-300 rounded-lg p-3 flex flex-col items-center text-center shadow-md transform hover:scale-105">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center mb-2">
                        <i class="fas fa-star text-white text-xl"></i>
                    </div>
                    <span class="font-bold text-gray-800">ШИНЭ ФАЙЛ</span>
                    <span class="text-xs text-gray-600 mt-1 truncate w-full" title="<?= htmlspecialchars($latest_file['title'] ?? 'Шинэ файл орж ирээгүй байна') ?>">
                        <?= htmlspecialchars($latest_file['title'] ?? '...') ?>
                    </span>
                </a>

                <a href="/Tools" class="bg-white/90 hover:bg-white transition-all duration-300 rounded-lg p-3 flex flex-col items-center text-center shadow-md transform hover:scale-105">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-amber-400 to-orange-600 flex items-center justify-center mb-2">
                        <i class="fas fa-tools text-white text-xl"></i>
                    </div>
                    <span class="font-bold text-gray-800">ОНЛАЙН ХЭРЭГСЛҮҮД</span>
                    <span class="text-xs text-gray-600 mt-1">Видео татагч болон бусад</span>
                </a>

                <a href="guides.php" class="bg-white/90 hover:bg-white transition-all duration-300 rounded-lg p-3 flex flex-col items-center text-center shadow-md transform hover:scale-105">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-teal-400 to-cyan-600 flex items-center justify-center mb-2">
                        <i class="fas fa-question-circle text-white text-xl"></i>
                    </div>
                    <span class="font-bold text-gray-800">ХЭРЭГТЭЙ ЗААВАР</span>
                    <span class="text-xs text-gray-600 mt-1">Зөвлөгөө, нийтлэл</span>
                </a>
                
            </div>
        </div>
    </div>
</body>
</html>