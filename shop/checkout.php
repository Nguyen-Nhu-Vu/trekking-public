<?php
// VNN TRAVEL - CHECKOUT COD
// Database: Aiven MySQL
// Tables: orders, order_details
// Thanh toan khi nhan hang - Khong su dung QR

ob_start();

$page_title = 'Thanh Toán Đơn Hàng';
$active_menu = 'shop';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$base = rtrim(
    defined('BASE_URL') ? BASE_URL : '/',
    '/'
) . '/';

// ======================================
// HAM HO TRO
// ======================================

function ck_h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function ck_money($value) {
    return number_format(
        (float)$value,
        0,
        ',',
        '.'
    ) . ' đ';
}

function ck_phone($value) {
    return preg_replace(
        '/[^0-9]/',
        '',
        (string)$value
    );
}

function ck_log($message) {
    error_log('COD Checkout: ' . $message);
}

// ======================================
// XAC DINH TAI KHOAN
// ======================================

$userId = (int)($_SESSION['user_id'] ?? 0);
$user = null;
$userError = '';

if ($userId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, fullname, email, phone
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
        ck_log('User: ' . $e->getMessage());
        $userId = 0;
        $userError = 'Không thể kiểm tra tài khoản.';
    }
}

// ======================================
// LAY GIO HANG
// ======================================

function ck_cart_items($pdo) {
    $cart = $_SESSION['cart'] ?? [];
    $items = [];

    if (!is_array($cart)) {
        return [];
    }

    foreach ($cart as $key => $row) {

        if (!is_array($row)) {
            continue;
        }

        $productId = (int)(
            $row['product_id']
            ?? $row['id']
            ?? 0
        );

        // Ho tro gio hang co ID nam o key.
        if ($productId <= 0 && is_numeric($key)) {
            $productId = (int)$key;
        }

        $quantity = (int)(
            $row['quantity']
            ?? $row['qty']
            ?? 1
        );

        if ($productId <= 0 || $quantity <= 0) {
            throw new RuntimeException(
                'Giỏ hàng chứa sản phẩm không hợp lệ.'
            );
        }

        // Lay gia va thong tin san pham tu MySQL.
        // Khong tin gia do session cung cap.
        $stmt = $pdo->prepare("
            SELECT id, name, price, stock, status
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || (int)$product['status'] !== 1) {
            throw new RuntimeException(
                'Một sản phẩm trong giỏ không còn được bán.'
            );
        }

        $price = (float)$product['price'];

        $items[] = [
            'id' => $productId,
            'name' => $product['name'],
            'size' => (string)(
                $row['size']
                ?? $row['variant']
                ?? ''
            ),
            'quantity' => $quantity,
            'price' => $price,
            'total' => $price * $quantity,
            'stock' => (int)$product['stock']
        ];
    }

    return $items;
}

$error = '';
$success = null;
$items = [];

try {
    $items = ck_cart_items($pdo);
} catch (Throwable $e) {
    ck_log('Cart: ' . $e->getMessage());

    $error = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Không thể tải giỏ hàng.';
}

$total = array_sum(
    array_column($items, 'total')
);

// ======================================
// CHONG GUI TRUNG DON
// ======================================

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] =
        bin2hex(random_bytes(16));
}

if (empty($_SESSION['checkout_order_code'])) {
    $_SESSION['checkout_order_code'] =
        'DH' . strtoupper(
            bin2hex(random_bytes(7))
        );
}

// ======================================
// THONG TIN KHACH HANG
// ======================================

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

// ======================================
// XU LY DAT HANG
// ======================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['place_order'])
) {

    $token = (string)(
        $_POST['checkout_token'] ?? ''
    );

    $fullname = trim(
        (string)$old['fullname']
    );

    $phone = ck_phone(
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

    if (
        !empty($_SESSION['checkout_completed'])
        && hash_equals(
            (string)$_SESSION['checkout_completed']['token'],
            $token
        )
    ) {

        $success = $_SESSION['checkout_completed']['order'];

    } elseif ($error !== '') {

        // Giu nguyen thong bao loi.

    } elseif ($userId <= 0) {

        $error = 'Vui lòng đăng nhập để đặt hàng.';

    } elseif (
        !$token ||
        !hash_equals(
            $_SESSION['checkout_token'],
            $token
        )
    ) {

        $error = 'Phiên đặt hàng không hợp lệ. Vui lòng tải lại trang.';

    } elseif (!$items) {

        $error = 'Giỏ hàng đang trống.';

    } elseif (
        $fullname === '' ||
        strlen($phone) < 9 ||
        strlen($phone) > 20 ||
        $address === ''
    ) {

        $error = 'Vui lòng nhập đầy đủ thông tin giao hàng.';

    } elseif (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = 'Địa chỉ email không hợp lệ.';

    } else {

        try {

            // Bat dau giao dich MySQL.
            $pdo->beginTransaction();

            // Kiem tra user ton tai.
            $checkUser = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $checkUser->execute([$userId]);

            if (!$checkUser->fetchColumn()) {
                throw new RuntimeException(
                    'Tài khoản không tồn tại.'
                );
            }

            // Kiem tra lai gia va ton kho,
            // khoa dong de tranh tranh chap.
            $verifiedItems = [];
            $verifiedTotal = 0;

            foreach ($items as $item) {

                $stmt = $pdo->prepare("
                    SELECT id, name, price, stock, status
                    FROM products
                    WHERE id = ?
                    FOR UPDATE
                ");

                $stmt->execute([$item['id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$product ||
                    (int)$product['status'] !== 1
                ) {
                    throw new RuntimeException(
                        'Sản phẩm không còn được bán.'
                    );
                }

                if (
                    (int)$product['stock']
                    < $item['quantity']
                ) {
                    throw new RuntimeException(
                        'Sản phẩm "' . $product['name'] .
                        '" không đủ số lượng trong kho.'
                    );
                }

                $price = (float)$product['price'];
                $lineTotal = $price * $item['quantity'];

                $verifiedItems[] = [
                    'id' => (int)$product['id'],
                    'name' => $product['name'],
                    'size' => $item['size'],
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'total' => $lineTotal
                ];

                $verifiedTotal += $lineTotal;
            }

            // ==================================
            // BANG ORDERS DUNG THEO FILE SQL
            // ==================================

            $orderCode =
                $_SESSION['checkout_order_code'];

            $orderSQL = "
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
                (
                    ?, ?, ?, ?, ?, ?,
                    'COD',
                    0,
                    ?,
                    ?,
                    'Chờ xác nhận'
                )
            ";

            $stmt = $pdo->prepare($orderSQL);

            $stmt->execute([
                $orderCode,
                $userId,
                $fullname,
                $phone,
                $email,
                $address,
                $verifiedTotal,
                $notes
            ]);

            $orderId = (int)$pdo->lastInsertId();

            if ($orderId <= 0) {
                throw new RuntimeException(
                    'Không lấy được mã đơn hàng.'
                );
            }

            // ==================================
            // BANG ORDER_DETAILS
            // TEN DUNG TRONG DATABASE
            // ==================================

            $detailSQL = "
                INSERT INTO order_details
                (
                    order_id,
                    product_id,
                    price,
                    quantity,
                    total
                )
                VALUES (?, ?, ?, ?, ?)
            ";

            $detailStmt = $pdo->prepare(
                $detailSQL
            );

            foreach ($verifiedItems as $item) {

                $detailStmt->execute([
                    $orderId,
                    $item['id'],
                    $item['price'],
                    $item['quantity'],
                    $item['total']
                ]);
            }

            // ==================================
            // CAP NHAT TON KHO
            // ==================================

            $stockStmt = $pdo->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE id = ?
                  AND stock >= ?
            ");

            foreach ($verifiedItems as $item) {

                $stockStmt->execute([
                    $item['quantity'],
                    $item['id'],
                    $item['quantity']
                ]);

                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Không thể cập nhật tồn kho.'
                    );
                }
            }

            // Luu tat ca thay doi.
            $pdo->commit();

            // ==================================
            // DAT HANG THANH CONG
            // ==================================

            $success = [
                'id' => $orderId,
                'code' => $orderCode,
                'fullname' => $fullname,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'total' => $verifiedTotal,
                'items' => $verifiedItems
            ];

            // Giu thong tin ket qua chong submit trung.
            $_SESSION['checkout_completed'] = [
                'token' => $token,
                'order' => $success
            ];

            // Chi xoa gio hang SAU KHI commit.
            $_SESSION['cart'] = [];
            $_SESSION['cart_count'] = 0;

            unset(
                $_SESSION['checkout_token'],
                $_SESSION['checkout_order_code']
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            ck_log($e->getMessage());

            if ($e instanceof RuntimeException) {
                $error = $e->getMessage();
            } else {
                $error =
                    'Không thể tạo đơn hàng. ' .
                    'Vui lòng thử lại.';
            }
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

.cod-container {
    max-width: 1160px;
    margin: auto;
}

.cod-title {
    color: #14532d;
    text-align: center;
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
    background: #fff;
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 5px 22px rgba(0,0,0,.06);
}

.cod-card h3 {
    font-size: 19px;
    color: #0f172a;
    margin: 0 0 23px;
    font-weight: 800;
}

.cod-field {
    margin-bottom: 18px;
}

.cod-field label {
    display: block;
    margin-bottom: 8px;
    color: #0f172a;
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
    outline: none;
    background: #fff;
}

.cod-field input:focus,
.cod-field textarea:focus {
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22,163,74,.08);
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
    border: 1px solid #86efac;
    background: #f0fdf4;
    border-radius: 12px;
    padding: 19px;
    margin-top: 12px;
}

.cod-payment strong {
    color: #14532d;
    font-size: 17px;
}

.cod-payment p {
    color: #475569;
    font-size: 14px;
    line-height: 1.8;
    margin: 10px 0 0;
}

.cod-item {
    padding: 15px 0;
    border-bottom: 1px solid #e5e7eb;
}

.cod-item-name {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
}

.cod-item-meta {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 8px;
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
    margin-top: 18px;
    font-size: 21px;
    font-weight: 900;
    color: #ea580c;
}

.cod-submit {
    background: #ea580c;
    color: white;
    border: none;
    width: 100%;
    border-radius: 11px;
    padding: 16px;
    font-size: 17px;
    font-weight: 800;
    cursor: pointer;
    margin-top: 20px;
}

.cod-submit:hover {
    background: #c2410c;
}

.cod-submit:disabled {
    opacity: .6;
    cursor: wait;
}

.cod-alert {
    padding: 17px;
    border-radius: 11px;
    margin-bottom: 22px;
    line-height: 1.6;
}

.cod-error {
    background: #fee2e2;
    color: #b91c1c;
}

.cod-success {
    background: #dcfce7;
    color: #166534;
}

.cod-success-card {
    max-width: 800px;
    margin: auto;
}

.cod-success-icon {
    color: #16a34a;
    font-size: 53px;
    text-align: center;
    margin-bottom: 15px;
}

.cod-success-card h2 {
    color: #14532d;
    text-align: center;
    margin-bottom: 16px;
}

.cod-order-info {
    background: #f8fafc;
    padding: 22px;
    border-radius: 12px;
    margin: 24px 0;
}

.cod-link {
    display: inline-block;
    background: #14532d;
    color: white;
    text-decoration: none;
    font-weight: 700;
    padding: 13px 22px;
    border-radius: 9px;
    margin-top: 18px;
}

.cod-empty {
    max-width: 700px;
    margin: auto;
    text-align: center;
    padding: 45px 25px;
}

.cod-empty h3 {
    color: #14532d;
}

.cod-hint {
    color: #64748b;
    font-size: 13px;
    line-height: 1.8;
    margin-top: 12px;
}

@media (max-width: 850px) {
    .cod-grid {
        grid-template-columns: 1fr;
    }

    .cod-two {
        grid-template-columns: 1fr;
    }

    .cod-card {
        padding: 20px;
    }

    .cod-title {
        font-size: 24px;
    }
}
</style>

<section class="cod-page">
<div class="cod-container">

<h1 class="cod-title">
    THANH TOÁN ĐƠN HÀNG
</h1>

<?php if ($success): ?>

    <!-- =================================
         DAT HANG THANH CONG
         ================================= -->

    <div class="cod-card cod-success-card">

        <div class="cod-success-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <h2>ĐẶT HÀNG THÀNH CÔNG!</h2>

        <div class="cod-alert cod-success">
            Đơn hàng của bạn đã được ghi nhận thành công.
            VNN Travel sẽ xử lý và giao hàng đến địa chỉ
            mà bạn đã đăng ký.
        </div>

        <div class="cod-order-info">

            <div class="cod-row">
                <span>Mã đơn hàng</span>
                <strong><?= ck_h($success['code']) ?></strong>
            </div>

            <div class="cod-row">
                <span>Họ và tên</span>
                <strong><?= ck_h($success['fullname']) ?></strong>
            </div>

            <div class="cod-row">
                <span>Số điện thoại</span>
                <strong><?= ck_h($success['phone']) ?></strong>
            </div>

            <div class="cod-row">
                <span>Địa chỉ giao hàng</span>
                <strong><?= ck_h($success['address']) ?></strong>
            </div>

            <div class="cod-row">
                <span>Phương thức thanh toán</span>
                <strong>Thanh toán khi nhận hàng (COD)</strong>
            </div>

            <div class="cod-row cod-total">
                <span>Tổng tiền</span>
                <strong><?= ck_money($success['total']) ?></strong>
            </div>

        </div>

        <h3>Sản phẩm đã đặt</h3>

        <?php foreach ($success['items'] as $item): ?>

            <div class="cod-row">
                <span>
                    <?= ck_h($item['name']) ?>
                    × <?= (int)$item['quantity'] ?>
                </span>

                <strong>
                    <?= ck_money($item['total']) ?>
                </strong>
            </div>

        <?php endforeach; ?>

        <div class="cod-payment">

            <strong>
                <i class="fa-solid fa-money-bill-wave"></i>
                Thanh toán khi nhận hàng
            </strong>

            <p>
                Bạn không cần chuyển khoản trước.
                Vui lòng thanh toán trực tiếp cho
                nhân viên giao hàng khi nhận sản phẩm.
            </p>

        </div>

        <p class="cod-hint">
            Giỏ hàng đã được xóa sau khi đơn hàng
            được lưu thành công.
        </p>

        <div style="text-align:center">

            <a class="cod-link"
               href="<?= ck_h($base) ?>index.php">
                Về trang chủ
            </a>

        </div>

    </div>

<?php elseif ($userId <= 0): ?>

    <!-- =================================
         CHUA DANG NHAP
         ================================= -->

    <div class="cod-card cod-empty">

        <h3>VUI LÒNG ĐĂNG NHẬP</h3>

        <p>
            <?= ck_h(
                $userError !== ''
                ? $userError
                : 'Bạn cần đăng nhập để đặt hàng.'
            ) ?>
        </p>

        <p>
            Website cần xác định tài khoản khách hàng
            để lưu đơn hàng và lịch sử mua sắm.
        </p>

        <a class="cod-link"
           href="<?= ck_h($base) ?>auth/login.php">
            Đăng nhập
        </a>

    </div>

<?php elseif (empty($items)): ?>

    <!-- =================================
         GIO HANG TRONG
         ================================= -->

    <div class="cod-card cod-empty">

        <h3>GIỎ HÀNG ĐANG TRỐNG</h3>

        <p>
            Bạn chưa có sản phẩm nào để đặt hàng.
        </p>

        <a class="cod-link"
           href="<?= ck_h($base) ?>index.php">
            Tiếp tục mua sắm
        </a>

    </div>

<?php else: ?>

    <!-- =================================
         FORM THANH TOAN COD
         ================================= -->

    <?php if ($error !== ''): ?>

        <div class="cod-alert cod-error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?= ck_h($error) ?>

        </div>

    <?php endif; ?>

    <form method="POST" id="checkoutForm">

        <input
            type="hidden"
            name="place_order"
            value="1"
        >

        <input
            type="hidden"
            name="checkout_token"
            value="<?= ck_h($_SESSION['checkout_token']) ?>"
        >

        <div class="cod-grid">

            <!-- COT THONG TIN GIAO HANG -->

            <div class="cod-card">

                <h3>
                    <i class="fa-solid fa-truck"></i>
                    Thông tin giao hàng
                </h3>

                <div class="cod-field">

                    <label>Họ và tên *</label>

                    <input
                        type="text"
                        name="fullname"
                        maxlength="150"
                        value="<?= ck_h($old['fullname']) ?>"
                        required
                    >

                </div>

                <div class="cod-two">

                    <div class="cod-field">

                        <label>Số điện thoại *</label>

                        <input
                            type="tel"
                            name="phone"
                            maxlength="20"
                            value="<?= ck_h($old['phone']) ?>"
                            required
                        >

                    </div>

                    <div class="cod-field">

                        <label>Email</label>

                        <input
                            type="email"
                            name="email"
                            maxlength="150"
                            value="<?= ck_h($old['email']) ?>"
                        >

                    </div>

                </div>

                <div class="cod-field">

                    <label>Địa chỉ giao hàng *</label>

                    <input
                        type="text"
                        name="address"
                        value="<?= ck_h($old['address']) ?>"
                        placeholder="Nhập địa chỉ nhận hàng"
                        required
                    >

                </div>

                <div class="cod-field">

                    <label>Ghi chú đơn hàng</label>

                    <textarea
                        name="notes"
                        placeholder="Ghi chú cho nhân viên giao hàng..."
                    ><?= ck_h($old['notes']) ?></textarea>

                </div>

                <h3 style="margin-top:25px">
                    Phương thức thanh toán
                </h3>

                <div class="cod-payment">

                    <strong>
                        <i class="fa-solid fa-money-bill-wave"></i>
                        Thanh toán khi nhận hàng (COD)
                    </strong>

                    <p>
                        Bạn thanh toán trực tiếp khi nhận
                        được sản phẩm. Không cần chuyển khoản
                        hay quét mã QR trước.
                    </p>

                </div>

            </div>

            <!-- COT DON HANG -->

            <div class="cod-card">

                <h3>
                    <i class="fa-solid fa-box"></i>
                    Đơn hàng của bạn
                </h3>

                <?php foreach ($items as $item): ?>

                    <div class="cod-item">

                        <div class="cod-item-name">
                            <?= ck_h($item['name']) ?>
                        </div>

                        <?php if ($item['size'] !== ''): ?>

                            <div class="cod-item-meta">
                                Phân loại:
                                <?= ck_h($item['size']) ?>
                            </div>

                        <?php endif; ?>

                        <div class="cod-row">

                            <span>
                                <?= (int)$item['quantity'] ?>
                                × <?= ck_money($item['price']) ?>
                            </span>

                            <strong>
                                <?= ck_money($item['total']) ?>
                            </strong>

                        </div>

                    </div>

                <?php endforeach; ?>

                <div class="cod-row">

                    <span>Tạm tính</span>

                    <strong>
                        <?= ck_money($total) ?>
                    </strong>

                </div>

                <div class="cod-row">

                    <span>Phí giao hàng</span>

                    <strong style="color:#16a34a">
                        Miễn phí
                    </strong>

                </div>

                <div class="cod-row cod-total">

                    <span>Tổng cộng</span>

                    <strong>
                        <?= ck_money($total) ?>
                    </strong>

                </div>

                <button
                    type="submit"
                    class="cod-submit"
                    id="checkoutButton"
                >
                    <i class="fa-solid fa-check"></i>
                    XÁC NHẬN ĐẶT HÀNG
                </button>

                <p class="cod-hint">
                    Đơn hàng sẽ được lưu vào hệ thống
                    và chuyển đến bộ phận quản lý.
                    Bạn chỉ thanh toán khi nhận hàng.
                </p>

            </div>

        </div>

    </form>

<?php endif; ?>

</div>
</section>

<script>
const checkoutForm = document.getElementById('checkoutForm');

if (checkoutForm) {

    checkoutForm.addEventListener('submit', function(event) {

        if (!checkoutForm.checkValidity()) {
            return;
        }

        const button = document.getElementById(
            'checkoutButton'
        );

        if (button) {
            button.disabled = true;
            button.innerHTML =
                'ĐANG XỬ LÝ ĐƠN HÀNG...';
        }

    });
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
