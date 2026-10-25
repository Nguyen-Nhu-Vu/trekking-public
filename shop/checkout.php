<?php
// VNN TRAVEL - THANH TOAN DON HANG
// COD ONLY - AIVEN MYSQL
// Popup thong bao dat hang thanh cong

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$page_title = 'Thanh Toán Đơn Hàng';
$active_menu = 'shop';

$base = rtrim(
    defined('BASE_URL') ? BASE_URL : '/',
    '/'
) . '/';

// ====================================
// CAC HAM HO TRO
// ====================================

function cod_h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function cod_money($value) {
    return number_format(
        (float)$value,
        0,
        ',',
        '.'
    ) . ' đ';
}

function cod_phone($value) {
    return preg_replace(
        '/[^0-9]/',
        '',
        (string)$value
    );
}

function cod_log($message) {
    error_log('COD Checkout: ' . $message);
}

// Doc gia tri ENUM trong MySQL.
// Su dung INFORMATION_SCHEMA de tranh loi SQL 1064.

function cod_enum(PDO $pdo, $column, $choices) {

    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    $stmt->execute([$column]);

    $type = $stmt->fetchColumn();

    if (!$type) {
        throw new RuntimeException(
            'Không tìm thấy cột ' . $column
        );
    }

    if (!preg_match('/^enum\((.*)\)$/is', $type, $match)) {
        return $choices[0];
    }

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $match[1],
        $values
    );

    $allowed = array_map(
        'stripcslashes',
        $values[1]
    );

    foreach ($choices as $choice) {
        if (in_array($choice, $allowed, true)) {
            return $choice;
        }
    }

    throw new RuntimeException(
        'Trạng thái ' . $column .
        ' chưa tương thích với MySQL.'
    );
}

// ====================================
// KIEM TRA TAI KHOAN
// ====================================

$userId = (int)($_SESSION['user_id'] ?? 0);
$user = null;
$error = '';
$success = null;

if ($userId > 0) {
    try {

        $stmt = $pdo->prepare("
            SELECT id, fullname, phone, email
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $userId = 0;
        }

    } catch (Throwable $e) {

        cod_log('User: ' . $e->getMessage());

        $error = 'Không thể kiểm tra tài khoản.';
    }
}

// ====================================
// DOC GIO HANG
// ====================================

$items = [];

try {

    $cart = $_SESSION['cart'] ?? [];

    if (is_array($cart)) {

        foreach ($cart as $key => $row) {

            if (!is_array($row)) {
                continue;
            }

            $id = (int)(
                $row['product_id']
                ?? $row['id']
                ?? (is_numeric($key) ? $key : 0)
            );

            $qty = (int)(
                $row['quantity']
                ?? $row['qty']
                ?? 1
            );

            if ($id <= 0 || $qty <= 0) {
                throw new RuntimeException(
                    'Giỏ hàng có sản phẩm không hợp lệ.'
                );
            }

            $stmt = $pdo->prepare("
                SELECT id, name, price, stock, status
                FROM products
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);

            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new RuntimeException(
                    'Một sản phẩm không còn tồn tại.'
                );
            }

            if ((int)$product['status'] !== 1) {
                throw new RuntimeException(
                    'Sản phẩm không còn được bán.'
                );
            }

            $price = (float)$product['price'];

            $items[] = [
                'id' => $id,
                'name' => $product['name'],
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
    }

} catch (Throwable $e) {

    cod_log('Cart: ' . $e->getMessage());

    $error = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Không thể tải giỏ hàng.';
}

// ====================================
// TINH TONG TIEN
// ====================================

$subtotal = array_sum(
    array_column($items, 'total')
);

$shippingFee = (
    $subtotal > 0 &&
    $subtotal < 1000000
) ? 35000 : 0;

$total = $subtotal + $shippingFee;

// ====================================
// TOKEN VA MA DON HANG
// ====================================

if (empty($_SESSION['cod_token'])) {
    $_SESSION['cod_token'] =
        bin2hex(random_bytes(16));
}

if (empty($_SESSION['cod_order_code'])) {
    $_SESSION['cod_order_code'] =
        'DH' . strtoupper(
            bin2hex(random_bytes(7))
        );
}

$orderCode = $_SESSION['cod_order_code'];

// ====================================
// THONG TIN KHACH HANG
// ====================================

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

// ====================================
// XU LY XAC NHAN DAT HANG
// ====================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['place_order'])
) {

    $token = (string)(
        $_POST['cod_token'] ?? ''
    );

    $submittedCode = (string)(
        $_POST['order_code'] ?? ''
    );

    $fullname = trim(
        (string)$old['fullname']
    );

    $phone = cod_phone(
        $old['phone']
    );

    $email = trim(
        (string)$old['email']
    );

    $address = trim(
        (string)$old['address']
    );

    $notes = trim(
        (string)$old['notes']
    );

    // Kiem tra form da thanh cong truoc do.

    if (
        !empty($_SESSION['cod_completed']) &&
        $submittedCode !== '' &&
        hash_equals(
            (string)$_SESSION['cod_completed']['code'],
            $submittedCode
        )
    ) {

        $success =
            $_SESSION['cod_completed']['order'];

    } elseif ($error !== '') {

        // Giu thong bao loi truoc do.

    } elseif ($userId <= 0) {

        $error = 'Vui lòng đăng nhập để đặt hàng.';

    } elseif (
        $token === '' ||
        !hash_equals(
            (string)$_SESSION['cod_token'],
            $token
        )
    ) {

        $error =
            'Phiên đặt hàng không hợp lệ. Vui lòng tải lại trang.';

    } elseif (
        $submittedCode === '' ||
        !hash_equals(
            (string)$orderCode,
            $submittedCode
        )
    ) {

        $error = 'Mã yêu cầu đặt hàng không hợp lệ.';

    } elseif (!$items) {

        $error = 'Giỏ hàng đang trống.';

    } elseif (
        $fullname === '' ||
        strlen($phone) < 9 ||
        strlen($phone) > 20 ||
        $address === ''
    ) {

        $error =
            'Vui lòng nhập đầy đủ thông tin giao hàng.';

    } elseif (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = 'Địa chỉ email không hợp lệ.';

    } else {

        try {

            $pdo->beginTransaction();

            // KIEM TRA TAI KHOAN

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$userId]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Tài khoản không còn tồn tại.'
                );
            }

            // KIEM TRA MA DON HANG TRUNG

            $stmt = $pdo->prepare("
                SELECT id
                FROM orders
                WHERE order_code = ?
                LIMIT 1
            ");

            $stmt->execute([$orderCode]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Đơn hàng này đã được ghi nhận.'
                );
            }

            // KIEM TRA SAN PHAM

            $verified = [];
            $verifiedSubtotal = 0;

            $productStmt = $pdo->prepare("
                SELECT id, name, price, stock, status
                FROM products
                WHERE id = ?
                FOR UPDATE
            ");

            foreach ($items as $item) {

                $productStmt->execute([$item['id']]);

                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$product ||
                    (int)$product['status'] !== 1
                ) {
                    throw new RuntimeException(
                        'Một sản phẩm không còn được bán.'
                    );
                }

                if (
                    (int)$product['stock'] < $item['qty']
                ) {
                    throw new RuntimeException(
                        'Sản phẩm "' . $product['name'] .
                        '" không đủ số lượng trong kho.'
                    );
                }

                $price = (float)$product['price'];

                $verified[] = [
                    'id' => (int)$product['id'],
                    'name' => $product['name'],
                    'size' => $item['size'],
                    'qty' => $item['qty'],
                    'price' => $price,
                    'total' => $price * $item['qty']
                ];

                $verifiedSubtotal +=
                    $price * $item['qty'];
            }

            $verifiedShipping = (
                $verifiedSubtotal > 0 &&
                $verifiedSubtotal < 1000000
            ) ? 35000 : 0;

            $verifiedTotal =
                $verifiedSubtotal + $verifiedShipping;

            // LAY GIA TRI ENUM HOP LE

            $orderStatus = cod_enum(
                $pdo,
                'status',
                [
                    'Chờ xác nhận',
                    'Chờ xử lý',
                    'pending',
                    'Pending'
                ]
            );

            $paymentMethod = cod_enum(
                $pdo,
                'payment_method',
                [
                    'COD',
                    'cod',
                    'cash',
                    'Thanh toán khi nhận hàng'
                ]
            );

            // ====================================
            // TAO DON HANG TRONG MYSQL
            // ====================================

            $stmt = $pdo->prepare("
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
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $orderCode,
                $userId,
                $fullname,
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

            // ====================================
            // LUU CHI TIET SAN PHAM
            // ====================================

            $detailStmt = $pdo->prepare("
                INSERT INTO order_details
                (
                    order_id,
                    product_id,
                    price,
                    quantity,
                    total
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($verified as $item) {

                $detailStmt->execute([
                    $orderId,
                    $item['id'],
                    $item['price'],
                    $item['qty'],
                    $item['total']
                ]);
            }

            // ====================================
            // CAP NHAT TON KHO
            // ====================================

            $stockStmt = $pdo->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE id = ?
                  AND stock >= ?
            ");

            foreach ($verified as $item) {

                $stockStmt->execute([
                    $item['qty'],
                    $item['id'],
                    $item['qty']
                ]);

                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Không thể cập nhật tồn kho.'
                    );
                }
            }

            // LUU GIAO DICH

            $pdo->commit();

            // ====================================
            // KET QUA DAT HANG THANH CONG
            // ====================================

            $success = [
                'id' => $orderId,
                'code' => $orderCode,
                'fullname' => $fullname,
                'phone' => $phone,
                'address' => $address,
                'total' => $verifiedTotal,
                'shipping' => $verifiedShipping,
                'items' => $verified
            ];

            $_SESSION['cod_completed'] = [
                'code' => $orderCode,
                'order' => $success
            ];

            // CHI XOA GIO HANG KHI LUU THANH CONG

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

            cod_log($e->getMessage());

            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể tạo đơn hàng. Vui lòng thử lại.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.cod-page {
    background: #f8fafc;
    padding: 40px 16px 75px;
    min-height: 75vh;
}

.cod-wrap {
    max-width: 1160px;
    margin: auto;
}

.cod-title {
    text-align: center;
    color: #14532d;
    font-size: 29px;
    font-weight: 800;
    margin-bottom: 30px;
}

.cod-grid {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 24px;
    align-items: start;
}

.cod-card {
    background: white;
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 5px 22px rgba(0,0,0,.06);
}

.cod-card h3 {
    color: #0f172a;
    font-size: 19px;
    margin: 0 0 22px;
}

.cod-field {
    margin-bottom: 18px;
}

.cod-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
}

.cod-field input,
.cod-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 13px 15px;
    border: 1px solid #d7e0eb;
    border-radius: 10px;
    font: inherit;
}

.cod-field textarea {
    min-height: 105px;
    resize: vertical;
}

.cod-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.cod-payment {
    background: #f0fdf4;
    border: 1px solid #86efac;
    border-radius: 12px;
    padding: 19px;
    line-height: 1.8;
}

.cod-payment strong {
    color: #14532d;
}

.cod-payment p {
    color: #475569;
    font-size: 14px;
    margin: 8px 0 0;
}

.cod-item {
    padding: 15px 0;
    border-bottom: 1px solid #e5e7eb;
}

.cod-item-name {
    font-weight: 800;
    margin-bottom: 9px;
}

.cod-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin: 13px 0;
}

.cod-total {
    border-top: 1px solid #e5e7eb;
    padding-top: 18px;
    color: #ea580c;
    font-weight: 900;
    font-size: 21px;
}

.cod-submit {
    display: block;
    width: 100%;
    padding: 16px;
    margin-top: 20px;
    border: 0;
    border-radius: 11px;
    color: white;
    background: #ea580c;
    font-weight: 800;
    font-size: 17px;
    cursor: pointer;
}

.cod-submit:hover {
    background: #c2410c;
}

.cod-submit:disabled {
    opacity: .6;
}

.cod-error {
    padding: 16px;
    background: #fee2e2;
    color: #b91c1c;
    border-radius: 10px;
    margin-bottom: 20px;
}

.cod-empty {
    max-width: 700px;
    margin: auto;
    text-align: center;
}

.cod-link {
    display: inline-block;
    padding: 12px 20px;
    margin-top: 15px;
    background: #14532d;
    color: white;
    text-decoration: none;
    border-radius: 10px;
}

.cod-muted {
    color: #64748b;
    font-size: 13px;
    line-height: 1.8;
}

/* =====================================
   POPUP DAT HANG THANH CONG
   ===================================== */

.cod-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    box-sizing: border-box;
    background: rgba(15,23,42,.65);
    backdrop-filter: blur(5px);
}

.cod-overlay.show {
    display: flex;
}

.cod-modal {
    position: relative;
    width: 100%;
    max-width: 460px;
    max-height: 90vh;
    overflow-y: auto;
    box-sizing: border-box;
    padding: 35px 28px 28px;
    background: white;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 25px 70px rgba(0,0,0,.25);
    animation: codAppear .2s ease-out;
}

@keyframes codAppear {
    from {
        opacity: 0;
        transform: scale(.94) translateY(12px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.cod-close {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 38px;
    height: 38px;
    border: none;
    border-radius: 50%;
    background: #f1f5f9;
    color: #475569;
    font-size: 23px;
    cursor: pointer;
}

.cod-close:hover {
    background: #e2e8f0;
}

.cod-check {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 78px;
    height: 78px;
    margin: 5px auto 19px;
    border-radius: 50%;
    background: #dcfce7;
    color: #16a34a;
    font-size: 42px;
}

.cod-modal h2 {
    color: #14532d;
    margin: 0 0 14px;
    font-size: 25px;
}

.cod-modal-desc {
    color: #475569;
    line-height: 1.8;
    margin-bottom: 22px;
    font-size: 15px;
}

.cod-order-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 18px;
    border-radius: 12px;
    text-align: left;
}

.cod-order-box .cod-row {
    font-size: 14px;
    overflow-wrap: anywhere;
}

.cod-order-box .cod-total {
    font-size: 19px;
}

.cod-modal-note {
    background: #f0fdf4;
    color: #166534;
    border-radius: 10px;
    padding: 15px;
    margin: 18px 0;
    line-height: 1.7;
    font-size: 14px;
}

.cod-modal-button {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 11px;
    background: #14532d;
    color: white;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
}

.cod-modal-button:hover {
    background: #166534;
}

body.cod-modal-open {
    overflow: hidden;
}

@media(max-width:850px) {
    .cod-grid,
    .cod-two {
        grid-template-columns: 1fr;
    }

    .cod-card {
        padding: 20px;
    }

    .cod-title {
        font-size: 24px;
    }

    .cod-modal {
        padding: 34px 20px 22px;
    }
}
</style>

<section class="cod-page">
<div class="cod-wrap">

<h1 class="cod-title">
    THANH TOÁN ĐƠN HÀNG
</h1>

<?php if ($success): ?>

    <div class="cod-card cod-empty">

        <h3 style="color:#14532d">
            Đơn hàng đã được ghi nhận
        </h3>

        <p>
            Mã đơn hàng:
            <strong><?= cod_h($success['code']) ?></strong>
        </p>

        <p>
            Cảm ơn bạn đã mua sắm tại VNN Travel!
        </p>

        <button type="button"
                class="cod-submit"
                onclick="openCodPopup()">

            Xem thông báo đặt hàng

        </button>

        <a class="cod-link"
           href="<?= cod_h($base) ?>shop/shop.php">

            Tiếp tục mua sắm

        </a>

    </div>

<?php elseif ($userId <= 0): ?>

    <div class="cod-card cod-empty">

        <h3>Vui lòng đăng nhập</h3>

        <p>
            Bạn cần đăng nhập để đặt hàng
            và theo dõi lịch sử mua hàng.
        </p>

        <a class="cod-link"
           href="<?= cod_h($base) ?>auth/login.php">

            Đăng nhập

        </a>

    </div>

<?php elseif (!$items && $error === ''): ?>

    <div class="cod-card cod-empty">

        <h3>Giỏ hàng đang trống</h3>

        <a class="cod-link"
           href="<?= cod_h($base) ?>shop/shop.php">

            Tiếp tục mua sắm

        </a>

    </div>

<?php else: ?>

    <?php if ($error !== ''): ?>

        <div class="cod-error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= cod_h($error) ?>

        </div>

    <?php endif; ?>

    <form method="POST" id="codForm">

        <input type="hidden"
               name="place_order"
               value="1">

        <input type="hidden"
               name="cod_token"
               value="<?= cod_h($_SESSION['cod_token']) ?>">

        <input type="hidden"
               name="order_code"
               value="<?= cod_h($orderCode) ?>">

        <div class="cod-grid">

            <!-- THONG TIN GIAO HANG -->

            <div class="cod-card">

                <h3>
                    <i class="fa-solid fa-truck"></i>
                    Thông tin giao hàng
                </h3>

                <div class="cod-field">

                    <label>Họ và tên *</label>

                    <input type="text"
                           name="fullname"
                           required
                           value="<?= cod_h($old['fullname']) ?>">

                </div>

                <div class="cod-two">

                    <div class="cod-field">

                        <label>Số điện thoại *</label>

                        <input type="tel"
                               name="phone"
                               required
                               value="<?= cod_h($old['phone']) ?>">

                    </div>

                    <div class="cod-field">

                        <label>Email</label>

                        <input type="email"
                               name="email"
                               value="<?= cod_h($old['email']) ?>">

                    </div>

                </div>

                <div class="cod-field">

                    <label>Địa chỉ giao hàng *</label>

                    <input type="text"
                           name="address"
                           required
                           value="<?= cod_h($old['address']) ?>">

                </div>

                <div class="cod-field">

                    <label>Ghi chú đơn hàng</label>

                    <textarea name="notes"><?= cod_h($old['notes']) ?></textarea>

                </div>

                <h3>Phương thức thanh toán</h3>

                <div class="cod-payment">

                    <strong>
                        <i class="fa-solid fa-money-bill-wave"></i>
                        Thanh toán khi nhận hàng (COD)
                    </strong>

                    <p>
                        Bạn không cần chuyển khoản trước.
                        Thanh toán trực tiếp khi nhận sản phẩm.
                    </p>

                </div>

            </div>

            <!-- THONG TIN DON HANG -->

            <div class="cod-card">

                <h3>
                    <i class="fa-solid fa-box"></i>
                    Đơn hàng của bạn
                </h3>

                <?php foreach ($items as $item): ?>

                    <div class="cod-item">

                        <div class="cod-item-name">
                            <?= cod_h($item['name']) ?>
                        </div>

                        <?php if ($item['size'] !== ''): ?>

                            <p class="cod-muted">
                                Phân loại:
                                <?= cod_h($item['size']) ?>
                            </p>

                        <?php endif; ?>

                        <div class="cod-row">

                            <span>
                                <?= (int)$item['qty'] ?>
                                × <?= cod_money($item['price']) ?>
                            </span>

                            <strong>
                                <?= cod_money($item['total']) ?>
                            </strong>

                        </div>

                    </div>

                <?php endforeach; ?>

                <div class="cod-row">

                    <span>Tạm tính</span>

                    <strong>
                        <?= cod_money($subtotal) ?>
                    </strong>

                </div>

                <div class="cod-row">

                    <span>Phí giao hàng</span>

                    <strong>
                        <?= $shippingFee == 0
                            ? 'Miễn phí'
                            : cod_money($shippingFee) ?>
                    </strong>

                </div>

                <div class="cod-row cod-total">

                    <span>Tổng cộng</span>

                    <strong>
                        <?= cod_money($total) ?>
                    </strong>

                </div>

                <button type="submit"
                        class="cod-submit"
                        id="codSubmit">

                    <i class="fa-solid fa-check"></i>
                    XÁC NHẬN ĐẶT HÀNG

                </button>

                <p class="cod-muted">
                    Đơn hàng sẽ được gửi đến bộ phận quản lý.
                    Bạn chỉ thanh toán khi nhận hàng.
                </p>

            </div>

        </div>

    </form>

<?php endif; ?>

</div>
</section>

<?php if ($success): ?>

<!-- =====================================
     POPUP THONG BAO THANH CONG
     ===================================== -->

<div class="cod-overlay show"
     id="codOverlay"
     onclick="if(event.target===this)closeCodPopup()">

    <div class="cod-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="codSuccessTitle">

        <!-- NUT X GOC PHAI -->

        <button type="button"
                class="cod-close"
                aria-label="Đóng thông báo"
                onclick="closeCodPopup()">

            &times;

        </button>

        <!-- DAU TICH XANH -->

        <div class="cod-check">

            <i class="fa-solid fa-check"></i>

        </div>

        <h2 id="codSuccessTitle">
            Đặt hàng thành công!
        </h2>

        <!-- NOI DUNG THONG BAO MOI -->

        <p class="cod-modal-desc">
            Đơn hàng của bạn đã được đặt thành công
            và sẽ sớm được giao đến tay bạn.
            Cảm ơn bạn đã tin tưởng và mua sắm
            tại VNN Travel!
        </p>

        <!-- THONG TIN DON HANG -->

        <div class="cod-order-box">

            <div class="cod-row">

                <span>Mã đơn hàng</span>

                <strong>
                    <?= cod_h($success['code']) ?>
                </strong>

            </div>

            <div class="cod-row">

                <span>Khách hàng</span>

                <strong>
                    <?= cod_h($success['fullname']) ?>
                </strong>

            </div>

            <div class="cod-row">

                <span>Phương thức thanh toán</span>

                <strong>COD</strong>

            </div>

            <div class="cod-row cod-total">

                <span>Tổng tiền</span>

                <strong>
                    <?= cod_money($success['total']) ?>
                </strong>

            </div>

        </div>

        <div class="cod-modal-note">

            <i class="fa-solid fa-circle-info"></i>

            Thanh toán khi nhận hàng.
            Bạn không cần chuyển khoản trước.

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
// =====================================
// CHONG BAM NUT DAT HANG NHIEU LAN
// =====================================

const codForm = document.getElementById('codForm');

if (codForm) {

    codForm.addEventListener('submit', function() {

        if (!codForm.checkValidity()) {
            return;
        }

        const btn = document.getElementById('codSubmit');

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'ĐANG XỬ LÝ ĐƠN HÀNG...';
        }
    });
}

// =====================================
// POPUP DAT HANG THANH CONG
// =====================================

const codOverlay = document.getElementById('codOverlay');

let codPreviousFocus = null;

function openCodPopup() {

    if (!codOverlay) return;

    codPreviousFocus = document.activeElement;

    codOverlay.classList.add('show');

    document.body.classList.add('cod-modal-open');

    const closeBtn =
        codOverlay.querySelector('.cod-close');

    if (closeBtn) {
        closeBtn.focus();
    }
}

function closeCodPopup() {

    if (!codOverlay) return;

    codOverlay.classList.remove('show');

    document.body.classList.remove('cod-modal-open');

    if (
        codPreviousFocus &&
        typeof codPreviousFocus.focus === 'function'
    ) {
        codPreviousFocus.focus();
    }
}

// BAM ESC DE DONG

document.addEventListener('keydown', function(event) {

    if (
        !codOverlay ||
        !codOverlay.classList.contains('show')
    ) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeCodPopup();
    }

    if (event.key === 'Tab') {

        const buttons = Array.from(
            codOverlay.querySelectorAll(
                'button:not([disabled])'
            )
        );

        if (!buttons.length) {
            return;
        }

        const first = buttons[0];
        const last = buttons[buttons.length - 1];

        if (
            event.shiftKey &&
            document.activeElement === first
        ) {

            event.preventDefault();
            last.focus();

        } else if (
            !event.shiftKey &&
            document.activeElement === last
        ) {

            event.preventDefault();
            first.focus();
        }
    }
});

// TU DONG MO POPUP SAU KHI DAT HANG

if (codOverlay) {
    openCodPopup();
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
