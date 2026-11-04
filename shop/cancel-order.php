
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function cancelRedirect(): void
{
    // Lay duong dan tu URL file PHP dang chay.
    // /trekking_new/shop/cancel-order.php
    // => /trekking_new/shop/my-orders.php
    $scriptPath = str_replace(
        '\\',
        '/',
        (string)($_SERVER['SCRIPT_NAME'] ?? '')
    );

    $scriptDir = rtrim(
        str_replace('\\', '/', dirname($scriptPath)),
        '/'
    );

    if (
        $scriptDir !== '' &&
        $scriptDir !== '.' &&
        preg_match('~(?:^|/)shop$~i', $scriptDir)
    ) {
        $url = $scriptDir . '/my-orders.php';
    } else {
        $url = rtrim(
            defined('BASE_URL') ? BASE_URL : '/',
            '/'
        ) . '/shop/my-orders.php';
    }

    if (!headers_sent()) {
        header('Location: ' . $url, true, 303);
        exit;
    }

    echo '<script>window.location.replace(' .
        json_encode(
            $url,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) .
        ');</script>';
    exit;
}

function cancelNotice(string $type, string $message): void
{
    $_SESSION['shop_order_notice'] = [
        'type' => $type,
        'message' => $message
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cancelRedirect();
}

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    cancelNotice(
        'error',
        'Vui lòng đăng nhập để hủy đơn hàng.'
    );
    cancelRedirect();
}

$csrf = (string)($_POST['csrf'] ?? '');
$savedCsrf = (string)($_SESSION['shop_cancel_csrf'] ?? '');

if (
    $savedCsrf === '' ||
    !hash_equals($savedCsrf, $csrf)
) {
    cancelNotice(
        'error',
        'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
    );
    cancelRedirect();
}

$orderId = filter_var(
    $_POST['order_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($orderId === false || $orderId === null || $orderId <= 0) {
    cancelNotice(
        'error',
        'Mã đơn hàng không hợp lệ.'
    );
    cancelRedirect();
}

try {
    $pdo->beginTransaction();

    // Khoa don hang de tranh xu ly trung lap.
    $checkOrder = $pdo->prepare("
        SELECT id, status
        FROM orders
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $checkOrder->execute([
        $orderId,
        $userId
    ]);

    $order = $checkOrder->fetch(PDO::FETCH_ASSOC);
    $checkOrder->closeCursor();

    if (!$order) {
        throw new RuntimeException(
            'Không tìm thấy đơn hàng của bạn.'
        );
    }

    if (trim((string)$order['status']) !== 'Chờ xác nhận') {
        throw new RuntimeException(
            'Đơn hàng đã được xử lý nên không thể hủy.'
        );
    }

    // Lay danh sach san pham trong don.
    $getDetails = $pdo->prepare("
        SELECT product_id, SUM(quantity) AS total_quantity
        FROM order_details
        WHERE order_id = ?
        GROUP BY product_id
    ");

    $getDetails->execute([$orderId]);

    $products = $getDetails->fetchAll(PDO::FETCH_ASSOC);
    $getDetails->closeCursor();

    if (!$products) {
        throw new RuntimeException(
            'Không tìm thấy chi tiết đơn hàng để hoàn kho.'
        );
    }

    // Chi huy don dang cho xac nhan.
    $cancelOrder = $pdo->prepare("
        UPDATE orders
        SET status = 'Đã hủy'
        WHERE id = ?
          AND user_id = ?
          AND status = 'Chờ xác nhận'
    ");

    $cancelOrder->execute([
        $orderId,
        $userId
    ]);

    if ($cancelOrder->rowCount() !== 1) {
        throw new RuntimeException(
            'Trạng thái đơn hàng đã thay đổi.'
        );
    }

    // Hoan so luong hang ve kho.
    $restoreStock = $pdo->prepare("
        UPDATE products
        SET stock = stock + ?
        WHERE id = ?
    ");

    foreach ($products as $product) {
        $productId = (int)$product['product_id'];
        $quantity = (int)$product['total_quantity'];

        if ($productId <= 0 || $quantity <= 0) {
            throw new RuntimeException(
                'Thông tin sản phẩm hoặc số lượng không hợp lệ.'
            );
        }

        $restoreStock->execute([
            $quantity,
            $productId
        ]);

        if ($restoreStock->rowCount() !== 1) {
            throw new RuntimeException(
                'Không thể hoàn kho cho sản phẩm #' .
                $productId
            );
        }
    }

    $pdo->commit();

    cancelNotice(
        'success',
        'Đơn hàng đã được hủy thành công.'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Cancel order error: ' . $e->getMessage()
    );

    cancelNotice(
        'error',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể hủy đơn hàng. Vui lòng thử lại.'
    );
}

cancelRedirect();
