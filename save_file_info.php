<?php
// save_file_info.php
session_start();
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Нэвтрэх шаардлагатай']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db_connect();
    
    // JS-ээс ирсэн мэдээлэл
    $s3Key = $_POST['s3_key']; // S3 дээрх зам
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = floatval($_POST['price']);
    $categoryId = intval($_POST['category_id']);
    $subcategoryId = intval($_POST['subcategory_id']);
    $childCategoryId = intval($_POST['child_category_id']);
    $fileSize = intval($_POST['file_size']);
    $fileType = $_POST['file_type']; // pdf, docx гэх мэт
    
    $userId = $_SESSION['user_id'];

    // DB-д бичих
    $sql = "INSERT INTO files (user_id, category_id, title, description, file_type, file_size, price, file_url, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iissdids", $userId, $categoryId, $title, $description, $fileType, $fileSize, $price, $s3Key);
    
    if (mysqli_stmt_execute($stmt)) {
        $fileId = mysqli_insert_id($conn);
        
        // Ангилал холбох
        $catSql = "INSERT INTO file_categories (file_id, subcategory_id, child_category_id) VALUES (?, ?, ?)";
        $catStmt = mysqli_prepare($conn, $catSql);
        mysqli_stmt_bind_param($catStmt, "iii", $fileId, $subcategoryId, $childCategoryId);
        mysqli_stmt_execute($catStmt);

        echo json_encode(['success' => true, 'redirect' => 'profile.php']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($conn)]);
    }
}
?>