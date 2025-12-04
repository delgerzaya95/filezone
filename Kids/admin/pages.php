<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config.php';

// PRG Загвар: Өмнөх үйлдлээс session-д хадгалсан мэдээллийг шалгах
$error = $_SESSION['page_error'] ?? null;
$success = $_SESSION['page_success'] ?? null;
unset($_SESSION['page_error'], $_SESSION['page_success']);

$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$book = null;
$pages = [];

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Холболт амжилтгүй: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Үйлдэл гүйцэтгэх (нэмэх, засах, устгах)
$action = $_REQUEST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $book_id > 0) {
    // Хуудас нэмэх
    if ($action === 'add_page') {
        $page_content = trim($_POST['page_content']);
        $page_number = intval($_POST['page_number']);
        $image_url = '';
        $temp_error = '';

        $check_stmt = $conn->prepare("SELECT id FROM book_pages WHERE book_id = ? AND page_number = ?");
        $check_stmt->bind_param("ii", $book_id, $page_number);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            $temp_error = "Энэ дугаартай хуудас аль хэдийн байна.";
        }
        $check_stmt->close();

        if (empty($temp_error) && isset($_FILES['page_image']) && $_FILES['page_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../assets/pages/{$book_id}/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $file_extension = pathinfo($_FILES['page_image']['name'], PATHINFO_EXTENSION);
            $file_name = $page_number . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['page_image']['tmp_name'], $file_path)) {
                $image_url = "assets/pages/{$book_id}/" . $file_name;
            } else {
                $temp_error = 'Зураг байршуулахад алдаа гарлаа.';
            }
        }

        if (empty($temp_error)) {
            if (empty($page_content) && empty($image_url)) {
                $temp_error = "Хуудасны агуулга эсвэл зураг оруулна уу.";
            } else {
                $stmt = $conn->prepare("INSERT INTO book_pages (book_id, page_number, image_url, content) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $book_id, $page_number, $image_url, $page_content);
                if ($stmt->execute()) {
                    $_SESSION['page_success'] = "Хуудас амжилттай нэмэгдлээ!";
                } else {
                    $temp_error = "Алдаа гарлаа: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        if ($temp_error) $_SESSION['page_error'] = $temp_error;
        header("Location: pages.php?book_id=" . $book_id);
        exit();
    }

    // Хуудас засах
    if ($action === 'edit_page') {
        $page_id = intval($_POST['page_id']);
        $page_content = trim($_POST['page_content']);
        $new_page_number = intval($_POST['page_number']);
        $temp_error = '';

        // Засах гэж буй хуудасны одоогийн мэдээллийг авах
        $old_page_stmt = $conn->prepare("SELECT page_number, image_url FROM book_pages WHERE id = ?");
        $old_page_stmt->bind_param("i", $page_id);
        $old_page_stmt->execute();
        $old_page_result = $old_page_stmt->get_result()->fetch_assoc();
        $old_page_stmt->close();

        $image_url = $old_page_result['image_url'] ?? '';
        
        // Шинэ зураг байршуулсан бол
        if (isset($_FILES['page_image']) && $_FILES['page_image']['error'] === UPLOAD_ERR_OK) {
             if(!empty($image_url) && file_exists('../' . $image_url)) unlink('../' . $image_url);

            $upload_dir = "../assets/pages/{$book_id}/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $file_extension = pathinfo($_FILES['page_image']['name'], PATHINFO_EXTENSION);
            $file_name = $new_page_number . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if(move_uploaded_file($_FILES['page_image']['tmp_name'], $file_path)) {
                $image_url = "assets/pages/{$book_id}/" . $file_name;
            } else {
                $temp_error = "Шинэ зураг байршуулахад алдаа гарлаа.";
            }
        } elseif ($old_page_result['page_number'] != $new_page_number && !empty($image_url)) {
            // Зураг хэвээрээ, гэхдээ хуудасны дугаар солигдсон бол зургийн нэрийг өөрчлөх
            $old_path = '../' . $image_url;
            $file_extension = pathinfo($old_path, PATHINFO_EXTENSION);
            $new_name = $new_page_number . '.' . $file_extension;
            $new_path = "../assets/pages/{$book_id}/" . $new_name;
            if(file_exists($old_path) && rename($old_path, $new_path)) {
                $image_url = "assets/pages/{$book_id}/" . $new_name;
            }
        }
        
        if (empty($temp_error)) {
            $stmt = $conn->prepare("UPDATE book_pages SET page_number = ?, image_url = ?, content = ? WHERE id = ?");
            $stmt->bind_param("issi", $new_page_number, $image_url, $page_content, $page_id);
            if ($stmt->execute()) {
                $_SESSION['page_success'] = "Хуудас амжилттай шинэчлэгдлээ!";
            } else {
                $_SESSION['page_error'] = "Алдаа гарлаа: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['page_error'] = $temp_error;
        }
        header("Location: pages.php?book_id=" . $book_id);
        exit();
    }
}

// Хуудас устгах
if ($action === 'delete_page' && isset($_GET['page_id'])) {
    $page_id = intval($_GET['page_id']);
    
    $stmt = $conn->prepare("SELECT image_url FROM book_pages WHERE id = ?");
    $stmt->bind_param("i", $page_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($page = $result->fetch_assoc()) {
        if (!empty($page['image_url']) && file_exists('../' . $page['image_url'])) {
            unlink('../' . $page['image_url']);
        }
    }
    $stmt->close();
    
    $delete_stmt = $conn->prepare("DELETE FROM book_pages WHERE id = ?");
    $delete_stmt->bind_param("i", $page_id);
    if ($delete_stmt->execute()) {
        $_SESSION['page_success'] = "Хуудас амжилттай устгагдлаа!";
    } else {
        $_SESSION['page_error'] = "Устгахад алдаа гарлаа: " . $delete_stmt->error;
    }
    $delete_stmt->close();
    header("Location: pages.php?book_id=" . $book_id);
    exit();
}


// Хуудас болон номны мэдээллийг дэлгэцэнд харуулах зорилгоор авах
if ($book_id > 0) {
    // Номны мэдээлэл (нүүр зурагтай нь)
    $stmt = $conn->prepare("SELECT id, title, cover_image FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    if ($result = $stmt->get_result()) {
        $book = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Хуудаснуудыг дугаараар нь буурах дарааллаар авах
    $pages_stmt = $conn->prepare("SELECT * FROM book_pages WHERE book_id = ? ORDER BY page_number DESC");
    $pages_stmt->bind_param("i", $book_id);
    $pages_stmt->execute();
    $pages_result = $pages_stmt->get_result();
    while ($row = $pages_result->fetch_assoc()) {
        $pages[] = $row;
    }
    $pages_stmt->close();
}
$conn->close();

?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хуудас удирдах | FileZone Kids Админ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <div class="bg-gray-800 text-white w-64 min-h-screen">
            <div class="p-4 border-b border-gray-700">
                <h1 class="text-xl font-bold"><i class="fas fa-book-open mr-2"></i> FileZone Kids</h1>
                <p class="text-sm text-gray-400">Админ Хянах Самбар</p>
            </div>
            <nav class="p-4">
                <ul class="space-y-2">
                    <li><a href="index.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg"><i class="fas fa-tachometer-alt"></i><span>Хянах самбар</span></a></li>
                    <li><a href="books.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg"><i class="fas fa-book"></i><span>Номнууд</span></a></li>
                    <li><a href="pages.php" class="flex items-center space-x-2 bg-gray-700 text-white px-4 py-2 rounded-lg"><i class="fas fa-file-alt"></i><span>Хуудаснууд</span></a></li>
                    <li><a href="users.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg"><i class="fas fa-users"></i><span>Хэрэглэгчид</span></a></li>
                    <li class="pt-4 mt-4 border-t border-gray-700"><a href="../includes/logout.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg"><i class="fas fa-sign-out-alt"></i><span>Гарах</span></a></li>
                </ul>
            </nav>
        </div>

        <div class="flex-1 p-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!$book_id): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Ном сонгох</h2>
                    <p class="text-gray-600 mb-4">Хуудас нэмэхийн тулд эхлээд номоо сонгоно уу.</p>
                    <?php
                    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                    $conn->set_charset("utf8mb4");
                    if (!$conn->connect_error) {
                        $books_result = $conn->query("SELECT id, title FROM books ORDER BY title");
                        if ($books_result && $books_result->num_rows > 0): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php while($book_item = $books_result->fetch_assoc()): ?>
                                    <a href="pages.php?book_id=<?php echo $book_item['id']; ?>" class="border border-gray-300 rounded-lg p-4 hover:bg-gray-50 hover:border-blue-500 transition-colors">
                                        <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($book_item['title']); ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Хуудаснуудыг удирдах</p>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-book-open text-4xl mb-3"></i>
                                <p>Одоогоор ном байхгүй байна.</p>
                                <a href="books.php" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">Эхний номоо нэмэх <i class="fas fa-arrow-right ml-1"></i></a>
                            </div>
                        <?php endif;
                        $conn->close();
                    }
                    ?>
                </div>
            <?php else: ?>
                 <div class="flex justify-between items-start mb-8">
                    <div class="flex items-center space-x-4">
                        <?php if($book && !empty($book['cover_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($book['cover_image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-20 h-28 object-cover rounded shadow-md">
                        <?php endif; ?>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">"<?php echo htmlspecialchars($book['title']); ?>" номны хуудас</h1>
                            <p class="text-sm text-gray-500">Номны ID: <?php echo $book['id']; ?></p>
                        </div>
                    </div>
                    <a href="books.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm flex-shrink-0"><i class="fas fa-arrow-left mr-1"></i> Номнууд руу буцах</a>
                </div>

                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Шинэ хуудас нэмэх</h2>
                    <form action="pages.php?book_id=<?php echo $book_id; ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_page">
                        <div class="mb-4">
                            <label for="page_number" class="block text-gray-700 font-medium mb-2">Хуудасны дугаар *</label>
                            <input type="number" id="page_number" name="page_number" min="1" required class="w-full md:w-1/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" value="<?php echo (count($pages) > 0 ? $pages[0]['page_number'] : 0) + 1; ?>">
                        </div>
                        <div class="mb-4">
                            <label for="page_image" class="block text-gray-700 font-medium mb-2">Хуудасны зураг</label>
                            <input type="file" id="page_image" name="page_image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <p class="text-sm text-gray-500 mt-1">JPG, PNG, GIF, WEBP форматын зураг оруулна уу (15MB хүртэл).</p>
                        </div>
                        <div class="mb-4">
                            <label for="page_content" class="block text-gray-700 font-medium mb-2">Хуудасны агуулга</label>
                            <textarea id="page_content" name="page_content" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Хуудасны агуулгыг оруулна уу..."></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg"><i class="fas fa-plus mr-1"></i> Хуудас нэмэх</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Одоо байгаа хуудаснууд (<?php echo count($pages); ?>)</h2>
                    <?php if (count($pages) > 0): ?>
                        <div class="space-y-6">
                            <?php foreach ($pages as $page): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">Хуудас <?php echo $page['page_number']; ?></span>
                                        <div class="flex space-x-2">
                                            <button type="button" onclick="editPage(<?php echo htmlspecialchars(json_encode($page), ENT_QUOTES, 'UTF-8'); ?>)" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm"><i class="fas fa-edit mr-1"></i> Засах</button>
                                            <a href="pages.php?book_id=<?php echo $book_id; ?>&action=delete_page&page_id=<?php echo $page['id']; ?>" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm" onclick="return confirm('Та энэ хуудсыг устгахдаа итгэлтэй байна уу?')"><i class="fas fa-trash mr-1"></i> Устгах</a>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 <?php echo !empty($page['image_url']) && !empty($page['content']) ? 'md:grid-cols-2' : ''; ?> gap-4 mt-2">
                                        <?php if (!empty($page['image_url'])): ?>
                                            <div><img src="../<?php echo htmlspecialchars($page['image_url']); ?>" alt="Хуудас <?php echo $page['page_number']; ?> зураг" class="w-full max-w-sm h-auto object-cover rounded border"></div>
                                        <?php endif; ?>
                                        <?php if (!empty($page['content'])): ?>
                                            <div class="bg-gray-50 p-3 rounded"><p class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($page['content']); ?></p></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500"><i class="fas fa-file-alt text-4xl mb-3"></i><p>Одоогоор хуудас байхгүй байна.</p></div>
                    <?php endif; ?>
                </div>

                <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                        <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-bold text-gray-800">Хуудас засах</h3><button type="button" onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">&times;</button></div>
                        <form action="pages.php?book_id=<?php echo $book_id; ?>" method="POST" id="editForm" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="edit_page">
                            <input type="hidden" name="page_id" id="edit_page_id">
                            <div class="mb-4">
                                <label for="edit_page_number" class="block text-gray-700 font-medium mb-2">Хуудасны дугаар *</label>
                                <input type="number" id="edit_page_number" name="page_number" min="1" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 font-medium mb-2">Одоогийн зураг</label>
                                <div id="current_image_container" class="mb-2"></div>
                            </div>
                            <div class="mb-4">
                                <label for="edit_page_image" class="block text-gray-700 font-medium mb-2">Шинэ зураг оруулах</label>
                                <input type="file" id="edit_page_image" name="page_image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div class="mb-4">
                                <label for="edit_page_content" class="block text-gray-700 font-medium mb-2">Хуудасны агуулга</label>
                                <textarea id="edit_page_content" name="page_content" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeEditModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">Цуцлах</button>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg"><i class="fas fa-save mr-1"></i> Хадгалах</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function editPage(page) {
            document.getElementById('edit_page_id').value = page.id;
            document.getElementById('edit_page_number').value = page.page_number;
            document.getElementById('edit_page_content').value = page.content;
            
            const imageContainer = document.getElementById('current_image_container');
            if (page.image_url) {
                imageContainer.innerHTML = `<img src="../${page.image_url}" alt="Одоогийн зураг" class="w-48 h-auto object-cover rounded border">`;
            } else {
                imageContainer.innerHTML = '<p class="text-gray-500 text-sm">Зураг байхгүй</p>';
            }
            
            document.getElementById('edit_page_image').value = '';
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }
    </script>
</body>
</html>