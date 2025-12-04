<?php
session_start();
require_once 'includes/functions.php';

// Хэрэглэгч нэвтэрсэн бол DB дээрх token-ийг устгах
if (isset($_SESSION['user_id'])) {
    $conn = db_connect();
    $sql = "UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_close($conn);
}

// Session устгах
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Remember Cookie устгах
setcookie('remember', '', time() - 3600, '/');

header("Location: login.php");
exit();
?>

<!-- Main Content -->
<main class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-10">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                Та системээс амжилттай гарлаа.
            </div>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4 mt-8">
                <a href="login.php" class="gradient-bg text-white px-6 py-3 rounded-md font-medium hover:bg-purple-700 transition flex items-center justify-center">
                    <i class="fas fa-sign-in-alt mr-2"></i> Дахин нэвтрэх
                </a>
                <a href="index.php" class="bg-white text-purple-600 border border-purple-600 px-6 py-3 rounded-md font-medium hover:bg-purple-50 transition flex items-center justify-center">
                    <i class="fas fa-home mr-2"></i> Нүүр хуудас руу буцах
                </a>
            </div>
        </div>
    </div>
</main>

<?php
// Include footer
include 'includes/footer.php';

// Redirect after 5 seconds
header("refresh:5;url=index.php");
exit;
?>