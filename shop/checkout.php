
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$page_title = 'Thanh Toán Đơn Hàng - VNN Travel';
$active_menu = 'shop';
$base = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') . '/';

function ckH($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function ckMoney($v) {
    return number_format((float)$v, 0, ',', '.') . ' đ';
}

function ckPhone($v) {
    return preg_replace('/[^0-9]/', '', (string)$v);
}

function ckEnum(PDO $pdo, $column, array $preferred) {
    $q = $pdo->prepare("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = ?
    ");
    $q->execute([$column]);
    $type = (string)$q->fetchColumn();

    if (!$type) {
        throw new RuntimeException('Thiếu cột orders.' . $column);
    }

    if (!preg_match('/^enum\((.*)\)$/is', $type, $m)) {
        return $preferred[0];
    }

    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $matches);
    $allowed = array_map('stripcslashes', $matches[1]);

    foreach ($preferred as $value) {
        if (in_array($value, $allowed, true)) {
            return $value;
        }
    }

    throw new RuntimeException(
        'Cột ' . $column . ' chưa hỗ trợ trạng thái cần lưu.'
    );
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$user = null;
$error = '';
$success = null;

if ($userId > 0) {
    try {
        $q = $pdo->prepare("
            SELECT id, fullname, phone, email, role
            FROM users WHERE id = ? LIMIT 1
        ");
        $q->execute([$userId]);
        $user = $q->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $userId = 0;
        }
    } catch (Throwable $e) {
        error_log('Checkout user: ' . $e->getMessage());
        $error = 'Không thể kiểm tra tài khoản.';
    }
}

// ==========================================
// KIEM TRA QUYEN DAT HANG
// ==========================================
// Khach vang lai: duoc mua.
// Customer: duoc mua.
// Admin / Manager / Staff: khong duoc mua.

$blockedBuyer = $userId > 0 && (
    !$user ||
    strtolower(trim((string)($user['role'] ?? ''))) !== 'customer'
);

if ($blockedBuyer) {
    // Khong goi http_response_code(403) de tranh canh bao
    // headers already sent tren moi truong deploy hien tai.

    require_once __DIR__ . '/../includes/header.php';
    ?>

    <section class="container section"
             style="min-height:55vh;padding:65px 16px">

        <div style="
            max-width:650px;
            margin:auto;
            padding:30px;
            text-align:center;
            background:white;
            border:1px solid #fecaca;
            border-radius:14px;
            box-shadow:0 8px 24px rgba(0,0,0,.05);
        ">

            <div style="
                width:65px;
                height:65px;
                border-radius:50%;
                background:#fee2e2;
                color:#b91c1c;
                display:flex;
                align-items:center;
                justify-content:center;
                margin:0 auto 18px;
                font-size:29px;
            ">
                <i class="fa-solid fa-user-lock"></i>
            </div>

            <h2 style="color:#b91c1c;margin-bottom:14px">
                Tài khoản không có quyền mua hàng
            </h2>

            <p style="color:#475569;line-height:1.8">
                Tài khoản Admin, Quản lý và Nhân viên
                chỉ được dùng để quản trị hệ thống,
                không được đặt hàng trên website.
            </p>

            <p style="color:#475569;line-height:1.8">
                Chỉ tài khoản Khách hàng hoặc
                khách vãng lai chưa đăng nhập
                mới có thể mua hàng.
            </p>

            <a href="<?= ckH($base) ?>shop/shop.php"
               class="btn btn-primary"
               style="display:inline-block;margin-top:17px">
                Quay về cửa hàng
            </a>

        </div>
    </section>

    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ==========================================
// GIO HANG HIEN TAI
// ==========================================

$items = [];
$cart = $_SESSION['cart'] ?? [];

try {
    if (is_array($cart)) {
        $productQuery = $pdo->prepare("
            SELECT id, name, price, stock, status
            FROM products WHERE id = ? LIMIT 1
        ");

        foreach ($cart as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int)(
                $row['product_id'] ??
                $row['id'] ??
                (is_numeric($key) ? $key : 0)
            );

            $qty = (int)($row['quantity'] ?? $row['qty'] ?? 1);

            if ($id <= 0 || $qty <= 0) {
                throw new RuntimeException('Giỏ hàng không hợp lệ.');
            }

            $productQuery->execute([$id]);
            $p = $productQuery->fetch(PDO::FETCH_ASSOC);
            $productQuery->closeCursor();

            if (!$p || (int)$p['status'] !== 1) {
                throw new RuntimeException(
                    'Một sản phẩm không còn được bán.'
                );
            }

            if ((int)$p['stock'] < $qty) {
                throw new RuntimeException(
                    'Sản phẩm "' . $p['name'] . '" không đủ hàng.'
                );
            }

            $price = (float)$p['price'];

            $items[] = [
                'id' => $id,
                'name' => $p['name'],
                'size' => (string)(
                    $row['size'] ?? $row['variant'] ?? ''
                ),
                'qty' => $qty,
                'price' => $price,
                'total' => $price * $qty
            ];
        }
    }
} catch (Throwable $e) {
    error_log('Checkout cart: ' . $e->getMessage());

    $error = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Không thể tải giỏ hàng.';
}

$subtotal = array_sum(array_column($items, 'total'));
$shippingFee = $subtotal > 0 && $subtotal < 1000000
    ? 35000
    : 0;

$total = $subtotal + $shippingFee;

if (empty($_SESSION['cod_token'])) {
    $_SESSION['cod_token'] = bin2hex(random_bytes(16));
}

if (empty($_SESSION['cod_order_code'])) {
    $_SESSION['cod_order_code'] =
        'DH' . strtoupper(bin2hex(random_bytes(7)));
}

$orderCode = $_SESSION['cod_order_code'];

$old = [
    'fullname' => $_POST['fullname'] ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'address' => $_POST['address'] ?? '',
    'notes' => $_POST['notes'] ?? '',
    'payment' => $_POST['payment_method'] ?? 'COD'
];

// ==========================================
// XU LY DAT HANG
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['place_order'])) {

    $token = (string)($_POST['cod_token'] ?? '');
    $submittedCode = (string)($_POST['order_code'] ?? '');

    $fullname = trim((string)$old['fullname']);
    $phone = ckPhone($old['phone']);
    $email = trim((string)$old['email']);
    $address = trim((string)$old['address']);
    $notes = trim((string)$old['notes']);
    $paymentChoice = (string)$old['payment'];

    if (
        !empty($_SESSION['cod_completed']) &&
        $submittedCode !== '' &&
        hash_equals(
            (string)$_SESSION['cod_completed']['code'],
            $submittedCode
        )
    ) {
        $success = $_SESSION['cod_completed']['order'];

    } elseif ($error !== '') {
        // Giu nguyen loi gio hang.

    } elseif (
        $token === '' ||
        !hash_equals((string)$_SESSION['cod_token'], $token)
    ) {
        $error = 'Phiên đặt hàng không hợp lệ.';

    } elseif (
        $submittedCode === '' ||
        !hash_equals((string)$orderCode, $submittedCode)
    ) {
        $error = 'Mã yêu cầu đặt hàng không hợp lệ.';

    } elseif (!$items) {
        $error = 'Giỏ hàng đang trống.';

    } elseif (!in_array($paymentChoice, ['COD', 'MoMo'], true)) {
        $error = 'Phương thức thanh toán không hợp lệ.';

    } elseif (
        $fullname === '' ||
        strlen($phone) < 9 ||
        strlen($phone) > 20 ||
        $address === ''
    ) {
        $error = 'Vui lòng nhập đủ thông tin giao hàng.';

    } elseif (
        $email &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Email không hợp lệ.';

    } else {
        try {
            $pdo->beginTransaction();

            if ($userId > 0) {
                $checkUser = $pdo->prepare(
                    "SELECT id FROM users WHERE id = ? LIMIT 1"
                );

                $checkUser->execute([$userId]);

                if (!$checkUser->fetchColumn()) {
                    throw new RuntimeException(
                        'Tài khoản không còn tồn tại.'
                    );
                }

                $checkUser->closeCursor();
            }

            $checkCode = $pdo->prepare(
                "SELECT id FROM orders WHERE order_code = ? LIMIT 1"
            );

            $checkCode->execute([$orderCode]);

            if ($checkCode->fetchColumn()) {
                throw new RuntimeException(
                    'Đơn hàng này đã được ghi nhận.'
                );
            }

            $checkCode->closeCursor();

            $productLock = $pdo->prepare("
                SELECT id, name, price, stock, status
                FROM products WHERE id = ? FOR UPDATE
            ");

            $verified = [];
            $verifiedSubtotal = 0;

            foreach ($items as $item) {
                $productLock->execute([$item['id']]);
                $p = $productLock->fetch(PDO::FETCH_ASSOC);
                $productLock->closeCursor();

                if (!$p || (int)$p['status'] !== 1) {
                    throw new RuntimeException(
                        'Sản phẩm không còn được bán.'
                    );
                }

                if ((int)$p['stock'] < $item['qty']) {
                    throw new RuntimeException(
                        '"' . $p['name'] . '" không đủ tồn kho.'
                    );
                }

                $price = (float)$p['price'];

                $verified[] = [
                    'id' => (int)$p['id'],
                    'name' => $p['name'],
                    'size' => $item['size'],
                    'qty' => $item['qty'],
                    'price' => $price,
                    'total' => $price * $item['qty']
                ];

                $verifiedSubtotal += $price * $item['qty'];
            }

            $verifiedShipping = $verifiedSubtotal > 0 &&
                $verifiedSubtotal < 1000000
                ? 35000
                : 0;

            $verifiedTotal = $verifiedSubtotal + $verifiedShipping;

            $status = ckEnum($pdo, 'status', [
                'Chờ xác nhận',
                'Chờ xử lý',
                'pending',
                'Pending'
            ]);

            $paymentMethod = $paymentChoice === 'MoMo'
                ? ckEnum($pdo, 'payment_method', ['MoMo'])
                : ckEnum($pdo, 'payment_method', [
                    'COD',
                    'cod',
                    'cash',
                    'Thanh toán khi nhận hàng'
                ]);

            $orderInsert = $pdo->prepare("
                INSERT INTO orders
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $orderInsert->execute([
                $orderCode,
                $userId > 0 ? $userId : null,
                $fullname,
                $phone,
                $email,
                $address,
                $paymentMethod,
                $verifiedShipping,
                $verifiedTotal,
                $notes,
                $status
            ]);

            $orderId = (int)$pdo->lastInsertId();

            if ($orderId <= 0) {
                throw new RuntimeException(
                    'Không tạo được đơn hàng.'
                );
            }

            $detailInsert = $pdo->prepare("
                INSERT INTO order_details
                (order_id, product_id, price, quantity, total)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stockUpdate = $pdo->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE id = ? AND stock >= ?
            ");

            foreach ($verified as $item) {
                $detailInsert->execute([
                    $orderId,
                    $item['id'],
                    $item['price'],
                    $item['qty'],
                    $item['total']
                ]);

                $stockUpdate->execute([
                    $item['qty'],
                    $item['id'],
                    $item['qty']
                ]);

                if ($stockUpdate->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Không thể cập nhật tồn kho.'
                    );
                }
            }

            $pdo->commit();

            $success = [
                'id' => $orderId,
                'code' => $orderCode,
                'fullname' => $fullname,
                'phone' => $phone,
                'total' => $verifiedTotal,
                'shipping' => $verifiedShipping,
                'payment' => $paymentChoice,
                'items' => $verified
            ];

            $_SESSION['cod_completed'] = [
                'code' => $orderCode,
                'order' => $success
            ];

            $_SESSION['cart'] = [];
            $_SESSION['cart_count'] = 0;

            unset(
                $_SESSION['cod_token'],
                $_SESSION['cod_order_code']
            );

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Checkout: ' . $e->getMessage());

            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể đặt hàng. Vui lòng thử lại.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.ck-page{background:#f3f6fa;min-height:70vh;padding:32px 16px 55px}
.ck-wrap{max-width:1160px;margin:auto}
.ck-title{color:#102c50;font-size:25px;margin:0 0 23px;font-weight:800}
.ck-grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(300px,.9fr);gap:20px;align-items:start}
.ck-card{padding:24px;background:#fff;border:1px solid #e0e8f0;border-radius:16px;margin-bottom:16px}
.ck-card h3{color:#0f2f55;font-size:18px;margin:0 0 17px}
.ck-field{margin-bottom:16px}
.ck-field label{display:block;color:#334155;font-size:13px;font-weight:700;margin-bottom:7px}
.ck-field input,.ck-field textarea{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:9px;font:inherit;font-size:13px;background:#fff;color:#102744}
.ck-field textarea{min-height:85px;resize:vertical}
.ck-two{display:grid;grid-template-columns:1fr 1fr;gap:13px}
.ck-payment-option{display:flex;gap:13px;align-items:flex-start;padding:17px;margin-bottom:11px;border:1px solid #dce6ee;border-radius:11px;cursor:pointer}
.ck-payment-option:has(input:checked){border-color:#166534;background:#f0fdf4}
.ck-payment-option input{margin-top:4px;accent-color:#166534}
.ck-payment-option strong{display:block;color:#10375a;font-size:14px}
.ck-payment-option p{margin:5px 0 0;color:#64748b;font-size:12px;line-height:1.6}
.ck-item{padding:13px 0;border-bottom:1px solid #e2e8f0}
.ck-item-name{font-size:13px;color:#18385c;font-weight:700}
.ck-row{display:flex;justify-content:space-between;gap:15px;align-items:center;padding:10px 0;color:#334155;font-size:13px}
.ck-row strong{color:#102744}
.ck-total{border-top:1px solid #e2e8f0;margin-top:10px;font-weight:800}
.ck-total strong{color:#ea580c;font-size:21px}
.ck-btn{background:#166534;color:#fff;border:0;border-radius:10px;padding:13px 20px;font-weight:800;cursor:pointer;font-size:13px}
.ck-btn.full{display:block;width:100%;margin-top:17px}
.ck-btn:disabled{opacity:.55;cursor:wait}
.ck-muted{color:#64748b;font-size:12px;line-height:1.7}
.ck-error{background:#fee2e2;color:#991b1b;padding:15px;border-radius:10px;margin-bottom:17px;font-size:13px}
.ck-empty{text-align:center;padding:30px}
.ck-link{display:inline-block;margin:15px 10px;color:#166534;font-size:13px;font-weight:700}
.ck-overlay{position:fixed;inset:0;z-index:999999;background:rgba(15,23,42,.6);display:flex;align-items:center;justify-content:center;padding:16px}
.ck-overlay[hidden]{display:none!important}
.ck-modal{position:relative;background:#fff;border-radius:17px;padding:27px;width:100%;max-width:475px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 70px #0003;text-align:center}
.ck-close{position:absolute;top:9px;right:12px;font-size:26px;background:transparent;border:0;cursor:pointer}
.ck-modal h2{color:#102e51;font-size:23px}
.ck-modal p{color:#64748b;font-size:13px;line-height:1.7}
.ck-qr{display:block;width:min(100%,270px);max-height:330px;object-fit:contain;margin:16px auto;border:1px solid #e2e8f0;border-radius:12px}
.ck-pay-info{background:#f8fafc;border:1px solid #e2e8f0;border-radius:11px;padding:12px;margin:14px 0;text-align:left;font-size:12px;overflow-wrap:anywhere}
.ck-pay-info div{margin:8px 0}
.ck-pay-info strong{color:#102e51}
.ck-copy{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:8px;border-radius:8px;cursor:pointer}
@media(max-width:850px){.ck-grid{grid-template-columns:1fr}.ck-two{grid-template-columns:1fr}}
</style>

<main class="ck-page">
<div class="ck-wrap">
<h1 class="ck-title">
    <i class="fa-solid fa-credit-card"></i>
    THANH TOÁN ĐƠN HÀNG
</h1>

<?php if ($success): ?>
<section class="ck-card ck-empty">
    <h3>Đặt hàng thành công!</h3>
    <p>Đơn hàng sẽ sớm được giao đến tay bạn.</p>

    <?php if ($userId <= 0): ?>
        <p>
            Khách vãng lai: nhân viên sẽ liên hệ bằng
            số điện thoại đã nhập. Nếu có thắc mắc,
            vui lòng liên hệ hotline trên website.
        </p>
    <?php endif; ?>

    <button class="ck-btn" type="button" onclick="ckOpen()">
        <?= ($success['payment'] ?? 'COD') === 'MoMo'
            ? 'Xem mã QR thanh toán'
            : 'Xem thông tin đơn hàng' ?>
    </button>

    <a class="ck-link"
       href="<?= ckH($base) ?>shop/my-orders.php">
        Đơn mua thiết bị của tôi
    </a>
</section>

<?php elseif (!$items && !$error): ?>
<section class="ck-card ck-empty">
    Giỏ hàng đang trống.
    <a href="<?= ckH($base) ?>shop/shop.php">
        Tiếp tục mua sắm
    </a>
</section>

<?php else: ?>
    <?php if ($error): ?>
        <div class="ck-error"><?= ckH($error) ?></div>
    <?php endif; ?>

<form method="POST" id="ckForm">
<input type="hidden" name="place_order" value="1">
<input type="hidden" name="cod_token"
       value="<?= ckH($_SESSION['cod_token']) ?>">
<input type="hidden" name="order_code"
       value="<?= ckH($orderCode) ?>">

<div class="ck-grid">
<section class="ck-card">
    <h3>
        <i class="fa-solid fa-truck"></i>
        Thông tin giao hàng
    </h3>

    <div class="ck-field">
        <label>Họ và tên *</label>
        <input name="fullname" required
               value="<?= ckH($old['fullname']) ?>">
    </div>

    <div class="ck-two">
        <div class="ck-field">
            <label>Số điện thoại *</label>
            <input name="phone" type="tel" required
                   value="<?= ckH($old['phone']) ?>">
        </div>

        <div class="ck-field">
            <label>Email</label>
            <input name="email" type="email"
                   value="<?= ckH($old['email']) ?>">
        </div>
    </div>

    <div class="ck-field">
        <label>Địa chỉ giao hàng *</label>
        <input name="address" required
               value="<?= ckH($old['address']) ?>">
    </div>

    <div class="ck-field">
        <label>Ghi chú</label>
        <textarea name="notes"><?= ckH($old['notes']) ?></textarea>
    </div>

    <h3>Phương thức thanh toán</h3>

    <label class="ck-payment-option">
        <input type="radio" name="payment_method" value="COD"
            <?= $old['payment'] !== 'MoMo' ? 'checked' : '' ?>>
        <span>
            <strong>Thanh toán khi nhận hàng (COD)</strong>
            <p>Thanh toán trực tiếp khi nhận sản phẩm.</p>
        </span>
    </label>

    <label class="ck-payment-option">
        <input type="radio" name="payment_method" value="MoMo"
            <?= $old['payment'] === 'MoMo' ? 'checked' : '' ?>>
        <span>
            <strong>Chuyển khoản bằng QR MoMo</strong>
            <p>
                Đặt hàng xong sẽ hiện mã QR.
                Quản lý xác nhận thanh toán thủ công.
            </p>
        </span>
    </label>
</section>

<section class="ck-card">
    <h3>
        <i class="fa-solid fa-box"></i>
        Đơn hàng của bạn
    </h3>

    <?php foreach ($items as $item): ?>
        <div class="ck-item">
            <div class="ck-item-name">
                <?= ckH($item['name']) ?>
            </div>

            <?php if ($item['size']): ?>
                <p class="ck-muted">
                    Phân loại: <?= ckH($item['size']) ?>
                </p>
            <?php endif; ?>

            <div class="ck-row">
                <span>
                    <?= (int)$item['qty'] ?> ×
                    <?= ckMoney($item['price']) ?>
                </span>
                <strong><?= ckMoney($item['total']) ?></strong>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="ck-row">
        <span>Tạm tính</span>
        <strong><?= ckMoney($subtotal) ?></strong>
    </div>

    <div class="ck-row">
        <span>Phí giao hàng</span>
        <strong>
            <?= $shippingFee
                ? ckMoney($shippingFee)
                : 'Miễn phí' ?>
        </strong>
    </div>

    <div class="ck-row ck-total">
        <span>Tổng cộng</span>
        <strong><?= ckMoney($total) ?></strong>
    </div>

    <button class="ck-btn full" type="submit" id="ckSubmit">
        XÁC NHẬN ĐẶT HÀNG
    </button>

    <p class="ck-muted">
        Đơn sẽ được ghi nhận ở trạng thái chờ xác nhận.
    </p>
</section>
</div>
</form>
<?php endif; ?>
</div>
</main>

<?php if ($success): ?>
<div class="ck-overlay" id="ckOverlay">
<div class="ck-modal" role="dialog" aria-modal="true"
     aria-label="Thông báo đặt hàng">

    <button type="button" class="ck-close"
            onclick="ckClose()" aria-label="Đóng">
        &times;
    </button>

    <h2>Đặt hàng thành công!</h2>
    <p>Đơn hàng sẽ sớm được giao đến tay bạn.</p>

    <?php if ($userId <= 0): ?>
        <p>
            Đơn khách vãng lai: nhân viên sẽ liên hệ bằng điện thoại.
            Thắc mắc vui lòng liên hệ hotline trên website.
        </p>
    <?php endif; ?>

    <?php if (($success['payment'] ?? 'COD') === 'MoMo'): ?>
        <p><strong>Quét mã MoMo để chuyển khoản</strong></p>
        <img class="ck-qr"
             src="<?= ckH($base) ?>assets/images/momo-qr-vu.jpg"
             alt="QR MoMo VNN Travel">
    <?php endif; ?>

    <?php
    $transferContent = implode(' ', [
        $success['code'],
        $success['fullname'],
        $success['phone']
    ]);
    ?>

    <div class="ck-pay-info">
        <div>
            Mã đơn:
            <strong><?= ckH($success['code']) ?></strong>
        </div>

        <div>
            Khách hàng:
            <strong><?= ckH($success['fullname']) ?></strong>
        </div>

        <div>
            Điện thoại:
            <strong><?= ckH($success['phone']) ?></strong>
        </div>

        <div>
            Phương thức:
            <strong>
                <?= ($success['payment'] ?? 'COD') === 'MoMo'
                    ? 'MoMo'
                    : 'COD' ?>
            </strong>
        </div>

        <div>
            Tổng tiền:
            <strong style="color:#ea580c">
                <?= ckMoney($success['total']) ?>
            </strong>
        </div>

        <?php if (($success['payment'] ?? 'COD') === 'MoMo'): ?>
            <div>
                Nội dung chuyển khoản:
                <br>
                <strong id="ckTransfer">
                    <?= ckH($transferContent) ?>
                </strong>
            </div>

            <button class="ck-copy" type="button"
                onclick="navigator.clipboard?.writeText(document.getElementById('ckTransfer').textContent)">
                Sao chép nội dung
            </button>
        <?php endif; ?>
    </div>

    <?php if (($success['payment'] ?? 'COD') === 'MoMo'): ?>
        <p>
            Đây là QR cố định, không tự điền số tiền hoặc
            nội dung chuyển khoản. Hãy nhập đúng số tiền
            và nội dung hiển thị bên trên.
            Chuyển khoản không đồng nghĩa đơn đã được
            xác nhận thanh toán.
        </p>
    <?php endif; ?>

    <button class="ck-btn" type="button" onclick="ckClose()">
        Đóng
    </button>

</div>
</div>
<?php endif; ?>

<script>
function ckOpen() {
    const overlay = document.getElementById('ckOverlay');
    if (overlay) overlay.hidden = false;
}

function ckClose() {
    const overlay = document.getElementById('ckOverlay');
    if (overlay) overlay.hidden = true;
}

const ckForm = document.getElementById('ckForm');

if (ckForm) {
    ckForm.addEventListener('submit', function() {
        if (!ckForm.checkValidity()) return;

        const button = document.getElementById('ckSubmit');

        if (button) {
            button.disabled = true;
            button.textContent = 'ĐANG XỬ LÝ...';
        }
    });
}

document.getElementById('ckOverlay')?.addEventListener(
    'click',
    function(e) {
        if (e.target === this) ckClose();
    }
);

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') ckClose();
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
