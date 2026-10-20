<?php
// trekking/admin/revenue-tours.php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireManager();

$current_page = 'revenue_tours';

// Thống kê doanh thu tour
$total_tour_rev = $pdo->query("SELECT IFNULL(SUM(total_price), 0) FROM tour_bookings WHERE status != 'Đã hủy'")->fetchColumn();
$paid_tour_rev = $pdo->query("SELECT IFNULL(SUM(total_price), 0) FROM tour_bookings WHERE status IN ('Đã xác nhận', 'Đã thanh toán', 'Đã hoàn thành')")->fetchColumn();
$pending_tour_rev = $pdo->query("SELECT IFNULL(SUM(total_price), 0) FROM tour_bookings WHERE status = 'Chờ xác nhận'")->fetchColumn();

// Danh sách tour có doanh thu cao nhất
$top_tours = $pdo->query("SELECT t.title, SUM(b.total_price) as revenue, COUNT(b.id) as total_bookings, SUM(b.number_people) as guests 
                          FROM tour_bookings b 
                          JOIN tours t ON b.tour_id = t.id 
                          WHERE b.status != 'Đã hủy' 
                          GROUP BY t.id 
                          ORDER BY revenue DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Báo Cáo Doanh Thu Tour Trekking - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>BÁO CÁO TÀI CHÍNH: DOANH THU TOUR LEO NÚI</strong></div>
        </div>

        <div class="admin-content">
            <div class="stat-card-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="stat-widget">
                    <div>
                        <p>TỔNG DOANH THU TOUR</p>
                        <h3 style="color:#1e5631;"><?= format_price($total_tour_rev) ?></h3>
                    </div>
                    <i class="fa-solid fa-mountain" style="color:#1e5631;"></i>
                </div>
                <div class="stat-widget">
                    <div>
                        <p>DOANH THU ĐÃ THU ĐƯỢC</p>
                        <h3 style="color:#16a34a;"><?= format_price($paid_tour_rev) ?></h3>
                    </div>
                    <i class="fa-solid fa-circle-check" style="color:#16a34a;"></i>
                </div>
                <div class="stat-widget">
                    <div>
                        <p>DOANH THU CHỜ DUYỆT</p>
                        <h3 style="color:#eab308;"><?= format_price($pending_tour_rev) ?></h3>
                    </div>
                    <i class="fa-solid fa-clock-rotate-left" style="color:#eab308;"></i>
                </div>
            </div>

            <div style="background:white; padding:25px; border-radius:10px; box-shadow:var(--shadow);">
                <h4 style="margin-bottom:18px; color:#1e293b;"><i class="fa-solid fa-trophy" style="color:#f59e0b;"></i> Top Các Tour Có Doanh Thu Cao Nhất</h4>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tên Tour</th>
                                <th>Số Lượt Đặt</th>
                                <th>Tổng Số Khách</th>
                                <th>Tổng Doanh Thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_tours as $tt): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($tt['title']) ?></strong></td>
                                    <td><?= $tt['total_bookings'] ?> lượt</td>
                                    <td><?= $tt['guests'] ?> khách</td>
                                    <td style="color:#1e5631; font-weight:bold;"><?= format_price($tt['revenue']) ?></td>
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