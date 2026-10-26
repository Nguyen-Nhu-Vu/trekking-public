<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function cancelOrderRedirect(): void {
    header('Location: /shop/my-orders.php', true, 303);
    exit;
}

function cancelOrderNotice(string $type, string $message): void {
    $_SESSION['shop_order_notice'] = [
        'type' => $type,
        'message' => $message
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cancelOrderRedirect();
}

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    cancelOrderNotice('error', 'Vui lòng đăng nhập để hủy đơn hàng.');
    cancelOrderRedirect();
}

$csrf = (string)($_POST['csrf'] ?? '');

if (
    empty($_SESSION['shop_cancel_csrf']) ||
    !hash_equals((string)$_SESSION['shop_cancel_csrf'], $csrf)
) {
    cancelOrderNotice('error', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
    cancelOrderRedirect();
}

$orderId = filter_var(
    $_POST['order_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$orderId || $orderId <= 0) {
    cancelOrderNotice('error', 'Đơn hàng không hợp lệ.');
    cancelOrderRedirect();
}

try {
    $pdo->beginTransaction();

    // Khóa dòng đơn trong lúc kiểm tra và cập nhật.
    // Chỉ chủ đơn mới có quyền hủy.
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
            'Không tìm thấy đơn hàng thuộc tài khoản của bạn.'
        );
    }

    if (trim((string)$order['status']) !== 'Chờ xác nhận') {
        throw new RuntimeException(
            'Đơn hàng đã được xử lý nên không thể hủy.'
        );
    }

    // Điều kiện status được kiểm tra lại ngay khi UPDATE.
    $stmt = $pdo->prepare("
        UPDATE orders
        SET status = 'Đã hủy'
        WHERE id = ?
          AND user_id = ?
          AND status = 'Chờ xác nhận'
    ");

    $stmt->execute([$orderId, $userId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Trạng thái đơn hàng đã thay đổi. Vui lòng tải lại trang.'
        );
    }

    $pdo->commit();

    cancelOrderNotice(
        'success',
        'Đơn hàng đã được hủy thành công.'
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Cancel shop order: ' . $e->getMessage());

    cancelOrderNotice(
        'error',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể hủy đơn hàng lúc này.'
    );
}

cancelOrderRedirect();
