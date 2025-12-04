<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
// Start session and include configuration
session_start();
require_once 'includes/functions.php';
require_once 'includes/qpay_config.php';
require_once 'includes/qpay_handler.php';

// ===================================================================
//  ШИНЭ: AJAX ХАНДЛАГЧ (download.php-д зориулав)
// ===================================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $user_id_ajax = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($user_id_ajax === 0) {
        echo json_encode(['success' => false, 'message' => 'Нэвтэрсэн байх шаардлагатай.']);
        exit;
    }

    $conn_ajax = db_connect();
    if (!$conn_ajax) {
         echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
         exit;
    }

    // --- ACTION 1: НЭХЭМЖЛЭХ ҮҮСГЭХ (QPay товч дарахад) ---
    if ($_GET['action'] == 'create_invoice' && isset($_POST['file_id'])) {
        $file_id_ajax = (int)$_POST['file_id'];
        
        $file_query = mysqli_query($conn_ajax, "SELECT * FROM files WHERE id = {$file_id_ajax}");
        $user_query = mysqli_query($conn_ajax, "SELECT * FROM users WHERE id = {$user_id_ajax}");
        
        $file_data = mysqli_fetch_assoc($file_query);
        $user_data = mysqli_fetch_assoc($user_query);

        if ($file_data && $user_data) {
            $invoice_response = create_qpay_invoice($file_data, $user_data);

            if (isset($invoice_response['invoice_id'])) {
                // Амжилттай бол SESSION-д хадгална
                $_SESSION['pending_file_purchase'] = [
                    'sender_invoice_no' => $invoice_response['sender_invoice_no'],
                    'qpay_invoice_id'   => $invoice_response['invoice_id'], // Энэ нь UUID
                    'file_id'           => $file_id_ajax,
                    'user_id'           => $user_id_ajax,
                    'created_at'        => time()
                ];
                echo json_encode(['success' => true, 'invoice_data' => $invoice_response]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Нэхэмжлэх үүсгэж чадсангүй: ' . ($invoice_response['error'] ?? 'Unknown error')]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'File or user not found.']);
        }
        mysqli_close($conn_ajax);
        exit;
    }

    // --- ACTION 2: ТӨЛБӨР ШАЛГАХ (Polling) ---
    if ($_GET['action'] == 'check_payment') {
        if (!isset($_SESSION['pending_file_purchase'])) {
            echo json_encode(['success' => false, 'message' => 'Pending purchase not found in session.']);
            exit;
        }

        $pending_purchase = $_SESSION['pending_file_purchase'];
        $qpay_invoice_id = $pending_purchase['qpay_invoice_id']; // UUID
        
        // 1. QPay-с шалгах
        $payment_check = check_qpay_payment_status($qpay_invoice_id);

        if ($payment_check['status'] == 'PAID') {
            // 2. QPay дээр төлөгдсөн бол манай системд бүртгэгдсэн эсэхийг шалгах
            $file_id_check = (int)$pending_purchase['file_id'];
            $user_id_check = (int)$pending_purchase['user_id'];

            $check_sql = "SELECT id FROM transactions WHERE user_id = ? AND file_id = ? AND status = 'success'";
            $stmt_check = mysqli_prepare($conn_ajax, $check_sql);
            mysqli_stmt_bind_param($stmt_check, "ii", $user_id_check, $file_id_check);
            mysqli_stmt_execute($stmt_check);
            $check_result = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($check_result) > 0) {
                // Амжилттай! Callback аль хэдийн ажилласан байна.
                unset($_SESSION['pending_file_purchase']);
                echo json_encode(['success' => true, 'message' => 'Payment confirmed and processed.']);
            
            } else {
                // ШИНЭЧЛЭЛ: QPay төлсөн, гэхдээ callback ажиллаагүй байна.
                // Polling хийж буй хэрэглэгч өөрөө гүйлгээг бүртгэнэ.
                
                // 1. Файлын мэдээлэл авах (Үнэ + ЭЗЭМШИГЧ)
                // user_id багана нэмэгдсэн
                $file_query_ajax = mysqli_query($conn_ajax, "SELECT price, user_id FROM files WHERE id = {$file_id_check}");
                $file_data_ajax = mysqli_fetch_assoc($file_query_ajax);
                
                $file_price = $file_data_ajax ? (float)$file_data_ajax['price'] : 0.0;
                $owner_id = $file_data_ajax ? (int)$file_data_ajax['user_id'] : 0;

                // Race condition-с сэргийлж transaction ашиглана
                mysqli_begin_transaction($conn_ajax);
                try {
                    // 1. Гүйлгээг бүртгэх
                    $insert_sql = "INSERT INTO transactions (user_id, file_id, amount, payment_method, status)
                                   VALUES (?, ?, ?, 'qpay', 'success')";
                    $insert_stmt = mysqli_prepare($conn_ajax, $insert_sql);
                    mysqli_stmt_bind_param($insert_stmt, "iid", $user_id_check, $file_id_check, $file_price);
                    $exec1 = mysqli_stmt_execute($insert_stmt);

                    // 2. Таталтын тоог нэмэх
                    $update_sql = "UPDATE files SET download_count = download_count + 1 WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn_ajax, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $file_id_check);
                    $exec2 = mysqli_stmt_execute($update_stmt);

                    // 3. ШИНЭ: Файл эзэмшигчид ШИМТГЭЛ ХАСАЖ мөнгө нэмэх (Polling дээр)
                    $exec3 = true; 
                    if ($owner_id > 0 && $file_price > 0) {
                        // calculate_earnings функц functions.php-д байгаа.
                        $earning = calculate_earnings($file_price);
                        
                        $owner_update = "UPDATE users SET balance = balance + ? WHERE id = ?";
                        $stmt_owner = mysqli_prepare($conn_ajax, $owner_update);
                        mysqli_stmt_bind_param($stmt_owner, "di", $earning, $owner_id);
                        $exec3 = mysqli_stmt_execute($stmt_owner);
                    }

                    if ($exec1 && $exec2 && $exec3) {
                        // Амжилттай бол commit хийнэ
                        mysqli_commit($conn_ajax);
                        unset($_SESSION['pending_file_purchase']);
                        echo json_encode(['success' => true, 'message' => 'Payment processed by poller (Earnings updated).']);
                    } else {
                        // Алдаа гарвал буцаана
                        mysqli_rollback($conn_ajax);
                        echo json_encode(['success' => false, 'message' => 'Payment confirmed, but failed to update database.']);
                    }

                } catch (Exception $e) {
                    mysqli_rollback($conn_ajax);
                    
                    if (mysqli_errno($conn_ajax) == 1062) {
                         unset($_SESSION['pending_file_purchase']);
                         echo json_encode(['success' => true, 'message' => 'Payment confirmed (race condition resolved).']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                    }
                }
            }
        } elseif ($payment_check['status'] == 'ERROR') {
            echo json_encode(['success' => false, 'message' => 'Payment check failed: ' . $payment_check['message']]);
        } else {
            // PENDING
            echo json_encode(['success' => false, 'message' => 'Payment not yet confirmed. (' . $payment_check['message'] . ')']);
        }
        mysqli_close($conn_ajax);
        exit;
    }
}

$qpay_invoice_data = null;
$qpay_error_message = null;

// Database connection
$conn = db_connect();
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get current user ID from session
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Initialize variables
$file = null;
$payment_success = false;
$balance = 0;
$tags = [];

// Fetch user balance if logged in
if ($user_id > 0) {
    $balance_query = "SELECT balance FROM users WHERE id = ?";
    $balance_stmt = mysqli_prepare($conn, $balance_query);
    mysqli_stmt_bind_param($balance_stmt, "i", $user_id);
    mysqli_stmt_execute($balance_stmt);
    $balance_result = mysqli_stmt_get_result($balance_stmt);
    
    if (mysqli_num_rows($balance_result) > 0) {
        $balance_row = mysqli_fetch_assoc($balance_result);
        $balance = $balance_row['balance'];
    }
}

// Get file ID from URL
$file_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch file details and tags
if ($file_id > 0) {
    $sql = "SELECT f.*, u.username 
    FROM files f 
    JOIN users u ON f.user_id = u.id 
    WHERE f.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $file_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $file = mysqli_fetch_assoc($result);
        
        // Fetch tags for this file
        $tag_sql = "SELECT t.name FROM tags t
        JOIN file_tags ft ON t.id = ft.tag_id
        WHERE ft.file_id = ?";
        $tag_stmt = mysqli_prepare($conn, $tag_sql);
        mysqli_stmt_bind_param($tag_stmt, "i", $file_id);
        mysqli_stmt_execute($tag_stmt);
        $tag_result = mysqli_stmt_get_result($tag_stmt);
        
        while ($tag = mysqli_fetch_assoc($tag_result)) {
            $tags[] = $tag;
        }
    }
}

// --- ШИНЭ: ХУДАЛДАЖ АВСАН ЭСЭХИЙГ ШАЛГАХ ---
$user_has_purchased = false;
if ($user_id > 0 && $file_id > 0) {
    $purchase_check_sql = "SELECT id FROM transactions WHERE user_id = ? AND file_id = ? AND status = 'success'";
    $purchase_stmt = mysqli_prepare($conn, $purchase_check_sql);
    mysqli_stmt_bind_param($purchase_stmt, "ii", $user_id, $file_id);
    mysqli_stmt_execute($purchase_stmt);
    $purchase_result = mysqli_stmt_get_result($purchase_stmt);
    
    if (mysqli_num_rows($purchase_result) > 0) {
        $user_has_purchased = true;
    }
}
// --- ШАЛГАЛТ ДУУСАВ ---

// Handle payment simulation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_method'])) {
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    
    // Check if file is free
    if ($file['price'] == 0) {
        $payment_success = true;
    } 
    // Check if user has enough balance for balance payment
    elseif ($payment_method == 'balance' && $balance < $file['price']) {
        $_SESSION['error'] = "Таны дансны үлдэгдэл хүрэлцэхгүй байна";
        header("Location: download.php?id=" . $file_id);
        exit();
    }
    
    // Record transaction
    $insert_sql = "INSERT INTO transactions (user_id, file_id, amount, payment_method, status)
    VALUES (?, ?, ?, ?, 'success')";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "iids", $user_id, $file_id, $file['price'], $payment_method);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $payment_success = true;
        $user_has_purchased = true; 

        // 1. Таталтын тоог нэмэх
        $update_sql = "UPDATE files SET download_count = download_count + 1 WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $file_id);
        mysqli_stmt_execute($update_stmt);
        
        // 2. Хэрэв үнэтэй файл бол гүйлгээ хийх
        if ($file['price'] > 0) {
            // А. Худалдан авагчаас мөнгө хасах (Хэрэв Balance-аар төлсөн бол)
            if ($payment_method == 'balance') {
                $buyer_new_balance = $balance - $file['price'];
                $buyer_update = "UPDATE users SET balance = ? WHERE id = ?";
                $stmt_buyer = mysqli_prepare($conn, $buyer_update);
                mysqli_stmt_bind_param($stmt_buyer, "di", $buyer_new_balance, $user_id);
                mysqli_stmt_execute($stmt_buyer);
            }

            // Б. Файл эзэмшигчид ШИМТГЭЛ ХАСАЖ мөнгө нэмэх (БҮХ ТӨРӨЛ ДЭЭР)
            // Шимтгэл тооцох
            $earning = calculate_earnings($file['price']);
            $owner_id = $file['user_id']; // Файл эзэмшигчийн ID

            // Эзэмшигчийн данс руу орлого нэмэх
            $owner_update = "UPDATE users SET balance = balance + ? WHERE id = ?";
            $stmt_owner = mysqli_prepare($conn, $owner_update);
            mysqli_stmt_bind_param($stmt_owner, "di", $earning, $owner_id);
            mysqli_stmt_execute($stmt_owner);
            
            // Сонголттой: Орлогын түүх бичих (Transactions хүснэгт рүү 'earning' төрлөөр)
            // Хэрэв хүсвэл энд нэмж болно.
        }
    }
}

// Include header
include 'includes/header.php';

// Include navigation
include 'includes/navigation.php';

?>

<main class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2"><?= $file ? 'Файл татах' : 'Файл олдсонгүй' ?></h1>
            <p class="text-gray-600"><?= $file ? 'Доорх файлыг татаж авахын тулд төлбөр төлнө үү' : 'Уучлаарай, таны хайсан файл байхгүй эсвэл устгагдсан байна.' ?></p>
        </div>
        
        <?php if (!$file): ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <i class="fas fa-file-excel text-red-500 text-5xl mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Файл олдсонгүй</h2>
                <p class="text-gray-600 mb-6">Уучлаарай, таны хайсан файл байхгүй эсвэл устгагдсан байна.</p>
                <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md font-medium">
                    <i class="fas fa-home mr-2"></i> Нүүр хуруу буцах
                </a>
            </div>
        <?php else: ?>
            <div class="flex flex-col lg:flex-row gap-8">
                <div class="w-full lg:w-1/2">
                    <div class="file-card bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center mb-4">
                            <span class="bg-purple-100 text-purple-600 px-3 py-1 rounded-full text-xs font-medium"><?= strtoupper($file['file_type']) ?></span>
                            <div class="flex space-x-2">
                                <span class="text-gray-500 text-sm"><i class="fas fa-eye mr-1"></i> <?= $file['view_count'] ?></span>
                                <span class="text-gray-500 text-sm"><i class="fas fa-download mr-1"></i> <?= $file['download_count'] ?></span>
                            </div>
                        </div>
                        
                        <div class="flex flex-col items-center text-center mb-4">
                            <div class="bg-purple-100 text-purple-600 p-4 rounded-full mb-3">
                                <i class="fas fa-file-<?= get_file_icon($file['file_type']) ?> text-3xl"></i>
                            </div>
                            <h3 class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($file['title']) ?></h3>
                            <p class="text-gray-600 text-sm">
                                <?= strtoupper($file['file_type']) ?>, 
                                <?= format_file_size($file['file_size']) ?>
                            </p>
                            <div class="<?= $file['price'] > 0 ? 'bg-gradient-to-r from-yellow-500 to-red-500' : 'bg-green-600' ?> text-white px-3 py-1 rounded-md font-bold mt-2">
                                <?= $file['price'] > 0 ? number_format($file['price']) . '₮' : 'Үнэгүй' ?>
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex items-center mb-3">
                                <img src="<?= get_user_avatar($file['user_id']) ?>" alt="<?= $file['username'] ?>" class="w-10 h-10 rounded-full mr-3">
                                <div>
                                    <a href="user-profile.php?id=<?= $file['user_id'] ?>" class="font-medium text-gray-800 hover:text-purple-600"><?= $file['username'] ?></a>
                                    <p class="text-xs text-gray-500">Гишүүн <?= date('Y-m-d', strtotime($file['upload_date'])) ?></p>
                                </div>
                            </div>
                            <div class="text-gray-600 description-content">
                                <?= ($file['description'] ?? 'No description available') ?>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs"><?= htmlspecialchars($tag['name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Төлбөр төлсний дараа</h3>
                        <ol class="list-decimal pl-5 space-y-3 text-gray-700">
                            <li>Төлбөр амжилттай болсны дараа татаж эхлэх товч идэвхжинэ</li>
                            <li>Татаж авсан файл таны "Худалдаж авсан файлууд" хэсэгт хадгалагдах болно</li>
                            <li>Файлаа дахин татаж авах боломжтой</li>
                            <li>Хугацааны хязгаарлалтгүйгээр хандаж болно</li>
                        </ol>
                    </div>
                </div>
                
                <div class="w-full lg:w-1/2">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <?php if ($user_has_purchased || $file['price'] == 0): ?>
                            <div id="download-section">
                                <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center mb-6">
                                    <div class="text-green-600 text-5xl mb-3">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h4 class="text-xl font-semibold text-gray-800 mb-2"><?= $file['price'] > 0 ? 'Төлбөр амжилттай!' : 'Үнэгүй файл' ?></h4>
                                    <p class="text-gray-600">Файлаа татаж авах боломжтой</p>
                                </div>
                                
                                <div class="text-center">
                                    <a href="<?= $file['file_url'] ?>" class="gradient-bg text-white py-3 px-8 rounded-md font-medium hover:bg-purple-700 transition inline-block" download>
                                        <i class="fas fa-download mr-2"></i> Файл татах
                                    </a>
                                    
                                    <div id="download-progress-container" class="hidden mt-4">
                                        <p class="download-status-text text-gray-500">Файл татагдаж байна...</p>
                                        
                                        <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                                            <div id="progress-bar" class="progress-bar bg-purple-600 h-2 rounded-full" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <h3 class="text-xl font-semibold text-gray-800 mb-6">Төлбөрийн хэрэгсэл сонгох</h3>
                            
                            <div class="space-y-4 mb-6">
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-gray-700">Дансны үлдэгдэл</p>
                                            <p class="text-2xl font-bold text-green-600">
                                                <?= number_format($balance) ?>₮
                                            </p>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="payment_method" value="balance">
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium <?= $balance < $file['price'] ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $balance < $file['price'] ? 'disabled' : '' ?>>
                                                Дансаар төлөх
                                            </button>
                                        </form>
                                    </div>
                                    <?php if ($balance < $file['price']): ?>
                                        <p class="text-red-500 text-sm mt-2">Таны дансны үлдэгдэл хүрэлцэхгүй байна</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="payment-method rounded-lg p-4 cursor-pointer" data-method="qpay">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                            <img src="assets/images/qpay-logo.png" alt="QPay" class="w-8 h-8">
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-800">QPay</h4>
                                            <p class="text-sm text-gray-600">Монголын тэргүүлэх төлбөрийн систем</p>
                                        </div>
                                        <div class="ml-4">
                                            <i class="fas fa-check-circle text-2xl text-gray-300 payment-check"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="payment-method rounded-lg p-4 cursor-pointer" data-method="card">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                            <i class="fas fa-credit-card text-purple-600 text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-800">Картаар төлөх</h4>
                                            <p class="text-sm text-gray-600">Visa, MasterCard, UnionPay</p>
                                        </div>
                                        <div class="ml-4">
                                            <i class="fas fa-check-circle text-2xl text-gray-300 payment-check"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="qpay-payment" class="mt-6 hidden"> <div id="qpay-loading-spinner" class="text-center p-4">
                                    <i class="fas fa-spinner fa-spin text-purple-600 text-3xl"></i>
                                    <p class="text-gray-600 mt-2">Нэхэмжлэх үүсгэж байна, түр хүлээнэ үү...</p>
                                </div>
                                
                                <div id="qpay-invoice-display" class="hidden text-center">
                                    </div>

                                <div id="qpay-error-display" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md">
                                    </div>
                            </div>
                            </div>                    
                            <form method="POST" id="card-payment" class="hidden">
                                <input type="hidden" name="payment_method" value="card">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Картын дугаар</label>
                                        <div class="relative">
                                            <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="1234 5678 9012 3456">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                                <i class="far fa-credit-card text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Дуусах хугацаа</label>
                                            <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="MM/YY">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">CVV код</label>
                                            <div class="relative">
                                                <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="123">
                                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                                    <i class="fas fa-question-circle text-gray-400" title="Картын ард байрлах 3 оронтой код"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Картын эзэмшигчийн нэр</label>
                                        <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Таны нэр">
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input id="save-card" type="checkbox" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                        <label for="save-card" class="ml-2 block text-sm text-gray-700">
                                            Картын мэдээллийг хадгалах
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="border-t border-gray-200 pt-6 mt-4">
                                    <button type="submit" class="w-full gradient-bg text-white py-3 rounded-md font-medium hover:bg-purple-700 transition">
                                        <i class="fas fa-lock mr-2"></i> Төлбөр төлөх (<?= number_format($file['price']) ?>₮)
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// Include footer
include 'includes/footer.php';

?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ===================================================================
    //  ЛОГИК 1: ФАЙЛ ТАТАХ ҮЕИЙН PROGRESS BAR
    // ===================================================================
    const downloadLink = document.querySelector('#download-section a[download]');
    if (downloadLink) {
        // ... (Энэ хэсэг танд хэвээрээ байсан тул би өөрчлөөгүй) ...
        downloadLink.addEventListener('click', function(e) {
            e.preventDefault();
            const fileUrl = this.href;
            const progressContainer = document.getElementById('download-progress-container');
            if (progressContainer) {
                progressContainer.classList.remove('hidden');
            }
            let progress = 0;
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.querySelector('.download-status-text');
            const interval = setInterval(() => {
                progress += Math.floor(Math.random() * 10) + 5;
                if (progress >= 100) {
                    progress = 100;
                    clearInterval(interval);
                    if (progressText) {
                        progressText.textContent = 'Файл амжилттай татагдлаа!';
                    }
                    this.innerHTML = '<i class="fas fa-check mr-2"></i> Татаж дууслаа';
                    this.classList.remove('gradient-bg', 'hover:bg-purple-700');
                    this.classList.add('bg-green-600', 'cursor-not-allowed');
                    this.style.pointerEvents = 'none'; 
                    setTimeout(() => {
                        window.location.href = fileUrl;
                    }, 500);
                }
                if (progressBar) {
                    progressBar.style.width = `${progress}%`;
                }
            }, 200);
        });
    }

    // ===================================================================
    //  ЛОГИК 2: ТӨЛБӨРИЙН ХЭРЭГСЭЛ СОНГОХ (САЙЖРУУЛСАН)
    // ===================================================================
    const paymentMethods = document.querySelectorAll('.payment-method');
    const qpaySection = document.getElementById('qpay-payment');
    const cardSection = document.getElementById('card-payment');
    
    // --- ШИНЭ: Polling-д зориулсан хувьсагч ---
    let qpayPollInterval = null;
    const currentFileId = <?php echo $file_id; ?>; // PHP-с file_id-г авах

    // --- ШИНЭ: Invoice үүсгэх функц ---
    const createInvoice = async () => {
        if (qpayPollInterval) clearInterval(qpayPollInterval);

        const qpayLoading = document.getElementById('qpay-loading-spinner');
        const qpayInvoice = document.getElementById('qpay-invoice-display');
        const qpayError = document.getElementById('qpay-error-display');

        qpayLoading.classList.remove('hidden');
        qpayInvoice.classList.add('hidden');
        qpayError.classList.add('hidden');

        try {
            const formData = new FormData();
            formData.append('file_id', currentFileId);

            const response = await fetch('download.php?action=create_invoice', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                qpayLoading.classList.add('hidden');
                qpayInvoice.innerHTML = buildInvoiceHTML(data.invoice_data);
                qpayInvoice.classList.remove('hidden');
                
                // Нэхэмжлэх үүссэний дараа Polling эхлүүлэх
                startPaymentPolling();
            } else {
                qpayLoading.classList.add('hidden');
                qpayError.innerHTML = data.message || 'Нэхэмжлэх үүсгэхэд тодорхойгүй алдаа гарлаа.';
                qpayError.classList.remove('hidden');
            }
        } catch (error) {
            qpayLoading.classList.add('hidden');
            qpayError.innerHTML = 'Сервертэй холбогдоход алдаа гарлаа: ' + error.message;
            qpayError.classList.remove('hidden');
        }
    };

    // --- ШИНЭ: QPay-н UI-г үүсгэх туслах функц ---
    const buildInvoiceHTML = (invoiceData) => {
        let urlsHTML = '';
        if (invoiceData.urls && Array.isArray(invoiceData.urls)) {
            urlsHTML = invoiceData.urls.map(link => {
                // QPay-н хариуд logo_url эсвэл logo гэж ирдэг
                let logoSrc = link.logo_url || link.logo; 
                if (link.link && logoSrc && link.name) {
                    return `
                    <a href="${link.link}" class="block" title="${link.name}" target="_blank">
                        <img src="${logoSrc}" alt="${link.name}" class="h-10 w-auto rounded-md shadow">
                    </a>`;
                }
                return '';
            }).join('');
        }

        return `
            <div id="payment-status-container" class="mb-6">
                <div id="payment-loading" class="bg-blue-50 border border-blue-200 text-blue-700 p-4 rounded-lg">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>QR кодыг уншуулж, төлбөрөө төлнө үү. Төлбөр автоматаар шалгагдана.</span>
                </div>
                <div id="payment-success" class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-lg hidden">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Төлбөр амжилттай! Хуудас 3 секундэд шинэчлэгдэж, татах хэсэг гарч ирнэ...</span>
                </div>
            </div>
            
            ${invoiceData.qr_image ? `<img src="data:image/png;base64,${invoiceData.qr_image}" alt="QPay QR Code" class="mx-auto mb-6 border border-gray-300 rounded-lg max-w-[240px]">` : ''}
            
            ${urlsHTML ? `
            <p class="text-sm text-gray-500 mb-4">Эсвэл банкны аппликейшн ашиглан нэвтэрч төлнө үү:</p>
            <div class="flex flex-wrap justify-center gap-4 mb-6">${urlsHTML}</div>` : ''}

            <div class="mt-6 pt-6 border-t border-gray-200">
                <button id="check-payment-btn" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-md font-medium mb-4 text-lg">
                    <i class="fas fa-redo mr-2"></i> Төлбөр шалгах
                </button>
                <a href="download.php?id=${currentFileId}" class="text-sm text-gray-500 hover:text-gray-600">
                    Цуцлах
                </a>
            </div>
        `;
    };

    // --- ШИНЭ: Polling-г эхлүүлэх болон шалгах функц ---
    const checkPayment = async () => {
        try {
            const response = await fetch('download.php?action=check_payment');
            const data = await response.json();

            if (data.success) {
                // АМЖИЛТТАЙ!
                clearInterval(qpayPollInterval);
                const successDiv = document.getElementById('payment-success');
                const loadingDiv = document.getElementById('payment-loading');
                if (loadingDiv) loadingDiv.classList.add('hidden');
                if (successDiv) successDiv.classList.remove('hidden');

                const checkBtn = document.getElementById('check-payment-btn');
                if(checkBtn) {
                    checkBtn.disabled = true;
                    checkBtn.innerHTML = '<i class="fas fa-check"></i> Амжилттай';
                }

                // Хуудсыг дахин ачаалах (download link-г харуулахын тулд)
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                console.log('QPay Check (File):', data.message);
                if (data.message.includes('processing')) {
                     const loadingDiv = document.getElementById('payment-loading');
                     if(loadingDiv) loadingDiv.querySelector('span').textContent = 'Төлбөр баталгаажсан. Гүйлгээг боловсруулж байна...';
                }
            }
        } catch (error) {
            console.error('Error checking payment:', error);
        }
    };

    const startPaymentPolling = () => {
        if (qpayPollInterval) clearInterval(qpayPollInterval);
        qpayPollInterval = setInterval(checkPayment, 5000); // 5 сек тутамд шалгах

        // "Төлбөр шалгах" товчийг UI-д нэмэгдсэний дараа event listener холбох
        setTimeout(() => {
            const checkBtn = document.getElementById('check-payment-btn');
            if (checkBtn) {
                checkBtn.addEventListener('click', () => {
                    checkBtn.disabled = true;
                    checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Шалгаж байна...';

                    checkPayment().finally(() => {
                        const successDiv = document.getElementById('payment-success');
                        if (successDiv && !successDiv.classList.contains('hidden')) {
                            // Амжилттай болсон тул товч идэвхгүй хэвээр үлдэнэ
                        } else {
                            checkBtn.disabled = false;
                            checkBtn.innerHTML = '<i class="fas fa-redo mr-2"></i> Төлбөр шалгах';
                        }
                    });
                });
            }
        }, 100); 
    };

    // --- ТӨЛБӨРИЙН АРГА СОНГОХ ҮНДСЭН ЛОГИК ---
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            if (qpayPollInterval) clearInterval(qpayPollInterval);

            paymentMethods.forEach(m => {
                m.classList.remove('border-purple-500', 'ring-2', 'ring-purple-200');
                m.querySelector('.payment-check').classList.remove('text-purple-600');
                m.querySelector('.payment-check').classList.add('text-gray-300');
            });
            
            this.classList.add('border-purple-500', 'ring-2', 'ring-purple-200');
            this.querySelector('.payment-check').classList.remove('text-gray-300');
            this.querySelector('.payment-check').classList.add('text-purple-600');
            
            const methodType = this.dataset.method;
            
            if (qpaySection) qpaySection.classList.add('hidden');
            if (cardSection) cardSection.classList.add('hidden');
            
            if (methodType === 'qpay' && qpaySection) {
                qpaySection.classList.remove('hidden');
                // --- ШИНЭ: Нэхэмжлэхийг шууд үүсгэх ---
                createInvoice();
            } else if (methodType === 'card' && cardSection) {
                cardSection.classList.remove('hidden');
            }
        });
    });

});
</script>