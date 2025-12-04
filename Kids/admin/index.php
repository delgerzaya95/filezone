<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once '../config.php';

// Өгөгдлийн сантай холбогдох
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Холболт амжилтгүй: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4"); // <-- ЭНЭ МӨРИЙГ НЭМЭЭРЭЙ

// Хянах самбарт тоонуудыг авах
$books_count = $conn->query("SELECT COUNT(*) FROM books")->fetch_row()[0];
$pages_count = $conn->query("SELECT COUNT(*) FROM book_pages")->fetch_row()[0];
$users_count = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];

// Сүүлийн нэмэгдсэн номнууд - БҮХ МЭДЭЭЛЛИЙГ НЬ АВДАГ БОЛГОЖ ӨӨРЧЛӨВ
$recent_books_result = $conn->query("SELECT * FROM books ORDER BY created_at DESC LIMIT 5");
$recent_books = [];
if ($recent_books_result) {
    while($row = $recent_books_result->fetch_assoc()) {
        $recent_books[] = $row;
    }
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хянах Самбар | Filezone Kids Админ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <div class="bg-gray-800 text-white w-64 min-h-screen">
            <div class="p-4 border-b border-gray-700">
                <h1 class="text-xl font-bold">
                    <i class="fas fa-book-open mr-2"></i> Filezone Kids
                </h1>
                <p class="text-sm text-gray-400">Админ Хянах Самбар</p>
            </div>
            <nav class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="index.php" class="flex items-center space-x-2 bg-gray-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Хянах Самбар</span>
                        </a>
                    </li>
                    <li>
                        <a href="books.php" class="flex items-center space-x-2 text-gray-300 hover:bg-gray-700 hover:text-white px-4 py-2 rounded-lg">
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
                <h1 class="text-2xl font-bold text-gray-800">Хянах Самбар</h1>
                <div class="text-sm text-gray-500">
                    Тавтай морил, <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Админ'; ?>!
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Нийт Номнууд</p>
                            <h3 class="text-2xl font-bold"><?php echo $books_count; ?></h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-book text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="books.php" class="text-blue-600 hover:text-blue-800 text-sm mt-2 inline-block">Бүгдийг харах</a>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Нийт Хуудаснууд</p>
                            <h3 class="text-2xl font-bold"><?php echo $pages_count; ?></h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-file-alt text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="pages.php" class="text-blue-600 hover:text-blue-800 text-sm mt-2 inline-block">Бүгдийг харах</a>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Хэрэглэгчид</p>
                            <h3 class="text-2xl font-bold"><?php echo $users_count; ?></h3>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-users text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                    <a href="users.php" class="text-blue-600 hover:text-blue-800 text-sm mt-2 inline-block">Бүгдийг харах</a>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Сүүлийн Нэмэгдсэн Номнууд</h2>
                    <a href="books.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-plus mr-1"></i> Шинэ Ном Нэмэх
                    </a>
                </div>

                <?php if (!empty($recent_books)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <?php foreach($recent_books as $book): ?>
                            <div id="book-card-<?php echo $book['id']; ?>" class="border rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                <img src="../<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                        alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                        class="w-full h-40 object-cover book-cover"
                                        onerror="this.src='../assets/images/default-book-cover.jpg'">
                                <div class="p-3">
                                    <h3 class="font-medium text-gray-800 truncate book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <div class="flex justify-between items-center mt-2">
                                        <span class="text-xs text-gray-500">ID: <?php echo $book['id']; ?></span>
                                        <?php $book_json = htmlspecialchars(json_encode($book), ENT_QUOTES, 'UTF-8'); ?>
                                        <button onclick='openEditModal(<?php echo $book_json; ?>)' class="text-blue-600 hover:text-blue-800 text-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-book-open text-4xl mb-3"></i>
                        <p>Ямар ч ном олдсонгүй. Эхний номоо нэмнэ үү!</p>
                    </div>
                <?php endif; ?>
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
                        <p class="text-xs text-gray-500 mt-1">Зургийг солих бол шинэ зураг сонгоно уу.</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-5 py-2 rounded-lg">Цуцлах</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">
                        <i class="fas fa-save mr-1"></i> Хадгалах
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const editModal = document.getElementById('editBookModal');
        const editForm = document.getElementById('editBookForm');

        function openEditModal(book) {
            // Формын талбаруудыг номны мэдээллээр дүүргэх
            document.getElementById('edit_book_id').value = book.id;
            document.getElementById('edit_title').value = book.title;
            document.getElementById('edit_description').value = book.description;
            document.getElementById('edit_age_group').value = book.age_group;
            document.getElementById('edit_type_id').value = book.type_id; // <-- ЭНЭ МӨРИЙГ НЭМЭЭРЭЙ
            document.getElementById('edit_current_cover_image').value = book.cover_image;
            document.getElementById('edit_cover_preview').src = `../${book.cover_image}`;
            
            // Modal-г харуулах
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
        }

        function closeEditModal() {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        }

        editForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Хуудсыг дахин ачаалуулахаас сэргийлэх

            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Хадгалж байна...';
            
            fetch('edit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeEditModal();
                    
                    // Хуудсан дээрх номны мэдээллийг шинэчлэх
                    const bookCard = document.getElementById(`book-card-${data.book.id}`);
                    if(bookCard) {
                        bookCard.querySelector('.book-title').textContent = data.book.title;
                        bookCard.querySelector('.book-cover').src = `../${data.book.cover_image}`;
                    }

                } else {
                    alert('Алдаа: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Системийн алдаа гарлаа.');
            })
            .finally(() => {
                // Товчийг буцааж идэвхжүүлэх
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-save mr-1"></i> Хадгалах';
            });
        });

        // ESC товчоор modal хаах
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !editModal.classList.contains('hidden')) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>