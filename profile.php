<?php
// Start session and output buffering
session_start();
ob_start();

// Include essential files
require_once 'includes/functions.php';

// ===================================================================
//  AUTHENTICATION & SETUP
// ===================================================================

// Check if user is logged in, redirect if not
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the logged-in user's ID from the session
$user_id = $_SESSION['user_id'];

// Database connection
$conn = db_connect();
if (!$conn) {
    die("Database connection failed.");
}


// ===================================================================
//  AJAX HANDLER: For fetching earnings graph data
// ===================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'earnings') {
    try {
        // Clear any previous output
        if (ob_get_level() > 0) ob_end_clean();

        $period = isset($_GET['period']) ? $_GET['period'] : 'month';
        $interval = '1 MONTH'; // Default
        if ($period == '3months') $interval = '3 MONTH';
        if ($period == '6months') $interval = '6 MONTH';

        $sql = "SELECT DATE_FORMAT(transaction_date, '%Y-%m-%d') as day, SUM(amount) as total 
        FROM user_transactions 
        WHERE user_id = ? AND type = 'sale' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL $interval)
        GROUP BY day 
        ORDER BY day ASC";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $labels = [];
        $data = [];
        while($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['day'];
            $data[] = $row['total'];
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
        mysqli_close($conn);
        exit;

    } catch (Exception $e) {
        if (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}


// ===================================================================
//  POST HANDLERS: For settings updates
// ===================================================================
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

     // ----- Handle Avatar Upload -----
        if ($action === 'update_avatar' && isset($_FILES['avatar'])) {
    // --- Start of JSON response ---
            header('Content-Type: application/json');
            $response = ['success' => false, 'error' => 'An unknown error occurred.'];

            $upload_dir = 'assets/avatars/' . $user_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $file_type = $_FILES['avatar']['type'];
            $file_size = $_FILES['avatar']['size'];
            $file_tmp = $_FILES['avatar']['tmp_name'];

            if (!in_array($file_type, $allowed_types)) {
                $response['error'] = "Only JPG, PNG, and WEBP files are allowed.";
    } elseif ($file_size > 2097152) { // 2MB
        $response['error'] = "File size must be less than 2MB.";
    } else {
        // Fetch the current avatar URL BEFORE updating
        $old_avatar_sql = "SELECT avatar_url FROM users WHERE id = ?";
        $old_avatar_stmt = mysqli_prepare($conn, $old_avatar_sql);
        mysqli_stmt_bind_param($old_avatar_stmt, "i", $user_id);
        mysqli_stmt_execute($old_avatar_stmt);
        $old_avatar_result = mysqli_fetch_assoc(mysqli_stmt_get_result($old_avatar_stmt));
        $old_avatar_path = $old_avatar_result['avatar_url'];

        // Generate unique filename and path
        $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $file_name = 'avatar_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($file_tmp, $file_path)) {
            $update_sql = "UPDATE users SET avatar_url = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $file_path, $user_id);

            if (mysqli_stmt_execute($update_stmt)) {
                // Delete old avatar if it's not the default one
                if ($old_avatar_path && $old_avatar_path !== 'assets/images/default-avatar.png' && file_exists($old_avatar_path)) {
                    unlink($old_avatar_path);
                }
                $response = ['success' => true, 'new_avatar_url' => $file_path];
            } else {
                $response['error'] = "Failed to update database.";
                if (file_exists($file_path)) unlink($file_path);
            }
        } else {
            $response['error'] = "Failed to move uploaded file.";
        }
    }
    // --- Echo JSON and exit ---
    echo json_encode($response);
    mysqli_close($conn);
    exit(); // Stop the rest of the page from loading
}

        // ----- Handle Profile Info Update -----
if ($action === 'update_profile') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $location = trim($_POST['location']);

    if (empty($username) || empty($email)) {
        $errors[] = "Username and Email are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {
        $sql = "UPDATE users SET username = ?, email = ?, phone = ?, location = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $phone, $location, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $success = "Profile information updated successfully!";
        } else {
            $errors[] = "Failed to update profile. The username or email might already be taken.";
        }
    }
}

        // ----- Handle Password Change -----
if ($action === 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    } else {
                // Fetch current password from DB
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);

                // Verify current password (using SHA2 as per your DB schema)
        if ($user_data && $user_data['password'] === hash('sha256', $current_password)) {
                    // Hash new password and update
            $new_password_hashed = hash('sha256', $new_password);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $new_password_hashed, $user_id);
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Password changed successfully!";
            } else {
                $errors[] = "Failed to update password.";
            }
        } else {
            $errors[] = "Incorrect current password.";
        }
    }
}
if ($action === 'delete_file') {
    $file_id = intval($_POST['file_id']);

            // 1. Файл энэ хэрэглэгчийнх мөн эсэхийг шалгах
    $check_sql = "SELECT * FROM files WHERE id = ? AND user_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $file_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $file_to_delete = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    if ($file_to_delete) {
                // 2. Үндсэн файлыг серверээс устгах
        if (!empty($file_to_delete['file_url']) && file_exists($file_to_delete['file_url'])) {
            unlink($file_to_delete['file_url']);
        }

                // 3. Зургуудыг серверээс устгах
        $img_sql = "SELECT preview_url FROM file_previews WHERE file_id = ?";
        $img_stmt = mysqli_prepare($conn, $img_sql);
        mysqli_stmt_bind_param($img_stmt, "i", $file_id);
        mysqli_stmt_execute($img_stmt);
        $img_result = mysqli_stmt_get_result($img_stmt);
        while ($img = mysqli_fetch_assoc($img_result)) {
            if (file_exists($img['preview_url'])) {
                unlink($img['preview_url']);
            }
        }

                // 4. Фолдерыг устгах (Хоосон болсон фолдеруудыг)
        $dir_path = 'uploads/files/' . $user_id . '/' . $file_id . '/';
        $preview_dir_path = 'uploads/previews/' . $user_id . '/' . $file_id . '/';
        if (is_dir($dir_path)) @rmdir($dir_path);
        if (is_dir($preview_dir_path)) @rmdir($preview_dir_path);

                // 5. Database-ээс устгах (Cascade delete тохируулаагүй бол гараар устгана)
                // Transaction, Previews, Categories, Tags зэргийг DB дээрээ CASCADE гэж тохируулсан байх ёстой.
                // Хэрэв үгүй бол энд тус бүрт нь DELETE query бичнэ.
        $del_sql = "DELETE FROM files WHERE id = ?";
        $del_stmt = mysqli_prepare($conn, $del_sql);
        mysqli_stmt_bind_param($del_stmt, "i", $file_id);

        if (mysqli_stmt_execute($del_stmt)) {
            $success = "Файл амжилттай устгагдлаа.";
                    // Тоолуурыг шинэчлэхийн тулд хуудсыг refresh хийх
            header("Refresh:0"); 
        } else {
            $errors[] = "Файл устгахад алдаа гарлаа.";
        }
    } else {
        $errors[] = "Файл олдсонгүй эсвэл танд устгах эрх байхгүй.";
    }
}
}
}


// ===================================================================
//  DATA FETCHING for page display
// ===================================================================

// Fetch user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    // If user not found, log out and redirect
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- Fetch Stats ---
// 1. Uploaded files count
$uploaded_files_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM files WHERE user_id = $user_id"))['total'] ?? 0;

// 2. Total downloads of user's files
$total_downloads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(download_count) as total FROM files WHERE user_id = $user_id"))['total'] ?? 0;

// 3. Purchased files count
$purchased_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM transactions WHERE user_id = $user_id"))['total'] ?? 0;

// 4. Average rating for user's files
$rating_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(r.rating) as avg_rating, COUNT(r.id) as rating_count FROM ratings r JOIN files f ON r.file_id = f.id WHERE f.user_id = $user_id"));
$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$rating_count = $rating_data['rating_count'] ?? 0;

// 5. НИЙТ ОЛСОН ОРЛОГО (Бодит борлуулалтаас 15% шимтгэл хасагдсан дүнгээр)
$commission_rate = defined('SITE_COMMISSION_PERCENT') ? SITE_COMMISSION_PERCENT : 15;
$keep_rate = (100 - $commission_rate) / 100;

// Таны файлуудыг бусад хүмүүс худалдаж авсан нийт дүн
$earnings_sql = "SELECT SUM(t.amount) as raw_total 
                 FROM transactions t 
                 JOIN files f ON t.file_id = f.id 
                 WHERE f.user_id = $user_id AND t.status = 'success'";
$raw_earnings = mysqli_fetch_assoc(mysqli_query($conn, $earnings_sql))['raw_total'] ?? 0;
$total_earnings = $raw_earnings * $keep_rate;

// 6. НИЙТ ЗАРЦУУЛСАН МӨНГӨ (Таны худалдан авалтууд)
$spent_sql = "SELECT SUM(amount) as total FROM transactions WHERE user_id = $user_id AND status = 'success'";
$total_spent = mysqli_fetch_assoc(mysqli_query($conn, $spent_sql))['total'] ?? 0;


// --- Fetch Tab Content ---
// 1. My Uploaded Files
$sort_order = 'upload_date DESC';
if (isset($_GET['sort']) && $_GET['sort'] == 'popular') {
    $sort_order = 'download_count DESC';
}
$my_files = [];
// Subquery ашиглан хамгийн эхний зургийг авна
$sql_my_files = "SELECT f.*, 
(SELECT preview_url FROM file_previews fp WHERE fp.file_id = f.id ORDER BY order_index ASC LIMIT 1) as thumbnail
FROM files f 
WHERE f.user_id = ? 
ORDER BY $sort_order";
$stmt_my_files = mysqli_prepare($conn, $sql_my_files);
mysqli_stmt_bind_param($stmt_my_files, "i", $user_id);
mysqli_stmt_execute($stmt_my_files);
$result_my_files = mysqli_stmt_get_result($stmt_my_files);
while ($row = mysqli_fetch_assoc($result_my_files)) {
    $my_files[] = $row;
}

// 2. My Purchased Files
$purchased_files = [];
$sql_purchased = "SELECT f.*, u.username as seller_name 
FROM transactions t 
JOIN files f ON t.file_id = f.id 
JOIN users u ON f.user_id = u.id 
WHERE t.user_id = ? ORDER BY t.transaction_date DESC";
$stmt_purchased = mysqli_prepare($conn, $sql_purchased);
mysqli_stmt_bind_param($stmt_purchased, "i", $user_id);
mysqli_stmt_execute($stmt_purchased);
$result_purchased = mysqli_stmt_get_result($stmt_purchased);
while ($row = mysqli_fetch_assoc($result_purchased)) {
    $purchased_files[] = $row;
}

// 3. Transaction History (Сайжруулсан & Зассан)
$user_transactions = [];

// Орлого (Sale) болон Зарлага (Purchase)-ыг нэгтгэж авах
// created_at биш transaction_date гэж ашиглана
$sql_transactions = "
    (SELECT t.id, t.amount, t.transaction_date, 'purchase' as type, f.title as description 
     FROM transactions t JOIN files f ON t.file_id = f.id 
     WHERE t.user_id = ? AND t.status = 'success')
    UNION
    (SELECT t.id, t.amount, t.transaction_date, 'sale' as type, CONCAT(f.title, ' (Зарагдсан)') as description 
     FROM transactions t JOIN files f ON t.file_id = f.id 
     WHERE f.user_id = ? AND t.status = 'success')
    ORDER BY transaction_date DESC LIMIT 50";

$stmt_transactions = mysqli_prepare($conn, $sql_transactions);
mysqli_stmt_bind_param($stmt_transactions, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt_transactions);
$result_transactions = mysqli_stmt_get_result($stmt_transactions);
while ($row = mysqli_fetch_assoc($result_transactions)) {
    $user_transactions[] = $row;
}

// 4. Initial Earnings Chart Data (Last 30 days)
$initial_earnings_sql = "SELECT DATE_FORMAT(transaction_date, '%Y-%m-%d') as day, SUM(amount) as total 
FROM user_transactions 
WHERE user_id = ? AND type = 'sale' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
GROUP BY day 
ORDER BY day ASC";
$stmt_earnings = mysqli_prepare($conn, $initial_earnings_sql);
mysqli_stmt_bind_param($stmt_earnings, "i", $user_id);
mysqli_stmt_execute($stmt_earnings);
$result_earnings = mysqli_stmt_get_result($stmt_earnings);
$initial_chart_labels = [];
$initial_chart_data = [];
while($row = mysqli_fetch_assoc($result_earnings)) {
    $initial_chart_labels[] = $row['day'];
    $initial_chart_data[] = $row['total'];
}
$initial_earnings_chart_data = json_encode(['labels' => $initial_chart_labels, 'data' => $initial_chart_data]);


// Generate a new CSRF token for forms
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Set Page Title
$pageTitle = "НАРХАН - " . htmlspecialchars($user['username']);

// Include header and navigation
include 'includes/header.php';
include 'includes/navigation.php';
?>

<div class="profile-header text-white py-12 bg-gradient-to-r from-purple-600 to-blue-500">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row items-center">
            <div class="relative mb-6 md:mb-0">
                <form id="avatar-form" method="POST" enctype="multipart/form-data" class="relative group">
                    <input type="hidden" name="action" value="update_avatar">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <img src="<?= htmlspecialchars($user['avatar_url'] ?? 'assets/images/default-avatar.png') ?>" 
                    alt="<?= htmlspecialchars($user['username']) ?>" 
                    class="w-32 h-32 rounded-full border-4 border-white object-cover cursor-pointer transition-opacity duration-300 hover:opacity-90"
                    id="avatar-preview">
                    
                    <input type="file" name="avatar" id="avatar-input" accept="image/jpeg, image/png, image/webp" class="hidden">
                    
                    <label for="avatar-input" class="absolute bottom-0 right-0 bg-purple-700 hover:bg-purple-800 text-white rounded-full p-2 cursor-pointer transition-all duration-300 transform hover:scale-110 shadow-md">
                        <i class="fas fa-camera text-sm"></i>
                    </label>
                    
                    <div id="avatar-loading" class="absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center hidden">
                        <i class="fas fa-spinner fa-spin text-white text-xl"></i>
                    </div>
                </form>
                
                <?php if ($user['is_premium']): ?>
                    <span class="absolute bottom-2 right-2 bg-yellow-500 text-white rounded-full p-1 text-xs">
                        <i class="fas fa-crown"></i>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="md:ml-8 mt-6 md:mt-0 text-center md:text-left">
                <div class="flex flex-wrap justify-center md:justify-start items-center mb-2">
                    <h1 class="text-3xl font-bold mr-3"><?= htmlspecialchars($user['username']) ?></h1>
                    
                    <?php if ($user['is_verified']): ?>
                        <span class="bg-blue-500 text-white px-3 py-1 rounded-full text-xs font-medium mr-2 flex items-center">
                            <i class="fas fa-check-circle mr-1"></i> Баталгаажсан
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($user['is_premium']): ?>
                        <span class="bg-yellow-500 text-white px-3 py-1 rounded-full text-xs font-medium flex items-center">
                            <i class="fas fa-star mr-1"></i> Premium
                        </span>
                    <?php endif; ?>
                </div>
                
                <p class="text-purple-200 mb-4">
                    <?= htmlspecialchars($user['email'] ?? '') ?> | 
                    <?= $uploaded_files_count ?> файл байршуулсан
                </p>
                
                <div class="flex flex-wrap justify-center md:justify-start gap-4">
                    <div class="bg-white bg-opacity-20 rounded-lg px-4 py-2 backdrop-blur-sm">
                        <p class="text-sm text-purple-100">Гишүүнчлэл</p>
                        <p class="font-medium"><?= date('Y-m-d', strtotime($user['join_date'])) ?></p>
                    </div>
                    
                    <div class="bg-white bg-opacity-20 rounded-lg px-4 py-2 backdrop-blur-sm">
                        <p class="text-sm text-purple-100">Сүүлд идэвхтэй</p>
                        <p class="font-medium"><?= date('Y-m-d', strtotime($user['last_active'])) ?></p>
                    </div>
                    
                    <div class="bg-white bg-opacity-20 rounded-lg px-4 py-2 backdrop-blur-sm">
                        <p class="text-sm text-purple-100">Байршил</p>
                        <p class="font-medium"><?= htmlspecialchars($user['location'] ?? 'Тодорхойгүй') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<main class="container mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
        <aside class="w-full lg:w-1/3">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Миний статистик</h3>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="stat-card bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-purple-600"><?= $uploaded_files_count ?></div>
                        <p class="text-sm text-gray-600">Байршуулсан файл</p>
                    </div>
                    <div class="stat-card bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-purple-600"><?= number_format($total_downloads) ?></div>
                        <p class="text-sm text-gray-600">Татагдсан тоо</p>
                    </div>
                    <div class="stat-card bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-purple-600"><?= $purchased_count ?></div>
                        <p class="text-sm text-gray-600">Худалдаж авсан</p>
                    </div>
                    <div class="stat-card bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-purple-600"><?= $avg_rating ?></div>
                        <p class="text-sm text-gray-600">Дундаж үнэлгээ</p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-medium text-gray-700 mb-2">Олсон орлого</h4>
                    <div class="text-3xl font-bold text-green-600"><?= number_format($total_earnings, 2) ?>₮</div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-medium text-gray-700 mb-2">Зарцуулсан мөнгө</h4>
                    <div class="text-3xl font-bold text-blue-600"><?= number_format($total_spent, 2) ?>₮</div>
                </div>
                
                <div class="bg-purple-50 rounded-lg p-4">
                    <h4 class="font-medium text-gray-700 mb-2">Нийт үнэлгээ</h4>
                    <div class="flex items-center mb-1">
                        <div class="flex">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $avg_rating): ?>
                                    <i class="fas fa-star text-yellow-400"></i>
                                <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                    <i class="fas fa-star-half-alt text-yellow-400"></i>
                                <?php else: ?>
                                    <i class="far fa-star text-yellow-400"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span class="ml-2 text-sm font-medium"><?= $avg_rating ?>/5 (<?= $rating_count ?> үнэлгээ)</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="progress-bar bg-yellow-400 h-2 rounded-full" style="width: <?= ($avg_rating / 5) * 100 ?>%"></div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Миний данс</h3>
                
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <p class="text-sm text-gray-600 mb-1">Боломжит үлдэгдэл</p>
                    <div class="text-2xl font-bold text-purple-600"><?= number_format($user['balance'], 2) ?>₮</div>
                </div>

                <div class="mb-4">
                    <a href="deposit.php" class="block w-full text-center bg-green-600 hover:bg-green-700 text-white py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-plus-circle mr-2"></i>Данс цэнэглэх
                    </a>
                </div>

                <!-- Мөнгө татах хэсэг -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Мөнгө татах</h3>
                    
                    <?php
                    $can_withdraw = (float)$user['balance'] >= 20000.00;
                    if (!$can_withdraw): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-yellow-500 mr-2"></i>
                                <p class="text-sm text-yellow-700">
                                    Мөнгө татах доод хэмжээ: <strong>20,000₮</strong>. Таны дансны үлдэгдэл хүрэлцэхгүй байна.
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-yellow-500 mr-2"></i>
                                <p class="text-sm text-yellow-700">
                                    Мөнгө татах доод хэмжээ: <strong>20,000₮</strong>. Татан авалтын хүсэлт админ баталгаажуулсны дараа 24 цагийн дотор дансанд шилжинэ.
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="withdraw.php" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Татах дүн</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500">₮</span>
                                    </div>
                                    <input type="number" name="amount" class="w-full border border-gray-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" 
                                    placeholder="20000" min="20000" step="1000" max="<?= $user['balance'] ?>" required>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Доод хэмжээ: 20,000₮ | Таны үлдэгдэл: <?= number_format($user['balance'], 2) ?>₮</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Банкны нэр</label>
                                <select name="bank_name" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                                    <option value="">Банкаа сонгоно уу</option>
                                    <option value="Хаан банк">Хаан банк</option>
                                    <option value="Хас банк">Хас банк</option>
                                    <option value="Голомт банк">Голомт банк</option>
                                    <option value="Төрийн банк">Төрийн банк</option>
                                    <option value="Худалдаа хөгжлийн банк">Худалдаа хөгжлийн банк</option>
                                    <option value="Ард кредит банк">Ард кредит банк</option>
                                    <option value="Богд банк">Богд банк</option>
                                    <option value="Капитрон банк">Капитрон банк</option>
                                    <option value="Чингис банк">Чингис банк</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Дансны дугаар</label>
                                <input type="text" name="details" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" 
                                placeholder="1234567890" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Дансны нэр</label>
                                <input type="text" name="account_name" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" 
                                placeholder="Таны бүтэн нэр" required>
                            </div>

                            <input type="hidden" name="withdraw_method" value="bank">

                            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-md font-medium transition">
                                <i class="fas fa-paper-plane mr-2"></i> Хүсэлт илгээх
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm">
                    <p class="text-yellow-700"><i class="fas fa-info-circle mr-2"></i>Хамгийн бага мөнгө татах дүн: 20,000₮</p>
                </div>
            </div>
        </aside>

        <div class="w-full lg:w-2/3">
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button class="tab-btn py-4 px-1 tab-active" data-tab="my-files">Миний файлууд</button>
                        <button class="tab-btn py-4 px-1 text-gray-500 hover:text-purple-600" data-tab="purchased">Худалдаж авсан</button>
                        <button class="tab-btn py-4 px-1 text-gray-500 hover:text-purple-600" data-tab="transactions">Гүйлгээний түүх</button>
                        <button class="tab-btn py-4 px-1 text-gray-500 hover:text-purple-600" data-tab="earnings">Орлогын график</button>
                        <button class="tab-btn py-4 px-1 text-gray-500 hover:text-purple-600" data-tab="settings">Тохиргоо</button>
                    </nav>
                </div>
                
                <div id="my-files" class="tab-content active p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Миний байршуулсан файлууд</h3>
                        <div class="flex space-x-2">
                            <a href="?sort=new" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded-md text-sm">Шинээр</a>
                            <a href="?sort=popular" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded-md text-sm">Их татагдсан</a>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($my_files)): ?>
                            <p class="text-gray-500 text-center py-4">Таньд байршуулсан файл байхгүй байна.</p>
                        <?php else: ?>
                            <?php foreach ($my_files as $file): ?>
                                <div class="file-card flex flex-col sm:flex-row items-start sm:items-center border border-gray-200 rounded-lg p-4 hover:shadow-md transition bg-white">
                                    
                                    <div class="w-full sm:w-24 h-24 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden mr-0 sm:mr-4 mb-3 sm:mb-0 relative">
                                        <?php if (!empty($file['thumbnail']) && file_exists($file['thumbnail'])): ?>
                                            <img src="<?= htmlspecialchars($file['thumbnail']) ?>" alt="Preview" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-purple-50 text-purple-500">
                                                <i class="fas fa-file-<?= get_file_icon($file['file_type']) ?> text-3xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex-1 w-full">
                                        <div class="flex justify-between items-start">
                                            <h4 class="font-semibold text-gray-800 text-lg line-clamp-1"><?= htmlspecialchars($file['title']) ?></h4>
                                            <div class="font-bold text-purple-600 whitespace-nowrap ml-2">
                                                <?= $file['price'] > 0 ? number_format($file['price'], 0) . '₮' : '<span class="text-green-600">Үнэгүй</span>' ?>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center text-sm text-gray-500 mt-2 gap-y-2">
                                            <span class="mr-3"><i class="fas fa-eye mr-1"></i> <?= $file['view_count'] ?></span>
                                            <span class="mr-3"><i class="fas fa-download mr-1"></i> <?= $file['download_count'] ?></span>
                                            <span class="mr-3"><i class="far fa-calendar-alt mr-1"></i> <?= date('Y-m-d', strtotime($file['upload_date'])) ?></span>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-between mt-3">
                                            <div class="mb-2 sm:mb-0">
                                                <?php if ($file['status'] === 'approved'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i> Баталгаажсан
                                                    </span>
                                                <?php elseif ($file['status'] === 'pending'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        <i class="fas fa-clock mr-1"></i> Хүлээгдэж буй
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        <i class="fas fa-times-circle mr-1"></i> Татгалзсан
                                                    </span>
                                                    <?php if (!empty($file['rejection_reason'])): ?>
                                                        <button onclick="showRejectionReason('<?= htmlspecialchars(addslashes($file['rejection_reason'])) ?>')" class="ml-2 text-xs text-red-600 underline hover:text-red-800">
                                                            Шалтгаан харах
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>

                                            <div class="flex space-x-2">
                                                <a href="edit_file.php?id=<?= $file['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium px-2 py-1 rounded hover:bg-blue-50 transition">
                                                    <i class="fas fa-edit mr-1"></i> Засах
                                                </a>
                                                
                                                <form method="POST" onsubmit="return confirm('Та энэ файлыг устгахдаа итгэлтэй байна уу? Устгасан файлыг сэргээх боломжгүй!');" class="inline">
                                                    <input type="hidden" name="action" value="delete_file">
                                                    <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium px-2 py-1 rounded hover:bg-red-50 transition">
                                                        <i class="fas fa-trash-alt mr-1"></i> Устгах
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="purchased" class="tab-content p-6">
                 <h3 class="text-lg font-semibold text-gray-800 mb-6">Худалдаж авсан файлууд</h3>
                 <div class="space-y-4">
                    <?php if (empty($purchased_files)): ?>
                     <p class="text-gray-500 text-center py-4">Та файл худалдаж аваагүй байна.</p>
                 <?php else: ?>
                    <?php foreach ($purchased_files as $file): ?>
                        <div class="file-card flex items-center border border-gray-200 rounded-lg p-4">
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-lg mr-4">
                                <i class="fas fa-file-<?= get_file_icon($file['file_type']) ?> text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($file['title']) ?></h4>
                                <div class="flex items-center text-sm text-gray-500 mt-1">
                                    <span><i class="fas fa-user mr-1"></i> <?= htmlspecialchars($file['seller_name']) ?></span>
                                    <span class="mx-2">•</span>
                                    <a href="<?= htmlspecialchars($file['file_url']) ?>" download class="text-purple-600 hover:underline"><i class="fas fa-download mr-1"></i> Татах</a>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-purple-600"><?= number_format($file['price'], 2) ?>₮</div>
                                <p class="text-sm text-gray-500"><?= date('Y-m-d', strtotime($file['upload_date'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="transactions" class="tab-content p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6">Гүйлгээний түүх</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Огноо</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дэлгэрэнгүй</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Төрөл</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дүн</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if(empty($user_transactions)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-gray-500">Гүйлгээний түүх байхгүй.</td></tr>
                        <?php else: ?>
                            <?php foreach($user_transactions as $trans): ?>
                                <?php 
                                    // Төрлөөс хамаарч тохиргоо хийх
                                    $is_income = ($trans['type'] == 'sale');
                                    $display_amount = $trans['amount'];
                                    
                                    // Хэрэв орлого бол шимтгэлээ хасаж харуулах (15%)
                                    if ($is_income) {
                                        $display_amount = $trans['amount'] * 0.85; 
                                    }

                                    $row_bg = $is_income ? 'hover:bg-green-50' : 'hover:bg-red-50';
                                    $text_color = $is_income ? 'text-green-600' : 'text-red-600';
                                    $sign = $is_income ? '+' : '-';
                                    $type_badge = $is_income ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                    $type_label = $is_income ? 'Орлого' : 'Зарлага';
                                ?>
                                <tr class="transaction-row border-b border-gray-100 <?= $row_bg ?> transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('Y-m-d H:i', strtotime($trans['transaction_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($trans['description']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $type_badge ?>">
                                            <?= $type_label ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold <?= $text_color ?>">
                                        <?= $sign ?><?= number_format($display_amount, 2) ?>₮
                                    </td>
                                </tr>
<?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="earnings" class="tab-content p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Орлогын график</h3>
                <div class="flex space-x-2">
                    <button class="bg-purple-600 text-white px-3 py-1 rounded-md text-sm earnings-period active-period" data-period="month">Энэ сар</button>
                    <button class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded-md text-sm earnings-period" data-period="3months">3 сар</button>
                    <button class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded-md text-sm earnings-period" data-period="6months">6 сар</button>
                </div>
            </div>
            <div class="h-64"><canvas id="earningsChart"></canvas></div>
        </div>

        <div id="settings" class="tab-content p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6">Тохиргоо</h3>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <ul class="list-disc pl-5">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form method="POST" class="bg-white border border-gray-200 rounded-lg p-6">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <h4 class="text-md font-semibold text-gray-800 mb-4">Профайлын тохиргоо</h4>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Хэрэглэгчийн нэр</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Имэйл хаяг</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Утасны дугаар</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Байршил</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($user['location']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                    </div>
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-md text-sm font-medium">Мэдээлэл хадгалах</button>
                </form>

                <form method="POST" class="bg-white border border-gray-200 rounded-lg p-6">
                 <input type="hidden" name="action" value="change_password">
                 <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                 <h4 class="text-md font-semibold text-gray-800 mb-4">Нууцлалын тохиргоо</h4>
                 <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Одоогийн нууц үг</label>
                    <input type="password" name="current_password" placeholder="********" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Шинэ нууц үг</label>
                    <input type="password" name="new_password" placeholder="Шинэ нууц үг" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Шинэ нууц үг давтах</label>
                    <input type="password" name="confirm_password" placeholder="Шинэ нууц үг давтах" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-md text-sm font-medium">Нууц үг солих</button>
            </form>
        </div>
    </div>
</div>
</div>
</div>
</main>

<div id="withdraw-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <h3 id="modal-title" class="text-xl font-semibold text-gray-800">Мөнгө татах</h3>
            <button id="close-modal-btn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
        </div>
        
        <form id="withdraw-form" action="withdraw.php" method="POST">
            <input type="hidden" name="withdraw_method" id="withdraw-method-input">
            
            <div class="mb-4">
                <label for="withdraw-amount" class="block text-sm font-medium text-gray-700 mb-1">Татах дүн</label>
                <input type="number" name="amount" id="withdraw-amount" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600" placeholder="20000" min="20000" max="<?= (float)$user['balance'] ?>" required>
                <p class="text-xs text-gray-500 mt-1">Боломжит үлдэгдэл: <?= number_format($user['balance'], 2) ?>₮</p>
            </div>
            
            <div class="mb-6">
                <label for="withdraw-details" id="modal-details-label" class="block text-sm font-medium text-gray-700 mb-1">Дансны мэдээлэл</label>
                <input type="text" name="details" id="withdraw-details" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600" required>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-6 rounded-md font-medium">
                    Хүсэлт илгээх
                </button>
            </div>
        </form>
    </div>
</div>

<div id="reason-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4 transform transition-all scale-100">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <h3 class="text-xl font-semibold text-red-600 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> Татгалзсан шалтгаан
            </h3>
            <button onclick="closeReasonModal()" class="text-gray-500 hover:text-gray-800 text-2xl focus:outline-none">&times;</button>
        </div>
        <div class="bg-red-50 p-4 rounded-lg border border-red-100">
            <p id="reason-text" class="text-gray-800 text-sm leading-relaxed"></p>
        </div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeReasonModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-6 rounded-md font-medium transition">
                Хаах
            </button>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    // =================================================
    //  Avatar Upload Functionality
    // =================================================
        const avatarForm = document.getElementById('avatar-form');
        const avatarInput = document.getElementById('avatar-input');
        const avatarPreview = document.getElementById('avatar-preview');
        const avatarLoading = document.getElementById('avatar-loading');

        if (avatarInput && avatarPreview) {
        // Event listener for when a new file is chosen
            avatarInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                // Client-side validation for file type and size
                    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!validTypes.includes(file.type)) {
                        alert('Зөвхөн JPG, PNG, WEBP зураг оруулна уу!');
                        return;
                    }
                if (file.size > 2 * 1024 * 1024) { // 2MB Limit
                    alert('Зурагны хэмжээ 2MB-ээс хэтрэхгүй байх ёстой!');
                    return;
                }

                avatarLoading.classList.remove('hidden');
                const formData = new FormData(avatarForm);

                // Use fetch to upload the file and handle the JSON response
                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the image source with the new path from the server
                        // A timestamp is added to prevent browser caching issues
                        avatarPreview.src = data.new_avatar_url + '?t=' + new Date().getTime();
                    } else {
                        // Display the specific error message from the server
                        alert('Алдаа гарлаа: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Аватар хуулах үед тодорхойгүй алдаа гарлаа.');
                })
                .finally(() => {
                    // Hide the loading spinner
                    avatarLoading.classList.add('hidden');
                });
            }
        });

        // Allow clicking the avatar image to open the file selection dialog
            avatarPreview.addEventListener('click', function() {
                avatarInput.click();
            });
        }


    // =================================================
    //  Tab Switching Functionality
    // =================================================
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');

            // Update button styles
                tabButtons.forEach(btn => {
                    btn.classList.remove('tab-active');
                    btn.classList.add('text-gray-500');
                });
                this.classList.add('tab-active');
                this.classList.remove('text-gray-500');

            // Hide all content and show the selected tab's content
                tabContents.forEach(content => content.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');

            // If the earnings tab is now active, initialize its chart
                if (tabId === 'earnings' && typeof initEarningsChart === 'function') {
                    initEarningsChart(
                        <?php echo $initial_earnings_chart_data; ?>.labels,
                        <?php echo $initial_earnings_chart_data; ?>.data,
                        'Орлогын график'
                        );
                }
            });
        });


    // =================================================
    //  Earnings Chart Logic
    // =================================================
        let earningsChart = null;

        function initEarningsChart(labels, data, title) {
            const ctx = document.getElementById('earningsChart').getContext('2d');
            if (earningsChart) {
                earningsChart.destroy();
            }
            earningsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Орлого (₮)',
                        data: data,
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        borderColor: '#7c3aed',
                        borderWidth: 2,
                        pointBackgroundColor: '#7c3aed',
                        pointRadius: 4,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => value.toLocaleString() + '₮'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: title,
                            font: {
                                size: 16
                            },
                            padding: {
                                bottom: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: context => 'Орлого: ' + context.parsed.y.toLocaleString() + '₮'
                            }
                        }
                    }
                }
            });
        }

        function updateChart(period) {
            let chartTitle = 'Орлогын график';
            if (period === '3months') chartTitle = 'Сүүлийн 3 сарын орлого';
            if (period === '6months') chartTitle = 'Сүүлийн 6 сарын орлого';

            fetch(`profile.php?ajax=earnings&period=${period}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    initEarningsChart(result.labels, result.data, chartTitle);
                }
            })
            .catch(error => console.error('Error fetching chart data:', error));
        }

    // Add event listeners for the chart's period filter buttons
        document.querySelectorAll('.earnings-period').forEach(button => {
            button.addEventListener('click', function() {
                const period = this.getAttribute('data-period');
                document.querySelectorAll('.earnings-period').forEach(btn => {
                    btn.classList.remove('bg-purple-600', 'text-white', 'active-period');
                    btn.classList.add('bg-gray-100', 'text-gray-800');
                });
                this.classList.add('bg-purple-600', 'text-white', 'active-period');
                updateChart(period);
            });
        });

    // Draw the initial chart if the earnings tab is active on page load
        if (document.querySelector('#earnings.active')) {
            initEarningsChart(
                <?php echo $initial_earnings_chart_data; ?>.labels,
                <?php echo $initial_earnings_chart_data; ?>.data,
                'Орлогын график'
                );
        }

    // =================================================
    //  Withdrawal Modal Functionality
    // =================================================
        const withdrawModal = document.getElementById('withdraw-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const withdrawBankBtn = document.getElementById('withdraw-bank-btn');
        const withdrawQpayBtn = document.getElementById('withdraw-qpay-btn');

        if (withdrawModal && closeModalBtn && withdrawBankBtn && withdrawQpayBtn) {

            const modalTitle = document.getElementById('modal-title');
            const modalDetailsLabel = document.getElementById('modal-details-label');
            const withdrawDetailsInput = document.getElementById('withdraw-details');
            const withdrawMethodInput = document.getElementById('withdraw-method-input');

            const openModal = (method) => {
                if (method === 'bank') {
                    modalTitle.textContent = 'Банкны данс руу татах';
                    modalDetailsLabel.textContent = 'Банкны дансны дугаар';
                    withdrawDetailsInput.placeholder = 'Таны дансны дугаар';
                    withdrawMethodInput.value = 'bank';
                } else if (method === 'qpay') {
                    modalTitle.textContent = 'QPay-р татах';
                    modalDetailsLabel.textContent = 'QPay-д бүртгэлтэй утасны дугаар';
                    withdrawDetailsInput.placeholder = 'Таны утасны дугаар';
                    withdrawMethodInput.value = 'qpay';
                }
                withdrawModal.classList.remove('hidden');
            };

            const closeModal = () => {
                withdrawModal.classList.add('hidden');
            };

            withdrawBankBtn.addEventListener('click', () => {
                if (!withdrawBankBtn.disabled) {
                    openModal('bank');
                }
            });

            withdrawQpayBtn.addEventListener('click', () => {
               if (!withdrawQpayBtn.disabled) {
                openModal('qpay');
            }
        });

            closeModalBtn.addEventListener('click', closeModal);

            withdrawModal.addEventListener('click', (event) => {
                if (event.target === withdrawModal) {
                    closeModal();
                }
            });

        }
    });

// Rejection Reason Modal Logic
function showRejectionReason(reason) {
    const modal = document.getElementById('reason-modal');
    const text = document.getElementById('reason-text');
    
    if (modal && text) {
        text.textContent = reason;
        modal.classList.remove('hidden');
    }
}

function closeReasonModal() {
    const modal = document.getElementById('reason-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('reason-modal');
    if (event.target == modal) {
        closeReasonModal();
    }
}
</script>