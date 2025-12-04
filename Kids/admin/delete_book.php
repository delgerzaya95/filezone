<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config.php';

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id > 0) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    // 1. Номны үндсэн зургийн замыг олох
    $stmt = $conn->prepare("SELECT cover_image FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($book = $result->fetch_assoc()) {
        $cover_image_path = '../' . $book['cover_image'];
        if (!empty($book['cover_image']) && file_exists($cover_image_path)) {
            unlink($cover_image_path); // Номны зургийг устгах
        }
    }
    $stmt->close();

    // 2. Номтой холбоотой бүх хуудасны зургийг устгах
    $pages_stmt = $conn->prepare("SELECT image_url FROM book_pages WHERE book_id = ?");
    $pages_stmt->bind_param("i", $book_id);
    $pages_stmt->execute();
    $pages_result = $pages_stmt->get_result();
    while ($page = $pages_result->fetch_assoc()) {
        $page_image_path = '../' . $page['image_url'];
        if (!empty($page['image_url']) && file_exists($page_image_path)) {
            unlink($page_image_path); // Хуудасны зургийг устгах
        }
    }
    $pages_stmt->close();

    // Номны ID-аар үүсгэгдсэн хавтасыг устгах
    $book_folder_path = "../assets/preview/{$book_id}";
    if (is_dir($book_folder_path)) {
        // Хавтас доторх файлуудыг шалгаад устгах
        $files = glob($book_folder_path . '/*');
        foreach($files as $file){
            if(is_file($file)) {
                unlink($file);
            }
        }
        rmdir($book_folder_path);
    }

    // 3. Холбоотой хуудаснуудыг мэдээллийн сангаас устгах
    $delete_pages_stmt = $conn->prepare("DELETE FROM book_pages WHERE book_id = ?");
    $delete_pages_stmt->bind_param("i", $book_id);
    $delete_pages_stmt->execute();
    $delete_pages_stmt->close();

    // 4. Номыг өөрийг нь мэдээллийн сангаас устгах
    $delete_book_stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
    $delete_book_stmt->bind_param("i", $book_id);
    $delete_book_stmt->execute();
    $delete_book_stmt->close();

    $conn->close();
}

// books.php хуудас руу буцах
header("Location: books.php");
exit();
?>