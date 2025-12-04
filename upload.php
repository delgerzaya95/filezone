<?php
set_time_limit(1800); // 30 минут (300MB файлыг удаан сүлжээгээр ч хуулахад хангалттай)
ob_start();

// Include essential files first
require_once 'includes/functions.php';
//Scaleway
require_once 'includes/s3_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect if not
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; // Store current page for redirect after login
    header("Location: login.php");
    exit();
}

// ===================================================================
//  AJAX HANDLER: Handle subcategory requests and exit immediately.
//  This block is moved to the top to ensure a clean JSON response.
// ===================================================================
// ===================================================================
//  AJAX HANDLER: Handle subcategory AND child category requests
// ===================================================================
if (isset($_GET['ajax'])) {
    try {
        // Clear any previous output buffer to prevent corrupting the JSON
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $conn = db_connect();
        if (!$conn) {
            throw new Exception("Database connection failed.");
        }
        mysqli_set_charset($conn, "utf8mb4");

        // Handle subcategories request
        if (isset($_GET['category_id'])) {
            $category_id = intval($_GET['category_id']);
            $sql = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC";
            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                throw new Exception("SQL prepare failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt, "i", $category_id);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("SQL execute failed: " . mysqli_stmt_error($stmt));
            }

            $result = mysqli_stmt_get_result($stmt);
            $subcategories = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $subcategories[] = $row;
            }

            // Set the correct header for a JSON response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $subcategories
            ]);
        }
        // Handle child categories request
        elseif (isset($_GET['subcategory_id'])) {
            $subcategory_id = intval($_GET['subcategory_id']);
            $sql = "SELECT id, name FROM child_category WHERE subcategory_id = ? ORDER BY name ASC";
            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                throw new Exception("SQL prepare failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt, "i", $subcategory_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("SQL execute failed: " . mysqli_stmt_error($stmt));
            }

            $result = mysqli_stmt_get_result($stmt);
            $child_categories = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $child_categories[] = $row;
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $child_categories
            ]);
        }

        mysqli_close($conn);
        exit; // IMPORTANT: Stop the script from rendering the rest of the page

    } catch (Exception $e) {
        // Ensure a clean JSON error response
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Send an appropriate error status code
        http_response_code(500); 
        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        exit; // IMPORTANT: Stop the script
    }
}

// ===================================================================
//  NORMAL PAGE LOAD: If it's not an AJAX request, proceed.
// ===================================================================

/*// Check if user is logged in, redirect if not
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; // Store current page
    header("Location: login.php");
    exit();
}*/

// Set page title
$pageTitle = "Filezone - Файл оруулах";

// Include header
include 'includes/header.php';

// Include navigation
include 'includes/navigation.php';

// Database connection
$conn = db_connect();
mysqli_set_charset($conn, "utf8mb4");

// Initialize variables
$errors = [];
$success = '';
$categories = [];
$subcategories = [];

// Get categories for dropdown
$sql = "SELECT * FROM categories ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
}

// CSRF token үүсгэх (session эхлүүлсний дараа)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Form бэлтгэх үед
$csrf_token = $_SESSION['csrf_token'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // (Your existing POST handling logic remains unchanged)
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token";
    } else {
        // Sanitize input data
        $title = trim($_POST['title']);
        $allowed_tags = '<p><br><b><strong><i><em><u><ul><ol><li><span><a>'; 
        $description = strip_tags($_POST['description'], $allowed_tags);
        $description = trim($description);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $subcategory_id = intval($_POST['subcategory_id']);
        $child_category_id = isset($_POST['child_category_id']) ? intval($_POST['child_category_id']) : 0;
        $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
        $access_level = NULL;
        $license = null;
        $user_id = $_SESSION['user_id'];
        
        // Validate required fields
        if (empty($title)) $errors[] = "Гарчиг хоосон байна";
        if (empty($description)) $errors[] = "Тайлбар хоосон байна";
        if ($price < 0) $errors[] = "Үнэ буруу байна";
        if (empty($category_id)) $errors[] = "Ангилал сонгоогүй байна";
        if (empty($subcategory_id)) $errors[] = "Дэд ангилал сонгоогүй байна";
        
       // File validation (ӨӨРЧЛӨГДСӨН)
        $resumableFile = isset($_POST['resumable_filename']) ? trim($_POST['resumable_filename']) : '';
        $mainFile = null;

        if (!empty($resumableFile)) {
            // 1. Resumable-аар орж ирсэн файлыг шалгах
            $tempPath = 'uploads/temp/' . $resumableFile;
            if (!file_exists($tempPath)) {
                $errors[] = "Файл олдсонгүй (Time out), дахин оруулна уу.";
            }
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            // 2. Файл огт сонгоогүй бол
            $errors[] = "Үндсэн файл оруулаагүй байна";
        } else {
            // 3. Жижиг файл (Resumable биш) сонгосон бол
            $mainFile = $_FILES['file'];
            // VirusTotal-аар шалгах
            if (check_virus_cloud($mainFile['tmp_name'])) {
                 $errors[] = "Аюултай файл! VirusTotal-ийн санд бүртгэгдсэн вирус байна.";
            }
        }
        
        // Validate preview images if any
        $validPreviews = [];
        if (!empty($_FILES['previews']['name'][0])) {
            $previewFiles = $_FILES['previews'];
            
            for ($i = 0; $i < count($previewFiles['name']); $i++) {
                if ($previewFiles['error'][$i] === UPLOAD_ERR_OK) {
                    $imageExt = strtolower(pathinfo($previewFiles['name'][$i], PATHINFO_EXTENSION));
                    $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($imageExt, $allowedImageTypes)) {
                        if ($previewFiles['size'][$i] > 5 * 1024 * 1024) { // 5MB max per image
                            $errors[] = "Зургийн хэмжээ 5MB-ээс их байна: " . $previewFiles['name'][$i];
                        } else {
                            $validPreviews[] = [
                                'name' => $previewFiles['name'][$i],
                                'tmp_name' => $previewFiles['tmp_name'][$i],
                                'type' => $previewFiles['type'][$i],
                                'size' => $previewFiles['size'][$i],
                                'error' => $previewFiles['error'][$i]
                            ];
                        }
                    }
                }
            }
            
            if (count($validPreviews) > 5) {
                $errors[] = "Хамгийн ихдээ 5 зураг оруулах боломжтой";
            }
        }
        
        // Proceed if no errors
        if (empty($errors)) {
            // Create base directories if they don't exist
            if (!is_dir('uploads/files/')) mkdir('uploads/files/', 0755, true);
            if (!is_dir('uploads/previews/')) mkdir('uploads/previews/', 0755, true);
            
            // Generate unique filenames LOGIC FIX
            // 1. Жинхэнэ нэрийг олж авах
            if (!empty($resumableFile) && isset($_POST['original_filename']) && !empty($_POST['original_filename'])) {
                // Resumable upload хийсэн бол JS-ээс ирсэн жинхэнэ нэрийг авна
                $originalFullName = $_POST['original_filename'];
                $fileSize = filesize('uploads/temp/' . $resumableFile);
            } elseif (isset($mainFile)) {
                // Энгийн upload хийсэн бол
                $originalFullName = $mainFile['name'];
                $fileSize = $mainFile['size'];
            } else {
                $originalFullName = 'unknown_file';
                $fileSize = 0;
            }

            // 2. Pathinfo ашиглан нэр, өргөтгөлийг найдвартай салгах
            $pathInfo = pathinfo($originalFullName);
            $fileNameOnly = $pathInfo['filename']; // "Миний файл"
            $extension = strtolower(isset($pathInfo['extension']) ? $pathInfo['extension'] : ''); // "pdf"

            // Begin transaction
            mysqli_begin_transaction($conn);

            try {
                // ---------------------------------------------------------
                // 3. DATABASE TYPE MAPPING (Зассан)
                // ---------------------------------------------------------
                $fileTypeForDB = 'other';
                $extMap = [
                    'pdf'=>'pdf', 'doc'=>'doc', 'docx'=>'doc', 'xls'=>'xls', 'xlsx'=>'xls',
                    'ppt'=>'ppt', 'pptx'=>'ppt', 'txt'=>'txt', 'jpg'=>'jpg', 'jpeg'=>'jpg',
                    'png'=>'png', 'gif'=>'gif', 'svg'=>'svg', 'psd'=>'psd', 'ai'=>'ai',
                    'mp3'=>'mp3', 'mp4'=>'mp4', 'mov'=>'mov', 'zip'=>'zip', 'rar'=>'rar', 'exe'=>'exe'
                ];
                
                if (!empty($extension)) {
                    if (array_key_exists($extension, $extMap)) {
                        $fileTypeForDB = $extMap[$extension];
                    } else {
                        // Жагсаалтад байхгүй ч өргөтгөл байвал түүгээр нь хадгалах (жнь: mkv, indd гэх мэт)
                        // Эсвэл DB чинь зөвхөн тодорхой утга авдаг бол 'other' хэвээр үлдээ.
                        // Одоогоор 'other' дээр унахгүй байх магадлал өндөр болсон.
                        $fileTypeForDB = 'other'; 
                    }
                }

                // ---------------------------------------------------------
                // 4. DATABASE INSERT
                // ---------------------------------------------------------
                $sql = "INSERT INTO files (user_id, category_id, title, description, file_type, file_size, price, access_level, license) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                
                mysqli_stmt_bind_param($stmt, "iisssidss", $user_id, $category_id, $title, $description, $fileTypeForDB, $fileSize, $price, $access_level, $license);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Database error: " . mysqli_error($conn));
                }
                
                $file_id = mysqli_insert_id($conn);
                
                // ---------------------------------------------------------
                // 5. ФАЙЛ ХАДГАЛАХ ЗАМ БЭЛДЭХ & НЭР ЦЭВЭРЛЭХ (Зассан)
                // ---------------------------------------------------------
                $finalDir = 'uploads/files/' . $user_id . '/' . $file_id . '/';
                $previewDir = 'uploads/previews/' . $user_id . '/' . $file_id . '/';

                if (!is_dir($finalDir)) mkdir($finalDir, 0755, true);
                if (!is_dir($previewDir)) mkdir($previewDir, 0755, true);
                
                // Нэрийг цэвэрлэхдээ Монгол үсэг, зай, ЦЭГ (.)-ийг зөвшөөрнө. 
                // Өмнөх код цэгийг устгаад байсан тул "File v1.2" гэдэг нэр "Filev12" болоод байсан.
                // Regex тайлбар: \p{L} (үсэг), \p{N} (тоо), \s (зай), \-_ (зураас), . (цэг)
                $safeFileName = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $fileNameOnly);
                
                // Дараалсан цэгүүдийг нэг цэг болгох (security: .. хасах)
                $safeFileName = preg_replace('/\.+/', '.', $safeFileName);
                $safeFileName = trim($safeFileName);
                
                // Хэрэв нэр хоосон болчихвол ID-гаар орлуулна
                if (empty($safeFileName)) {
                    $safeFileName = 'file_' . uniqid();
                }

                // Эцсийн нэрийг угсрах
                $finalNameWithExt = $safeFileName . '.' . $extension;
                $finalFilePath = $finalDir . $finalNameWithExt;
                
                // Хэрэв ижил нэртэй файл байвал тоо залгах: file.pdf -> file_1.pdf
                $counter = 1;
                while (file_exists($finalFilePath)) {
                    $finalNameWithExt = $safeFileName . '_' . $counter . '.' . $extension;
                    $finalFilePath = $finalDir . $finalNameWithExt;
                    $counter++;
                }

                // ---------------------------------------------------------
                // 6. ФАЙЛЫГ ЗӨӨХ (MOVE)
                // ---------------------------------------------------------
                // ---------------------------------------------------------
                // 6. SCALEWAY S3 РУУ ХУУЛАХ (Scaleway руу шилжүүлсэн хэсэг)
                // ---------------------------------------------------------
                
                // S3 Client үүсгэх
                $s3 = get_s3_client();
                $bucketName = 'filezone-bucket'; // Таны Scaleway Bucket нэр
                
                // S3 дээр хадгалах зам (Жишээ нь: files/1/15/minii_file.pdf)
                // user_id болон file_id-аар хавтас үүсгэж цэгцтэй байлгана
                $s3Key = 'files/' . $user_id . '/' . $file_id . '/' . $finalNameWithExt;

                // Эх файл хаана байгааг тодорхойлох
                $sourceFile = '';
                if (!empty($resumableFile)) {
                    $sourceFile = 'uploads/temp/' . $resumableFile;
                } elseif (isset($mainFile)) {
                    $sourceFile = $mainFile['tmp_name'];
                }

                // ---------------------------------------------------------
                // 6. SCALEWAY S3 РУУ ХУУЛАХ (Debug хувилбар)
                // ---------------------------------------------------------
                
                // Лог бичих функц
                function write_log($message) {
                    $logFile = __DIR__ . '/s3_upload_debug.log';
                    $time = date('Y-m-d H:i:s');
                    file_put_contents($logFile, "[$time] $message" . PHP_EOL, FILE_APPEND);
                }

                write_log("Эхэлж байна. User ID: $user_id, File ID: $file_id");

                // S3 Client үүсгэх
                try {
                    $s3 = get_s3_client();
                    write_log("S3 Client амжилттай үүслээ.");
                } catch (Exception $e) {
                    write_log("S3 Client үүсгэхэд алдаа: " . $e->getMessage());
                    throw $e;
                }

                $bucketName = 'filezone-bucket'; 
                $s3Key = 'files/' . $user_id . '/' . $file_id . '/' . $finalNameWithExt;

                // Эх файл хаана байгааг тодорхойлох
                $sourceFile = '';
                if (!empty($resumableFile)) {
                    $sourceFile = 'uploads/temp/' . $resumableFile;
                } elseif (isset($mainFile)) {
                    $sourceFile = $mainFile['tmp_name'];
                }

                write_log("Эх файл: $sourceFile");

                if (file_exists($sourceFile)) {
                    $fileSize = filesize($sourceFile);
                    write_log("Файл олдлоо. Хэмжээ: " . round($fileSize / 1024 / 1024, 2) . " MB");

                    try {
                        write_log("S3 руу хуулж эхэллээ (Multipart)...");
                        
                        // S3 руу файл хуулах (Multipart Upload сайжруулсан тохиргоотой)
                        $uploader = new \Aws\S3\MultipartUploader($s3, $sourceFile, [
                            'bucket' => $bucketName,
                            'key'    => $s3Key,
                            'acl'    => 'private',
                            'concurrency' => 5, // 5 хэсгийг зэрэг хуулна (Хурдыг нэмнэ)
                            'part_size'   => 5 * 1024 * 1024, // Нэг хэсгийн хэмжээ 5MB
                        ]);

                        // Хуулах үйлдлийг гүйцэтгэх
                        $result = $uploader->upload();

                        write_log("S3 руу хуулах үйлдэл дууслаа. URL: " . $result['ObjectURL']);

                        // DB-д хадгалах замыг S3-ийн Key-ээр солих
                        $finalFilePath = $s3Key;

                        // Түр файлыг устгах
                        if (!empty($resumableFile)) {
                            @unlink($sourceFile);
                            write_log("Түр файлыг устгалаа.");
                        }

                    } catch (Aws\Exception\AwsException $e) {
                        write_log("S3 AWS Алдаа: " . $e->getMessage());
                        throw new Exception("S3 Upload Error: " . $e->getMessage());
                    } catch (Exception $e) {
                        write_log("Ерөнхий алдаа: " . $e->getMessage());
                        throw new Exception("Upload Error: " . $e->getMessage());
                    }
                } else {
                    write_log("Эх файл олдсонгүй! ($sourceFile)");
                    throw new Exception("Эх файл олдсонгүй.");
                }
                
                // ---------------------------------------------------------
                // 7. БУСАД DB UPDATE (Хэвээрээ)
                // ---------------------------------------------------------
                
                // Файлын замыг шинэчлэх
                $updateSql = "UPDATE files SET file_url = ? WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                mysqli_stmt_bind_param($updateStmt, "si", $finalFilePath, $file_id);
                mysqli_stmt_execute($updateStmt);
                
                // Ангилал холбох
                $subcatSql = "INSERT INTO file_categories (file_id, subcategory_id, child_category_id) VALUES (?, ?, ?)";
                $stmtSub = mysqli_prepare($conn, $subcatSql);
                mysqli_stmt_bind_param($stmtSub, "iii", $file_id, $subcategory_id, $child_category_id);
                mysqli_stmt_execute($stmtSub);
                                
                // Шошго (Tags)
                if (!empty($tags)) {
                    $tag_array = array_map('trim', explode(',', $tags));
                    foreach ($tag_array as $tag) {
                        $tag = trim($tag);
                        if (!empty($tag)) {
                            // Check tag
                            $tag_sql = "SELECT id FROM tags WHERE name = ?";
                            $stmtTag = mysqli_prepare($conn, $tag_sql);
                            mysqli_stmt_bind_param($stmtTag, "s", $tag);
                            mysqli_stmt_execute($stmtTag);
                            $result = mysqli_stmt_get_result($stmtTag);
                            
                            if (mysqli_num_rows($result) > 0) {
                                $row = mysqli_fetch_assoc($result);
                                $tag_id = $row['id'];
                            } else {
                                // Insert tag
                                $insert_tag = "INSERT INTO tags (name) VALUES (?)";
                                $stmtInsert = mysqli_prepare($conn, $insert_tag);
                                mysqli_stmt_bind_param($stmtInsert, "s", $tag);
                                mysqli_stmt_execute($stmtInsert);
                                $tag_id = mysqli_insert_id($conn);
                            }
                            
                            $link_tag = "INSERT INTO file_tags (file_id, tag_id) VALUES (?, ?)";
                            $stmtLink = mysqli_prepare($conn, $link_tag);
                            mysqli_stmt_bind_param($stmtLink, "ii", $file_id, $tag_id);
                            mysqli_stmt_execute($stmtLink);
                        }
                    }
                }
                
                // Зураг (Previews)
                if (isset($validPreviews) && !empty($validPreviews)) {
                    foreach ($validPreviews as $index => $preview) {
                        $pInfo = pathinfo($preview['name']);
                        $pExt = isset($pInfo['extension']) ? strtolower($pInfo['extension']) : 'jpg';
                        $previewName = uniqid() . '_preview.' . $pExt;
                        $previewPath = $previewDir . $previewName;
                        
                        if (move_uploaded_file($preview['tmp_name'], $previewPath)) {
                            $previewSql = "INSERT INTO file_previews (file_id, preview_url, order_index) VALUES (?, ?, ?)";
                            $stmtPrev = mysqli_prepare($conn, $previewSql);
                            $order = $index + 1;
                            mysqli_stmt_bind_param($stmtPrev, "isi", $file_id, $previewPath, $order);
                            mysqli_stmt_execute($stmtPrev);
                        }
                    }
                }
                
                mysqli_commit($conn);
                
                if (function_exists('notify_admin_of_upload')) {
                    notify_admin_of_upload($conn, $user_id, $title, $price, $description);
                }
                
                die('success'); 
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                
                // Алдаа гарвал үүссэн хавтаснуудыг цэвэрлэх
                if (isset($finalDir) && is_dir($finalDir)) {
                    $files = glob($finalDir . '*');
                    foreach ($files as $file) if (is_file($file)) unlink($file);
                    rmdir($finalDir);
                }
                
                http_response_code(500);
                die($e->getMessage());
            }
        }
    }
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>

<main class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Файл оруулах</h1>
            <p class="text-gray-600">Өөрийн файлаа НАРХАН платформ дээр байршуулж, бусад хэрэглэгчидтэй хуваалцаарай</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success) && !empty($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form id="upload-form" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6 mb-6">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" id="resumable_filename" name="resumable_filename" value="">    
        <input type="hidden" id="original_filename" name="original_filename" value="">

        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Үндсэн файл оруулах</h3>
            <div id="main-drop-area" class="upload-container border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer">
                <i class="fas fa-cloud-upload-alt text-4xl text-purple-500 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Файлаа энд чирж буулгах эсвэл</h3>
                <p class="text-gray-500 mb-4">Ямар ч төрлийн файл оруулах боломжтой</p>
                <button id="browse-btn" type="button" class="gradient-bg text-white px-6 py-2 rounded-md font-medium hover:bg-purple-700 transition">
                    <i class="fas fa-folder-open mr-2"></i> Файл сонгох
                </button>
                <input type="file" id="file-input" name="file" class="hidden">
            </div>

            <div id="main-file-preview" class="mt-4 hidden">
                <div class="bg-gray-50 rounded-lg p-4 flex items-center">
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-lg mr-4">
                        <i class="fas fa-file text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h4 id="main-file-name" class="font-medium text-gray-800"></h4>
                        <p id="main-file-size" class="text-xs text-gray-500"></p>
                    </div>
                    <button type="button" class="text-red-500 hover:text-red-700 delete-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Жишээ зураг оруулах (заавал биш)</h3>
            <div id="image-drop-area" class="upload-container border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer">
                <i class="fas fa-images text-4xl text-purple-500 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Зургаа энд чирж буулгах эсвэл</h3>
                <p class="text-gray-500 mb-4">Дэмжих формат: JPG, PNG, GIF (Хамгийн ихдээ 5 зураг)</p>
                <button id="image-browse-btn" type="button" class="bg-white text-purple-600 border border-purple-600 px-6 py-2 rounded-md font-medium hover:bg-purple-50 transition">
                    <i class="fas fa-folder-open mr-2"></i> Зураг сонгох
                </button>
                <input type="file" id="image-input" name="previews[]" class="hidden" accept="image/*" multiple>
            </div>

            <div id="image-preview-container" class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4 hidden">
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Файлын мэдээлэл</h3>

            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Гарчиг</label>
                        <input type="text" id="title" name="title" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" 
                        placeholder="Файлын гарчиг" required value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">
                    </div>

                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Үнэ (MNT)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500">₮</span>
                            </div>
                            <input type="text" id="price" name="price" class="price-input w-full border border-gray-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" 
                            placeholder="0" value="<?= isset($_POST['price']) ? number_format(floatval($_POST['price']), 0, '.', ',') : '' ?>">
                            <input type="hidden" id="price_actual" name="price_actual" value="<?= isset($_POST['price']) ? floatval($_POST['price']) : '0' ?>">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Тайлбар</label>
                    <textarea id="description" name="description" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" 
                    placeholder="Файлын тайлбар..." required><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                </div>

                <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ангилал</label>
                <div class="mb-4">
                    <div class="flex flex-wrap gap-2 mb-3">
                        <?php foreach ($categories as $category): ?>
                            <button type="button" 
                                class="category-badge px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-800 hover:bg-purple-100 hover:text-purple-800 transition <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id'] ? 'bg-purple-100 text-purple-800' : ''); ?>" 
                                data-category="<?= $category['id'] ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <input type="hidden" id="category_id" name="category_id" value="<?= isset($_POST['category_id']) ? htmlspecialchars($_POST['category_id']) : '' ?>">

                    <!-- Subcategory Container -->
                    <div id="subcategory-container" class="bg-white border border-gray-200 rounded-md p-4 mb-4 <?= isset($_POST['category_id']) ? '' : 'hidden' ?>">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Дэд ангилал сонгох:</h4>
                        <div id="subcategory-list" class="space-y-2">
                            <?php if (isset($_POST['category_id'])): ?>
                                <?php 
                                $cat_id = intval($_POST['category_id']);
                                $subcat_sql = "SELECT * FROM subcategories WHERE category_id = ? ORDER BY name ASC";
                                $subcat_stmt = mysqli_prepare($conn, $subcat_sql);
                                mysqli_stmt_bind_param($subcat_stmt, "i", $cat_id);
                                mysqli_stmt_execute($subcat_stmt);
                                $subcat_result = mysqli_stmt_get_result($subcat_stmt);
                                ?>
                                <?php while ($subcat = mysqli_fetch_assoc($subcat_result)): ?>
                                    <div class="subcategory-item px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded cursor-pointer <?= (isset($_POST['subcategory_id']) && $_POST['subcategory_id'] == $subcat['id'] ? 'bg-purple-100 text-purple-800' : ''); ?>" 
                                       data-subcategory-id="<?= $subcat['id'] ?>">
                                       <?= htmlspecialchars($subcat['name']) ?>
                                   </div>
                               <?php endwhile; ?>
                           <?php endif; ?>
                       </div>
                   </div>

                   <input type="hidden" id="subcategory_id" name="subcategory_id" value="<?= isset($_POST['subcategory_id']) ? htmlspecialchars($_POST['subcategory_id']) : '' ?>">

                   <!-- Child Category Container -->
                   <div id="child-category-container" class="bg-white border border-gray-200 rounded-md p-4 <?= (isset($_POST['subcategory_id']) && !empty($_POST['subcategory_id'])) ? '' : 'hidden' ?>">
                       <h4 class="text-sm font-medium text-gray-700 mb-3">Жижиг ангилал сонгох (заавал биш):</h4>
                       <div id="child-category-list" class="space-y-2">
                           <?php if (isset($_POST['subcategory_id']) && !empty($_POST['subcategory_id'])): ?>
                               <?php 
                               $subcat_id = intval($_POST['subcategory_id']);
                               $childcat_sql = "SELECT * FROM child_category WHERE subcategory_id = ? ORDER BY name ASC";
                               $childcat_stmt = mysqli_prepare($conn, $childcat_sql);
                               mysqli_stmt_bind_param($childcat_stmt, "i", $subcat_id);
                               mysqli_stmt_execute($childcat_stmt);
                               $childcat_result = mysqli_stmt_get_result($childcat_stmt);
                               ?>
                               <?php while ($childcat = mysqli_fetch_assoc($childcat_result)): ?>
                                   <div class="child-category-item px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded cursor-pointer <?= (isset($_POST['child_category_id']) && $_POST['child_category_id'] == $childcat['id'] ? 'bg-purple-100 text-purple-800' : ''); ?>" 
                                      data-child-category-id="<?= $childcat['id'] ?>">
                                      <?= htmlspecialchars($childcat['name']) ?>
                                  </div>
                              <?php endwhile; ?>
                          <?php endif; ?>
                      </div>
                      <p class="text-xs text-gray-500 mt-2">Жижиг ангилал нь файлыг илүү нарийн ангилахад тусална</p>
                  </div>

                  <input type="hidden" id="child_category_id" name="child_category_id" value="<?= isset($_POST['child_category_id']) ? htmlspecialchars($_POST['child_category_id']) : '' ?>">
               </div>
                </div>

           <div class="mb-6">
                <label for="tags" class="block text-sm font-medium text-gray-700 mb-1">
                    Шошго 
                    <span class="text-purple-600 cursor-help" title="Шошгын ач холбогдол">ℹ️</span>
                </label>
                <input type="text" id="tags" name="tags" class="tag-input w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" 
                placeholder="Шошго нэмэх (таслалаар тусгаарлах)" value="<?= isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : '' ?>">
                
                <!-- Шошгын тайлбар хэсэг -->
                <div class="mt-2 p-3 bg-blue-50 rounded-lg">
                    <h4 class="text-sm font-medium text-blue-800 mb-1">Шошгын ач холбогдол:</h4>
                    <ul class="text-xs text-blue-700 list-disc pl-4 space-y-1">
                        <li><strong>Хайлтын үр дүнг сайжруулах</strong> - Шошго ашиглан хэрэглэгчид таны файлыг хялбархан олно</li>
                        <li><strong>Илүү нарийн ангилах</strong> - Ангилалаас гадна нэмэлт төрөл, чиглэлээр ангилагдана</li>
                        <li><strong>Холбоотой файлуудыг санал болгох</strong> - Ижил шошготой бусад файлуудыг хэрэглэгчдэд санал болгоно</li>
                        <li><strong>Түлхүүр үгс оруулах</strong> - Файлын гол түлхүүр үгсээ шошгонд оруулна</li>
                    </ul>
                    <p class="text-xs text-blue-600 mt-2">
                        <strong>Жишээ:</strong> диплом, судалгаа, математик, N5, япон хэл, бизнес төлөвлөгөө
                    </p>
                </div>
                
                <div class="flex flex-wrap gap-2 mt-2" id="tags-display"></div>
            </div>
    </div>
</div>

    <div id="upload-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full text-center transform scale-100 transition-transform duration-300">
            
            <div id="modal-icon-container" class="mb-4 flex justify-center">
                <div id="modal-spinner" class="w-16 h-16 border-4 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>
                <div id="modal-success-icon" class="hidden w-16 h-16 bg-green-100 text-green-500 rounded-full flex items-center justify-center text-3xl">
                    <i class="fas fa-check"></i>
                </div>
                <div id="modal-error-icon" class="hidden w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center text-3xl">
                    <i class="fas fa-times"></i>
                </div>
            </div>

            <h3 id="modal-title" class="text-xl font-bold text-gray-800 mb-1">Файл хуулж байна...</h3>
            <p id="modal-subtitle" class="text-sm text-gray-500 mb-6">Түр хүлээнэ үү</p>

            <div id="modal-progress-area" class="w-full bg-gray-200 rounded-full h-2.5 mb-2 overflow-hidden">
                <div id="modal-progress-bar" class="bg-purple-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <div id="modal-percent" class="text-right text-xs font-semibold text-purple-600">0%</div>

            <button id="modal-close-btn" type="button" class="hidden mt-4 w-full bg-gray-200 text-gray-800 py-2 rounded-lg hover:bg-gray-300 transition">
                Хаах
            </button>
        </div>
    </div>

<div class="flex justify-end">
    <button type="submit" id="submit-btn" class="gradient-bg text-white px-8 py-3 rounded-md font-medium hover:bg-purple-700 transition flex items-center">
        <i class="fas fa-cloud-upload-alt mr-2"></i> Файл байршуулах
    </button>
</div>
</form>

<!-- Copyright Анхааруулга хэсэг -->
    <div class="warning-box bg-yellow-50 border border-yellow-200 rounded-lg shadow-md p-6 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mt-1"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">💡 ЗӨВЛӨМЖ & ⚠️ АНХААРУУЛГА</h3>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Зөвлөмж -->
                    <div>
                        <h4 class="font-semibold text-green-700 mb-2">✅ Файл байршуулах зөвлөмж</h4>
                        <ul class="list-disc pl-5 space-y-2 text-gray-700 text-sm">
                            <li>Файлын хэмжээ <strong>300MB-аас хэтрэхгүй байх</strong>(Хэрвээ та үүнээс дээш хэмжээгээр оруулах бол админтай холбоо барина уу)</li>
                            <li>Зөвхөн өөрийн эзэмшлийн файлуудыг байршуулах</li>
                            <li>Файлын гарчиг, тайлбарыг тодорхой оруулах</li>
                            <li>Зохих ангилал, шошготой байх</li>
                            <li>Бүтээгдэхүүний үнийг шударгаар тогтоох</li>
                            <li>Жишээ зургуудыг оруулах (заавал биш)</li>
                        </ul>
                    </div>

                    <!-- Copyright Анхааруулга -->
                    <div>
                        <h4 class="font-semibold text-red-700 mb-2">🚨 ЗОХИОЛЫН ЭРХИЙН АНХААРУУЛГА</h4>
                        <ul class="list-disc pl-5 space-y-2 text-gray-700 text-sm">
                            <li><strong>Монгол Улсын Зохиогчийн эрхийн тухай хуулиар</strong> хамгаалагдсан агуулгыг зөвшөөрөлгүйгээр байршуулахыг хориглоно</li>
                            <li>Бусдын зохиогчийн эрхийг зөрчих агуулга байршуулбал <strong>торгууль, эрүүгийн хариуцлага</strong> хүлээх боломжтой</li>
                            <li>Зөвхөн өөрийн бүтээсэн эсвэл байршуулах эрхтэй агуулгыг оруулах</li>
                            <li>Эрхгүй агуулга олдвол файл татагдаж, бүртгэл түдгэлзүүлэгдэнэ</li>
                        </ul>
                        
                        <div class="mt-3 p-3 bg-red-50 rounded border border-red-200">
                            <p class="text-xs text-red-700 font-medium">
                                📞 Зохиогчийн эрхийн асуудал гарвал: 
                                <strong>Монгол Улсын Зохиогчийн эрхийн байгууллага - 7011-1234</strong>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Copyright Law Reference -->
                <div class="mt-4 p-3 bg-gray-100 rounded border border-gray-300">
                    <p class="text-xs text-gray-600">
                        <strong>Монгол Улсын Зохиогчийн эрхийн тухай хууль:</strong> 
                        Голын 8-р зүйл - Зохиогчийн эрхийн хамгаалалт; Голын 42-р зүйл - Зохиогчийн эрхийг зөрчсөн хариуцлага
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<?php
// Include footer
include 'includes/footer.php';

?>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // TinyMCE Editor-ийг description дээр ачаалах
    tinymce.init({
        selector: '#description', // Таны textarea-ийн ID
        height: 300,
        menubar: false,
        plugins: 'emoticons lists link autolink charmap',
        toolbar: 'bold italic underline | bullist numlist | emoticons | link',
        branding: false,
        statusbar: false,
        setup: function(editor) {
            // Агуулга өөрчлөгдөх бүрт textarea-г шинэчилж байх (Validation-д хэрэгтэй)
            editor.on('change', function() {
                editor.save();
            });
        }
    });
        // ======================
// SIMPLE PRICE FORMATTING (MNT)
// ======================
    const priceInput = document.getElementById('price');
const priceDisplay = document.createElement('input'); // Create a display input

// Configure display input (what users see)
priceDisplay.id = 'price_display';
priceDisplay.className = priceInput.className;
priceDisplay.type = 'text';
priceDisplay.placeholder = '0';

// Hide the original input (but keep it for form submission)
priceInput.type = 'hidden';

// Insert display input next to original input
priceInput.parentNode.insertBefore(priceDisplay, priceInput.nextSibling);

// Format price as user types
priceDisplay.addEventListener('input', function() {
    // Remove all non-digits
    let cleanValue = this.value.replace(/[^\d]/g, '');
    
    // Default to 0 if empty
    if (cleanValue === '') cleanValue = '0';
    
    // Update hidden input with raw number
    priceInput.value = cleanValue;
    
    // Format display with commas
    this.value = Number(cleanValue).toLocaleString('mn-MN');
});

// Initialize on page load (if a price exists)
if (priceInput.value) {
    priceDisplay.value = Number(priceInput.value).toLocaleString('mn-MN');
}
    // Main file elements
const dropArea = document.getElementById('main-drop-area');
const fileInput = document.getElementById('file-input');
const browseBtn = document.getElementById('browse-btn');
const mainFilePreview = document.getElementById('main-file-preview');
const mainFileName = document.getElementById('main-file-name');
const mainFileSize = document.getElementById('main-file-size');

    // Image elements
const imageDropArea = document.getElementById('image-drop-area');
const imageInput = document.getElementById('image-input');
const imageBrowseBtn = document.getElementById('image-browse-btn');
const imagePreviewContainer = document.getElementById('image-preview-container');

// ======================
    // 1. RESUMABLE & MODAL UI HANDLING (NEW)
    // ======================
    
    // UI Elements
    const uploadForm = document.getElementById('upload-form');
    const submitBtn = document.getElementById('submit-btn');
    const hiddenFilenameInput = document.getElementById('resumable_filename');
    
    // Modal Elements
    const uploadModal = document.getElementById('upload-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalSubtitle = document.getElementById('modal-subtitle');
    const modalSpinner = document.getElementById('modal-spinner');
    const modalSuccessIcon = document.getElementById('modal-success-icon');
    const modalErrorIcon = document.getElementById('modal-error-icon');
    const modalProgressArea = document.getElementById('modal-progress-area');
    const modalProgressBar = document.getElementById('modal-progress-bar');
    const modalPercent = document.getElementById('modal-percent');
    const modalCloseBtn = document.getElementById('modal-close-btn');

    // Resumable Setup
    var r = new Resumable({
        target: 'upload_chunk.php',
        chunkSize: 20 * 1024 * 1024, // 20MB
        simultaneousUploads: 4,
        testChunks: false,
        throttleProgressCallbacks: 1,
        maxFiles: 1
    });

    if (!r.support) {
        alert("Таны хөтөч том файл хуулах үйлдлийг дэмжихгүй байна.");
    } else {
        r.assignBrowse(browseBtn);
        r.assignDrop(dropArea);

        // 1. Файл сонгогдох үед
        r.on('fileAdded', function(file) {
            // 300MB хэмжээ (300 * 1024 * 1024)
            const maxFileSize = 300 * 1024 * 1024; 

            if (file.size > maxFileSize) {
                alert('Уучлаарай, файлын хэмжээ 300MB-аас хэтрэхгүй байх ёстой.');
                r.removeFile(file);
                return;
            }

            // Хэмжээ зөв бол цааш үргэлжилнэ
            dropArea.classList.add('hidden');
            mainFilePreview.classList.remove('hidden');
            mainFileName.textContent = file.fileName;
            mainFileSize.textContent = formatFileSize(file.size);
            
            // Товчийг идэвхжүүлэх
            if(submitBtn) submitBtn.disabled = false;

        });

        // 2. Хуулах явц (Progress)
        r.on('fileProgress', function(file) {
            const percent = Math.floor(file.progress() * 100);
            updateModalProgress(percent);
        });

        // 3. Файл хуулагдаж дуусах (Chunk Upload Complete)
        r.on('fileSuccess', function(file, message) {
            if(hiddenFilenameInput) hiddenFilenameInput.value = message;
            const originalNameInput = document.getElementById('original_filename');
            if(originalNameInput) originalNameInput.value = file.fileName;
            // Файл хуулагдлаа, одоо формыг сервер рүү илгээнэ
            modalTitle.textContent = "Мэдээллийг бүртгэж байна...";
            modalSubtitle.textContent = "Түр хүлээнэ үү";
            sendFormData(); 
        });

        // 4. Алдаа гарах
        r.on('fileError', function(file, message) {
            showModalError("Файл хуулахад алдаа гарлаа: " + message);
            r.cancel();
        });
    }

    // Preview дээрх устгах товч
    const deleteBtn = mainFilePreview.querySelector('.delete-btn');
    if(deleteBtn) {
        deleteBtn.onclick = function() {
            mainFilePreview.classList.add('hidden');
            dropArea.classList.remove('hidden');
            if(hiddenFilenameInput) hiddenFilenameInput.value = '';
            r.cancel();
        };
    }

    // ======================
    // FORM SUBMIT LOGIC
    // ======================
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // TinyMCE-ээс textarea руу утгыг хадгалах (ЭНИЙГ НЭМНЭ)
            if (window.tinymce && tinymce.get('description')) {
                tinymce.triggerSave();
            }

            // Шалгалт
            if (r.files.length === 0 && (!hiddenFilenameInput.value)) {
                alert('Та үндсэн файлаа сонгоно уу!');
                return;
            }

            // Modal нээх
            openModal();

            // Хэрэв файл хуулагдаагүй бол эхлээд хуулна
            if (!hiddenFilenameInput.value && r.files.length > 0) {
                modalTitle.textContent = "Файл хуулж байна...";
                r.upload();
            } else {
                // Хуулагдсан бол шууд илгээнэ
                modalTitle.textContent = "Мэдээллийг илгээж байна...";
                updateModalProgress(100);
                sendFormData();
            }
        });
    }

    // AJAX: Өгөгдөл илгээх
    function sendFormData() {
        const formData = new FormData(uploadForm);
        formData.delete('file'); // Том файлыг дахин явуулахгүй

        const xhr = new XMLHttpRequest();

        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                // АМЖИЛТТАЙ БОЛСОН!
                showModalSuccess();
                
                // 2 секундын дараа шилжүүлэх
                setTimeout(function() {
                    window.location.href = "profile.php?msg=file_uploaded_success";
                }, 2000);
            } else {
                showModalError('Серверийн алдаа: ' + xhr.status);
            }
        });

        xhr.addEventListener('error', function() {
            showModalError('Сүлжээний алдаа гарлаа.');
        });

        xhr.open('POST', 'upload.php', true);
        xhr.send(formData);
    }

    // ======================
    // HELPER FUNCTIONS (MODAL CONTROLS)
    // ======================
    
    function openModal() {
        uploadModal.classList.remove('hidden');
        // Reset state
        modalSpinner.classList.remove('hidden');
        modalSuccessIcon.classList.add('hidden');
        modalErrorIcon.classList.add('hidden');
        modalProgressArea.classList.remove('hidden');
        modalCloseBtn.classList.add('hidden');
        modalPercent.textContent = '0%';
        modalProgressBar.style.width = '0%';
    }

    function updateModalProgress(percent) {
        modalProgressBar.style.width = percent + '%';
        modalPercent.textContent = percent + '%';
    }

    function showModalSuccess() {
        modalSpinner.classList.add('hidden');
        modalSuccessIcon.classList.remove('hidden'); // Ногоон зөв тэмдэг
        modalTitle.textContent = "Амжилттай!";
        modalTitle.classList.add('text-green-600');
        modalSubtitle.textContent = "Файл бүрэн байршлаа. Шилжиж байна...";
        modalProgressArea.classList.add('hidden');
        modalPercent.classList.add('hidden');
    }

    function showModalError(msg) {
        modalSpinner.classList.add('hidden');
        modalErrorIcon.classList.remove('hidden'); // Улаан буруу тэмдэг
        modalTitle.textContent = "Алдаа гарлаа";
        modalTitle.classList.add('text-red-600');
        modalSubtitle.textContent = msg;
        modalProgressArea.classList.add('hidden');
        modalPercent.classList.add('hidden');
        
        // Хаах товчийг гаргаж ирэх
        modalCloseBtn.classList.remove('hidden');
        modalCloseBtn.onclick = function() {
            uploadModal.classList.add('hidden');
            // Reset title color
            modalTitle.classList.remove('text-red-600');
        };
    }


    // ======================
    // IMAGE HANDLING (ШИНЭЧЛЭГДСЭН - SORTABLE)
    // ======================

    // SortableJS идэвхжүүлэх (Чирж зөөх үйлдэл)
    new Sortable(imagePreviewContainer, {
        animation: 150,
        ghostClass: 'sortable-ghost', // Чирж байхад бүдэгрэх загвар
        onEnd: function (evt) {
            // Зөөж дууссаны дараа input доторх файлын дарааллыг шинэчлэх
            updateInputFilesOrder();
        }
    });

    // 1. Зураг сонгох товч дарах
    if(imageBrowseBtn) {
        imageBrowseBtn.addEventListener('click', function() {
            imageInput.click();
        });
    }

    // 2. Input-ээс зураг сонгогдох үед
    imageInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            // Шинэ зургуудыг нэмэх (хуучныг устгахгүйгээр)
            handleImageFiles(Array.from(this.files));
        }
    });

    // 3. Зураг чирж оруулж ирэх (Drop)
    if(imageDropArea) {
        imageDropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-purple-500', 'bg-purple-50');
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                // Шинэ зургуудыг нэмэх
                handleImageFiles(Array.from(e.dataTransfer.files));
            }
        });
    }

    // 4. Зургийг дэлгэцэнд харуулах функц
    function handleImageFiles(newFiles) {
        // Одоо байгаа зургийн тоог шалгах
        const currentCount = imagePreviewContainer.children.length;
        const availableSlots = 5 - currentCount;

        if (availableSlots <= 0) {
            alert('Та дээд тал нь 5 зураг оруулах боломжтой.');
            return;
        }

        // Зөвхөн зураг мөн эсэх болон тоо хэтрээгүйг шүүж авах
        const validFiles = newFiles
            .filter(file => file.type.startsWith('image/'))
            .slice(0, availableSlots);

        if (validFiles.length === 0 && newFiles.length > 0) {
             return; // Нэмэх боломжгүй бол буцах
        }

        validFiles.forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewDiv = document.createElement('div');
                previewDiv.className = 'relative group cursor-grab'; // cursor-grab нэмсэн
                
                // Файлыг DOM элемент дээр хадгалах (дараа нь эрэмбэлэхэд хэрэгтэй)
                previewDiv.file = file; 

                previewDiv.innerHTML = `
                    <div class="border border-gray-200 rounded-lg overflow-hidden h-32 relative">
                        <img src="${e.target.result}" alt="Preview" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                        <button type="button" class="absolute top-2 right-2 bg-white rounded-full p-1 text-red-500 hover:text-red-700 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity duration-200 delete-preview">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-[10px] p-1 text-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                            <i class="fas fa-arrows-alt mr-1"></i> Зөөх
                        </div>
                    </div>
                `;

                // Устгах товч
                previewDiv.querySelector('.delete-preview').addEventListener('click', function() {
                    previewDiv.remove();
                    updateInputFilesOrder(); // Устгасны дараа input-ээ шинэчилнэ
                });

                imagePreviewContainer.appendChild(previewDiv);
                
                // Шинэ зураг орсны дараа input-ээ шинэчлэх
                updateInputFilesOrder(); 
            };
            reader.readAsDataURL(file);
        });

        imagePreviewContainer.classList.remove('hidden');
    }

    // 5. Input file-ийг DOM дээрх дарааллаар шинэчлэх функц (ХАМГИЙН ЧУХАЛ НЬ)
    function updateInputFilesOrder() {
        const dt = new DataTransfer();
        const previewDivs = Array.from(imagePreviewContainer.children);

        previewDivs.forEach(div => {
            if (div.file) {
                dt.items.add(div.file);
            }
        });

        imageInput.files = dt.files;
        
        // Хэрэв зураггүй бол container-ийг нуух
        if (dt.files.length === 0) {
            imagePreviewContainer.classList.add('hidden');
        }
    }

    // ======================
    // DRAG & DROP HELPERS
    // ======================

    // Highlight drop area when dragging over
['dragenter', 'dragover'].forEach(eventName => {
    [dropArea, imageDropArea].forEach(area => {
        area.addEventListener(eventName, function(e) {
            e.preventDefault();
            this.classList.add('border-purple-500', 'bg-purple-50');
        });
    });
});

    // Remove highlight when leaving
['dragleave', 'drop'].forEach(eventName => {
    [dropArea, imageDropArea].forEach(area => {
        area.addEventListener(eventName, function(e) {
            e.preventDefault();
            this.classList.remove('border-purple-500', 'bg-purple-50');
        });
    });
});

    // ======================
    // UTILITY FUNCTIONS
    // ======================

    // Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

document.getElementById('upload-form').addEventListener('submit', function(e) {
    
});
// ===== ЭЦСИЙН ШИНЭЧЛЭГДСЭН FORM SUBMIT КОД END =====
    // ======================
// CATEGORY HANDLING
// ======================

// Category selection
document.querySelectorAll('.category-badge').forEach(badge => {
    badge.addEventListener('click', function() {
        // Remove active class from all badges
        document.querySelectorAll('.category-badge').forEach(b => {
            b.classList.remove('bg-purple-100', 'text-purple-800');
        });

        // Add active class to clicked badge
        this.classList.add('bg-purple-100', 'text-purple-800');

        // Set category ID in hidden input
        const categoryId = this.getAttribute('data-category');
        document.getElementById('category_id').value = categoryId;

        // Show subcategory container
        document.getElementById('subcategory-container').classList.remove('hidden');

        // Clear previous subcategory selection
        document.getElementById('subcategory_id').value = '';
        document.querySelectorAll('.subcategory-item').forEach(item => {
            item.classList.remove('bg-purple-100', 'text-purple-800');
        });

        // Load subcategories via AJAX
        loadSubcategories(categoryId);
    });
});

// Subcategory selection
document.addEventListener('click', function(e) {
    if (e.target.closest('.subcategory-item')) {
        const item = e.target.closest('.subcategory-item');

        // Remove active class from all subcategories
        document.querySelectorAll('.subcategory-item').forEach(i => {
            i.classList.remove('bg-purple-100', 'text-purple-800');
        });

        // Add active class to clicked subcategory
        item.classList.add('bg-purple-100', 'text-purple-800');

        // Set subcategory ID in hidden input
        const subcategoryId = item.getAttribute('data-subcategory-id');
        document.getElementById('subcategory_id').value = subcategoryId;
    }
});

// Subcategory selection
document.addEventListener('click', function(e) {
    if (e.target.closest('.subcategory-item')) {
        const item = e.target.closest('.subcategory-item');

        // Remove active class from all subcategories
        document.querySelectorAll('.subcategory-item').forEach(i => {
            i.classList.remove('bg-purple-100', 'text-purple-800');
        });

        // Add active class to clicked subcategory
        item.classList.add('bg-purple-100', 'text-purple-800');

        // Set subcategory ID in hidden input
        const subcategoryId = item.getAttribute('data-subcategory-id');
        document.getElementById('subcategory_id').value = subcategoryId;

        // АЛХАМ 5: ЭНЭ КОДЫГ ДЭЭД КОДНЫ ДАРАА ШУУД НЭМНЭ
        // Load child categories
        loadChildCategories(subcategoryId);
    }
});

// Child category selection
document.addEventListener('click', function(e) {
    if (e.target.closest('.child-category-item')) {
        const item = e.target.closest('.child-category-item');

        // Remove active class from all child categories
        document.querySelectorAll('.child-category-item').forEach(i => {
            i.classList.remove('bg-purple-100', 'text-purple-800');
        });

        // Add active class to clicked child category
        item.classList.add('bg-purple-100', 'text-purple-800');

        // Set child category ID in hidden input
        const childCategoryId = item.getAttribute('data-child-category-id');
        document.getElementById('child_category_id').value = childCategoryId;
    }
});

// Load child categories via AJAX
function loadChildCategories(subcategoryId) {
    const childCategoryContainer = document.getElementById('child-category-container');
    const childCategoryList = document.getElementById('child-category-list');
    
    childCategoryContainer.classList.remove('hidden');
    childCategoryList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-purple-500"></i> Ачааллаж байна...</div>';
    
    fetch(`upload.php?subcategory_id=${subcategoryId}&ajax=1`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error(`Invalid response from server. Received: ${text.substring(0, 200)}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Unknown error occurred');
        }
        
        if (data.data.length > 0) {
            childCategoryList.innerHTML = '';
            data.data.forEach(childCategory => {
                const item = document.createElement('div');
                item.className = 'child-category-item px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded cursor-pointer';
                item.setAttribute('data-child-category-id', childCategory.id);
                item.textContent = childCategory.name;
                childCategoryList.appendChild(item);
            });
        } else {
            childCategoryList.innerHTML = '<div class="text-center py-4 text-gray-500">Энэ дэд ангилалд жижиг ангилал байхгүй байна</div>';
        }
    })
    .catch(error => {
        console.error('Child category load error:', error);
        childCategoryList.innerHTML = `
            <div class="text-center py-4 text-gray-500">
                Жижиг ангилал ачаалахад алдаа гарлаа
            </div>
        `;
    });
}

// Load subcategories via AJAX
function loadSubcategories(categoryId) {
    const subcategoryList = document.getElementById('subcategory-list');
    subcategoryList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-purple-500"></i> Ачааллаж байна...</div>';
    
    fetch(`upload.php?category_id=${categoryId}&ajax=1`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest' // Helps identify AJAX requests
        }
    })
    .then(response => {
        // First check if the response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error(`Invalid response from server. Please check server logs. Received: ${text.substring(0, 200)}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Unknown error occurred');
        }
        
        if (data.data.length > 0) {
            subcategoryList.innerHTML = '';
            data.data.forEach(subcategory => {
                const item = document.createElement('div');
                item.className = 'subcategory-item px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded cursor-pointer';
                item.setAttribute('data-subcategory-id', subcategory.id);
                item.textContent = subcategory.name;
                subcategoryList.appendChild(item);
            });
        } else {
            subcategoryList.innerHTML = '<div class="text-center py-4 text-gray-500">Энэ ангилалд дэд ангилал байхгүй байна</div>';
        }
    })
    .catch(error => {
        console.error('Subcategory load error:', error);
        subcategoryList.innerHTML = `
            <div class="text-center py-4 text-red-500">
                <p>Дэд ангилал ачаалахад алдаа гарлаа.</p>
                <p class="text-xs mt-1">${error.message}</p>
                <button type="button" onclick="loadSubcategories(${categoryId})" 
                        class="mt-2 px-3 py-1 bg-purple-100 text-purple-800 rounded text-sm hover:bg-purple-200 transition">
                    <i class="fas fa-sync-alt mr-1"></i> Дахин оролдох
                </button>
            </div>
        `;
    });
}
});
</script>