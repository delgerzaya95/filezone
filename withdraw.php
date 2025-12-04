<?php
// Start session and include essential files
session_start();
require_once 'includes/functions.php';

// ===================================================================
//  AUTHENTICATION & SETUP
// ===================================================================

// Check if the user is logged in, redirect to login page if not
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the logged-in user's ID
$user_id = (int)$_SESSION['user_id'];

// Establish a database connection
$conn = db_connect();
if (!$conn) {
    // Terminate script if the connection fails
    die("Database connection failed: " . mysqli_connect_error());
}

// ===================================================================
//  VARIABLE INITIALIZATION
// ===================================================================

$error_message = '';
$success_message = '';
$MIN_WITHDRAWAL_AMOUNT = 20000; // Minimum withdrawal amount

// ===================================================================
//  POST REQUEST HANDLING (FORM SUBMISSION)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'], $_POST['withdraw_method'], $_POST['details'])) {

    // Sanitize and validate the input data
    $withdraw_amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $withdraw_method = htmlspecialchars($_POST['withdraw_method']);
    $details = htmlspecialchars($_POST['details']);
    
    // НЭМЭЛТ: bank_name болон account_name-ийг авах
    $bank_name = isset($_POST['bank_name']) ? htmlspecialchars($_POST['bank_name']) : null;
    $account_name = isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : null;

    // Fetch user's current balance
    $user_sql = "SELECT balance FROM users WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $user = mysqli_fetch_assoc($user_result);
    $current_balance = $user ? (float)$user['balance'] : 0;

    // ===================================================================
    //  VALIDATION CHECKS
    // ===================================================================

    if ($withdraw_amount === false || $withdraw_amount <= 0) {
        $error_message = "Хүчингүй дүн оруулсан байна.";
    } elseif ($withdraw_amount < $MIN_WITHDRAWAL_AMOUNT) {
        $error_message = "Мөнгө татах доод хэмжээ " . number_format($MIN_WITHDRAWAL_AMOUNT, 2) . "₮ байна.";
    } elseif ($withdraw_amount > $current_balance) {
        $error_message = "Таны дансны үлдэгдэл хүрэлцэхгүй байна.";
    } elseif (empty($details)) {
        $error_message = "Татгалзан авах дансны мэдээллээ оруулна уу.";
    } elseif (!in_array($withdraw_method, ['bank', 'qpay'])) {
        $error_message = "Буруу татан авалтын төрөл байна.";
    } 
    // НЭМЭЛТ: Банкны мэдээллийн шалгалт
    elseif ($withdraw_method == 'bank' && (empty($bank_name) || empty($account_name))) {
        $error_message = "Банкны нэр болон дансны нэрийг оруулна уу.";
    } else {
        // If all checks pass, proceed with the transaction
        mysqli_begin_transaction($conn);

        try {
            // 1. Update the user's balance in the `users` table
            $update_sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "di", $withdraw_amount, $user_id);
            $update_executed = mysqli_stmt_execute($update_stmt);

            if (!$update_executed) {
                throw new Exception("Дансны үлдэгдлийг шинэчлэхэд алдаа гарлаа.");
            }

            // 2. Record the withdrawal in the `user_transactions` table
            $description = "Мөнгө татах (" . ucfirst($withdraw_method) . "): " . $details;
            $insert_sql = "INSERT INTO user_transactions (user_id, type, amount, description) VALUES (?, 'withdrawal', ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "ids", $user_id, $withdraw_amount, $description);
            $insert_executed = mysqli_stmt_execute($insert_stmt);

            if (!$insert_executed) {
                throw new Exception("Гүйлгээний түүхэнд бичихэд алдаа гарлаа.");
            }
            
            // 3. (NOTIFICATION) Insert into a new withdrawal_requests table for admin review
            // This is a crucial step for managing and tracking requests.
            // ШИНЭЧЛЭЛТ: bank_name болон account_name-ийг хадгалах
            $request_sql = "INSERT INTO withdrawal_requests (user_id, amount, method, bank_name, account_name, details, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $request_stmt = mysqli_prepare($conn, $request_sql);
            mysqli_stmt_bind_param($request_stmt, "idssss", $user_id, $withdraw_amount, $withdraw_method, $bank_name, $account_name, $details);
            $request_executed = mysqli_stmt_execute($request_stmt);

            if (!$request_executed) {
                throw new Exception("Мөнгө татах хүсэлтийг бүртгэхэд алдаа гарлаа.");
            }

            // If all queries succeed, commit the transaction
            mysqli_commit($conn);
            $success_message = number_format($withdraw_amount, 2) . "₮ татах хүсэлтийг амжилттай илгээлээ. Удахгүй таны дансанд шилжих болно.";
            
            // Send email notification to admin
            notify_admin_of_withdrawal($conn, $user_id, $withdraw_amount, $withdraw_method, $bank_name, $account_name, $details);

        } catch (Exception $e) {
            // If any query fails, roll back all changes
            mysqli_rollback($conn);
            $error_message = "Гүйлгээ хийхэд алдаа гарлаа: " . $e->getMessage();
        }
    }
} else {
    // Redirect if not a POST request
    header("Location: profile.php");
    exit();
}

// ===================================================================
//  PAGE DISPLAY
// ===================================================================

include 'includes/header.php';
include 'includes/navigation.php';
?>

<!-- Main Content -->
<main class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <?php if ($success_message): ?>
                <!-- Success Message Display -->
                <div class="text-green-500 text-6xl mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Хүсэлт амжилттай</h2>
                <p class="text-gray-700 text-lg mb-6"><?= htmlspecialchars($success_message) ?></p>
            <?php else: ?>
                <!-- Error Message Display -->
                <div class="text-red-500 text-6xl mb-4">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Алдаа гарлаа</h2>
                <p class="text-gray-700 text-lg mb-6"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>

            <div class="flex justify-center gap-4 mt-8">
                <a href="profile.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-md font-medium">
                    <i class="fas fa-user mr-2"></i> Миний хуудас руу буцах
                </a>
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-md font-medium">
                    <i class="fas fa-home mr-2"></i> Нүүр хуудас
                </a>
            </div>
        </div>
    </div>
</main>

<?php
// Include the website footer
include 'includes/footer.php';
?>