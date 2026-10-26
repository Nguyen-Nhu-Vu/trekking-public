<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$page_title = 'Đơn Mua Thiết Bị - VNN Travel';

if (!function_exists('shopOrderH')) {
    function shopOrderH($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('shopOrderMoney')) {
    function shopOrderMoney($value): string {
        return number_format((float)$value, 0, ',', '.') . ' đ';
    }
}

if (!function_exists('shopOrderColumn')) {
    function shopOrderColumn(
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

if (!function_exists('shopOrderColumns')) {
    function shopOrderColumns(PDO $pdo, string $table): array {
        if (!in_array(
            $table,
            ['orders', 'order_details', 'products'],
            true
        )) {
            return [];
        }

        return array_column(
            $pdo->query("SHOW COLUMNS FROM `$table`")
                ->fetchAll(PDO::FETCH_ASSOC),
            'Field'
        );
    }
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$orders = [];
$orderItems = [];
$error = '';

if (empty($_SESSION['shop_cancel_csrf'])) {
    $_SESSION['shop_cancel_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['shop_cancel_csrf'];

$notice = $_SESSION['shop_order_notice'] ?? null;
unset($_SESSION['shop_order_notice']);

if ($userId > 0) {
    try {
        // Chỉ tải đơn của tài khoản đang đăng nhập.
        $stmt = $pdo->prepare("
            SELECT *
            FROM orders
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
        ");

        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($orders) {
            $detailCols = shopOrderColumns(
                $pdo,
                'order_details'
            );

            $productCols = shopOrderColumns(
                $pdo,
                'products'
            );

            $nameCol = shopOrderColumn(
                $productCols,
                ['name', 'product_name', 'title']
            );

            $detailNameCol = shopOrderColumn(
                $detailCols,
                ['product_name', 'name', 'title']
            );

            $qtyCol = shopOrderColumn(
                $detailCols,
                ['quantity', 'qty']
            );

            $nameExpr = "'Sản phẩm'";

            if ($detailNameCol) {
                $nameExpr = "d.`$detailNameCol`";
            }

            $joinProducts = '';

            if (
                $nameCol &&
                in_array('product_id', $detailCols, true)
            ) {
                $joinProducts = "
                    LEFT JOIN products p
                        ON p.id = d.product_id
                ";

                $nameExpr = "COALESCE(
                    NULLIF(p.`$nameCol`, ''),
                    " . ($detailNameCol
                        ? "NULLIF(d.`$detailNameCol`, ''),"
                        : "") . "
                    'Sản phẩm'
                )";
            }

            $qtyExpr = $qtyCol
                ? "d.`$qtyCol`"
                : "1";

            $ids = array_map(
                fn($row) => (int)$row['id'],
                $orders
            );

            $placeholders = implode(
                ',',
                array_fill(0, count($ids), '?')
            );

            $sql = "
                SELECT
                    d.order_id,
                    $nameExpr AS item_name,
                    $qtyExpr AS item_quantity
                FROM order_details d
                $joinProducts
                WHERE d.order_id IN ($placeholders)
                ORDER BY d.order_id, d.id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $orderId = (int)$item['order_id'];
                $orderItems[$orderId][] = $item;
            }
        }

    } catch (Throwable $e) {
        error_log('My orders: ' . $e->getMessage());
        $error = 'Không thể tải danh sách đơn hàng.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.vnn-orders-page {
    min-height: 65vh;
    background: #f3f6fa;
    padding: 35px 18px 60px;
}

.vnn-orders-container {
    max-width: 1160px;
    margin: 0 auto;
}

.vnn-orders-title {
    margin: 0 0 22px;
    color: #102d50;
    font-size: 26px;
    font-weight: 800;
}

.vnn-order-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    margin-bottom: 18px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(15,23,42,.04);
}

.vnn-order-top {
    padding: 17px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.vnn-order-code {
    color: #123257;
    font-size: 14px;
    font-weight: 800;
}

.vnn-order-date {
    margin-top: 6px;
    color: #64748b;
    font-size: 12px;
}

.vnn-order-status {
    display: inline-flex;
    padding: 8px 12px;
    border-radius: 25px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 800;
}

.vnn-order-status.pending {
    background: #fff7ed;
    color: #c2410c;
}

.vnn-order-status.success {
    background: #dcfce7;
    color: #166534;
}

.vnn-order-status.cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

.vnn-order-content {
    padding: 18px 20px;
}

.vnn-order-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    padding: 11px 0;
    border-bottom: 1px solid #edf2f7;
    font-size: 13px;
}

.vnn-order-item:last-child {
    border-bottom: none;
}

.vnn-order-item-name {
    color: #1e3658;
    font-weight: 600;
}

.vnn-order-item-qty {
    color: #166534;
    font-weight: 800;
    white-space: nowrap;
}

.vnn-order-bottom {
    padding: 15px 20px;
    border-top: 1px solid #e2e8f0;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.vnn-order-total {
    color: #ea580c;
    font-size: 18px;
    font-weight: 900;
}

.vnn-order-cancel {
    padding: 10px 16px;
    border-radius: 9px;
    background: white;
    border: 1px solid #dc2626;
    color: #dc2626;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.vnn-order-cancel:hover {
    background: #fef2f2;
}

.vnn-order-message {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 23px;
    border-radius: 14px;
    color: #64748b;
    font-size: 14px;
    text-align: center;
}

.vnn-order-login {
    color: #166534;
    font-weight: 800;
}

/* POPUP */

.vnn-order-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;

    display: flex;
    justify-content: center;
    align-items: center;

    background: rgba(15,23,42,.52);
    padding: 20px;
}

.vnn-order-overlay[hidden] {
    display: none !important;
}

.vnn-order-modal {
    width: 100%;
    max-width: 420px;
    background: white;
    border-radius: 18px;
    padding: 28px;
    text-align: center;
    box-shadow: 0 20px 65px rgba(0,0,0,.2);
}

.vnn-order-modal-icon {
    font-size: 43px;
    color: #dc2626;
}

.vnn-order-modal-icon.success {
    color: #16a34a;
}

.vnn-order-modal h2 {
    margin: 14px 0 10px;
    font-size: 21px;
    color: #0f172a;
}

.vnn-order-modal p {
    color: #64748b;
    line-height: 1.7;
    font-size: 13px;
}

.vnn-order-modal-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 23px;
}

.vnn-order-modal-actions button {
    padding: 11px 18px;
    border: none;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.vnn-modal-back {
    background: #e2e8f0;
    color: #334155;
}

.vnn-modal-confirm {
    background: #dc2626;
    color: white;
}

.vnn-modal-ok {
    background: #166534;
    color: white;
}

@media(max-width:600px) {
    .vnn-orders-page {
        padding: 20px 12px 40px;
    }

    .vnn-orders-title {
        font-size: 21px;
    }
}
</style>

<main class="vnn-orders-page">
    <div class="vnn-orders-container">

        <h1 class="vnn-orders-title">
            <i class="fa-solid fa-box"></i>
            ĐƠN MUA THIẾT BỊ CỦA TÔI
        </h1>

        <?php if ($userId <= 0): ?>

            <div class="vnn-order-message">
                Vui lòng
                <a
                    class="vnn-order-login"
                    href="/auth/login.php"
                >
                    đăng nhập
                </a>
                để xem đơn hàng.
            </div>

        <?php elseif ($error !== ''): ?>

            <div class="vnn-order-message">
                <?= shopOrderH($error) ?>
            </div>

        <?php elseif (!$orders): ?>

            <div class="vnn-order-message">
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

                $canCancel = $status === 'Chờ xác nhận';

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

                <article class="vnn-order-card">

                    <div class="vnn-order-top">
                        <div>
                            <div class="vnn-order-code">
                                Mã đơn: <?= shopOrderH($code) ?>
                            </div>

                            <div class="vnn-order-date">
                                Ngày đặt:
                                <?= !empty($order['created_at'])
                                    ? shopOrderH(date(
                                        'd/m/Y H:i',
                                        strtotime($order['created_at'])
                                    ))
                                    : '—' ?>
                            </div>
                        </div>

                        <span class="vnn-order-status <?= $statusClass ?>">
                            <?= shopOrderH($status) ?>
                        </span>
                    </div>

                    <div class="vnn-order-content">

                        <?php if ($items): ?>

                            <?php foreach ($items as $item): ?>

                                <div class="vnn-order-item">

                                    <span class="vnn-order-item-name">
                                        <?= shopOrderH($item['item_name']) ?>
                                    </span>

                                    <span class="vnn-order-item-qty">
                                        × <?= (int)$item['item_quantity'] ?>
                                    </span>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="vnn-order-item">
                                <span>Chưa có thông tin chi tiết sản phẩm.</span>
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="vnn-order-bottom">

                        <div>
                            <span style="color:#64748b;font-size:13px">
                                Tổng tiền:
                            </span>

                            <strong class="vnn-order-total">
                                <?= shopOrderMoney($amount) ?>
                            </strong>
                        </div>

                        <?php if ($canCancel): ?>

                            <button
                                type="button"
                                class="vnn-order-cancel"
                                data-order-id="<?= $orderId ?>"
                                data-order-code="<?= shopOrderH($code) ?>"
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

<!-- FORM GUI YEU CAU HUY -->

<form
    id="vnnCancelOrderForm"
    method="POST"
    action="/shop/cancel-order.php"
    hidden
>
    <input
        type="hidden"
        name="csrf"
        value="<?= shopOrderH($csrf) ?>"
    >

    <input
        type="hidden"
        name="order_id"
        id="vnnCancelOrderId"
    >
</form>

<!-- POPUP XAC NHAN -->

<div
    class="vnn-order-overlay"
    id="vnnCancelModal"
    hidden
>
    <div class="vnn-order-modal">

        <div class="vnn-order-modal-icon">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>

        <h2>Xác nhận hủy đơn hàng</h2>

        <p>
            Bạn có chắc chắn muốn hủy đơn
            <strong id="vnnCancelCode"></strong>?
        </p>

        <p>
            Chỉ đơn hàng đang chờ xác nhận mới được hủy.
            Sau khi hủy, bạn không thể khôi phục đơn này.
        </p>

        <div class="vnn-order-modal-actions">

            <button
                class="vnn-modal-back"
                type="button"
                id="vnnCancelBack"
            >
                Quay lại
            </button>

            <button
                class="vnn-modal-confirm"
                type="button"
                id="vnnCancelConfirm"
            >
                Xác nhận hủy
            </button>

        </div>
    </div>
</div>

<!-- POPUP KET QUA -->

<div
    class="vnn-order-overlay"
    id="vnnOrderResultModal"
    <?= $notice ? '' : 'hidden' ?>
>
    <div class="vnn-order-modal">

        <div class="vnn-order-modal-icon <?= ($notice['type'] ?? '') === 'success'
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
            <?= shopOrderH($notice['message'] ?? '') ?>
        </p>

        <div class="vnn-order-modal-actions">
            <button
                class="vnn-modal-ok"
                type="button"
                id="vnnResultClose"
            >
                Đóng
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('vnnCancelModal');
    const form = document.getElementById('vnnCancelOrderForm');
    const idInput = document.getElementById('vnnCancelOrderId');
    const codeLabel = document.getElementById('vnnCancelCode');
    const resultModal = document.getElementById('vnnOrderResultModal');

    document.querySelectorAll('.vnn-order-cancel')
        .forEach(button => {
            button.addEventListener('click', () => {
                idInput.value = button.dataset.orderId;
                codeLabel.textContent = button.dataset.orderCode;
                modal.hidden = false;
            });
        });

    document.getElementById('vnnCancelBack')
        ?.addEventListener('click', () => {
            modal.hidden = true;
        });

    document.getElementById('vnnCancelConfirm')
        ?.addEventListener('click', () => {
            const button = document.getElementById('vnnCancelConfirm');
            button.disabled = true;
            button.textContent = 'Đang xử lý...';
            form.requestSubmit();
        });

    document.getElementById('vnnResultClose')
        ?.addEventListener('click', () => {
            resultModal.hidden = true;
        });

    [modal, resultModal].forEach(overlay => {
        overlay?.addEventListener('click', event => {
            if (event.target === overlay) {
                overlay.hidden = true;
            }
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            modal.hidden = true;
            resultModal.hidden = true;
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
