<?php
ob_start();

// Include essential files first
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Нэвтрээгүй бол зогсоох
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Засах файлын ID-г авах
if (!isset($_GET['id'])) {
    header("Location: profile.php");
    exit();
}

$file_id = intval($_GET['id']);
$conn = db_connect();
mysqli_set_charset($conn, "utf8mb4");

// ===================================================================
//  AJAX HANDLER: Зураг устгах (Single Image Delete)
// ===================================================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_preview') {
    try {
        if (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');

        $preview_id = intval($_POST['preview_id']);
        
        // Зураг нь энэ файл болон хэрэглэгчийнх мөн эсэхийг шалгах
        $check_sql = "SELECT p.preview_url, p.id 
                      FROM file_previews p 
                      JOIN files f ON p.file_id = f.id 
                      WHERE p.id = ? AND f.user_id = ?";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, "ii", $preview_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $preview = mysqli_fetch_assoc($result);

        if ($preview) {
            // Файлыг устгах
            if (file_exists($preview['preview_url'])) {
                unlink($preview['preview_url']);
            }
            // DB-ээс устгах
            $del_sql = "DELETE FROM file_previews WHERE id = ?";
            $del_stmt = mysqli_prepare($conn, $del_sql);
            mysqli_stmt_bind_param($del_stmt, "i", $preview_id);
            mysqli_stmt_execute($del_stmt);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Permission denied or image not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===================================================================
//  AJAX HANDLER: Ангилал сонгох (Upload.php-тэй ижил)
// ===================================================================
if (isset($_GET['ajax'])) {
    // ... (Энэ хэсэг upload.php-тэй яг ижил тул хуулж тавих эсвэл require хийж болно. 
    // Гэхдээ файлаа тусад нь байлгахын тулд энд бүтнээр нь бичлээ) ...
    try {
        if (ob_get_level() > 0) ob_end_clean();

        // Handle subcategories request
        if (isset($_GET['category_id'])) {
            $category_id = intval($_GET['category_id']);
            $sql = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $category_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $subcategories = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $subcategories[] = $row;
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $subcategories]);
        }
        // Handle child categories request
        elseif (isset($_GET['subcategory_id'])) {
            $subcategory_id = intval($_GET['subcategory_id']);
            $sql = "SELECT id, name FROM child_category WHERE subcategory_id = ? ORDER BY name ASC";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $subcategory_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $child_categories = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $child_categories[] = $row;
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $child_categories]);
        }
        exit;
    } catch (Exception $e) {
        http_response_code(500); 
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ===================================================================
//  ӨГӨГДӨЛ ТАТАХ (EDIT DATA FETCHING)
// ===================================================================

// 1. Файлын үндсэн мэдээлэл
$file_sql = "SELECT * FROM files WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $file_sql);
mysqli_stmt_bind_param($stmt, "ii", $file_id, $user_id);
mysqli_stmt_execute($stmt);
$file_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$file_data) {
    die("File not found or access denied.");
}

// 2. Ангиллын мэдээлэл
$cat_sql = "SELECT * FROM file_categories WHERE file_id = ?";
$cat_stmt = mysqli_prepare($conn, $cat_sql);
mysqli_stmt_bind_param($cat_stmt, "i", $file_id);
mysqli_stmt_execute($cat_stmt);
$cat_data = mysqli_fetch_assoc(mysqli_stmt_get_result($cat_stmt));

// 3. Шошго (Tags)
$tags_sql = "SELECT t.name FROM tags t JOIN file_tags ft ON t.id = ft.tag_id WHERE ft.file_id = ?";
$tags_stmt = mysqli_prepare($conn, $tags_sql);
mysqli_stmt_bind_param($tags_stmt, "i", $file_id);
mysqli_stmt_execute($tags_stmt);
$tags_result = mysqli_stmt_get_result($tags_stmt);
$tags_array = [];
while ($row = mysqli_fetch_assoc($tags_result)) {
    $tags_array[] = $row['name'];
}
$current_tags = implode(', ', $tags_array);

// 4. Зургууд (Previews)
$previews_sql = "SELECT * FROM file_previews WHERE file_id = ? ORDER BY order_index ASC";
$previews_stmt = mysqli_prepare($conn, $previews_sql);
mysqli_stmt_bind_param($previews_stmt, "i", $file_id);
mysqli_stmt_execute($previews_stmt);
$previews_result = mysqli_stmt_get_result($previews_stmt);
$current_previews = [];
while ($row = mysqli_fetch_assoc($previews_result)) {
    $current_previews[] = $row;
}

// Dropdown Categories
$categories = [];
$sql = "SELECT * FROM categories ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) $categories[] = $row;


// ===================================================================
//  FORM SUBMISSION (UPDATE)
// ===================================================================
$errors = [];
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token";
    } else {
        $title = trim($_POST['title']);
        // HTML цэвэрлэх (<a> tag зөвшөөрөгдсөн)
        $allowed_tags = '<p><br><b><strong><i><em><u><ul><ol><li><span><a>';
        $description = strip_tags($_POST['description'], $allowed_tags);
        $description = trim($description);
        
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $subcategory_id = intval($_POST['subcategory_id']);
        $child_category_id = isset($_POST['child_category_id']) ? intval($_POST['child_category_id']) : 0;
        $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';

        // Validation
        if (empty($title)) $errors[] = "Гарчиг хоосон байна";
        if (empty($description)) $errors[] = "Тайлбар хоосон байна";
        if ($price < 0) $errors[] = "Үнэ буруу байна";
        if (empty($category_id)) $errors[] = "Ангилал сонгоогүй байна";
        if (empty($subcategory_id)) $errors[] = "Дэд ангилал сонгоогүй байна";

        // File Update Check
        $resumableFile = isset($_POST['resumable_filename']) ? trim($_POST['resumable_filename']) : '';
        $mainFile = null;
        $isFileUpdated = false;

        if (!empty($resumableFile)) {
            $tempPath = 'uploads/temp/' . $resumableFile;
            if (file_exists($tempPath)) {
                $isFileUpdated = true;
            } else {
                $errors[] = "Шинэ файл олдсонгүй, дахин оруулна уу.";
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $mainFile = $_FILES['file'];
            $isFileUpdated = true;
        }

        // Image Validation (New Images)
        $validPreviews = [];
        if (!empty($_FILES['previews']['name'][0])) {
            $previewFiles = $_FILES['previews'];
            for ($i = 0; $i < count($previewFiles['name']); $i++) {
                if ($previewFiles['error'][$i] === UPLOAD_ERR_OK) {
                    $validPreviews[] = [
                        'name' => $previewFiles['name'][$i],
                        'tmp_name' => $previewFiles['tmp_name'][$i]
                    ];
                }
            }
        }

        if (empty($errors)) {
            mysqli_begin_transaction($conn);
            try {
                // 1. UPDATE FILE INFO
                $updateSql = "UPDATE files SET title=?, description=?, price=?, category_id=? WHERE id=?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                mysqli_stmt_bind_param($updateStmt, "ssdii", $title, $description, $price, $category_id, $file_id);
                mysqli_stmt_execute($updateStmt);

                // 2. HANDLE FILE REPLACEMENT
                if ($isFileUpdated) {
                    $finalDir = 'uploads/files/' . $user_id . '/' . $file_id . '/';
                    if (!is_dir($finalDir)) mkdir($finalDir, 0755, true);

                    // Хуучин файлыг устгах
                    if (!empty($file_data['file_url']) && file_exists($file_data['file_url'])) {
                        unlink($file_data['file_url']);
                    }

                    // Шинэ файлын нэр
                    if (!empty($resumableFile)) {
                        $originalFullName = $_POST['original_filename'];
                        $fileSize = filesize('uploads/temp/' . $resumableFile);
                        $tempSourcePath = 'uploads/temp/' . $resumableFile;
                    } else {
                        $originalFullName = $mainFile['name'];
                        $fileSize = $mainFile['size'];
                    }

                    $pathInfo = pathinfo($originalFullName);
                    $fileNameOnly = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $pathInfo['filename']);
                    $extension = strtolower($pathInfo['extension']);
                    
                    // DB Type
                    $extMap = ['pdf'=>'pdf', 'doc'=>'doc', 'docx'=>'doc', 'zip'=>'zip', 'rar'=>'rar', 'exe'=>'exe']; // (Shortened)
                    $fileTypeForDB = array_key_exists($extension, $extMap) ? $extMap[$extension] : 'other';

                    $safeFileName = trim($fileNameOnly) ?: 'file_' . uniqid();
                    $finalNameWithExt = $safeFileName . '.' . $extension;
                    $finalFilePath = $finalDir . $finalNameWithExt;

                    // Зөөх/Хуулах
                    if (!empty($resumableFile)) {
                        if (!@rename($tempSourcePath, $finalFilePath)) {
                            // Fallback to copy/unlink if rename fails
                            copy($tempSourcePath, $finalFilePath);
                            unlink($tempSourcePath);
                        }
                    } else {
                        move_uploaded_file($mainFile['tmp_name'], $finalFilePath);
                    }

                    // DB Update path
                    $pathSql = "UPDATE files SET file_url=?, file_type=?, file_size=? WHERE id=?";
                    $pathStmt = mysqli_prepare($conn, $pathSql);
                    mysqli_stmt_bind_param($pathStmt, "ssii", $finalFilePath, $fileTypeForDB, $fileSize, $file_id);
                    mysqli_stmt_execute($pathStmt);
                }

                // 3. UPDATE CATEGORIES
                // Check if entry exists
                $checkCat = mysqli_query($conn, "SELECT file_id FROM file_categories WHERE file_id = $file_id");
                if (mysqli_num_rows($checkCat) > 0) {
                    $subcatSql = "UPDATE file_categories SET subcategory_id=?, child_category_id=? WHERE file_id=?";
                    $stmtSub = mysqli_prepare($conn, $subcatSql);
                    mysqli_stmt_bind_param($stmtSub, "iii", $subcategory_id, $child_category_id, $file_id);
                } else {
                    $subcatSql = "INSERT INTO file_categories (file_id, subcategory_id, child_category_id) VALUES (?, ?, ?)";
                    $stmtSub = mysqli_prepare($conn, $subcatSql);
                    mysqli_stmt_bind_param($stmtSub, "iii", $file_id, $subcategory_id, $child_category_id);
                }
                mysqli_stmt_execute($stmtSub);

                // 4. UPDATE TAGS (Delete old, insert new)
                mysqli_query($conn, "DELETE FROM file_tags WHERE file_id = $file_id");
                if (!empty($tags)) {
                    $tag_array = array_map('trim', explode(',', $tags));
                    foreach ($tag_array as $tag) {
                        if (!empty($tag)) {
                            $tag_sql = "SELECT id FROM tags WHERE name = ?";
                            $stmtTag = mysqli_prepare($conn, $tag_sql);
                            mysqli_stmt_bind_param($stmtTag, "s", $tag);
                            mysqli_stmt_execute($stmtTag);
                            $res = mysqli_stmt_get_result($stmtTag);
                            if ($row = mysqli_fetch_assoc($res)) {
                                $tag_id = $row['id'];
                            } else {
                                $stmtIns = mysqli_prepare($conn, "INSERT INTO tags (name) VALUES (?)");
                                mysqli_stmt_bind_param($stmtIns, "s", $tag);
                                mysqli_stmt_execute($stmtIns);
                                $tag_id = mysqli_insert_id($conn);
                            }
                            $stmtLink = mysqli_prepare($conn, "INSERT INTO file_tags (file_id, tag_id) VALUES (?, ?)");
                            mysqli_stmt_bind_param($stmtLink, "ii", $file_id, $tag_id);
                            mysqli_stmt_execute($stmtLink);
                        }
                    }
                }

                // 5. ADD NEW PREVIEWS
                $previewDir = 'uploads/previews/' . $user_id . '/' . $file_id . '/';
                if (!is_dir($previewDir)) mkdir($previewDir, 0755, true);

                // Get current max order index
                $orderRes = mysqli_query($conn, "SELECT MAX(order_index) as max_order FROM file_previews WHERE file_id = $file_id");
                $maxRow = mysqli_fetch_assoc($orderRes);
                $startOrder = ($maxRow['max_order'] ?? 0) + 1;

                foreach ($validPreviews as $index => $preview) {
                    $pExt = pathinfo($preview['name'], PATHINFO_EXTENSION);
                    $previewName = uniqid() . '_preview.' . $pExt;
                    $previewPath = $previewDir . $previewName;
                    
                    if (move_uploaded_file($preview['tmp_name'], $previewPath)) {
                        $pSql = "INSERT INTO file_previews (file_id, preview_url, order_index) VALUES (?, ?, ?)";
                        $pStmt = mysqli_prepare($conn, $pSql);
                        $order = $startOrder + $index;
                        mysqli_stmt_bind_param($pStmt, "isi", $file_id, $previewPath, $order);
                        mysqli_stmt_execute($pStmt);
                    }
                }

                mysqli_commit($conn);
                
                // Шинэчлэгдсэн мэдээлэл харуулахын тулд refresh
                header("Location: edit_file.php?id=$file_id&success=1");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors[] = "Error: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Файл засах - " . htmlspecialchars($file_data['title']);
include 'includes/header.php';
include 'includes/navigation.php';
?>

<main class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Файл засах</h1>
            <a href="profile.php" class="text-gray-600 hover:text-purple-600"><i class="fas fa-arrow-left"></i> Буцах</a>
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

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                Мэдээлэл амжилттай шинэчлэгдлээ.
            </div>
        <?php endif; ?>

        <form id="upload-form" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6 mb-6">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" id="resumable_filename" name="resumable_filename" value="">    
            <input type="hidden" id="original_filename" name="original_filename" value="">

            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Үндсэн файл (Солих бол шинийг оруулна уу)</h3>
                
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded flex items-center justify-between">
                    <span class="text-sm text-blue-800">
                        <i class="fas fa-file-alt mr-2"></i> Одоогийн файл: 
                        <strong><?= basename($file_data['file_url']) ?></strong>
                    </span>
                    <span class="text-xs text-gray-500"><?= number_format($file_data['file_size'] / 1024 / 1024, 2) ?> MB</span>
                </div>

                <div id="main-drop-area" class="upload-container border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer">
                    <i class="fas fa-cloud-upload-alt text-4xl text-purple-500 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Файлаа энд чирж буулгах эсвэл</h3>
                    <button id="browse-btn" type="button" class="gradient-bg text-white px-6 py-2 rounded-md font-medium hover:bg-purple-700 transition">
                        <i class="fas fa-folder-open mr-2"></i> Шинэ файл сонгох
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
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Зураг (Шинээр нэмэх эсвэл хасах)</h3>
                
                <?php if (!empty($current_previews)): ?>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <?php foreach ($current_previews as $img): ?>
                            <div class="relative group border rounded-lg overflow-hidden h-32" id="preview-<?= $img['id'] ?>">
                                <img src="<?= htmlspecialchars($img['preview_url']) ?>" class="w-full h-full object-cover">
                                <button type="button" onclick="deleteExistingPreview(<?= $img['id'] ?>)" 
                                        class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 text-xs opacity-0 group-hover:opacity-100 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div id="image-drop-area" class="upload-container border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer">
                    <i class="fas fa-images text-4xl text-purple-500 mb-4"></i>
                    <p class="text-gray-500 mb-4">Шинэ зураг нэмэх (JPG, PNG)</p>
                    <button id="image-browse-btn" type="button" class="bg-white text-purple-600 border border-purple-600 px-6 py-2 rounded-md font-medium hover:bg-purple-50 transition">
                        <i class="fas fa-folder-open mr-2"></i> Зураг сонгох
                    </button>
                    <input type="file" id="image-input" name="previews[]" class="hidden" accept="image/*" multiple>
                </div>
                <div id="image-preview-container" class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4 hidden"></div>
            </div>

            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Гарчиг</label>
                        <input type="text" name="title" class="w-full border border-gray-300 rounded-md px-3 py-2" 
                               value="<?= htmlspecialchars($file_data['title']) ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Үнэ (MNT)</label>
                        <input type="text" id="price" name="price" class="w-full border border-gray-300 rounded-md px-3 py-2" 
                               value="<?= htmlspecialchars($file_data['price']) ?>">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Тайлбар</label>
                    <textarea id="description" name="description" class="w-full border border-gray-300 rounded-md px-3 py-2" rows="5"><?= htmlspecialchars($file_data['description']) ?></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ангилал</label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <?php foreach ($categories as $category): ?>
                            <button type="button" 
                                class="category-badge px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-800 hover:bg-purple-100 hover:text-purple-800 transition <?php echo ($file_data['category_id'] == $category['id'] ? 'bg-purple-100 text-purple-800' : ''); ?>" 
                                data-category="<?= $category['id'] ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="category_id" name="category_id" value="<?= $file_data['category_id'] ?>">

                    <div id="subcategory-container" class="bg-white border border-gray-200 rounded-md p-4 mb-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Дэд ангилал:</h4>
                        <div id="subcategory-list" class="space-y-2"></div>
                    </div>
                    <input type="hidden" id="subcategory_id" name="subcategory_id" value="<?= $cat_data['subcategory_id'] ?? '' ?>">

                    <div id="child-category-container" class="bg-white border border-gray-200 rounded-md p-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Жижиг ангилал:</h4>
                        <div id="child-category-list" class="space-y-2"></div>
                    </div>
                    <input type="hidden" id="child_category_id" name="child_category_id" value="<?= $cat_data['child_category_id'] ?? '' ?>">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Шошго (Таслалаар тусгаарлах)</label>
                    <input type="text" name="tags" class="w-full border border-gray-300 rounded-md px-3 py-2" 
                           value="<?= htmlspecialchars($current_tags) ?>">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" id="submit-btn" class="gradient-bg text-white px-8 py-3 rounded-md font-medium hover:bg-purple-700 transition">
                    Хадгалах
                </button>
            </div>
        </form>
    </div>
</main>

<div id="upload-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full text-center">
        <div id="modal-spinner" class="w-16 h-16 border-4 border-purple-200 border-t-purple-600 rounded-full animate-spin mx-auto mb-4"></div>
        <h3 id="modal-title" class="text-xl font-bold text-gray-800 mb-1">Боловсруулж байна...</h3>
        <div id="modal-progress-area" class="w-full bg-gray-200 rounded-full h-2.5 mt-4 mb-2 overflow-hidden">
            <div id="modal-progress-bar" class="bg-purple-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <div id="modal-percent" class="text-right text-xs font-semibold text-purple-600">0%</div>
    </div>
</div>

<script src="https://cdn.tiny.cloud/1/g492qv0cyczptbbzcso4exirfkhg3l20o9z13ujy2i0arcw5/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<script>
// PHP variables to JS for pre-selection
const CURRENT_CAT = "<?= $file_data['category_id'] ?>";
const CURRENT_SUBCAT = "<?= $cat_data['subcategory_id'] ?? '' ?>";
const CURRENT_CHILD = "<?= $cat_data['child_category_id'] ?? '' ?>";

// Delete Existing Preview
function deleteExistingPreview(id) {
    if(!confirm('Энэ зургийг устгах уу?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_preview');
    formData.append('preview_id', id);
    
    fetch('edit_file.php?id=<?= $file_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            document.getElementById('preview-'+id).remove();
        } else {
            alert('Error: ' + data.error);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. TinyMCE
    tinymce.init({
        selector: '#description',
        height: 300,
        menubar: false,
        plugins: 'emoticons lists link autolink charmap',
        toolbar: 'bold italic underline | bullist numlist | emoticons | link',
        branding: false,
        statusbar: false,
        setup: function(editor) {
            editor.on('change', function() { editor.save(); });
        }
    });

    // 2. Resumable Upload Logic (Same as upload.php but simplified for edit)
    const dropArea = document.getElementById('main-drop-area');
    const browseBtn = document.getElementById('browse-btn');
    const hiddenFilenameInput = document.getElementById('resumable_filename');
    const uploadForm = document.getElementById('upload-form');
    const uploadModal = document.getElementById('upload-modal');
    const modalProgressBar = document.getElementById('modal-progress-bar');
    const modalPercent = document.getElementById('modal-percent');

    var r = new Resumable({
        target: 'upload_chunk.php',
        chunkSize: 20 * 1024 * 1024,
        simultaneousUploads: 4,
        testChunks: false,
        throttleProgressCallbacks: 1
    });

    if (r.support) {
        r.assignBrowse(browseBtn);
        r.assignDrop(dropArea);
        
        r.on('fileAdded', function(file) {
            if (file.size > 300 * 1024 * 1024) {
                alert('File too large (Max 300MB)');
                r.removeFile(file);
                return;
            }
            document.getElementById('main-drop-area').classList.add('hidden');
            document.getElementById('main-file-preview').classList.remove('hidden');
            document.getElementById('main-file-name').textContent = file.fileName;
            document.getElementById('main-file-size').textContent = (file.size/1024/1024).toFixed(2) + ' MB';
        });

        r.on('fileProgress', function(file) {
            const pct = Math.floor(file.progress() * 100) + '%';
            modalProgressBar.style.width = pct;
            modalPercent.textContent = pct;
        });

        r.on('fileSuccess', function(file, message) {
            hiddenFilenameInput.value = message;
            document.getElementById('original_filename').value = file.fileName;
            uploadForm.submit(); // Submit form after file upload
        });
    }

    // 3. New Image Sorting
    const imageInput = document.getElementById('image-input');
    const imagePreviewContainer = document.getElementById('image-preview-container');
    const imageBrowseBtn = document.getElementById('image-browse-btn');
    const imageDropArea = document.getElementById('image-drop-area');

    new Sortable(imagePreviewContainer, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function (evt) { updateInputFilesOrder(); }
    });

    imageBrowseBtn.addEventListener('click', () => imageInput.click());
    
    imageInput.addEventListener('change', function() {
        if (this.files.length) handleImageFiles(Array.from(this.files));
    });

    function handleImageFiles(files) {
        // Simple preview logic
        files.forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'relative group border rounded h-32 cursor-grab';
                div.file = file;
                div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">
                                 <button type="button" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 delete-new-img"><i class="fas fa-times"></i></button>`;
                div.querySelector('.delete-new-img').onclick = function() {
                    div.remove();
                    updateInputFilesOrder();
                };
                imagePreviewContainer.appendChild(div);
                updateInputFilesOrder();
            };
            reader.readAsDataURL(file);
        });
        imagePreviewContainer.classList.remove('hidden');
    }

    function updateInputFilesOrder() {
        const dt = new DataTransfer();
        Array.from(imagePreviewContainer.children).forEach(div => {
            if(div.file) dt.items.add(div.file);
        });
        imageInput.files = dt.files;
    }

    // 4. Form Submit
    uploadForm.addEventListener('submit', function(e) {
        if (window.tinymce) tinymce.triggerSave();
        
        // If a new file is added via Resumable, upload it first
        if (r.files.length > 0 && !hiddenFilenameInput.value) {
            e.preventDefault();
            uploadModal.classList.remove('hidden');
            r.upload();
        }
    });

    // 5. Category Loading Logic (Auto-load existing)
    
    // Initial Load
    if (CURRENT_CAT) {
        loadSubcategories(CURRENT_CAT, CURRENT_SUBCAT, CURRENT_CHILD);
    }

    // Change Event
    document.querySelectorAll('.category-badge').forEach(badge => {
        badge.addEventListener('click', function() {
            document.querySelectorAll('.category-badge').forEach(b => b.classList.remove('bg-purple-100', 'text-purple-800'));
            this.classList.add('bg-purple-100', 'text-purple-800');
            document.getElementById('category_id').value = this.dataset.category;
            loadSubcategories(this.dataset.category);
        });
    });

    function loadSubcategories(catId, selectedSub = null, selectedChild = null) {
        const container = document.getElementById('subcategory-list');
        fetch(`upload.php?category_id=${catId}&ajax=1`)
        .then(r => r.json())
        .then(res => {
            if(res.success && res.data.length) {
                container.innerHTML = res.data.map(sub => `
                    <div class="subcategory-item px-3 py-2 hover:bg-gray-100 rounded cursor-pointer ${sub.id == selectedSub ? 'bg-purple-100 text-purple-800' : ''}" 
                         data-id="${sub.id}">${sub.name}</div>
                `).join('');
                
                // Add click events to new items
                container.querySelectorAll('.subcategory-item').forEach(item => {
                    item.addEventListener('click', function() {
                        container.querySelectorAll('.subcategory-item').forEach(i => i.classList.remove('bg-purple-100', 'text-purple-800'));
                        this.classList.add('bg-purple-100', 'text-purple-800');
                        document.getElementById('subcategory_id').value = this.dataset.id;
                        loadChildCategories(this.dataset.id);
                    });
                });

                if(selectedSub) loadChildCategories(selectedSub, selectedChild);
            } else {
                container.innerHTML = '<div class="text-gray-500 text-sm">No subcategories</div>';
            }
        });
    }

    function loadChildCategories(subId, selectedChild = null) {
        const container = document.getElementById('child-category-list');
        fetch(`upload.php?subcategory_id=${subId}&ajax=1`)
        .then(r => r.json())
        .then(res => {
            if(res.success && res.data.length) {
                container.innerHTML = res.data.map(child => `
                    <div class="child-category-item px-3 py-2 hover:bg-gray-100 rounded cursor-pointer ${child.id == selectedChild ? 'bg-purple-100 text-purple-800' : ''}" 
                         data-id="${child.id}">${child.name}</div>
                `).join('');
                
                container.querySelectorAll('.child-category-item').forEach(item => {
                    item.addEventListener('click', function() {
                        container.querySelectorAll('.child-category-item').forEach(i => i.classList.remove('bg-purple-100', 'text-purple-800'));
                        this.classList.add('bg-purple-100', 'text-purple-800');
                        document.getElementById('child_category_id').value = this.dataset.id;
                    });
                });
            } else {
                container.innerHTML = '<div class="text-gray-500 text-sm">No child categories</div>';
            }
        });
    }
    
    // Price formatter
    const priceInput = document.getElementById('price');
    priceInput.addEventListener('input', function() {
        // Simple logic to keep numbers
        this.value = this.value.replace(/[^0-9.]/g, '');
    });
});
</script>

<?php include 'includes/footer.php'; ?>