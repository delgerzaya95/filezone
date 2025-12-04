<?php
// session_start() функц үргэлж хамгийн эхэнд байх ёстой.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === АЛДААГ ЗАССАН ХЭСЭГ START ===
// 'role' хувьсагч байгаа эсэхийг шалгаад, дараа нь утгыг нь шалгана.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
// Get recent files
$recent_files_query = "SELECT f.id, f.title, f.price, f.status, u.username 
FROM files f 
JOIN users u ON f.user_id = u.id 
ORDER BY f.upload_date DESC 
LIMIT 3";
$recent_files_result = mysqli_query($conn, $recent_files_query);
$recent_files = [];
while ($row = mysqli_fetch_assoc($recent_files_result)) {
    $recent_files[] = $row;
}
// Get total users count
$users_query = "SELECT COUNT(*) as total_users FROM users";
$users_result = mysqli_query($conn, $users_query);
$users_data = mysqli_fetch_assoc($users_result);
$total_users = $users_data['total_users'];

// Get total files count
$files_query = "SELECT COUNT(*) as total_files FROM files";
$files_result = mysqli_query($conn, $files_query);
$files_data = mysqli_fetch_assoc($files_result);
$total_files = $files_data['total_files'];

// Get total transactions amount
$transactions_query = "SELECT SUM(amount) as total_amount FROM transactions WHERE status='success'";
$transactions_result = mysqli_query($conn, $transactions_query);
$transactions_data = mysqli_fetch_assoc($transactions_result);
$total_transactions = $transactions_data['total_amount'] ? $transactions_data['total_amount'] : 0;

// Get pending files for approval
$pending_files_query = "SELECT f.id, f.title, f.file_type, f.file_size, f.price, f.status, 
u.username, u.avatar_url as user_avatar
FROM files f
JOIN users u ON f.user_id = u.id
WHERE f.status = 'pending'
ORDER BY f.upload_date DESC
LIMIT 5";
$pending_files_result = mysqli_query($conn, $pending_files_query);
$pending_files_list = [];
while ($row = mysqli_fetch_assoc($pending_files_result)) {
    $pending_files_list[] = $row;
}

// Update pending files count to use actual count
$pending_query = "SELECT COUNT(*) as pending_files FROM files WHERE status='pending'";
$pending_result = mysqli_query($conn, $pending_query);
$pending_data = mysqli_fetch_assoc($pending_result);
$pending_files = $pending_data['pending_files'];

// Static growth percentages (you can replace with real calculations later)
$user_growth = 12.3;
$files_growth = 8.7;
$transactions_growth = 15.2;
$pending_change = -3.5;
// ================================================
// Get last 7 days data for files and transactions
$file_counts = array();
$transaction_counts = array();
$chart_labels = array();

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date)); // Day names
    
    // Count files added each day
    $query = "SELECT COUNT(*) as count FROM files WHERE DATE(upload_date) = '$date'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $file_counts[] = $row['count'];
    
    // Count transactions each day
    $query = "SELECT COUNT(*) as count FROM transactions WHERE DATE(transaction_date) = '$date' AND status='success'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $transaction_counts[] = $row['count'];
}
// Get recent activities
$activities_query = "(
    SELECT
    'user' as type,
    CONCAT('Шинэ хэрэглэгч: ', u.username) COLLATE utf8mb4_unicode_ci as description,
    u.join_date as activity_date,
    u.username as user_name
    FROM users u
    ORDER BY u.join_date DESC
    LIMIT 1
)
UNION ALL
(
    SELECT
    'file' as type,
    CONCAT('Шинэ файл: ', f.title) COLLATE utf8mb4_unicode_ci as description,
    f.upload_date as activity_date,
    u.username as user_name
    FROM files f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.upload_date DESC
    LIMIT 1
)
UNION ALL
(
    SELECT
    'transaction' as type,
    CONCAT('Гүйлгээ: ', t.amount, '₮') COLLATE utf8mb4_unicode_ci as description,
    t.transaction_date as activity_date,
    u.username as user_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'success'
    ORDER BY t.transaction_date DESC
    LIMIT 1
)
UNION ALL
(
    SELECT
    'comment' as type,
    CONCAT('Сэтгэгдэл: ', SUBSTRING(c.comment, 1, 20), '...') COLLATE utf8mb4_unicode_ci as description,
    c.comment_date as activity_date,
    u.username as user_name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.comment_date DESC
    LIMIT 1
)
ORDER BY activity_date DESC
LIMIT 4";

$activities_result = mysqli_query($conn, $activities_query);
$activities = [];
while ($row = mysqli_fetch_assoc($activities_result)) {
    $activities[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filezone - Админ Панел</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#7c3aed',
                        dark: '#1e293b',
                        light: '#f8fafc',
                        admin: '#2d3748'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Admin Layout -->
    <div class="flex h-screen">

        <?php include 'sidebar.php' ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Mobile Header -->
            <header class="admin-header text-white py-4 px-6 flex items-center justify-between md:hidden">
                <button class="text-white">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-bold">Filezone</h1>
                <div>
                    <i class="fas fa-bell"></i>
                </div>
            </header>
            
            <!-- Admin Header -->
            <header class="bg-white shadow-sm py-4 px-6 hidden md:flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Хянах самбар</h2>
                    <p class="text-gray-600">Өнөөдөр: 2024 оны 4 сарын 25</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                        <span class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-4 h-4 text-xs flex items-center justify-center">3</span>
                    </div>
                    <div class="flex items-center">
                        <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                        alt="Admin" class="w-10 h-10 rounded-full">
                        <span class="ml-3 text-gray-700">Админ хэрэглэгч</span>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50 admin-content">
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Users Card -->
                    <div class="stat-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Хэрэглэгчид</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($total_users); ?></p>
                            </div>
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full">
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                        </div>
                        <p class="<?php echo $user_growth >= 0 ? 'text-green-600' : 'text-red-600'; ?> text-sm mt-2">
                            <i class="fas fa-arrow-<?php echo $user_growth >= 0 ? 'up' : 'down'; ?> mr-1"></i> 
                            <?php echo abs($user_growth); ?>% <?php echo $user_growth >= 0 ? 'өссөн' : 'буурсан'; ?>
                        </p>
                    </div>
                    
                    <!-- Files Card -->
                    <div class="stat-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Файлууд</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($total_files); ?></p>
                            </div>
                            <div class="bg-green-100 text-green-600 p-3 rounded-full">
                                <i class="fas fa-file-alt text-2xl"></i>
                            </div>
                        </div>
                        <p class="<?php echo $files_growth >= 0 ? 'text-green-600' : 'text-red-600'; ?> text-sm mt-2">
                            <i class="fas fa-arrow-<?php echo $files_growth >= 0 ? 'up' : 'down'; ?> mr-1"></i> 
                            <?php echo abs($files_growth); ?>% <?php echo $files_growth >= 0 ? 'өссөн' : 'буурсан'; ?>
                        </p>
                    </div>
                    
                    <!-- Transactions Card -->
                    <div class="stat-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Гүйлгээ</p>
                                <p class="text-3xl font-bold text-gray-800">₮<?php echo number_format($total_transactions, 2); ?></p>
                            </div>
                            <div class="bg-purple-100 text-purple-600 p-3 rounded-full">
                                <i class="fas fa-shopping-cart text-2xl"></i>
                            </div>
                        </div>
                        <p class="<?php echo $transactions_growth >= 0 ? 'text-green-600' : 'text-red-600'; ?> text-sm mt-2">
                            <i class="fas fa-arrow-<?php echo $transactions_growth >= 0 ? 'up' : 'down'; ?> mr-1"></i> 
                            <?php echo abs($transactions_growth); ?>% <?php echo $transactions_growth >= 0 ? 'өссөн' : 'буурсан'; ?>
                        </p>
                    </div>
                    
                    <!-- Pending Card -->
                    <div class="stat-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-gray-500">Хүлээгдэж буй</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $pending_files; ?></p>
                            </div>
                            <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">
                                <i class="fas fa-clock text-2xl"></i>
                            </div>
                        </div>
                        <p class="<?php echo $pending_change >= 0 ? 'text-green-600' : 'text-red-600'; ?> text-sm mt-2">
                            <i class="fas fa-arrow-<?php echo $pending_change >= 0 ? 'up' : 'down'; ?> mr-1"></i> 
                            <?php echo abs($pending_change); ?>% <?php echo $pending_change >= 0 ? 'өссөн' : 'буурсан'; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Charts and Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Chart Section -->
                    <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">Үйл ажиллагааны график</h3>
                            <div>
                                <button id="weekBtn" class="bg-blue-600 text-white px-3 py-1 rounded-md text-sm">7 хоног</button>
                                <button id="monthBtn" class="bg-gray-100 text-gray-800 px-3 py-1 rounded-md text-sm ml-2">30 хоног</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">Сүүлийн үйл ажиллагаа</h3>
                        <div class="space-y-4">
                            <?php foreach ($activities as $activity): ?>
                                <div class="flex items-start">
                                    <div class="<?php 
                                    echo $activity['type'] == 'user' ? 'bg-blue-100 text-blue-600' : 
                                    ($activity['type'] == 'file' ? 'bg-green-100 text-green-600' : 
                                       ($activity['type'] == 'transaction' ? 'bg-purple-100 text-purple-600' : 
                                       'bg-yellow-100 text-yellow-600')); ?> p-2 rounded-full mr-3">
                                    <i class="fas <?php 
                                    echo $activity['type'] == 'user' ? 'fa-user-plus' : 
                                    ($activity['type'] == 'file' ? 'fa-file-upload' : 
                                       ($activity['type'] == 'transaction' ? 'fa-shopping-cart' : 
                                       'fa-comment')); ?>"></i>
                                   </div>
                                   <div>
                                    <p class="font-medium text-gray-800">
                                        <?php 
                                        echo $activity['type'] == 'user' ? 'Шинэ хэрэглэгч бүртгүүллээ' : 
                                        ($activity['type'] == 'file' ? 'Шинэ файл нэмэгдлээ' : 
                                           ($activity['type'] == 'transaction' ? 'Шинэ гүйлгээ амжилттай боллоо' : 
                                           'Шинэ сэтгэгдэл үлдээгдлээ')); ?>
                                       </p>
                                       <p class="text-sm text-gray-600">
                                        <?php echo $activity['user_name'] . ' - ' . $activity['description']; ?>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        <?php 
                                        $time_diff = time() - strtotime($activity['activity_date']);
                                        if ($time_diff < 60) {
                                            echo 'Саяхан';
                                        } elseif ($time_diff < 3600) {
                                            echo floor($time_diff / 60) . ' минут өмнө';
                                        } elseif ($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . ' цагийн өмнө';
                                        } else {
                                            echo floor($time_diff / 86400) . ' өдрийн өмнө';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Files and Users -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Recent Files -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Сүүлд нэмэгдсэн файлууд</h3>
                        <a href="#" class="text-blue-600 hover:underline text-sm">Бүгдийг харах</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 data-table">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файлын нэр</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үнэ</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($recent_files as $file): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($file['title']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($file['username']) ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?= number_format($file['price'], 2) ?>₮</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php if ($file['status'] === 'approved'): ?>
                                                <span class="badge-approved px-2 py-1 text-xs rounded-full">Баталгаажсан</span>
                                            <?php elseif ($file['status'] === 'pending'): ?>
                                                <span class="badge-pending px-2 py-1 text-xs rounded-full">Хүлээгдэж буй</span>
                                            <?php else: ?>
                                                <span class="badge-rejected px-2 py-1 text-xs rounded-full">Татгалзсан</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <a href="files.php?search=<?= urlencode($file['title']) ?>" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Сүүлд бүртгүүлсэн хэрэглэгчид</h3>
                        <a href="#" class="text-blue-600 hover:underline text-sm">Бүгдийг харах</a>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center">
                            <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                            alt="User" class="w-10 h-10 rounded-full">
                            <div class="ml-3 flex-1">
                                <h4 class="font-semibold text-gray-800">Бат-Эрдэнэ</h4>
                                <p class="text-sm text-gray-600">bat-erdene@example.com</p>
                            </div>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">Premium</span>
                        </div>

                        <div class="flex items-center">
                            <img src="https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                            alt="User" class="w-10 h-10 rounded-full">
                            <div class="ml-3 flex-1">
                                <h4 class="font-semibold text-gray-800">Мөнхзул</h4>
                                <p class="text-sm text-gray-600">munkhzul@example.com</p>
                            </div>
                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs">Энгийн</span>
                        </div>

                        <div class="flex items-center">
                            <img src="https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                            alt="User" class="w-10 h-10 rounded-full">
                            <div class="ml-3 flex-1">
                                <h4 class="font-semibold text-gray-800">Болдбаатар</h4>
                                <p class="text-sm text-gray-600">boldbaatar@example.com</p>
                            </div>
                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs">Энгийн</span>
                        </div>

                        <div class="flex items-center">
                            <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                            alt="User" class="w-10 h-10 rounded-full">
                            <div class="ml-3 flex-1">
                                <h4 class="font-semibold text-gray-800">Энхтайван</h4>
                                <p class="text-sm text-gray-600">enkhtaiwan@example.com</p>
                            </div>
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">Шинэ</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <!-- Pending Approvals -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Хүлээгдэж буй файлууд</h3>
                    <span class="bg-red-500 text-white rounded-full px-3 py-1 text-sm"><?php echo $pending_files; ?> ширхэг</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 data-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файлын нэр</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Хэрэглэгч</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үнэ</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($pending_files_list as $file): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($file['title']); ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo strtoupper($file['file_type']); ?>, 
                                            <?php echo round($file['file_size'] / 1000000, 1); ?>MB
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <img class="h-8 w-8 rounded-full" src="<?php echo htmlspecialchars($file['user_avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=50&q=80'); ?>" alt="">
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($file['username']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <span class="font-bold"><?php echo number_format($file['price'], 2); ?>₮</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                        <form method="POST" action="files.php" class="inline">
                                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                            <button type="submit" name="approve_file" class="text-green-600 hover:text-green-900 mr-3">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="files.php" class="inline">
                                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                            <button type="submit" name="reject_file" class="text-red-600 hover:text-red-900 mr-3">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        <button onclick="openEditModal(
                                            <?php echo $file['id']; ?>, 
                                            '<?php echo addslashes($file['title']); ?>',
                                            '',
                                            <?php echo $file['price']; ?>,
                                            'pending',
                                            0
                                            )" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<!-- Edit File Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-3">
            <h3 class="text-xl font-bold">Файл засах</h3>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" action="files.php">
            <input type="hidden" name="file_id" id="edit_file_id">
            <input type="hidden" name="edit_file" value="1">
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Гарчиг</label>
                <input type="text" name="title" id="edit_title" class="w-full px-3 py-2 border rounded-md" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Тайлбар</label>
                <textarea name="description" id="edit_description" class="w-full px-3 py-2 border rounded-md" rows="3"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Үнэ (₮)</label>
                <input type="number" step="0.01" name="price" id="edit_price" class="w-full px-3 py-2 border rounded-md" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Статус</label>
                <select name="status" id="edit_status" class="w-full px-3 py-2 border rounded-md">
                    <option value="pending">Хүлээгдэж буй</option>
                    <option value="approved">Баталгаажсан</option>
                    <option value="rejected">Татгалзсан</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_premium" id="edit_premium" class="form-checkbox">
                    <span class="ml-2">Premium файл</span>
                </label>
            </div>
            
            <div class="flex justify-end pt-2">
                <button type="button" onclick="closeEditModal()" class="mr-3 px-4 py-2 bg-gray-200 rounded-md">Цуцлах</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">Хадгалах</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, title, description, price, status, isPremium) {
    document.getElementById('edit_file_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_premium').checked = isPremium ? true : false;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<script>
        // Simple JavaScript for interactive elements
    document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.md\\:hidden button');
        const sidebar = document.querySelector('.admin-sidebar');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });
        }

            // Update current year in footer
        const yearElement = document.getElementById('current-year');
        if (yearElement) {
            yearElement.textContent = new Date().getFullYear();
        }
    });
    const activityData = {
        week: {
            labels: ['Даваа', 'Мягмар', 'Лхагва', 'Пүрэв', 'Баасан', 'Бямба', 'Ням'],
            datasets: [{
                label: 'Файл нэмэгдсэн',
                data: [12, 19, 8, 15, 12, 5, 9],
                backgroundColor: 'rgba(79, 70, 229, 0.2)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }, {
                label: 'Гүйлгээ',
                data: [8, 15, 12, 17, 14, 10, 13],
                backgroundColor: 'rgba(124, 58, 237, 0.2)',
                borderColor: 'rgba(124, 58, 237, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        month: {
            labels: ['1-7', '8-14', '15-21', '22-28', '29-30'],
            datasets: [{
                label: 'Файл нэмэгдсэн',
                data: [45, 60, 52, 70, 25],
                backgroundColor: 'rgba(79, 70, 229, 0.2)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }, {
                label: 'Гүйлгээ',
                data: [38, 55, 60, 65, 20],
                backgroundColor: 'rgba(124, 58, 237, 0.2)',
                borderColor: 'rgba(124, 58, 237, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        }
    };

    // Initialize Chart
    const ctx = document.getElementById('activityChart').getContext('2d');
    const activityChart = new Chart(ctx, {
        type: 'line',
        data: activityData.week,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Button event handlers
    document.getElementById('weekBtn').addEventListener('click', function() {
        activityChart.data = activityData.week;
        activityChart.update();
        this.classList.remove('bg-gray-100', 'text-gray-800');
        this.classList.add('bg-blue-600', 'text-white');
        document.getElementById('monthBtn').classList.remove('bg-blue-600', 'text-white');
        document.getElementById('monthBtn').classList.add('bg-gray-100', 'text-gray-800');
    });

    document.getElementById('monthBtn').addEventListener('click', function() {
        activityChart.data = activityData.month;
        activityChart.update();
        this.classList.remove('bg-gray-100', 'text-gray-800');
        this.classList.add('bg-blue-600', 'text-white');
        document.getElementById('weekBtn').classList.remove('bg-blue-600', 'text-white');
        document.getElementById('weekBtn').classList.add('bg-gray-100', 'text-gray-800');
    });
</script>
</body>
</html>