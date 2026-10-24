
<?php
ob_start();

$page_title = 'Thanh toán đơn hàng';
$active_menu = 'shop';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$base = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') . '/';

function co_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function co_money($v) {
    return number_format((float)$v, 0, ',', '.') . ' đ';
}

function co_columns($pdo, $table) {
    $s = $pdo->query("SHOW COLUMNS FROM `$table`");
    $result = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $result[$c['Field']] = $c;
    }
    return $result;
}

function co_put(&$data, $cols, $names, $value) {
    foreach ($names as $name) {
        if (isset($cols[$name])) {
            $data[$name] = $value;
            return;
        }
    }
}

function co_enum($column, $choices) {
    $type = $column['Type'];

    if (!preg_match('/^enum\((.*)\)$/is', $type, $m)) {
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
        'Trạng thái database không tương thích.'
    );
}

function co_insert($pdo, $table, $data) {
    if (!$data) throw new RuntimeException('Thiếu dữ liệu.');

    $names = array_keys($data);
    foreach ($names as $name) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException('Tên cột không hợp lệ.');
        }
    }

    $fields = '`' . implode('`,`', $names) . '`';
    $marks = implode(',', array_fill(0, count($names), '?'));

    $s = $pdo->prepare(
        "INSERT INTO `$table` ($fields) VALUES ($marks)"
    );
    $s->execute(array_values($data));

    return (int)$pdo->lastInsertId();
}

$cart = $_SESSION['cart'] ?? [];
$items = [];

if (is_array($cart)) {
    foreach ($cart as $row) {
        if (!is_array($row)) continue;

        $qty = max(1, (int)($row['quantity'] ?? $row['qty'] ?? 1));
        $price = (float)($row['price'] ?? $row['sale_price'] ?? 0);

        $items[] = [
            'id' => (int)($row['product_id'] ?? $row['id'] ?? 0),
            'name' => (string)($row['name'] ?? $row['product_name'] ?? ''),
            'size' => (string)($row['size'] ?? $row['variant'] ?? ''),
            'qty' => $qty,
            'price' => $price,
            'total' => $qty * $price
        ];
    }
}

$total = array_sum(array_column($items, 'total'));
$error = '';
$success = null;

$userId = (int)($_SESSION['user_id'] ?? 0);
$user = null;

if ($userId > 0) {
    try {
        $s = $pdo->prepare(
            'SELECT id, fullname, email, phone FROM users WHERE id = ?'
        );
        $s->execute([$userId]);
        $user = $s->fetch(PDO::FETCH_ASSOC);

        if (!$user) $userId = 0;
    } catch (Throwable $e) {
        error_log('Checkout user: ' . $e->getMessage());
        $error = 'Không kiểm tra được tài khoản. Vui lòng thử lại.';
    }
}

if (empty($_SESSION['cod_token'])) {
    $_SESSION['cod_token'] = bin2hex(random_bytes(16));
}

if (empty($_SESSION['cod_code'])) {
    $_SESSION['cod_code'] =
        'DH' . strtoupper(bin2hex(random_bytes(5)));
}

$old = [
    'fullname' => $_POST['fullname'] ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'address' => $_POST['address'] ?? '',
    'note' => $_POST['note'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $token = (string)($_POST['cod_token'] ?? '');
    $name = trim((string)$old['fullname']);
    $phone = preg_replace('/\D+/', '', (string)$old['phone']);
    $email = trim((string)$old['email']);
    $address = trim((string)$old['address']);
    $note = trim((string)$old['note']);

    if ($error !== '') {
        // Giu nguyen loi kiem tra tai khoan.
    } elseif ($userId <= 0) {
        $error = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại để đặt hàng.';
    } elseif (
        !$token ||
        !hash_equals($_SESSION['cod_token'], $token)
    ) {
        $error = 'Phiên đặt hàng không hợp lệ. Vui lòng tải lại trang.';
    } elseif (!$items) {
        $error = 'Giỏ hàng đang trống.';
    } elseif (!$name || strlen($phone) < 9 || !$address) {
        $error = 'Vui lòng điền đủ họ tên, số điện thoại và địa chỉ.';
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } else {
        try {
            $pdo->beginTransaction();

            // Kiem tra tai khoan mot lan nua trong giao dich.
            $s = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            $s->execute([$userId]);

            if (!$s->fetchColumn()) {
                throw new RuntimeException(
                    'Tài khoản không còn tồn tại.'
                );
            }

            $cols = co_columns($pdo, 'orders');
            $data = [];

            co_put($data, $cols, ['order_code','code','order_no'], $_SESSION['cod_code']);
            co_put($data, $cols, ['user_id','customer_id'], $userId);
            co_put($data, $cols, ['customer_name','fullname','full_name','name'], $name);
            co_put($data, $cols, ['phone','phone_number','mobile'], $phone);
            co_put($data, $cols, ['email'], $email);
            co_put($data, $cols, ['address','shipping_address'], $address);
            co_put($data, $cols, ['note','notes','message'], $note);
            co_put($data, $cols, ['subtotal'], $total);
            co_put($data, $cols, ['shipping_fee'], 0);
            co_put($data, $cols, ['total_amount','total','grand_total'], $total);

            if (isset($cols['payment_method'])) {
                $data['payment_method'] = co_enum(
                    $cols['payment_method'],
                    ['cod','COD','cash','Cash','Thanh toán khi nhận hàng']
                );
            }

            if (isset($cols['payment_status'])) {
                $data['payment_status'] = co_enum(
                    $cols['payment_status'],
                    ['pending','unpaid','Chưa thanh toán','Chờ thanh toán','Chờ xác nhận']
                );
            }

            if (isset($cols['status'])) {
                $data['status'] = co_enum(
                    $cols['status'],
                    ['Chờ xác nhận','Chờ xử lý','Chờ duyệt','pending','Pending','Mới','new','Đang xử lý']
                );
            }

            co_put($data, $cols, ['created_at'], date('Y-m-d H:i:s'));

            $orderId = co_insert($pdo, 'orders', $data);
            $detailCols = co_columns($pdo, 'order_items');

            foreach ($items as $item) {
                $detail = [];

                co_put($detail, $detailCols, ['order_id'], $orderId);
                co_put($detail, $detailCols, ['product_id','item_id'], $item['id']);
                co_put($detail, $detailCols, ['product_name','name','item_name'], $item['name']);
                co_put($detail, $detailCols, ['size','variant'], $item['size']);
                co_put($detail, $detailCols, ['price','unit_price'], $item['price']);
                co_put($detail, $detailCols, ['quantity','qty'], $item['qty']);
                co_put($detail, $detailCols, ['subtotal','line_total','total'], $item['total']);

                co_insert($pdo, 'order_items', $detail);
            }

            $pdo->commit();

            $success = [
                'code' => $_SESSION['cod_code'],
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'total' => $total
            ];

            $_SESSION['cart'] = [];
            $_SESSION['cart_count'] = 0;
            unset($_SESSION['cod_token'], $_SESSION['cod_code']);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();

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
.cod-page{background:#f8fafc;min-height:70vh;padding:40px 16px 70px}
.cod-container{max-width:1160px;margin:auto}
.cod-title{text-align:center;color:#14532d;margin-bottom:30px}
.cod-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:24px}
.cod-card{background:white;border-radius:16px;padding:26px;box-shadow:0 5px 22px #0000000b}
.cod-card h3{margin:0 0 20px;color:#0f172a;font-size:19px}
.cod-field{margin-bottom:18px}
.cod-field label{display:block;font-weight:700;margin-bottom:8px}
.cod-field input,.cod-field textarea{width:100%;box-sizing:border-box;padding:13px;border:1px solid #d8e0eb;border-radius:9px;font:inherit}
.cod-field textarea{min-height:100px}
.cod-two{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.cod-payment{background:#f0fdf4;border:1px solid #86efac;padding:18px;border-radius:11px;line-height:1.7}
.cod-payment strong{color:#14532d}
.cod-item{padding:15px 0;border-bottom:1px solid #e5e7eb}
.cod-row{display:flex;justify-content:space-between;gap:14px;margin:13px 0}
.cod-total{color:#ea580c;font-size:21px;font-weight:800;border-top:1px solid #ddd;padding-top:15px}
.cod-button{background:#ea580c;border:0;border-radius:11px;color:white;padding:15px;font-size:16px;font-weight:800;width:100%;cursor:pointer;margin-top:19px}
.cod-button:disabled{opacity:.6}
.cod-error{padding:17px;border-radius:11px;background:#fee2e2;color:#991b1b;margin-bottom:22px}
.cod-success{padding:25px;border-radius:12px;background:#dcfce7;color:#166534;margin-bottom:24px}
.cod-link{display:inline-block;padding:13px 20px;border-radius:9px;background:#14532d;color:#fff;text-decoration:none;margin-top:16px}
@media(max-width:850px){.cod-grid,.cod-two{grid-template-columns:1fr}.cod-card{padding:20px}}
</style>

<section class="cod-page">
<div class="cod-container">

<h1 class="cod-title">THANH TOÁN ĐƠN HÀNG</h1>

<?php if ($success): ?>

<div class="cod-card" style="max-width:760px;margin:auto">
    <div class="cod-success">
        <h2>ĐẶT HÀNG THÀNH CÔNG!</h2>
        Đơn hàng đã được lưu vào hệ thống.
        Bạn chỉ thanh toán khi nhận hàng.
    </div>

    <div class="cod-row">
        <span>Mã đơn hàng</span>
        <strong><?= co_h($success['code']) ?></strong>
    </div>
    <div class="cod-row">
        <span>Khách hàng</span>
        <strong><?= co_h($success['name']) ?></strong>
    </div>
    <div class="cod-row">
        <span>Số điện thoại</span>
        <strong><?= co_h($success['phone']) ?></strong>
    </div>
    <div class="cod-row">
        <span>Địa chỉ</span>
        <strong><?= co_h($success['address']) ?></strong>
    </div>
    <div class="cod-row">
        <span>Thanh toán</span>
        <strong>Khi nhận hàng (COD)</strong>
    </div>
    <div class="cod-row cod-total">
        <span>Tổng tiền</span>
        <strong><?= co_money($success['total']) ?></strong>
    </div>

    <a class="cod-link" href="<?= co_h($base) ?>index.php">
        Về trang chủ
    </a>
</div>

<?php elseif ($userId <= 0): ?>

<div class="cod-card" style="max-width:700px;margin:auto;text-align:center">
    <h3>Yêu cầu đăng nhập</h3>
    <p>
        Website chưa nhận được phiên đăng nhập hợp lệ.
        Bạn cần đăng nhập để đặt hàng và theo dõi đơn.
    </p>
    <a class="cod-link" href="<?= co_h($base) ?>auth/login.php">
        Đăng nhập
    </a>
</div>

<?php elseif (!$items): ?>

<div class="cod-card" style="text-align:center">
    <h3>Giỏ hàng đang trống</h3>
    <a class="cod-link" href="<?= co_h($base) ?>index.php">
        Tiếp tục mua sắm
    </a>
</div>

<?php else: ?>

<?php if ($error): ?>
<div class="cod-error"><?= co_h($error) ?></div>
<?php endif; ?>

<form method="POST" id="cod-form">
<input type="hidden" name="place_order" value="1">
<input type="hidden" name="cod_token"
       value="<?= co_h($_SESSION['cod_token']) ?>">

<div class="cod-grid">
    <div class="cod-card">
        <h3>Thông tin giao hàng</h3>

        <div class="cod-field">
            <label>Họ và tên *</label>
            <input name="fullname" required
                   value="<?= co_h($old['fullname']) ?>">
        </div>

        <div class="cod-two">
            <div class="cod-field">
                <label>Số điện thoại *</label>
                <input type="tel" name="phone" required
                       value="<?= co_h($old['phone']) ?>">
            </div>

            <div class="cod-field">
                <label>Email</label>
                <input type="email" name="email"
                       value="<?= co_h($old['email']) ?>">
            </div>
        </div>

        <div class="cod-field">
            <label>Địa chỉ giao hàng *</label>
            <input name="address" required
                   value="<?= co_h($old['address']) ?>">
        </div>

        <div class="cod-field">
            <label>Ghi chú đơn hàng</label>
            <textarea name="note"><?= co_h($old['note']) ?></textarea>
        </div>

        <h3>Phương thức thanh toán</h3>
        <div class="cod-payment">
            <strong>Thanh toán khi nhận hàng (COD)</strong>
            <p>
                Bạn không phải chuyển khoản trước.
                Nhân viên sẽ giao hàng đến địa chỉ đã đăng ký.
                Bạn thanh toán khi nhận sản phẩm.
            </p>
        </div>
    </div>

    <div class="cod-card">
        <h3>Đơn hàng của bạn</h3>

        <?php foreach ($items as $item): ?>
        <div class="cod-item">
            <strong><?= co_h($item['name']) ?></strong>

            <?php if ($item['size']): ?>
            <p>Phân loại: <?= co_h($item['size']) ?></p>
            <?php endif; ?>

            <div class="cod-row">
                <span><?= $item['qty'] ?> × <?= co_money($item['price']) ?></span>
                <strong><?= co_money($item['total']) ?></strong>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="cod-row">
            <span>Tạm tính</span>
            <strong><?= co_money($total) ?></strong>
        </div>
        <div class="cod-row">
            <span>Phí giao hàng</span>
            <strong>Miễn phí</strong>
        </div>
        <div class="cod-row cod-total">
            <span>Tổng cộng</span>
            <strong><?= co_money($total) ?></strong>
        </div>

        <button type="submit" class="cod-button" id="cod-submit">
            XÁC NHẬN ĐẶT HÀNG
        </button>

        <p style="font-size:13px;color:#64748b">
            Đơn hàng sẽ được gửi đến quản lý.
            Bạn chỉ thanh toán khi nhận hàng.
        </p>
    </div>
</div>
</form>
<?php endif; ?>

</div>
</section>

<script>
const codForm = document.getElementById('cod-form');

if (codForm) {
    codForm.addEventListener('submit', function () {
        if (!codForm.checkValidity()) return;
        const button = document.getElementById('cod-submit');
        if (button) {
            button.disabled = true;
            button.textContent = 'ĐANG XỬ LÝ ĐƠN HÀNG...';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
