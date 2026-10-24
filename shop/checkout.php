<?php
ob_start();

$page_title = 'Thanh toán đơn hàng';
$active_menu = 'shop';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CẤU HÌNH QR CỐ ĐỊNH
|--------------------------------------------------------------------------
*/
$fixedQrImage = '/assets/images/momo-qr-vu.jpg'; // upload ảnh QR vào đây
$bankOwnerName = 'NGUYEN NHU VU';
$bankMask = '*******253';
$bankTypeLabel = 'MoMo / VietQR cố định';

/*
|--------------------------------------------------------------------------
| HÀM HỖ TRỢ
|--------------------------------------------------------------------------
*/
if (!function_exists('vnn_money')) {
    function vnn_money($amount)
    {
        return number_format((float)$amount, 0, ',', '.') . ' đ';
    }
}

function vnn_h($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function vnn_strip_vietnamese($str)
{
    $map = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a',
        'â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a',
        'ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e',
        'ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o',
        'ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o',
        'ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u',
        'ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
        'đ'=>'d',
        'À'=>'A','Á'=>'A','Ạ'=>'A','Ả'=>'A','Ã'=>'A',
        'Â'=>'A','Ầ'=>'A','Ấ'=>'A','Ậ'=>'A','Ẩ'=>'A','Ẫ'=>'A',
        'Ă'=>'A','Ằ'=>'A','Ắ'=>'A','Ặ'=>'A','Ẳ'=>'A','Ẵ'=>'A',
        'È'=>'E','É'=>'E','Ẹ'=>'E','Ẻ'=>'E','Ẽ'=>'E',
        'Ê'=>'E','Ề'=>'E','Ế'=>'E','Ệ'=>'E','Ể'=>'E','Ễ'=>'E',
        'Ì'=>'I','Í'=>'I','Ị'=>'I','Ỉ'=>'I','Ĩ'=>'I',
        'Ò'=>'O','Ó'=>'O','Ọ'=>'O','Ỏ'=>'O','Õ'=>'O',
        'Ô'=>'O','Ồ'=>'O','Ố'=>'O','Ộ'=>'O','Ổ'=>'O','Ỗ'=>'O',
        'Ơ'=>'O','Ờ'=>'O','Ớ'=>'O','Ợ'=>'O','Ở'=>'O','Ỡ'=>'O',
        'Ù'=>'U','Ú'=>'U','Ụ'=>'U','Ủ'=>'U','Ũ'=>'U',
        'Ư'=>'U','Ừ'=>'U','Ứ'=>'U','Ự'=>'U','Ử'=>'U','Ữ'=>'U',
        'Ỳ'=>'Y','Ý'=>'Y','Ỵ'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y',
        'Đ'=>'D'
    ];
    return strtr($str, $map);
}

function vnn_slug_transfer_name($name)
{
    $name = vnn_strip_vietnamese($name);
    $name = strtoupper($name);
    $name = preg_replace('/[^A-Z0-9 ]/', '', $name);
    $parts = preg_split('/\s+/', trim($name));
    $parts = array_filter($parts);

    if (empty($parts)) {
        return 'KHACH';
    }

    // Lấy tối đa 2 từ cuối để gọn
    $parts = array_slice($parts, -2);
    $result = implode('', $parts);

    return substr($result, 0, 12);
}

function vnn_phone_digits($phone)
{
    return preg_replace('/\D+/', '', (string)$phone);
}

function vnn_last4_phone($phone)
{
    $digits = vnn_phone_digits($phone);
    if ($digits === '') return '0000';
    return substr(str_pad($digits, 4, '0', STR_PAD_LEFT), -4);
}

function vnn_generate_order_code()
{
    return 'DH' . date('ymdHis') . rand(100, 999);
}

function vnn_build_shop_transfer_content($orderCode, $fullName, $phone)
{
    $nameToken = vnn_slug_transfer_name($fullName);
    $phoneToken = vnn_last4_phone($phone);

    // Nội dung CK cho hàng hóa
    return 'SHOP ' . $orderCode . ' ' . $nameToken . ' ' . $phoneToken;
}

function vnn_get_cart_items()
{
    $cart = $_SESSION['cart'] ?? [];
    $items = [];

    if (!is_array($cart)) {
        return [];
    }

    foreach ($cart as $key => $item) {
        if (!is_array($item)) {
            continue;
        }

        $qty = (int)($item['quantity'] ?? $item['qty'] ?? 1);
        if ($qty < 1) $qty = 1;

        $price = (float)($item['price'] ?? $item['sale_price'] ?? $item['unit_price'] ?? 0);

        $id = (int)($item['product_id'] ?? $item['id'] ?? 0);
        $name = trim((string)($item['name'] ?? $item['product_name'] ?? 'Sản phẩm'));
        $image = trim((string)($item['image'] ?? $item['thumbnail'] ?? $item['main_image'] ?? ''));
        $size = trim((string)($item['size'] ?? $item['variant'] ?? ''));
        $slug = trim((string)($item['slug'] ?? ''));

        $items[] = [
            'product_id' => $id,
            'name'       => $name,
            'price'      => $price,
            'quantity'   => $qty,
            'subtotal'   => $price * $qty,
            'image'      => $image,
            'size'       => $size,
            'slug'       => $slug
        ];
    }

    return $items;
}

function vnn_cart_total($items)
{
    $total = 0;
    foreach ($items as $item) {
        $total += (float)($item['subtotal'] ?? 0);
    }
    return $total;
}

function vnn_get_table_columns(PDO $pdo, $table)
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'Field');
    } catch (Throwable $e) {
        return [];
    }
}

function vnn_set_first_existing_field(&$data, $columns, $candidateFields, $value)
{
    foreach ($candidateFields as $field) {
        if (in_array($field, $columns, true)) {
            $data[$field] = $value;
            return;
        }
    }
}

function vnn_dynamic_insert(PDO $pdo, $table, $data)
{
    if (empty($data)) {
        throw new Exception("Không có dữ liệu để lưu vào bảng {$table}");
    }

    $fields = array_keys($data);
    $placeholders = array_map(function ($f) {
        return ':' . $f;
    }, $fields);

    $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $fields) . "`) VALUES (" . implode(',', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);

    foreach ($data as $field => $value) {
        $stmt->bindValue(':' . $field, $value);
    }

    $stmt->execute();
    return $pdo->lastInsertId();
}

function vnn_guess_user_id()
{
    if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }

    if (isset($_SESSION['customer']) && is_array($_SESSION['customer']) && isset($_SESSION['customer']['id'])) {
        return (int)$_SESSION['customer']['id'];
    }

    return 0;
}

/*
|--------------------------------------------------------------------------
| DỮ LIỆU GIỎ HÀNG
|--------------------------------------------------------------------------
*/
$cartItems = vnn_get_cart_items();
$subtotal = vnn_cart_total($cartItems);
$shippingFee = 0;
$grandTotal = $subtotal + $shippingFee;

if (empty($_SESSION['checkout_order_code'])) {
    $_SESSION['checkout_order_code'] = vnn_generate_order_code();
}
$previewOrderCode = $_SESSION['checkout_order_code'];

$userInfo = $_SESSION['user'] ?? [];
$old = [
    'fullname'       => $_POST['fullname'] ?? ($userInfo['fullname'] ?? $userInfo['name'] ?? ''),
    'phone'          => $_POST['phone'] ?? ($userInfo['phone'] ?? ''),
    'email'          => $_POST['email'] ?? ($userInfo['email'] ?? ''),
    'address'        => $_POST['address'] ?? ($userInfo['address'] ?? ''),
    'note'           => $_POST['note'] ?? '',
    'payment_method' => $_POST['payment_method'] ?? 'cod'
];

$errorMessage = '';
$successData = $_SESSION['checkout_success'] ?? null;

if ($successData) {
    unset($_SESSION['checkout_success']);
    unset($_SESSION['checkout_order_code']);
}

/*
|--------------------------------------------------------------------------
| XỬ LÝ ĐẶT HÀNG
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $fullname = trim((string)($_POST['fullname'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $paymentMethod = trim((string)($_POST['payment_method'] ?? 'cod'));
    $postedOrderCode = trim((string)($_POST['order_code'] ?? ''));
    $orderCode = $postedOrderCode !== '' ? $postedOrderCode : vnn_generate_order_code();

    $old = [
        'fullname'       => $fullname,
        'phone'          => $phone,
        'email'          => $email,
        'address'        => $address,
        'note'           => $note,
        'payment_method' => $paymentMethod
    ];

    if (empty($cartItems)) {
        $errorMessage = 'Giỏ hàng đang trống. Vui lòng thêm sản phẩm trước khi thanh toán.';
    } elseif ($fullname === '' || $phone === '' || $address === '') {
        $errorMessage = 'Vui lòng nhập đầy đủ họ tên, số điện thoại và địa chỉ nhận hàng.';
    } elseif (!in_array($paymentMethod, ['cod', 'qr'], true)) {
        $errorMessage = 'Phương thức thanh toán không hợp lệ.';
    } else {
        $transferContent = vnn_build_shop_transfer_content($orderCode, $fullname, $phone);
        $paymentInstruction = '';

        if ($paymentMethod === 'qr') {
            $paymentInstruction =
                "[THANH TOÁN QR]\n" .
                "Mã đơn: {$orderCode}\n" .
                "Nội dung chuyển khoản: {$transferContent}\n" .
                "Tổng tiền: {$grandTotal}\n";
        }

        $mergedNote = trim($note);
        if ($paymentInstruction !== '') {
            $mergedNote .= ($mergedNote !== '' ? "\n\n" : '') . $paymentInstruction;
        }

        try {
            $pdo->beginTransaction();

            /*
            --------------------------------------------------------------
            | 1. INSERT ORDERS
            --------------------------------------------------------------
            */
            $orderColumns = vnn_get_table_columns($pdo, 'orders');
            if (empty($orderColumns)) {
                throw new Exception('Không tìm thấy bảng orders.');
            }

            $orderData = [];

            vnn_set_first_existing_field($orderData, $orderColumns, ['order_code', 'code', 'order_no'], $orderCode);
            vnn_set_first_existing_field($orderData, $orderColumns, ['user_id', 'customer_id'], vnn_guess_user_id() ?: null);
            vnn_set_first_existing_field($orderData, $orderColumns, ['customer_name', 'fullname', 'full_name', 'name'], $fullname);
            vnn_set_first_existing_field($orderData, $orderColumns, ['phone', 'phone_number', 'mobile'], $phone);
            vnn_set_first_existing_field($orderData, $orderColumns, ['email'], $email);
            vnn_set_first_existing_field($orderData, $orderColumns, ['address', 'shipping_address'], $address);
            vnn_set_first_existing_field($orderData, $orderColumns, ['note', 'notes', 'message'], $mergedNote);
            vnn_set_first_existing_field($orderData, $orderColumns, ['subtotal'], $subtotal);
            vnn_set_first_existing_field($orderData, $orderColumns, ['shipping_fee', 'shipping'], $shippingFee);
            vnn_set_first_existing_field($orderData, $orderColumns, ['total_amount', 'total', 'grand_total'], $grandTotal);
            vnn_set_first_existing_field($orderData, $orderColumns, ['payment_method'], $paymentMethod);
            vnn_set_first_existing_field($orderData, $orderColumns, ['payment_status'], $paymentMethod === 'qr' ? 'unpaid' : 'pending');
            vnn_set_first_existing_field($orderData, $orderColumns, ['status'], $paymentMethod === 'qr' ? 'pending_payment' : 'pending');
            vnn_set_first_existing_field($orderData, $orderColumns, ['transfer_content'], $transferContent);
            vnn_set_first_existing_field($orderData, $orderColumns, ['created_at'], date('Y-m-d H:i:s'));
            vnn_set_first_existing_field($orderData, $orderColumns, ['updated_at'], date('Y-m-d H:i:s'));

            $orderId = vnn_dynamic_insert($pdo, 'orders', $orderData);

            /*
            --------------------------------------------------------------
            | 2. INSERT ORDER ITEMS
            --------------------------------------------------------------
            */
            $orderItemColumns = vnn_get_table_columns($pdo, 'order_items');
            if (empty($orderItemColumns)) {
                throw new Exception('Không tìm thấy bảng order_items.');
            }

            foreach ($cartItems as $item) {
                $itemData = [];

                vnn_set_first_existing_field($itemData, $orderItemColumns, ['order_id'], $orderId);
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['product_id', 'item_id'], (int)$item['product_id']);
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['product_name', 'name', 'item_name'], $item['name']);
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['product_image', 'image', 'thumbnail'], $item['image']);
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['size', 'variant'], $item['size']);
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['price', 'unit_price'], (float)$item['price']);
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['quantity', 'qty'], (int)$item['quantity']);
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['subtotal', 'line_total', 'total'], (float)$item['subtotal']);
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['created_at'], date('Y-m-d H:i:s'));
                vnn_set_first_existing_field($itemData, $orderItemColumns, ['updated_at'], date('Y-m-d H:i:s'));

                vnn_dynamic_insert($pdo, 'order_items', $itemData);
            }

            $pdo->commit();

            /*
            --------------------------------------------------------------
            | 3. XÓA GIỎ HÀNG + THÔNG BÁO THÀNH CÔNG
            --------------------------------------------------------------
            */
            $_SESSION['cart'] = [];
            $_SESSION['cart_count'] = 0;

            $_SESSION['checkout_success'] = [
                'order_id'         => $orderId,
                'order_code'       => $orderCode,
                'fullname'         => $fullname,
                'phone'            => $phone,
                'email'            => $email,
                'address'          => $address,
                'note'             => $note,
                'payment_method'   => $paymentMethod,
                'payment_method_label' => $paymentMethod === 'qr' ? 'Chuyển khoản QR cố định' : 'Thanh toán khi nhận hàng (COD)',
                'total_amount'     => $grandTotal,
                'transfer_content' => $transferContent,
                'qr_image'         => $fixedQrImage,
                'bank_owner'       => $bankOwnerName,
                'bank_mask'        => $bankMask,
                'bank_type_label'  => $bankTypeLabel
            ];

            header('Location: checkout.php?success=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errorMessage = 'Đặt hàng thất bại: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.checkout-page{
    padding: 40px 0 60px;
    background: #f8fafc;
    min-height: 70vh;
}
.checkout-wrap{
    max-width: 1180px;
    margin: 0 auto;
    padding: 0 15px;
}
.checkout-title{
    text-align: center;
    font-size: 30px;
    font-weight: 800;
    margin-bottom: 28px;
    color: #14532d;
}
.checkout-grid{
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 24px;
}
.checkout-card{
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 6px 22px rgba(0,0,0,.06);
    padding: 24px;
}
.section-title{
    font-size: 20px;
    font-weight: 800;
    margin-bottom: 18px;
    color: #0f172a;
}
.form-group{
    margin-bottom: 16px;
}
.form-group label{
    display: block;
    font-weight: 700;
    margin-bottom: 8px;
    color: #0f172a;
}
.form-control{
    width: 100%;
    border: 1px solid #d7dee8;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 15px;
    outline: none;
}
.form-control:focus{
    border-color: #f97316;
    box-shadow: 0 0 0 3px rgba(249,115,22,.10);
}
textarea.form-control{
    min-height: 100px;
    resize: vertical;
}
.two-col{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.alert-box{
    padding: 15px 18px;
    border-radius: 12px;
    margin-bottom: 18px;
    font-weight: 600;
}
.alert-error{
    background:#fee2e2;
    border:1px solid #fecaca;
    color:#b91c1c;
}
.alert-success{
    background:#dcfce7;
    border:1px solid #86efac;
    color:#166534;
}
.checkout-items{
    border:1px solid #e5e7eb;
    border-radius:14px;
    overflow:hidden;
}
.checkout-item{
    display:flex;
    gap:12px;
    padding:14px;
    border-bottom:1px solid #eef2f7;
}
.checkout-item:last-child{
    border-bottom:none;
}
.checkout-item img{
    width:74px;
    height:74px;
    object-fit:cover;
    border-radius:10px;
    background:#f1f5f9;
}
.checkout-item-info{
    flex:1;
}
.checkout-item-name{
    font-weight:800;
    color:#111827;
    margin-bottom:4px;
}
.checkout-item-meta{
    color:#64748b;
    font-size:14px;
    margin-bottom:4px;
}
.checkout-item-price{
    color:#ea580c;
    font-weight:800;
}
.summary-box{
    margin-top:16px;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:16px;
    background:#fffdfb;
}
.summary-row{
    display:flex;
    justify-content:space-between;
    gap:10px;
    padding:8px 0;
    font-size:15px;
}
.summary-row.total{
    border-top:1px dashed #d1d5db;
    margin-top:8px;
    padding-top:14px;
    font-size:21px;
    font-weight:800;
    color:#ea580c;
}
.pay-method-wrap{
    display:grid;
    gap:12px;
    margin-top:10px;
}
.pay-method-option{
    border:1px solid #dbe4ee;
    border-radius:14px;
    padding:15px;
    cursor:pointer;
    transition:.2s ease;
}
.pay-method-option.active{
    border-color:#16a34a;
    background:#f0fdf4;
    box-shadow:0 0 0 3px rgba(34,197,94,.08);
}
.pay-method-head{
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:800;
    color:#0f172a;
}
.pay-method-desc{
    margin-top:8px;
    color:#64748b;
    font-size:14px;
    line-height:1.6;
}
.pay-method-option input[type="radio"]{
    transform:scale(1.2);
}
.qr-panel{
    display:none;
    margin-top:16px;
    border:2px dashed #f9a8d4;
    background:#fff7fb;
    border-radius:16px;
    padding:18px;
}
.qr-panel.show{
    display:block;
}
.qr-grid{
    display:grid;
    grid-template-columns: 260px 1fr;
    gap: 18px;
    align-items:start;
}
.qr-image-box{
    text-align:center;
    background:#fff;
    border:1px solid #f5c2dc;
    border-radius:16px;
    padding:14px;
}
.qr-image-box img{
    max-width:100%;
    height:auto;
    display:block;
    margin:0 auto 12px;
    border-radius:12px;
}
.qr-bank-name{
    font-size:28px;
    font-weight:900;
    letter-spacing:1px;
    color:#1f2937;
}
.qr-bank-mask{
    font-size:16px;
    color:#475569;
    margin-top:4px;
    font-weight:700;
}
.transfer-box{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:15px;
}
.transfer-title{
    font-size:17px;
    font-weight:800;
    margin-bottom:12px;
    color:#111827;
}
.transfer-line{
    margin-bottom:10px;
    color:#334155;
    line-height:1.6;
}
.transfer-highlight{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-top:10px;
}
.transfer-code{
    background:#fff7ed;
    border:1px solid #fed7aa;
    border-radius:12px;
    padding:14px;
    font-size:18px;
    font-weight:900;
    color:#c2410c;
    word-break:break-word;
}
.copy-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:#0f172a;
    color:#fff;
    border:none;
    border-radius:10px;
    padding:10px 14px;
    cursor:pointer;
    font-weight:700;
    width:fit-content;
}
.checkout-btn{
    width:100%;
    border:none;
    border-radius:14px;
    padding:15px;
    font-size:18px;
    font-weight:900;
    background:linear-gradient(90deg,#f97316,#ea580c);
    color:#fff;
    cursor:pointer;
    margin-top:18px;
}
.checkout-btn:hover{
    opacity:.96;
}
.empty-cart{
    text-align:center;
    background:#fff;
    padding:40px 20px;
    border-radius:18px;
    box-shadow:0 6px 22px rgba(0,0,0,.06);
}
.success-card{
    background:#fff;
    border-radius:18px;
    box-shadow:0 6px 22px rgba(0,0,0,.06);
    padding:28px;
}
.success-big{
    font-size:28px;
    font-weight:900;
    color:#15803d;
    margin-bottom:14px;
}
.success-info{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-top:14px;
    margin-bottom:20px;
}
.success-info-item{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:12px;
}
.success-label{
    font-size:13px;
    color:#64748b;
    margin-bottom:6px;
}
.success-value{
    font-size:16px;
    font-weight:800;
    color:#0f172a;
    word-break:break-word;
}
.success-actions{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top:20px;
}
.btn-soft{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:12px 16px;
    border-radius:12px;
    text-decoration:none;
    font-weight:800;
    border:none;
    cursor:pointer;
}
.btn-primary-soft{
    background:#166534;
    color:#fff;
}
.btn-gray-soft{
    background:#eef2f7;
    color:#0f172a;
}
.note-mini{
    color:#64748b;
    font-size:14px;
    line-height:1.7;
}
@media (max-width: 992px){
    .checkout-grid{
        grid-template-columns:1fr;
    }
    .qr-grid{
        grid-template-columns:1fr;
    }
}
@media (max-width: 640px){
    .two-col,
    .success-info{
        grid-template-columns:1fr;
    }
}
</style>

<div class="checkout-page">
    <div class="checkout-wrap">
        <h1 class="checkout-title">THANH TOÁN ĐƠN HÀNG</h1>

        <?php if ($successData): ?>
            <div class="success-card">
                <div class="success-big">🎉 Đặt hàng thành công!</div>

                <div class="alert-box alert-success">
                    Đơn hàng của bạn đã được tạo thành công.
                    <?php if (($successData['payment_method'] ?? '') === 'qr'): ?>
                        Giỏ hàng đã được xóa. Vui lòng quét QR bên dưới và chuyển khoản đúng cú pháp để quản lý xác nhận đơn nhanh hơn.
                    <?php else: ?>
                        Giỏ hàng đã được xóa. Nhân viên sẽ sớm liên hệ xác nhận đơn hàng của bạn.
                    <?php endif; ?>
                </div>

                <div class="success-info">
                    <div class="success-info-item">
                        <div class="success-label">Mã đơn hàng</div>
                        <div class="success-value"><?= vnn_h($successData['order_code'] ?? '') ?></div>
                    </div>
                    <div class="success-info-item">
                        <div class="success-label">Phương thức thanh toán</div>
                        <div class="success-value"><?= vnn_h($successData['payment_method_label'] ?? '') ?></div>
                    </div>
                    <div class="success-info-item">
                        <div class="success-label">Khách hàng</div>
                        <div class="success-value"><?= vnn_h($successData['fullname'] ?? '') ?></div>
                    </div>
                    <div class="success-info-item">
                        <div class="success-label">Số điện thoại</div>
                        <div class="success-value"><?= vnn_h($successData['phone'] ?? '') ?></div>
                    </div>
                    <div class="success-info-item">
                        <div class="success-label">Địa chỉ nhận hàng</div>
                        <div class="success-value"><?= vnn_h($successData['address'] ?? '') ?></div>
                    </div>
                    <div class="success-info-item">
                        <div class="success-label">Tổng thanh toán</div>
                        <div class="success-value" style="color:#ea580c;"><?= vnn_money($successData['total_amount'] ?? 0) ?></div>
                    </div>
                </div>

                <?php if (($successData['payment_method'] ?? '') === 'qr'): ?>
                    <div class="qr-panel show" style="display:block;">
                        <div class="qr-grid">
                            <div class="qr-image-box">
                                <img src="<?= vnn_h($successData['qr_image'] ?? $fixedQrImage) ?>" alt="QR thanh toán">
                                <div class="qr-bank-name"><?= vnn_h($successData['bank_owner'] ?? $bankOwnerName) ?></div>
                                <div class="qr-bank-mask"><?= vnn_h($successData['bank_mask'] ?? $bankMask) ?></div>
                            </div>

                            <div class="transfer-box">
                                <div class="transfer-title">Thông tin chuyển khoản</div>

                                <div class="transfer-line">
                                    <strong>Hình thức:</strong>
                                    <?= vnn_h($successData['bank_type_label'] ?? $bankTypeLabel) ?>
                                </div>

                                <div class="transfer-line">
                                    <strong>Số tiền cần chuyển:</strong>
                                    <span style="color:#dc2626;font-weight:900;"><?= vnn_money($successData['total_amount'] ?? 0) ?></span>
                                </div>

                                <div class="transfer-line">
                                    <strong>Nội dung chuyển khoản bắt buộc:</strong>
                                </div>

                                <div class="transfer-highlight">
                                    <div class="transfer-code" id="finalTransferContent"><?= vnn_h($successData['transfer_content'] ?? '') ?></div>
                                    <button type="button" class="copy-btn" onclick="copyTransferContent('finalTransferContent')">
                                        📋 Sao chép nội dung chuyển khoản
                                    </button>
                                </div>

                                <div class="note-mini" style="margin-top:14px;">
                                    <strong>Lưu ý:</strong><br>
                                    - Bạn phải chuyển khoản đúng <strong>nội dung</strong> ở trên.<br>
                                    - Đây là QR cố định nên hệ thống <strong>không tự động xác nhận đã thanh toán</strong> như cổng thanh toán online.<br>
                                    - Sau khi bạn chuyển khoản, quản lý sẽ kiểm tra và xác nhận thủ công trong hệ thống.
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="success-actions">
                    <a href="/shop/" class="btn-soft btn-primary-soft">🛍️ Tiếp tục mua hàng</a>
                    <a href="/index.php" class="btn-soft btn-gray-soft">🏠 Về trang chủ</a>
                </div>
            </div>

        <?php elseif (empty($cartItems)): ?>
            <div class="empty-cart">
                <h2 style="font-size:26px;margin-bottom:14px;">Giỏ hàng đang trống</h2>
                <p style="color:#64748b;margin-bottom:20px;">Bạn chưa có sản phẩm nào để thanh toán.</p>
                <a href="/shop/" class="btn-soft btn-primary-soft">🛍️ Mua hàng ngay</a>
            </div>

        <?php else: ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="alert-box alert-error"><?= vnn_h($errorMessage) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="place_order" value="1">
                <input type="hidden" name="order_code" id="order_code_hidden" value="<?= vnn_h($previewOrderCode) ?>">

                <div class="checkout-grid">
                    <!-- BÊN TRÁI -->
                    <div class="checkout-card">
                        <div class="section-title">🧾 Thông tin giao hàng</div>

                        <div class="form-group">
                            <label>Họ và tên *</label>
                            <input type="text" name="fullname" id="fullname_input" class="form-control" required value="<?= vnn_h($old['fullname']) ?>">
                        </div>

                        <div class="two-col">
                            <div class="form-group">
                                <label>Số điện thoại nhận hàng *</label>
                                <input type="text" name="phone" id="phone_input" class="form-control" required value="<?= vnn_h($old['phone']) ?>">
                            </div>

                            <div class="form-group">
                                <label>Địa chỉ Email</label>
                                <input type="email" name="email" class="form-control" value="<?= vnn_h($old['email']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Địa chỉ giao nhận cụ thể *</label>
                            <input type="text" name="address" class="form-control" required value="<?= vnn_h($old['address']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Ghi chú đơn hàng</label>
                            <textarea name="note" class="form-control"><?= vnn_h($old['note']) ?></textarea>
                        </div>

                        <div class="section-title" style="margin-top:24px;">💳 Phương thức thanh toán</div>

                        <div class="pay-method-wrap">
                            <label class="pay-method-option <?= $old['payment_method'] === 'cod' ? 'active' : '' ?>">
                                <div class="pay-method-head">
                                    <input type="radio" name="payment_method" value="cod" <?= $old['payment_method'] === 'cod' ? 'checked' : '' ?>>
                                    <span>Thanh toán khi nhận hàng (COD)</span>
                                </div>
                                <div class="pay-method-desc">
                                    Bạn thanh toán trực tiếp cho nhân viên giao hàng khi nhận được sản phẩm.
                                </div>
                            </label>

                            <label class="pay-method-option <?= $old['payment_method'] === 'qr' ? 'active' : '' ?>">
                                <div class="pay-method-head">
                                    <input type="radio" name="payment_method" value="qr" <?= $old['payment_method'] === 'qr' ? 'checked' : '' ?>>
                                    <span>Chuyển khoản QR cố định</span>
                                </div>
                                <div class="pay-method-desc">
                                    Hệ thống sẽ tạo đơn hàng, xóa giỏ hàng và hiển thị QR để bạn chuyển khoản đúng cú pháp.
                                </div>
                            </label>
                        </div>

                        <div id="qrPanel" class="qr-panel <?= $old['payment_method'] === 'qr' ? 'show' : '' ?>">
                            <div class="qr-grid">
                                <div class="qr-image-box">
                                    <img src="<?= vnn_h($fixedQrImage) ?>" alt="QR thanh toán cố định">
                                    <div class="qr-bank-name"><?= vnn_h($bankOwnerName) ?></div>
                                    <div class="qr-bank-mask"><?= vnn_h($bankMask) ?></div>
                                </div>

                                <div class="transfer-box">
                                    <div class="transfer-title">Nội dung chuyển khoản bắt buộc</div>

                                    <div class="transfer-line">
                                        <strong>Áp dụng cho hàng hóa:</strong><br>
                                        Cú pháp: <strong>SHOP MãĐơn TênKhách 4SốCuốiSĐT</strong>
                                    </div>

                                    <div class="transfer-line">
                                        <strong>Mã đơn dự kiến:</strong>
                                        <span id="previewOrderCodeText"><?= vnn_h($previewOrderCode) ?></span>
                                    </div>

                                    <div class="transfer-line">
                                        <strong>Nội dung bạn cần nhập đúng:</strong>
                                    </div>

                                    <div class="transfer-highlight">
                                        <div class="transfer-code" id="transferPreview">SHOP <?= vnn_h($previewOrderCode) ?> KHACH 0000</div>
                                        <button type="button" class="copy-btn" onclick="copyTransferContent('transferPreview')">
                                            📋 Sao chép nội dung
                                        </button>
                                    </div>

                                    <div class="note-mini" style="margin-top:14px;">
                                        Ví dụ tour sau này bạn có thể làm theo dạng:
                                        <strong>TOUR MATOUR TENTOUR TENKHACH 4SODT</strong><br>
                                        Còn với hàng hóa, file này mình đã làm sẵn cú pháp:
                                        <strong>SHOP MãĐơn TênKhách 4SốCuốiSĐT</strong>.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BÊN PHẢI -->
                    <div class="checkout-card">
                        <div class="section-title">📦 Đơn hàng của bạn</div>

                        <div class="checkout-items">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="checkout-item">
                                    <img src="<?= vnn_h($item['image'] !== '' ? $item['image'] : '/assets/images/no-image.png') ?>" alt="<?= vnn_h($item['name']) ?>">
                                    <div class="checkout-item-info">
                                        <div class="checkout-item-name"><?= vnn_h($item['name']) ?></div>
                                        <div class="checkout-item-meta">
                                            Số lượng: <?= (int)$item['quantity'] ?>
                                            <?php if ($item['size'] !== ''): ?>
                                                | Size: <?= vnn_h($item['size']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="checkout-item-price">
                                            <?= vnn_money($item['price']) ?> x <?= (int)$item['quantity'] ?>
                                            = <?= vnn_money($item['subtotal']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="summary-box">
                            <div class="summary-row">
                                <span>Tạm tính</span>
                                <strong><?= vnn_money($subtotal) ?></strong>
                            </div>

                            <div class="summary-row">
                                <span>Phí vận chuyển</span>
                                <strong><?= $shippingFee > 0 ? vnn_money($shippingFee) : 'Miễn phí' ?></strong>
                            </div>

                            <div class="summary-row total">
                                <span>Tổng cộng</span>
                                <span><?= vnn_money($grandTotal) ?></span>
                            </div>

                            <button type="submit" class="checkout-btn">
                                XÁC NHẬN ĐẶT HÀNG
                            </button>

                            <div class="note-mini" style="margin-top:14px;">
                                - Nếu chọn <strong>COD</strong>: đơn sẽ ở trạng thái chờ xác nhận.<br>
                                - Nếu chọn <strong>QR</strong>: sau khi đặt hàng thành công, hệ thống sẽ hiển thị QR + nội dung chuyển khoản để bạn thanh toán đúng cú pháp.
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function onlyDigits(value) {
    return (value || '').replace(/\D+/g, '');
}

function stripVietnamese(str) {
    str = str || '';
    const from =
        "ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴàáạảãâầấậẩẫăằắặẳẵÈÉẸẺẼÊỀẾỆỂỄèéẹẻẽêềếệểễÌÍỊỈĨìíịỉĩ" +
        "ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠòóọỏõôồốộổỗơờớợởỡÙÚỤỦŨƯỪỨỰỬỮùúụủũưừứựửữỲÝỴỶỸỳýỵỷỹĐđ";
    const to =
        "AAAAAAAAAAAAAAAAAaaaaaaaaaaaaaaaaaEEEEEEEEEEEeeeeeeeeeeeIIIIIiiiii" +
        "OOOOOOOOOOOOOOOOOOooooooooooooooooooUUUUUUUUUUUuuuuuuuuuuuYYYYYyyyyyDd";
    for (let i = 0; i < from.length; i++) {
        str = str.replaceAll(from[i], to[i]);
    }
    return str;
}

function slugTransferName(name) {
    name = stripVietnamese(name).toUpperCase();
    name = name.replace(/[^A-Z0-9 ]/g, ' ');
    let parts = name.trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'KHACH';
    parts = parts.slice(-2);
    return parts.join('').substring(0, 12) || 'KHACH';
}

function last4Phone(phone) {
    let d = onlyDigits(phone);
    if (!d) return '0000';
    return d.slice(-4).padStart(4, '0');
}

function buildTransferContent(orderCode, fullName, phone) {
    return 'SHOP ' + orderCode + ' ' + slugTransferName(fullName) + ' ' + last4Phone(phone);
}

function updatePaymentUI() {
    const radios = document.querySelectorAll('input[name="payment_method"]');
    const qrPanel = document.getElementById('qrPanel');
    const fullName = document.getElementById('fullname_input')?.value || '';
    const phone = document.getElementById('phone_input')?.value || '';
    const orderCode = document.getElementById('order_code_hidden')?.value || '';
    const transferPreview = document.getElementById('transferPreview');

    let selected = 'cod';
    radios.forEach(r => {
        if (r.checked) selected = r.value;
    });

    document.querySelectorAll('.pay-method-option').forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            option.classList.add('active');
        } else {
            option.classList.remove('active');
        }
    });

    if (qrPanel) {
        if (selected === 'qr') {
            qrPanel.classList.add('show');
        } else {
            qrPanel.classList.remove('show');
        }
    }

    if (transferPreview) {
        transferPreview.textContent = buildTransferContent(orderCode, fullName, phone);
    }
}

function copyTransferContent(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;

    const text = el.innerText.trim();

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Đã sao chép nội dung chuyển khoản!');
        }).catch(() => {
            alert('Không thể sao chép tự động, bạn hãy copy thủ công.');
        });
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-99999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            document.execCommand('copy');
            alert('Đã sao chép nội dung chuyển khoản!');
        } catch (e) {
            alert('Không thể sao chép tự động, bạn hãy copy thủ công.');
        }
        document.body.removeChild(textarea);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updatePaymentUI();

    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', updatePaymentUI);
    });

    const fullNameInput = document.getElementById('fullname_input');
    const phoneInput = document.getElementById('phone_input');

    if (fullNameInput) {
        fullNameInput.addEventListener('input', updatePaymentUI);
    }
    if (phoneInput) {
        phoneInput.addEventListener('input', updatePaymentUI);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
