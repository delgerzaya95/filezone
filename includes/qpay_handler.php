<?php
require_once 'qpay_config.php';
// Мэдээллийн сантай ажиллах функцүүдийг дуудах шаардлагатай
require_once 'functions.php'; 

// QPay-с Authentication token авах функц
function get_qpay_token() {
    $url = QPAY_API_URL . 'auth/token';
    $credentials = base64_encode(QPAY_CLIENT_ID . ':' . QPAY_CLIENT_SECRET);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentials
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('QPay token error: ' . curl_error($ch));
        return null;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

// Файл худалдан авах нэхэмжлэх үүсгэх
function create_qpay_invoice($file, $user) {
    $token = get_qpay_token();
    if (!$token) {
        return ['error' => 'Token авч чадсангүй.'];
    }

    $url = QPAY_API_URL . 'invoice';

    // ---> ЗАСВАРЛАСАН ХЭСЭГ (1) <---
    // 1. Уникаль дугаарыг ЭХЭЛЖ үүсгэнэ
    $sender_invoice_no = 'filezone-' . $user['id'] . '-' . $file['id'] . '-' . time();
    $amount = $file['price'];
    
    // 2. Гүйлгээний утгандаа уникаль дугаараа НЭМЖ өгнө
    $description = $file['title'] . ' (ID: ' . $sender_invoice_no . ')';
    // ---> ЗАСВАР ДУУСАВ <---

    $post_data = [
        'invoice_code' => QPAY_INVOICE_CODE,
        'sender_invoice_no' => $sender_invoice_no,
        'invoice_receiver_code' => (string)$user['id'], 
        'invoice_description' => $description, // Шинэчилсэн гүйлгээний утга
        'amount' => $amount,
        'callback_url' => QPAY_CALLBACK_URL . '?invoice_id=' . $sender_invoice_no . '&user_id=' . $user['id'] . '&file_id=' . $file['id'],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('QPay invoice error: ' . curl_error($ch));
        return ['error' => 'Нэхэмжлэх үүсгэхэд алдаа гарлаа.'];
    }
    curl_close($ch);

    $result = json_decode($response, true);
    
    // Хэрэв амжилттай болсон бол sender_invoice_no-г хариунд нэмнэ
    if (is_array($result) && !isset($result['error'])) {
        $result['sender_invoice_no'] = $sender_invoice_no;
    }
    
    return $result;
}

// deposit.php-д зориулсан QPay invoice үүсгэх функц
function create_qpay_deposit_invoice($deposit_data, $user) {
    $token = get_qpay_token();
    if (!$token) {
        return ['error' => 'Token авч чадсангүй.'];
    }

    $url = QPAY_API_URL . 'invoice';

    // ---> ЗАСВАРЛАСАН ХЭСЭГ (2) <---
    // 1. Уникаль дугаарыг ЭХЭЛЖ үүсгэнэ
    $sender_invoice_no = 'FZ' . date('Ymd') . $user['id'] . time();
    $amount = $deposit_data['price'];
    
    // 2. Гүйлгээний утгандаа уникаль дугаараа НЭМЖ өгнө
    $description = 'Данс цэнэглэлт. User: ' . $user['username'] . '. Invoice: ' . $sender_invoice_no;
    // ---> ЗАСВАР ДУУСАВ <---
    
    $note = implode(' | ', [
        'Хэрэглэгч: ' . $user['username'] . ' (ID: ' . $user['id'] . ')',
        'Цэнэглэх дүн: ' . number_format($amount) . ' MNT',
        'Огноо: ' . date('Y-m-d H:i:s'),
        'Үйлчилгээ: FileZone.mn',
        'Тайлбар: Веб сайтын баланс цэнэглэлт',
        'InvoiceID: ' . $sender_invoice_no // Note-д бас нэмэхэд илүүдэхгүй
    ]);

    $post_data = [
        'invoice_code' => QPAY_INVOICE_CODE,
        'sender_invoice_no' => $sender_invoice_no,
        'invoice_receiver_code' => 'FZ_CUST_' . $user['id'],
        'invoice_description' => $description, // Шинэчилсэн гүйлгээний утга
        'amount' => $amount,
        'callback_url' => QPAY_CALLBACK_URL . '?type=deposit&invoice_id=' . $sender_invoice_no . '&user_id=' . $user['id'] . '&amount=' . $amount,
        'note' => $note,
        'sender_branch_code' => 'FILEZONE_WEB',
        'sender_register_no' => 'FZ_USER_' . $user['id']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log('cURL Error: ' . curl_error($ch));
        error_log('Verbose: ' . $verboseLog);
        curl_close($ch);
        return ['error' => 'Холболтын алдаа: ' . curl_error($ch)];
    }
    
    curl_close($ch);
    $result = json_decode($response, true);
    error_log('QPay Response: ' . print_r($result, true));
    
    if (isset($result['error_code'])) {
        return ['error' => 'QPay алдаа (' . $result['error_code'] . '): ' . ($result['error_message'] ?? 'Тодорхойгүй алдаа')];
    }
    
    if (!isset($result['invoice_id'])) {
        return ['error' => 'Нэхэмжлэх ID авч чадсангүй. QPay хариу: ' . $response];
    }

    $result['sender_invoice_no'] = $sender_invoice_no;
    
    return $result;
}
/**
 * ===================================================================
 * ШИНЭЧИЛСЭН ФУНКЦ (АЮУЛГҮЙ БАЙДАЛ) - ИЛҮҮ НАЙДВАРТАЙ АРГА
 * ===================================================================
 * QPay-н нэхэмжлэхийг ШУУД ID-Г НЬ АШИГЛАН шалгах.
 *
 * @param string $qpay_invoice_id QPay-с үүсгэсэн (UUID) нэхэмжлэхийн ID
 * @return array ['status' => 'PAID'|'PENDING'|'ERROR', 'message' => '...']
 */
function check_qpay_payment_status($qpay_invoice_id) {
    $token = get_qpay_token();
    if (!$token) {
        error_log("Check Status Error: Could not get token.");
        return ['status' => 'ERROR', 'message' => 'Token авч чадсангүй. (Config шалгана уу)'];
    }

    // Таны config-д QPAY_API_URL нь / -ээр төгссөн байх ёстойг анхаарна уу!
    // Жишээ нь: 'https://api.qpay.mn/v2/'
    $url = QPAY_API_URL . 'invoice/' . $qpay_invoice_id;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPGET, 1); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        error_log('QPay check (invoice/get) cURL error: ' . $error);
        curl_close($ch);
        return ['status' => 'ERROR', 'message' => 'cURL Error: ' . $error];
    }
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data === null) {
        error_log("QPay Check Error: Invalid JSON response. Response: " . $response);
        return ['status' => 'ERROR', 'message' => 'QPay-с буруу хариу ирлээ (JSON).'];
    }

    if (isset($data['error_code'])) {
         error_log("QPay Check API Error: " . ($data['error_message'] ?? $response));
         return ['status' => 'ERROR', 'message' => 'QPay API Error: ' . ($data['error_message'] ?? 'Unknown')];
    }

    // =======================================================
    //  *** ЛОГИКИЙН ЗАСВАР (Таны логт үндэслэв) ***
    // =======================================================

    // 1. "payments" массив дотор "PAID" статус байгаа эсэхийг шалгах (Хамгийн найдвартай)
    if (isset($data['payments']) && is_array($data['payments'])) {
        foreach ($data['payments'] as $payment) {
            if (isset($payment['payment_status']) && strtoupper($payment['payment_status']) == 'PAID') {
                error_log("QPay Check Success (Payment Array): Invoice $qpay_invoice_id is PAID.");
                return ['status' => 'PAID', 'message' => 'Төлөгдсөн (Payment Array).'];
            }
        }
    }

    // 2. Хэрэв "payments" дотор "PAID" байхгүй бол дээд түвшний статусыг шалгах
    // (Зарим тохиолдолд шууд 'invoice_status' нь 'PAID' байж болно)
    if (isset($data['invoice_status']) && strtoupper($data['invoice_status']) == 'PAID') {
        error_log("QPay Check Success (Invoice Status): Invoice $qpay_invoice_id is PAID.");
        return ['status' => 'PAID', 'message' => 'Төлөгдсөн (Invoice Status).'];
    }
    
    // 3. "PAID" статус хаана ч олдсонгүй, PENDING гэж үзнэ
    // Таны лог дээрх "invoice_status":"CLOSED" эсвэл "NEW", "PENDING" гэх мэт байж болно.
    $current_status = $data['invoice_status'] ?? 'UNKNOWN';
    error_log("QPay Check: Invoice $qpay_invoice_id not paid. Status: " . $current_status . ". Response: " . $response);
    return ['status' => 'PENDING', 'message' => 'Хүлээгдэж байна: ' . $current_status];
    
    // =======================================================
    //  *** ЗАСВАР ДУУСАВ ***
    // =======================================================
}

/**
 * ===================================================================
 * ШИНЭ ФУНКЦ (CALLBACK-Д ЗОРИУЛСАН)
 * ===================================================================
 * QPay-н нэхэмжлэхийг SENDER_INVOICE_NO-г ашиглан шалгах.
 * Энэ нь "payment/check" endpoint-г ашиглана.
 *
 * @param string $sender_invoice_no Бидний үүсгэсэн (жишээ нь: FZ2025...) нэхэмжлэхийн дугаар
 * @return bool Төлөгдсөн бол true, үгүй бол false
 */
function check_qpay_payment_by_sender_no($sender_invoice_no) {
    $token = get_qpay_token();
    if (!$token) {
        error_log("Check Status (SenderNo) Error: Could not get token.");
        return false;
    }

    $url = QPAY_API_URL . 'payment/check'; // Энэ нь өөр endpoint
    
    $post_data = [
        'object_type' => 'INVOICE',
        'object_id' => $sender_invoice_no,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1); // Энэ бол POST хүсэлт
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('QPay check (payment/check) error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $data = json_decode($response, true);

    // Энэ endpoint нь гүйлгээний жагсаалт (array) буцаадаг
    if (isset($data['rows']) && is_array($data['rows'])) {
        foreach ($data['rows'] as $payment) {
            // Төлөгдсөн (PAID) статус бүхий ядаж нэг гүйлгээ байвал true буцаана
            if (isset($payment['payment_status']) && strtoupper($payment['payment_status']) == 'PAID') {
                error_log("QPay Check Success (SenderNo): Invoice $sender_invoice_no is PAID.");
                return true;
            }
        }
    }
    
    // Төлөгдсөн гүйлгээ олдсонгүй
    error_log("QPay Check Failed (SenderNo): Invoice $sender_invoice_no not paid. Response: " . $response);
    return false;
}

/**
 * ===================================================================
 * ШИНЭ ФУНКЦ: АМЖИЛТТАЙ ЦЭНЭГЛЭЛТИЙГ БОЛОВСРУУЛАХ
 * ===================================================================
 * Энэ функцийг callback handler болон AJAX шалгагч хоёулаа дуудна.
 * Энэ нь гүйлгээний давхцалыг шалгаад, хэрэглэгчийн balance-г нэмнэ.
 *
 * @param int $user_id
 * @param float $amount
 * @param string $invoice_id (Энэ бол sender_invoice_no)
 * @return bool Төлбөр амжилттай бүртгэгдсэн (эсвэл өмнө нь бүртгэгдсэн) бол true, алдаа гарвал false.
 */
function process_successful_deposit($user_id, $amount, $invoice_id) {
    $conn = db_connect();
    if (!$conn) {
        error_log("process_successful_deposit: Database connection failed.");
        return false;
    }

    $user_id = (int)$user_id;
    $amount = (float)$amount;
    $invoice_id_safe = mysqli_real_escape_string($conn, $invoice_id);

    // 1. Дүн 0 эсвэл түүнээс бага бол шууд алдаа буцаах
    if ($amount <= 0) {
        error_log("QPay Deposit Zero Amount: Invoice $invoice_id_safe");
        return false;
    }

    // 2. Гүйлгээ давхцаж байгаа эсэхийг шалгах (маш чухал)
    $check_sql = "SELECT id FROM user_transactions WHERE description LIKE '%" . $invoice_id_safe . "%'";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        // Энэ гүйлгээ АМЖИЛТТАЙ бичигдсэн байна. Дахин юу ч хийх хэрэггүй.
        error_log("QPay Deposit Duplicate Ignored: Invoice $invoice_id_safe");
        return true; // "Амжилттай" гэж үзнэ (өмнө нь орсон)
    }

    // 3. Шинэ гүйлгээ байна, DB-д бичье
    mysqli_begin_transaction($conn);
    try {
        // 1. Хэрэглэгчийн үлдэгдлийг нэмэх (users хүснэгт)
        $update_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt_update, "di", $amount, $user_id);
        mysqli_stmt_execute($stmt_update);

        // 2. Гүйлгээний түүхэнд бичих (user_transactions хүснэгт)
        $description = "Данс цэнэглэлт (Qpay). Invoice: " . $invoice_id_safe;
        $insert_sql = "INSERT INTO user_transactions (user_id, type, amount, description) VALUES (?, 'deposit', ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt_insert, "ids", $user_id, $amount, $description);
        mysqli_stmt_execute($stmt_insert);

        mysqli_commit($conn);
        error_log("QPay Deposit Success (NEW): User $user_id, Amount $amount, Invoice $invoice_id_safe");
        return true; // Амжилттай, шинээр бичигдлээ

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("QPay Deposit DB Error: " . $e->getMessage());
        return false; // Алдаа гарлаа
    }
}


/**
 * ===================================================================
 * ХАМГИЙН ЧУХАЛ ХЭСЭГ: QPAY CALLBACK HANDLER
 * ===================================================================
 * QPay-с төлбөр төлөгдсөн үед энэ хэсэг рүү хандана.
 * Энэ код файлын хамгийн доор, ямар нэг функц дотор биш байх ёстой.
 */

// Callback URL-с ирсэн мэдээллийг шалгах (Бид GET-ээр тохируулсан)
if (isset($_GET['invoice_id']) && isset($_GET['user_id'])) {
    
    $invoice_id_from_qpay = $_GET['invoice_id'];
    
    // 1. АЮУЛГҮЙ БАЙДАЛ: Энэ нэхэмжлэх үнэхээр төлөгдсөн эсэхийг QPay-с шалгах
    $is_paid = check_qpay_payment_by_sender_no($invoice_id_from_qpay);
    
    if ($is_paid) {
        // Төлбөр АМЖИЛТТАЙ баталгаажлаа
        $conn = db_connect(); // DB-тэй холбогдох
        if (!$conn) {
            error_log('Callback Error: Database connection failed.');
            http_response_code(500); // Серверийн алдаа
            exit;
        }

        $user_id = (int)$_GET['user_id'];
        $invoice_id_safe = mysqli_real_escape_string($conn, $invoice_id_from_qpay);

        // 2. Ямар төрлийн гүйлгээ вэ? (Данс цэнэглэлт үү, Файл худалдан авалт уу?)
        if (isset($_GET['type']) && $_GET['type'] == 'deposit' && isset($_GET['amount'])) {
            // ===== ДАНС ЦЭНЭГЛЭЛТ =====
            $amount = (float)$_GET['amount'];
            
            process_successful_deposit($user_id, $amount, $invoice_id_from_qpay);

        } elseif (isset($_GET['file_id'])) {
            // ===== ФАЙЛ ХУДАЛДАН АВАЛТ =====
            $file_id = (int)$_GET['file_id'];
            
            // Файлын үнийг DB-с авах
            $file_sql = "SELECT price, user_id FROM files WHERE id = ?";
            $stmt = mysqli_prepare($conn, $file_sql);
            mysqli_stmt_bind_param($stmt, "i", $file_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $file = mysqli_fetch_assoc($result);
            
            $amount = $file ? (float)$file['price'] : 0;
            $owner_id = $file ? (int)$file['user_id'] : 0;
            mysqli_stmt_bind_param($stmt, "i", $file_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $file = mysqli_fetch_assoc($result);
            $amount = $file ? $file['price'] : 0;

            // Гүйлгээ давхцаж байгаа эсэхийг шалгах (transactions хүснэгт)
            $check_sql = "SELECT id FROM transactions WHERE user_id = ? AND file_id = ? AND status = 'success' AND payment_method = 'qpay'";
            $stmt_check = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $file_id);
            mysqli_stmt_execute($stmt_check);
            $check_result = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($check_result) == 0 && $amount > 0) {
             mysqli_begin_transaction($conn);
             try {
                // 1. Худалдан авалтын түүх бичих
                $insert_sql = "INSERT INTO transactions (user_id, file_id, amount, payment_method, status) VALUES (?, ?, ?, 'qpay', 'success')";
                $stmt_insert = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($stmt_insert, "iid", $user_id, $file_id, $amount);
                mysqli_stmt_execute($stmt_insert);
                
                // 2. Файлын таталтын тоог нэмэх
                $update_sql = "UPDATE files SET download_count = download_count + 1 WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt_update, "i", $file_id);
                mysqli_stmt_execute($stmt_update);

                // 3. ШИНЭ: Файл эзэмшигчид ШИМТГЭЛ ХАСАЖ мөнгө нэмэх
                if ($owner_id > 0) {
                    $earning = calculate_earnings($amount); // functions.php-д бичсэн функц
                    
                    $owner_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                    $stmt_owner = mysqli_prepare($conn, $owner_sql);
                    mysqli_stmt_bind_param($stmt_owner, "di", $earning, $owner_id);
                    mysqli_stmt_execute($stmt_owner);
                }
                
                mysqli_commit($conn);
                error_log("QPay Purchase Success: User $user_id, File $file_id, Invoice $invoice_id_safe");
            } catch (Exception $e) {
                 mysqli_rollback($conn);
                 error_log("QPay Purchase DB Error: " . $e->getMessage());
            }
        } else {
                 error_log("QPay Purchase Duplicate/Free: User $user_id, File $file_id.");
            }
        }
        
        // QPay-д "Амжилттай" гэсэн хариуг буцаах (Энэ нь чухал)
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Callback processed.']);
        exit;
        
    } else {
        // Төлбөр баталгаажсангүй (Хуурамч дуудлага эсвэл төлбөр цуцлагдсан)
        error_log("QPay Callback Error: Payment not verified for invoice $invoice_id_from_qpay");
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Payment not verified.']);
        exit;
    }
}

// Хэрэв callback биш, зүгээр л файл (жишээ нь, download.php-с) include хийгдэж байвал
// юу ч хийхгүй, зүгээр л функцүүдийг бэлэн болгоно.
?>