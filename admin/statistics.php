<?php

// Database connection

$conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");

if (!$conn) {

    die("Connection failed: " . mysqli_connect_error());

}

mysqli_set_charset($conn, "utf8");



// Get current date in Mongolian format

function getMongolianDate() {

    $months = [

        '1' => '1-р сар', '2' => '2-р сар', '3' => '3-р сар',

        '4' => '4-р сар', '5' => '5-р сар', '6' => '6-р сар',

        '7' => '7-р сар', '8' => '8-р сар', '9' => '9-р сар',

        '10' => '10-р сар', '11' => '11-р сар', '12' => '12-р сар'

    ];

    $day = date('j');

    $month = $months[date('n')];

    $year = date('Y');

    return "$year оны $month-ны $day";

}



// Get stats data

$stats_sql = "SELECT 

    (SELECT COUNT(*) FROM users) as total_users,

    (SELECT COUNT(*) FROM files) as total_files,

    (SELECT SUM(amount) FROM transactions WHERE status = 'success') as total_revenue,

    (SELECT AVG(rating) FROM ratings) as avg_rating,

    (SELECT COUNT(*) FROM users WHERE last_active > DATE_SUB(NOW(), INTERVAL 1 MONTH)) as active_users,

    (SELECT COUNT(*) FROM users WHERE is_premium = 1) as premium_users,

    (SELECT COUNT(*) FROM files WHERE is_premium = 1) as premium_files,

    (SELECT COUNT(*) FROM transactions) as total_transactions,

    (SELECT COUNT(*) FROM transactions WHERE status = 'pending') as pending_transactions";



$stats_result = mysqli_query($conn, $stats_sql);

$stats = mysqli_fetch_assoc($stats_result);



// User growth data (last 6 months)

$user_growth_sql = "SELECT 

    DATE_FORMAT(join_date, '%Y-%m') as month,

    COUNT(*) as count

FROM users

WHERE join_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)

GROUP BY DATE_FORMAT(join_date, '%Y-%m')

ORDER BY month ASC";



$user_growth_result = mysqli_query($conn, $user_growth_sql);

$user_growth_data = [];

$user_growth_labels = [];

while ($row = mysqli_fetch_assoc($user_growth_result)) {

    $user_growth_data[] = $row['count'];

    $user_growth_labels[] = date('M', strtotime($row['month']));

}



// Revenue data (last 6 months)

$revenue_sql = "SELECT 

    DATE_FORMAT(transaction_date, '%Y-%m') as month,

    SUM(amount) as total_amount

FROM transactions

WHERE status = 'success' 

AND transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)

GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')

ORDER BY month ASC";



$revenue_result = mysqli_query($conn, $revenue_sql);

$revenue_data = [];

$revenue_labels = [];

while ($row = mysqli_fetch_assoc($revenue_result)) {

    $revenue_data[] = $row['total_amount'];

    $revenue_labels[] = date('M', strtotime($row['month']));

}



// File uploads by day of week

$file_uploads_sql = "SELECT 

    DAYNAME(upload_date) as day,

    COUNT(*) as count

FROM files

GROUP BY DAYNAME(upload_date), DAYOFWEEK(upload_date)

ORDER BY DAYOFWEEK(upload_date)";



$file_uploads_result = mysqli_query($conn, $file_uploads_sql);

$file_uploads_data = [0, 0, 0, 0, 0, 0, 0]; // Initialize for all 7 days

$days_map = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 

             'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];

while ($row = mysqli_fetch_assoc($file_uploads_result)) {

    $day_index = $days_map[$row['day']];

    $file_uploads_data[$day_index] = $row['count'];

}



// Downloads data

$downloads_sql = "SELECT 

    DAYNAME(last_updated) as day,

    SUM(download_count) as downloads

FROM files

GROUP BY DAYNAME(last_updated), DAYOFWEEK(last_updated)

ORDER BY DAYOFWEEK(last_updated)";



$downloads_result = mysqli_query($conn, $downloads_sql);

$downloads_data = [0, 0, 0, 0, 0, 0, 0];

while ($row = mysqli_fetch_assoc($downloads_result)) {

    $day_index = $days_map[$row['day']];

    $downloads_data[$day_index] = $row['downloads'];

}



// Categories distribution

$categories_sql = "SELECT 

    c.name as category_name,

    COUNT(fc.file_id) as file_count

FROM categories c

LEFT JOIN subcategories sc ON c.id = sc.category_id

LEFT JOIN file_categories fc ON sc.id = fc.subcategory_id

GROUP BY c.id

ORDER BY file_count DESC";



$categories_result = mysqli_query($conn, $categories_sql);

$categories_data = [];

$categories_labels = [];

while ($row = mysqli_fetch_assoc($categories_result)) {

    $categories_labels[] = $row['category_name'];

    $categories_data[] = $row['file_count'];

}



// Top uploaders

$top_uploaders_sql = "SELECT 

    u.id, u.username, u.full_name, u.avatar_url,

    COUNT(f.id) as file_count,

    SUM(t.amount) as total_earnings,

    AVG(r.rating) as avg_rating

FROM users u

LEFT JOIN files f ON u.id = f.user_id

LEFT JOIN transactions t ON f.id = t.file_id AND t.status = 'success'

LEFT JOIN ratings r ON f.id = r.file_id

GROUP BY u.id

ORDER BY file_count DESC

LIMIT 3";



$top_uploaders_result = mysqli_query($conn, $top_uploaders_sql);

$top_uploaders = [];

while ($row = mysqli_fetch_assoc($top_uploaders_result)) {

    $top_uploaders[] = $row;

}



// Top files

$top_files_sql = "SELECT 

    f.id, f.title,

    f.download_count,

    SUM(t.amount) as total_earnings,

    AVG(r.rating) as avg_rating

FROM files f

LEFT JOIN transactions t ON f.id = t.file_id AND t.status = 'success'

LEFT JOIN ratings r ON f.id = r.file_id

GROUP BY f.id

ORDER BY download_count DESC

LIMIT 3";



$top_files_result = mysqli_query($conn, $top_files_sql);

$top_files = [];

while ($row = mysqli_fetch_assoc($top_files_result)) {

    $top_files[] = $row;

}

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>НАРХАН - Статистик</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" type="text/css" href="css/styles.css">

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

        <!-- Sidebar -->

        <?php include 'sidebar.php' ?>

        

        <!-- Main Content -->

        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- Mobile Header -->

            <header class="admin-header text-white py-4 px-6 flex items-center justify-between md:hidden">

                <button class="text-white">

                    <i class="fas fa-bars text-xl"></i>

                </button>

                <h1 class="text-xl font-bold">НАРХАН Админ</h1>

                <div>

                    <i class="fas fa-bell"></i>

                </div>

            </header>

            

            <!-- Admin Header -->

            <header class="bg-white shadow-sm py-4 px-6 hidden md:flex justify-between items-center">

                <div>

                    <h2 class="text-xl font-bold text-gray-800">Статистик</h2>

                    <p class="text-gray-600">Өнөөдөр: <?php echo getMongolianDate(); ?></p>

                </div>

                <div class="flex items-center space-x-4">

                    <div class="relative">

                        <i class="fas fa-bell text-gray-600 text-xl"></i>

                        <span class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-4 h-4 text-xs flex items-center justify-center"><?php echo $stats['pending_transactions']; ?></span>

                    </div>

                    <div class="flex items-center">

                        <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 

                             alt="Admin" class="w-10 h-10 rounded-full">

                        <span class="ml-3 text-gray-700">Админ хэрэглэгч</span>

                    </div>

                </div>

            </header>

            

            <!-- Main Content Area -->

            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">

                <!-- Date Range Selector -->

                <div class="bg-white rounded-lg shadow-md p-4 mb-6">

                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">

                        <h3 class="text-lg font-semibold text-gray-800 mb-2 md:mb-0">Өгөгдлийн хүрээ сонгох</h3>

                        <div class="flex flex-wrap gap-2">

                            <button class="tab-button active px-4 py-2 rounded-md text-sm" data-period="7days">

                                7 хоног

                            </button>

                            <button class="tab-button px-4 py-2 rounded-md text-sm" data-period="30days">

                                30 хоног

                            </button>

                            <button class="tab-button px-4 py-2 rounded-md text-sm" data-period="90days">

                                90 хоног

                            </button>

                            <button class="tab-button px-4 py-2 rounded-md text-sm" data-period="year">

                                Энэ жил

                            </button>

                            <button class="tab-button px-4 py-2 rounded-md text-sm" data-period="custom">

                                Гаргах

                            </button>

                        </div>

                    </div>

                </div>

                

                <!-- Stats Overview -->

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">

                    <div class="stat-card bg-white rounded-lg shadow-md p-6">

                        <div class="flex justify-between items-center">

                            <div>

                                <p class="text-gray-500">Нийт хэрэглэгчид</p>

                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_users']); ?></p>

                            </div>

                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full">

                                <i class="fas fa-users text-2xl"></i>

                            </div>

                        </div>

                        <p class="text-green-600 text-sm mt-2">

                            <i class="fas fa-arrow-up mr-1"></i> <?php echo round(($stats['active_users'] / $stats['total_users']) * 100, 1); ?>% идэвхтэй

                        </p>

                    </div>

                    

                    <div class="stat-card bg-white rounded-lg shadow-md p-6">

                        <div class="flex justify-between items-center">

                            <div>

                                <p class="text-gray-500">Нийт файлууд</p>

                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_files']); ?></p>

                            </div>

                            <div class="bg-green-100 text-green-600 p-3 rounded-full">

                                <i class="fas fa-file-alt text-2xl"></i>

                            </div>

                        </div>

                        <p class="text-green-600 text-sm mt-2">

                            <i class="fas fa-arrow-up mr-1"></i> <?php echo number_format($stats['premium_files']); ?> premium

                        </p>

                    </div>

                    

                    <div class="stat-card bg-white rounded-lg shadow-md p-6">

                        <div class="flex justify-between items-center">

                            <div>

                                <p class="text-gray-500">Нийт гүйлгээ</p>

                                <p class="text-3xl font-bold text-gray-800">₮<?php echo number_format($stats['total_revenue'], 2); ?></p>

                            </div>

                            <div class="bg-purple-100 text-purple-600 p-3 rounded-full">

                                <i class="fas fa-shopping-cart text-2xl"></i>

                            </div>

                        </div>

                        <p class="text-green-600 text-sm mt-2">

                            <i class="fas fa-arrow-up mr-1"></i> <?php echo number_format($stats['total_transactions']); ?> гүйлгээ

                        </p>

                    </div>

                    

                    <div class="stat-card bg-white rounded-lg shadow-md p-6">

                        <div class="flex justify-between items-center">

                            <div>

                                <p class="text-gray-500">Дундаж үнэлгээ</p>

                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['avg_rating'], 1); ?>/5</p>

                            </div>

                            <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">

                                <i class="fas fa-star text-2xl"></i>

                            </div>

                        </div>

                        <p class="text-green-600 text-sm mt-2">

                            <i class="fas fa-arrow-up mr-1"></i> <?php echo number_format($stats['premium_users']); ?> premium хэрэглэгч

                        </p>

                    </div>

                </div>

                

                <!-- Main Charts Section -->

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                    <!-- User Growth Chart -->

                    <div class="bg-white rounded-lg shadow-md p-6">

                        <div class="flex justify-between items-center mb-4">

                            <h3 class="text-lg font-semibold text-gray-800">Хэрэглэгчийн өсөлт</h3>

                            <div class="flex items-center">

                                <span class="w-3 h-3 bg-blue-500 rounded-full mr-1"></span>

                                <span class="text-sm text-gray-600">Шинэ хэрэглэгчид</span>

                            </div>

                        </div>

                        <div class="chart-container">

                            <canvas id="userGrowthChart"></canvas>

                        </div>

                    </div>

                    

                    <!-- Revenue Chart -->

                    <div class="bg-white rounded-lg shadow-md p-6">

                        <div class="flex justify-between items-center mb-4">

                            <h3 class="text-lg font-semibold text-gray-800">Орлогын график</h3>

                            <div class="flex items-center space-x-2">

                                <div class="flex items-center">

                                    <span class="w-3 h-3 bg-green-500 rounded-full mr-1"></span>

                                    <span class="text-sm text-gray-600">Орлого</span>

                                </div>

                            </div>

                        </div>

                        <div class="chart-container">

                            <canvas id="revenueChart"></canvas>

                        </div>

                    </div>

                </div>

                

                <!-- Secondary Charts Section -->

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

                    <!-- File Uploads Chart -->

                    <div class="bg-white rounded-lg shadow-md p-6">

                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Файл байршуулалт</h3>

                        <div class="chart-container">

                            <canvas id="fileUploadsChart"></canvas>

                        </div>

                    </div>

                    

                    <!-- Downloads Chart -->

                    <div class="bg-white rounded-lg shadow-md p-6">

                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Файл татагдалт</h3>

                        <div class="chart-container">

                            <canvas id="downloadsChart"></canvas>

                        </div>

                    </div>

                    

                    <!-- Categories Distribution -->

                    <div class="bg-white rounded-lg shadow-md p-6">

                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Ангилалын хуваарилалт</h3>

                        <div class="chart-container">

                            <canvas id="categoriesChart"></canvas>

                        </div>

                    </div>

                </div>

                

                <!-- Top Performers -->

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                    <!-- Top Uploaders -->

                    <div class="bg-white rounded-lg shadow-md p-6">

                        <div class="flex justify-between items-center mb-4">

                            <h3 class="text-lg font-semibold text-gray-800">Шилдэг нийтлэгчид</h3>

                            <a href="users.php" class="text-blue-600 hover:underline text-sm">Бүгдийг харах</a>

                        </div>

                        <div class="overflow-x-auto">

                            <table class="min-w-full divide-y divide-gray-200 data-table">

                                <thead>

                                    <tr>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Хэрэглэгч</th>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файл</th>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Орлого</th>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үнэлгээ</th>

                                    </tr>

                                </thead>

                                <tbody class="divide-y divide-gray-200">

                                    <?php foreach ($top_uploaders as $uploader): ?>

                                    <tr>

                                        <td class="px-4 py-3 whitespace-nowrap">

                                            <div class="flex items-center">

                                                <img src="<?php echo htmlspecialchars($uploader['avatar_url'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80'); ?>" 

                                                     alt="User" class="w-8 h-8 rounded-full mr-2">

                                                <span class="font-medium"><?php echo htmlspecialchars($uploader['full_name'] ?: $uploader['username']); ?></span>

                                            </div>

                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo $uploader['file_count']; ?></td>

                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">₮<?php echo number_format($uploader['total_earnings'] ?: 0, 2); ?></td>

                                        <td class="px-4 py-3 whitespace-nowrap">

                                            <span class="badge-success px-2 py-1 text-xs rounded-full"><?php echo number_format($uploader['avg_rating'] ?: 0, 1); ?>/5</span>

                                        </td>

                                    </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                    

                    <!-- Top Files -->

                    <div class="bg-white rounded-lg shadow-md p-6">

                        <div class="flex justify-between items-center mb-4">

                            <h3 class="text-lg font-semibold text-gray-800">Шилдэг файлууд</h3>

                            <a href="files.php" class="text-blue-600 hover:underline text-sm">Бүгдийг харах</a>

                        </div>

                        <div class="overflow-x-auto">

                            <table class="min-w-full divide-y divide-gray-200 data-table">

                                <thead>

                                    <tr>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файлын нэр</th>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Татагдалт</th>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Орлого</th>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үнэлгээ</th>

                                    </tr>

                                </thead>

                                <tbody class="divide-y divide-gray-200">

                                    <?php foreach ($top_files as $file): ?>

                                    <tr>

                                        <td class="px-4 py-3 whitespace-nowrap">

                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($file['title']); ?></div>

                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo $file['download_count']; ?></td>

                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">₮<?php echo number_format($file['total_earnings'] ?: 0, 2); ?></td>

                                        <td class="px-4 py-3 whitespace-nowrap">

                                            <span class="badge-success px-2 py-1 text-xs rounded-full"><?php echo number_format($file['avg_rating'] ?: 0, 1); ?>/5</span>

                                        </td>

                                    </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

                

                <!-- Platform Health -->

                <div class="bg-white rounded-lg shadow-md p-6 mb-6">

                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Платформын эрүүл мэнд</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                        <div class="bg-gray-50 rounded-lg p-4">

                            <div class="flex justify-between items-center mb-2">

                                <h4 class="font-medium text-gray-700">Хэрэглэгчийн идэвх</h4>

                                <span class="badge-success px-2 py-1 text-xs rounded-full"><?php echo round(($stats['active_users'] / $stats['total_users']) * 100); ?>%</span>

                            </div>

                            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">

                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo ($stats['active_users'] / $stats['total_users']) * 100; ?>%"></div>

                            </div>

                            <p class="text-sm text-gray-600"><?php echo $stats['active_users']; ?> идэвхтэй хэрэглэгч</p>

                        </div>

                        

                        <div class="bg-gray-50 rounded-lg p-4">

                            <div class="flex justify-between items-center mb-2">

                                <h4 class="font-medium text-gray-700">Premium гишүүд</h4>

                                <span class="badge-warning px-2 py-1 text-xs rounded-full"><?php echo round(($stats['premium_users'] / $stats['total_users']) * 100); ?>%</span>

                            </div>

                            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">

                                <div class="bg-yellow-500 h-2 rounded-full" style="width: <?php echo ($stats['premium_users'] / $stats['total_users']) * 100; ?>%"></div>

                            </div>

                            <p class="text-sm text-gray-600"><?php echo $stats['premium_users']; ?> premium гишүүд</p>

                        </div>

                        

                        <div class="bg-gray-50 rounded-lg p-4">

                            <div class="flex justify-between items-center mb-2">

                                <h4 class="font-medium text-gray-700">Гүйлгээний амжилт</h4>

                                <span class="badge-success px-2 py-1 text-xs rounded-full"><?php echo round((($stats['total_transactions'] - $stats['pending_transactions']) / $stats['total_transactions']) * 100); ?>%</span>

                            </div>

                            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">

                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo (($stats['total_transactions'] - $stats['pending_transactions']) / $stats['total_transactions']) * 100; ?>%"></div>

                            </div>

                            <p class="text-sm text-gray-600"><?php echo $stats['total_transactions'] - $stats['pending_transactions']; ?> амжилттай гүйлгээ</p>

                        </div>

                    </div>

                </div>

                

                <!-- Export Options -->

                <div class="bg-white rounded-lg shadow-md p-6">

                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">

                        <div>

                            <h3 class="text-lg font-semibold text-gray-800 mb-2">Статистик татах</h3>

                            <p class="text-gray-600">Өгөгдлийг CSV, Excel эсвэл PDF форматаар татаж авах</p>

                        </div>

                        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">

                            <a href="export.php?type=csv" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">

                                <i class="fas fa-file-csv mr-2"></i> CSV татах

                            </a>

                            <a href="export.php?type=excel" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">

                                <i class="fas fa-file-excel mr-2"></i> Excel татах

                            </a>

                            <a href="export.php?type=pdf" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">

                                <i class="fas fa-file-pdf mr-2"></i> PDF татах

                            </a>

                        </div>

                    </div>

                </div>

            </main>

        </div>

    </div>



    <script>

        document.addEventListener('DOMContentLoaded', function() {

            // Tab buttons functionality

            const tabButtons = document.querySelectorAll('.tab-button');

            tabButtons.forEach(button => {

                button.addEventListener('click', function() {

                    tabButtons.forEach(btn => btn.classList.remove('active'));

                    this.classList.add('active');

                    const period = this.dataset.period;

                    // Here you would update charts based on selected period

                    console.log('Period selected:', period);

                    // You would need to implement AJAX calls to update data based on period

                });

            });

            

            // User Growth Chart

            const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');

            const userGrowthChart = new Chart(userGrowthCtx, {

                type: 'line',

                data: {

                    labels: <?php echo json_encode($user_growth_labels); ?>,

                    datasets: [{

                        label: 'Шинэ хэрэглэгчид',

                        data: <?php echo json_encode($user_growth_data); ?>,

                        backgroundColor: 'rgba(59, 130, 246, 0.1)',

                        borderColor: '#3b82f6',

                        borderWidth: 2,

                        tension: 0.3,

                        fill: true

                    }]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {

                            display: false

                        }

                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            grid: {

                                color: 'rgba(0, 0, 0, 0.05)'

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

            

            // Revenue Chart

            const revenueCtx = document.getElementById('revenueChart').getContext('2d');

            const revenueChart = new Chart(revenueCtx, {

                type: 'bar',

                data: {

                    labels: <?php echo json_encode($revenue_labels); ?>,

                    datasets: [

                        {

                            label: 'Орлого',

                            data: <?php echo json_encode($revenue_data); ?>,

                            backgroundColor: '#10b981',

                            borderRadius: 4

                        }

                    ]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {

                            display: false

                        },

                        tooltip: {

                            callbacks: {

                                label: function(context) {

                                    let label = context.dataset.label || '';

                                    if (label) {

                                        label += ': ';

                                    }

                                    if (context.parsed.y !== null) {

                                        label += context.parsed.y.toLocaleString() + '₮';

                                    }

                                    return label;

                                }

                            }

                        }

                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            grid: {

                                color: 'rgba(0, 0, 0, 0.05)'

                            },

                            ticks: {

                                callback: function(value) {

                                    return value.toLocaleString() + '₮';

                                }

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

            

            // File Uploads Chart

            const fileUploadsCtx = document.getElementById('fileUploadsChart').getContext('2d');

            const fileUploadsChart = new Chart(fileUploadsCtx, {

                type: 'line',

                data: {

                    labels: ['Ням', 'Дав', 'Мяг', 'Лха', 'Пүр', 'Баа', 'Бям'],

                    datasets: [{

                        label: 'Файл байршуулалт',

                        data: <?php echo json_encode($file_uploads_data); ?>,

                        backgroundColor: 'rgba(124, 58, 237, 0.1)',

                        borderColor: '#7c3aed',

                        borderWidth: 2,

                        tension: 0.3,

                        fill: true

                    }]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {

                            display: false

                        }

                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            grid: {

                                color: 'rgba(0, 0, 0, 0.05)'

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

            

            // Downloads Chart

            const downloadsCtx = document.getElementById('downloadsChart').getContext('2d');

            const downloadsChart = new Chart(downloadsCtx, {

                type: 'bar',

                data: {

                    labels: ['Ням', 'Дав', 'Мяг', 'Лха', 'Пүр', 'Баа', 'Бям'],

                    datasets: [{

                        label: 'Файл татагдалт',

                        data: <?php echo json_encode($downloads_data); ?>,

                        backgroundColor: '#3b82f6',

                        borderRadius: 4

                    }]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {

                            display: false

                        }

                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            grid: {

                                color: 'rgba(0, 0, 0, 0.05)'

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

            

            // Categories Distribution Chart

            const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');

            const categoriesChart = new Chart(categoriesCtx, {

                type: 'doughnut',

                data: {

                    labels: <?php echo json_encode($categories_labels); ?>,

                    datasets: [{

                        data: <?php echo json_encode($categories_data); ?>,

                        backgroundColor: [

                            '#4f46e5',

                            '#10b981',

                            '#f59e0b',

                            '#3b82f6',         

                            '#ec4899',

                            '#8b5cf6'

                        ],

                        borderWidth: 0

                    }]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {

                            position: 'right',

                            labels: {

                                boxWidth: 12,

                                padding: 20

                            }

                        }

                    },

                    cutout: '70%'

                }

            });

        });

    </script>

</body>

</html>

<?php

mysqli_close($conn);

?>