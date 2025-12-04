<?php

session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===================================================================

//  DATABASE CONNECTION

// ===================================================================

$conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8");



// ===================================================================

//  HANDLE FORM SUBMISSIONS (ACTIONS)

// ===================================================================

// ===================================================================
//  HANDLE ADMIN ACTIONS
// ===================================================================

// Check if an admin action (complete or reject) is being performed
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
    $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);

    // Get the success message from URL if it was set after a redirect
    $success = isset($_GET['success']) ? urldecode($_GET['success']) : null;
    $error = null; // Use a new variable for admin action errors

    if ($request_id === false || $request_id <= 0) {
        $error = "Хүсэлтийн ID буруу байна.";
    } else {
        // Fetch the request details, including bank details for refund description
        $req_sql = "SELECT wr.amount, wr.user_id, wr.status, wr.details FROM withdrawal_requests wr WHERE id = ?";
        $req_stmt = mysqli_prepare($conn, $req_sql);
        mysqli_stmt_bind_param($req_stmt, "i", $request_id);
        mysqli_stmt_execute($req_stmt);
        $req_result = mysqli_stmt_get_result($req_stmt);
        $request = mysqli_fetch_assoc($req_result);
        mysqli_stmt_close($req_stmt);

        if (!$request || $request['status'] !== 'pending') {
            $error = "Хүсэлт олдсонгүй эсвэл аль хэдийн боловсруулагдсан байна.";
        } else {
            mysqli_begin_transaction($conn);
            $action_success = false;

            try {
                if (isset($_POST['complete_request'])) {
                    // Action: COMPLETE REQUEST (Set status to 'completed')
                    // Balance update is NOT needed here as it was deducted on request creation
                    $update_sql = "UPDATE withdrawal_requests SET status = 'completed', processed_date = NOW() WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $request_id);
                    $update_executed = mysqli_stmt_execute($update_stmt);
                    
                    if (!$update_executed) {
                        throw new Exception("Хүсэлтийн статусыг шинэчлэхэд алдаа гарлаа.");
                    }

                    mysqli_commit($conn);
                    $success = "Хүсэлтийг **Амжилттай Биелсэн** төлөвт орууллаа. Мөнгө шилжүүлэх үйлдэл хийгдсэн гэж үзнэ.";
                    $action_success = true;

                } elseif (isset($_POST['reject_request'])) {
                    // Action: REJECT REQUEST (Set status to 'rejected' AND refund the amount to user balance)
                    $amount = $request['amount'];
                    $user_id = $request['user_id'];
                    $details = $request['details'];
                    $description = "Мөнгө татах хүсэлтээс татгалзсан: №" . $request_id . " (Данс: " . $details . ")";

                    // 1. Update the user's balance: refund the amount
                    $update_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "di", $amount, $user_id);
                    $update_executed = mysqli_stmt_execute($update_stmt);

                    if (!$update_executed) {
                        throw new Exception("Хэрэглэгчийн үлдэгдлийг буцаан олгоход алдаа гарлаа.");
                    }

                    // 2. Insert a 'deposit' transaction for the refund in user_transactions
                    $insert_sql = "INSERT INTO user_transactions (user_id, type, amount, description) VALUES (?, 'deposit', ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_sql);
                    mysqli_stmt_bind_param($insert_stmt, "ids", $user_id, $amount, $description);
                    $insert_executed = mysqli_stmt_execute($insert_stmt);

                    if (!$insert_executed) {
                        throw new Exception("Буцаан олголтын гүйлгээний түүхэнд бичихэд алдаа гарлаа.");
                    }

                    // 3. Update withdrawal_requests status
                    $update_req_sql = "UPDATE withdrawal_requests SET status = 'rejected', processed_date = NOW() WHERE id = ?";
                    $update_req_stmt = mysqli_prepare($conn, $update_req_sql);
                    mysqli_stmt_bind_param($update_req_stmt, "i", $request_id);
                    $update_req_executed = mysqli_stmt_execute($update_req_stmt);

                    if (!$update_req_executed) {
                        throw new Exception("Хүсэлтийн статусыг шинэчлэхэд алдаа гарлаа.");
                    }

                    mysqli_commit($conn);
                    $success = "Хүсэлтээс **Татгалзлаа**. " . number_format($amount, 2) . "₮ хэрэглэгчийн дансанд амжилттай буцаан олгогдлоо.";
                    $action_success = true;

                } else {
                    // No valid action
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Админ үйлдэл хийхэд алдаа гарлаа: " . $e->getMessage();
            }

            // After a successful action, redirect to clear POST data and see updated list
            if ($action_success) {
                 // Preserve current search/filter/page parameters
                $query_params = http_build_query([
                    'page' => $page ?? 1,
                    'search' => $search ?? '',
                    'status' => $filter_status ?? '',
                    'success' => urlencode($success) // Pass success message via GET
                ]);
                header("Location: withdrawals.php?" . $query_params);
                exit;
            }
        }
    }
}


// Check if there's a success message from a redirect (admin action)
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

// Check if there's an error message from a redirect (admin action)
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Check if there's an error message from the user withdrawal attempt
if (isset($error_message)) {
    $error = $error_message;
}

// Check if there's a success message from the user withdrawal attempt
if (isset($success_message)) {
    $success = $success_message;
}

// ===================================================================

//  DATA FETCHING & FILTERING

// ===================================================================



// Get filter values from URL

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';



// Base query with join to get user info

$sql = "SELECT wr.*, u.username, u.email 

        FROM withdrawal_requests wr

        JOIN users u ON wr.user_id = u.id

        WHERE 1=1";



// Apply filters

if (!empty($search)) {

    $sql .= " AND (u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR wr.details LIKE '%$search%')";

}



if ($filter_status === 'completed') {

    $sql .= " AND wr.status = 'completed'";

} elseif ($filter_status === 'pending') {

    $sql .= " AND wr.status = 'pending'";

} elseif ($filter_status === 'rejected') {

    $sql .= " AND wr.status = 'rejected'";

}



$sql .= " ORDER BY wr.request_date DESC";



// Get total count for pagination

$count_result = mysqli_query($conn, $sql);

$total_requests = mysqli_num_rows($count_result);



// Pagination logic

$per_page = 10;

$total_pages = ceil($total_requests / $per_page);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$offset = ($page - 1) * $per_page;



$sql .= " LIMIT $offset, $per_page";

$result = mysqli_query($conn, $sql);



// Get stats for the header cards

$stats_sql = "SELECT 

    COUNT(*) as total,

    SUM(status = 'pending') as pending,

    SUM(status = 'completed') as completed,

    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount

    FROM withdrawal_requests";

$stats_result = mysqli_query($conn, $stats_sql);

$stats = mysqli_fetch_assoc($stats_result);



?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>НАРХАН - Мөнгө татах хүсэлт</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" type="text/css" href="css/styles.css">

</head>

<body class="bg-gray-50 font-sans">

    <div class="flex h-screen">

        <!-- Sidebar -->

        <?php include 'sidebar.php' ?>

        

        <!-- Main Content -->

        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- Admin Header -->

            <header class="bg-white shadow-sm py-4 px-6 hidden md:flex justify-between items-center">

                <div>

                    <h2 class="text-xl font-bold text-gray-800">Мөнгө татах хүсэлтүүд</h2>

                    <p class="text-gray-600">Нийт <?php echo number_format($total_requests); ?> хүсэлт, <?php echo number_format($stats['pending'] ?? 0); ?> хүлээгдэж буй</p>

                </div>

                <div class="flex items-center space-x-4">

                    <div class="relative">

                        <i class="fas fa-bell text-gray-600 text-xl"></i>

                        <span class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-4 h-4 text-xs flex items-center justify-center"><?php echo number_format($stats['pending'] ?? 0); ?></span>

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

                <?php if (isset($success)): ?>

                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">

                        <span class="block sm:inline"><?php echo $success; ?></span>

                    </div>

                <?php endif; ?>

                

                <!-- Stats Cards -->

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

                    <div class="stat-card bg-white rounded-lg shadow-md p-4">

                        <div class="flex items-center">

                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full mr-3"><i class="fas fa-list-alt"></i></div>

                            <div>

                                <p class="text-gray-500 text-sm">Нийт хүсэлт</p>

                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['total'] ?? 0); ?></p>

                            </div>

                        </div>

                    </div>

                    <div class="stat-card bg-white rounded-lg shadow-md p-4">

                        <div class="flex items-center">

                            <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full mr-3"><i class="fas fa-clock"></i></div>

                            <div>

                                <p class="text-gray-500 text-sm">Хүлээгдэж буй</p>

                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['pending'] ?? 0); ?></p>

                            </div>

                        </div>

                    </div>

                     <div class="stat-card bg-white rounded-lg shadow-md p-4">

                        <div class="flex items-center">

                            <div class="bg-green-100 text-green-600 p-3 rounded-full mr-3"><i class="fas fa-check-circle"></i></div>

                            <div>

                                <p class="text-gray-500 text-sm">Биелсэн</p>

                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['completed'] ?? 0); ?></p>

                            </div>

                        </div>

                    </div>

                    <div class="stat-card bg-white rounded-lg shadow-md p-4">

                        <div class="flex items-center">

                            <div class="bg-purple-100 text-purple-600 p-3 rounded-full mr-3"><i class="fas fa-wallet"></i></div>

                            <div>

                                <p class="text-gray-500 text-sm">Хүлээгдэж буй дүн</p>

                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['pending_amount'] ?? 0, 2); ?>₮</p>

                            </div>

                        </div>

                    </div>

                </div>

                

                <!-- Filters -->

                <div class="bg-white rounded-lg shadow-md p-4 mb-6">

                    <form method="GET" action="withdrawals.php" class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-4">

                        <div class="relative flex-grow">

                            <input type="text" name="search" placeholder="Хэрэглэгч, имэйл, данс хайх..." 

                            value="<?php echo htmlspecialchars($search); ?>" 

                            class="search-box w-full border border-gray-300 rounded-md py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500">

                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>

                        </div>

                        

                        <div>

                            <select name="status" class="w-full md:w-48 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">

                                <option value="">Бүх статус</option>

                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Хүлээгдэж буй</option>

                                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Биелсэн</option>

                                <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Татгалзсан</option>

                            </select>

                        </div>

                        

                        <div class="md:ml-auto">

                            <button type="submit" class="w-full md:w-auto gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700">

                                <i class="fas fa-filter mr-1"></i> Шүүх

                            </button>

                            <a href="withdrawals.php" class="w-full md:w-auto mt-2 md:mt-0 md:ml-2 inline-block text-center bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300">

                                <i class="fas fa-sync-alt mr-1"></i> Цэвэрлэх

                            </a>

                        </div>

                    </form>

                </div>

                <!-- Requests Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 data-table">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Хэрэглэгч</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дүн</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Банкны мэдээлэл</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Огноо</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($request = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['username'] ?? ''); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['email'] ?? ''); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold">
                                                <?php echo number_format($request['amount'] ?? 0, 2); ?>₮
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['bank_name'] ?? ''); ?></div>
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['account_name'] ?? ''); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['details'] ?? ''); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('Y-m-d H:i', strtotime($request['request_date'] ?? 'now')); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                    $status_class = '';
                                                    $status = $request['status'] ?? 'pending';
                                                    if ($status === 'completed') $status_class = 'badge-approved';
                                                    elseif ($status === 'pending') $status_class = 'badge-pending';
                                                    else $status_class = 'badge-rejected';
                                                ?>
                                                <span class="<?php echo $status_class; ?> px-2 py-1 text-xs rounded-full"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if (($request['status'] ?? '') === 'pending'): ?>
                                                    <form method="POST" action="withdrawals.php" class="inline" onsubmit="return confirm('Та энэ гүйлгээг хийж, хүсэлтийг БИЕЛСЭН болгохдоо итгэлтэй байна уу?');">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id'] ?? ''; ?>">
                                                        <button type="submit" name="complete_request" class="text-green-600 hover:text-green-900 mr-3" title="Биелсэн болгох">
                                                            <i class="fas fa-check-circle fa-lg"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="withdrawals.php" class="inline" onsubmit="return confirm('Та энэ хүсэлтээс татгалзаж, мөнгийг хэрэглэгчийн дансанд буцаахдаа итгэлтэй байна уу?');">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id'] ?? ''; ?>">
                                                        <button type="submit" name="reject_request" class="text-red-600 hover:text-red-900" title="Татгалзах">
                                                            <i class="fas fa-times-circle fa-lg"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400">Боловсруулсан: <?php echo date('Y-m-d', strtotime($request['processed_date'] ?? 'now')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-6 text-gray-500">Илэрц олдсонгүй.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                

                <!-- Pagination -->

                <!-- (Pagination logic copied from files.php for consistency) -->

                <div class="flex items-center justify-between">

                    <div class="text-sm text-gray-500">

                        Showing <span class="font-medium"><?php echo ($page - 1) * $per_page + 1; ?></span> to <span class="font-medium"><?php echo min($page * $per_page, $total_requests); ?></span> of <span class="font-medium"><?php echo number_format($total_requests); ?></span> results

                    </div>

                    <div class="flex space-x-1">

                        <?php if ($page > 1): ?>

                            <a href="withdrawals.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>

                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                            <a href="withdrawals.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium <?php echo $i == $page ? 'text-white pagination-active' : 'text-gray-700 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>

                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>

                            <a href="withdrawals.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>

                        <?php endif; ?>

                    </div>

                </div>

            </main>

        </div>

    </div>

</body>

</html>

<?php

mysqli_close($conn);

?>

