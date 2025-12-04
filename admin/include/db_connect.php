<?php
// include/db_connect.php
function db_connect() {
    $host = 'localhost';
    $db   = 'filezone_mn';
    $user = 'filezone_mn';
    $pass = '099da7e85a2688';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}

// mysqli холболт бас үүсгэх
function mysqli_connect_db() {
    $host = 'localhost';
    $username = 'filezone_mn';
    $password = 'Filezone.mn@2025';
    $dbname = 'filezone_mn';
    
    $conn = mysqli_connect($host, $username, $password, $dbname);
    mysqli_set_charset($conn, "utf8mb4");
    return $conn;
}
?>