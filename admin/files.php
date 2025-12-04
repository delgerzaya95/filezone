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
// Edit file хэсэгт нэмэх
if (isset($_POST['edit_file'])) {
    $file_id = intval($_POST['file_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    
    // Шинэ ангилал
    $main_category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0; // <== НЭМСЭН МӨР
    $subcategory_id = isset($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : 0;
    $child_category_id = isset($_POST['child_category_id']) ? intval($_POST['child_category_id']) : null;

    // Файлын мэдээллийг шинэчлэх
    $sql = "UPDATE files SET 
    title = '$title',
    description = '$description',
    price = $price,
    status = '$status',
    is_premium = $is_premium,
    last_updated = CURRENT_TIMESTAMP
    "; // <== ӨӨРЧИЛСӨН МӨР (WHERE-г түр хассан)
    
    // Хэрэв хэрэглэгч үндсэн ангилал сонгосон бол (0-ээс их) түүнийг шинэчлэх
    if ($main_category_id > 0) { // <== НЭМСЭН МӨР
        $sql .= ", category_id = $main_category_id"; // <== НЭМСЭН МӨР
    } // <== НЭМСЭН МӨР
    
    $sql .= " WHERE id = $file_id"; // <== НЭМСЭН МӨР (WHERE-г буцааж нэмсэн)
    
    if (mysqli_query($conn, $sql)) {
        // Ангилалыг шинэчлэх (file_categories junction table)
        if ($subcategory_id > 0) {
            // Эхлээд file_categories дотор бичлэг байгаа эсэхийг шалгах
            $check_sql = "SELECT * FROM file_categories WHERE file_id = $file_id";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Байгаа бол шинэчлэх
                $update_cat_sql = "UPDATE file_categories SET 
                    subcategory_id = $subcategory_id, 
                    child_category_id = " . ($child_category_id ? $child_category_id : 'NULL') . "
                    WHERE file_id = $file_id";
            } else {
                // Байхгүй бол шинээр үүсгэх
                $update_cat_sql = "INSERT INTO file_categories (file_id, subcategory_id, child_category_id) 
                    VALUES ($file_id, $subcategory_id, " . ($child_category_id ? $child_category_id : 'NULL') . ")";
            }
            mysqli_query($conn, $update_cat_sql);
        }
        
        header("Location: files.php");
        exit();
    } else {
        $error = "Error updating file: " . mysqli_error($conn);
    }
}
    // Approve file
    if (isset($_POST['approve_file'])) {
        $file_id = intval($_POST['file_id']);
        $sql = "UPDATE files SET status = 'approved' WHERE id = $file_id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: files.php");
            exit();
        } else {
            $error = "Error approving file: " . mysqli_error($conn);
        }
    }
    
    // Reject file
    if (isset($_POST['reject_file'])) {
        $file_id = intval($_POST['file_id']);
        $reject_reason = isset($_POST['reject_reason']) ? mysqli_real_escape_string($conn, $_POST['reject_reason']) : '';
        
    // Debug: Check what values are being received
        error_log("Rejecting file ID: $file_id, Reason: $reject_reason");
        
        $sql = "UPDATE files SET status = 'rejected', reject_reason = '$reject_reason' WHERE id = $file_id";
        
    // Debug: Output the SQL query
        error_log("SQL Query: $sql");

        if (mysqli_query($conn, $sql)) {
            $affected_rows = mysqli_affected_rows($conn);
            error_log("Affected rows: $affected_rows");
            header("Location: files.php");
            exit();
        } else {
            $error = "Error rejecting file: " . mysqli_error($conn);
            error_log($error);
        }
    }
    // Delete file
    // Delete file
    // Delete file
    if (isset($_POST['delete_file'])) {
        $file_id = intval($_POST['file_id']);

    // 1. Устгахаасаа өмнө файлын замыг мэдээллийн сангаас авах
        $query = "SELECT file_url, user_id FROM files WHERE id = $file_id";
        $result = mysqli_query($conn, $query);
        
        if ($file = mysqli_fetch_assoc($result)) {
        // ==> ӨӨРЧЛӨЛТ: Сервер дээр ажиллах замыг ".." нэмж үүсгэх
            $main_file_path_on_server = '../' . $file['file_url'];
            $user_id = $file['user_id'];
            
        // 2. Файлын жишээ зургуудын замыг авах
            $previews_query = "SELECT preview_url FROM file_previews WHERE file_id = $file_id";
            $previews_result = mysqli_query($conn, $previews_query);
            
        // 3. Алдаа гарахаас сэргийлж TRANSACTION эхлүүлэх
            mysqli_begin_transaction($conn);

            try {
            // 4. Үндсэн файлыг серверээс устгах
                if (file_exists($main_file_path_on_server) && is_file($main_file_path_on_server)) {
                    unlink($main_file_path_on_server);
                }

            // 5. Жишээ зургуудыг серверээс устгах
                while ($preview = mysqli_fetch_assoc($previews_result)) {
                // ==> ӨӨРЧЛӨЛТ: Сервер дээр ажиллах замыг ".." нэмж үүсгэх
                    $preview_path_on_server = '../' . $preview['preview_url'];
                    if (file_exists($preview_path_on_server) && is_file($preview_path_on_server)) {
                        unlink($preview_path_on_server);
                    }
                }
                
            // 6. Файлын болон жишээ зургийн хавтаснуудыг устгах
            // ==> ӨӨРЧЛӨЛТ: Хавтасны замыг ".." нэмж зааж өгөх
                $fileDir = '../uploads/files/' . $user_id . '/' . $file_id . '/';
                $previewDir = '../uploads/previews/' . $user_id . '/' . $file_id . '/';

                if (is_dir($fileDir)) {
                    rmdir($fileDir);
                }
                if (is_dir($previewDir)) {
                    rmdir($previewDir);
                }
                
            // 7. Мэдээллийн сангаас файлын бүх бичлэгийг устгах (cascade устгал)
                mysqli_query($conn, "DELETE FROM file_previews WHERE file_id = $file_id");
                mysqli_query($conn, "DELETE FROM file_tags WHERE file_id = $file_id");
                mysqli_query($conn, "DELETE FROM file_categories WHERE file_id = $file_id");
                mysqli_query($conn, "DELETE FROM transactions WHERE file_id = $file_id");
                mysqli_query($conn, "DELETE FROM ratings WHERE file_id = $file_id");
                
                $delete_sql = "DELETE FROM files WHERE id = $file_id";
                if (!mysqli_query($conn, $delete_sql)) {
                    throw new Exception("Database record deletion failed: " . mysqli_error($conn));
                }
                
            // Бүх зүйл амжилттай бол өөрчлөлтийг батлах
                mysqli_commit($conn);
                
                header("Location: files.php");
                exit();

            } catch (Exception $e) {
            // Ямар нэг алдаа гарвал бүх өөрчлөлтийг буцаах
                mysqli_rollback($conn);
                $error = "Error deleting file and its assets: " . $e->getMessage();
            }

        } else {
            $error = "File not found in the database.";
        }
    }
    
    // Toggle premium status
    if (isset($_POST['toggle_premium'])) {
        $file_id = intval($_POST['file_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        $sql = "UPDATE files SET is_premium = $new_status WHERE id = $file_id";
        
        if (mysqli_query($conn, $sql)) {
            header("Location: files.php");
            exit();
        } else {
            $error = "Error updating file: " . mysqli_error($conn);
        }
    }
}

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$filter_category = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Base query with joins to get user and category info
$sql = "SELECT f.*, u.username, u.full_name as user_full_name, u.avatar_url as user_avatar,
GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as categories
FROM files f
LEFT JOIN users u ON f.user_id = u.id
LEFT JOIN file_categories fc ON f.id = fc.file_id
LEFT JOIN subcategories sc ON fc.subcategory_id = sc.id
LEFT JOIN categories c ON sc.category_id = c.id
WHERE 1=1";

// Apply filters
if (!empty($search)) {
    $sql .= " AND (f.title LIKE '%$search%' OR f.description LIKE '%$search%')";
}

if ($filter_status === 'approved') {
    $sql .= " AND f.status = 'approved'";
} elseif ($filter_status === 'pending') {
    $sql .= " AND f.status = 'pending'";
} elseif ($filter_status === 'rejected') {
    $sql .= " AND f.status = 'rejected'";
}

if (!empty($filter_type)) {
    $sql .= " AND f.file_type = '$filter_type'";
}

if ($filter_category > 0) {
    $sql .= " AND sc.category_id = $filter_category";
}

$sql .= " GROUP BY f.id";

// Get total count for pagination
$count_result = mysqli_query($conn, $sql);
$total_files = mysqli_num_rows($count_result);

// Pagination
$per_page = 10;
$total_pages = ceil($total_files / $per_page);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$sql .= " LIMIT $offset, $per_page";
$result = mysqli_query($conn, $sql);

// Get all files for stats
$stats_sql = "SELECT 
COUNT(*) as total_files,
SUM(status = 'approved') as approved_files,
SUM(status = 'pending') as pending_files,
SUM(is_premium = 1) as premium_files
FROM files";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get categories for filter dropdown
$categories_sql = "SELECT * FROM categories";
$categories_result = mysqli_query($conn, $categories_sql);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filezone - Файлын удирдлага</title>
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
        <!-- Sidebar -->
        <?php include 'sidebar.php' ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Mobile Header -->
            <header class="admin-header text-white py-4 px-6 flex items-center justify-between md:hidden">
                <button class="text-white">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-bold">Filezone Админ</h1>
                <div>
                    <i class="fas fa-bell"></i>
                </div>
            </header>
            
            <!-- Admin Header -->
            <header class="bg-white shadow-sm py-4 px-6 hidden md:flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Файлын удирдлага</h2>
                    <p class="text-gray-600">Нийт <?php echo number_format($total_files); ?> файл, <?php echo $stats['pending_files']; ?> хүлээгдэж буй</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                        <span class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-4 h-4 text-xs flex items-center justify-center"><?php echo $stats['pending_files']; ?></span>
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
                
                <!-- File Management Tools -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">Файлын жагсаалт</h3>
                        <p class="text-gray-600">Бүх байршуулсан файлууд</p>
                    </div>
                    
                    <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-2 w-full md:w-auto">
                        <form method="GET" action="files.php" class="relative">
                            <input type="text" name="search" placeholder="Файл хайх..." 
                            value="<?php echo htmlspecialchars($search); ?>" 
                            class="search-box w-full md:w-64 border border-gray-300 rounded-md py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </form>
                        <a href="add_file.php" class="gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700 flex items-center justify-center">
                            <i class="fas fa-plus mr-2"></i> Шинэ файл
                        </a>
                    </div>
                </div>
                
                <!-- File Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="stat-card bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center">
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full mr-3">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Нийт файлууд</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['total_files']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center">
                            <div class="bg-green-100 text-green-600 p-3 rounded-full mr-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Баталгаажсан</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['approved_files']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center">
                            <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full mr-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Хүлээгдэж буй</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['pending_files']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center">
                            <div class="bg-purple-100 text-purple-600 p-3 rounded-full mr-3">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Premium файлууд</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo number_format($stats['premium_files']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- File Filters -->
                <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    <form method="GET" action="files.php" class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-4">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Төрөл</label>
                            <select name="type" class="w-full md:w-48 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">Бүх төрөл</option>
                                <option value="pdf" <?php echo $filter_type === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                                <option value="doc" <?php echo $filter_type === 'doc' ? 'selected' : ''; ?>>Word</option>
                                <option value="xls" <?php echo $filter_type === 'xls' ? 'selected' : ''; ?>>Excel</option>
                                <option value="ppt" <?php echo $filter_type === 'ppt' ? 'selected' : ''; ?>>PowerPoint</option>
                                <option value="jpg" <?php echo $filter_type === 'jpg' ? 'selected' : ''; ?>>Зураг</option>
                                <option value="zip" <?php echo $filter_type === 'zip' ? 'selected' : ''; ?>>ZIP/RAR</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                            <select name="status" class="w-full md:w-48 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="">Бүх статус</option>
                                <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Баталгаажсан</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Хүлээгдэж буй</option>
                                <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Татгалзсан</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ангилал</label>
                            <select name="category" class="w-full md:w-48 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="0">Бүх ангилал</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $filter_category == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="md:ml-auto">
                            <button type="submit" class="gradient-bg text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-purple-700">
                                <i class="fas fa-filter mr-1"></i> Шүүх
                            </button>
                            <a href="files.php" class="ml-2 bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300">
                                <i class="fas fa-sync-alt mr-1"></i> Цэвэрлэх
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Files Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 data-table">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файл</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Хэрэглэгч</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үнэ</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($file = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="file-type-icon file-<?php echo $file['file_type']; ?> mr-3">
                                                    <?php 
                                                    $icon = 'fa-file';
                                                    if ($file['file_type'] === 'pdf') $icon = 'fa-file-pdf';
                                                    elseif ($file['file_type'] === 'doc') $icon = 'fa-file-word';
                                                    elseif ($file['file_type'] === 'xls') $icon = 'fa-file-excel';
                                                    elseif ($file['file_type'] === 'ppt') $icon = 'fa-file-powerpoint';
                                                    elseif ($file['file_type'] === 'jpg' || $file['file_type'] === 'png') $icon = 'fa-file-image';
                                                    elseif ($file['file_type'] === 'zip' || $file['file_type'] === 'rar') $icon = 'fa-file-archive';
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($file['title']); ?></div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo strtoupper($file['file_type']); ?>, 
                                                        <?php echo round($file['file_size'] / 1000000, 1); ?>MB
                                                    </div>
                                                    <?php if (!empty($file['categories'])): ?>
                                                        <div class="text-xs text-gray-400 mt-1">
                                                            <?php echo htmlspecialchars($file['categories']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8">
                                                    <img class="h-8 w-8 rounded-full" src="css/images/default_avatar.png" alt="">
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($file['user_full_name'] ?: $file['username']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="font-bold"><?php echo number_format($file['price'], 2); ?>₮</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($file['status'] === 'approved'): ?>
                                                <span class="badge-approved px-2 py-1 text-xs rounded-full">Баталгаажсан</span>
                                            <?php elseif ($file['status'] === 'pending'): ?>
                                                <span class="badge-pending px-2 py-1 text-xs rounded-full">Хүлээгдэж буй</span>
                                            <?php else: ?>
                                                <span class="badge-rejected px-2 py-1 text-xs rounded-full">Татгалзсан</span>
                                            <?php endif; ?>

                                            <?php if ($file['is_premium']): ?>
                                                <span class="badge-premium px-2 py-1 text-xs rounded-full ml-1">Premium</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <?php if ($file['status'] === 'pending'): ?>
                                                <form method="POST" action="files.php" class="inline">
                                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                    <button type="submit" name="approve_file" class="text-green-600 hover:text-green-900 mr-3">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="files.php" class="inline">
                                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                    <button type="button" onclick="showRejectModal(<?php echo $file['id']; ?>)" class="text-red-600 hover:text-red-900 mr-3">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Edit Button (opens modal) -->
                                            <button onclick="openEditModal(
                                                <?php echo $file['id']; ?>, 
                                                '<?php echo addslashes($file['title']); ?>',
                                                '<?php echo addslashes($file['description']); ?>',
                                                <?php echo $file['price']; ?>,
                                                '<?php echo $file['status']; ?>',
                                                <?php echo $file['is_premium']; ?>
                                                )" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <form method="POST" action="files.php" class="inline" onsubmit="return confirm('Та энэ файлыг устгахдаа итгэлтэй байна уу?');">
                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                <input type="hidden" name="delete_file" value="1">
                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="files.php" class="inline ml-3">
                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $file['is_premium']; ?>">
                                                <button type="submit" name="toggle_premium" class="text-purple-600 hover:text-purple-900">
                                                    <i class="fas fa-crown"></i>
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
                        Showing <span class="font-medium"><?php echo ($page - 1) * $per_page + 1; ?></span> to <span class="font-medium"><?php echo min($page * $per_page, $total_files); ?></span> of <span class="font-medium"><?php echo number_format($total_files); ?></span> results
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="files.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&type=<?php echo urlencode($filter_type); ?>&category=<?php echo $filter_category; ?>" 
                             class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                             Previous
                         </a>
                     <?php endif; ?>

                     <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="files.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&type=<?php echo urlencode($filter_type); ?>&category=<?php echo $filter_category; ?>" 
                         class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium <?php echo $i == $page ? 'text-white pagination-active' : 'text-gray-700 hover:bg-gray-50'; ?>">
                         <?php echo $i; ?>
                     </a>
                 <?php endfor; ?>

                 <?php if ($page < $total_pages): ?>
                    <a href="files.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&type=<?php echo urlencode($filter_type); ?>&category=<?php echo $filter_category; ?>" 
                     class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                     Next
                 </a>
             <?php endif; ?>
         </div>
     </div>
 </main>
</div>
</div>
<!-- Edit File Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-3">
            <h3 class="text-xl font-bold">Файл засах</h3>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" action="files.php">
            <input type="hidden" name="file_id" id="edit_file_id">
            <input type="hidden" name="edit_file" value="1">
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Гарчиг</label>
                <input type="text" name="title" id="edit_title" class="w-full px-3 py-2 border rounded-md" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Тайлбар</label>
                <textarea name="description" id="edit_description" class="w-full px-3 py-2 border rounded-md" rows="3"></textarea>
            </div>

            <!-- Ангилал сонгох хэсэг -->
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Ангилал</label>
                <div class="space-y-2 border rounded-md p-3 bg-gray-50">
                    <!-- Үндсэн ангилал -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Үндсэн ангилал</label>
                        <select name="category_id" id="edit_category_id" class="w-full px-3 py-2 border rounded-md" onchange="onCategoryChange(this)">
                            <option value="">-- Үндсэн ангилал сонгох --</option>
                            <?php 
                            $cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
                            $cat_result = mysqli_query($conn, $cat_query);
                            while ($cat = mysqli_fetch_assoc($cat_result)) {
                                echo '<option value="'.$cat['id'].'">'.htmlspecialchars($cat['name']).'</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Дэд ангилал -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Дэд ангилал</label>
                        <select name="subcategory_id" id="edit_subcategory_id" class="w-full px-3 py-2 border rounded-md" onchange="onSubcategoryChange(this)">
                            <option value="">-- Дэд ангилал сонгох --</option>
                        </select>
                    </div>

                    <!-- Child ангилал -->
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Дэдийн ангилал</label>
                        <select name="child_category_id" id="edit_child_category_id" class="w-full px-3 py-2 border rounded-md">
                            <option value="">-- Дэдийн ангилал сонгох --</option>
                        </select>
                    </div>
                    
                    <!-- Одоогийн ангилал мэдээлэл -->
                    <div id="current_categories_info" class="text-sm text-gray-600 bg-white p-2 rounded border hidden">
                        <strong>Одоогийн ангилал:</strong>
                        <div id="current_categories_text"></div>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Үнэ (₮)</label>
                <input type="number" step="0.01" name="price" id="edit_price" class="w-full px-3 py-2 border rounded-md" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Статус</label>
                <select name="status" id="edit_status" class="w-full px-3 py-2 border rounded-md">
                    <option value="pending">Хүлээгдэж буй</option>
                    <option value="approved">Баталгаажсан</option>
                    <option value="rejected">Татгалзсан</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_premium" id="edit_premium" class="form-checkbox">
                    <span class="ml-2">Premium файл</span>
                </label>
            </div>
            
            <div class="flex justify-end pt-2">
                <button type="button" onclick="closeEditModal()" class="mr-3 px-4 py-2 bg-gray-200 rounded-md">Цуцлах</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">Хадгалах</button>
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

            // File search functionality
        const searchBox = document.querySelector('.search-box');
        if (searchBox) {
            searchBox.addEventListener('input', function() {
                    // Implement search functionality here
                console.log('Searching for files:', this.value);
            });
        }
    });
    function openEditModal(id, title, description, price, status, isPremium) {
        document.getElementById('edit_file_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_premium').checked = isPremium ? true : false;
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
    function showRejectModal(fileId) {
        const reason = prompt("Татгалзсан шалтгаан оруулна уу:");
        if (reason !== null) {
        // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'files.php';

            const fileIdInput = document.createElement('input');
            fileIdInput.type = 'hidden';
            fileIdInput.name = 'file_id';
            fileIdInput.value = fileId;

            const reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            name = 'reject_reason';
            reasonInput.value = reason;

            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'reject_file';
            submitInput.value = '1';

            form.appendChild(fileIdInput);
            form.appendChild(reasonInput);
            form.appendChild(submitInput);

            document.body.appendChild(form);
            form.submit();
        }
    }

// Ангилал мэдээллийг ачаалах функц
function loadFileCategories(fileId) {
    fetch(`include/get_file_categories.php?file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            console.log('File categories data:', data); // Debugging
            if (data.success) {
                // Одоогийн ангилалыг харуулах
                document.getElementById('current_categories_info').classList.remove('hidden');
                document.getElementById('current_categories_text').innerHTML = 
                    `<div>Үндсэн: ${data.category_name || '-'}</div>
                     <div>Дэд: ${data.subcategory_name || '-'}</div>
                     <div>Дэдийн: ${data.child_category_name || '-'}</div>`;
                
                // Dropdown-уудыг тохируулах
                if (data.category_id) {
                    document.getElementById('edit_category_id').value = data.category_id;
                    // Дэд ангилал ачаалах
                    loadSubcategories(data.category_id, data.subcategory_id, data.child_category_id);
                }
            } else {
                console.log('No category data found');
            }
        })
        .catch(error => console.error('Error:', error));
}

// Дэд ангилал ачаалах
function loadSubcategories(categoryId, selectedSubcategoryId = null, selectedChildId = null) {
    const subcategorySelect = document.getElementById('edit_subcategory_id');
    const childSelect = document.getElementById('edit_child_category_id');
    
    // Дэд ангилал хоослох
    subcategorySelect.innerHTML = '<option value="">-- Дэд ангилал сонгох --</option>';
    childSelect.innerHTML = '<option value="">-- Дэдийн ангилал сонгох --</option>';
    
    if (!categoryId) return;
    
    fetch(`include/get_subcategories.php?category_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            console.log('Subcategories data:', data); // Debugging
            data.forEach(subcat => {
                const option = document.createElement('option');
                option.value = subcat.id;
                option.textContent = subcat.name;
                if (selectedSubcategoryId && subcat.id == selectedSubcategoryId) {
                    option.selected = true;
                    // Дэдийн ангилал ачаалах
                    loadChildCategories(selectedSubcategoryId, selectedChildId);
                }
                subcategorySelect.appendChild(option);
            });
            
            // Хэрэв selectedSubcategoryId байгаа ч dropdown-д ороогүй бол дахин ачаалах
            if (selectedSubcategoryId && !subcategorySelect.value) {
                setTimeout(() => {
                    subcategorySelect.value = selectedSubcategoryId;
                    if (selectedSubcategoryId) {
                        loadChildCategories(selectedSubcategoryId, selectedChildId);
                    }
                }, 100);
            }
        })
        .catch(error => console.error('Error loading subcategories:', error));
}

// Дэдийн ангилал ачаалах
function loadChildCategories(subcategoryId, selectedChildId = null) {
    const childSelect = document.getElementById('edit_child_category_id');
    childSelect.innerHTML = '<option value="">-- Дэдийн ангилал сонгох --</option>';
    
    if (!subcategoryId) return;
    
    fetch(`include/get_child_categories.php?subcategory_id=${subcategoryId}`)
        .then(response => response.json())
        .then(data => {
            console.log('Child categories data:', data); // Debugging
            data.forEach(child => {
                const option = document.createElement('option');
                option.value = child.id;
                option.textContent = child.name;
                if (selectedChildId && child.id == selectedChildId) {
                    option.selected = true;
                }
                childSelect.appendChild(option);
            });
            
            // Хэрэв selectedChildId байгаа ч dropdown-д ороогүй бол дахин тохируулах
            if (selectedChildId && !childSelect.value) {
                setTimeout(() => {
                    childSelect.value = selectedChildId;
                }, 100);
            }
        })
        .catch(error => console.error('Error loading child categories:', error));
}

function openEditModal(id, title, description, price, status, isPremium) {
    document.getElementById('edit_file_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_premium').checked = isPremium ? true : false;
    
    // Dropdown-уудыг эхлээд reset хийх
    document.getElementById('edit_category_id').value = '';
    document.getElementById('edit_subcategory_id').innerHTML = '<option value="">-- Дэд ангилал сонгох --</option>';
    document.getElementById('edit_child_category_id').innerHTML = '<option value="">-- Дэдийн ангилал сонгох --</option>';
    document.getElementById('current_categories_info').classList.add('hidden');
    
    // Ангилал мэдээллийг ачаалах
    loadFileCategories(id);
    
    document.getElementById('editModal').classList.remove('hidden');
}
// Үндсэн ангилал солигдох үед
function onCategoryChange(select) {
    const categoryId = select.value;
    const subcategorySelect = document.getElementById('edit_subcategory_id');
    const childSelect = document.getElementById('edit_child_category_id');
    
    subcategorySelect.innerHTML = '<option value="">-- Дэд ангилал сонгох --</option>';
    childSelect.innerHTML = '<option value="">-- Дэдийн ангилал сонгох --</option>';
    
    if (categoryId) {
        loadSubcategories(categoryId);
    }
}

// Дэд ангилал солигдох үед
function onSubcategoryChange(select) {
    const subcategoryId = select.value;
    const childSelect = document.getElementById('edit_child_category_id');
    
    childSelect.innerHTML = '<option value="">-- Дэдийн ангилал сонгох --</option>';
    
    if (subcategoryId) {
        loadChildCategories(subcategoryId);
    }
}
</script>
</body>
</html>
<?php
mysqli_close($conn);
?>