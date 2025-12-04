<?php
// setup_cors.php - Үүнийг нэг удаа ажиллуулахад хангалттай
require_once 'includes/s3_connect.php';

$s3 = get_s3_client();
$bucketName = 'filezone-bucket'; // Таны bucket нэр

try {
    $result = $s3->putBucketCors([
        'Bucket' => $bucketName,
        'CORSConfiguration' => [
            'CORSRules' => [
                [
                    'AllowedHeaders' => ['*'],
                    'AllowedMethods' => ['GET', 'PUT', 'POST', 'HEAD'],
                    'AllowedOrigins' => ['*'], // Эсвэл 'https://www.filezone.mn' гэж тодорхой зааж болно
                    'ExposeHeaders'  => ['ETag'],
                    'MaxAgeSeconds'  => 3000
                ],
            ],
        ],
    ]);
    echo "CORS тохиргоо амжилттай хийгдлээ!";
} catch (Aws\Exception\AwsException $e) {
    echo "Алдаа гарлаа: " . $e->getMessage();
}
?>