<?php
// QPay API-н үндсэн хаяг
define('QPAY_API_URL', 'https://merchant.qpay.mn/v2/');

// QPay-с танд олгосон шинэ, зөв мэдээллүүд
define('QPAY_CLIENT_ID', 'FILE_ZONE');             // Энд таны username орлоо
define('QPAY_CLIENT_SECRET', 'uxM9fkB1');          // Энд таны password орлоо

// QPay-с танд олгосон нэхэмжлэхийн код
define('QPAY_INVOICE_CODE', 'FILE_ZONE_INVOICE'); // Энд таны INVOICE_CODE орлоо

// Төлбөрийн мэдээлэл хүлээн авах Callback URL
define('QPAY_CALLBACK_URL', 'https://www.filezone.mn/includes/qpay_handler.php');

?>