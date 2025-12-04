<?php
// get_presigned_url.php
session_start();
require_once 'includes/functions.php';
require_once 'includes/s3_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Нэвтрэх шаардлагатай']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileName = $_POST['name'] ?? '';
    $fileType = $_POST['type'] ?? '';
    
    if (empty($fileName)) {
        echo json_encode(['error' => 'Файлын нэр буруу']);
        exit;
    }

    $s3 = get_s3_client();
    $bucketName = 'filezone-bucket';
    $userId = $_SESSION['user_id'];
    
    // Файлын өргөтгөл
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    // Шинэ нэр (Unique ID)
    $newFileName = uniqid('file_') . '.' . $ext;
    // S3 дээрх зам (Түр хавтас гэж байхгүй, шууд үндсэн зам руугаа орно)
    // Түр зуур 'uploads_staging' гэдэг хавтас руу хийж байгаад амжилттай болбол DB-д бүртгэе
    $key = 'files/' . $userId . '/' . $newFileName;

    try {
        // PUT хүсэлт хийх тусгай команд үүсгэх
        $cmd = $s3->getCommand('PutObject', [
            'Bucket' => $bucketName,
            'Key'    => $key,
            'ContentType' => $fileType,
            'ACL'    => 'private'
        ]);

        // Холбоос 20 минут хүчинтэй байна
        $request = $s3->createPresignedRequest($cmd, '+20 minutes');
        $presignedUrl = (string)$request->getUri();

        echo json_encode([
            'success' => true,
            'url' => $presignedUrl,
            'key' => $key, // Энийг дараа нь DB-д хадгална
            'filename' => $newFileName
        ]);

    } catch (Exception $e) {
        echo json_encode(['error' => 'S3 Холбоос үүсгэж чадсангүй: ' . $e->getMessage()]);
    }
}
?>