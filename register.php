<?php
// Start session
session_start();

require_once 'includes/functions.php';
$conn = db_connect(); // Add this line to get the database connection

// Initialize variables
$username = $email = '';
$errors = array();

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $terms = isset($_POST['terms']) ? true : false;

    // Validate username
    if (empty($username)) {
        $errors['username'] = 'Хэрэглэгчийн нэр оруулна уу';
    } elseif (strlen($username) < 4) {
        $errors['username'] = 'Хэрэглэгчийн нэр хамгийн багадаа 4 тэмдэгтээс бүрдсэн байх ёстой';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Хэрэглэгчийн нэр зөвхөн үсэг, тоо, доогуур зураас агуулж болно';
    } else {
        // Check if username exists
        $query = "SELECT id FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $query);
        if (mysqli_num_rows($result) > 0) {
            $errors['username'] = 'Энэ хэрэглэгчийн нэр аль хэдийн бүртгэгдсэн байна';
        }
    }

    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Имэйл хаяг оруулна уу';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Хүчинтэй имэйл хаяг оруулна уу';
    } else {
        // Check if email exists
        $query = "SELECT id FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $query);
        if (mysqli_num_rows($result) > 0) {
            $errors['email'] = 'Энэ имэйл хаяг аль хэдийн бүртгэгдсэн байна';
        }
    }

    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Нууц үг оруулна уу';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Нууц үг хамгийн багадаа 8 тэмдэгтээс бүрдсэн байх ёстой';
    }

    // Validate confirm password
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Нууц үг таарахгүй байна';
    }

    // Validate terms
    if (!$terms) {
        $errors['terms'] = 'Үйлчилгээний нөхцөлийг зөвшөөрнө үү';
    }

    // If no errors, register user
    if (empty($errors)) {
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user into database
        $query = "INSERT INTO users (username, email, password, join_date, last_active) 
                  VALUES ('$username', '$email', '$password_hash', NOW(), NOW())";
        
        if (mysqli_query($conn, $query)) {
            // Registration successful
            $_SESSION['user_id'] = mysqli_insert_id($conn);
            $_SESSION['username'] = $username;
            $_SESSION['success'] = 'Та амжилттай бүртгүүллээ!';
            
            // Redirect to profile page
            header('Location: profile.php');
            exit();
        } else {
            $errors['database'] = 'Алдаа гарлаа: ' . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filezone - Бүртгүүлэх</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        .register-card {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .register-card:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        .input-field:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
        }
        .social-btn:hover {
            transform: translateY(-2px);
        }
        .password-strength {
            height: 4px;
            transition: all 0.3s ease;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Top Bar -->
    <div class="bg-gray-900 text-white py-2 px-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="tel:97655145313" class="text-sm hover:text-purple-300"><i class="fas fa-phone-alt mr-1"></i> (976) 5514-5313</a>
                <a href="mailto:koklikeshare@gmail.com" class="text-sm hover:text-purple-300"><i class="fas fa-envelope mr-1"></i> info@filezone.mn</a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="https://www.facebook.com/filezone.mn" target="_blank" class="text-white hover:text-purple-300">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="contact.php" class="text-sm hover:text-purple-300">Холбоо барих</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <a href="index.php">
                    <h1 class="text-3xl font-bold text-blue-900">Filezone</h1>
                </a>
                <h2 class="mt-6 text-2xl font-extrabold text-gray-900">
                    Шинэ бүртгэл үүсгэх
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Эсвэл <a href="login.php" class="font-medium text-purple-600 hover:text-purple-500">НЭВТРЭХ</a>
                </p>
                
                <!-- Display success message if redirected from registration -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <!-- Display database error if any -->
                <?php if (isset($errors['database'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline"><?php echo $errors['database']; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-lg shadow-md register-card p-8">
                <form class="mt-8 space-y-6" action="register.php" method="POST">
                    <div class="rounded-md shadow-sm space-y-4">
                        <!-- Username Field -->
                        <div>
                            <label for="username" class="sr-only">Хэрэглэгчийн нэр</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input id="username" name="username" type="text" autocomplete="username" required 
                                    class="input-field pl-10 block w-full px-3 py-2 border <?php echo isset($errors['username']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md focus:outline-none"
                                    placeholder="Хэрэглэгчийн нэр"
                                    value="<?php echo htmlspecialchars($username); ?>">
                            </div>
                            <?php if (isset($errors['username'])): ?>
                                <p class="error-message"><?php echo $errors['username']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Email Field -->
                        <div>
                            <label for="email" class="sr-only">Имэйл хаяг</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input id="email" name="email" type="email" autocomplete="email" required 
                                    class="input-field pl-10 block w-full px-3 py-2 border <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md focus:outline-none"
                                    placeholder="Имэйл хаяг"
                                    value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                            <?php if (isset($errors['email'])): ?>
                                <p class="error-message"><?php echo $errors['email']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Password Field -->
                        <div>
                            <label for="password" class="sr-only">Нууц үг</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input id="password" name="password" type="password" autocomplete="new-password" required 
                                    class="input-field pl-10 block w-full px-3 py-2 border <?php echo isset($errors['password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md focus:outline-none"
                                    placeholder="Нууц үг">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none" onclick="togglePassword('password', this)">
                                        <i class="far fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-1 flex space-x-1">
                                <div id="strength-weak" class="password-strength w-1/3 bg-gray-200 rounded-full"></div>
                                <div id="strength-medium" class="password-strength w-1/3 bg-gray-200 rounded-full"></div>
                                <div id="strength-strong" class="password-strength w-1/3 bg-gray-200 rounded-full"></div>
                            </div>
                            <p id="password-hint" class="mt-1 text-xs text-gray-500">Нууц үг дор хаяж 8 тэмдэгттэй байх ёстой</p>
                            <?php if (isset($errors['password'])): ?>
                                <p class="error-message"><?php echo $errors['password']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Confirm Password Field -->
                        <div>
                            <label for="confirm-password" class="sr-only">Нууц үг давтах</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input id="confirm-password" name="confirm-password" type="password" autocomplete="new-password" required 
                                    class="input-field pl-10 block w-full px-3 py-2 border <?php echo isset($errors['confirm_password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md focus:outline-none"
                                    placeholder="Нууц үг давтах">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none" onclick="togglePassword('confirm-password', this)">
                                        <i class="far fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                            <p id="password-match" class="mt-1 text-xs text-red-500 hidden">Нууц үг таарахгүй байна</p>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <p class="error-message"><?php echo $errors['confirm_password']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Terms Checkbox -->
                    <div class="flex items-center">
                        <input id="terms" name="terms" type="checkbox" required
                            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                        <label for="terms" class="ml-2 block text-sm text-gray-900">
                            Би <a href="terms.html" class="text-purple-600 hover:text-purple-500">Үйлчилгээний нөхцөл</a> болон <a href="privacy.html" class="text-purple-600 hover:text-purple-500">Нууцлалын бодлого</a>-ыг зөвшөөрч байна
                        </label>
                    </div>
                    <?php if (isset($errors['terms'])): ?>
                        <p class="error-message"><?php echo $errors['terms']; ?></p>
                    <?php endif; ?>

                    <!-- Submit Button -->
                    <div>
                        <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white gradient-bg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-user-plus text-purple-200"></i>
                            </span>
                            Бүртгүүлэх
                        </button>
                    </div>
                </form>

                <!-- Social Login Options -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">
                                Эсвэл
                            </span>
                        </div>
                    </div>

                    <div style="margin-top: 15px; text-align: center;">
                        <a href="google-login.php" style="display: block; background: #4285F4; color: white; width: 100%; border-radius: 5px; text-decoration: none; padding: 10px 0; font-family: sans-serif; font-weight: bold;">
                            Google-ээр нэвтрэх
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Login Link -->
            <div class="text-center text-sm text-gray-600 mt-4">
                <p>Бүртгэлтэй юу? <a href="login.php" class="font-medium text-purple-600 hover:text-purple-500">Нэвтрэх</a></p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);
            const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
            field.setAttribute('type', type);
            
            const icon = button.querySelector('i');
            icon.classList.toggle('fa-eye-slash');
            icon.classList.toggle('fa-eye');
        }
        
        // Password strength indicator
        const passwordField = document.getElementById('password');
        const weakBar = document.getElementById('strength-weak');
        const mediumBar = document.getElementById('strength-medium');
        const strongBar = document.getElementById('strength-strong');
        const passwordHint = document.getElementById('password-hint');
        
        passwordField.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Character variety
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update strength bars
            weakBar.style.backgroundColor = '#e5e7eb';
            mediumBar.style.backgroundColor = '#e5e7eb';
            strongBar.style.backgroundColor = '#e5e7eb';
            
            if (strength >= 1) {
                weakBar.style.backgroundColor = '#ef4444';
                passwordHint.textContent = 'Сул нууц үг';
            }
            if (strength >= 3) {
                mediumBar.style.backgroundColor = '#f59e0b';
                passwordHint.textContent = 'Дунд зэргийн нууц үг';
            }
            if (strength >= 5) {
                strongBar.style.backgroundColor = '#10b981';
                passwordHint.textContent = 'Хүчтэй нууц үг!';
            }
            
            if (password.length === 0) {
                passwordHint.textContent = 'Нууц үг дор хаяж 8 тэмдэгттэй байх ёстой';
            }
        });
        
        // Password match validation
        const confirmPasswordField = document.getElementById('confirm-password');
        const passwordMatchMessage = document.getElementById('password-match');
        
        confirmPasswordField.addEventListener('input', function() {
            if (this.value !== passwordField.value && this.value.length > 0) {
                passwordMatchMessage.classList.remove('hidden');
            } else {
                passwordMatchMessage.classList.add('hidden');
            }
        });
    </script>
</body>
</html>