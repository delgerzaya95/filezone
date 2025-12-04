<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Хэрэглэгч нэвтэрсэн эсэхийг шалгах
if (!isset($_SESSION['user_id'])) {
    // Нэвтэрсний дараа яг энэ хуудас руу буцаж ирэхийн тулд хаягийг нь хадгалах
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Хэрэв нэвтрээгүй бол нэвтрэх хуудас руу үсэргэнэ
    header("Location: login.php");
    exit(); // Энэ мөр маш чухал!
}
// Database connection
$conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Approve transaction
    if (isset($_POST['approve_transaction'])) {
        $transaction_id = intval($_POST['transaction_id']);
        $sql = "UPDATE transactions SET status = 'success' WHERE id = $transaction_id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: transactions.php");
            exit();
        } else {
            $error = "Error approving transaction: " . mysqli_error($conn);
        }
    }
    
    // Reject transaction
    if (isset($_POST['reject_transaction'])) {
        $transaction_id = intval($_POST['transaction_id']);
        $sql = "UPDATE transactions SET status = 'failed' WHERE id = $transaction_id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: transactions.php");
            exit();
        } else {
            $error = "Error rejecting transaction: " . mysqli_error($conn);
        }
    }
    
    // Delete transaction
    if (isset($_POST['delete_transaction'])) {
        $transaction_id = intval($_POST['transaction_id']);
        $sql = "DELETE FROM transactions WHERE id = $transaction_id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: transactions.php");
            exit();
        } else {
            $error = "Error deleting transaction: " . mysqli_error($conn);
        }
    }
}

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$filter_method = isset($_GET['method']) ? mysqli_real_escape_string($conn, $_GET['method']) : '';

// Base query with joins to get user and file info
$sql = "SELECT t.*, 
               u.username, u.full_name as user_full_name, u.avatar_url as user_avatar,
               f.title as file_title, f.file_type, f.file_size
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN files f ON t.file_id = f.id
        WHERE 1=1";

// Apply filters
if (!empty($search)) {
    $sql .= " AND (u.username LIKE '%$search%' OR u.full_name LIKE '%$search%' OR f.title LIKE '%$search%')";
}

if (!empty($filter_status)) {
    $sql .= " AND t.status = '$filter_status'";
}

if (!empty($filter_type)) {
    $sql .= " AND t.type = '$filter_type'";
}

if (!empty($filter_method)) {
    $sql .= " AND t.payment_method = '$filter_method'";
}

$sql .= " ORDER BY t.transaction_date DESC";

// Get total count for pagination
$count_result = mysqli_query($conn, $sql);
$total_transactions = mysqli_num_rows($count_result);

// Pagination
$per_page = 10;
$total_pages = ceil($total_transactions / $per_page);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$sql .= " LIMIT $offset, $per_page";
$result = mysqli_query($conn, $sql);

// Get all transactions for stats
$stats_sql = "SELECT 
                COUNT(*) as total_transactions,
                SUM(status = 'success') as success_transactions,
                SUM(status = 'pending') as pending_transactions,
                SUM(status = 'failed') as failed_transactions,
                SUM(amount) as total_amount
              FROM transactions";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>НАРХАН - Гүйлгээнүүд</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <h2 class="text-xl font-bold text-gray-800">Гүйлгээний удирдлага</h2>
                    <p class="text-gray-600">Нийт <?php echo number_format($total_transactions); ?> гүйлгээ, <?php echo $stats['pending_transactions']; ?> хүлээгдэж буй</p>
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
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50 admin-content">
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Transaction Management Tools -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">Гүйлгээний жагсаалт</h3>
                        <p class="text-gray-600">Бүх хэрэглэгчийн гүйлгээнүүд</p>
                    </div>
                    
                    <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-2 w-full md:w-auto">
                        <form method="GET" action="transactions.php" class="relative">
                            <input type="text" name="search" placeholder="Гүйлгээ хайх..." 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   class="search-box w-full md:w-64 border border-gray-300 rounded-md py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </form>
                        <button onclick="window.print()" class="gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700 flex items-center justify-center">
                            <i class="fas fa-download mr-2"></i> Экспортлох
                        </button>
                    </div>
                </div>
                
                <!-- Transaction Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="stat-card bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center">
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full mr-3">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Нийт гүйлгээ</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['total_transactions']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center">
                            <div class="bg-green-100 text-green-600 p-3 rounded-full mr-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Амжилттай</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['success_transactions']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center">
                            <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full mr-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Хүлээгдэж буй</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['pending_transactions']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center">
                            <div class="bg-purple-100 text-purple-600 p-3 rounded-full mr-3">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Нийт дүн</p>
                                <p class="text-xl font-bold text-gray-800">₮<?php echo number_format($stats['total_amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction Filters -->
                <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    <form method="GET" action="transactions.php" class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-4">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                            <select name="status" class="w-full md:w-48 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">Бүх статус</option>
                                <option value="success" <?php echo $filter_status === 'success' ? 'selected' : ''; ?>>Амжилттай</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Хүлээгдэж буй</option>
                                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Амжилтгүй</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Төлбөрийн хэрэгсэл</label>
                            <select name="method" class="w-full md:w-48 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">Бүх хэрэгсэл</option>
                                <option value="balance" <?php echo $filter_method === 'balance' ? 'selected' : ''; ?>>Баланс</option>
                                <option value="qpay" <?php echo $filter_method === 'qpay' ? 'selected' : ''; ?>>QPay</option>
                                <option value="card" <?php echo $filter_method === 'card' ? 'selected' : ''; ?>>Карт</option>
                                <option value="socialpay" <?php echo $filter_method === 'socialpay' ? 'selected' : ''; ?>>SocialPay</option>
                            </select>
                        </div>
                        
                        <div class="md:ml-auto">
                            <button type="submit" class="gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700">
                                <i class="fas fa-filter mr-1"></i> Шүүх
                            </button>
                            <a href="transactions.php" class="ml-2 bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300">
                                <i class="fas fa-sync-alt mr-1"></i> Цэвэрлэх
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Transactions Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 data-table">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Гүйлгээ ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Хэрэглэгч</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файл</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Төрөл</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дүн</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Огноо</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($transaction = mysqli_fetch_assoc($result)): ?>
                                <tr class="transaction-row">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#TRX-<?php echo $transaction['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <img class="h-8 w-8 rounded-full" src="css/images/default_avatar.png" alt="">
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($transaction['user_full_name'] ?: $transaction['username']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($transaction['file_id']): ?>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($transaction['file_title']); ?></div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo strtoupper($transaction['file_type']); ?>, 
                                                <?php echo round($transaction['file_size'] / 1000000, 1); ?>MB
                                            </div>
                                        <?php else: ?>
                                            <div class="text-sm text-gray-500">-</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php 
                                        $type = 'Файл худалдаа';
                                        if ($transaction['file_id'] === null) {
                                            $type = 'Данс цэнэглэлт';
                                        }
                                        echo $type;
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="font-bold"><?php echo number_format($transaction['amount'], 2); ?>₮</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($transaction['status'] === 'success'): ?>
                                            <span class="badge-success px-2 py-1 text-xs rounded-full">Амжилттай</span>
                                        <?php elseif ($transaction['status'] === 'pending'): ?>
                                            <span class="badge-pending px-2 py-1 text-xs rounded-full">Хүлээгдэж буй</span>
                                        <?php else: ?>
                                            <span class="badge-failed px-2 py-1 text-xs rounded-full">Амжилтгүй</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($transaction['status'] === 'pending'): ?>
                                            <form method="POST" action="transactions.php" class="inline">
                                                <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                <button type="submit" name="approve_transaction" class="text-green-600 hover:text-green-900 mr-3">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="transactions.php" class="inline">
                                                <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                <button type="submit" name="reject_transaction" class="text-red-600 hover:text-red-900 mr-3">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="transactions.php" class="inline" onsubmit="return confirm('Та энэ гүйлгээг устгахдаа итгэлтэй байна уу?');">
                                            <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                            <input type="hidden" name="delete_transaction" value="1">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Showing <span class="font-medium"><?php echo ($page - 1) * $per_page + 1; ?></span> to <span class="font-medium"><?php echo min($page * $per_page, $total_transactions); ?></span> of <span class="font-medium"><?php echo number_format($total_transactions); ?></span> results
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="transactions.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&method=<?php echo urlencode($filter_method); ?>" 
                               class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="transactions.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&method=<?php echo urlencode($filter_method); ?>" 
                               class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium <?php echo $i == $page ? 'text-white pagination-active' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="transactions.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&method=<?php echo urlencode($filter_method); ?>" 
                               class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

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
            
            // Transaction search functionality
            const searchBox = document.querySelector('.search-box');
            if (searchBox) {
                searchBox.addEventListener('input', function() {
                    // Implement search functionality here
                    console.log('Searching for transactions:', this.value);
                });
            }
        });
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>