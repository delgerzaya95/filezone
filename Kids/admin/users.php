<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

// Өгөгдлийн сантай холбогдох
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Холболт амжилтгүй: " . $conn->connect_error);
}

// Өнөөдрийн статистик
$today = date('Y-m-d');
$today_visitors_result = $conn->query("SELECT COUNT(*) FROM user_sessions WHERE DATE(login_time) = '$today'");
$today_visitors = $today_visitors_result ? $today_visitors_result->fetch_row()[0] : 0;

// Энэ долоо хоногийн статистик
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_visitors_result = $conn->query("SELECT COUNT(*) FROM user_sessions WHERE DATE(login_time) >= '$week_start'");
$week_visitors = $week_visitors_result ? $week_visitors_result->fetch_row()[0] : 0;

// Энэ сарын статистик
$month_start = date('Y-m-01');
$month_visitors_result = $conn->query("SELECT COUNT(*) FROM user_sessions WHERE DATE(login_time) >= '$month_start'");
$month_visitors = $month_visitors_result ? $month_visitors_result->fetch_row()[0] : 0;

// Нийт статистик
$total_visitors_result = $conn->query("SELECT COUNT(*) FROM user_sessions");
$total_visitors = $total_visitors_result ? $total_visitors_result->fetch_row()[0] : 0;

// Хамгийн их уншсан номнууд
$popular_books = $conn->query("
    SELECT b.id, b.title, b.cover_image, COUNT(ur.book_id) as read_count 
    FROM books b 
    LEFT JOIN user_reading ur ON b.id = ur.book_id 
    GROUP BY b.id 
    ORDER BY read_count DESC 
    LIMIT 5
");

// Дундаж унших хугацаа
$avg_time_result = $conn->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, login_time, logout_time)) FROM user_sessions WHERE logout_time IS NOT NULL");
$avg_time_value = $avg_time_result ? $avg_time_result->fetch_row()[0] : 0;
$avg_reading_time = $avg_time_value ? round($avg_time_value, 1) : 0;

// Хэрэглэгчийн үйл ажиллагааны түүх
$recent_activities = $conn->query("
    SELECT us.user_id, us.login_time, us.logout_time, 
           TIMESTAMPDIFF(MINUTE, us.login_time, us.logout_time) as session_duration,
           (SELECT COUNT(*) FROM user_reading ur WHERE ur.user_id = us.user_id AND ur.read_date = DATE(us.login_time)) as books_read
    FROM user_sessions us 
    ORDER BY us.login_time DESC 
    LIMIT 10
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хэрэглэгчийн Статистик | Filezone Kids Админ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="flex">
        <!-- Хажуу цэс -->
        <div class="bg-gray-800 text-white w-64 min-h-screen">
            <div class="p-4 border-b border-gray-700">
                <h1 class="text-xl font-bold">
                    <i class="fas fa-book-open mr-2"></i> Filezone Kids
                </h1>
                <p class="text-sm text-gray-400">Админ Хянах Самбар</p>
            </div>
            <nav class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="index.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Хянах самбар</span>
                        </a>
                    </li>
                    <li>
                        <a href="books.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-book"></i>
                            <span>Номнууд</span>
                        </a>
                    </li>
                    <li>
                        <a href="pages.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-file-alt"></i>
                            <span>Хуудаснууд</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center space-x-2 bg-gray-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-users"></i>
                            <span>Хэрэглэгчид</span>
                        </a>
                    </li>
                    <li class="pt-4 mt-4 border-t border-gray-700">
                        <a href="../includes/logout.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Гарах</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Үндсэн агуулга -->
        <div class="flex-1 p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Хэрэглэгчийн Статистик</h1>
                <div class="text-sm text-gray-500">
                    <?php echo date('Y оны m сарын d'); ?>
                </div>
            </div>

            <!-- Статистик картууд -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Өнөөдрийн зочид</p>
                            <h3 class="text-2xl font-bold"><?php echo $today_visitors; ?></h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-user-clock text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Энэ долоо хоног</p>
                            <h3 class="text-2xl font-bold"><?php echo $week_visitors; ?></h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-calendar-week text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Энэ сар</p>
                            <h3 class="text-2xl font-bold"><?php echo $month_visitors; ?></h3>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-calendar-alt text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Нийт зочид</p>
                            <h3 class="text-2xl font-bold"><?php echo $total_visitors; ?></h3>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-users text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Дундаж унших хугацаа -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Дундаж Унших Хугацаа</h2>
                    <div class="text-center py-8">
                        <div class="text-4xl font-bold text-blue-600 mb-2"><?php echo $avg_reading_time; ?></div>
                        <p class="text-gray-600">минут</p>
                    </div>
                    <p class="text-sm text-gray-500 text-center">Нэг удаагийн сессийн дундаж хугацаа</p>
                </div>

                <!-- Онлайнд байгаа хэрэглэгчид -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Онлайнд байгаа хэрэглэгчид</h2>
                    <div class="text-center py-8">
                        <div class="text-4xl font-bold text-green-600 mb-2">0</div>
                        <p class="text-gray-600">хэрэглэгч</p>
                    </div>
                    <p class="text-sm text-gray-500 text-center">Одоо сайтад байгаа хэрэглэгчид</p>
                </div>
            </div>

            <!-- Хамгийн их уншсан номнууд -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Хамгийн Их Уншсан Номнууд</h2>
                <?php if ($popular_books && $popular_books->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while($book = $popular_books->fetch_assoc()): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <img src="../../<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                         class="w-12 h-16 object-cover rounded"
                                         onerror="this.src='../assets/images/default-book-cover.jpg'">
                                    <div>
                                        <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($book['title']); ?></h3>
                                        <p class="text-sm text-gray-500">ID: <?php echo $book['id']; ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-blue-600"><?php echo $book['read_count']; ?></div>
                                    <p class="text-sm text-gray-500">уншилт</p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-chart-line text-4xl mb-3"></i>
                        <p>Уншилтын статистик байхгүй байна</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Сүүлийн үйл ажиллагаа -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Сүүлийн Үйл Ажиллагаа</h2>
                <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Хэрэглэгч ID</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Нэвтрэх хугацаа</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Хугацаа</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Уншсан ном</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while($activity = $recent_activities->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-sm"><?php echo $activity['user_id']; ?></td>
                                        <td class="px-4 py-3 text-sm"><?php echo date('Y-m-d H:i', strtotime($activity['login_time'])); ?></td>
                                        <td class="px-4 py-3 text-sm">
                                            <?php echo $activity['session_duration'] ? $activity['session_duration'] . ' мин' : 'Тодорхойгүй'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm"><?php echo $activity['books_read']; ?> ном</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-history text-4xl mb-3"></i>
                        <p>Үйл ажиллагааны түүх байхгүй байна</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>