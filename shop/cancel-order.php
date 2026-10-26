
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function coRedirect(): void {
    header('Location: /shop/my-orders.php', true, 303);
    exit;
}

function coNotice(string $type, string $message): void {
    $_SESSION['shop_order_notice'] = [
        'type' => $type,
        'message' => $message
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    coRedirect();
}

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    coNotice('error', 'Vui lòng đăng nhập để hủy đơn hàng.');
    coRedirect();
}

$csrf = (string)($_POST['csrf'] ?? '');

if (
    empty($_SESSION['shop_cancel_csrf']) ||
    !hash_equals((string)$_SESSION['shop_cancel_csrf'], $csrf)
) {
    coNotice('error', 'Phiên làm việc không hợp lệ.');
    coRedirect();
}

$orderId = filter_var(
    $_POST['order_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$orderId || $orderId <= 0) {
    coNotice('error', 'Mã đơn hàng không hợp lệ.');
    coRedirect();
}

try {
    $pdo->beginTransaction();

    // Khoa don hang truoc khi kiem tra.
    $stmt = $pdo->prepare("
        SELECT id, status
        FROM orders
        WHERE id = ? AND user_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

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

    // Lay so luong san pham da tru kho luc checkout.
    $details = $pdo->prepare("
        SELECT product_id, SUM(quantity) AS quantity
        FROM order_details
        WHERE order_id = ?
        GROUP BY product_id
    ");

    $details->execute([$orderId]);
    $products = $details->fetchAll(PDO::FETCH_ASSOC);
    $details->closeCursor();

    if (!$products) {
        throw new RuntimeException(
            'Không tìm thấy chi tiết đơn hàng để hoàn kho.'
        );
    }

    // Cap nhat trang thai truoc.
    // Dieu kien Chờ xác nhận dam bao khong huy hai lan.
    $update = $pdo->prepare("
        UPDATE orders
        SET status = 'Đã hủy'
        WHERE id = ?
          AND user_id = ?
          AND status = 'Chờ xác nhận'
    ");

    $update->execute([$orderId, $userId]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException(
            'Trạng thái đơn hàng đã thay đổi.'
        );
    }

    // Hoan dung so luong tung san pham.
    $restore = $pdo->prepare("
        UPDATE products
        SET stock = stock + ?
        WHERE id = ?
    ");

    foreach ($products as $product) {
        $quantity = (int)$product['quantity'];
        $productId = (int)$product['product_id'];

        if ($quantity <= 0 || $productId <= 0) {
            throw new RuntimeException(
                'Số lượng hoàn kho không hợp lệ.'
            );
        }

        $restore->execute([$quantity, $productId]);

        if ($restore->rowCount() !== 1) {
            throw new RuntimeException(
                'Không thể hoàn tồn kho cho một sản phẩm.'
            );
        }
    }

    $pdo->commit();

    coNotice(
        'success',
        'Đơn hàng đã được hủy thành công.'
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Cancel order: ' . $e->getMessage());

    coNotice(
        'error',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể hủy đơn hàng lúc này.'
    );
}

coRedirect();
