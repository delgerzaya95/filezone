<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'filezone_kids');
define('DB_PASS', 'Filezone.mn@2025');
define('DB_NAME', 'filezone_kids');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Мэдээллийн сантай холбогдох функц
 * @return mysqli|null
 */
function db_connect_kids() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // Die silently in production, or log error
        // die("Kids DB Connection Failed: " . $conn->connect_error);
        return null;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Зочны 'visit' үйлдлийг бүртгэх функц
 * Энэ нь тухайн IP хаягнаас тухайн өдөр орсон анхны хандалтыг л бүртгэнэ.
 */
function log_visitor() {
    // Хэрэв энэ session-д аль хэдийн бүртгэсэн бол дахин ажиллахгүй
    if (isset($_SESSION['visitor_logged_today']) && $_SESSION['visitor_logged_today'] === date('Y-m-d')) {
        return;
    }

    $conn = db_connect_kids();
    if (!$conn) return;

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $today_date = date('Y-m-d');

    // Энэ IP хаягаар өнөөдөр 'visit' бүртгэгдсэн эсэхийг шалгах
    $stmt = $conn->prepare("SELECT id FROM visitor_activity WHERE ip_address = ? AND activity_type = 'visit' AND DATE(activity_timestamp) = ? LIMIT 1");
    $stmt->bind_param("ss", $ip_address, $today_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Хэрэв бүртгэл байхгүй бол шинээр нэмэх
        $insert_stmt = $conn->prepare("INSERT INTO visitor_activity (ip_address, user_agent, activity_type) VALUES (?, ?, 'visit')");
        $insert_stmt->bind_param("ss", $ip_address, $user_agent);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    $stmt->close();
    $conn->close();

    // Session-д бүртгэснийг тэмдэглэх
    $_SESSION['visitor_logged_today'] = $today_date;
}

// Хуудас болгонд зочныг бүртгэх функцийг дуудах
log_visitor();

?>