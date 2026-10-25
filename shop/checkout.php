
<?php
// VNN Travel - Checkout COD
// Database: Aiven MySQL
// Tables: orders, order_details
// Success popup - no redirect after POST

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$page_title = 'Thanh Toán Đơn Hàng';
$active_menu = 'shop';

$base = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') . '/';

function vn_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function vn_money($v) {
    return number_format((float)$v, 0, ',', '.') . ' đ';
}

function vn_phone($v) {
    return preg_replace('/[^0-9]/', '', (string)$v);
}

function vn_cart() {
    $cart = $_SESSION['cart'] ?? [];
    $items = [];

    if (!is_array($cart)) return [];

    foreach ($cart as $key => $row) {
        if (!is_array($row)) continue;

        $id = (int)(
            $row['product_id']
            ?? $row['id']
            ?? (is_numeric($key) ? $key : 0)
        );

        $qty = (int)($row['quantity'] ?? $row['qty'] ?? 0);

        $price = (float)(
            $row['price']
            ?? $row['sale_price']
            ?? 0
        );

        if ($id <= 0 || $qty <= 0 || $price < 0) {
            throw new RuntimeException(
                'Giỏ hàng chứa sản phẩm không hợp lệ.'
            );
        }

        $items[] = [
            'id' => $id,
            'name' => (string)(
                $row['name']
                ?? $row['product_name']
                ?? 'Sản phẩm'
            ),
            'size' => (string)(
                $row['size']
                ?? $row['variant']
                ?? ''
            ),
            'qty' => $qty,
            'price' => $price,
            'total' => $price * $qty
        ];
    }

    return $items;
}

function vn_enum(PDO $pdo, $column, $choices) {
    $stmt = $pdo->prepare(
        "SHOW COLUMNS FROM `orders` LIKE ?"
    );
    $stmt->execute([$column]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        throw new RuntimeException(
            'Thiếu cột ' . $column . ' trong bảng orders.'
        );
    }

    if (!preg_match('/^enum\((.*)\)$/is', $info['Type'], $m)) {
        return $choices[0];
    }

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $m[1],
        $matches
    );

    $allowed = array_map('stripcslashes', $matches[1]);

    foreach ($choices as $choice) {
        if (in_array($choice, $allowed, true)) {
            return $choice;
        }
    }

    throw new RuntimeException(
        'Trạng thái MySQL không tương thích: ' . $column
    );
}

$error = '';
$success = null;
$items = [];

$userId = (int)($_SESSION['user_id'] ?? 0);
$user = null;

if ($userId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, fullname, phone, email
             FROM users WHERE id=? LIMIT 1"
        );
        $stmt->execute([$userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) $userId = 0;
    } catch (Throwable $e) {
        error_log('Checkout user: ' . $e->getMessage());
        $error = 'Không thể kiểm tra tài khoản.';
    }
}

try {
    $items = vn_cart();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$subtotal = array_sum(array_column($items, 'total'));

// Phi giao hang nhu ban checkout truoc.
$shippingFee = ($subtotal > 0 && $subtotal < 1000000)
    ? 35000
    : 0;

$total = $subtotal + $shippingFee;

$old = [
    'fullname' => $_POST['fullname']
        ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone']
        ?? ($user['phone'] ?? ''),
    'email' => $_POST['email']
        ?? ($user['email'] ?? ''),
    'address' => $_POST['address'] ?? '',
    'notes' => $_POST['notes'] ?? ''
];

// Ma don duoc giu trong session trong luc checkout.
if (empty($_SESSION['checkout_order_code'])) {
    $_SESSION['checkout_order_code'] =
        'DH' . strtoupper(bin2hex(random_bytes(8)));
}

$orderCode = $_SESSION['checkout_order_code'];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['place_order'])
) {
    $name = trim((string)$old['fullname']);
    $phone = vn_phone($old['phone']);
    $email = trim((string)$old['email']);
    $address = trim((string)$old['address']);
    $notes = trim((string)$old['notes']);

    $submittedCode = (string)($_POST['order_code'] ?? '');

    if (
        !empty($_SESSION['checkout_completed'])
        && $submittedCode !== ''
        && hash_equals(
            (string)$_SESSION['checkout_completed']['code'],
            $submittedCode
        )
    ) {
        // Cung mot form da duoc luu thanh cong.
        // Khong insert lai don hang.
        $success = $_SESSION['checkout_completed']['order'];

    } elseif ($error !== '') {
        // Giu nguyen thong bao loi.

    } elseif ($userId <= 0) {
        $error = 'Vui lòng đăng nhập để đặt hàng.';

    } elseif (
        !$submittedCode
        || !hash_equals((string)$orderCode, $submittedCode)
    ) {
        $error = 'Phiên đặt hàng không hợp lệ. Vui lòng tải lại trang.';

    } elseif (!$items) {
        $error = 'Giỏ hàng đang trống.';

    } elseif (
        $name === ''
        || strlen($phone) < 9
        || strlen($phone) > 20
        || $address === ''
    ) {
        $error = 'Vui lòng nhập đầy đủ thông tin giao hàng.';

    } elseif (
        $email !== ''
        && !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Email không hợp lệ.';

    } else {
        try {
            $pdo->beginTransaction();

            // Xac minh gia trong database.
            $verifiedItems = [];
            $verifiedSubtotal = 0;

            $productStmt = $pdo->prepare(
                "SELECT id, name, price
                 FROM products
                 WHERE id=?
                 FOR UPDATE"
            );

            foreach ($items as $item) {
                $productStmt->execute([$item['id']]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new RuntimeException(
                        'Một sản phẩm không còn tồn tại.'
                    );
                }

                $price = (float)$product['price'];
                $lineTotal = $price * $item['qty'];

                $verifiedItems[] = [
                    'id' => (int)$product['id'],
                    'name' => $product['name'],
                    'size' => $item['size'],
                    'qty' => $item['qty'],
                    'price' => $price,
                    'total' => $lineTotal
                ];

                $verifiedSubtotal += $lineTotal;
            }

            $verifiedShipping = (
                $verifiedSubtotal > 0
                && $verifiedSubtotal < 1000000
            ) ? 35000 : 0;

            $verifiedTotal =
                $verifiedSubtotal + $verifiedShipping;

            $orderStatus = vn_enum(
                $pdo,
                'status',
                ['Chờ xác nhận', 'Chờ xử lý', 'pending']
            );

            $paymentMethod = vn_enum(
                $pdo,
                'payment_method',
                ['COD', 'cod', 'cash', 'Thanh toán khi nhận hàng']
            );

            // Luu don hang.
            $stmt = $pdo->prepare(
                "INSERT INTO orders
                (
                    order_code,
                    user_id,
                    fullname,
                    phone,
                    email,
                    address,
                    payment_method,
                    shipping_fee,
                    total_amount,
                    notes,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $orderCode,
                $userId,
                $name,
                $phone,
                $email,
                $address,
                $paymentMethod,
                $verifiedShipping,
                $verifiedTotal,
                $notes,
                $orderStatus
            ]);

            $orderId = (int)$pdo->lastInsertId();

            if ($orderId <= 0) {
                throw new RuntimeException(
                    'Không tạo được mã đơn hàng.'
                );
            }

            // Luu chi tiet don hang.
            $detailStmt = $pdo->prepare(
                "INSERT INTO order_details
                (
                    order_id,
                    product_id,
                    price,
                    quantity,
                    total
                )
                VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($verifiedItems as $item) {
                $detailStmt->execute([
                    $orderId,
                    $item['id'],
                    $item['price'],
                    $item['qty'],
                    $item['total']
                ]);
            }

            $pdo->commit();

            // Giao dich da thanh cong.
            $success = [
                'id' => $orderId,
                'code' => $orderCode,
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'items' => $verifiedItems,
                'subtotal' => $verifiedSubtotal,
                'shipping' => $verifiedShipping,
                'total' => $verifiedTotal
            ];

            $_SESSION['checkout_completed'] = [
                'code' => $orderCode,
                'order' => $success
            ];

            // Xoa gio hang sau khi commit.
            $_SESSION['cart'] = [];
            $_SESSION['cart_count'] = 0;

            unset($_SESSION['checkout_order_code']);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('COD Checkout: ' . $e->getMessage());

            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể tạo đơn hàng. Vui lòng thử lại.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.co-page {
    background:#f8fafc;
    min-height:75vh;
    padding:40px 16px 75px;
}
.co-wrap {
    max-width:1160px;
    margin:auto;
}
.co-title {
    text-align:center;
    color:#14532d;
    font-size:29px;
    font-weight:800;
    margin:0 0 30px;
}
.co-grid {
    display:grid;
    grid-template-columns:1.1fr .9fr;
    gap:24px;
    align-items:start;
}
.co-card {
    background:#fff;
    border-radius:16px;
    padding:26px;
    box-shadow:0 5px 22px rgba(0,0,0,.06);
}
.co-card h3 {
    color:#0f172a;
    font-size:19px;
    margin:0 0 23px;
}
.co-field {
    margin-bottom:18px;
}
.co-field label {
    display:block;
    font-weight:700;
    margin-bottom:8px;
}
.co-field input,
.co-field textarea {
    width:100%;
    box-sizing:border-box;
    padding:13px 15px;
    border:1px solid #d7e0eb;
    border-radius:10px;
    font:inherit;
}
.co-field textarea {
    min-height:105px;
    resize:vertical;
}
.co-two {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.co-payment {
    border:1px solid #86efac;
    background:#f0fdf4;
    border-radius:12px;
    padding:19px;
    line-height:1.8;
}
.co-payment strong {
    color:#14532d;
}
.co-payment p {
    color:#475569;
    font-size:14px;
    margin:8px 0 0;
}
.co-item {
    padding:15px 0;
    border-bottom:1px solid #e5e7eb;
}
.co-item-name {
    font-weight:800;
    color:#0f172a;
    margin-bottom:8px;
}
.co-row {
    display:flex;
    justify-content:space-between;
    gap:15px;
    margin:13px 0;
}
.co-total {
    border-top:1px solid #e5e7eb;
    padding-top:18px;
    font-size:21px;
    color:#ea580c;
    font-weight:900;
}
.co-btn {
    display:block;
    width:100%;
    background:#ea580c;
    color:white;
    border:0;
    border-radius:11px;
    padding:16px;
    font-size:17px;
    font-weight:800;
    cursor:pointer;
    margin-top:20px;
}
.co-btn:disabled {
    opacity:.6;
    cursor:wait;
}
.co-alert {
    border-radius:11px;
    padding:17px;
    margin-bottom:22px;
}
.co-error {
    background:#fee2e2;
    color:#b91c1c;
}
.co-muted {
    color:#64748b;
    font-size:13px;
    line-height:1.7;
    margin-top:14px;
}
.co-link {
    display:inline-block;
    background:#14532d;
    color:white;
    border-radius:10px;
    text-decoration:none;
    padding:13px 22px;
    font-weight:700;
}

/* POPUP THANH CONG */
.cod-overlay {
    position:fixed;
    inset:0;
    z-index:999999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:18px;
    box-sizing:border-box;
    background:rgba(15,23,42,.65);
    backdrop-filter:blur(5px);
}
.cod-overlay.show {
    display:flex;
}
.cod-modal {
    position:relative;
    background:white;
    border-radius:20px;
    width:100%;
    max-width:460px;
    max-height:90vh;
    overflow-y:auto;
    padding:36px 30px 30px;
    box-sizing:border-box;
    box-shadow:0 25px 70px rgba(0,0,0,.25);
    animation:codPop .2s ease-out;
    text-align:center;
}
@keyframes codPop {
    from {opacity:0;transform:scale(.94) translateY(12px)}
    to {opacity:1;transform:scale(1) translateY(0)}
}
.cod-close {
    position:absolute;
    top:14px;
    right:14px;
    width:36px;
    height:36px;
    border:0;
    border-radius:50%;
    background:#f1f5f9;
    color:#475569;
    font-size:22px;
    cursor:pointer;
}
.cod-close:hover {
    background:#e2e8f0;
}
.cod-success-icon {
    width:78px;
    height:78px;
    border-radius:50%;
    background:#dcfce7;
    color:#16a34a;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:43px;
    margin:5px auto 20px;
}
.cod-modal h2 {
    font-size:25px;
    color:#14532d;
    margin:0 0 13px;
}
.cod-modal-desc {
    color:#64748b;
    line-height:1.7;
    margin:0 0 22px;
}
.cod-order-box {
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:18px;
    text-align:left;
}
.cod-order-box .co-row {
    font-size:14px;
    margin:12px 0;
    overflow-wrap:anywhere;
}
.cod-order-box .co-total {
    font-size:18px;
    margin-top:14px;
}
.cod-modal-note {
    background:#f0fdf4;
    color:#166534;
    font-size:14px;
    line-height:1.6;
    padding:13px;
    border-radius:10px;
    margin:18px 0;
}
.cod-modal-button {
    width:100%;
    padding:14px;
    border:0;
    border-radius:11px;
    background:#14532d;
    color:white;
    font-size:16px;
    font-weight:800;
    cursor:pointer;
}
body.cod-popup-open {
    overflow:hidden;
}
@media(max-width:850px) {
    .co-grid,.co-two {grid-template-columns:1fr}
    .co-card {padding:20px}
    .co-title {font-size:24px}
    .cod-modal {padding:34px 20px 22px}
}
</style>

<section class="co-page">
<div class="co-wrap">

<h1 class="co-title">THANH TOÁN ĐƠN HÀNG</h1>

<?php if ($userId <= 0 && !$success): ?>

<div class="co-card" style="max-width:650px;margin:auto;text-align:center">
    <h3>Vui lòng đăng nhập</h3>
    <p>Bạn cần đăng nhập để đặt hàng.</p>
    <a class="co-link" href="<?= vn_h($base) ?>auth/login.php">
        Đăng nhập
    </a>
</div>

<?php elseif (empty($items) && !$success && !$error): ?>

<div class="co-card" style="text-align:center">
    <h3>Giỏ hàng đang trống</h3>
    <a class="co-link" href="<?= vn_h($base) ?>shop/shop.php">
        Tiếp tục mua sắm
    </a>
</div>

<?php elseif (!$success): ?>

<?php if ($error): ?>
<div class="co-alert co-error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?= vn_h($error) ?>
</div>
<?php endif; ?>

<form method="POST" id="co-form">
<input type="hidden" name="place_order" value="1">
<input type="hidden" name="order_code"
       value="<?= vn_h($orderCode) ?>">

<div class="co-grid">

    <div class="co-card">

        <h3>
            <i class="fa-solid fa-truck"></i>
            Thông tin giao hàng
        </h3>

        <div class="co-field">
            <label>Họ và tên *</label>
            <input name="fullname" required
                   value="<?= vn_h($old['fullname']) ?>">
        </div>

        <div class="co-two">
            <div class="co-field">
                <label>Số điện thoại *</label>
                <input type="tel" name="phone" required
                       value="<?= vn_h($old['phone']) ?>">
            </div>
            <div class="co-field">
                <label>Email</label>
                <input type="email" name="email"
                       value="<?= vn_h($old['email']) ?>">
            </div>
        </div>

        <div class="co-field">
            <label>Địa chỉ giao hàng *</label>
            <input name="address" required
                   value="<?= vn_h($old['address']) ?>">
        </div>

        <div class="co-field">
            <label>Ghi chú đơn hàng</label>
            <textarea name="notes"><?= vn_h($old['notes']) ?></textarea>
        </div>

        <h3>Phương thức thanh toán</h3>

        <div class="co-payment">
            <strong>
                <i class="fa-solid fa-money-bill-wave"></i>
                Thanh toán khi nhận hàng (COD)
            </strong>
            <p>
                Không cần quét mã QR hoặc chuyển khoản trước.
                Bạn thanh toán trực tiếp khi nhận hàng.
            </p>
        </div>

    </div>

    <div class="co-card">

        <h3>
            <i class="fa-solid fa-box"></i>
            Đơn hàng của bạn
        </h3>

        <?php foreach ($items as $item): ?>
        <div class="co-item">
            <div class="co-item-name"><?= vn_h($item['name']) ?></div>

            <?php if ($item['size'] !== ''): ?>
            <p class="co-muted">
                Phân loại: <?= vn_h($item['size']) ?>
            </p>
            <?php endif; ?>

            <div class="co-row">
                <span>
                    <?= (int)$item['qty'] ?>
                    × <?= vn_money($item['price']) ?>
                </span>
                <strong><?= vn_money($item['total']) ?></strong>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="co-row">
            <span>Tạm tính</span>
            <strong><?= vn_money($subtotal) ?></strong>
        </div>

        <div class="co-row">
            <span>Phí giao hàng</span>
            <strong>
                <?= $shippingFee == 0
                    ? 'Miễn phí'
                    : vn_money($shippingFee) ?>
            </strong>
        </div>

        <div class="co-row co-total">
            <span>Tổng cộng</span>
            <strong><?= vn_money($total) ?></strong>
        </div>

        <button type="submit" class="co-btn" id="co-submit">
            <i class="fa-solid fa-check"></i>
            XÁC NHẬN ĐẶT HÀNG
        </button>

        <p class="co-muted">
            Đơn hàng sẽ được chuyển đến bộ phận quản lý.
            Bạn chỉ thanh toán khi nhận hàng.
        </p>

    </div>
</div>
</form>

<?php else: ?>

<!-- Trang nen sau khi thanh cong, popup se tu mo. -->
<div class="co-card" style="max-width:700px;margin:auto;text-align:center">
    <h3 style="color:#14532d">Đơn hàng đã được ghi nhận</h3>
    <p>Đơn hàng <?= vn_h($success['code']) ?> đã được lưu.</p>
    <button class="co-btn" type="button" onclick="openCodPopup()">
        Xem thông báo đặt hàng
    </button>
    <a class="co-link" href="<?= vn_h($base) ?>shop/shop.php">
        Tiếp tục mua sắm
    </a>
</div>

<?php endif; ?>

</div>
</section>

<?php if ($success): ?>

<!-- POPUP DAT HANG THANH CONG -->
<div class="cod-overlay show"
     id="codSuccessOverlay"
     role="presentation"
     onclick="if(event.target===this)closeCodPopup()">

    <div class="cod-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="codSuccessTitle">

        <button type="button"
                class="cod-close"
                aria-label="Đóng thông báo"
                onclick="closeCodPopup()">
            &times;
        </button>

        <div class="cod-success-icon">
            <i class="fa-solid fa-check"></i>
        </div>

        <h2 id="codSuccessTitle">
            Đặt hàng thành công!
        </h2>

        <p class="cod-modal-desc">
            Đơn hàng của bạn đã được ghi nhận.
            VNN Travel sẽ xử lý và giao hàng
            đến địa chỉ bạn đã đăng ký.
        </p>

        <div class="cod-order-box">

            <div class="co-row">
                <span>Mã đơn hàng</span>
                <strong><?= vn_h($success['code']) ?></strong>
            </div>

            <div class="co-row">
                <span>Khách hàng</span>
                <strong><?= vn_h($success['name']) ?></strong>
            </div>

            <div class="co-row">
                <span>Thanh toán</span>
                <strong>COD</strong>
            </div>

            <div class="co-row co-total">
                <span>Tổng tiền</span>
                <strong><?= vn_money($success['total']) ?></strong>
            </div>

        </div>

        <div class="cod-modal-note">
            <i class="fa-solid fa-circle-info"></i>
            Bạn chỉ thanh toán khi nhận hàng.
            Không cần chuyển khoản trước.
        </div>

        <button type="button"
                class="cod-modal-button"
                onclick="closeCodPopup()">
            Đóng
        </button>

    </div>
</div>

<?php endif; ?>

<script>
const coForm = document.getElementById('co-form');

if (coForm) {
    coForm.addEventListener('submit', function() {
        if (!coForm.checkValidity()) return;

        const button = document.getElementById('co-submit');

        if (button) {
            button.disabled = true;
            button.textContent = 'ĐANG XỬ LÝ ĐƠN HÀNG...';
        }
    });
}

const codOverlay = document.getElementById('codSuccessOverlay');
let codPreviousFocus = null;

function openCodPopup() {
    if (!codOverlay) return;

    codPreviousFocus = document.activeElement;

    codOverlay.classList.add('show');
    document.body.classList.add('cod-popup-open');

    const closeButton = codOverlay.querySelector('.cod-close');
    if (closeButton) closeButton.focus();
}

function closeCodPopup() {
    if (!codOverlay) return;

    codOverlay.classList.remove('show');
    document.body.classList.remove('cod-popup-open');

    if (
        codPreviousFocus
        && typeof codPreviousFocus.focus === 'function'
    ) {
        codPreviousFocus.focus();
    }
}

document.addEventListener('keydown', function(event) {
    if (!codOverlay || !codOverlay.classList.contains('show')) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeCodPopup();
    }

    if (event.key === 'Tab') {
        const controls = Array.from(
            codOverlay.querySelectorAll('button:not([disabled])')
        );

        if (!controls.length) return;

        const first = controls[0];
        const last = controls[controls.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
});

if (codOverlay) {
    openCodPopup();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
