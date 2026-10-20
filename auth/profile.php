<?php
// trekking/auth/profile.php
$page_title = "Hồ Sơ Cá Nhân - VNN Travel";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    if (empty($fullname)) {
        $error = "Họ tên không được để trống!";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, phone = ? WHERE id = ?");
        $stmt->execute([$fullname, $phone, $user_id]);
        $_SESSION['fullname'] = $fullname;
        $msg = "Cập nhật hồ sơ cá nhân thành công!";
    }
}

$u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$u_stmt->execute([$user_id]);
$user = $u_stmt->fetch();

// Thống kê số lượng tour và đơn hàng của khách hàng này
$my_tour_count = $pdo->prepare("SELECT COUNT(*) FROM tour_bookings WHERE user_id = ?");
$my_tour_count->execute([$user_id]);
$total_tours = $my_tour_count->fetchColumn();

$my_order_count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$my_order_count->execute([$user_id]);
$total_orders = $my_order_count->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div style="max-width:650px; margin:0 auto;">
        
        <!-- CÁC LỐI TẮT QUẢN LÝ DÀNH CHO KHÁCH HÀNG (ĐÃ LÀM SẠCH 100% CHỮ RÁC) -->
        <div class="grid grid-2" style="margin-bottom:25px; gap:18px;">
            <a href="<?= BASE_URL ?>tours/my-tours.php" style="background:#fff7ed; border:1px solid #fed7aa; padding:20px; border-radius:12px; display:flex; align-items:center; gap:16px; text-decoration:none; box-shadow:0 2px 8px rgba(0,0,0,0.04); transition:transform 0.2s;">
                <div style="width:50px; height:50px; border-radius:50%; background:#ea580c; color:white; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                    <i class="fa-solid fa-ticket"></i>
                </div>
                <div>
                    <h4 style="margin:0; color:#9a3412; font-size:1.05rem;">Tour Tôi Đã Đăng Ký</h4>
                    <span style="font-size:0.85rem; color:#c2410c; font-weight:700;">Đang có <?= $total_tours ?> chuyến đi</span>
                </div>
            </a>

            <a href="<?= BASE_URL ?>shop/my-orders.php" style="background:#f0fdf4; border:1px solid #bbf7d0; padding:20px; border-radius:12px; display:flex; align-items:center; gap:16px; text-decoration:none; box-shadow:0 2px 8px rgba(0,0,0,0.04); transition:transform 0.2s;">
                <div style="width:50px; height:50px; border-radius:50%; background:#16a34a; color:white; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div>
                    <h4 style="margin:0; color:#166534; font-size:1.05rem;">Đơn Mua Thiết Bị</h4>
                    <span style="font-size:0.85rem; color:#15803d; font-weight:700;">Đang có <?= $total_orders ?> đơn hàng</span>
                </div>
            </a>
        </div>

        <!-- FORM THÔNG TIN CÁ NHÂN -->
        <div style="background:white; padding:35px; border-radius:12px; box-shadow:var(--shadow); border:1px solid #e2e8f0;">
            <h2 style="color:var(--primary); margin-bottom:20px; text-align:center;">THÔNG TIN CÁ NHÂN</h2>

            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Địa chỉ Email (Cố định)</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f1f5f9;">
                </div>
                <div class="form-group">
                    <label>Họ và tên *</label>
                    <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Số điện thoại liên lạc</label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Vai trò tài khoản</label>
                    <input type="text" class="form-control" value="<?= strtoupper($user['role']) ?>" disabled style="background:#f1f5f9; font-weight:bold;">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; margin-top:10px;">LƯU THAY ĐỔI</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>