<?php
// VNN Travel - Lich su mua trang bi
// shop/my-orders.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$page_title = 'Lịch Sử Mua Trang Bị - VNN Travel';
$active_menu = 'shop';

function moh($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$base = rtrim(BASE_URL, '/') . '/';
$userId = (int) ($_SESSION['user_id'] ?? 0);

// Lay danh sach don hang cua tai khoan
$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE user_id = ?
    ORDER BY id DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lay chi tiet san pham trong cac don hang
$orderItems = [];
$detailError = '';

if ($orders) {
    try {
        $orderIds = array_map(
            'intval',
            array_column($orders, 'id')
        );

        $placeholders = implode(
            ',',
            array_fill(0, count($orderIds), '?')
        );

        $stmt = $pdo->prepare("
            SELECT
                od.order_id,
                od.product_id,
                od.quantity,
                od.price,
                od.total,
                p.name AS product_name,
                p.image AS product_image
            FROM order_details od
            LEFT JOIN products p
                ON p.id = od.product_id
            WHERE od.order_id IN ($placeholders)
            ORDER BY od.order_id DESC, od.product_id
        ");

        $stmt->execute($orderIds);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $orderId = (int) $item['order_id'];

            if (!isset($orderItems[$orderId])) {
                $orderItems[$orderId] = [];
            }

            $orderItems[$orderId][] = $item;
        }

    } catch (Throwable $e) {
        error_log(
            'My orders details: ' . $e->getMessage()
        );

        $detailError =
            'Không thể tải chi tiết sản phẩm. ' .
            'Vui lòng thử lại sau.';
    }
}

function moStatusStyle($status)
{
    switch ((string) $status) {
        case 'Đã xác nhận':
            return 'background:#dbeafe;color:#1e40af';

        case 'Đang giao':
            return 'background:#ffedd5;color:#c2410c';

        case 'Đã giao':
            return 'background:#dcfce7;color:#166534';

        case 'Đã hủy':
            return 'background:#fee2e2;color:#991b1b';

        default:
            return 'background:#fef3c7;color:#92400e';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.mo-page {
    padding-top: 35px;
    padding-bottom: 65px;
}
.mo-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 26px;
}
.mo-header h1 {
    color: #0f172a;
    font-size: 27px;
    margin: 10px 0;
}
.mo-subtitle {
    color: #ea580c;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 1px;
}
.mo-back {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 12px 18px;
    border-radius: 10px;
    color: white !important;
    background: #166534;
    font-size: 14px;
    font-weight: 800;
    text-decoration: none;
}
.mo-back:hover {
    background: #15803d;
}
.mo-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 18px rgba(15,23,42,.05);
}
.mo-scroll {
    overflow-x: auto;
    width: 100%;
}
.mo-table {
    min-width: 1050px;
    width: 100%;
    border-collapse: collapse;
}
.mo-table th {
    padding: 17px 13px;
    background: #f8fafc;
    color: #0f172a;
    text-align: left;
    font-size: 13px;
}
.mo-table td {
    padding: 16px 13px;
    border-top: 1px solid #e2e8f0;
    vertical-align: middle;
    font-size: 13px;
    color: #334155;
}
.mo-table tbody tr:hover {
    background: #fafcff;
}
.mo-order-code {
    font-weight: 900;
    color: #1e3a5f;
}
.mo-products {
    min-width: 240px;
}
.mo-product {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 0;
    border-bottom: 1px dashed #e2e8f0;
}
.mo-product:last-child {
    border-bottom: none;
}
.mo-product-image {
    width: 49px;
    height: 49px;
    border-radius: 8px;
    object-fit: cover;
    background: #f1f5f9;
    flex-shrink: 0;
}
.mo-product-name {
    font-weight: 800;
    color: #0f172a;
    line-height: 1.5;
}
.mo-product-meta {
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
}
.mo-price {
    color: #ea580c !important;
    font-weight: 900;
    white-space: nowrap;
}
.mo-badge {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-weight: 800;
    font-size: 12px;
    white-space: nowrap;
}
.mo-notice {
    background: #dcfce7;
    color: #166534;
    border-radius: 10px;
    padding: 17px 20px;
    margin-bottom: 20px;
    line-height: 1.8;
}
.mo-warning {
    background: #fef3c7;
    color: #92400e;
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.mo-empty {
    text-align: center;
    padding: 45px 20px;
    color: #64748b;
}
.mo-empty i {
    font-size: 40px;
    color: #94a3b8;
    display: block;
    margin-bottom: 14px;
}
@media (max-width: 700px) {
    .mo-header h1 {
        font-size: 22px;
    }
}
</style>

<div class="container section mo-page">

    <div class="mo-header">

        <div>
            <span class="mo-subtitle">
                TÀI KHOẢN KHÁCH HÀNG
            </span>

            <h1>
                <i class="fa-solid fa-bag-shopping"></i>
                Lịch Sử Mua Trang Bị
            </h1>
        </div>

        <a
            href="<?= moh($base) ?>shop/shop.php"
            class="mo-back"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Tiếp tục mua hàng
        </a>

    </div>

    <!-- THONG BAO SAU KHI DAT HANG -->

    <?php if (isset($_GET['success'])): ?>

        <div class="mo-notice" role="status">
            <i class="fa-solid fa-circle-check"></i>

            <strong>Đặt hàng thành công!</strong>

            <?php if (!empty($_GET['code'])): ?>
                <div>
                    Mã đơn hàng:
                    <strong>
                        <?= moh($_GET['code']) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <div>
                Đơn hàng sẽ sớm được giao đến tay bạn.
            </div>
        </div>

    <?php endif; ?>

    <?php if ($detailError): ?>

        <div class="mo-warning" role="alert">
            <?= moh($detailError) ?>
        </div>

    <?php endif; ?>

    <!-- LICH SU DON HANG -->

    <div class="mo-card">

        <div class="mo-scroll">

            <table class="mo-table">

                <thead>
                    <tr>
                        <th>Mã đơn hàng</th>
                        <th>Sản phẩm đã mua</th>
                        <th>Người nhận</th>
                        <th>Địa chỉ nhận</th>
                        <th>Tổng tiền</th>
                        <th>Ngày đặt</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($orders as $order): ?>

                        <?php
                        $orderId = (int) $order['id'];
                        $items = $orderItems[$orderId] ?? [];
                        ?>

                        <tr>

                            <td>
                                <span class="mo-order-code">
                                    <?= moh($order['order_code']) ?>
                                </span>
                            </td>

                            <td class="mo-products">

                                <?php if ($items): ?>

                                    <?php foreach ($items as $item): ?>

                                        <div class="mo-product">

                                            <?php if (!empty($item['product_image'])): ?>

                                                <img
                                                    class="mo-product-image"
                                                    src="<?= moh($item['product_image']) ?>"
                                                    alt=""
                                                    loading="lazy"
                                                >

                                            <?php endif; ?>

                                            <div>

                                                <div class="mo-product-name">
                                                    <?= moh(
                                                        $item['product_name']
                                                        ?: 'Sản phẩm đã ngừng kinh doanh'
                                                    ) ?>
                                                </div>

                                                <div class="mo-product-meta">
                                                    SL:
                                                    <strong>
                                                        <?= (int) $item['quantity'] ?>
                                                    </strong>

                                                    · Giá:
                                                    <?= format_price($item['price']) ?>
                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <span style="color:#94a3b8">
                                        Chưa có chi tiết sản phẩm
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= moh($order['fullname']) ?>
                            </td>

                            <td>
                                <?= moh($order['address']) ?>
                            </td>

                            <td class="mo-price">
                                <?= format_price($order['total_amount']) ?>
                            </td>

                            <td style="white-space:nowrap">
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($order['created_at'])
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="mo-badge"
                                    style="<?= moStatusStyle($order['status']) ?>"
                                >
                                    <?= moh($order['status']) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$orders): ?>

                        <tr>
                            <td colspan="7">

                                <div class="mo-empty">

                                    <i class="fa-solid fa-box-open"></i>

                                    <strong>
                                        Bạn chưa có đơn hàng nào.
                                    </strong>

                                    <p>
                                        Hãy khám phá các trang bị
                                        trekking tại cửa hàng.
                                    </p>

                                    <a
                                        href="<?= moh($base) ?>shop/shop.php"
                                        class="mo-back"
                                    >
                                        Khám phá cửa hàng
                                    </a>

                                </div>

                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
