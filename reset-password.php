<?php
session_start();
require_once 'includes/functions.php';

$error = '';
$success = '';
$valid_token = false;

// Token шалгах
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $conn = db_connect();
    
    // Token таарах болон хугацаа нь дуусаагүй эсэхийг шалгах
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        $valid_token = true;
    } else {
        $error = "Холбоос хүчингүй болсон эсвэл буруу байна.";
    }
} else {
    header("Location: login.php");
    exit();
}

// Нууц үг солих
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token']; // Hidden input-ээс авах

    if (strlen($password) < 6) {
        $error = "Нууц үг дор хаяж 6 тэмдэгт байх ёстой.";
    } elseif ($password !== $confirm_password) {
        $error = "Нууц үгнүүд таарахгүй байна.";
    } else {
        // Шинэ нууц үгийг hash хийх
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Нууц үгийг шинэчилж, токенийг устгах
        $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $token);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Нууц үг амжилттай солигдлоо! Та одоо нэвтрэх боломжтой.";
            // Токенийг дахин ашиглахгүйн тулд valid_token-г false болгоно
            $valid_token = false;
        } else {
            $error = "Нууц үг солиход алдаа гарлаа.";
        }
    }
}

require_once 'includes/header.php';
?>

<main class="container mx-auto px-4 py-12 flex items-center justify-center" style="min-height: 60vh;">
    <div class="w-full max-w-md bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="gradient-bg py-4 px-6">
            <h2 class="text-white text-xl font-bold text-center">Шинэ нууц үг зохиох</h2>
        </div>
        
        <div class="p-8">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-4 text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-4 text-sm">
                    <?php echo htmlspecialchars($success); ?>
                    <div class="mt-4 text-center">
                        <a href="login.php" class="inline-block bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Нэвтрэх</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($valid_token && empty($success)): ?>
            <form action="reset-password.php?token=<?= htmlspecialchars($token) ?>" method="POST" class="space-y-6">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Шинэ нууц үг</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Нууц үг давтах</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>
                
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-md text-sm font-medium transition duration-200">
                    Нууц үг солих
                </button>
            </form>
            <?php endif; ?>
            
            <?php if (!$valid_token && empty($success)): ?>
                <div class="text-center mt-4">
                    <a href="forgot-password.php" class="text-purple-600 hover:underline">Дахин хүсэлт илгээх</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>