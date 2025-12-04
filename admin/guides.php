<?php
// admin/guides.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect("localhost", "filezone_mn", "099da7e85a2688", "filezone_mn");
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }
mysqli_set_charset($conn, "utf8mb4");

$error = '';
$success = '';
$edit_guide = null;
$edit_guide_images = [];

// === ФУНКЦҮҮД ===
function generateSlug($title) {
    $cyrillic = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'];
    $latin = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','kh','ts','ch','sh','shch','','y','','e','yu','ya'];
    $title = str_replace($cyrillic, $latin, strtolower($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $title);
    return trim($slug, '-');
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

// === POST ХҮСЭЛТ БОЛОВСРУУЛАХ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);
    try {
        // ... (Өмнөх POST боловсруулах код хэвээрээ) ...
        // === ШИНЭЧЛЭЛТ: Олон зураг болон Онцлох зураг боловсруулах ===
        $author_id = $_SESSION['user_id'];
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $guide_type = $_POST['guide_type'] === 'pdf' ? 'pdf' : 'content';
        $content = $guide_type === 'content' ? $_POST['content'] : '';
        $youtube_url = mysqli_real_escape_string($conn, $_POST['youtube_url']);
        $layout_type = $_POST['layout_type'] === 'video_focused' ? 'video_focused' : 'standard';
        $price = floatval($_POST['price']);
        $status = in_array($_POST['status'], ['draft', 'published']) ? $_POST['status'] : 'draft';
        $slug = generateSlug($title);
        
        $pdf_url = $_POST['current_pdf_url'] ?? null;
        $featured_image = $_POST['current_featured_image'] ?? null;

        // PDF файл байршуулах
        if ($guide_type === 'pdf' && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/guides/pdfs/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_name = uniqid() . '-' . basename($_FILES['pdf_file']['name']);
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_dir . $file_name)) {
                if ($pdf_url && file_exists('../' . $pdf_url)) @unlink('../' . $pdf_url); // Хуучныг устгах
                $pdf_url = 'uploads/guides/pdfs/' . $file_name;
            } else { throw new Exception("PDF файл байршуулахад алдаа гарлаа."); }
        }

        // Онцлох зураг байршуулах
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/guides/featured/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $img_name = uniqid() . '-' . basename($_FILES['featured_image']['name']);
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_dir . $img_name)) {
                if ($featured_image && file_exists('../' . $featured_image)) @unlink('../' . $featured_image); // Хуучныг устгах
                $featured_image = 'uploads/guides/featured/' . $img_name;
            } else { throw new Exception("Онцлох зураг байршуулахад алдаа гарлаа."); }
        }

        $guide_id = 0;
        if (isset($_POST['update_guide'])) {
            $guide_id = intval($_POST['guide_id']);
            $stmt = mysqli_prepare($conn, "UPDATE guides SET title=?, content=?, price=?, status=?, slug=?, guide_type=?, pdf_url=?, youtube_url=?, layout_type=?, featured_image=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssdsssssss", $title, $content, $price, $status, $slug, $guide_type, $pdf_url, $youtube_url, $layout_type, $featured_image, $guide_id);
            if(!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($conn));
            $success = "Заавар амжилттай шинэчлэгдлээ.";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO guides (author_id, title, content, price, status, slug, guide_type, pdf_url, youtube_url, layout_type, featured_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issdsssssss", $author_id, $title, $content, $price, $status, $slug, $guide_type, $pdf_url, $youtube_url, $layout_type, $featured_image);
            if(!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($conn));
            $guide_id = mysqli_insert_id($conn);
            $success = "Шинэ заавар амжилттай нэмэгдлээ.";
        }

        // Нийтлэлийн олон зураг байршуулах
        if (isset($_FILES['guide_images']) && !empty($_FILES['guide_images']['name'][0]) && $guide_id > 0) {
            $image_upload_dir = "../uploads/guides/images/{$guide_id}/";
            if (!is_dir($image_upload_dir)) mkdir($image_upload_dir, 0755, true);

            foreach ($_FILES['guide_images']['name'] as $key => $name) {
                if ($_FILES['guide_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $img_name = uniqid() . '-' . basename($name);
                    if (move_uploaded_file($_FILES['guide_images']['tmp_name'][$key], $image_upload_dir . $img_name)) {
                        $image_url_db = "uploads/guides/images/{$guide_id}/" . $img_name;
                        $img_stmt = mysqli_prepare($conn, "INSERT INTO guide_images (guide_id, image_url) VALUES (?, ?)");
                        mysqli_stmt_bind_param($img_stmt, "is", $guide_id, $image_url_db);
                        mysqli_stmt_execute($img_stmt);
                    }
                }
            }
        }
        mysqli_commit($conn);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

// === УСТГАХ ҮЙЛДЭЛ ===
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $guide_id = intval($_GET['id']);
    
    // 1. Устгах файлуудын мэдээллийг авах
    $res = mysqli_query($conn, "SELECT pdf_url, featured_image FROM guides WHERE id = $guide_id");
    $guide_files = mysqli_fetch_assoc($res);

    mysqli_begin_transaction($conn);
    try {
        // 2. Файлуудыг серверээс устгах
        if ($guide_files) {
            if ($guide_files['pdf_url'] && file_exists('../' . $guide_files['pdf_url'])) @unlink('../' . $guide_files['pdf_url']);
            if ($guide_files['featured_image'] && file_exists('../' . $guide_files['featured_image'])) @unlink('../' . $guide_files['featured_image']);
        }
        // Нийтлэлийн зургуудын хавтсыг тэр чигт нь устгах
        $images_dir = "../uploads/guides/images/{$guide_id}";
        if(is_dir($images_dir)) {
            deleteDirectory($images_dir);
        }

        // 3. Датабаазаас устгах (guide_images-ийн бичлэгүүд CASCADE-р устгагдана)
        $stmt = mysqli_prepare($conn, "DELETE FROM guides WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $guide_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Датабаазаас устгахад алдаа гарлаа.");

        mysqli_commit($conn);
        header("Location: guides.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Устгах үйлдэл амжилтгүй боллоо: " . $e->getMessage();
    }
}


// === ЗАСАХААР МЭДЭЭЛЭЛ АВАХ ===
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $guide_id = intval($_GET['id']);
    $result = mysqli_query($conn, "SELECT * FROM guides WHERE id = $guide_id");
    $edit_guide = mysqli_fetch_assoc($result);
    // Засаж буй зааврын зургуудыг авах
    $img_result = mysqli_query($conn, "SELECT * FROM guide_images WHERE guide_id = $guide_id ORDER BY order_index ASC");
    while($row = mysqli_fetch_assoc($img_result)) {
        $edit_guide_images[] = $row;
    }
}

// Бүх зааврыг датабаазаас авах
$guides_result = mysqli_query($conn, "SELECT g.*, u.username FROM guides g LEFT JOIN users u ON g.author_id = u.id ORDER BY g.created_at DESC");

?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filezone - Зааврын удирдлага</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white shadow-sm py-4 px-6"><h2 class="text-xl font-bold text-gray-800">Зааврын удирдлага</h2></header>
            
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= $success; ?></div><?php endif; ?>

                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= $edit_guide ? 'Заавар засах' : 'Шинэ заавар нэмэх' ?></h3>
                    <form method="POST" action="guides.php" enctype="multipart/form-data">
                        <?php if ($edit_guide): ?>
                            <input type="hidden" name="update_guide" value="1">
                            <input type="hidden" name="guide_id" value="<?= $edit_guide['id'] ?>">
                            <input type="hidden" name="current_pdf_url" value="<?= htmlspecialchars($edit_guide['pdf_url'] ?? '') ?>">
                            <input type="hidden" name="current_featured_image" value="<?= htmlspecialchars($edit_guide['featured_image'] ?? '') ?>">
                        <?php endif; ?>

                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2 font-medium">Зааврын төрөл</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center"><input type="radio" name="guide_type" value="content" class="form-radio" <?= (!isset($edit_guide) || $edit_guide['guide_type'] == 'content') ? 'checked' : '' ?>> <span class="ml-2">Нийтлэл бичих</span></label>
                                <label class="flex items-center"><input type="radio" name="guide_type" value="pdf" class="form-radio" <?= (isset($edit_guide) && $edit_guide['guide_type'] == 'pdf') ? 'checked' : '' ?>> <span class="ml-2">PDF файл оруулах</span></label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-1">Гарчиг</label>
                            <input type="text" name="title" class="w-full border border-gray-300 rounded-md px-3 py-2" value="<?= htmlspecialchars($edit_guide['title'] ?? '') ?>" required>
                        </div>
                        
                        <div id="content-fields" class="space-y-4">
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-1">Агуулга (HTML код ашиглаж болно)</label>
                                <textarea name="content" class="w-full border border-gray-300 rounded-md px-3 py-2" rows="10"><?= htmlspecialchars($edit_guide['content'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-1">Нийтлэлийн зургууд (олон зураг сонгож болно)</label>
                                <input type="file" name="guide_images[]" multiple accept="image/*" class="w-full border border-gray-300 rounded-md px-3 py-2 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
                            </div>
                             <div class="mb-4">
                                <label class="block text-gray-700 mb-1">YouTube видео холбоос (заавал биш)</label>
                                <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..." class="w-full border border-gray-300 rounded-md px-3 py-2" value="<?= htmlspecialchars($edit_guide['youtube_url'] ?? '') ?>">
                            </div>
                        </div>

                        <div id="pdf-fields" class="hidden space-y-4">
                             <div class="mb-4">
                                <label class="block text-gray-700 mb-1">PDF Файл</label>
                                <input type="file" name="pdf_file" accept=".pdf" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <?php if (isset($edit_guide['pdf_url']) && !empty($edit_guide['pdf_url'])): ?>
                                    <p class="text-sm text-gray-500 mt-1">Одоогийн файл: <a href="../<?= htmlspecialchars($edit_guide['pdf_url']) ?>" target="_blank" class="text-blue-600">Үзэх</a>. Солих бол шинэ файл сонгоно уу.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-4 mt-4">
                            <label class="block text-gray-700 mb-1">Онцлох зураг</label>
                            <input type="file" name="featured_image" accept="image/*" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <?php if (isset($edit_guide['featured_image']) && !empty($edit_guide['featured_image'])): ?>
                                <div class="mt-2">
                                    <img src="../<?= htmlspecialchars($edit_guide['featured_image']) ?>" class="h-24 border rounded-md">
                                    <p class="text-sm text-gray-500 mt-1">Одоогийн зураг. Солих бол шинэ зураг сонгоно уу.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-gray-700 mb-1">Үнэ (₮)</label>
                                <input type="number" name="price" class="w-full border border-gray-300 rounded-md px-3 py-2" value="<?= htmlspecialchars($edit_guide['price'] ?? '0.00') ?>" step="0.01" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-1">Статус</label>
                                <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    <option value="published" <?= (isset($edit_guide['status']) && $edit_guide['status'] == 'published') ? 'selected' : '' ?>>Нийтлэх</option>
                                    <option value="draft" <?= (isset($edit_guide['status']) && $edit_guide['status'] == 'draft') ? 'selected' : '' ?>>Ноорог</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-1">Харагдах байдал</label>
                                <select name="layout_type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    <option value="standard" <?= (isset($edit_guide['layout_type']) && $edit_guide['layout_type'] == 'standard') ? 'selected' : '' ?>>Стандарт</option>
                                    <option value="video_focused" <?= (isset($edit_guide['layout_type']) && $edit_guide['layout_type'] == 'video_focused') ? 'selected' : '' ?>>Видеог онцолсон</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <?php if ($edit_guide): ?><a href="guides.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 mr-2">Цуцлах</a><?php endif; ?>
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">Хадгалах</button>
                        </div>
                    </form>

                    <?php if ($edit_guide && !empty($edit_guide_images)): ?>
                        <div class="mt-6 border-t pt-4">
                            <h4 class="text-md font-semibold text-gray-800 mb-2">Оруулсан зургууд</h4>
                            <p class="text-sm text-gray-500 mb-4">Зургийнхаа кодыг хуулж аваад, дээрх "Агуулга" хэсэгт хүссэн газраа тавиарай.</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                                <?php foreach($edit_guide_images as $image): ?>
                                    <div class="text-center">
                                        <img src="../<?= htmlspecialchars($image['image_url']) ?>" class="w-full h-24 object-cover rounded-md border">
                                        <button onclick="copyToClipboard('<img src=\'../<?= htmlspecialchars($image['image_url']) ?>\' alt=\'\' style=\'max-width:100%; border-radius: 8px; margin-top: 1rem; margin-bottom: 1rem;\'>')" class="text-xs bg-gray-100 text-gray-700 px-2 py-1 mt-1 rounded hover:bg-gray-200 w-full">Код хуулах</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Гарчиг</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Төрөл</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Үнэ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Огноо</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Үйлдэл</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($guide = mysqli_fetch_assoc($guides_result)): ?>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($guide['title']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= $guide['guide_type'] == 'pdf' ? 'PDF Файл' : 'Нийтлэл' ?></td>
                                    <td class="px-6 py-4"><?= $guide['status'] === 'published' ? '<span class="badge-approved">Нийтлэгдсэн</span>' : '<span class="badge-pending">Ноорог</span>' ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900 font-bold"><?= number_format($guide['price']) ?>₮</td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('Y-m-d', strtotime($guide['created_at'])) ?></td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <a href="guides.php?action=edit&id=<?= $guide['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3"><i class="fas fa-edit"></i></a>
                                        <a href="guides.php?action=delete&id=<?= $guide['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Энэ зааврыг болон холбогдох бүх зургийг устгахдаа итгэлтэй байна уу?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const guideTypeRadios = document.querySelectorAll('input[name="guide_type"]');
        const contentFields = document.getElementById('content-fields');
        const pdfFields = document.getElementById('pdf-fields');

        function toggleFields() {
            if (document.querySelector('input[name="guide_type"]:checked').value === 'pdf') {
                contentFields.classList.add('hidden');
                pdfFields.classList.remove('hidden');
            } else {
                contentFields.classList.remove('hidden');
                pdfFields.classList.add('hidden');
            }
        }
        guideTypeRadios.forEach(radio => radio.addEventListener('change', toggleFields));
        toggleFields();
    });

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Зургийн HTML код хуулагдлаа! Одоо "Агуулга" хэсэгт тавьж болно.');
        }, function(err) {
            alert('Кодыг хуулж чадсангүй.');
        });
    }
</script>
</body>
</html>
<?php mysqli_close($conn); ?>