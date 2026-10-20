<?php
// trekking/admin/revenue-shop.php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireManager();

$current_page = 'revenue_shop';

// Thống kê doanh thu bán hàng
$total_shop_rev = $pdo->query("SELECT IFNULL(SUM(total_amount), 0) FROM orders WHERE status != 'Đã hủy'")->fetchColumn();
$paid_shop_rev = $pdo->query("SELECT IFNULL(SUM(total_amount), 0) FROM orders WHERE status = 'Đã giao'")->fetchColumn();
$delivering_shop_rev = $pdo->query("SELECT IFNULL(SUM(total_amount), 0) FROM orders WHERE status = 'Đang giao'")->fetchColumn();

// Danh sách các đơn hàng hoàn tất gần đây
$recent_orders = $pdo->query("SELECT * FROM orders WHERE status != 'Đã hủy' ORDER BY id DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Báo Cáo Doanh Thu Bán Hàng Dã Ngoại - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>BÁO CÁO TÀI CHÍNH: DOANH THU CỬA HÀNG DỤNG CỤ</strong></div>
        </div>

        <div class="admin-content">
            <div class="stat-card-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="stat-widget">
                    <div>
                        <p>TỔNG DOANH THU ĐƠN HÀNG</p>
                        <h3 style="color:#ea580c;"><?= format_price($total_shop_rev) ?></h3>
                    </div>
                    <i class="fa-solid fa-box-open" style="color:#ea580c;"></i>
                </div>
                <div class="stat-widget">
                    <div>
                        <p>ĐƠN ĐÃ GIAO THÀNH CÔNG</p>
                        <h3 style="color:#16a34a;"><?= format_price($paid_shop_rev) ?></h3>
                    </div>
                    <i class="fa-solid fa-truck-ramp-box" style="color:#16a34a;"></i>
                </div>
                <div class="stat-widget">
                    <div>
                        <p>TIỀN HÀNG ĐANG GIAO</p>
                        <h3 style="color:#0284c7;"><?= format_price($delivering_shop_rev) ?></h3>
                    </div>
                    <i class="fa-solid fa-truck-fast" style="color:#0284c7;"></i>
                </div>
            </div>

            <div style="background:white; padding:25px; border-radius:10px; box-shadow:var(--shadow);">
                <h4 style="margin-bottom:18px; color:#1e293b;"><i class="fa-solid fa-receipt"></i> Đơn Hàng Mới Nhất Đem Lại Doanh Thu</h4>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Mã Đơn</th>
                                <th>Khách Hàng</th>
                                <th>Số Tiền</th>
                                <th>Trạng Thái</th>
                                <th>Ngày Tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $ro): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ro['order_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($ro['fullname']) ?></td>
                                    <td style="color:#ea580c; font-weight:bold;"><?= format_price($ro['total_amount']) ?></td>
                                    <td><span class="badge" style="background:#0284c7;"><?= htmlspecialchars($ro['status']) ?></span></td>
                                    <td><?= date('d/m/Y H:i', strtotime($ro['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>