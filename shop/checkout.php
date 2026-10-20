<?php
// trekking/shop/checkout.php
$page_title = "Thanh Toán Đơn Hàng";
$active_menu = 'shop';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: " . BASE_URL . "shop/shop.php");
    exit;
}

$user = null;
if (isLoggedIn()) {
    $u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $u_stmt->execute([$_SESSION['user_id']]);
    $user = $u_stmt->fetch();
}

$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping_fee = ($subtotal >= 1000000) ? 0 : 35000;
$total = $subtotal + $shipping_fee;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if (empty($fullname) || empty($phone) || empty($address)) {
        $error = "Vui lòng nhập đầy đủ họ tên, điện thoại và địa chỉ giao hàng!";
    } else {
        $order_code = 'ORD-' . date('Ymd') . '-' . rand(100, 999);
        $user_id = $_SESSION['user_id'] ?? null;

        $pdo->beginTransaction();
        try {
            $ins_o = $pdo->prepare("INSERT INTO orders (order_code, user_id, fullname, phone, email, address, total_amount, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xác nhận')");
            $ins_o->execute([$order_code, $user_id, $fullname, $phone, $email, $address, $total, $notes]);
            $order_id = $pdo->lastInsertId();

            $ins_detail = $pdo->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $up_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

            foreach ($cart as $item) {
                $ins_detail->execute([$order_id, $item['id'], $item['quantity'], $item['price']]);
                $up_stock->execute([$item['quantity'], $item['id']]);
            }

            $pdo->commit();
            unset($_SESSION['cart']);
            header("Location: " . BASE_URL . "shop/my-orders.php?success=1&code=" . $order_code);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Lỗi khi tạo đơn hàng: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div style="max-width:850px; margin:0 auto; background:white; padding:35px; border-radius:12px; box-shadow:var(--shadow-lg);">
        <h2 style="color:var(--primary); margin-bottom:20px; text-align:center;">THANH TOÁN ĐƠN HÀNG</h2>

        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <form action="" method="POST">
            <div class="grid grid-2">
                <div>
                    <h4 style="margin-bottom:15px; color:#1e293b;"><i class="fa-solid fa-truck"></i> Thông Tin Giao Hàng</h4>
                    <div class="form-group">
                        <label>Họ và tên *</label>
                        <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại nhận hàng *</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Địa chỉ Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Địa chỉ giao nhận cụ thể *</label>
                        <input type="text" name="address" class="form-control" placeholder="Số nhà, tên đường, phường/xã..." required>
                    </div>
                    <div class="form-group">
                        <label>Ghi chú đơn hàng</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Giao giờ hành chính..."></textarea>
                    </div>
                </div>

                <div>
                    <h4 style="margin-bottom:15px; color:#1e293b;"><i class="fa-solid fa-receipt"></i> Đơn Hàng Của Bạn</h4>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:18px; margin-bottom:20px;">
                        <?php foreach ($cart as $item): ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:0.9rem;">
                                <span><?= htmlspecialchars($item['name']) ?> × <?= $item['quantity'] ?></span>
                                <strong><?= format_price($item['price'] * $item['quantity']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <div style="border-top:1px solid #e2e8f0; margin-top:10px; padding-top:10px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                <span>Phí giao hàng:</span>
                                <span><?= $shipping_fee == 0 ? 'Miễn phí' : format_price($shipping_fee) ?></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:1.2rem; font-weight:800; color:var(--accent);">
                                <span>Tổng cộng:</span>
                                <span><?= format_price($total) ?></span>
                            </div>
                        </div>
                    </div>

                    <div style="background:#fff7ed; padding:12px; border-radius:6px; font-size:0.85rem; margin-bottom:20px; border:1px solid #fed7aa;">
                        <i class="fa-solid fa-shield-check" style="color:var(--accent);"></i> Phương thức: <strong>Thanh toán khi nhận hàng (COD)</strong>
                    </div>

                    <button type="submit" class="btn btn-accent" style="width:100%; padding:14px; font-size:1.1rem; border-radius:30px;">
                        XÁC NHẬN ĐẶT HÀNG
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>