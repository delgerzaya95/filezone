<?php
session_start();
require_once 'db_connect.php';

$category_id = intval($_GET['category_id']);

try {
    $pdo = db_connect();
    
    $sql = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$category_id]);
    $subcategories = $stmt->fetchAll();

    echo json_encode($subcategories);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>