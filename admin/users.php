<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Хэрэглэгч нэвтэрсэн эсэхийг шалгах
if (!isset($_SESSION['user_id'])) {
    // Нэвтэрсний дараа яг энэ хуудас руу буцаж ирэхийн тулд хаягийг нь хадгалах
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Хэрэв нэвтрээгүй бол нэвтрэх хуудас руу үсэргэнэ
    header("Location: login.php");
    exit(); // Энэ мөр маш чухал!
}
    // Database connection
    $conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, "utf8");
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new user
        if (isset($_POST['add_user'])) {
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $location = mysqli_real_escape_string($conn, $_POST['location']);
            $is_premium = isset($_POST['is_premium']) ? 1 : 0;
            $balance = floatval($_POST['balance']);
            
            $sql = "INSERT INTO users (username, email, password, full_name, phone, location, is_premium, balance) 
            VALUES ('$username', '$email', '$password', '$full_name', '$phone', '$location', $is_premium, $balance)";
            
            if (mysqli_query($conn, $sql)) {
                header("Location: users.php");
                exit();
            } else {
                $error = "Error adding user: " . mysqli_error($conn);
            }
        }
        
        // Delete user
        if (isset($_POST['delete_user'])) {
            $user_id = intval($_POST['user_id']);
            $sql = "DELETE FROM users WHERE id = $user_id";
            
            if (mysqli_query($conn, $sql)) {
                header("Location: users.php");
                exit();
            } else {
                $error = "Error deleting user: " . mysqli_error($conn);
            }
        }
        
        // Toggle premium status
        if (isset($_POST['toggle_premium'])) {
            $user_id = intval($_POST['user_id']);
            $current_status = intval($_POST['current_status']);
            $new_status = $current_status ? 0 : 1;
            
            $sql = "UPDATE users SET is_premium = $new_status WHERE id = $user_id";
            
            if (mysqli_query($conn, $sql)) {
                header("Location: users.php");
                exit();
            } else {
                $error = "Error updating user: " . mysqli_error($conn);
            }
        }
        
        // Update balance
        if (isset($_POST['update_balance'])) {
            $user_id = intval($_POST['user_id']);
            $balance = floatval($_POST['balance']);
            
            $sql = "UPDATE users SET balance = $balance WHERE id = $user_id";
            
            if (mysqli_query($conn, $sql)) {
                header("Location: users.php");
                exit();
            } else {
                $error = "Error updating balance: " . mysqli_error($conn);
            }
        }

        // Update user
        if (isset($_POST['update_user'])) {
            $user_id = intval($_POST['user_id']);
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $location = mysqli_real_escape_string($conn, $_POST['location']);
            $balance = floatval($_POST['balance']);
            $is_premium = isset($_POST['is_premium']) ? 1 : 0;
            
            $sql = "UPDATE users SET 
            username = '$username',
            email = '$email',
            full_name = '$full_name',
            phone = '$phone',
            location = '$location',
            balance = $balance,
            is_premium = $is_premium,
            is_verified = ".(isset($_POST['is_verified']) ? 1 : 0)."
            WHERE id = $user_id";
            
            if (mysqli_query($conn, $sql)) {
                header("Location: users.php");
                exit();
            } else {
                $error = "Error updating user: " . mysqli_error($conn);
            }
        }
    }
    
    // Search functionality
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
    $filter_role = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
    
    // Base query
    $sql = "SELECT * FROM users WHERE 1=1";
    
    // Apply filters
    if (!empty($search)) {
        $sql .= " AND (username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%')";
    }
    
    if ($filter_status === 'active') {
        $sql .= " AND last_active > DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    } elseif ($filter_status === 'inactive') {
        $sql .= " AND last_active <= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    }
    
    if ($filter_role === 'premium') {
        $sql .= " AND is_premium = 1";
    } elseif ($filter_role === 'admin') {
        $sql .= " AND is_admin = 1";
    }
    
    // Get total count for pagination
    $count_result = mysqli_query($conn, $sql);
    $total_users = mysqli_num_rows($count_result);
    
    // Pagination
    $per_page = 10;
    $total_pages = ceil($total_users / $per_page);
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    $sql .= " LIMIT $offset, $per_page";
    $result = mysqli_query($conn, $sql);
    
    // Get all users for stats
    $stats_sql = "SELECT 
    COUNT(*) as total_users,
    SUM(is_premium) as premium_users,
    SUM(last_active > DATE_SUB(NOW(), INTERVAL 1 MONTH)) as active_users
    FROM users";
    $stats_result = mysqli_query($conn, $stats_sql);
    $stats = mysqli_fetch_assoc($stats_result);
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>НАРХАН - Хэрэглэгчийн удирдлага</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="css/styles.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#7c3aed',
                        dark: '#1e293b',
                        light: '#f8fafc',
                        admin: '#2d3748'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Admin Layout -->
    <div class="flex h-screen">

        <?php include 'sidebar.php' ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Mobile Header -->
            <header class="admin-header text-white py-4 px-6 flex items-center justify-between md:hidden">
                <button class="text-white">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-bold">НАРХАН Админ</h1>
                <div>
                    <i class="fas fa-bell"></i>
                </div>
            </header>
            
            <!-- Admin Header -->
            <header class="bg-white shadow-sm py-4 px-6 hidden md:flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Хэрэглэгчийн удирдлага</h2>
                    <p class="text-gray-600">Нийт <?php echo number_format($total_users); ?> бүртгэлтэй хэрэглэгч</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                        <span class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-4 h-4 text-xs flex items-center justify-center">3</span>
                    </div>
                    <div class="flex items-center">
                        <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                        alt="Admin" class="w-10 h-10 rounded-full">
                        <span class="ml-3 text-gray-700">Админ хэрэглэгч</span>
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
                
                <!-- User Management Tools -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">Хэрэглэгчийн жагсаалт</h3>
                        <p class="text-gray-600">Бүх бүртгэлтэй хэрэглэгчид</p>
                    </div>
                    
                    <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-2 w-full md:w-auto">
                        <form method="GET" action="users.php" class="relative">
                            <input type="text" name="search" placeholder="Хэрэглэгч хайх..." 
                            value="<?php echo htmlspecialchars($search); ?>" 
                            class="search-box w-full md:w-64 border border-gray-300 rounded-md py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </form>
                        <button onclick="document.getElementById('addUserModal').classList.remove('hidden')" 
                        class="gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700 flex items-center justify-center">
                        <i class="fas fa-plus mr-2"></i> Шинэ хэрэглэгч
                    </button>
                </div>
            </div>

            <!-- User Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="stat-card bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 text-blue-600 p-3 rounded-full mr-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Нийт хэрэглэгчид</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['total_users']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 text-green-600 p-3 rounded-full mr-3">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Идэвхтэй хэрэглэгчид</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['active_users']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="bg-purple-100 text-purple-600 p-3 rounded-full mr-3">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Premium гишүүд</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['premium_users']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full mr-3">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Админ эрхтэй</p>
                            <p class="text-xl font-bold text-gray-800">5</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Filters -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <form method="GET" action="users.php" class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-4">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Эрх</label>
                        <select name="role" class="w-full md:w-48 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Бүх эрх</option>
                            <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Админ</option>
                            <option value="premium" <?php echo $filter_role === 'premium' ? 'selected' : ''; ?>>Premium гишүүн</option>
                            <option value="regular" <?php echo $filter_role === 'regular' ? 'selected' : ''; ?>>Энгийн гишүүн</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Идэвхтэй байдал</label>
                        <select name="status" class="w-full md:w-48 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Бүх хэрэглэгчид</option>
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Идэвхтэй</option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Идэвхгүй</option>
                        </select>
                    </div>

                    <div class="md:ml-auto">
                        <button type="submit" class="gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700">
                            <i class="fas fa-filter mr-1"></i> Шүүх
                        </button>
                        <a href="users.php" class="ml-2 bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300">
                            <i class="fas fa-sync-alt mr-1"></i> Цэвэрлэх
                        </a>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 data-table">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Хэрэглэгч</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Бүртгүүлсэн</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Баланс</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($user = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full" src="css/images/default_avatar.png" alt="">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('Y-m-d', strtotime($user['join_date'])); ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?php 
                                            $join_date = new DateTime($user['join_date']);
                                            $now = new DateTime();
                                            $interval = $join_date->diff($now);
                                            
                                            if ($interval->y > 0) {
                                                echo $interval->y . ' жилийн өмнө';
                                            } elseif ($interval->m > 0) {
                                                echo $interval->m . ' сарын өмнө';
                                            } elseif ($interval->d > 0) {
                                                echo $interval->d . ' хоногийн өмнө';
                                            } else {
                                                echo 'Өнөөдөр';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="font-bold"><?php echo number_format($user['balance'], 2); ?>₮</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['is_premium']): ?>
                                            <span class="badge-premium px-2 py-1 text-xs rounded-full">Premium</span>
                                        <?php endif; ?>
                                        <?php if ($user['is_verified']): ?>
                                            <span class="badge-success px-2 py-1 text-xs rounded-full ml-1">Баталгаажсан</span>
                                        <?php else: ?>
                                            <span class="badge-warning px-2 py-1 text-xs rounded-full ml-1">Баталгаажаагүй</span>
                                        <?php endif; ?>
                                        <?php if (strtotime($user['last_active']) > strtotime('-1 month')): ?>
                                        <span class="badge-active px-2 py-1 text-xs rounded-full ml-1">Идэвхтэй</span>
                                    <?php else: ?>
                                        <span class="badge-inactive px-2 py-1 text-xs rounded-full ml-1">Идэвхгүй</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo htmlspecialchars($user['phone']); ?>', '<?php echo htmlspecialchars($user['location']); ?>', <?php echo $user['is_premium']; ?>, <?php echo $user['balance']; ?>, <?php echo $user['is_verified']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="users.php" class="inline" onsubmit="return confirm('Та энэ хэрэглэгчийг устгахдаа итгэлтэй байна уу?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="delete_user" value="1">
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500">
                Showing <span class="font-medium"><?php echo ($page - 1) * $per_page + 1; ?></span> to <span class="font-medium"><?php echo min($page * $per_page, $total_users); ?></span> of <span class="font-medium"><?php echo number_format($total_users); ?></span> results
            </div>
            <div class="flex space-x-1">
                <?php if ($page > 1): ?>
                    <a href="users.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&role=<?php echo urlencode($filter_role); ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                       Previous
                   </a>
               <?php endif; ?>

               <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="users.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&role=<?php echo urlencode($filter_role); ?>" 
                   class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium <?php echo $i == $page ? 'text-white pagination-active' : 'text-gray-700 hover:bg-gray-50'; ?>">
                   <?php echo $i; ?>
               </a>
           <?php endfor; ?>

           <?php if ($page < $total_pages): ?>
            <a href="users.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&role=<?php echo urlencode($filter_role); ?>" 
               class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
               Next
           </a>
       <?php endif; ?>
   </div>
</div>
</main>
</div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Шинэ хэрэглэгч нэмэх</h3>
            <button onclick="document.getElementById('addUserModal').classList.add('hidden')" 
            class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <form method="POST" action="users.php">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Хэрэглэгчийн нэр</label>
            <input type="text" name="username" id="username" required 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="email">И-мэйл</label>
            <input type="email" name="email" id="email" required 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Нууц үг</label>
            <input type="password" name="password" id="password" required 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="full_name">Бүтэн нэр</label>
            <input type="text" name="full_name" id="full_name" 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Утас</label>
            <input type="text" name="phone" id="phone" 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="location">Байршил</label>
            <input type="text" name="location" id="location" 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="balance">Баланс</label>
            <input type="number" name="balance" id="balance" step="0.01" min="0" value="0" 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4 flex items-center">
            <input type="checkbox" name="is_premium" id="is_premium" 
            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
            <label for="is_premium" class="ml-2 block text-sm text-gray-700">Premium гишүүн</label>
        </div>

        <div class="mb-4 flex items-center">
            <input type="checkbox" name="is_verified" id="edit_is_verified" 
            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
            <label for="edit_is_verified" class="ml-2 block text-sm text-gray-700">Баталгаажсан</label>
        </div>

        <div class="flex justify-end">
            <button type="button" onclick="document.getElementById('addUserModal').classList.add('hidden')" 
            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
            Цуцлах
        </button>
        <button type="submit" name="add_user" 
        class="gradient-bg text-white font-bold py-2 px-4 rounded hover:bg-purple-700">
        Хадгалах
    </button>
</div>
</form>
</div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Хэрэглэгч засах</h3>
            <button onclick="document.getElementById('editUserModal').classList.add('hidden')" 
            class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <form method="POST" action="users.php">
        <input type="hidden" name="user_id" id="edit_user_id">

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_username">Хэрэглэгчийн нэр</label>
            <input type="text" name="username" id="edit_username" required 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_email">И-мэйл</label>
            <input type="email" name="email" id="edit_email" required 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_full_name">Бүтэн нэр</label>
            <input type="text" name="full_name" id="edit_full_name" 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_phone">Утас</label>
            <input type="text" name="phone" id="edit_phone" 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_location">Байршил</label>
            <input type="text" name="location" id="edit_location" 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4 flex items-center">
            <input type="checkbox" name="is_verified" id="edit_is_verified" 
            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
            <label for="edit_is_verified" class="ml-2 block text-sm text-gray-700">Баталгаажсан</label>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_balance">Баланс</label>
            <input type="number" name="balance" id="edit_balance" step="0.01" min="0" 
            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-4 flex items-center">
            <input type="checkbox" name="is_premium" id="edit_is_premium" 
            class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
            <label for="edit_is_premium" class="ml-2 block text-sm text-gray-700">Premium гишүүн</label>
        </div>

        <div class="flex justify-end">
            <button type="button" onclick="document.getElementById('editUserModal').classList.add('hidden')" 
            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
            Цуцлах
        </button>
        <button type="submit" name="update_user" 
        class="gradient-bg text-white font-bold py-2 px-4 rounded hover:bg-purple-700">
        Хадгалах
    </button>
</div>
</form>
</div>
</div>

<script>
        // Simple JavaScript for interactive elements
    document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.md\\:hidden button');
        const sidebar = document.querySelector('.admin-sidebar');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
            });
        }

            // User search functionality
        const searchBox = document.querySelector('.search-box');
        if (searchBox) {
            searchBox.addEventListener('input', function() {
                    // Implement search functionality here
                console.log('Searching for:', this.value);
            });
        }
    });

    function openEditModal(id, username, email, full_name, phone, location, is_premium, balance, is_verified) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_full_name').value = full_name || '';
        document.getElementById('edit_phone').value = phone || '';
        document.getElementById('edit_location').value = location || '';
        document.getElementById('edit_balance').value = balance;
        document.getElementById('edit_is_premium').checked = is_premium;
        document.getElementById('edit_is_verified').checked = is_verified;

        document.getElementById('editUserModal').classList.remove('hidden');
    }
</script>
</body>
</html>
<?php
mysqli_close($conn);
?>