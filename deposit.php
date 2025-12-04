<?php
// Start session and include essential files
session_start();
require_once 'includes/functions.php';
require_once 'includes/qpay_config.php';
require_once 'includes/qpay_handler.php';

// ===================================================================
//  AJAX: ТӨЛБӨР ШАЛГАХ ХАНДЛАГЧ (САЙЖРУУЛСАН)
// ===================================================================
if (isset($_GET['action']) && $_GET['action'] == 'check_payment') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['pending_deposit']) || !isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Pending deposit not found or user not logged in.']);
        exit;
    }

    $pending_deposit = $_SESSION['pending_deposit'];
    $user_id = (int)$_SESSION['user_id'];

    if ($user_id !== (int)$pending_deposit['user_id']) {
        echo json_encode(['success' => false, 'message' => 'User mismatch.']);
        exit;
    }

    $sender_invoice_no = $pending_deposit['sender_invoice_no']; 
    $qpay_invoice_id = $pending_deposit['qpay_invoice_id'];     
    $amount = (float)$pending_deposit['amount'];

    // 1. QPay-с төлбөрийн статусыг шалгах (Шинэчилсэн функц)
    $payment_check = check_qpay_payment_status($qpay_invoice_id);

    if ($payment_check['status'] == 'PAID') {
        // 2. Төлбөр төлөгдсөн бол CИСТЕМД бүртгэх
        $processed = process_successful_deposit($user_id, $amount, $sender_invoice_no);

        if ($processed) {
            unset($_SESSION['pending_deposit']);
            echo json_encode(['success' => true, 'message' => 'Payment confirmed and processed.']);
        } else {
            // Энэ алдаа нь process_successful_deposit дотор гарсан гэсэн үг (DB алдаа)
            echo json_encode(['success' => false, 'message' => 'Payment confirmed but failed to update balance. Please contact support.']);
        }
    } elseif ($payment_check['status'] == 'ERROR') {
        // 3. АЛДАА гарсан бол хэрэглэгчид мэдэгдэх
        // Энэ нь танд config, cURL-н алдааг харуулна
        echo json_encode(['success' => false, 'message' => 'Payment check failed: ' . $payment_check['message']]);
    } else {
        // 4. PENDING (Хүлээгдэж буй)
        echo json_encode(['success' => false, 'message' => 'Payment not yet confirmed. (' . $payment_check['message'] . ')']);
    }

    exit; // AJAX-н хариуг буцаагаад зогсох
}
// ===================================================================
//  AUTHENTICATION & SETUP
// ===================================================================
// Check if the user is logged in, redirect to login page if not
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the logged-in user's ID
$user_id = (int)$_SESSION['user_id'];

// Establish a database connection
$conn = db_connect();
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// ===================================================================
//  VARIABLE INITIALIZATION
// ===================================================================
$deposit_success = false;
$error_message = '';
$success_message = '';
$qpay_invoice_data = null;
$qpay_error_message = null;

// Get user's current balance
$balance = 0;
$balance_query = "SELECT balance FROM users WHERE id = ?";
$balance_stmt = mysqli_prepare($conn, $balance_query);
mysqli_stmt_bind_param($balance_stmt, "i", $user_id);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);

if (mysqli_num_rows($balance_result) > 0) {
    $balance_row = mysqli_fetch_assoc($balance_result);
    $balance = $balance_row['balance'];
}

// ===================================================================
//  POST REQUEST HANDLING (FORM SUBMISSION)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle QPay payment request
    if (isset($_POST['pay_with_qpay']) && isset($_POST['deposit_amount'])) {
        $deposit_amount = filter_var($_POST['deposit_amount'], FILTER_VALIDATE_FLOAT);
        
        // Validate deposit amount
        if ($deposit_amount === false || $deposit_amount < 1000) {
            $error_message = "Хүчингүй дүн оруулсан байна. Хамгийн бага дүн: 1,000₮";
        } else {
            // Get user data
            $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = {$user_id}");
            $current_user_data = mysqli_fetch_assoc($user_query);

            if ($current_user_data) {
                // Create deposit data for QPay invoice
                    $deposit_data = [
                        'id' => 'deposit_' . $user_id . '_' . time(),
                        'title' => 'FileZone.mn - Цэнэглэлт - ' . $current_user_data['username'] . ' - ' . date('Y-m-d H:i:s'),
                        'price' => $deposit_amount,
                        'user_id' => $user_id,
                        'username' => $current_user_data['username']
                    ];

                    // Call QPay to create invoice
                    $invoice_response = create_qpay_deposit_invoice($deposit_data, $current_user_data);

                if (isset($invoice_response['invoice_id'])) {
                    // Store the invoice data temporarily in session for verification
                    $_SESSION['pending_deposit'] = [
                        'sender_invoice_no' => $invoice_response['sender_invoice_no'],
                        'qpay_invoice_id'   => $invoice_response['invoice_id'],
                        'amount' => $deposit_amount,
                        'user_id' => $user_id,
                        'created_at' => time()
                    ];
                    
                    $qpay_invoice_data = $invoice_response;
                } else {
                    $error_message = "Нэхэмжлэх үүсгэхэд алдаа гарлаа: " . 
                        (isset($invoice_response['error']) ? $invoice_response['error'] : 'Тодорхойгүй алдаа');
                }
            } else {
                $error_message = "Хэрэглэгчийн мэдээллийг олж чадсангүй.";
            }
        }
    }
    
    // Handle direct balance update (for testing/alternative methods)
    if (isset($_POST['deposit_amount'], $_POST['payment_method']) && $_POST['payment_method'] == 'balance') {
        $deposit_amount = filter_var($_POST['deposit_amount'], FILTER_VALIDATE_FLOAT);
        $payment_method = htmlspecialchars($_POST['payment_method']);

        if ($deposit_amount === false || $deposit_amount <= 0) {
            $error_message = "Хүчингүй дүн оруулсан байна.";
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);
            try {
                // Update user balance
                $update_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "di", $deposit_amount, $user_id);
                $update_executed = mysqli_stmt_execute($update_stmt);
                
                if (!$update_executed) {
                    throw new Exception("Дансны үлдэгдлийг шинэчлэхэд алдаа гарлаа.");
                }

                // Record transaction
                $description = "Данс цэнэглэлт (" . ucfirst($payment_method) . ")";
                $insert_sql = "INSERT INTO user_transactions (user_id, type, amount, description) VALUES (?, 'deposit', ?, ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "ids", $user_id, $deposit_amount, $description);
                $insert_executed = mysqli_stmt_execute($insert_stmt);
                
                if (!$insert_executed) {
                    throw new Exception("Гүйлгээний түүхэнд бичихэд алдаа гарлаа.");
                }

                mysqli_commit($conn);
                $deposit_success = true;
                $success_message = number_format($deposit_amount, 2) . "₮-г дансанд амжилттай нэмлээ.";
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_message = "Гүйлгээ хийхэд алдаа гарлаа: " . $e->getMessage();
            }
        }
    }
}

// ===================================================================
//  PAGE SETUP & HEADER INCLUSION
// ===================================================================
include 'includes/header.php';
include 'includes/navigation.php';
?>

<main class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Данс цэнэглэх</h1>
            <p class="text-gray-600">Дансаа цэнэглэж, файлуудыг хялбархан худалдаж аваарай.</p>
            <?php if ($balance > 0): ?>
                <div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4 inline-block">
                    <p class="text-green-800 font-semibold">Таны дансны үлдэгдэл: <?= number_format($balance) ?>₮</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($deposit_success): ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <div class="text-green-500 text-6xl mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Цэнэглэлт амжилттай</h2>
                <p class="text-gray-700 text-lg mb-6"><?= htmlspecialchars($success_message) ?></p>
                <div class="flex justify-center gap-4">
                    <a href="profile.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-md font-medium">
                        <i class="fas fa-user mr-2"></i> Миний хуудас
                    </a>
                    <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-md font-medium">
                        <i class="fas fa-home mr-2"></i> Нүүр хуудас
                    </a>
                </div>
            </div>

        <?php elseif ($qpay_invoice_data): ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">QPay төлбөр төлөх</h2>

                <div id="payment-status-container" class="mb-6">
                    <div id="payment-loading" class="bg-blue-50 border border-blue-200 text-blue-700 p-4 rounded-lg">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>QR кодыг уншуулж, төлбөрөө төлнө үү. Төлбөр автоматаар шалгагдана.</span>
                    </div>
                    <div id="payment-success" class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-lg hidden">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>Төлбөр амжилттай! Таны данс цэнэглэгдлээ. 3 секундэд шилжих болно...</span>
                    </div>
                </div>

                <div class="max-w-md mx-auto">

                    <?php if (isset($qpay_invoice_data['qr_image'])): ?>
                        <img src="data:image/png;base64,<?= $qpay_invoice_data['qr_image'] ?>" alt="QPay QR Code" class="mx-auto mb-6 border border-gray-300 rounded-lg">
                    <?php endif; ?>

                    <?php if (isset($qpay_invoice_data['urls']) && is_array($qpay_invoice_data['urls'])): ?>
                        <p class="text-sm text-gray-500 mb-4">Эсвэл банкны аппликейшн ашиглан нэвтэрч төлнө үү:</p>
                        <div class="flex flex-wrap justify-center gap-4 mb-6">
                            <?php foreach ($qpay_invoice_data['urls'] as $link): ?>
                                <?php if(isset($link['link']) && isset($link['logo_url']) && isset($link['name'])): // Check if all keys exist ?>
                                    <a href="<?= htmlspecialchars($link['link']) ?>" class="block" title="<?= htmlspecialchars($link['name']) ?>">
                                        <img src="<?= htmlspecialchars($link['logo_url']) ?>" alt="<?= htmlspecialchars($link['name']) ?>" class="h-10 w-auto rounded-md shadow">
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <button id="check-payment-btn" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-md font-medium mb-4 text-lg">
                            <i class="fas fa-redo mr-2"></i> Төлбөр шалгах
                        </button>
                        <a href="deposit.php" class="text-sm text-gray-500 hover:text-gray-600">
                            Цуцлах
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Deposit Form -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-6">
                        <p><?= htmlspecialchars($error_message) ?></p>
                    </div>
                <?php endif; ?>

                <form id="deposit-form" method="POST">
                    <div class="mb-6">
                        <label for="deposit_amount" class="block text-lg font-semibold text-gray-800 mb-3">Цэнэглэх дүнгээ сонгоно уу</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <button type="button" class="amount-btn bg-gray-100 hover:bg-purple-100 text-gray-800 font-bold py-3 px-4 rounded-lg transition" data-amount="10000">10,000₮</button>
                            <button type="button" class="amount-btn bg-gray-100 hover:bg-purple-100 text-gray-800 font-bold py-3 px-4 rounded-lg transition" data-amount="20000">20,000₮</button>
                            <button type="button" class="amount-btn bg-gray-100 hover:bg-purple-100 text-gray-800 font-bold py-3 px-4 rounded-lg transition" data-amount="50000">50,000₮</button>
                            <button type="button" class="amount-btn bg-gray-100 hover:bg-purple-100 text-gray-800 font-bold py-3 px-4 rounded-lg transition" data-amount="100000">100,000₮</button>
                        </div>
                        <div class="relative">
                            <input type="number" id="deposit_amount" name="deposit_amount" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 text-lg text-center focus:outline-none focus:ring-2 focus:ring-purple-500" 
                                   placeholder="Эсвэл өөрийн дүнгээ оруулна уу" 
                                   min="1000" step="1000" required>
                            <span class="absolute right-3 top-3 text-gray-500">₮</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Хамгийн бага дүн: 1,000₮</p>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Төлбөрийн хэрэгсэл сонгох</h3>
                        <div class="space-y-3">
                            <div class="payment-method rounded-lg p-4 cursor-pointer border border-gray-200 hover:border-purple-500 transition" data-method="qpay">
                                <div class="flex items-center">
                                    <img src="assets/images/qpay-logo.png" alt="QPay" class="w-10 h-10 mr-4">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-800">QPay</h4>
                                        <p class="text-sm text-gray-600">QR код уншуулан төлбөр төлөх</p>
                                    </div>
                                    <i class="fas fa-check-circle text-2xl text-gray-300 payment-check"></i>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="payment_method" id="payment_method" value="" required>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <button type="submit" name="pay_with_qpay" id="submit-button" 
                                class="w-full bg-purple-600 text-white py-3 rounded-md font-bold text-lg hover:bg-purple-700 transition opacity-50 cursor-not-allowed" disabled>
                            <i class="fas fa-shield-alt mr-2"></i> <span id="button-amount">0</span>₮ цэнэглэх
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
include 'includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ===================================================================
    //  ЛОГИК 1: ДАНС ЦЭНЭГЛЭХ ФОРМЫН ХУУДАСНЫ АЖИЛЛАГАА
    // ===================================================================
    const depositForm = document.getElementById('deposit-form');
    
    // Хэрэв "deposit-form" (мөнгөн дүн сонгох) хэсэг байвал...
    if (depositForm) {
        const amountInput = document.getElementById('deposit_amount');
        const amountButtons = document.querySelectorAll('.amount-btn');
        const paymentMethodInputs = document.querySelectorAll('.payment-method');
        const hiddenPaymentMethod = document.getElementById('payment_method');
        const submitButton = document.getElementById('submit-button');
        const buttonAmountSpan = document.getElementById('button-amount');
        
        let selectedAmount = 0;
        let selectedMethod = '';

        function validateForm() {
            selectedAmount = parseFloat(amountInput.value) || 0;
            const isAmountValid = selectedAmount >= 1000;
            const isMethodSelected = hiddenPaymentMethod.value !== '';

            if (isAmountValid && isMethodSelected) {
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                buttonAmountSpan.textContent = selectedAmount.toLocaleString();
            } else {
                submitButton.disabled = true;
                submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                buttonAmountSpan.textContent = selectedAmount > 0 ? selectedAmount.toLocaleString() : '0';
            }
        }

        amountButtons.forEach(button => {
            button.addEventListener('click', function() {
                const amount = this.dataset.amount;
                amountInput.value = amount;
                amountButtons.forEach(btn => btn.classList.remove('bg-purple-600', 'text-white'));
                this.classList.add('bg-purple-600', 'text-white');
                validateForm();
            });
        });

        amountInput.addEventListener('input', function() {
            amountButtons.forEach(btn => btn.classList.remove('bg-purple-600', 'text-white'));
            validateForm();
        });

        paymentMethodInputs.forEach(method => {
            method.addEventListener('click', function() {
                const methodType = this.dataset.method;
                hiddenPaymentMethod.value = methodType;
                
                paymentMethodInputs.forEach(m => {
                    m.classList.remove('border-purple-600', 'ring-2', 'ring-purple-200');
                    m.querySelector('.payment-check').classList.remove('text-purple-600');
                    m.querySelector('.payment-check').classList.add('text-gray-300');
                });
                
                this.classList.add('border-purple-600', 'ring-2', 'ring-purple-200');
                this.querySelector('.payment-check').classList.add('text-purple-600');
                this.querySelector('.payment-check').classList.remove('text-gray-300');
                
                validateForm();
            });
        });

        // Эхлэх үед формыг шалгах
        validateForm();
    }

    
    // ===================================================================
    //  ЛОГИК 2: QR КОД ХАРУУЛАХ ХУУДАСНЫ АЖИЛЛАГАА
    // ===================================================================
    const paymentStatusContainer = document.getElementById('payment-status-container');
    
    // Хэрэв "payment-status-container" (QR код харуулах) хэсэг байвал...
    if (paymentStatusContainer) {
        const loadingDiv = document.getElementById('payment-loading');
        const successDiv = document.getElementById('payment-success');
        const checkBtn = document.getElementById('check-payment-btn');
        let pollInterval;

        // Шалгах функц
        const checkPayment = async () => {
            try {
                const response = await fetch('deposit.php?action=check_payment');
                const data = await response.json();

                if (data.success) {
                    // АМЖИЛТТАЙ!
                    clearInterval(pollInterval);
                    loadingDiv.classList.add('hidden');
                    successDiv.classList.remove('hidden');
                    
                    // "Төлбөр шалгах" товчийг бүрмөсөн идэвхгүй болгох
                    if(checkBtn) {
                        checkBtn.disabled = true;
                        checkBtn.innerHTML = '<i class="fas fa-check"></i> Амжилттай';
                    }

                    setTimeout(() => {
                        window.location.href = 'profile.php';
                    }, 3000);
                } else {
                    console.log(data.message);
                }
            } catch (error) {
                console.error('Error checking payment:', error);
            }
        };

        // 1. Хуудас ачаалахад 5 секунд тутамд автоматаар шалгаж эхлэх
        pollInterval = setInterval(checkPayment, 5000);

        // 2. "Төлбөр шалгах" товчийг дарахад гараар шалгах
        if (checkBtn) {
            checkBtn.addEventListener('click', () => {
                checkBtn.disabled = true;
                checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Шалгаж байна...';

                checkPayment().finally(() => {
                    // (Амжилттай болоогүй бол) товчийг буцааж идэвхжүүлэх
                    if (!successDiv.classList.contains('hidden')) {
                        // "Амжилттай" болсон тул товч идэвхгүй хэвээр үлдэнэ
                    } else {
                        checkBtn.disabled = false;
                        checkBtn.innerHTML = '<i class="fas fa-redo mr-2"></i> Төлбөр шалгах';
                    }
                });
            });
        }
    }

});
</script>