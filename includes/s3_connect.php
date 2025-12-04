<?php
// includes/s3_connect.php

// Composer-ийн autoload-ийг дуудах (Зам нь зөв эсэхийг шалгаарай)
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function get_s3_client() {
    // Scaleway тохиргоо (Эдгээрийг өөрийнхөөрөө солиорой)
    $accessKey  = 'SCW7XEM4MJQ8XCB1ZNG3';
    $secretKey  = 'bb735182-b48a-4da5-a9c9-299d1949551f';
    $bucketName = 'filezone-bucket'; // Таны үүсгэсэн Bucket нэр
    $region     = 'fr-par';          // Region (Жишээ нь: fr-par, nl-ams)

    try {
        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => $region,
            'endpoint' => "https://s3.$region.scw.cloud",
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => $accessKey,
                'secret' => $secretKey,
            ],
        ]);
        return $s3;
    } catch (AwsException $e) {
        error_log("S3 Connection Error: " . $e->getMessage());
        return null;
    }
}
?>