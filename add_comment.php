<?php
// add-comment.php
require_once 'includes/functions.php';

// Start session to access user information
session_start();

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Validate user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Та эхлээд нэвтэрнэ үү.";
    header("Location: login.php");
    exit();
}

// Get and sanitize input data
$file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$parent_comment_id = isset($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : null;

// Validate inputs
if ($file_id <= 0) {
    $_SESSION['error'] = "Файл олдсонгүй.";
    header("Location: index.php");
    exit();
}

if (empty($comment)) {
    $_SESSION['error'] = "Сэтгэгдэл хоосон байна.";
    header("Location: file-details.php?id=" . $file_id);
    exit();
}

// Check if the file exists
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

// If this is a reply, validate the parent comment exists
if ($parent_comment_id !== null) {
    $parent_check_sql = "SELECT id FROM comments WHERE id = ? AND file_id = ?";
    $parent_stmt = mysqli_prepare($conn, $parent_check_sql);
    mysqli_stmt_bind_param($parent_stmt, "ii", $parent_comment_id, $file_id);
    mysqli_stmt_execute($parent_stmt);
    $parent_result = mysqli_stmt_get_result($parent_stmt);

    if (mysqli_num_rows($parent_result) === 0) {
        $_SESSION['error'] = "Анхны сэтгэгдэл олдсонгүй.";
        header("Location: file-details.php?id=" . $file_id);
        exit();
    }
}

// Insert the comment into database
$insert_sql = "INSERT INTO comments (user_id, file_id, comment, parent_comment_id) 
               VALUES (?, ?, ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_sql);
mysqli_stmt_bind_param($insert_stmt, "iisi", 
    $_SESSION['user_id'], 
    $file_id, 
    $comment,
    $parent_comment_id
);

if (mysqli_stmt_execute($insert_stmt)) {
    $_SESSION['success'] = "Сэтгэгдэл амжилттай илгээгдлээ.";
} else {
    $_SESSION['error'] = "Сэтгэгдэл илгээхэд алдаа гарлаа: " . mysqli_error($conn);
}

mysqli_close($conn);

// Redirect back to the file details page
header("Location: file-details.php?id=" . $file_id . "#discussion");
exit();