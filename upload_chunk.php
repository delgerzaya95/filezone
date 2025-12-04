<?php
// upload_chunk.php
header('Content-Type: text/plain'); 

// Тохиргоо
$temp_dir = 'uploads/temp/';
if (!is_dir($temp_dir)) mkdir($temp_dir, 0755, true);

// Resumable.js-ээс ирэх параметрүүд
$chunkNumber = isset($_POST['resumableChunkNumber']) ? intval($_POST['resumableChunkNumber']) : 0;
$totalChunks = isset($_POST['resumableTotalChunks']) ? intval($_POST['resumableTotalChunks']) : 0;
$identifier = isset($_POST['resumableIdentifier']) ? preg_replace('/[^a-zA-Z0-9._-]/', '', $_POST['resumableIdentifier']) : '';
$filename = isset($_POST['resumableFilename']) ? $_POST['resumableFilename'] : 'unknown_file';

// Validation
if ($chunkNumber < 1 || $totalChunks < 1 || empty($identifier)) {
    http_response_code(400);
    die('Invalid parameters');
}

// Хэсэг файлын нэр (Part file)
$partFilePath = $temp_dir . $identifier . '.part' . $chunkNumber;

// 1. Хэсэг файлыг хүлээж авах
if (!empty($_FILES) && !empty($_FILES['file']['tmp_name'])) {
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        die('Upload error code: ' . $_FILES['file']['error']);
    }
    
    // Хэсэг файлыг хадгалах
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $partFilePath)) {
        http_response_code(500);
        die('Failed to save chunk');
    }
}

// 2. Бүх хэсэг ирсэн эсэхийг шалгах
$allChunksUploaded = true;
for ($i = 1; $i <= $totalChunks; $i++) {
    if (!file_exists($temp_dir . $identifier . '.part' . $i)) {
        $allChunksUploaded = false;
        break;
    }
}

// 3. БҮХ ХЭСЭГ ИРСЭН БОЛ ЭВЛҮҮЛЭХ (MERGE)
if ($allChunksUploaded) {
    // Temp folder дотор эцсийн файлыг үүсгэнэ.
    // Нэрийг нь identifier-аар үүсгэх нь найдвартай (давхардал үүсэхгүй)
    $finalTempFileName = $identifier . '_merged'; 
    $finalTempPath = $temp_dir . $finalTempFileName;

    // Хэрэв файл аль хэдийн үүссэн бол дахин эвлүүлэхгүй (Race condition-оос сэргийлэх)
    if (!file_exists($finalTempPath)) {
        
        $outFile = fopen($finalTempPath, "wb"); // Write Binary горим
        if ($outFile) {
            for ($i = 1; $i <= $totalChunks; $i++) {
                $partPath = $temp_dir . $identifier . '.part' . $i;
                $partFile = fopen($partPath, "rb"); // Read Binary горим
                
                if ($partFile) {
                    while ($buff = fread($partFile, 4096)) {
                        fwrite($outFile, $buff);
                    }
                    fclose($partFile);
                    // Хэсгийг устгах (Temp цэвэрлэх)
                    unlink($partPath); 
                } else {
                    // Хэсэг уншиж чадаагүй бол
                    fclose($outFile);
                    http_response_code(500);
                    die('Failed to open chunk ' . $i);
                }
            }
            fclose($outFile);
        } else {
            http_response_code(500);
            die('Failed to create merged file');
        }
    }
    
    // JS руу эвлүүлсэн файлын нэрийг буцаана (зөвхөн нэрийг)
    echo $finalTempFileName;
}
?>