<?php
ob_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn() || getRole() !== 'manager') {
    http_response_code(403);
    exit('Chỉ tài khoản Quản lý được xem thông tin khách hàng.');
}

$current_page = 'customers';
$page_title = 'Chi tiết khách hàng - VNN Travel';

function cdH($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function cdMoney($value): string {
    return number_format((float)$value, 0, ',', '.') . ' đ';
}
function cdDate($value): string {
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '—';
}

$customerId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$customerId || $customerId <= 0) {
    http_response_code(400);
    exit('Mã khách hàng không hợp lệ.');
}

$customer = null;
$orders = [];
$tours = [];
$reviews = [];
$error = '';

try {
    $customerStmt = $pdo->prepare("SELECT
        u.id, u.fullname, u.email, u.phone, u.address, u.status, u.created_at,
        COALESCE(o.order_count, 0) AS order_count,
        COALESCE(o.order_amount, 0) AS order_amount,
        COALESCE(t.tour_count, 0) AS tour_count,
        COALESCE(t.tour_amount, 0) AS tour_amount,
        COALESCE(r.review_count, 0) AS review_count
    FROM users u
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS order_count, SUM(total_amount) AS order_amount
        FROM orders
        WHERE user_id IS NOT NULL AND status IN ('Đã giao', 'Hoàn thành')
        GROUP BY user_id
    ) o ON o.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS tour_count, SUM(total_price) AS tour_amount
        FROM tour_bookings
        WHERE user_id IS NOT NULL AND status IN ('Đã thanh toán', 'Đã hoàn thành')
        GROUP BY user_id
    ) t ON t.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS review_count
        FROM reviews GROUP BY user_id
    ) r ON r.user_id = u.id
    WHERE u.role = 'customer' AND u.id = ? LIMIT 1");
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$customer) {
        http_response_code(404);
        exit('Không tìm thấy tài khoản khách hàng.');
    }

    $orderStmt = $pdo->prepare("SELECT o.order_code, o.created_at, o.total_amount, o.status,
        (SELECT GROUP_CONCAT(CONCAT(COALESCE(p.name,'Sản phẩm'), ' x', od.quantity) SEPARATOR ', ')
         FROM order_details od
         LEFT JOIN products p ON p.id = od.product_id
         WHERE od.order_id = o.id) AS items
        FROM orders o WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 20");
    $orderStmt->execute([$customerId]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

    $tourStmt = $pdo->prepare("SELECT b.booking_code, b.created_at, b.departure_date,
        b.total_price, b.status, t.title
        FROM tour_bookings b LEFT JOIN tours t ON t.id = b.tour_id
        WHERE b.user_id = ? ORDER BY b.id DESC LIMIT 20");
    $tourStmt->execute([$customerId]);
    $tours = $tourStmt->fetchAll(PDO::FETCH_ASSOC);

    $reviewStmt = $pdo->prepare("SELECT r.rating, r.comment, r.created_at, r.status,
        p.name AS product_name, t.title AS tour_name
        FROM reviews r
        LEFT JOIN products p ON p.id = r.product_id
        LEFT JOIN tours t ON t.id = r.tour_id
        WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 30");
    $reviewStmt->execute([$customerId]);
    $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Customer details: ' . $e->getMessage());
    $error = 'Không thể tải thông tin chi tiết. Vui lòng kiểm tra dữ liệu MySQL.';
}

$totalAmount = $customer
    ? (float)$customer['order_amount'] + (float)$customer['tour_amount']
    : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= cdH($page_title) ?></title>
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box}
.cd-page{flex:1;min-width:0;min-height:100vh;padding:24px;background:#f2f5fa;color:#173454;font:15px/1.6 Arial,sans-serif}
.cd-panel{min-width:0;background:white;border:1px solid #e0e8f1;border-radius:14px;padding:23px;margin-bottom:18px;box-shadow:0 3px 15px #1231}
.cd-header{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}
.cd-title{font-size:25px;margin:0;color:#10294b;line-height:1.4}
.cd-muted{margin:7px 0 0;color:#64748b;font-size:14px}
.cd-button{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:43px;padding:10px 16px;border-radius:9px;background:#e2e8f0;color:#334155!important;text-decoration:none;font-size:14px;font-weight:800}
.cd-profile{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap}
.cd-profile h2{margin:0;font-size:23px;color:#10294b;overflow-wrap:anywhere}
.cd-profile p{margin:8px 0;color:#64748b;overflow-wrap:anywhere}
.cd-status{display:inline-block;padding:7px 12px;border-radius:24px;background:#dcfce7;color:#166534;font-weight:800;font-size:13px;white-space:nowrap}
.cd-status.off{background:#fee2e2;color:#991b1b}
.cd-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:20px}
.cd-stat{min-width:0;padding:17px;border:1px solid #dce6f1;border-radius:12px;background:#f8fafc}
.cd-stat span{display:block;color:#64748b;font-size:13px}
.cd-stat strong{display:block;margin-top:7px;font-size:22px;color:#123b66;overflow-wrap:anywhere}
.cd-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.cd-section-title{font-size:19px;font-weight:800;color:#10294b;margin:0 0 15px}
.cd-section-title i{margin-right:7px;color:#166534}
.cd-table-scroll{width:100%;overflow-x:auto;border:1px solid #e2e8f0;border-radius:10px}
.cd-table{border-collapse:collapse;width:100%;min-width:600px;table-layout:fixed}
.cd-table th,.cd-table td{padding:13px 12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:middle;font-size:13px;line-height:1.6;overflow-wrap:anywhere}
.cd-table th{background:#f8fafc;color:#173454;font-weight:800;white-space:nowrap}
.cd-table tbody tr:last-child td{border-bottom:0}
.cd-amount{font-weight:800;color:#166534;white-space:nowrap}
.cd-review{padding:17px 0;border-bottom:1px solid #e2e8f0}
.cd-review:last-child{border:0}
.cd-review-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.cd-review strong{color:#10294b}
.cd-review small{color:#64748b}
.cd-stars{color:#ea580c;font-size:18px;letter-spacing:2px}
.cd-review p{margin:8px 0 0;white-space:pre-wrap;overflow-wrap:anywhere}
.cd-empty{color:#64748b;padding:21px;text-align:center}
.cd-warning{border-radius:9px;padding:13px 16px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;margin-top:17px}
.cd-error{padding:15px;border-radius:9px;background:#fee2e2;color:#991b1b}
@media(max-width:1150px){.cd-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.cd-columns{grid-template-columns:1fr}}
@media(max-width:650px){.cd-page{padding:12px}.cd-panel{padding:16px}.cd-stats{grid-template-columns:1fr}.cd-title{font-size:21px}}
</style>
</head>
<body class="admin-body">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="admin-main cd-page">
<section class="cd-panel cd-header">
    <div>
        <h1 class="cd-title"><i class="fa-solid fa-address-card"></i> CHI TIẾT KHÁCH HÀNG</h1>
        <p class="cd-muted">Thông tin tài khoản, đơn hàng, đặt tour và đánh giá của khách hàng.</p>
    </div>
    <a class="cd-button" href="index.php"><i class="fa-solid fa-arrow-left"></i> Quay lại danh sách</a>
</section>
<?php if ($error !== ''): ?>
<section class="cd-panel"><div class="cd-error"><?= cdH($error) ?></div></section>
<?php endif; ?>
<?php if ($customer): ?>
<section class="cd-panel">
    <div class="cd-profile">
        <div>
            <h2><i class="fa-solid fa-circle-user"></i> <?= cdH($customer['fullname']) ?> <small style="color:#64748b">#<?= (int)$customer['id'] ?></small></h2>
            <p><i class="fa-solid fa-envelope"></i> <?= cdH($customer['email']) ?> &nbsp; <i class="fa-solid fa-phone"></i> <?= cdH($customer['phone'] ?: 'Chưa cập nhật') ?></p>
            <p><i class="fa-solid fa-calendar"></i> Tham gia: <?= cdDate($customer['created_at']) ?></p>
            <p><i class="fa-solid fa-location-dot"></i> Địa chỉ: <?= cdH($customer['address'] ?: 'Chưa cập nhật') ?></p>
        </div>
        <span class="cd-status <?= (int)$customer['status'] === 1 ? '' : 'off' ?>"><?= (int)$customer['status'] === 1 ? 'Đang hoạt động' : 'Đang khóa' ?></span>
    </div>
    <div class="cd-stats">
        <div class="cd-stat"><span>Đơn hàng hoàn tất</span><strong><?= (int)$customer['order_count'] ?></strong></div>
        <div class="cd-stat"><span>Tour đã trả / hoàn thành</span><strong><?= (int)$customer['tour_count'] ?></strong></div>
        <div class="cd-stat"><span>Giá trị đơn hoàn tất</span><strong><?= cdMoney($customer['order_amount']) ?></strong></div>
        <div class="cd-stat"><span>Tổng giá trị giao dịch hoàn tất</span><strong><?= cdMoney($totalAmount) ?></strong></div>
    </div>
</section>
<div class="cd-columns">
    <section class="cd-panel">
        <h2 class="cd-section-title"><i class="fa-solid fa-bag-shopping"></i> Lịch sử mua hàng (20 đơn mới nhất)</h2>
        <div class="cd-table-scroll"><table class="cd-table">
            <colgroup><col style="width:21%"><col style="width:32%"><col style="width:17%"><col style="width:16%"><col style="width:14%"></colgroup>
            <thead><tr><th>Mã đơn</th><th>Sản phẩm</th><th>Ngày</th><th>Trạng thái</th><th>Giá trị</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
            <tr><td><?= cdH($order['order_code']) ?></td><td><?= cdH($order['items'] ?: '—') ?></td><td><?= cdDate($order['created_at']) ?></td><td><?= cdH($order['status']) ?></td><td class="cd-amount"><?= cdMoney($order['total_amount']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="5" class="cd-empty">Chưa có đơn hàng.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
    <section class="cd-panel">
        <h2 class="cd-section-title"><i class="fa-solid fa-person-hiking"></i> Lịch sử đặt tour (20 đơn mới nhất)</h2>
        <div class="cd-table-scroll"><table class="cd-table">
            <colgroup><col style="width:24%"><col style="width:28%"><col style="width:17%"><col style="width:17%"><col style="width:14%"></colgroup>
            <thead><tr><th>Mã booking</th><th>Tour</th><th>Khởi hành</th><th>Trạng thái</th><th>Giá trị</th></tr></thead>
            <tbody>
            <?php foreach ($tours as $tour): ?>
            <tr><td><?= cdH($tour['booking_code']) ?></td><td><?= cdH($tour['title'] ?: 'Tour không còn tồn tại') ?></td><td><?= cdH($tour['departure_date']) ?></td><td><?= cdH($tour['status']) ?></td><td class="cd-amount"><?= cdMoney($tour['total_price']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$tours): ?><tr><td colspan="5" class="cd-empty">Chưa có đăng ký tour.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</div>
<section class="cd-panel">
    <h2 class="cd-section-title"><i class="fa-solid fa-star"></i> Lịch sử đánh giá & nhận xét (<?= count($reviews) ?> bản ghi mới nhất)</h2>
    <?php foreach ($reviews as $review): ?>
        <article class="cd-review">
            <div class="cd-review-head">
                <strong><?= cdH($review['product_name'] ?: ($review['tour_name'] ?: 'Đối tượng không còn tồn tại')) ?></strong>
                <small><?= cdDate($review['created_at']) ?> — <?= (int)$review['status'] === 1 ? 'Đang hiển thị' : 'Đã ẩn' ?></small>
            </div>
            <div><span class="cd-stars"><?= str_repeat('★', max(0, min(5, (int)$review['rating']))) ?></span> <small>(<?= (int)$review['rating'] ?>/5)</small></div>
            <p><?= cdH($review['comment']) ?></p>
        </article>
    <?php endforeach; ?>
    <?php if (!$reviews): ?><p class="cd-empty">Khách hàng chưa có nhận xét.</p><?php endif; ?>
    <p class="cd-warning">Tổng giá trị giao dịch tính đơn hàng Đã giao/Hoàn thành và tour Đã thanh toán/Đã hoàn thành. Đơn COD đã giao chưa chắc đã được xác nhận thực thu nếu hệ thống chưa lưu trạng thái thanh toán riêng.</p>
</section>
<?php endif; ?>
</main>
</body>
</html>
