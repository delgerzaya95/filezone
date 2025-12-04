<?php
// Ямар нэгэн санамсаргүй гаралт (echo, warning, whitespace) JSON-г эвдэхээс сэргийлж output buffer-г эхлүүлнэ.
ob_start();

// Алдааг дэлгэцэнд шууд хэвлэхийг хориглоно.
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Үндсэн хариуг урьдчилан бэлдэх
$response = ['success' => false, 'error' => 'Тодорхойгүй алдаа гарлаа.'];

// PHP-н warning, notice-г алдаа болгон барьж авах функц
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    session_start();

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Нэвтрэх шаардлагатай.');
    }

    require_once '../config.php';

    // Үлгэрийн төрлүүдийг энд зарлаж өгөх
    $story_types = [
        ['id' => 1, 'name' => 'Монгол ардын үлгэр'],
        ['id' => 2, 'name' => 'Монгол домог'],
        ['id' => 3, 'name' => 'Монгол зүйр цэцэн үг'],
        ['id' => 4, 'name' => 'Олон улсын үлгэр'],
        ['id' => 5, 'name' => 'Шилдэг хүүхдийн ном'],
        ['id' => 6, 'name' => 'Боловсролын ном'],
        ['id' => 7, 'name' => 'Адал явдалт түүх']
    ];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Буруу төрлийн хүсэлт байна.');
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Өгөгдлийн сантай холбогдож чадсангүй: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    // POST-оос ирсэн мэдээллийг авах
    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $age_group = isset($_POST['age_group']) ? $_POST['age_group'] : '';
    $type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
    $cover_image = isset($_POST['current_cover_image']) ? $_POST['current_cover_image'] : '';

    if ($book_id <= 0 || empty($title) || $type_id <= 0) {
        throw new Exception('Номын ID, нэр эсвэл төрөл хоосон байна.');
    }
    
    // type_id-аар type_name-г олох
    $type_name = '';
    foreach ($story_types as $type) {
        if ($type['id'] == $type_id) {
            $type_name = $type['name'];
            break;
        }
    }
    if (empty($type_name)) {
        throw new Exception('Буруу номын төрөл сонгогдсон байна.');
    }

    // Файл байршуулах хэсэг
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $book_folder_path = "../assets/preview/{$book_id}";

        // Хуучин зургийг устгах
        if (is_dir($book_folder_path)) {
            $files = glob($book_folder_path . '/*');
            foreach($files as $file){
                if(is_file($file)) {
                    @unlink($file);
                }
            }
        } else {
            @mkdir($book_folder_path, 0755, true);
        }
        
        if (!is_writable($book_folder_path)) { 
            throw new Exception("Сервер дээрх '{$book_folder_path}' хавтас руу бичих зөвшөөрөл алга."); 
        }
        
        // Шинэ зургийг хадгалах
        $file_extension = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        $new_file_name = $book_id . '.' . $file_extension;
        $file_path = $book_folder_path . '/' . $new_file_name;

        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $file_path)) {
            $cover_image = 'assets/preview/' . $book_id . '/' . $new_file_name;
        } else {
            throw new Exception("Шинэ зураг байршуулахад алдаа гарлаа.");
        }
    }

    // Өгөгдлийн санг шинэчлэх SQL (type_id, type_name нэмэгдсэн)
    $sql = "UPDATE books SET title = ?, description = ?, age_group = ?, cover_image = ?, type_id = ?, type_name = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('SQL Query бэлдэхэд алдаа гарлаа: ' . $conn->error);
    }

    $stmt->bind_param("ssssisi", $title, $description, $age_group, $cover_image, $type_id, $type_name, $book_id);

    if ($stmt->execute() === false) {
        throw new Exception('Өгөгдлийн санд мэдээллийг шинэчлэхэд алдаа гарлаа: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    $response = [
        'success' => true,
        'message' => 'Ном амжилттай шинэчлэгдлээ!',
        'book' => ['id' => $book_id, 'title' => $title, 'cover_image' => $cover_image]
    ];

} catch (Throwable $e) {
    $error_message = sprintf(
        "Алдааны мэдээлэл: %s (Файл: %s, Мөр: %s)",
        $e->getMessage(),
        basename($e->getFile()),
        $e->getLine()
    );
    $response['error'] = $error_message;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
ob_end_flush();