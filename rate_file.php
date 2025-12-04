<?php
require_once 'includes/functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Үнэлгээ өгөхийн тулд нэвтэрнэ үү.";
    header("Location: login.php");
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Validate inputs
$file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

if ($file_id <= 0 || $rating < 1 || $rating > 5) {
    $_SESSION['error'] = "Хүчингүй үнэлгээ.";
    header("Location: index.php");
    exit();
}

// Check if file exists
$conn = db_connect();
$file_check_sql = "SELECT id FROM files WHERE id = ?";
$file_stmt = mysqli_prepare($conn, $file_check_sql);
mysqli_stmt_bind_param($file_stmt, "i", $file_id);
mysqli_stmt_execute($file_stmt);
$file_result = mysqli_stmt_get_result($file_stmt);

if (mysqli_num_rows($file_result) === 0) {
    $_SESSION['error'] = "Файл олдсонгүй.";
    header("Location: index.php");
    exit();
}

// Check if user has already rated this file
$check_sql = "SELECT id FROM ratings WHERE file_id = ? AND user_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $file_id, $_SESSION['user_id']);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    // Update existing rating
    $update_sql = "UPDATE ratings SET rating = ?, rating_date = NOW() WHERE file_id = ? AND user_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "iii", $rating, $file_id, $_SESSION['user_id']);
    $success = mysqli_stmt_execute($update_stmt);
} else {
    // Insert new rating
    $insert_sql = "INSERT INTO ratings (file_id, user_id, rating) VALUES (?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "iii", $file_id, $_SESSION['user_id'], $rating);
    $success = mysqli_stmt_execute($insert_stmt);
}

if ($success) {
    $_SESSION['success'] = "Үнэлгээ амжилттай хадгалагдлаа.";
} else {
    $_SESSION['error'] = "Үнэлгээ хадгалахад алдаа гарлаа: " . mysqli_error($conn);
}

mysqli_close($conn);
header("Location: file-details.php?id=" . $file_id . "#history");
exit();
?>