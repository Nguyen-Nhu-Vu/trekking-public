<?php
// trekking/shop/my-orders.php
$page_title = "Đơn Hàng Của Tôi";
$active_menu = 'shop';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div class="section-header" style="text-align:left; margin-bottom:25px;">
        <span class="section-subtitle">Tài Khoản Khách Hàng</span>
        <h2 class="section-title">Lịch Sử Mua Trang Bị</h2>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i> Đặt hàng thành công! Mã đơn của bạn là: <strong><?= htmlspecialchars($_GET['code'] ?? '') ?></strong>. Đội ngũ kho TrekHub sẽ đóng gói và bàn giao bưu tá sớm nhất.
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Mã Đơn Hàng</th>
                    <th>Người Nhận</th>
                    <th>Địa Chỉ Nhận</th>
                    <th>Tổng Tiền</th>
                    <th>Ngày Đặt</th>
                    <th>Trạng Thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($o['order_code']) ?></strong></td>
                            <td><?= htmlspecialchars($o['fullname']) ?></td>
                            <td><?= htmlspecialchars($o['address']) ?></td>
                            <td style="color:var(--accent); font-weight:bold;"><?= format_price($o['total_amount']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                            <td>
                                <?php
                                $badge_style = "background:#e2e8f0; color:#334155;";
                                if ($o['status'] === 'Đã xác nhận') $badge_style = "background:#dbeafe; color:#1e40af;";
                                elseif ($o['status'] === 'Đang giao') $badge_style = "background:#ffedd5; color:#c2410c;";
                                elseif ($o['status'] === 'Đã giao') $badge_style = "background:#dcfce7; color:#166534;";
                                elseif ($o['status'] === 'Đã hủy') $badge_style = "background:#fee2e2; color:#991b1b;";
                                ?>
                                <span style="display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.8rem; font-weight:bold; <?= $badge_style ?>">
                                    <?= htmlspecialchars($o['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:30px; color:var(--gray);">Bạn chưa mua đơn hàng nào. Hãy ghé qua cửa hàng đồ dã ngoại nhé!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>