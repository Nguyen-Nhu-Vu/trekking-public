<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

$current_page = 'revenue';
$page_title = 'Doanh thu tổng hợp - VNN Travel';

if (!function_exists('rh')) {
    function rh($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function revenueRows(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function revenueNumber(PDO $pdo, string $sql, array $params = []): float {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

function revenueColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function revenueFirstColumn(array $columns, array $candidates): ?string {
    foreach ($candidates as $name) {
        if (in_array($name, $columns, true)) {
            return $name;
        }
    }
    return null;
}

// ===========================================
// BO LOC
// ===========================================

$source = (string)($_GET['source'] ?? 'all');
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);
$tourId = max(0, (int)($_GET['tour_id'] ?? 0));
$categoryId = max(0, (int)($_GET['category_id'] ?? 0));

if (!in_array($source, ['all', 'tour', 'shop'], true)) {
    $source = 'all';
}

if ($year < 2020 || $year > 2100) {
    $year = (int)date('Y');
}

if ($month < 0 || $month > 12) {
    $month = 0;
}

$yearStart = sprintf('%04d-01-01', $year);
$yearEnd = sprintf('%04d-01-01', $year + 1);

$from = $month
    ? sprintf('%04d-%02d-01', $year, $month)
    : $yearStart;

$to = $month
    ? date('Y-m-d', strtotime($from . ' +1 month'))
    : $yearEnd;

$tours = revenueRows(
    $pdo,
    "SELECT id, title FROM tours ORDER BY title"
);

$categories = revenueRows(
    $pdo,
    "SELECT id, name FROM categories ORDER BY name"
);

if (!in_array($tourId, array_map('intval', array_column($tours, 'id')), true)) {
    $tourId = 0;
}

if (!in_array($categoryId, array_map('intval', array_column($categories, 'id')), true)) {
    $categoryId = 0;
}

if ($source !== 'tour') {
    $tourId = 0;
}

if ($source !== 'shop') {
    $categoryId = 0;
}

// ===========================================
// XAC DINH BANG CHI TIET DON HANG
// ===========================================

$detailTable = null;
$detailColumns = [];

foreach (['order_details', 'order_items'] as $candidate) {
    $cols = revenueColumns($pdo, $candidate);

    if ($cols) {
        $detailTable = $candidate;
        $detailColumns = $cols;
        break;
    }
}

$orderIdCol = revenueFirstColumn($detailColumns, ['order_id']);
$productIdCol = revenueFirstColumn($detailColumns, ['product_id']);
$qtyCol = revenueFirstColumn($detailColumns, ['quantity', 'qty']);
$priceCol = revenueFirstColumn($detailColumns, ['price', 'unit_price', 'product_price']);
$subtotalCol = revenueFirstColumn($detailColumns, ['subtotal', 'total_price', 'line_total', 'total']);

$categorySupported =
    $detailTable !== null &&
    $orderIdCol !== null &&
    $productIdCol !== null &&
    (
        $subtotalCol !== null ||
        ($qtyCol !== null && $priceCol !== null)
    );

$lineAmount = '';

if ($categorySupported) {
    $lineAmount = $subtotalCol !== null
        ? "od.`$subtotalCol`"
        : "(od.`$qtyCol` * od.`$priceCol`)";
}

$categoryWarning = '';

if ($categoryId > 0 && !$categorySupported) {
    $categoryWarning = 'Chưa đủ dữ liệu chi tiết đơn hàng để thống kê theo danh mục.';
}

function revenueShop(
    PDO $pdo,
    string $from,
    string $to,
    int $categoryId,
    bool $supported,
    ?string $detailTable,
    ?string $orderIdCol,
    ?string $productIdCol,
    string $lineAmount
): ?float {

    if ($categoryId === 0) {
        return revenueNumber($pdo, "
            SELECT COALESCE(SUM(total_amount), 0)
            FROM orders
            WHERE status = 'Đã giao'
              AND created_at >= ?
              AND created_at < ?
        ", [$from, $to]);
    }

    if (!$supported) {
        return null;
    }

    return revenueNumber($pdo, "
        SELECT COALESCE(SUM($lineAmount), 0)
        FROM `$detailTable` od
        JOIN orders o ON o.id = od.`$orderIdCol`
        JOIN products p ON p.id = od.`$productIdCol`
        WHERE o.status = 'Đã giao'
          AND o.created_at >= ?
          AND o.created_at < ?
          AND p.category_id = ?
    ", [$from, $to, $categoryId]);
}

// ===========================================
// DOANH THU HOAN THANH
// ===========================================

$tourFilter = '';
$tourParams = [];

if ($tourId > 0) {
    $tourFilter = ' AND b.tour_id = ?';
    $tourParams[] = $tourId;
}

$paidTour = "b.status IN ('Đã thanh toán', 'Đã hoàn thành')";

$tourRevenue = revenueNumber($pdo, "
    SELECT COALESCE(SUM(b.total_price), 0)
    FROM tour_bookings b
    WHERE $paidTour
      AND b.created_at >= ?
      AND b.created_at < ?
      $tourFilter
", array_merge([$from, $to], $tourParams));

$shopRevenue = revenueShop(
    $pdo,
    $from,
    $to,
    $categoryId,
    $categorySupported,
    $detailTable,
    $orderIdCol,
    $productIdCol,
    $lineAmount
);

$displayTour = $source !== 'shop' ? $tourRevenue : 0;
$displayShop = $source !== 'tour' ? $shopRevenue : 0;

$totalRevenue = $displayShop === null
    ? null
    : $displayTour + $displayShop;

// ===========================================
// GIA TRI DANG XU LY
// ===========================================

$tourPending = revenueNumber($pdo, "
    SELECT COALESCE(SUM(b.total_price), 0)
    FROM tour_bookings b
    WHERE b.status IN ('Chờ xác nhận', 'Đã xác nhận')
      AND b.created_at >= ?
      AND b.created_at < ?
      $tourFilter
", array_merge([$from, $to], $tourParams));

$shopPending = revenueNumber($pdo, "
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE status IN ('Chờ xác nhận', 'Đã xác nhận', 'Đang giao')
      AND created_at >= ?
      AND created_at < ?
", [$from, $to]);

$pendingTotal = match ($source) {
    'tour' => $tourPending,
    'shop' => $shopPending,
    default => $tourPending + $shopPending
};

// ===========================================
// BIEU DO DOANH THU 12 THANG
// ===========================================

$tourMonths = array_fill(1, 12, 0);
$shopMonths = array_fill(1, 12, 0);

$tourChartRows = revenueRows($pdo, "
    SELECT
        MONTH(b.created_at) AS month_number,
        COALESCE(SUM(b.total_price), 0) AS revenue
    FROM tour_bookings b
    WHERE $paidTour
      AND b.created_at >= ?
      AND b.created_at < ?
      $tourFilter
    GROUP BY MONTH(b.created_at)
", array_merge([$yearStart, $yearEnd], $tourParams));

foreach ($tourChartRows as $row) {
    $m = (int)$row['month_number'];

    if ($m >= 1 && $m <= 12) {
        $tourMonths[$m] = (float)$row['revenue'];
    }
}

if ($categoryId === 0) {
    $shopChartRows = revenueRows($pdo, "
        SELECT
            MONTH(created_at) AS month_number,
            COALESCE(SUM(total_amount), 0) AS revenue
        FROM orders
        WHERE status = 'Đã giao'
          AND created_at >= ?
          AND created_at < ?
        GROUP BY MONTH(created_at)
    ", [$yearStart, $yearEnd]);

    foreach ($shopChartRows as $row) {
        $m = (int)$row['month_number'];

        if ($m >= 1 && $m <= 12) {
            $shopMonths[$m] = (float)$row['revenue'];
        }
    }
} elseif ($categorySupported) {
    for ($m = 1; $m <= 12; $m++) {
        $start = sprintf('%04d-%02d-01', $year, $m);
        $end = date('Y-m-d', strtotime($start . ' +1 month'));

        $shopMonths[$m] = revenueShop(
            $pdo,
            $start,
            $end,
            $categoryId,
            $categorySupported,
            $detailTable,
            $orderIdCol,
            $productIdCol,
            $lineAmount
        ) ?? 0;
    }
}

// CHINH SUA QUAN TRONG:
// TAT CA = 1 COT TONG CUA MOI THANG

$totalMonths = [];

for ($m = 1; $m <= 12; $m++) {
    $totalMonths[] =
        (float)$tourMonths[$m] +
        (float)$shopMonths[$m];
}

// ===========================================
// CHI TIET TOUR
// ===========================================

$tourDetails = [];

if ($source !== 'shop') {
    $tourDetails = revenueRows($pdo, "
        SELECT
            t.title AS tour_name,
            COUNT(b.id) AS booking_count,
            COALESCE(SUM(b.number_people), 0) AS guests,
            COALESCE(SUM(b.total_price), 0) AS revenue
        FROM tour_bookings b
        JOIN tours t ON t.id = b.tour_id
        WHERE $paidTour
          AND b.created_at >= ?
          AND b.created_at < ?
          $tourFilter
        GROUP BY t.id, t.title
        ORDER BY revenue DESC
    ", array_merge([$from, $to], $tourParams));
}

// ===========================================
// CHI TIET BAN HANG
// ===========================================

$shopDetails = [];

if ($source !== 'tour') {
    if ($categoryId === 0) {
        $shopDetails = revenueRows($pdo, "
            SELECT order_code, fullname,
                   total_amount AS revenue, created_at
            FROM orders
            WHERE status = 'Đã giao'
              AND created_at >= ?
              AND created_at < ?
            ORDER BY created_at DESC
            LIMIT 200
        ", [$from, $to]);
    } elseif ($categorySupported) {
        $shopDetails = revenueRows($pdo, "
            SELECT o.order_code, o.fullname,
                   SUM($lineAmount) AS revenue,
                   o.created_at
            FROM `$detailTable` od
            JOIN orders o ON o.id = od.`$orderIdCol`
            JOIN products p ON p.id = od.`$productIdCol`
            WHERE o.status = 'Đã giao'
              AND o.created_at >= ?
              AND o.created_at < ?
              AND p.category_id = ?
            GROUP BY o.id, o.order_code, o.fullname, o.created_at
            ORDER BY o.created_at DESC
            LIMIT 200
        ", [$from, $to, $categoryId]);
    }
}

// ===========================================
// TRANG THAI GIAO DICH
// ===========================================

$tourStatuses = revenueRows($pdo, "
    SELECT status, COUNT(*) AS quantity
    FROM tour_bookings
    WHERE created_at >= ? AND created_at < ?
    GROUP BY status
    ORDER BY quantity DESC
", [$from, $to]);

$orderStatuses = revenueRows($pdo, "
    SELECT status, COUNT(*) AS quantity
    FROM orders
    WHERE created_at >= ? AND created_at < ?
    GROUP BY status
    ORDER BY quantity DESC
", [$from, $to]);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= rh($page_title) ?></title>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

<style>
.revenue-page {
    flex: 1;
    min-width: 0;
    background: #f1f5f9;
}
.revenue-content {
    padding: 24px;
}
.revenue-box {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    padding: 24px;
    margin-bottom: 22px;
}
.revenue-box h2 {
    color: #0f172a;
    font-size: 20px;
    margin: 0 0 20px;
}
.revenue-filters {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.revenue-field label {
    display: block;
    color: #334155;
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 8px;
}
.revenue-field select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: #fff;
    font: inherit;
}
.revenue-btn {
    background: #166534;
    color: white;
    border: none;
    border-radius: 9px;
    padding: 12px 20px;
    margin-top: 18px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}
.revenue-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}
.revenue-stat {
    background: white;
    padding: 21px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    min-width: 0;
}
.revenue-label {
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
}
.revenue-value {
    margin-top: 14px;
    font-size: 25px;
    font-weight: 900;
    color: #166534;
    overflow-wrap: anywhere;
}
.revenue-value.orange {color:#ea580c}
.revenue-value.blue {color:#0284c7}
.revenue-value.yellow {color:#d97706}

.revenue-chart-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}
.revenue-chart-toolbar h2 {
    margin: 0;
}
.revenue-chart {
    position: relative;
    height: 350px;
}
.revenue-chart-select {
    min-width: 180px;
}
.revenue-table-wrap {
    overflow-x: auto;
}
.revenue-table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}
.revenue-table th,
.revenue-table td {
    text-align: left;
    padding: 14px 12px;
    border-bottom: 1px solid #e2e8f0;
}
.revenue-table th {
    background: #f8fafc;
    color: #0f172a;
    font-size: 13px;
}
.revenue-money {
    color: #ea580c;
    font-weight: 800;
}
.revenue-status-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}
.revenue-status-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}
.revenue-note {
    color: #64748b;
    line-height: 1.8;
    font-size: 13px;
}
.revenue-warning {
    padding: 14px;
    margin-bottom: 20px;
    border-radius: 9px;
    background: #fff7ed;
    color: #9a3412;
}
@media(max-width:1200px) {
    .revenue-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media(max-width:800px) {
    .revenue-filters,
    .revenue-status-grid {
        grid-template-columns: 1fr;
    }
}
@media(max-width:550px) {
    .revenue-stats {
        grid-template-columns: 1fr;
    }
    .revenue-content {
        padding: 12px;
    }
}
</style>
</head>

<body class="admin-body">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main revenue-page">

<div class="admin-topbar">
    <strong>
        <i class="fa-solid fa-chart-line"></i>
        DOANH THU & BÁO CÁO
    </strong>
</div>

<div class="admin-content revenue-content">

    <!-- BO LOC -->

    <section class="revenue-box">
        <h2>Bộ lọc doanh thu</h2>

        <form method="GET" id="revenueFilterForm">
            <div class="revenue-filters">

                <div class="revenue-field">
                    <label for="source">Nguồn doanh thu</label>
                    <select name="source" id="source"
                            onchange="this.form.submit()">
                        <option value="all" <?= $source==='all'?'selected':'' ?>>
                            Tất cả
                        </option>
                        <option value="tour" <?= $source==='tour'?'selected':'' ?>>
                            Tour
                        </option>
                        <option value="shop" <?= $source==='shop'?'selected':'' ?>>
                            Bán hàng
                        </option>
                    </select>
                </div>

                <div class="revenue-field">
                    <label for="month">Tháng</label>
                    <select name="month" id="month">
                        <option value="0" <?= $month===0?'selected':'' ?>>
                            Cả năm
                        </option>
                        <?php for ($m=1;$m<=12;$m++): ?>
                            <option value="<?= $m ?>"
                                <?= $month===$m?'selected':'' ?>>
                                Tháng <?= $m ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="revenue-field">
                    <label for="year">Năm</label>
                    <select name="year" id="year">
                        <?php for ($y=2020;$y<=max(2100,$year);$y++): ?>
                            <?php if ($y <= (int)date('Y')+1 || $y===$year): ?>
                                <option value="<?= $y ?>"
                                    <?= $year===$y?'selected':'' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </select>
                </div>

                <?php if ($source==='tour'): ?>
                    <div class="revenue-field">
                        <label for="tour_id">Lọc theo từng tour</label>
                        <select name="tour_id" id="tour_id">
                            <option value="0">Tất cả tour</option>
                            <?php foreach ($tours as $tour): ?>
                                <option value="<?= (int)$tour['id'] ?>"
                                    <?= $tourId===(int)$tour['id']?'selected':'' ?>>
                                    <?= rh($tour['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($source==='shop'): ?>
                    <div class="revenue-field">
                        <label for="category_id">Danh mục hàng</label>
                        <select name="category_id" id="category_id">
                            <option value="0">Tất cả danh mục</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"
                                    <?= $categoryId===(int)$category['id']?'selected':'' ?>>
                                    <?= rh($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

            </div>

            <button class="revenue-btn" type="submit">
                <i class="fa-solid fa-filter"></i>
                Xem thống kê
            </button>
        </form>
    </section>

    <?php if ($categoryWarning): ?>
        <div class="revenue-warning"><?= rh($categoryWarning) ?></div>
    <?php endif; ?>

    <!-- CAC THE DOANH THU -->

    <div class="revenue-stats">

        <div class="revenue-stat">
            <div class="revenue-label">Tổng doanh thu hoàn thành</div>
            <div class="revenue-value orange">
                <?= $totalRevenue === null
                    ? 'Chưa đủ dữ liệu'
                    : format_price($totalRevenue) ?>
            </div>
        </div>

        <div class="revenue-stat">
            <div class="revenue-label">Doanh thu Tour</div>
            <div class="revenue-value">
                <?= format_price($displayTour) ?>
            </div>
        </div>

        <div class="revenue-stat">
            <div class="revenue-label">Doanh thu Bán hàng</div>
            <div class="revenue-value blue">
                <?= $displayShop === null
                    ? 'Chưa đủ dữ liệu'
                    : format_price($displayShop) ?>
            </div>
        </div>

        <div class="revenue-stat">
            <div class="revenue-label">Giá trị đơn đang xử lý</div>
            <div class="revenue-value yellow">
                <?= format_price($pendingTotal) ?>
            </div>
            <p class="revenue-note">
                Chưa tính vào doanh thu hoàn thành.
            </p>
        </div>

    </div>

    <!-- BIEU DO -->

    <section class="revenue-box">

        <div class="revenue-chart-toolbar">

            <h2>
                <i class="fa-solid fa-chart-column"></i>
                Biểu đồ doanh thu năm <?= $year ?>
            </h2>

            <div class="revenue-field">
                <label for="chartSource">Hiển thị biểu đồ</label>
                <select
                    id="chartSource"
                    class="revenue-chart-select"
                >
                    <option value="all">Tất cả</option>
                    <option value="tour">Chỉ Tour</option>
                    <option value="shop">Chỉ Bán hàng</option>
                </select>
            </div>

        </div>

        <div class="revenue-chart">
            <canvas id="revenueChart"></canvas>
        </div>

    </section>

    <!-- KIEM TRA TRANG THAI -->

    <section class="revenue-box">
        <h2>
            <i class="fa-solid fa-circle-info"></i>
            Kiểm tra trạng thái giao dịch
        </h2>

        <p class="revenue-note">
            Chỉ các giao dịch đã hoàn thành mới được tính vào
            tổng doanh thu.
        </p>

        <div class="revenue-status-grid">

            <div>
                <h3>Đăng ký Tour</h3>

                <?php foreach ($tourStatuses as $row): ?>
                    <div class="revenue-status-row">
                        <span><?= rh($row['status']) ?></span>
                        <strong><?= (int)$row['quantity'] ?></strong>
                    </div>
                <?php endforeach; ?>

                <?php if (!$tourStatuses): ?>
                    <p class="revenue-note">Chưa có đăng ký trong kỳ.</p>
                <?php endif; ?>
            </div>

            <div>
                <h3>Đơn hàng</h3>

                <?php foreach ($orderStatuses as $row): ?>
                    <div class="revenue-status-row">
                        <span><?= rh($row['status']) ?></span>
                        <strong><?= (int)$row['quantity'] ?></strong>
                    </div>
                <?php endforeach; ?>

                <?php if (!$orderStatuses): ?>
                    <p class="revenue-note">Chưa có đơn hàng trong kỳ.</p>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- CHI TIET DOANH THU TOUR -->

    <?php if ($source !== 'shop'): ?>

        <section class="revenue-box">

            <h2>Doanh thu theo từng Tour</h2>

            <div class="revenue-table-wrap">
                <table class="revenue-table">
                    <thead>
                        <tr>
                            <th>Tên Tour</th>
                            <th>Lượt đăng ký</th>
                            <th>Số khách</th>
                            <th>Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tourDetails as $row): ?>
                            <tr>
                                <td><?= rh($row['tour_name']) ?></td>
                                <td><?= (int)$row['booking_count'] ?></td>
                                <td><?= (int)$row['guests'] ?></td>
                                <td class="revenue-money">
                                    <?= format_price($row['revenue']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$tourDetails): ?>
                            <tr>
                                <td colspan="4">
                                    Không có doanh thu Tour hoàn thành trong kỳ.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>

    <?php endif; ?>

    <!-- CHI TIET DOANH THU BAN HANG -->

    <?php if ($source !== 'tour'): ?>

        <section class="revenue-box">

            <h2>Doanh thu đơn hàng</h2>

            <div class="revenue-table-wrap">
                <table class="revenue-table">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Doanh thu</th>
                            <th>Ngày tạo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shopDetails as $row): ?>
                            <tr>
                                <td><?= rh($row['order_code']) ?></td>
                                <td><?= rh($row['fullname']) ?></td>
                                <td class="revenue-money">
                                    <?= format_price($row['revenue']) ?>
                                </td>
                                <td><?= rh($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$shopDetails): ?>
                            <tr>
                                <td colspan="4">
                                    Không có doanh thu bán hàng hoàn thành trong kỳ.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>

    <?php endif; ?>

    <p class="revenue-note">
        Doanh thu được xác định dựa trên trạng thái hiện tại của
        giao dịch. Biểu đồ phân theo tháng tạo đơn.
    </p>

</div>
</main>

<script>
const tourMonths = <?= json_encode(
    array_values($tourMonths),
    JSON_NUMERIC_CHECK
) ?>;

const shopMonths = <?= json_encode(
    array_values($shopMonths),
    JSON_NUMERIC_CHECK
) ?>;

const totalMonths = <?= json_encode(
    $totalMonths,
    JSON_NUMERIC_CHECK
) ?>;

const chartCanvas = document.getElementById('revenueChart');
const chartSource = document.getElementById('chartSource');

const moneyFormat = new Intl.NumberFormat('vi-VN');

let revenueChart = null;

function drawRevenueChart(type) {

    if (!chartCanvas || typeof Chart === 'undefined') {
        return;
    }

    if (revenueChart !== null) {
        revenueChart.destroy();
    }

    let dataset;

    if (type === 'tour') {
        dataset = {
            label: 'Doanh thu Tour',
            data: tourMonths,
            backgroundColor: '#166534',
            borderRadius: 5
        };
    } else if (type === 'shop') {
        dataset = {
            label: 'Doanh thu Bán hàng',
            data: shopMonths,
            backgroundColor: '#ea580c',
            borderRadius: 5
        };
    } else {
        // TAT CA CHI CO MOT COT TONG MOI THANG
        dataset = {
            label: 'Tổng doanh thu',
            data: totalMonths,
            backgroundColor: '#0284c7',
            borderRadius: 5
        };
    }

    revenueChart = new Chart(chartCanvas, {
        type: 'bar',

        data: {
            labels: [
                'Tháng 1', 'Tháng 2', 'Tháng 3',
                'Tháng 4', 'Tháng 5', 'Tháng 6',
                'Tháng 7', 'Tháng 8', 'Tháng 9',
                'Tháng 10', 'Tháng 11', 'Tháng 12'
            ],
            datasets: [dataset]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' +
                                moneyFormat.format(context.parsed.y) +
                                ' đ';
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return moneyFormat.format(value);
                        }
                    }
                }
            }
        }
    });
}

drawRevenueChart('all');

chartSource.addEventListener('change', function() {
    drawRevenueChart(this.value);
});
</script>

</body>
</html>
