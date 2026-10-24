
<?php
ob_start();

$page_title = 'Thanh toán đơn hàng';
$active_menu = 'shop';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') . '/';
$qrImage = $base . 'assets/images/momo-qr-vu.jpg';

function ck_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ck_money($value) {
    return number_format((float)$value, 0, ',', '.') . ' đ';
}

function ck_name($value) {
    $value = trim((string)$value);
    if (class_exists('Transliterator')) {
        $t = Transliterator::create('Any-Latin; Latin-ASCII');
        if ($t) $value = $t->transliterate($value);
    }

    $value = strtr($value, ['đ'=>'d', 'Đ'=>'D']);
    $value = strtoupper($value);
    return preg_replace('/[^A-Z0-9]/', '', $value) ?: 'KHACHHANG';
}

function ck_phone($value) {
    return preg_replace('/\D/', '', (string)$value);
}

function ck_columns($pdo, $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `" . $table . "`");
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
}

function ck_field(&$data, $columns, $names, $value) {
    foreach ($names as $name) {
        if (in_array($name, $columns, true)) {
            $data[$name] = $value;
            return;
        }
    }
}

function ck_insert($pdo, $table, $data) {
    if (!$data) throw new Exception('Thiếu dữ liệu lưu đơn');

    $fields = array_keys($data);
    foreach ($fields as $field) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            throw new Exception('Tên cột không hợp lệ');
        }
    }

    $cols = '`' . implode('`,`', $fields) . '`';
    $holders = implode(',', array_fill(0, count($fields), '?'));

    $stmt = $pdo->prepare(
        "INSERT INTO `$table` ($cols) VALUES ($holders)"
    );
    $stmt->execute(array_values($data));

    return (int)$pdo->lastInsertId();
}

function ck_cart() {
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) return [];

    $items = [];
    foreach ($cart as $key => $row) {
        if (!is_array($row)) continue;

        $qty = max(1, (int)($row['quantity'] ?? $row['qty'] ?? 1));
        $price = (float)($row['price'] ?? $row['sale_price'] ?? $row['unit_price'] ?? 0);

        $items[] = [
            'id' => (int)($row['product_id'] ?? $row['id'] ?? 0),
            'name' => (string)($row['name'] ?? $row['product_name'] ?? 'Sản phẩm'),
            'size' => (string)($row['size'] ?? $row['variant'] ?? ''),
            'image' => (string)($row['image'] ?? $row['thumbnail'] ?? ''),
            'qty' => $qty,
            'price' => $price,
            'total' => $price * $qty
        ];
    }
    return $items;
}

$items = ck_cart();
$total = array_sum(array_column($items, 'total'));

if (empty($_SESSION['checkout_order_code'])) {
    $_SESSION['checkout_order_code'] =
        'DH' . date('ymdHis') . random_int(100,999);
}

$orderCode = $_SESSION['checkout_order_code'];
$success = $_SESSION['checkout_success'] ?? null;

if ($success) {
    unset($_SESSION['checkout_success']);
}

$user = $_SESSION['user'] ?? [];
$error = '';

$old = [
    'fullname' => $_POST['fullname'] ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'address' => $_POST['address'] ?? ($user['address'] ?? ''),
    'note' => $_POST['note'] ?? '',
    'payment_method' => $_POST['payment_method'] ?? 'cod'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $name = trim($old['fullname']);
    $phone = ck_phone($old['phone']);
    $email = trim($old['email']);
    $address = trim($old['address']);
    $note = trim($old['note']);
    $method = $old['payment_method'];

    $transfer = 'SHOP ' . $orderCode . ' ' .
        ck_name($name) . ' ' . $phone;

    if (!$items) {
        $error = 'Giỏ hàng đang trống.';
    } elseif (!$name || strlen($phone) < 9 || !$address) {
        $error = 'Vui lòng nhập đúng họ tên, số điện thoại và địa chỉ.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (!in_array($method, ['cod','qr'], true)) {
        $error = 'Phương thức thanh toán không hợp lệ.';
    } else {
        try {
            $pdo->beginTransaction();

            $columns = ck_columns($pdo, 'orders');
            $data = [];

            ck_field($data,$columns,['order_code','code','order_no'],$orderCode);
            ck_field($data,$columns,['user_id','customer_id'],(int)($_SESSION['user_id'] ?? 0) ?: null);
            ck_field($data,$columns,['customer_name','fullname','full_name','name'],$name);
            ck_field($data,$columns,['phone','phone_number','mobile'],$phone);
            ck_field($data,$columns,['email'],$email);
            ck_field($data,$columns,['address','shipping_address'],$address);

            $fullNote = $note;
            if ($method === 'qr') {
                $fullNote .= "\n[CHỜ THANH TOÁN CHUYỂN KHOẢN]";
                $fullNote .= "\nNội dung: " . $transfer;
            }

            ck_field($data,$columns,['note','notes','message'],trim($fullNote));
            ck_field($data,$columns,['subtotal'],$total);
            ck_field($data,$columns,['shipping_fee','shipping'],0);
            ck_field($data,$columns,['total_amount','total','grand_total'],$total);
            ck_field($data,$columns,['payment_method'],$method);
            ck_field($data,$columns,['payment_status'],'pending');
            ck_field($data,$columns,['status'],'pending');
            ck_field($data,$columns,['transfer_content'],$transfer);
            ck_field($data,$columns,['created_at'],date('Y-m-d H:i:s'));

            $orderId = ck_insert($pdo,'orders',$data);

            $itemColumns = ck_columns($pdo,'order_items');

            foreach ($items as $item) {
                $d = [];
                ck_field($d,$itemColumns,['order_id'],$orderId);
                ck_field($d,$itemColumns,['product_id','item_id'],$item['id']);
                ck_field($d,$itemColumns,['product_name','name','item_name'],$item['name']);
                ck_field($d,$itemColumns,['size','variant'],$item['size']);
                ck_field($d,$itemColumns,['price','unit_price'],$item['price']);
                ck_field($d,$itemColumns,['quantity','qty'],$item['qty']);
                ck_field($d,$itemColumns,['subtotal','line_total','total'],$item['total']);
                ck_insert($pdo,'order_items',$d);
            }

            $pdo->commit();

            $_SESSION['checkout_success'] = [
                'id' => $orderId,
                'code' => $orderCode,
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'total' => $total,
                'method' => $method,
                'transfer' => $transfer
            ];

            // Chi xoa gio sau khi database luu thanh cong
            $_SESSION['cart'] = [];
            $_SESSION['cart_count'] = 0;
            unset($_SESSION['checkout_order_code']);

            header('Location: ' . $base . 'shop/checkout.php?success=1');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Checkout: ' . $e->getMessage());
            $error = 'Không thể tạo đơn hàng. Vui lòng thử lại.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.vnn-checkout {
    padding: 40px 16px 70px;
    background: #f8fafc;
    min-height: 65vh;
}
.vnn-wrap {max-width:1160px;margin:auto}
.vnn-heading {
    text-align:center;color:#14532d;
    font-size:28px;margin-bottom:28px
}
.vnn-grid {display:grid;grid-template-columns:1.1fr .9fr;gap:25px}
.vnn-card {
    background:white;padding:25px;border-radius:16px;
    box-shadow:0 5px 22px #0000000d
}
.vnn-card h3 {font-size:19px;margin:0 0 18px}
.vnn-group {margin-bottom:16px}
.vnn-group label {display:block;font-weight:700;margin-bottom:7px}
.vnn-group input,.vnn-group textarea {
    width:100%;padding:12px;border:1px solid #d8e0ea;
    border-radius:9px;box-sizing:border-box;font:inherit
}
.vnn-group textarea {min-height:95px}
.vnn-two {display:grid;grid-template-columns:1fr 1fr;gap:15px}
.vnn-method {
    display:block;margin:12px 0;padding:17px;
    border:1px solid #dae2ea;border-radius:12px;
    cursor:pointer;background:#fff
}
.vnn-method:has(input:checked) {
    border-color:#16a34a;background:#f0fdf4
}
.vnn-method strong {margin-left:8px}
.vnn-method p {
    color:#64748b;font-size:14px;
    line-height:1.6;margin:10px 0 0
}
.vnn-qr {
    display:none;background:#fff7fb;
    border:2px dashed #f9a8d4;padding:20px;
    border-radius:15px;margin-top:18px
}
.vnn-qr.show {display:block}
.vnn-qr-inner {
    display:grid;grid-template-columns:230px 1fr;
    align-items:start;gap:20px
}
.vnn-qr img {
    width:100%;height:auto;border-radius:12px;
    background:white
}
.vnn-transfer {
    padding:15px;background:white;
    border:1px solid #e5e7eb;border-radius:12px
}
.vnn-transfer-code {
    font-weight:800;color:#c2410c;
    background:#fff7ed;border:1px solid #fed7aa;
    border-radius:8px;padding:12px;
    overflow-wrap:anywhere;margin:12px 0
}
.vnn-copy {
    background:#14532d;color:white;border:0;
    border-radius:8px;padding:10px 15px;cursor:pointer
}
.vnn-item {
    padding:12px 0;border-bottom:1px solid #e5e7eb
}
.vnn-row {
    display:flex;justify-content:space-between;
    gap:12px;margin:12px 0
}
.vnn-total {
    color:#ea580c;font-size:21px;
    font-weight:800;border-top:1px solid #ddd;
    padding-top:16px
}
.vnn-submit {
    display:block;width:100%;background:#f97316;
    border:0;border-radius:12px;color:white;
    font-weight:800;font-size:17px;
    padding:15px;cursor:pointer;margin-top:22px
}
.vnn-alert {padding:17px;border-radius:10px;margin-bottom:20px}
.vnn-error {background:#fee2e2;color:#b91c1c}
.vnn-ok {background:#dcfce7;color:#166534}
.vnn-success {max-width:850px;margin:auto}
.vnn-details {display:grid;grid-template-columns:1fr 1fr;gap:12px}
.vnn-detail {
    background:#f8fafc;border-radius:10px;padding:14px;
    overflow-wrap:anywhere
}
.vnn-note {font-size:14px;color:#64748b;line-height:1.7}
@media(max-width:850px) {
    .vnn-grid,.vnn-qr-inner,.vnn-two,.vnn-details {
        grid-template-columns:1fr
    }
}
</style>

<div class="vnn-checkout">
<div class="vnn-wrap">

<h1 class="vnn-heading">THANH TOÁN ĐƠN HÀNG</h1>

<?php if ($success): ?>

<div class="vnn-card vnn-success">

    <div class="vnn-alert vnn-ok">
        <strong>Đặt hàng thành công!</strong>
        Đơn hàng của bạn đã được lưu vào hệ thống.
    </div>

    <div class="vnn-details">
        <div class="vnn-detail">
            <small>Mã đơn hàng</small>
            <h3><?= ck_h($success['code']) ?></h3>
        </div>
        <div class="vnn-detail">
            <small>Khách hàng</small>
            <h3><?= ck_h($success['name']) ?></h3>
        </div>
        <div class="vnn-detail">
            <small>Số điện thoại</small>
            <h3><?= ck_h($success['phone']) ?></h3>
        </div>
        <div class="vnn-detail">
            <small>Tổng tiền</small>
            <h3><?= ck_money($success['total']) ?></h3>
        </div>
    </div>

    <?php if ($success['method'] === 'qr'): ?>

    <h3 style="margin-top:25px">
        Thanh toán bằng hình thức chuyển khoản
    </h3>

    <div class="vnn-qr show">
        <div class="vnn-qr-inner">

            <img src="<?= ck_h($qrImage) ?>"
                 alt="Mã QR thanh toán">

            <div class="vnn-transfer">
                <p><strong>Số tiền cần chuyển</strong></p>
                <h3 style="color:#ea580c">
                    <?= ck_money($success['total']) ?>
                </h3>

                <p><strong>Nội dung chuyển khoản bắt buộc</strong></p>

                <div class="vnn-transfer-code" id="transfer-success">
                    <?= ck_h($success['transfer']) ?>
                </div>

                <button type="button" class="vnn-copy"
                    onclick="copyTransfer('transfer-success')">
                    Sao chép nội dung
                </button>

                <p class="vnn-note">
                    Vui lòng chuyển đúng số tiền và nội dung.
                    Đơn hàng hiện đang chờ thanh toán.
                    Quản lý sẽ kiểm tra giao dịch và xác nhận thủ công.
                </p>
            </div>
        </div>
    </div>

    <?php else: ?>

    <p class="vnn-note" style="margin-top:22px">
        Phương thức: Thanh toán khi nhận hàng (COD).
        Nhân viên sẽ liên hệ xác nhận đơn hàng.
    </p>

    <?php endif; ?>

    <div style="margin-top:25px">
        <a href="<?= $base ?>shop/shop.php" class="vnn-copy"
           style="text-decoration:none;display:inline-block">
            Tiếp tục mua hàng
        </a>
    </div>
</div>

<?php elseif (!$items): ?>

<div class="vnn-card" style="text-align:center">
    <h3>Giỏ hàng đang trống</h3>
    <a href="<?= $base ?>shop/shop.php">Quay lại cửa hàng</a>
</div>

<?php else: ?>

<?php if ($error): ?>
<div class="vnn-alert vnn-error"><?= ck_h($error) ?></div>
<?php endif; ?>

<form method="POST" id="checkout-form">
<input type="hidden" name="place_order" value="1">

<div class="vnn-grid">
<div class="vnn-card">

    <h3>Thông tin giao hàng</h3>

    <div class="vnn-group">
        <label>Họ và tên *</label>
        <input name="fullname" id="ck-name" required
               value="<?= ck_h($old['fullname']) ?>">
    </div>

    <div class="vnn-two">
        <div class="vnn-group">
            <label>Số điện thoại *</label>
            <input name="phone" id="ck-phone" required
                   inputmode="tel"
                   value="<?= ck_h($old['phone']) ?>">
        </div>

        <div class="vnn-group">
            <label>Email</label>
            <input type="email" name="email"
                   value="<?= ck_h($old['email']) ?>">
        </div>
    </div>

    <div class="vnn-group">
        <label>Địa chỉ giao hàng *</label>
        <input name="address" required
               value="<?= ck_h($old['address']) ?>">
    </div>

    <div class="vnn-group">
        <label>Ghi chú</label>
        <textarea name="note"><?= ck_h($old['note']) ?></textarea>
    </div>

    <h3>Phương thức thanh toán</h3>

    <label class="vnn-method">
        <input type="radio" name="payment_method" value="cod"
               <?= $old['payment_method']==='cod' ? 'checked' : '' ?>>
        <strong>Thanh toán khi nhận hàng (COD)</strong>
        <p>Thanh toán trực tiếp khi nhận sản phẩm.</p>
    </label>

    <label class="vnn-method">
        <input type="radio" name="payment_method" value="qr"
               <?= $old['payment_method']==='qr' ? 'checked' : '' ?>>
        <strong>Thanh toán bằng hình thức chuyển khoản</strong>
        <p>Quét mã QR và nhập đúng nội dung chuyển khoản.</p>
    </label>

    <div id="qr-panel" class="vnn-qr">
        <div class="vnn-qr-inner">

            <img src="<?= ck_h($qrImage) ?>"
                 alt="QR thanh toán">

            <div class="vnn-transfer">
                <strong>Nội dung chuyển khoản</strong>
                <p class="vnn-note">
                    Cú pháp: SHOP + Mã đơn + Tên khách + SĐT đầy đủ
                </p>

                <div class="vnn-transfer-code" id="transfer-preview"></div>

                <button type="button" class="vnn-copy"
                    onclick="copyTransfer('transfer-preview')">
                    Sao chép nội dung
                </button>

                <p class="vnn-note">
                    Số tiền: <strong><?= ck_money($total) ?></strong>
                </p>
            </div>

        </div>
    </div>

</div>

<div class="vnn-card">
    <h3>Đơn hàng của bạn</h3>

    <?php foreach ($items as $item): ?>
    <div class="vnn-item">
        <strong><?= ck_h($item['name']) ?></strong>

        <?php if ($item['size']): ?>
            <div>Phân loại: <?= ck_h($item['size']) ?></div>
        <?php endif; ?>

        <div class="vnn-row">
            <span><?= (int)$item['qty'] ?> × <?= ck_money($item['price']) ?></span>
            <strong><?= ck_money($item['total']) ?></strong>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="vnn-row">
        <span>Tạm tính</span>
        <strong><?= ck_money($total) ?></strong>
    </div>

    <div class="vnn-row">
        <span>Phí giao hàng</span>
        <strong>Miễn phí</strong>
    </div>

    <div class="vnn-row vnn-total">
        <span>Tổng cộng</span>
        <span><?= ck_money($total) ?></span>
    </div>

    <button type="submit" class="vnn-submit" id="submit-order">
        XÁC NHẬN ĐẶT HÀNG
    </button>

    <p class="vnn-note">
        Đơn hàng chỉ được tạo khi bạn xác nhận.
        Chuyển khoản sẽ được kiểm tra thủ công, không tự động
        đánh dấu đã thanh toán.
    </p>
</div>
</div>
</form>

<?php endif; ?>

</div>
</div>

<script>
const ckOrderCode = <?= json_encode($orderCode) ?>;

function transferName(text) {
    return (text || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g,'d')
        .replace(/Đ/g,'D')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g,'') || 'KHACHHANG';
}

function updateCheckout() {
    const panel = document.getElementById('qr-panel');
    if (!panel) return;

    const method = document.querySelector(
        'input[name="payment_method"]:checked'
    )?.value || 'cod';

    panel.classList.toggle('show', method === 'qr');

    const name = document.getElementById('ck-name')?.value || '';
    const phone = (document.getElementById('ck-phone')?.value || '')
        .replace(/\D/g,'');

    document.getElementById('transfer-preview').textContent =
        'SHOP ' + ckOrderCode + ' ' +
        transferName(name) + ' ' + phone;
}

async function copyTransfer(id) {
    const text = document.getElementById(id)?.textContent.trim();
    if (!text) return;

    try {
        await navigator.clipboard.writeText(text);
        alert('Đã sao chép nội dung chuyển khoản!');
    } catch (e) {
        window.prompt('Sao chép nội dung này:', text);
    }
}

document.querySelectorAll('input[name="payment_method"]')
    .forEach(el => el.addEventListener('change', updateCheckout));

document.getElementById('ck-name')
    ?.addEventListener('input', updateCheckout);

document.getElementById('ck-phone')
    ?.addEventListener('input', updateCheckout);

updateCheckout();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
