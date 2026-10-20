<?php
// trekking/tours/my-tours.php
$page_title = "Lịch Sử Tour Của Tôi";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT b.*, t.title as tour_title, t.image as tour_image 
                       FROM tour_bookings b 
                       JOIN tours t ON b.tour_id = t.id 
                       WHERE b.user_id = ? 
                       ORDER BY b.id DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div class="section-header" style="text-align:left; margin-bottom:25px;">
        <span class="section-subtitle">Tài Khoản Khách Hàng</span>
        <h2 class="section-title">Các Chuyến Đi Tôi Đã Đăng Ký</h2>
    </div>

    <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i> Đăng ký tour thành công! Mã booking: <strong><?= htmlspecialchars($_GET['code'] ?? '') ?></strong>. Đội ngũ TrekHub sẽ liên hệ bạn để xác nhận lịch khởi hành sớm nhất.
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Mã Booking</th>
                    <th>Chuyến Đi</th>
                    <th>Ngày Khởi Hành</th>
                    <th>Số Khách</th>
                    <th>Tổng Tiền</th>
                    <th>Trạng Thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($bookings) > 0): ?>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <img src="<?= htmlspecialchars($b['tour_image']) ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">
                                    <span><?= htmlspecialchars($b['tour_title']) ?></span>
                                </div>
                            </td>
                            <td>
                                <strong style="color:#c2410c;">
                                    <i class="fa-regular fa-calendar-days"></i> 
                                    <?= !empty($b['departure_date']) ? date('d/m/Y', strtotime($b['departure_date'])) : 'Liên hệ sắp xếp' ?>
                                </strong>
                            </td>
                            <td><?= $b['number_people'] ?> người</td>
                            <td style="color:var(--accent); font-weight:bold;"><?= format_price($b['total_price']) ?></td>
                            <td>
                                <?php
                                $badge_style = "background:#e2e8f0; color:#334155;";
                                if ($b['status'] === 'Đã xác nhận') $badge_style = "background:#dbeafe; color:#1e40af;";
                                elseif ($b['status'] === 'Đã thanh toán') $badge_style = "background:#dcfce7; color:#166534;";
                                elseif ($b['status'] === 'Đã hủy') $badge_style = "background:#fee2e2; color:#991b1b;";
                                ?>
                                <span style="display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.8rem; font-weight:bold; <?= $badge_style ?>">
                                    <?= htmlspecialchars($b['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:30px; color:var(--gray);">Bạn chưa đăng ký chuyến leo núi nào. Hãy khám phá và trải nghiệm ngay hôm nay!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>