<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config.php';

// PRG Загвар: Өмнөх POST хүсэлтээс session-д хадгалсан мэдээллийг шалгах
$error = '';
if (isset($_SESSION['form_error'])) {
    $error = $_SESSION['form_error'];
    unset($_SESSION['form_error']);
}
$success = '';
if (isset($_SESSION['form_success'])) {
    $success = $_SESSION['form_success'];
    unset($_SESSION['form_success']);
}
// "Хуудас нэмэх" холбоосонд ашиглах номны ID-г session-оос авах
$book_id_for_redirect = null;
if (isset($_SESSION['last_book_id'])) {
    $book_id_for_redirect = $_SESSION['last_book_id'];
    unset($_SESSION['last_book_id']);
}


// Үлгэрийн төрлүүд
$story_types = [
    ['id' => 1, 'name' => 'Монгол ардын үлгэр'],
    ['id' => 2, 'name' => 'Монгол домог'],
    ['id' => 3, 'name' => 'Монгол зүйр цэцэн үг'],
    ['id' => 4, 'name' => 'Олон улсын үлгэр'],
    ['id' => 5, 'name' => 'Шилдэг хүүхдийн ном'],
    ['id' => 6, 'name' => 'Боловсролын ном'],
    ['id' => 7, 'name' => 'Адал явдалт түүх'],
    ['id' => 8, 'name' => 'Гадаад үлгэр']
];

// Form илгээгдсэн үед ажиллах хэсэг
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Холболт амжилтгүй: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $age_group = $_POST['age_group'];
    $type_id = $_POST['type_id'];
    $type_name = '';
    $temp_error = '';
    
    foreach ($story_types as $type) {
        if ($type['id'] == $type_id) {
            $type_name = $type['name'];
            break;
        }
    }

    // 1. Файл орж ирсэн эсэхийг шалгах
    if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) {
        $temp_error = 'Номны зургийг заавал оруулна уу.';
    } else {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['cover_image']['type'], $allowed_types)) {
            $temp_error = 'Зөвхөн JPG, PNG, GIF, WEBP форматын зураг оруулна уу';
        }
    }
    
    if (empty($title)) {
        $temp_error = "Номын нэрийг оруулна уу";
    }

    // Алдаагүй бол үргэлжлүүлнэ
    if (empty($temp_error)) {
        $slug = generateSlug($title);
        
        $check_slug_stmt = $conn->prepare("SELECT id FROM books WHERE slug = ?");
        $check_slug_stmt->bind_param("s", $slug);
        $check_slug_stmt->execute();
        $check_slug_stmt->store_result();
        
        if ($check_slug_stmt->num_rows > 0) {
            $counter = 1;
            $original_slug = $slug;
            do {
                $slug = $original_slug . '-' . $counter;
                $check_slug_stmt->bind_param("s", $slug);
                $check_slug_stmt->execute();
                $check_slug_stmt->store_result();
                $counter++;
            } while ($check_slug_stmt->num_rows > 0 && $counter < 100);
        }
        $check_slug_stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO books (title, slug, cover_image, description, type_id, type_name, age_group) VALUES (?, ?, '', ?, ?, ?, ?)");
        $stmt->bind_param("sssiss", $title, $slug, $description, $type_id, $type_name, $age_group);
        
        if ($stmt->execute()) {
            $book_id = $stmt->insert_id;

            $file_extension = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $new_file_name = $book_id . '.' . $file_extension;
            $upload_dir = '../assets/preview/' . $book_id . '/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $file_path)) {
                $cover_image_db_path = 'assets/preview/' . $book_id . '/' . $new_file_name;
                
                $update_stmt = $conn->prepare("UPDATE books SET cover_image = ? WHERE id = ?");
                $update_stmt->bind_param("si", $cover_image_db_path, $book_id);
                if ($update_stmt->execute()) {
                    $_SESSION['form_success'] = "Ном амжилттай нэмэгдлээ!";
                    $_SESSION['last_book_id'] = $book_id; // Амжилттай болсон номны ID-г session-д хадгалах
                } else {
                    $temp_error = "Зургийн замыг хадгалахад алдаа гарлаа: " . $update_stmt->error;
                    $conn->query("DELETE FROM books WHERE id = $book_id");
                }
                $update_stmt->close();
            } else {
                $temp_error = 'Файл байршуулахад алдаа гарлаа';
                $conn->query("DELETE FROM books WHERE id = $book_id");
            }
        } else {
            $temp_error = "Алдаа гарлаа: " . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();

    // Алдаа гарсан бол алдааг session-д хадгалах
    if (!empty($temp_error)) {
        $_SESSION['form_error'] = $temp_error;
    }

    // Хуудсыг дахин ачаалж, GET хүсэлт болгох
    header("Location: books.php");
    exit();
}

function generateSlug($title) {
    $cyrillic = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я','А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'];
    $latin = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','kh','ts','ch','sh','shch','','y','','e','yu','ya','a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','kh','ts','ch','sh','shch','','y','','e','yu','ya'];
    $title = str_replace($cyrillic, $latin, $title);
    $slug = preg_replace('/[^a-zA-Z0-9]+/i', '-', $title);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    if (empty($slug)) {
        $slug = 'book-' . uniqid();
    }
    return $slug;
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Номнууд | FileZone Kids Админ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <div class="bg-gray-800 text-white w-64 min-h-screen">
            <div class="p-4 border-b border-gray-700">
                <h1 class="text-xl font-bold">
                    <i class="fas fa-book-open mr-2"></i> FileZone Kids
                </h1>
                <p class="text-sm text-gray-400">Админ Хянах Самбар</p>
            </div>
            <nav class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="index.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Хянах самбар</span>
                        </a>
                    </li>
                    <li>
                        <a href="books.php" class="flex items-center space-x-2 bg-gray-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-book"></i>
                            <span>Номнууд</span>
                        </a>
                    </li>
                    <li>
                        <a href="pages.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-file-alt"></i>
                            <span>Хуудаснууд</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-users"></i>
                            <span>Хэрэглэгчид</span>
                        </a>
                    </li>
                    <li class="pt-4 mt-4 border-t border-gray-700">
                        <a href="../includes/logout.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Гарах</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="flex-1 p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Шинэ ном нэмэх</h1>
                <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Буцах
                </a>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                    <?php if (isset($book_id_for_redirect)): ?>
                        <a href="pages.php?book_id=<?php echo $book_id_for_redirect; ?>" class="ml-4 bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                            Хуудаснууд нэмэх <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
                <div class="mb-6">
                    <label for="title" class="block text-gray-700 font-medium mb-2">Номын нэр *</label>
                    <input type="text" id="title" name="title" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Номын нэрийг оруулна уу">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="age_group" class="block text-gray-700 font-medium mb-2">Насны ангилал *</label>
                        <select id="age_group" name="age_group" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Сонгох</option>
                            <option value="4-7">4-7 нас</option>
                            <option value="8-12">8-12 нас</option>
                            <option value="all">Бүх насны</option>
                        </select>
                    </div>
                    <div>
                        <label for="type_id" class="block text-gray-700 font-medium mb-2">Үлгэрийн төрөл *</label>
                        <select id="type_id" name="type_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Сонгох</option>
                            <?php foreach ($story_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-6">
                    <label for="cover_image" class="block text-gray-700 font-medium mb-2">Номны зураг *</label>
                    <input type="file" id="cover_image" name="cover_image" accept="image/*" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div class="mb-6">
                    <label for="description" class="block text-gray-700 font-medium mb-2">Номын тайлбар *</label>
                    <textarea id="description" name="description" rows="4" required
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                              placeholder="Номын тайлбарыг оруулна уу"></textarea>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                        <i class="fas fa-save mr-1"></i> Ном нэмэх
                    </button>
                </div>
            </form>

            <div class="bg-white rounded-lg shadow p-6 mt-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Одоо байгаа номнууд</h2>
                <?php
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if (!$conn->connect_error) {
                    $conn->set_charset("utf8mb4");
                    $books_result = $conn->query("SELECT * FROM books ORDER BY created_at DESC");
                    
                    if ($books_result && $books_result->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-700">
                                <thead class="bg-gray-100 text-gray-700 uppercase">
                                    <tr>
                                        <th class="px-4 py-3">Зураг</th>
                                        <th class="px-4 py-3">Номын нэр</th>
                                        <th class="px-4 py-3">Насны ангилал</th>
                                        <th class="px-4 py-3">Үйлдэл</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($book = $books_result->fetch_assoc()): ?>
                                        <tr id="book-row-<?php echo $book['id']; ?>" class="border-b hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <img src="../<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                                     class="w-12 h-16 object-cover rounded book-cover">
                                            </td>
                                            <td class="px-4 py-3 font-medium book-title">
                                                <?php echo htmlspecialchars($book['title']); ?>
                                            </td>
                                            <td class="px-4 py-3 book-age-group">
                                                <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                                    <?php echo htmlspecialchars($book['age_group']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex space-x-2">
                                                    <a href="pages.php?book_id=<?php echo $book['id']; ?>" class="bg-blue-600 text-white px-3 py-1 rounded text-sm"><i class="fas fa-plus mr-1"></i> Хуудас</a>
                                                    <?php $book_json = htmlspecialchars(json_encode($book), ENT_QUOTES, 'UTF-8'); ?>
                                                    <button onclick='openEditModal(<?php echo $book_json; ?>)' class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm"><i class="fas fa-edit mr-1"></i> Засах</button>
                                                    <a href="delete_book.php?id=<?php echo $book['id']; ?>" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm" onclick="return confirm('Энэ номтой холбоотой бүх хуудас, зургууд устгагдана! Та итгэлтэй байна уу?')"><i class="fas fa-trash mr-1"></i> Устгах</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center py-8 text-gray-500">Одоогоор ном байхгүй байна.</p>
                    <?php endif;
                    $conn->close();
                }
                ?>
            </div>
        </div>
    </div>
    
    <div id="editBookModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-xl font-bold text-gray-800">Ном засах</h3>
                <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form id="editBookForm">
                <input type="hidden" name="book_id" id="edit_book_id">
                <input type="hidden" name="current_cover_image" id="edit_current_cover_image">
                <div class="space-y-4">
                    <div>
                        <label for="edit_title" class="block text-gray-700 font-medium mb-1">Номын нэр *</label>
                        <input type="text" id="edit_title" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_age_group" class="block text-gray-700 font-medium mb-1">Насны ангилал *</label>
                            <select id="edit_age_group" name="age_group" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="4-7">4-7 нас</option>
                                <option value="8-12">8-12 нас</option>
                                <option value="all">Бүх насны</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit_type_id" class="block text-gray-700 font-medium mb-1">Үлгэрийн төрөл *</label>
                            <select id="edit_type_id" name="type_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <?php foreach ($story_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="edit_description" class="block text-gray-700 font-medium mb-1">Номын тайлбар *</label>
                        <textarea id="edit_description" name="description" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Номны зураг</label>
                        <img id="edit_cover_preview" src="" alt="Cover" class="w-24 h-32 object-cover rounded border mb-2">
                        <input type="file" id="edit_cover_image" name="cover_image" accept="image/*" class="w-full text-sm">
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-5 py-2 rounded-lg">Цуцлах</button>
                    <button type="button" id="editBookSubmitButton" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg"><i class="fas fa-save mr-1"></i> Хадгалах</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const editModal = document.getElementById('editBookModal');
        const editForm = document.getElementById('editBookForm');

        function openEditModal(book) {
            document.getElementById('edit_book_id').value = book.id;
            document.getElementById('edit_title').value = book.title;
            document.getElementById('edit_description').value = book.description;
            document.getElementById('edit_age_group').value = book.age_group;
            document.getElementById('edit_type_id').value = book.type_id; // ЭНЭ МӨР НЭМЭГДСЭН
            document.getElementById('edit_current_cover_image').value = book.cover_image;
            document.getElementById('edit_cover_preview').src = `../${book.cover_image}`;
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
        }

        function closeEditModal() {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        }

        document.getElementById('editBookSubmitButton').addEventListener('click', function() {
            const editForm = document.getElementById('editBookForm');
            const formData = new FormData(editForm);
            const submitButton = this; // 'this' нь уг товчийг заана

            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Хадгалж байна...';

            fetch('edit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload(); 
                } else {
                    alert('Алдаа: ' + (data.error || 'Тодорхойгүй алдаа гарлаа.'));
                }
            })
            .catch(error => {
                alert('Системийн алдаа гарлаа. Дэлгэрэнгүйг console хэсгээс харна уу.');
                console.error('Fetch Error:', error);
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-save mr-1"></i> Хадгалах';
            });
        });
    </script>
</body>
</html>