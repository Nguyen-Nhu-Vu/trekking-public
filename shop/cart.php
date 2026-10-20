<?php
// trekking/shop/cart.php
$page_title = "Giỏ Hàng Của Bạn";
$active_menu = 'shop';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Xóa 1 sản phẩm
if (isset($_GET['remove'])) {
    $rid = intval($_GET['remove']);
    unset($_SESSION['cart'][$rid]);
    header("Location: " . BASE_URL . "shop/cart.php");
    exit;
}

// Xóa toàn bộ
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header("Location: " . BASE_URL . "shop/cart.php");
    exit;
}

// Cập nhật số lượng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantities'] as $pid => $qty) {
        $qty = intval($qty);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$pid]);
        } else {
            if (isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid]['quantity'] = $qty;
            }
        }
    }
    header("Location: " . BASE_URL . "shop/cart.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping_fee = ($subtotal > 0 && $subtotal < 1000000) ? 35000 : 0;
$total = $subtotal + $shipping_fee;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <h2 style="margin-bottom:25px;"><i class="fa-solid fa-cart-shopping"></i> GIỎ HÀNG CỦA BẠN</h2>

    <?php if (count($cart) > 0): ?>
        <form action="" method="POST">
            <input type="hidden" name="update_cart" value="1">
            <div class="grid grid-3" style="align-items:start;">
                <div style="grid-column: span 2;" class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Ảnh</th>
                                <th>Tên Sản Phẩm</th>
                                <th>Đơn Giá</th>
                                <th style="width:100px;">Số Lượng</th>
                                <th>Thành Tiền</th>
                                <th>Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $pid => $item): ?>
                                <tr>
                                    <td><img src="<?= htmlspecialchars($item['image']) ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px;"></td>
                                    <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                    <td><?= format_price($item['price']) ?></td>
                                    <td>
                                        <input type="number" name="quantities[<?= $pid ?>]" value="<?= $item['quantity'] ?>" min="1" class="form-control" style="padding:4px; text-align:center;">
                                    </td>
                                    <td style="color:var(--accent); font-weight:bold;"><?= format_price($item['price'] * $item['quantity']) ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>shop/cart.php?remove=<?= $pid ?>" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="display:flex; justify-content:space-between; margin-top:15px;">
                        <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-rotate"></i> Cập nhật giỏ hàng</button>
                        <a href="<?= BASE_URL ?>shop/cart.php?clear=1" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc muốn xóa sạch giỏ hàng?');">Xóa toàn bộ</a>
                    </div>
                </div>

                <!-- CỘT TỔNG TIỀN -->
                <div style="background:white; padding:25px; border-radius:10px; border:1px solid #fed7aa; box-shadow:var(--shadow);">
                    <h3 style="border-bottom:1px solid #e2e8f0; padding-bottom:10px; margin-bottom:15px;">Tóm Tắt Đơn Hàng</h3>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span>Tạm tính:</span>
                        <strong><?= format_price($subtotal) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                        <span>Phí vận chuyển:</span>
                        <strong><?= $shipping_fee == 0 ? '<span style="color:#16a34a;">Miễn phí</span>' : format_price($shipping_fee) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:1.25rem; font-weight:800; color:var(--accent); border-top:1px solid #e2e8f0; padding-top:12px; margin-bottom:20px;">
                        <span>Tổng thanh toán:</span>
                        <span><?= format_price($total) ?></span>
                    </div>
                    <a href="<?= BASE_URL ?>shop/checkout.php" class="btn btn-primary" style="display:block; text-align:center; width:100%; padding:14px; border-radius:30px;">
                        TIẾN HÀNH ĐẶT HÀNG &rarr;
                    </a>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div style="text-align:center; padding:70px 0; background:white; border-radius:12px; box-shadow:var(--shadow);">
            <i class="fa-solid fa-basket-shopping" style="font-size:3.5rem; color:#cbd5e1; margin-bottom:15px; display:block;"></i>
            <h3>Giỏ hàng của bạn đang trống!</h3>
            <p style="color:#64748b; margin-bottom:20px;">Hãy dạo một vòng cửa hàng để chọn cho mình những món đồ leo núi ưng ý.</p>
            <a href="<?= BASE_URL ?>shop/shop.php" class="btn btn-primary"><i class="fa-solid fa-store"></i> Đến Cửa Hàng Ngay</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>