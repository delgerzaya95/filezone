<?php
session_start();
require_once 'includes/functions.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to either the stored URL or index.php
    $redirect_url = $_SESSION['redirect_url'] ?? 'index.php';
    unset($_SESSION['redirect_url']);
    header("Location: $redirect_url");
    exit();
}

// Initialize error variable
$error = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Хэрэглэгчийн нэр болон нууц үгээ оруулна уу";
    } else {
        // Connect to database
        $conn = db_connect();
        
        // Prepare SQL to prevent SQL injection
        $sql = "SELECT id, username, email, password FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Verify password
            if (password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                // Password correct - set session variables
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                
                // Handle "remember me" if checked
                if (isset($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $update_sql = "UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "ssi", $token, $expiry, $row['id']);
                    mysqli_stmt_execute($update_stmt);
                    
                    setcookie('remember', $token, time() + (30 * 24 * 60 * 60), '/');
                }
                
                // Close connection
                mysqli_close($conn);
                
                // Redirect to either the stored URL or index.php
                $redirect_url = $_SESSION['redirect_url'] ?? 'index.php';
                unset($_SESSION['redirect_url']);
                header("Location: $redirect_url");
                exit();
            } else {
                $error = "Хэрэглэгчийн нэр эсвэл нууц үг буруу байна";
            }
        } else {
            $error = "Хэрэглэгчийн нэр эсвэл нууц үг буруу байна";
        }
        
        // Close connection
        mysqli_close($conn);
    }
    
    // If there's an error, redirect back with error message
    if (!empty($error)) {
        header("Location: login.php?error=" . urlencode($error));
        exit();
    }
}

// Check for remember me cookie (Сайжруулсан хувилбар)
if (isset($_COOKIE['remember']) && !empty($_COOKIE['remember']) && !isset($_SESSION['user_id'])) {
    $conn = db_connect();
    $token = $_COOKIE['remember'];
    
    // АЮУЛГҮЙ БАЙДАЛ: Token хоосон биш, мөн хэт богино биш эсэхийг шалгах
    if (strlen($token) > 20) {
        $sql = "SELECT id, username, email FROM users WHERE remember_token = ? AND token_expiry > NOW()";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Session Fixation-аас сэргийлж ID-г шинэчлэх
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['email'] = $row['email'];
            
            $redirect_url = $_SESSION['redirect_url'] ?? 'index.php';
            unset($_SESSION['redirect_url']);
            header("Location: $redirect_url");
            exit();
        }
    }
    // Хэрэв cookie хүчингүй бол устгах (цэвэрлэх)
    setcookie('remember', '', time() - 3600, '/');
    mysqli_close($conn);
}

require_once 'includes/header.php';
?>

<main class="container mx-auto px-4 py-6">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden login-form">
        <div class="gradient-bg py-4 px-6">
            <h2 class="text-white text-xl font-bold">НЭВТРЭХ</h2>
        </div>
        
        <div class="p-6">
            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Хэрэглэгчийн нэр эсвэл Имэйл</label>
                    <input type="text" id="username" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Нууц үг</label>
                    <input type="password" id="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">Намайг сана</label>
                    </div>
                    
                    <a href="forgot-password.php" class="text-sm text-purple-600 hover:underline">Нууц үгээ мартсан?</a>
                </div>
                
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-md text-sm font-medium">
                    Нэвтрэх
                </button>
            </form>
            
            <div class="mt-4 text-center">
                <p class="text-sm text-gray-600">Бүртгэлгүй юу? <a href="register.php" class="text-purple-600 hover:underline">Бүртгүүлэх</a></p>
            </div>
            
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Эсвэл</span>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-1 gap-3">
                    <a href="google-login.php" class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fab fa-google text-red-600"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>