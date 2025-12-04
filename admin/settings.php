<?php
// Database connection
$conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update system settings
    if (isset($_POST['update_settings'])) {
        $settings = $_POST['settings'];
        
        foreach ($settings as $key => $value) {
            $escaped_value = mysqli_real_escape_string($conn, $value);
            $sql = "UPDATE system_settings SET setting_value = '$escaped_value' WHERE setting_key = '$key'";
            mysqli_query($conn, $sql);
        }
        
        // Handle file uploads
        if (!empty($_FILES['site_logo']['name'])) {
            $logo = uploadFile('site_logo');
            if ($logo) {
                $sql = "UPDATE system_settings SET setting_value = '$logo' WHERE setting_key = 'site_logo'";
                mysqli_query($conn, $sql);
            }
        }
        
        if (!empty($_FILES['site_favicon']['name'])) {
            $favicon = uploadFile('site_favicon');
            if ($favicon) {
                $sql = "UPDATE system_settings SET setting_value = '$favicon' WHERE setting_key = 'site_favicon'";
                mysqli_query($conn, $sql);
            }
        }
        
        // Set success message
        $_SESSION['success_message'] = "Тохиргоо амжилттай хадгалагдлаа";
        header("Location: settings.php");
        exit();
    }
    
    // Handle maintenance mode
    if (isset($_POST['update_maintenance'])) {
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $message = mysqli_real_escape_string($conn, $_POST['message']);
        $allow_api = isset($_POST['allow_api_access']) ? 1 : 0;
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : NULL;
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : NULL;
        
        // Check if maintenance record exists
        $check_sql = "SELECT id FROM system_maintenance LIMIT 1";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $sql = "UPDATE system_maintenance SET 
                    is_active = $is_active,
                    message = '$message',
                    allow_api_access = $allow_api,
                    start_time = " . ($start_time ? "'$start_time'" : "NULL") . ",
                    end_time = " . ($end_time ? "'$end_time'" : "NULL") . ",
                    updated_at = NOW()";
        } else {
            $sql = "INSERT INTO system_maintenance (is_active, message, allow_api_access, start_time, end_time, created_at)
                    VALUES ($is_active, '$message', $allow_api, " . 
                    ($start_time ? "'$start_time'" : "NULL") . ", " . 
                    ($end_time ? "'$end_time'" : "NULL") . ", NOW())";
        }
        
        mysqli_query($conn, $sql);
        
        $_SESSION['success_message'] = "Засварын горимын тохиргоо амжилттай хадгалагдлаа";
        header("Location: settings.php?tab=maintenance");
        exit();
    }
}

// File upload helper function
function uploadFile($field_name) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = basename($_FILES[$field_name]['name']);
    $target_file = $target_dir . uniqid() . '_' . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES[$field_name]['tmp_name']);
    if ($check === false) {
        return false;
    }
    
    // Check file size (max 2MB)
    if ($_FILES[$field_name]['size'] > 2000000) {
        return false;
    }
    
    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif', 'ico'])) {
        return false;
    }
    
    if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $target_file)) {
        return $target_file;
    }
    
    return false;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Get all system settings grouped by category
$settings = [];
$sql = "SELECT * FROM system_settings ORDER BY setting_group, setting_key";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_group']][$row['setting_key']] = $row;
}

// Get maintenance settings
$maintenance = [
    'is_active' => false,
    'message' => '',
    'allow_api_access' => false,
    'start_time' => '',
    'end_time' => ''
];

$sql = "SELECT * FROM system_maintenance LIMIT 1";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    $maintenance = mysqli_fetch_assoc($result);
}

// Get admin IP restrictions
$ip_restrictions = [];
$sql = "SELECT * FROM admin_ip_restrictions";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $ip_restrictions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>НАРХАН - Тохиргоо</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <style>
        .admin-sidebar {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        }
        .admin-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        .nav-active {
            background-color: rgba(124, 58, 237, 0.2);
            border-left: 4px solid #7c3aed;
        }
        .setting-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .setting-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left-color: #7c3aed;
        }
        .tab-button {
            transition: all 0.3s ease;
        }
        .tab-button.active {
            background-color: #4f46e5;
            color: white;
        }
        .toggle-checkbox:checked {
            right: 0;
            border-color: #4f46e5;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #4f46e5;
        }
    </style>
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
                <h1 class="text-xl font-bold">НАРХАН Админ</h1>
                <div>
                    <i class="fas fa-bell"></i>
                </div>
            </header>
            
            <!-- Admin Header -->
            <header class="bg-white shadow-sm py-4 px-6 hidden md:flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Тохиргоо</h2>
                    <p class="text-gray-600">Платформын тохиргоог удирдах</p>
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
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Settings Tabs -->
                <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    <div class="flex flex-wrap gap-2">
                        <a href="?tab=general" class="tab-button <?php echo $current_tab === 'general' ? 'active' : ''; ?> px-4 py-2 rounded-md text-sm">
                            <i class="fas fa-cog mr-2"></i> Ерөнхий
                        </a>
                        <a href="?tab=payment" class="tab-button <?php echo $current_tab === 'payment' ? 'active' : ''; ?> px-4 py-2 rounded-md text-sm">
                            <i class="fas fa-credit-card mr-2"></i> Төлбөр
                        </a>
                        <a href="?tab=files" class="tab-button <?php echo $current_tab === 'files' ? 'active' : ''; ?> px-4 py-2 rounded-md text-sm">
                            <i class="fas fa-file-upload mr-2"></i> Файл
                        </a>
                        <a href="?tab=email" class="tab-button <?php echo $current_tab === 'email' ? 'active' : ''; ?> px-4 py-2 rounded-md text-sm">
                            <i class="fas fa-envelope mr-2"></i> Имэйл
                        </a>
                        <a href="?tab=security" class="tab-button <?php echo $current_tab === 'security' ? 'active' : ''; ?> px-4 py-2 rounded-md text-sm">
                            <i class="fas fa-shield-alt mr-2"></i> Аюулгүй байдал
                        </a>
                        <a href="?tab=maintenance" class="tab-button <?php echo $current_tab === 'maintenance' ? 'active' : ''; ?> px-4 py-2 rounded-md text-sm">
                            <i class="fas fa-tools mr-2"></i> Засвар
                        </a>
                    </div>
                </div>
                
                <!-- General Settings -->
                <?php if ($current_tab === 'general'): ?>
                <form method="POST" action="settings.php" enctype="multipart/form-data">
                    <input type="hidden" name="update_settings" value="1">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Платформын ерөнхий тохиргоо</h3>
                        
                        <div class="space-y-6">
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Вэбсайтын нэр</h4>
                                        <p class="text-sm text-gray-600">Таны платформын нэр бүх хуудсанд харагдана</p>
                                    </div>
                                    <input type="text" name="settings[site_name]" class="border border-gray-300 rounded-md px-3 py-2 w-64" 
                                           value="<?php echo htmlspecialchars($settings['general']['site_name']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Вэбсайтын URL</h4>
                                        <p class="text-sm text-gray-600">Таны платформын үндсэн хаяг</p>
                                    </div>
                                    <input type="text" name="settings[site_url]" class="border border-gray-300 rounded-md px-3 py-2 w-64" 
                                           value="<?php echo htmlspecialchars($settings['general']['site_url']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Лого</h4>
                                        <p class="text-sm text-gray-600">Таны платформын лого</p>
                                    </div>
                                    <div class="flex items-center">
                                        <?php if (!empty($settings['general']['site_logo']['setting_value'])): ?>
                                            <img src="<?php echo htmlspecialchars($settings['general']['site_logo']['setting_value']); ?>" alt="Current Logo" class="w-12 h-12 rounded-md mr-4">
                                        <?php endif; ?>
                                        <input type="file" name="site_logo" class="border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Фавикон</h4>
                                        <p class="text-sm text-gray-600">Браузерын tab дээр харагдах жижиг зураг</p>
                                    </div>
                                    <div class="flex items-center">
                                        <?php if (!empty($settings['general']['site_favicon']['setting_value'])): ?>
                                            <img src="<?php echo htmlspecialchars($settings['general']['site_favicon']['setting_value']); ?>" alt="Current Favicon" class="w-8 h-8 rounded-md mr-4">
                                        <?php endif; ?>
                                        <input type="file" name="site_favicon" class="border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Хэрэглэгчийн бүртгэл</h4>
                                        <p class="text-sm text-gray-600">Шинэ хэрэглэгч бүртгүүлэхийг зөвшөөрөх эсэх</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="settings[user_registration]" id="user_registration" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $settings['general']['user_registration']['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <label for="user_registration" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Хэрэглэгчийн баталгаажуулалт</h4>
                                        <p class="text-sm text-gray-600">Шинэ хэрэглэгчид имэйлээр баталгаажуулах</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="settings[email_verification]" id="email_verification" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $settings['general']['email_verification']['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <label for="email_verification" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Хадгалах хугацаа</h4>
                                        <p class="text-sm text-gray-600">Файлыг сервер дээр хадгалах хугацаа (хоног)</p>
                                    </div>
                                    <input type="number" name="settings[file_storage_days]" class="border border-gray-300 rounded-md px-3 py-2 w-24" 
                                           value="<?php echo htmlspecialchars($settings['general']['file_storage_days']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-6">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium">
                                    <i class="fas fa-save mr-2"></i> Хадгалах
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- Payment Settings -->
                <?php if ($current_tab === 'payment'): ?>
                <form method="POST" action="settings.php">
                    <input type="hidden" name="update_settings" value="1">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Төлбөрийн тохиргоо</h3>
                        
                        <div class="space-y-6">
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Валют</h4>
                                        <p class="text-sm text-gray-600">Платформын үндсэн валют</p>
                                    </div>
                                    <select name="settings[default_currency]" class="border border-gray-300 rounded-md px-3 py-2 w-64">
                                        <option value="MNT" <?php echo $settings['payment']['default_currency']['setting_value'] === 'MNT' ? 'selected' : ''; ?>>Монгол төгрөг (₮)</option>
                                        <option value="USD" <?php echo $settings['payment']['default_currency']['setting_value'] === 'USD' ? 'selected' : ''; ?>>Америк доллар ($)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Комиссын хувь</h4>
                                        <p class="text-sm text-gray-600">Файл борлуулалтаас авдаг комиссын хувь</p>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="number" name="settings[commission_rate]" class="border border-gray-300 rounded-md px-3 py-2 w-24" 
                                               value="<?php echo htmlspecialchars($settings['payment']['commission_rate']['setting_value']); ?>">
                                        <span class="ml-2">%</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Хамгийн бага гаргах дүн</h4>
                                        <p class="text-sm text-gray-600">Хэрэглэгч данснаас мөнгө гаргах хамгийн бага дүн</p>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="number" name="settings[min_payout_amount]" class="border border-gray-300 rounded-md px-3 py-2 w-24" 
                                               value="<?php echo htmlspecialchars($settings['payment']['min_payout_amount']['setting_value']); ?>">
                                        <span class="ml-2">₮</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-6">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium">
                                    <i class="fas fa-save mr-2"></i> Хадгалах
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- File Settings -->
                <?php if ($current_tab === 'files'): ?>
                <form method="POST" action="settings.php">
                    <input type="hidden" name="update_settings" value="1">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Файлын тохиргоо</h3>
                        
                        <div class="space-y-6">
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Хамгийн их файлын хэмжээ</h4>
                                        <p class="text-sm text-gray-600">Нэг файлын хамгийн их хэмжээ (MB)</p>
                                    </div>
                                    <input type="number" name="settings[max_file_size]" class="border border-gray-300 rounded-md px-3 py-2 w-24" 
                                           value="<?php echo htmlspecialchars($settings['files']['max_file_size']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Зөвшөөрөгдсөн файлын төрлүүд</h4>
                                        <p class="text-sm text-gray-600">Платформ дээр байршуулах боломжтой файлын төрлүүд</p>
                                    </div>
                                    <input type="text" name="settings[allowed_file_types]" class="border border-gray-300 rounded-md px-3 py-2 w-64" 
                                           value="<?php echo htmlspecialchars($settings['files']['allowed_file_types']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Файлын урьдчилсан үзүүлэлт</h4>
                                        <p class="text-sm text-gray-600">Файлын урьдчилсан үзүүлэлтийг идэвхжүүлэх</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="settings[file_preview]" id="file_preview" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $settings['files']['file_preview']['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <label for="file_preview" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Файлын автомат шалгалт</h4>
                                        <p class="text-sm text-gray-600">Файлыг вирус шалгах програмтай шалгах</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="settings[virus_scan]" id="virus_scan" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $settings['files']['virus_scan']['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <label for="virus_scan" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Файлын автомат баталгаажуулалт</h4>
                                        <p class="text-sm text-gray-600">Файлыг админ баталгаажуулалгүйгээр шууд нийтлэх</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="settings[auto_approve_files]" id="auto_approve" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $settings['files']['auto_approve_files']['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <label for="auto_approve" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-6">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium">
                                    <i class="fas fa-save mr-2"></i> Хадгалах
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- Email Settings -->
                <?php if ($current_tab === 'email'): ?>
                <form method="POST" action="settings.php">
                    <input type="hidden" name="update_settings" value="1">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Имэйлийн тохиргоо</h3>
                        
                        <div class="space-y-6">
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">SMTP сервер</h4>
                                        <p class="text-sm text-gray-600">Имэйл илгээх серверийн хаяг</p>
                                    </div>
                                    <input type="text" name="settings[smtp_host]" class="border border-gray-300 rounded-md px-3 py-2 w-64" 
                                           value="<?php echo htmlspecialchars($settings['email']['smtp_host']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">SMTP порт</h4>
                                        <p class="text-sm text-gray-600">Имэйл серверийн порт</p>
                                    </div>
                                    <input type="number" name="settings[smtp_port]" class="border border-gray-300 rounded-md px-3 py-2 w-24" 
                                           value="<?php echo htmlspecialchars($settings['email']['smtp_port']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">SMTP хэрэглэгчийн нэр</h4>
                                        <p class="text-sm text-gray-600">Имэйл серверт нэвтрэх нэр</p>
                                    </div>
                                    <input type="text" name="settings[smtp_username]" class="border border-gray-300 rounded-md px-3 py-2 w-64" 
                                           value="<?php echo htmlspecialchars($settings['email']['smtp_username']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">SMTP нууц үг</h4>
                                        <p class="text-sm text-gray-600">Имэйл серверт нэвтрэх нууц үг</p>
                                    </div>
                                    <input type="password" name="settings[smtp_password]" class="border border-gray-300 rounded-md px-3 py-2 w-64" 
                                           value="<?php echo htmlspecialchars($settings['email']['smtp_password']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">SMTP шифрлэлт</h4>
                                        <p class="text-sm text-gray-600">Имэйл серверийн холболтын шифрлэлт</p>
                                    </div>
                                    <select name="settings[smtp_encryption]" class="border border-gray-300 rounded-md px-3 py-2 w-64">
                                        <option value="tls" <?php echo $settings['email']['smtp_encryption']['setting_value'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo $settings['email']['smtp_encryption']['setting_value'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="" <?php echo empty($settings['email']['smtp_encryption']['setting_value']) ? 'selected' : ''; ?>>Шифрлэлтгүй</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-6">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium">
                                    <i class="fas fa-save mr-2"></i> Хадгалах
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- Security Settings -->
                <?php if ($current_tab === 'security'): ?>
                <form method="POST" action="settings.php">
                    <input type="hidden" name="update_settings" value="1">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Аюулгүй байдлын тохиргоо</h3>
                        
                        <div class="space-y-6">
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Нууц үгийн хүч</h4>
                                        <p class="text-sm text-gray-600">Хэрэглэгчийн нууц үгийн доод хэмжээ</p>
                                    </div>
                                    <select name="settings[password_strength]" class="border border-gray-300 rounded-md px-3 py-2 w-64">
                                        <option value="1" <?php echo $settings['security']['password_strength']['setting_value'] == '1' ? 'selected' : ''; ?>>Сул (6 тэмдэгт)</option>
                                        <option value="2" <?php echo $settings['security']['password_strength']['setting_value'] == '2' ? 'selected' : ''; ?>>Дунд зэрэг (8 тэмдэгт)</option>
                                        <option value="3" <?php echo $settings['security']['password_strength']['setting_value'] == '3' ? 'selected' : ''; ?>>                                               Хүчтэй (10 тэмдэгт, тусгай тэмдэгт)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Нэвтрэх оролдлогын хязгаар</h4>
                                        <p class="text-sm text-gray-600">Хэрэглэгчийн нэвтрэх оролдлогын хамгийн их тоо</p>
                                    </div>
                                    <input type="number" name="settings[login_attempts]" class="border border-gray-300 rounded-md px-3 py-2 w-24" 
                                           value="<?php echo htmlspecialchars($settings['security']['login_attempts']['setting_value']); ?>">
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">2-шатлалт баталгаажуулалт</h4>
                                        <p class="text-sm text-gray-600">Админ хэрэглэгчдэд 2-шатлалт баталгаажуулалт шаардах</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="settings[enable_2fa]" id="enable_2fa" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $settings['security']['enable_2fa']['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <label for="enable_2fa" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">IP хаягаар хязгаарлах</h4>
                                        <p class="text-sm text-gray-600">Зөвхөн тодорхой IP хаягаас админ хэсэгт нэвтрэхийг зөвшөөрөх</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="settings[ip_restriction]" id="ip_restriction" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $settings['security']['ip_restriction']['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <label for="ip_restriction" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- IP Restrictions List -->
                            <div class="bg-white rounded-lg shadow-sm border p-4">
                                <h4 class="font-medium text-gray-800 mb-3">Зөвшөөрөгдсөн IP хаягууд</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Хаяг</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Тайлбар</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($ip_restrictions as $ip): ?>
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($ip['ip_address']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($ip['description']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                                    <form method="POST" action="delete_ip.php" class="inline">
                                                        <input type="hidden" name="id" value="<?php echo $ip['id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Add IP Form -->
                                <form method="POST" action="add_ip.php" class="mt-4">
                                    <div class="flex items-end space-x-2">
                                        <div class="flex-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Шинэ IP хаяг</label>
                                            <input type="text" name="ip_address" class="border border-gray-300 rounded-md px-3 py-2 w-full" placeholder="123.45.67.89" required>
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Тайлбар</label>
                                            <input type="text" name="description" class="border border-gray-300 rounded-md px-3 py-2 w-full" placeholder="Үндсэн админ">
                                        </div>
                                        <div>
                                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                                <i class="fas fa-plus"></i> Нэмэх
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="flex justify-end mt-6">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium">
                                    <i class="fas fa-save mr-2"></i> Хадгалах
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- Maintenance Settings -->
                <?php if ($current_tab === 'maintenance'): ?>
                <form method="POST" action="settings.php">
                    <input type="hidden" name="update_maintenance" value="1">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Засварын горим</h3>
                        
                        <div class="space-y-6">
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">Засварын горим</h4>
                                        <p class="text-sm text-gray-600">Засварын горимыг идэвхжүүлэх/идэвхгүй болгох</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="is_active" id="maintenance_mode" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $maintenance['is_active'] ? 'checked' : ''; ?>>
                                        <label for="maintenance_mode" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex flex-col">
                                    <label for="message" class="font-medium text-gray-800 mb-2">Засварын мессеж</label>
                                    <textarea name="message" id="message" rows="4" class="border border-gray-300 rounded-md px-3 py-2 w-full"><?php echo htmlspecialchars($maintenance['message']); ?></textarea>
                                    <p class="text-sm text-gray-600 mt-1">Засварын горимд хэрэглэгчдэд харагдах мессеж</p>
                                </div>
                            </div>
                            
                            <div class="setting-card bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-800">API хандалтыг зөвшөөрөх</h4>
                                        <p class="text-sm text-gray-600">Засварын горимд API хандалтыг зөвшөөрөх</p>
                                    </div>
                                    <div class="relative inline-block w-10 mr-2 align-middle select-none">
                                        <input type="checkbox" name="allow_api_access" id="allow_api" 
                                               class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" 
                                               <?php echo $maintenance['allow_api_access'] ? 'checked' : ''; ?>>
                                        <label for="allow_api" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="setting-card bg-gray-50 rounded-lg p-4">
                                    <label for="start_time" class="font-medium text-gray-800 mb-2">Эхлэх хугацаа</label>
                                    <input type="datetime-local" name="start_time" id="start_time" 
                                           class="border border-gray-300 rounded-md px-3 py-2 w-full" 
                                           value="<?php echo $maintenance['start_time'] ? date('Y-m-d\TH:i', strtotime($maintenance['start_time'])) : ''; ?>">
                                    <p class="text-sm text-gray-600 mt-1">Засварын горим автоматаар эхлэх хугацаа</p>
                                </div>
                                
                                <div class="setting-card bg-gray-50 rounded-lg p-4">
                                    <label for="end_time" class="font-medium text-gray-800 mb-2">Дуусах хугацаа</label>
                                    <input type="datetime-local" name="end_time" id="end_time" 
                                           class="border border-gray-300 rounded-md px-3 py-2 w-full" 
                                           value="<?php echo $maintenance['end_time'] ? date('Y-m-d\TH:i', strtotime($maintenance['end_time'])) : ''; ?>">
                                    <p class="text-sm text-gray-600 mt-1">Засварын горим автоматаар дуусах хугацаа</p>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-6">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md font-medium">
                                    <i class="fas fa-save mr-2"></i> Хадгалах
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </main>
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
            
            // Toggle switches
            const toggleSwitches = document.querySelectorAll('.toggle-checkbox');
            toggleSwitches.forEach(switchEl => {
                switchEl.addEventListener('change', function() {
                    const label = this.nextElementSibling;
                    if (this.checked) {
                        label.classList.add('bg-blue-600');
                        label.classList.remove('bg-gray-300');
                    } else {
                        label.classList.add('bg-gray-300');
                        label.classList.remove('bg-blue-600');
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>