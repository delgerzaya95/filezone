<?php
// test_real_upload.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/s3_connect.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    
    // Алдаа гарсан эсэхийг шалгах
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = '<div style="color: red;">Файл хуулахад алдаа гарлаа. Error code: ' . $file['error'] . '</div>';
    } else {
        $s3 = get_s3_client();
        
        if ($s3) {
            $bucketName = 'filezone-bucket'; // Таны bucket нэр
            $fileName = 'test_uploads/' . time() . '_' . $file['name']; // Давхардахаас сэргийлж цаг нэмэв
            
            try {
                // S3 руу хуулах
                $result = $s3->putObject([
                    'Bucket'     => $bucketName,
                    'Key'        => $fileName,
                    'SourceFile' => $file['tmp_name'],
                    'ACL'        => 'public-read', // Туршилтын үед шууд үзэх боломжтойгоор
                ]);

                $url = $result['ObjectURL'];
                $message = '<div style="color: green; font-weight: bold;">АМЖИЛТТАЙ! Файл хуулагдлаа.</div>';
                $message .= '<div>Файлын зам: ' . $fileName . '</div>';
                $message .= '<div>Холбоос: <a href="' . $url . '" target="_blank">' . $url . '</a></div>';
                
            } catch (Exception $e) {
                $message = '<div style="color: red;">S3 Алдаа: ' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div style="color: red;">S3 Client үүсгэж чадсангүй.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <title>S3 Upload Test</title>
    <style>
        body { font-family: sans-serif; padding: 50px; }
        .box { border: 2px dashed #ccc; padding: 30px; text-align: center; max-width: 500px; margin: 0 auto; }
        input { margin: 20px 0; }
        button { background: blue; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: darkblue; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Scaleway S3 Upload Туршилт</h2>
        
        <?php if ($message): ?>
            <div style="margin-bottom: 20px; padding: 10px; background: #f0f0f0; border-radius: 5px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="test_file" required>
            <br>
            <button type="submit">Файл Хуулах</button>
        </form>
    </div>
</body>
</html>