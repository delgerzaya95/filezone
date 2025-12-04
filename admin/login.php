<?php
// Display errors for development, turn off in production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Use absolute path for includes for better reliability
require_once __DIR__ . '/include/db_connect.php';

// Verify session is properly started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is already logged in and redirect to the dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: index.php");
    exit();
}

$error = '';

// --- CSRF TOKEN GENERATION ---
// Create a CSRF token if one doesn't exist in the session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- CSRF TOKEN VALIDATION ---
    // Check if the submitted token matches the one in the session
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Хүсэлт буруу байна. Хуудсыг дахин ачааллана уу.';
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if (empty($username) || empty($password)) {
            $error = 'Хэрэглэгчийн нэр болон нууц үгээ оруулна уу!';
        } else {
            try {
                $pdo = db_connect();
                
                $stmt = $pdo->prepare("SELECT id as user_id, username, password, role, status 
                                      FROM users 
                                      WHERE username = :username AND role = 'admin'");
                
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                
                if ($stmt->rowCount() === 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (password_verify($password, $user['password'])) {
                        if ($user['status'] === 'active') {
                            // Set session variables
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            
                            // --- SESSION REGENERATION (SECURITY) ---
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);
                            
                            // Update last login time
                            $update_stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = :user_id");
                            $update_stmt->bindParam(':user_id', $user['user_id']);
                            $update_stmt->execute();
                            
                            // Redirect to the admin dashboard
                            header("Location: index.php");
                            exit();
                        } else {
                            $error = 'Таны бүртгэл идэвхгүй байна!';
                        }
                    } else {
                        $error = 'Хэрэглэгчийн нэр эсвэл нууц үг буруу байна!';
                    }
                } else {
                    $error = 'Админ эрхгүй хэрэглэгч эсвэл буруу нэр!';
                }
            } catch (PDOException $e) {
                // --- SECURE ERROR HANDLING ---
                // Log the detailed error for the developer
                error_log('Login PDOException: ' . $e->getMessage());
                // Show a generic error message to the user
                $error = 'Системийн алдаа гарлаа. Түр хүлээгээд дахин оролдоно уу.';
            }
        }
    }
}

// All PHP logic is done. Now, start HTML output.
?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filezone - Админ Нэвтрэх</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-lg shadow-xl overflow-hidden">
                <div class="gradient-bg text-white p-6 text-center">
                    <h1 class="text-2xl font-bold">
                        <i class="fas fa-crown mr-2"></i>
                        Filezone Админ
                    </h1>
                    <p class="text-white text-opacity-80 mt-1">Файлын платформын удирдлага</p>
                </div>
                
                <form method="POST" action="login.php" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                            <i class="fas fa-user mr-1"></i> Хэрэглэгчийн нэр
                        </label>
                        <input type="text" name="username" id="username" required
                        class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                            <i class="fas fa-lock mr-1"></i> Нууц үг
                        </label>
                        <input type="password" name="password" id="password" required
                        class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <button type="submit" class="gradient-bg text-white font-bold py-3 px-4 rounded w-full hover:opacity-90 focus:outline-none focus:shadow-outline">
                            <i class="fas fa-sign-in-alt mr-2"></i> Нэвтрэх
                        </button>
                    </div>
                </form>
                
                <div class="bg-gray-50 px-6 py-4 text-center">
                    <p class="text-gray-600 text-sm">
                        © <?php echo date('Y'); ?> Filezone Файл Дэлгүүр. Бүх эрх хуулиар хамгаалагдсан.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// End and send the output buffer
ob_end_flush();
?>