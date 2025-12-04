<?php
session_start();
require_once 'db_connect.php';

$subcategory_id = intval($_GET['subcategory_id']);

try {
    $pdo = db_connect();
    
    $sql = "SELECT id, name FROM child_category WHERE subcategory_id = ? ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subcategory_id]);
    $child_categories = $stmt->fetchAll();

    echo json_encode($child_categories);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>