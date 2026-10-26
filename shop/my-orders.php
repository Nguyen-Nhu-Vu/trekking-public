<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$page_title = 'Đơn Mua Thiết Bị - VNN Travel';
$base = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') . '/';

function myH($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function myMoney($v) {
    return number_format((float)$v, 0, ',', '.') . ' đ';
}
function myCols(PDO $pdo, $table) {
    if (!in_array($table, ['products', 'order_details'], true)) return [];
    return array_column(
        $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );
}
function myPick(array $cols, array $choices) {
    foreach ($choices as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}
function myImage($image, $base) {
    $image = trim((string)$image);

    if (!$image) return '';

    if ($image[0] === '[') {
        $images = json_decode($image, true);
        if (is_array($images)) $image = (string)($images[0] ?? '');
    }

    if (preg_match('~^https?://~i', $image)) return $image;
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $image)) return '';

    $image = str_replace('\\', '/', $image);
    if (str_contains($image, '..')) return '';

    return $base . ltrim($image, '/');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$orders = [];
$itemsByOrder = [];
$error = '';

if (empty($_SESSION['shop_cancel_csrf'])) {
    $_SESSION['shop_cancel_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['shop_cancel_csrf'];

$notice = $_SESSION['shop_order_notice'] ?? null;
unset($_SESSION['shop_order_notice']);

if ($userId > 0) {
    try {
        $q = $pdo->prepare("
            SELECT * FROM orders
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $q->execute([$userId]);
        $orders = $q->fetchAll(PDO::FETCH_ASSOC);

        if ($orders) {
            $dc = myCols($pdo, 'order_details');
            $pc = myCols($pdo, 'products');

            $name = myPick($pc, ['name', 'product_name', 'title']);
            $image = myPick($pc, [
                'image', 'image_url', 'product_image',
                'thumbnail', 'photo', 'images'
            ]);

            $detailName = myPick($dc, ['product_name', 'name', 'title']);
            $detailImage = myPick($dc, ['product_image', 'image', 'image_url']);
            $qty = myPick($dc, ['quantity', 'qty']);
            $price = myPick($dc, ['price', 'unit_price', 'product_price']);
            $total = myPick($dc, ['total', 'subtotal', 'line_total']);

            $join = in_array('product_id', $dc, true)
                ? 'LEFT JOIN products p ON p.id = d.product_id'
                : '';

            $nameOptions = [];
            if ($detailName) $nameOptions[] = "NULLIF(d.`$detailName`, '')";
            if ($join && $name) $nameOptions[] = "NULLIF(p.`$name`, '')";
            $nameOptions[] = "'Sản phẩm'";

            $imageOptions = [];
            if ($detailImage) $imageOptions[] = "NULLIF(d.`$detailImage`, '')";
            if ($join && $image) $imageOptions[] = "NULLIF(p.`$image`, '')";
            $imageOptions[] = "''";

            $nameSql = 'COALESCE(' . implode(',', $nameOptions) . ')';
            $imageSql = 'COALESCE(' . implode(',', $imageOptions) . ')';
            $qtySql = $qty ? "d.`$qty`" : 'NULL';
            $priceSql = $price ? "d.`$price`" : 'NULL';
            $totalSql = $total ? "d.`$total`" : 'NULL';

            $ids = array_map(
                static fn($order) => (int)$order['id'],
                $orders
            );
            $marks = implode(',', array_fill(0, count($ids), '?'));

            $q = $pdo->prepare("
                SELECT
                    d.order_id,
                    $nameSql AS item_name,
                    $imageSql AS item_image,
                    $qtySql AS item_quantity,
                    $priceSql AS item_price,
                    $totalSql AS item_total
                FROM order_details d
                $join
                WHERE d.order_id IN ($marks)
                ORDER BY d.order_id, d.id
            ");
            $q->execute($ids);

            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $id = (int)$item['order_id'];
                $quantity = $item['item_quantity'] === null
                    ? null : (int)$item['item_quantity'];
                $unitPrice = $item['item_price'] === null
                    ? null : (float)$item['item_price'];
                $lineTotal = $item['item_total'] === null
                    ? null : (float)$item['item_total'];

                if ($lineTotal === null && $unitPrice !== null && $quantity !== null) {
                    $lineTotal = $unitPrice * $quantity;
                }
                if ($unitPrice === null && $lineTotal !== null && $quantity > 0) {
                    $unitPrice = $lineTotal / $quantity;
                }

                $itemsByOrder[$id][] = [
                    'name' => $item['item_name'],
                    'image' => myImage($item['item_image'], $base),
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                    'total' => $lineTotal
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('My orders: ' . $e->getMessage());
        $error = 'Không thể tải đơn hàng.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.mo-page{background:#f3f6fa;padding:35px 17px 55px;min-height:65vh}
.mo-wrap{max-width:1240px;margin:auto}
.mo-title{font-size:24px;font-weight:800;color:#102e52;margin:0 0 24px}
.mo-card{background:#fff;border:1px solid #e0e8f0;border-radius:16px;overflow:hidden;margin-bottom:19px}
.mo-header{padding:17px 20px;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;gap:13px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0}
.mo-code{font-size:14px;font-weight:800;color:#102e52}
.mo-small{font-size:12px;color:#64748b;margin-top:5px}
.mo-status{border-radius:22px;padding:8px 13px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:800}
.mo-status.pending{background:#fff7ed;color:#c2410c}
.mo-status.cancelled{background:#fee2e2;color:#b91c1c}
.mo-status.done{background:#dcfce7;color:#166534}
.mo-columns,.mo-item{display:grid;grid-template-columns:minmax(0,2.5fr) minmax(100px,.9fr) minmax(65px,.6fr) minmax(120px,1fr);gap:13px;align-items:center;padding:14px 20px}
.mo-columns{font-size:12px;color:#64748b;font-weight:800;border-bottom:1px solid #e2e8f0}
.mo-columns>div:not(:first-child){text-align:right}
.mo-item{border-bottom:1px solid #edf2f7;font-size:13px}
.mo-product{display:flex;align-items:center;gap:14px;min-width:0;color:#12365e;font-weight:700}
.mo-img{width:82px;height:82px;flex:0 0 82px;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;overflow:hidden;color:#94a3b8;font-size:25px}
.mo-img img{display:block;width:100%;height:100%;object-fit:cover}
.mo-right{text-align:right;white-space:nowrap}
.mo-qty{color:#166534;font-weight:800}
.mo-line{color:#ea580c;font-weight:800}
.mo-footer{padding:17px 20px;display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap}
.mo-total{color:#64748b;font-size:13px}
.mo-total strong{font-size:19px;color:#ea580c;margin-left:7px}
.mo-actions{display:flex;gap:9px;flex-wrap:wrap}
.mo-btn{border:0;border-radius:9px;padding:11px 16px;font-size:12px;font-weight:800;cursor:pointer}
.mo-qrbtn{background:#166534;color:#fff}
.mo-cancel{background:#fff;color:#dc2626;border:1px solid #dc2626}
.mo-empty{background:#fff;padding:32px;text-align:center;color:#64748b;border-radius:14px}
.mo-overlay{position:fixed;inset:0;z-index:999999;background:rgba(15,23,42,.52);display:flex;align-items:center;justify-content:center;padding:18px}
.mo-overlay[hidden]{display:none!important}
.mo-modal{background:#fff;border-radius:17px;padding:28px;width:100%;max-width:450px;max-height:90vh;overflow-y:auto;text-align:center;box-shadow:0 20px 70px #0003;position:relative}
.mo-modal h2{font-size:21px;color:#102e52}
.mo-modal p{font-size:13px;color:#64748b;line-height:1.7}
.mo-modal-close{position:absolute;right:11px;top:9px;border:0;background:none;font-size:25px;cursor:pointer}
.mo-qr-image{display:block;width:min(100%,270px);max-height:330px;object-fit:contain;margin:15px auto;border:1px solid #e2e8f0;border-radius:12px}
.mo-info{text-align:left;padding:13px;background:#f8fafc;border-radius:10px;overflow-wrap:anywhere;font-size:12px}
.mo-info div{margin:8px 0}
.mo-confirm{background:#dc2626;color:#fff}
.mo-back{background:#e2e8f0;color:#334155}
@media(max-width:850px){.mo-columns{display:none}.mo-item{grid-template-columns:1fr 1fr}.mo-product{grid-column:1/-1}.mo-line{grid-column:1/-1}}
@media(max-width:550px){.mo-page{padding:20px 11px}.mo-title{font-size:20px}.mo-item,.mo-header,.mo-footer{padding:13px}.mo-img{width:65px;height:65px;flex-basis:65px}}
</style>

<main class="mo-page">
<div class="mo-wrap">
<h1 class="mo-title"><i class="fa-solid fa-box"></i> ĐƠN MUA THIẾT BỊ CỦA TÔI</h1>

<?php if ($userId <= 0): ?>
    <div class="mo-empty">
        Vui lòng <a href="<?= myH($base) ?>auth/login.php">đăng nhập</a>
        để xem đơn hàng.
    </div>
<?php elseif ($error): ?>
    <div class="mo-empty"><?= myH($error) ?></div>
<?php elseif (!$orders): ?>
    <div class="mo-empty">Bạn chưa có đơn mua thiết bị nào.</div>
<?php else: ?>

<?php foreach ($orders as $order): ?>
<?php
$id = (int)$order['id'];
$code = (string)($order['order_code'] ?? ('Đơn #' . $id));
$status = trim((string)($order['status'] ?? ''));
$pending = $status === 'Chờ xác nhận';
$isMomo = strcasecmp(trim((string)($order['payment_method'] ?? '')), 'MoMo') === 0;
$showQr = $pending && $isMomo;
$amount = $order['total_amount'] ?? $order['total_price'] ?? 0;
$person = (string)($order['fullname'] ?? '');
$phone = (string)($order['phone'] ?? '');
$transfer = trim($code . ' ' . $person . ' ' . $phone);
$items = $itemsByOrder[$id] ?? [];

$badge = match ($status) {
    'Chờ xác nhận' => 'pending',
    'Đã hủy' => 'cancelled',
    'Đã giao', 'Đã hoàn thành' => 'done',
    default => ''
};
?>
<article class="mo-card">
    <div class="mo-header">
        <div>
            <div class="mo-code">Mã đơn: <?= myH($code) ?></div>
            <div class="mo-small">
                Ngày đặt:
                <?= !empty($order['created_at'])
                    ? myH(date('d/m/Y H:i', strtotime($order['created_at'])))
                    : '—' ?>
            </div>
            <div class="mo-small">
                Thanh toán: <?= $isMomo ? 'MoMo' : 'Khi nhận hàng (COD)' ?>
            </div>
        </div>
        <span class="mo-status <?= myH($badge) ?>"><?= myH($status) ?></span>
    </div>

    <div class="mo-columns">
        <div>Sản phẩm</div>
        <div>Đơn giá</div>
        <div>Số lượng</div>
        <div>Thành tiền</div>
    </div>

    <?php foreach ($items as $item): ?>
    <div class="mo-item">
        <div class="mo-product">
            <div class="mo-img">
                <?php if ($item['image']): ?>
                    <img src="<?= myH($item['image']) ?>"
                         alt="<?= myH($item['name']) ?>"
                         loading="lazy"
                         onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                    <i class="fa-solid fa-image" hidden></i>
                <?php else: ?>
                    <i class="fa-solid fa-image"></i>
                <?php endif; ?>
            </div>
            <div><?= myH($item['name']) ?></div>
        </div>
        <div class="mo-right">
            <?= $item['price'] === null ? '—' : myMoney($item['price']) ?>
        </div>
        <div class="mo-right mo-qty">
            <?= $item['quantity'] === null ? '—' : (int)$item['quantity'] ?>
        </div>
        <div class="mo-right mo-line">
            <?= $item['total'] === null ? '—' : myMoney($item['total']) ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (!$items): ?>
        <div class="mo-empty">Chưa có thông tin sản phẩm.</div>
    <?php endif; ?>

    <div class="mo-footer">
        <div class="mo-total">
            Tổng tiền: <strong><?= myMoney($amount) ?></strong>
        </div>

        <div class="mo-actions">
            <?php if ($showQr): ?>
                <button type="button" class="mo-btn mo-qrbtn"
                    data-qr="1"
                    data-code="<?= myH($code) ?>"
                    data-amount="<?= myH(myMoney($amount)) ?>"
                    data-transfer="<?= myH($transfer) ?>">
                    <i class="fa-solid fa-qrcode"></i>
                    Xem QR thanh toán
                </button>
            <?php endif; ?>

            <?php if ($pending): ?>
                <button type="button" class="mo-btn mo-cancel"
                    data-cancel="1"
                    data-id="<?= $id ?>"
                    data-code="<?= myH($code) ?>">
                    Hủy đơn hàng
                </button>
            <?php endif; ?>
        </div>
    </div>
</article>
<?php endforeach; ?>
<?php endif; ?>
</div>
</main>

<form id="moCancelForm" method="POST"
      action="<?= myH($base) ?>shop/cancel-order.php" hidden>
    <input type="hidden" name="csrf" value="<?= myH($csrf) ?>">
    <input type="hidden" name="order_id" id="moCancelId">
</form>

<!-- QR MOMO -->
<div class="mo-overlay" id="moQrOverlay" hidden>
    <div class="mo-modal" role="dialog" aria-modal="true">
        <button class="mo-modal-close" type="button" data-close>&times;</button>
        <h2>Thanh toán qua MoMo</h2>
        <img class="mo-qr-image"
             src="<?= myH($base) ?>assets/images/momo-qr-vu.jpg"
             alt="QR MoMo VNN Travel">
        <div class="mo-info">
            <div>Mã đơn: <strong id="moQrCode"></strong></div>
            <div>Số tiền cần chuyển: <strong id="moQrAmount"></strong></div>
            <div>Nội dung chuyển khoản:
                <strong id="moQrTransfer"></strong>
            </div>
        </div>
        <p>
            Hãy nhập đúng số tiền và nội dung khi chuyển khoản.
            Đây là QR cố định, không tự xác nhận thanh toán.
        </p>
        <button class="mo-btn mo-qrbtn" type="button" id="moCopy">
            Sao chép nội dung
        </button>
        <button class="mo-btn mo-back" type="button" data-close>Đóng</button>
    </div>
</div>

<!-- XAC NHAN HUY -->
<div class="mo-overlay" id="moCancelOverlay" hidden>
    <div class="mo-modal" role="dialog" aria-modal="true">
        <button class="mo-modal-close" type="button" data-close>&times;</button>
        <h2>Xác nhận hủy đơn hàng</h2>
        <p>
            Bạn chắc chắn muốn hủy đơn
            <strong id="moCancelCode"></strong>?
        </p>
        <p>Đơn đã hủy không thể khôi phục.</p>
        <button class="mo-btn mo-back" type="button" data-close>Quay lại</button>
        <button class="mo-btn mo-confirm" type="button" id="moCancelSubmit">
            Xác nhận hủy
        </button>
    </div>
</div>

<!-- THONG BAO -->
<div class="mo-overlay" id="moNoticeOverlay"
     <?= $notice ? '' : 'hidden' ?>>
    <div class="mo-modal">
        <h2><?= ($notice['type'] ?? '') === 'success'
            ? 'Đã lưu thay đổi'
            : 'Thông báo' ?></h2>
        <p><?= myH($notice['message'] ?? '') ?></p>
        <button class="mo-btn mo-qrbtn" type="button" data-close>
            Đóng
        </button>
    </div>
</div>

<script>
(function() {
    const $ = id => document.getElementById(id);
    const overlays = ['moQrOverlay', 'moCancelOverlay', 'moNoticeOverlay'];

    function closeAll() {
        overlays.forEach(id => {
            const el = $(id);
            if (el) el.hidden = true;
        });
    }

    document.querySelectorAll('[data-qr]').forEach(button => {
        button.addEventListener('click', () => {
            $('moQrCode').textContent = button.dataset.code;
            $('moQrAmount').textContent = button.dataset.amount;
            $('moQrTransfer').textContent = button.dataset.transfer;
            $('moQrOverlay').hidden = false;
        });
    });

    document.querySelectorAll('[data-cancel]').forEach(button => {
        button.addEventListener('click', () => {
            $('moCancelId').value = button.dataset.id;
            $('moCancelCode').textContent = button.dataset.code;
            $('moCancelOverlay').hidden = false;
        });
    });

    document.querySelectorAll('[data-close]').forEach(button => {
        button.addEventListener('click', closeAll);
    });

    $('moCopy')?.addEventListener('click', function() {
        navigator.clipboard?.writeText($('moQrTransfer').textContent);
    });

    $('moCancelSubmit')?.addEventListener('click', function() {
        this.disabled = true;
        this.textContent = 'Đang xử lý...';
        $('moCancelForm').requestSubmit();
    });

    overlays.forEach(id => {
        $(id)?.addEventListener('click', function(e) {
            if (e.target === this) closeAll();
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAll();
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
