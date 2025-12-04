<?php
session_start();
require_once 'db_connect.php';

$file_id = intval($_GET['file_id']);

try {
    $pdo = db_connect();
    
    $sql = "SELECT 
        fc.subcategory_id, 
        fc.child_category_id,
        c.id as category_id,
        c.name as category_name,
        sc.name as subcategory_name, 
        cc.name as child_category_name
    FROM file_categories fc
    LEFT JOIN subcategories sc ON fc.subcategory_id = sc.id
    LEFT JOIN categories c ON sc.category_id = c.id
    LEFT JOIN child_category cc ON fc.child_category_id = cc.id
    WHERE fc.file_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$file_id]);
    $data = $stmt->fetch();

    if ($data) {
        echo json_encode([
            'success' => true,
            'category_id' => $data['category_id'],
            'category_name' => $data['category_name'],
            'subcategory_id' => $data['subcategory_id'],
            'subcategory_name' => $data['subcategory_name'],
            'child_category_id' => $data['child_category_id'],
            'child_category_name' => $data['child_category_name']
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>