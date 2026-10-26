<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$page_title = 'Đơn Mua Thiết Bị - VNN Travel';

if (!function_exists('moH')) {
    function moH($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('moMoney')) {
    function moMoney($value): string {
        return number_format(
            (float)$value,
            0,
            ',',
            '.'
        ) . ' đ';
    }
}

if (!function_exists('moColumns')) {
    function moColumns(PDO $pdo, string $table): array {
        if (!in_array(
            $table,
            ['orders', 'order_details', 'products'],
            true
        )) {
            return [];
        }

        $stmt = $pdo->query(
            "SHOW COLUMNS FROM `$table`"
        );

        return array_column(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            'Field'
        );
    }
}

if (!function_exists('moFindColumn')) {
    function moFindColumn(
        array $columns,
        array $candidates
    ): ?string {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('moImageUrl')) {
    function moImageUrl($image): string {
        $image = trim((string)$image);

        if ($image === '') {
            return '';
        }

        // Neu anh duoc luu duoi dang JSON array,
        // lay anh dau tien.
        if ($image[0] === '[') {
            $decoded = json_decode($image, true);

            if (is_array($decoded)) {
                $image = trim(
                    (string)($decoded[0] ?? '')
                );
            }
        }

        if ($image === '') {
            return '';
        }

        // URL anh ben ngoai
        if (preg_match(
            '~^https?://~i',
            $image
        )) {
            return $image;
        }

        // Khong cho phep javascript: hay data:
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $image)) {
            return '';
        }

        $image = str_replace('\\', '/', $image);
        $image = ltrim($image, '/');

        if (str_contains($image, '..')) {
            return '';
        }

        // Anh luu trong thu muc cua website
        return '/' . $image;
    }
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$orders = [];
$orderItems = [];
$error = '';

if (empty($_SESSION['shop_cancel_csrf'])) {
    $_SESSION['shop_cancel_csrf'] =
        bin2hex(random_bytes(32));
}

$csrf = $_SESSION['shop_cancel_csrf'];

$notice = $_SESSION['shop_order_notice'] ?? null;
unset($_SESSION['shop_order_notice']);

// ========================================
// LAY DON HANG
// ========================================

if ($userId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM orders
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
        ");

        $stmt->execute([$userId]);

        $orders = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        // ====================================
        // LAY CHI TIET SAN PHAM
        // ====================================

        if ($orders) {
            $detailCols = moColumns(
                $pdo,
                'order_details'
            );

            $productCols = moColumns(
                $pdo,
                'products'
            );

            // Ten san pham
            $productNameCol = moFindColumn(
                $productCols,
                ['name', 'product_name', 'title']
            );

            $detailNameCol = moFindColumn(
                $detailCols,
                ['product_name', 'name', 'title']
            );

            // Anh san pham
            $productImageCol = moFindColumn(
                $productCols,
                [
                    'image',
                    'image_url',
                    'product_image',
                    'thumbnail',
                    'photo',
                    'images'
                ]
            );

            $detailImageCol = moFindColumn(
                $detailCols,
                [
                    'product_image',
                    'image',
                    'image_url',
                    'thumbnail'
                ]
            );

            // So luong da mua
            $quantityCol = moFindColumn(
                $detailCols,
                ['quantity', 'qty']
            );

            // Gia tai thoi diem dat hang:
            // Uu tien gia trong chi tiet don.
            $unitPriceCol = moFindColumn(
                $detailCols,
                [
                    'unit_price',
                    'price',
                    'product_price',
                    'sale_price'
                ]
            );

            // Thanh tien da luu cua moi dong
            $lineTotalCol = moFindColumn(
                $detailCols,
                [
                    'subtotal',
                    'total_price',
                    'line_total',
                    'total_amount'
                ]
            );

            $joinProduct = '';

            if (
                in_array('product_id', $detailCols, true) &&
                $productCols
            ) {
                $joinProduct = "
                    LEFT JOIN products p
                        ON p.id = d.product_id
                ";
            }

            $hasProductJoin = $joinProduct !== '';

            $nameParts = [];

            if ($detailNameCol) {
                $nameParts[] =
                    "NULLIF(d.`$detailNameCol`, '')";
            }

            if ($hasProductJoin && $productNameCol) {
                $nameParts[] =
                    "NULLIF(p.`$productNameCol`, '')";
            }

            $nameParts[] = "'Sản phẩm'";

            $nameExpr = 'COALESCE(' .
                implode(', ', $nameParts) .
            ')';

            $imageParts = [];

            if ($detailImageCol) {
                $imageParts[] =
                    "NULLIF(d.`$detailImageCol`, '')";
            }

            if ($hasProductJoin && $productImageCol) {
                $imageParts[] =
                    "NULLIF(p.`$productImageCol`, '')";
            }

            $imageParts[] = "''";

            $imageExpr = 'COALESCE(' .
                implode(', ', $imageParts) .
            ')';

            $quantityExpr = $quantityCol
                ? "d.`$quantityCol`"
                : "NULL";

            $unitPriceExpr = $unitPriceCol
                ? "d.`$unitPriceCol`"
                : "NULL";

            $lineTotalExpr = $lineTotalCol
                ? "d.`$lineTotalCol`"
                : "NULL";

            $orderIds = array_map(
                static fn($order) => (int)$order['id'],
                $orders
            );

            $placeholders = implode(
                ',',
                array_fill(0, count($orderIds), '?')
            );

            $sql = "
                SELECT
                    d.order_id,
                    $nameExpr AS item_name,
                    $imageExpr AS item_image,
                    $quantityExpr AS item_quantity,
                    $unitPriceExpr AS item_unit_price,
                    $lineTotalExpr AS item_line_total
                FROM order_details d
                $joinProduct
                WHERE d.order_id IN ($placeholders)
                ORDER BY d.order_id, d.id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($orderIds);

            $items = $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

            foreach ($items as $item) {
                $orderId = (int)$item['order_id'];

                $quantity = $item['item_quantity'] === null
                    ? null
                    : (int)$item['item_quantity'];

                $unitPrice = $item['item_unit_price'] === null
                    ? null
                    : (float)$item['item_unit_price'];

                $lineTotal = $item['item_line_total'] === null
                    ? null
                    : (float)$item['item_line_total'];

                // Neu khong co cot don gia nhung co
                // thanh tien va so luong thi suy ra don gia.
                if (
                    $unitPrice === null &&
                    $lineTotal !== null &&
                    $quantity !== null &&
                    $quantity > 0
                ) {
                    $unitPrice = $lineTotal / $quantity;
                }

                // Neu chi co don gia va so luong,
                // tinh thanh tien tung san pham.
                if (
                    $lineTotal === null &&
                    $unitPrice !== null &&
                    $quantity !== null
                ) {
                    $lineTotal = $unitPrice * $quantity;
                }

                $orderItems[$orderId][] = [
                    'name' => $item['item_name'],
                    'image' => moImageUrl(
                        $item['item_image']
                    ),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal
                ];
            }
        }

    } catch (Throwable $e) {
        error_log(
            'Shop my-orders: ' . $e->getMessage()
        );

        $error = 'Không thể tải danh sách đơn hàng.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ========================================
   TRANG DON MUA THIET BI
   ======================================== */

.my-orders-page {
    min-height: 65vh;
    padding: 34px 16px 60px;
    background: #f3f6fa;
}

.my-orders-container {
    max-width: 1240px;
    margin: auto;
}

.my-orders-title {
    margin: 0 0 24px;
    color: #102d50;
    font-size: 25px;
    font-weight: 800;
}

/* ========================================
   CARD DON HANG
   ======================================== */

.my-order-card {
    margin-bottom: 20px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: white;
    box-shadow: 0 5px 22px rgba(15,23,42,.04);
}

.my-order-header {
    padding: 17px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.my-order-code {
    color: #102d50;
    font-size: 14px;
    font-weight: 800;
}

.my-order-date {
    margin-top: 6px;
    color: #64748b;
    font-size: 12px;
}

.my-order-status {
    display: inline-flex;
    align-items: center;
    padding: 8px 13px;
    border-radius: 25px;

    background: #dbeafe;
    color: #1d4ed8;

    font-size: 12px;
    font-weight: 800;
}

.my-order-status.pending {
    background: #fff7ed;
    color: #c2410c;
}

.my-order-status.success {
    background: #dcfce7;
    color: #166534;
}

.my-order-status.cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

/* ========================================
   TIEU DE CAC COT
   ======================================== */

.my-order-columns {
    display: grid;
    grid-template-columns:
        minmax(0, 2.5fr)
        minmax(110px, .9fr)
        minmax(75px, .6fr)
        minmax(120px, 1fr);

    gap: 15px;
    align-items: center;

    padding: 13px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;

    color: #64748b;
    font-size: 12px;
    font-weight: 800;
}

.my-order-columns > div:not(:first-child) {
    text-align: right;
}

/* ========================================
   DONG SAN PHAM
   ======================================== */

.my-order-product {
    display: grid;
    grid-template-columns:
        minmax(0, 2.5fr)
        minmax(110px, .9fr)
        minmax(75px, .6fr)
        minmax(120px, 1fr);

    gap: 15px;
    align-items: center;

    padding: 17px 20px;
    border-bottom: 1px solid #edf2f7;
}

.my-order-product:last-child {
    border-bottom: 0;
}

.my-order-product-info {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
}

.my-order-product-image {
    flex: 0 0 85px;
    width: 85px;
    height: 85px;

    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
    background: #f8fafc;

    display: flex;
    align-items: center;
    justify-content: center;
}

.my-order-product-image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.my-order-product-image i {
    color: #94a3b8;
    font-size: 26px;
}

.my-order-product-name {
    min-width: 0;
    color: #13355b;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.6;
}

.my-order-unit-price {
    color: #475569;
    font-size: 13px;
    white-space: nowrap;
    text-align: right;
}

.my-order-quantity {
    color: #166534;
    font-size: 13px;
    font-weight: 800;
    text-align: right;
}

.my-order-line-total {
    color: #ea580c;
    font-size: 14px;
    font-weight: 800;
    text-align: right;
    white-space: nowrap;
}

/* ========================================
   TONG TIEN
   ======================================== */

.my-order-footer {
    padding: 17px 20px;
    border-top: 1px solid #e2e8f0;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.my-order-total {
    color: #64748b;
    font-size: 13px;
}

.my-order-total strong {
    margin-left: 6px;
    color: #ea580c;
    font-size: 19px;
    font-weight: 900;
}

/* ========================================
   NUT HUY DON
   ======================================== */

.my-order-cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;

    padding: 11px 17px;
    border: 1px solid #dc2626;
    border-radius: 9px;
    background: white;

    color: #dc2626;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.my-order-cancel:hover {
    background: #fef2f2;
}

.my-orders-empty {
    padding: 35px 20px;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    background: white;
    color: #64748b;
    text-align: center;
    font-size: 14px;
}

/* ========================================
   POPUP
   ======================================== */

.my-order-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;

    display: flex;
    justify-content: center;
    align-items: center;

    padding: 20px;
    background: rgba(15,23,42,.5);
}

.my-order-overlay[hidden] {
    display: none !important;
}

.my-order-modal {
    width: 100%;
    max-width: 420px;
    padding: 28px;

    border-radius: 18px;
    background: white;

    text-align: center;
    box-shadow: 0 20px 65px rgba(0,0,0,.2);
}

.my-order-modal-icon {
    color: #dc2626;
    font-size: 44px;
}

.my-order-modal-icon.success {
    color: #16a34a;
}

.my-order-modal h2 {
    margin: 14px 0 10px;
    color: #0f172a;
    font-size: 20px;
}

.my-order-modal p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

.my-order-modal-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 22px;
}

.my-order-modal-actions button {
    padding: 11px 18px;
    border: 0;
    border-radius: 9px;

    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.my-modal-back {
    background: #e2e8f0;
    color: #334155;
}

.my-modal-confirm {
    background: #dc2626;
    color: white;
}

.my-modal-ok {
    background: #166534;
    color: white;
}

/* ========================================
   RESPONSIVE
   ======================================== */

@media (max-width: 850px) {
    .my-order-columns {
        display: none;
    }

    .my-order-product {
        grid-template-columns:
            minmax(0, 1fr)
            minmax(90px, .5fr);
        gap: 10px;
    }

    .my-order-product-info {
        grid-column: 1 / -1;
    }

    .my-order-unit-price {
        text-align: left;
    }

    .my-order-quantity {
        text-align: right;
    }

    .my-order-line-total {
        grid-column: 1 / -1;
        text-align: right;
    }

    .my-order-line-total::before {
        content: "Thành tiền: ";
        color: #64748b;
        font-weight: 500;
    }
}

@media (max-width: 550px) {
    .my-orders-page {
        padding: 19px 11px 40px;
    }

    .my-orders-title {
        font-size: 20px;
    }

    .my-order-product {
        padding: 13px;
    }

    .my-order-product-image {
        flex-basis: 72px;
        width: 72px;
        height: 72px;
    }

    .my-order-header,
    .my-order-footer {
        padding: 14px;
    }

    .my-order-total strong {
        font-size: 17px;
    }
}
</style>

<main class="my-orders-page">

    <div class="my-orders-container">

        <h1 class="my-orders-title">
            <i class="fa-solid fa-box"></i>
            ĐƠN MUA THIẾT BỊ CỦA TÔI
        </h1>

        <?php if ($userId <= 0): ?>

            <div class="my-orders-empty">
                Vui lòng
                <a href="/auth/login.php">
                    đăng nhập
                </a>
                để xem đơn hàng.
            </div>

        <?php elseif ($error !== ''): ?>

            <div class="my-orders-empty">
                <?= moH($error) ?>
            </div>

        <?php elseif (!$orders): ?>

            <div class="my-orders-empty">
                Bạn chưa có đơn mua thiết bị nào.
            </div>

        <?php else: ?>

            <?php foreach ($orders as $order): ?>

                <?php
                $orderId = (int)$order['id'];

                $code = (string)(
                    $order['order_code'] ??
                    ('Đơn #' . $orderId)
                );

                $status = trim(
                    (string)($order['status'] ?? '')
                );

                $canCancel =
                    $status === 'Chờ xác nhận';

                $statusClass = match ($status) {
                    'Chờ xác nhận' => 'pending',
                    'Đã giao',
                    'Đã hoàn thành' => 'success',
                    'Đã hủy' => 'cancelled',
                    default => ''
                };

                $amount =
                    $order['total_amount'] ??
                    $order['total_price'] ??
                    0;

                $items = $orderItems[$orderId] ?? [];
                ?>

                <article class="my-order-card">

                    <!-- MA DON VA TRANG THAI -->

                    <div class="my-order-header">

                        <div>

                            <div class="my-order-code">
                                Mã đơn:
                                <?= moH($code) ?>
                            </div>

                            <div class="my-order-date">
                                Ngày đặt:
                                <?= !empty($order['created_at'])
                                    ? moH(date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $order['created_at']
                                        )
                                    ))
                                    : '—' ?>
                            </div>

                        </div>

                        <span
                            class="my-order-status <?= moH($statusClass) ?>"
                        >
                            <?= moH($status) ?>
                        </span>

                    </div>

                    <!-- TIEU DE COT -->

                    <div class="my-order-columns">

                        <div>Sản phẩm</div>
                        <div>Đơn giá</div>
                        <div>Số lượng</div>
                        <div>Thành tiền</div>

                    </div>

                    <!-- DANH SACH SAN PHAM -->

                    <?php if ($items): ?>

                        <?php foreach ($items as $item): ?>

                            <div class="my-order-product">

                                <!-- HINH ANH VA TEN -->

                                <div class="my-order-product-info">

                                    <div class="my-order-product-image">

                                        <?php if ($item['image'] !== ''): ?>

                                            <img
                                                src="<?= moH($item['image']) ?>"
                                                alt="<?= moH($item['name']) ?>"
                                                loading="lazy"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='block';"
                                            >

                                            <i
                                                class="fa-solid fa-image"
                                                style="display:none"
                                            ></i>

                                        <?php else: ?>

                                            <i class="fa-solid fa-image"></i>

                                        <?php endif; ?>

                                    </div>

                                    <div class="my-order-product-name">
                                        <?= moH($item['name']) ?>
                                    </div>

                                </div>

                                <!-- DON GIA -->

                                <div class="my-order-unit-price">

                                    <?= $item['unit_price'] === null
                                        ? '—'
                                        : moH(
                                            moMoney(
                                                $item['unit_price']
                                            )
                                        ) ?>

                                </div>

                                <!-- SO LUONG -->

                                <div class="my-order-quantity">

                                    <?= $item['quantity'] === null
                                        ? '—'
                                        : (int)$item['quantity'] ?>

                                </div>

                                <!-- THANH TIEN -->

                                <div class="my-order-line-total">

                                    <?= $item['line_total'] === null
                                        ? '—'
                                        : moH(
                                            moMoney(
                                                $item['line_total']
                                            )
                                        ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="my-orders-empty">
                            Chưa có thông tin chi tiết sản phẩm.
                        </div>

                    <?php endif; ?>

                    <!-- TONG TIEN VA NUT HUY -->

                    <div class="my-order-footer">

                        <div class="my-order-total">

                            Tổng tiền:

                            <strong>
                                <?= moH(moMoney($amount)) ?>
                            </strong>

                        </div>

                        <?php if ($canCancel): ?>

                            <button
                                type="button"
                                class="my-order-cancel"
                                data-order-id="<?= $orderId ?>"
                                data-order-code="<?= moH($code) ?>"
                            >
                                <i class="fa-solid fa-xmark"></i>
                                Hủy đơn hàng
                            </button>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>

<!-- FORM HUY DON - GIU NGUYEN CO CHE CU -->

<form
    id="myCancelForm"
    method="POST"
    action="/shop/cancel-order.php"
    hidden
>
    <input
        type="hidden"
        name="csrf"
        value="<?= moH($csrf) ?>"
    >

    <input
        type="hidden"
        name="order_id"
        id="myCancelId"
    >
</form>

<!-- POPUP XAC NHAN HUY -->

<div
    class="my-order-overlay"
    id="myCancelModal"
    hidden
>
    <div class="my-order-modal">

        <div class="my-order-modal-icon">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>

        <h2>Xác nhận hủy đơn hàng</h2>

        <p>
            Bạn có chắc chắn muốn hủy đơn
            <strong id="myCancelCode"></strong>?
        </p>

        <p>
            Chỉ đơn hàng đang chờ xác nhận mới được hủy.
            Sau khi hủy, không thể khôi phục đơn này.
        </p>

        <div class="my-order-modal-actions">

            <button
                type="button"
                class="my-modal-back"
                id="myCancelBack"
            >
                Quay lại
            </button>

            <button
                type="button"
                class="my-modal-confirm"
                id="myCancelConfirm"
            >
                Xác nhận hủy
            </button>

        </div>

    </div>
</div>

<!-- POPUP KET QUA -->

<div
    class="my-order-overlay"
    id="myResultModal"
    <?= $notice ? '' : 'hidden' ?>
>
    <div class="my-order-modal">

        <div class="my-order-modal-icon <?= ($notice['type'] ?? '') === 'success'
            ? 'success'
            : '' ?>">

            <i class="fa-solid <?= ($notice['type'] ?? '') === 'success'
                ? 'fa-circle-check'
                : 'fa-circle-xmark' ?>"></i>

        </div>

        <h2>
            <?= ($notice['type'] ?? '') === 'success'
                ? 'Đã hủy đơn hàng'
                : 'Thông báo' ?>
        </h2>

        <p>
            <?= moH($notice['message'] ?? '') ?>
        </p>

        <div class="my-order-modal-actions">

            <button
                type="button"
                class="my-modal-ok"
                id="myResultClose"
            >
                Đóng
            </button>

        </div>

    </div>
</div>

<script>
(function() {

    const modal = document.getElementById(
        'myCancelModal'
    );

    const form = document.getElementById(
        'myCancelForm'
    );

    const idInput = document.getElementById(
        'myCancelId'
    );

    const codeLabel = document.getElementById(
        'myCancelCode'
    );

    const resultModal = document.getElementById(
        'myResultModal'
    );

    document.querySelectorAll(
        '.my-order-cancel'
    ).forEach(function(button) {

        button.addEventListener('click', function() {

            idInput.value =
                button.dataset.orderId;

            codeLabel.textContent =
                button.dataset.orderCode;

            modal.hidden = false;

        });

    });

    document.getElementById(
        'myCancelBack'
    )?.addEventListener('click', function() {

        modal.hidden = true;

    });

    document.getElementById(
        'myCancelConfirm'
    )?.addEventListener('click', function() {

        const button = this;

        button.disabled = true;
        button.textContent = 'Đang xử lý...';

        form.requestSubmit();

    });

    document.getElementById(
        'myResultClose'
    )?.addEventListener('click', function() {

        resultModal.hidden = true;

    });

    [modal, resultModal].forEach(function(overlay) {

        if (!overlay) return;

        overlay.addEventListener(
            'click',
            function(event) {

                if (event.target === overlay) {
                    overlay.hidden = true;
                }

            }
        );

    });

    document.addEventListener(
        'keydown',
        function(event) {

            if (event.key === 'Escape') {
                modal.hidden = true;
                resultModal.hidden = true;
            }

        }
    );

})();
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
