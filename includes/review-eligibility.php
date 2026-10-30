
<?php
// Kiểm tra quyền đánh giá dựa trên lịch sử đặt tour / mua sản phẩm.
// Không thay đổi dữ liệu, không cấp quyền cho tài khoản quản trị.
if (!function_exists('vnnCanReviewTour')) {
    function vnnCanReviewTour(PDO $pdo, int $userId, int $tourId): bool {
        if ($userId <= 0 || $tourId <= 0) return false;
        $stmt = $pdo->prepare("SELECT 1 FROM tour_bookings b
            JOIN users u ON u.id = b.user_id
            WHERE b.user_id = ? AND b.tour_id = ?
              AND u.role = 'customer'
              AND LOWER(TRIM(b.status)) NOT IN ('đã hủy', 'hủy', 'cancelled', 'canceled', 'từ chối', 'rejected')
            LIMIT 1");
        $stmt->execute([$userId, $tourId]);
        return (bool)$stmt->fetchColumn();
    }
}
if (!function_exists('vnnCanReviewProduct')) {
    function vnnCanReviewProduct(PDO $pdo, int $userId, int $productId): bool {
        if ($userId <= 0 || $productId <= 0) return false;
        $stmt = $pdo->prepare("SELECT 1 FROM orders o
            JOIN order_details d ON d.order_id = o.id
            JOIN users u ON u.id = o.user_id
            WHERE o.user_id = ? AND d.product_id = ?
              AND u.role = 'customer'
              AND LOWER(TRIM(o.status)) IN ('đã giao', 'đã thanh toán', 'hoàn thành', 'delivered', 'paid', 'completed')
            LIMIT 1");
        $stmt->execute([$userId, $productId]);
        return (bool)$stmt->fetchColumn();
    }
}
