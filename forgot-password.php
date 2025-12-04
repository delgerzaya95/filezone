<?php
session_start();
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Имэйл хаягаа оруулна уу.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Имэйл хаяг буруу байна.";
    } else {
        $conn = db_connect();
        
        // Имэйл бүртгэлтэй эсэхийг шалгах
        $stmt = mysqli_prepare($conn, "SELECT id, username FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            // Токен үүсгэх
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 цагийн хугацаатай

            // Баазад токен хадгалах
            $update_sql = "UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ssi", $token, $expiry, $user['id']);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // ========================================================
                //  ШИНЭ ХЭСЭГ: BREVO API АШИГЛАН МЭЙЛ ИЛГЭЭХ
                // ========================================================
                
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
                
                $subject = 'Нууц үг сэргээх хүсэлт';
                
                // Мэйлийн агуулга
                $body = "
                    <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:20px; border:1px solid #eee; border-radius:5px;'>
                        <h2 style='color:#7c3aed;'>Сайн байна уу, {$user['username']}?</h2>
                        <p>Та FileZone.mn дээрх нууц үгээ сэргээх хүсэлт илгээсэн байна.</p>
                        <p>Доорх холбоос дээр дарж нууц үгээ солино уу (Холбоос 1 цагийн дараа хүчингүй болно):</p>
                        <p style='margin: 20px 0;'>
                            <a href='{$resetLink}' style='background:#7c3aed; color:white; padding:12px 24px; text-decoration:none; border-radius:5px; font-weight:bold;'>Нууц үг сэргээх</a>
                        </p>
                        <p style='color:#666; font-size:13px;'>Хэрэв та энэ хүсэлтийг илгээгээгүй бол энэ мэйлийг тоохгүй орхино уу.</p>
                    </div>
                ";

                // functions.php доторх функцээ дуудах
                if (send_email_via_brevo($email, $user['username'], $subject, $body)) {
                    $success = "Таны имэйл хаяг руу сэргээх холбоос илгээлээ. Spam фолдероо шалгахаа мартуузай.";
                } else {
                    $error = "Мэйл илгээхэд алдаа гарлаа. Та дараа дахин оролдоно уу.";
                }
                
            } else {
                $error = "Өгөгдлийн сантай холбогдоход алдаа гарлаа.";
            }
        } else {
            // Аюулгүй байдлын үүднээс имэйл байхгүй байсан ч амжилттай гэж харагдуулж болно
            $error = "Энэ имэйл хаяг бүртгэлгүй байна.";
        }
    }
}

require_once 'includes/header.php';
?>

<main class="container mx-auto px-4 py-12 flex items-center justify-center" style="min-height: 60vh;">
    <div class="w-full max-w-md bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="gradient-bg py-4 px-6">
            <h2 class="text-white text-xl font-bold text-center">Нууц үг сэргээх</h2>
        </div>
        
        <div class="p-8">
            <p class="text-gray-600 text-sm mb-6 text-center">
                Бүртгэлтэй имэйл хаягаа оруулна уу. Бид танд нууц үг сэргээх холбоос илгээх болно.
            </p>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-4 text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-4 text-sm">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form action="forgot-password.php" method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Имэйл хаяг</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" required 
                               class="w-full pl-10 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                               placeholder="name@example.com">
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-md text-sm font-medium transition duration-200">
                    Илгээх
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="login.php" class="text-sm text-purple-600 hover:text-purple-800 font-medium">
                    <i class="fas fa-arrow-left mr-1"></i> Нэвтрэх хэсэг рүү буцах
                </a>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>