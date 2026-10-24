<?php
// VNN Travel - Thanh toan cua hang
// Chi su dung COD - Thanh toan khi nhan hang

ob_start();

$page_title = 'Thanh toán đơn hàng';
$active_menu = 'shop';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = rtrim(
    defined('BASE_URL') ? BASE_URL : '/',
    '/'
) . '/';

function cod_h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function cod_money($value) {
    return number_format(
        (float)$value, 0, ',', '.'
    ) . ' đ';
}

function cod_phone($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function cod_columns(PDO $pdo, $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $result = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $result[$column['Field']] = $column;
    }

    return $result;
}

function cod_field(&$data, $columns, $names, $value) {
    foreach ($names as $name) {
        if (isset($columns[$name])) {
            $data[$name] = $value;
            return;
        }
    }
}

// Chon gia tri hop le cua cot ENUM trong MySQL.
function cod_enum_value($columns, $field, $preferred) {
    if (!isset($columns[$field])) {
        return null;
    }

    $type = $columns[$field]['Type'];

    if (!preg_match('/^enum\((.*)\)$/is', $type, $m)) {
        return $preferred[0];
    }

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $m[1],
        $matches
    );

    $allowed = array_map(
        'stripcslashes',
        $matches[1]
    );

    foreach ($preferred as $value) {
        if (in_array($value, $allowed, true)) {
            return $value;
        }
    }

    throw new RuntimeException(
        "Cột $field chưa có trạng thái phù hợp."
    );
}

function cod_insert(PDO $pdo, $table, $data) {
    if (!$data) {
        throw new RuntimeException('Thiếu dữ liệu đơn hàng.');
    }

    $columns = array_keys($data);

    foreach ($columns as $column) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new RuntimeException('Tên cột không hợp lệ.');
        }
    }

    $fields = '`' . implode('`,`', $columns) . '`';

    $placeholders = implode(
        ',',
        array_fill(0, count($columns), '?')
    );

    $sql = "INSERT INTO `$table` ($fields)
            VALUES ($placeholders)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));

    return (int)$pdo->lastInsertId();
}

function cod_cart() {
    $cart = $_SESSION['cart'] ?? [];

    if (!is_array($cart)) {
        return [];
    }

    $items = [];

    foreach ($cart as $row) {
        if (!is_array($row)) {
            continue;
        }

        $qty = max(
            1,
            (int)($row['quantity'] ?? $row['qty'] ?? 1)
        );

        $price = (float)(
            $row['price']
            ?? $row['sale_price']
            ?? $row['unit_price']
            ?? 0
        );

        $items[] = [
            'id' => (int)(
                $row['product_id']
                ?? $row['id']
                ?? 0
            ),
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

$items = cod_cart();
$total = array_sum(array_column($items, 'total'));

if (empty($_SESSION['cod_order_code'])) {
    $_SESSION['cod_order_code'] =
        'DH' . date('ymd') . strtoupper(
            bin2hex(random_bytes(3))
        );
}

$orderCode = $_SESSION['cod_order_code'];

$user = $_SESSION['user'] ?? [];
$error = '';
$success = null;

if (
    isset($_GET['success']) &&
    !empty($_SESSION['cod_success'])
) {
    $success = $_SESSION['cod_success'];
    unset($_SESSION['cod_success']);
}

$old = [
    'fullname' => $_POST['fullname']
        ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone']
        ?? ($user['phone'] ?? ''),
    'email' => $_POST['email']
        ?? ($user['email'] ?? ''),
    'address' => $_POST['address']
        ?? ($user['address'] ?? ''),
    'note' => $_POST['note'] ?? ''
];

// Tao don hang COD
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['place_order'])
) {
    $name = trim((string)$old['fullname']);
    $phone = cod_phone($old['phone']);
    $email = trim((string)$old['email']);
    $address = trim((string)$old['address']);
    $note = trim((string)$old['note']);

    if (!$items) {
        $error = 'Giỏ hàng đang trống.';
    } elseif (
        $name === '' ||
        strlen($phone) < 9 ||
        $address === ''
    ) {
        $error =
            'Vui lòng nhập đầy đủ họ tên, số điện thoại và địa chỉ.';
    } elseif (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Địa chỉ email không hợp lệ.';
    } else {
        try {
            $pdo->beginTransaction();

            $columns = cod_columns($pdo, 'orders');
            $data = [];

            cod_field(
                $data, $columns,
                ['order_code','code','order_no'],
                $orderCode
            );

            $userId = (int)(
                $_SESSION['user_id']
                ?? ($user['id'] ?? 0)
            );

            cod_field(
                $data, $columns,
                ['user_id','customer_id'],
                $userId ?: null
            );

            cod_field(
                $data, $columns,
                ['customer_name','fullname','full_name','name'],
                $name
            );

            cod_field(
                $data, $columns,
                ['phone','phone_number','mobile'],
                $phone
            );

            cod_field(
                $data, $columns,
                ['email'],
                $email
            );

            cod_field(
                $data, $columns,
                ['address','shipping_address'],
                $address
            );

            cod_field(
                $data, $columns,
                ['note','notes','message'],
                $note
            );

            cod_field(
                $data, $columns,
                ['subtotal'],
                $total
            );

            cod_field(
                $data, $columns,
                ['shipping_fee','shipping'],
                0
            );

            cod_field(
                $data, $columns,
                ['total_amount','total','grand_total'],
                $total
            );

            // Chi thanh toan khi nhan hang.
            $methodColumn = null;

            foreach (['payment_method'] as $candidate) {
                if (isset($columns[$candidate])) {
                    $methodColumn = $candidate;
                    break;
                }
            }

            if ($methodColumn !== null) {
                $data[$methodColumn] = cod_enum_value(
                    $columns,
                    $methodColumn,
                    [
                        'cod',
                        'COD',
                        'cash',
                        'Cash',
                        'Thanh toán khi nhận hàng'
                    ]
                );
            }

            // Trang thai thanh toan: chua thu tien.
            if (isset($columns['payment_status'])) {
                $data['payment_status'] = cod_enum_value(
                    $columns,
                    'payment_status',
                    [
                        'pending',
                        'unpaid',
                        'Chưa thanh toán',
                        'Chờ thanh toán',
                        'Chờ xác nhận',
                        'Pending'
                    ]
                );
            }

            // Trang thai don hang tu dong chon tu ENUM.
            if (isset($columns['status'])) {
                $data['status'] = cod_enum_value(
                    $columns,
                    'status',
                    [
                        'Chờ xác nhận',
                        'Chờ xử lý',
                        'Chờ duyệt',
                        'pending',
                        'Pending',
                        'Mới',
                        'new',
                        'Đang xử lý'
                    ]
                );
            }

            cod_field(
                $data, $columns,
                ['created_at'],
                date('Y-m-d H:i:s')
            );

            $orderId = cod_insert(
                $pdo,
                'orders',
                $data
            );

            // Luu san pham cua don hang
            $itemColumns = cod_columns(
                $pdo,
                'order_items'
            );

            foreach ($items as $item) {
                $detail = [];

                cod_field(
                    $detail, $itemColumns,
                    ['order_id'],
                    $orderId
                );

                cod_field(
                    $detail, $itemColumns,
                    ['product_id','item_id'],
                    $item['id']
                );

                cod_field(
                    $detail, $itemColumns,
                    ['product_name','name','item_name'],
                    $item['name']
                );

                cod_field(
                    $detail, $itemColumns,
                    ['size','variant'],
                    $item['size']
                );

                cod_field(
                    $detail, $itemColumns,
                    ['price','unit_price'],
                    $item['price']
                );

                cod_field(
                    $detail, $itemColumns,
                    ['quantity','qty'],
                    $item['qty']
                );

                cod_field(
                    $detail, $itemColumns,
                    ['subtotal','line_total','total'],
                    $item['total']
                );

                cod_insert(
                    $pdo,
                    'order_items',
                    $detail
                );
            }

            $pdo->commit();

            // Chi xoa gio hang khi da luu thanh cong
            $_SESSION['cart'] = [];
            $_SESSION['cart_count'] = 0;

            $_SESSION['cod_success'] = [
                'code' => $orderCode,
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'total' => $total,
                'items' => $items
            ];

            unset($_SESSION['cod_order_code']);

            header(
                'Location: ' .
                $base .
                'shop/checkout.php?success=1',
                true,
                303
            );
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'COD Checkout: ' . $e->getMessage()
            );

            $error =
                'Không thể tạo đơn hàng. Vui lòng thử lại.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.cod-page {
    background:#f8fafc;
    padding:40px 15px 70px;
    min-height:70vh;
}

.cod-container {
    max-width:1160px;
    margin:0 auto;
}

.cod-title {
    color:#14532d;
    text-align:center;
    font-size:29px;
    font-weight:800;
    margin-bottom:30px;
}

.cod-grid {
    display:grid;
    grid-template-columns:1.1fr .9fr;
    gap:24px;
}

.cod-card {
    background:white;
    padding:26px;
    border-radius:17px;
    box-shadow:0 5px 22px rgba(0,0,0,.06);
}

.cod-card h3 {
    font-size:19px;
    font-weight:800;
    color:#0f172a;
    margin:0 0 22px;
}

.cod-field {
    margin-bottom:18px;
}

.cod-field label {
    display:block;
    font-weight:700;
    color:#0f172a;
    margin-bottom:9px;
}

.cod-field input,
.cod-field textarea {
    width:100%;
    border:1px solid #d7e0eb;
    border-radius:10px;
    padding:13px 15px;
    font:inherit;
    box-sizing:border-box;
    outline:none;
}

.cod-field input:focus,
.cod-field textarea:focus {
    border-color:#16a34a;
    box-shadow:0 0 0 3px rgba(22,163,74,.08);
}

.cod-field textarea {
    min-height:110px;
    resize:vertical;
}

.cod-two {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

.cod-payment {
    background:#f0fdf4;
    border:1px solid #86efac;
    border-radius:12px;
    padding:19px;
    margin-top:15px;
}

.cod-payment strong {
    color:#14532d;
    font-size:17px;
}

.cod-payment p {
    color:#475569;
    font-size:14px;
    line-height:1.7;
    margin:10px 0 0;
}

.cod-item {
    padding:15px 0;
    border-bottom:1px solid #e5e7eb;
}

.cod-item-name {
    color:#0f172a;
    font-weight:800;
    margin-bottom:7px;
}

.cod-item-meta {
    color:#64748b;
    font-size:14px;
    margin-bottom:8px;
}

.cod-row {
    display:flex;
    justify-content:space-between;
    gap:15px;
    margin:13px 0;
}

.cod-total {
    border-top:1px solid #e5e7eb;
    padding-top:17px;
    color:#ea580c;
    font-size:21px;
    font-weight:900;
}

.cod-submit {
    width:100%;
    padding:16px;
    border:0;
    border-radius:12px;
    color:#fff;
    background:#ea580c;
    font-weight:800;
    font-size:17px;
    cursor:pointer;
    margin-top:20px;
}

.cod-submit:disabled {
    opacity:.6;
    cursor:wait;
}

.cod-error {
    background:#fee2e2;
    color:#b91c1c;
    padding:17px;
    border-radius:11px;
    margin-bottom:20px;
}

.cod-success {
    max-width:800px;
    margin:auto;
}

.cod-success-icon {
    font-size:50px;
    color:#16a34a;
    text-align:center;
}

.cod-success h2 {
    text-align:center;
    color:#14532d;
    margin:15px 0;
}

.cod-success-note {
    background:#dcfce7;
    color:#166534;
    padding:18px;
    border-radius:12px;
    text-align:center;
    line-height:1.8;
    margin:22px 0;
}

.cod-info {
    background:#f8fafc;
    padding:20px;
    border-radius:12px;
}

.cod-link {
    display:inline-block;
    background:#14532d;
    color:white;
    text-decoration:none;
    padding:13px 20px;
    border-radius:10px;
    margin-top:20px;
    font-weight:700;
}

@media(max-width:850px) {
    .cod-grid {
        grid-template-columns:1fr;
    }

    .cod-two {
        grid-template-columns:1fr;
    }

    .cod-card {
        padding:20px;
    }
}
</style>

<section class="cod-page">
<div class="cod-container">

<h1 class="cod-title">THANH TOÁN ĐƠN HÀNG</h1>

<?php if ($success): ?>

<div class="cod-card cod-success">

    <div class="cod-success-icon">
        <i class="fa-solid fa-circle-check"></i>
    </div>

    <h2>ĐẶT HÀNG THÀNH CÔNG!</h2>

    <div class="cod-success-note">
        Đơn hàng của bạn đã được ghi nhận thành công.
        Nhân viên VNN Travel sẽ liên hệ để xác nhận
        và giao hàng trong thời gian sớm nhất.
    </div>

    <div class="cod-info">

        <div class="cod-row">
            <span>Mã đơn hàng</span>
            <strong><?= cod_h($success['code']) ?></strong>
        </div>

        <div class="cod-row">
            <span>Khách hàng</span>
            <strong><?= cod_h($success['name']) ?></strong>
        </div>

        <div class="cod-row">
            <span>Số điện thoại</span>
            <strong><?= cod_h($success['phone']) ?></strong>
        </div>

        <div class="cod-row">
            <span>Địa chỉ giao hàng</span>
            <strong><?= cod_h($success['address']) ?></strong>
        </div>

        <div class="cod-row">
            <span>Phương thức thanh toán</span>
            <strong>Thanh toán khi nhận hàng (COD)</strong>
        </div>

        <div class="cod-row cod-total">
            <span>Tổng tiền cần thanh toán</span>
            <strong><?= cod_money($success['total']) ?></strong>
        </div>

    </div>

    <p style="margin-top:20px;color:#64748b">
        Bạn chỉ thanh toán khi nhận được hàng.
        Giỏ hàng đã được xóa sau khi lưu đơn thành công.
    </p>

    <div style="text-align:center">
        <a href="<?= cod_h($base) ?>index.php"
           class="cod-link">
            Về trang chủ
        </a>
    </div>

</div>

<?php elseif (!$items): ?>

<div class="cod-card" style="text-align:center">
    <h3>Giỏ hàng đang trống</h3>

    <p>Bạn chưa có sản phẩm nào để thanh toán.</p>

    <a href="<?= cod_h($base) ?>index.php"
       class="cod-link">
        Tiếp tục mua hàng
    </a>
</div>

<?php else: ?>

<?php if ($error): ?>
<div class="cod-error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?= cod_h($error) ?>
</div>
<?php endif; ?>

<form method="POST" id="cod-form">

<input type="hidden" name="place_order" value="1">

<div class="cod-grid">

    <div class="cod-card">

        <h3>
            <i class="fa-solid fa-truck"></i>
            Thông tin giao hàng
        </h3>

        <div class="cod-field">
            <label>Họ và tên *</label>

            <input type="text"
                   name="fullname"
                   value="<?= cod_h($old['fullname']) ?>"
                   required>
        </div>

        <div class="cod-two">

            <div class="cod-field">
                <label>Số điện thoại *</label>

                <input type="tel"
                       name="phone"
                       value="<?= cod_h($old['phone']) ?>"
                       required>
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
                   value="<?= cod_h($old['address']) ?>"
                   required>
        </div>

        <div class="cod-field">
            <label>Ghi chú đơn hàng</label>

            <textarea name="note"><?= cod_h($old['note']) ?></textarea>
        </div>

        <h3>Phương thức thanh toán</h3>

        <div class="cod-payment">

            <strong>
                <i class="fa-solid fa-money-bill-wave"></i>
                Thanh toán khi nhận hàng (COD)
            </strong>

            <p>
                Bạn không cần chuyển khoản trước.
                Khi nhân viên giao sản phẩm đến địa chỉ
                đã đăng ký, bạn kiểm tra và thanh toán
                trực tiếp khi nhận hàng.
            </p>

        </div>

    </div>

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
                <div class="cod-item-meta">
                    Phân loại:
                    <?= cod_h($item['size']) ?>
                </div>
            <?php endif; ?>

            <div class="cod-row">
                <span>
                    <?= (int)$item['qty'] ?>
                    ×
                    <?= cod_money($item['price']) ?>
                </span>

                <strong>
                    <?= cod_money($item['total']) ?>
                </strong>
            </div>

        </div>

        <?php endforeach; ?>

        <div class="cod-row">
            <span>Tạm tính</span>
            <strong><?= cod_money($total) ?></strong>
        </div>

        <div class="cod-row">
            <span>Phí giao hàng</span>
            <strong>Miễn phí</strong>
        </div>

        <div class="cod-row cod-total">
            <span>Tổng cộng</span>
            <strong><?= cod_money($total) ?></strong>
        </div>

        <button type="submit"
                class="cod-submit"
                id="cod-submit">
            <i class="fa-solid fa-check"></i>
            XÁC NHẬN ĐẶT HÀNG
        </button>

        <p style="color:#64748b;font-size:13px;line-height:1.7">
            Đơn hàng sẽ được gửi đến bộ phận quản lý.
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
    codForm.addEventListener('submit', function() {
        if (!codForm.checkValidity()) return;

        const button = document.getElementById('cod-submit');

        if (button) {
            button.disabled = true;
            button.textContent = 'ĐANG XỬ LÝ ĐƠN HÀNG...';
        }
    });
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
