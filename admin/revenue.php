<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

$current_page = 'revenue';
$page_title = 'Doanh Thu & Báo Cáo - VNN Travel';

if (!function_exists('rvH')) {
    function rvH($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rvMoney')) {
    function rvMoney($v) {
        return number_format((float)$v, 0, ',', '.') . ' đ';
    }
}

if (!function_exists('rvRows')) {
    function rvRows(PDO $pdo, string $sql, array $params = []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('rvColumns')) {
    function rvColumns(PDO $pdo, string $table) {
        if (!in_array($table, [
            'orders', 'order_details', 'products',
            'tour_bookings', 'tours'
        ], true)) {
            return [];
        }

        try {
            return array_column(
                $pdo->query("SHOW COLUMNS FROM `$table`")
                    ->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('rvFindColumn')) {
    function rvFindColumn(array $columns, array $names) {
        foreach ($names as $name) {
            if (in_array($name, $columns, true)) {
                return $name;
            }
        }
        return null;
    }
}

if (!function_exists('rvDate')) {
    function rvDate($date) {
        $time = strtotime((string)$date);
        return $time ? date('d/m/Y H:i', $time) : '—';
    }
}

// =========================================
// BO LOC
// =========================================

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);

if (!$year || $year < 2020 || $year > 2100) {
    $year = (int)date('Y');
}

$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

if (!$month || $month < 1 || $month > 12) {
    $month = 0;
}

$source = (string)($_GET['source'] ?? 'all');

if (!in_array($source, ['all', 'tour', 'shop'], true)) {
    $source = 'all';
}

$chartSource = (string)($_GET['chart'] ?? 'all');

if (!in_array($chartSource, ['all', 'tour', 'shop'], true)) {
    $chartSource = 'all';
}

$filterTour = filter_input(
    INPUT_GET,
    'tour_id',
    FILTER_VALIDATE_INT
);

$filterTour = $filterTour > 0 ? $filterTour : 0;

$startDate = sprintf('%04d-01-01 00:00:00', $year);
$endDate = sprintf('%04d-01-01 00:00:00', $year + 1);

if ($month) {
    $startDate = sprintf(
        '%04d-%02d-01 00:00:00',
        $year,
        $month
    );

    $endDate = (new DateTimeImmutable($startDate))
        ->modify('+1 month')
        ->format('Y-m-d H:i:s');
}

// =========================================
// CAU TRUC DATABASE
// =========================================

$orderCols = rvColumns($pdo, 'orders');
$detailCols = rvColumns($pdo, 'order_details');
$productCols = rvColumns($pdo, 'products');
$bookingCols = rvColumns($pdo, 'tour_bookings');

$quantityColumn = rvFindColumn(
    $detailCols,
    ['quantity', 'qty', 'number']
);

$productNameColumn = rvFindColumn(
    $productCols,
    ['name', 'product_name', 'title']
);

$detailNameColumn = rvFindColumn(
    $detailCols,
    ['product_name', 'name', 'title']
);

$orderTotalColumn = rvFindColumn(
    $orderCols,
    ['total_amount', 'total_price', 'amount']
);

$bookingTotalColumn = rvFindColumn(
    $bookingCols,
    ['total_price', 'total_amount', 'amount']
);

$bookingPeopleColumn = rvFindColumn(
    $bookingCols,
    [
        'number_people',
        'number_of_people',
        'num_people',
        'total_people',
        'people_count',
        'quantity'
    ]
);

// =========================================
// BIEN DU LIEU
// =========================================

$tours = [];
$tourBookings = [];
$orders = [];
$orderProducts = [];
$tourReport = [];
$errors = [];

$tourRevenue = 0;
$shopRevenue = 0;
$processingValue = 0;

$chartTour = array_fill(1, 12, 0);
$chartShop = array_fill(1, 12, 0);

$paidTourStatuses = [
    'Đã thanh toán',
    'Đã hoàn thành'
];

$completedOrderStatuses = [
    'Đã giao',
    'Đã hoàn thành',
    'Hoàn thành',
    'completed',
    'delivered'
];

// =========================================
// TOUR
// =========================================

try {
    $tours = rvRows(
        $pdo,
        "SELECT id, title FROM tours ORDER BY title"
    );
} catch (Throwable $e) {
    error_log('Revenue tours: ' . $e->getMessage());
}

// =========================================
// DOANH THU TOUR
// =========================================

try {
    $sql = "
        SELECT b.*, t.title AS report_tour_name
        FROM tour_bookings b
        LEFT JOIN tours t ON t.id = b.tour_id
        WHERE b.created_at >= ?
          AND b.created_at < ?
    ";

    $params = [$startDate, $endDate];

    if ($filterTour > 0) {
        $sql .= " AND b.tour_id = ? ";
        $params[] = $filterTour;
    }

    $sql .= " ORDER BY b.created_at DESC ";

    $tourBookings = rvRows($pdo, $sql, $params);

    foreach ($tourBookings as $booking) {
        $status = (string)($booking['status'] ?? '');

        $amount = $bookingTotalColumn
            ? (float)($booking[$bookingTotalColumn] ?? 0)
            : 0;

        if (in_array($status, $paidTourStatuses, true)) {
            $tourRevenue += $amount;

            $m = (int)date(
                'n',
                strtotime($booking['created_at'])
            );

            if ($m >= 1 && $m <= 12) {
                $chartTour[$m] += $amount;
            }

            $id = (int)$booking['tour_id'];

            if (!isset($tourReport[$id])) {
                $tourReport[$id] = [
                    'name' => $booking['report_tour_name']
                        ?: 'Tour #' . $id,
                    'bookings' => 0,
                    'people' => 0,
                    'revenue' => 0
                ];
            }

            $tourReport[$id]['bookings']++;

            if ($bookingPeopleColumn) {
                $tourReport[$id]['people'] +=
                    (int)($booking[$bookingPeopleColumn] ?? 0);
            }

            $tourReport[$id]['revenue'] += $amount;

        } elseif ($status !== 'Đã hủy') {
            $processingValue += $amount;
        }
    }

} catch (Throwable $e) {
    error_log('Revenue bookings: ' . $e->getMessage());
    $errors[] = 'Không thể tải dữ liệu doanh thu tour.';
}

// =========================================
// DOANH THU DON HANG
// =========================================

try {
    $orders = rvRows(
        $pdo,
        "
        SELECT *
        FROM orders
        WHERE created_at >= ?
          AND created_at < ?
        ORDER BY created_at DESC
        ",
        [$startDate, $endDate]
    );

    foreach ($orders as $order) {
        $amount = $orderTotalColumn
            ? (float)($order[$orderTotalColumn] ?? 0)
            : 0;

        $status = (string)($order['status'] ?? '');

        if (in_array(
            $status,
            $completedOrderStatuses,
            true
        )) {
            $shopRevenue += $amount;

            $m = (int)date(
                'n',
                strtotime($order['created_at'])
            );

            if ($m >= 1 && $m <= 12) {
                $chartShop[$m] += $amount;
            }

        } elseif ($status !== 'Đã hủy') {
            $processingValue += $amount;
        }
    }

} catch (Throwable $e) {
    error_log('Revenue orders: ' . $e->getMessage());
    $errors[] = 'Không thể tải dữ liệu đơn hàng.';
}

// =========================================
// TEN SAN PHAM + SO LUONG
// =========================================

// Lay cac dong chi tiet don hang.
// Khong nhan ban ghi order khi tinh doanh thu.

try {
    if (in_array('order_id', $detailCols, true)) {
        $productJoin = '';
        $nameParts = [];

        if (
            in_array('product_id', $detailCols, true) &&
            $productNameColumn
        ) {
            $productJoin = "
                LEFT JOIN products p
                    ON p.id = d.product_id
            ";

            $nameParts[] = "NULLIF(p.`$productNameColumn`, '')";
        }

        if ($detailNameColumn) {
            $nameParts[] = "NULLIF(d.`$detailNameColumn`, '')";
        }

        $nameParts[] = "'Sản phẩm không còn trong danh mục'";

        $nameExpression = 'COALESCE(' .
            implode(', ', $nameParts) . ')';

        $quantityExpression = $quantityColumn
            ? "d.`$quantityColumn`"
            : "NULL";

        $items = rvRows(
            $pdo,
            "
            SELECT
                d.order_id,
                $nameExpression AS item_name,
                $quantityExpression AS item_quantity
            FROM order_details d
            $productJoin
            INNER JOIN orders o ON o.id = d.order_id
            WHERE o.created_at >= ?
              AND o.created_at < ?
            ORDER BY d.order_id, d.id
            ",
            [$startDate, $endDate]
        );

        foreach ($items as $item) {
            $orderId = (int)$item['order_id'];

            if (!isset($orderProducts[$orderId])) {
                $orderProducts[$orderId] = [];
            }

            $orderProducts[$orderId][] = [
                'name' => $item['item_name'],
                'quantity' => $item['item_quantity'] === null
                    ? null
                    : (int)$item['item_quantity']
            ];
        }
    }

} catch (Throwable $e) {
    error_log('Revenue products: ' . $e->getMessage());
    $errors[] = 'Không thể tải chi tiết sản phẩm trong đơn hàng.';
}

$totalRevenue = $tourRevenue + $shopRevenue;

// =========================================
// BIEU DO MOT COT TONG
// =========================================

$chartLabels = [];
$chartValues = [];

for ($i = 1; $i <= 12; $i++) {
    $chartLabels[] = 'Tháng ' . $i;

    if ($chartSource === 'tour') {
        $chartValues[] = $chartTour[$i];
    } elseif ($chartSource === 'shop') {
        $chartValues[] = $chartShop[$i];
    } else {
        $chartValues[] = $chartTour[$i] + $chartShop[$i];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= rvH($page_title) ?></title>

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
* {
    box-sizing: border-box;
}

.rv-main {
    flex: 1 1 auto;
    min-width: 0;
    background: #f1f5f9;
    color: #122544;
}

.rv-head {
    padding: 22px 27px;
    background: white;
    border-bottom: 1px solid #e2e8f0;
}

.rv-head h1 {
    margin: 0;
    color: #102d50;
    font-size: 20px;
    font-weight: 800;
}

.rv-body {
    max-width: 1800px;
    margin: auto;
    padding: 24px;
}

.rv-panel {
    padding: 23px;
    margin-bottom: 22px;
    border: 1px solid #dfe7f1;
    border-radius: 17px;
    background: white;
}

.rv-panel h2 {
    margin: 0 0 20px;
    color: #10213f;
    font-size: 19px;
    font-weight: 800;
}

.rv-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 13px;
}

.rv-field {
    flex: 1 1 145px;
    min-width: 135px;
}

.rv-field label {
    display: block;
    margin-bottom: 7px;
    color: #475569;
    font-size: 12px;
    font-weight: 800;
}

.rv-field select {
    width: 100%;
    height: 43px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    font: inherit;
    font-size: 13px;
}

.rv-submit {
    height: 43px;
    padding: 0 22px;
    border: 0;
    border-radius: 9px;
    background: #166534;
    color: white;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.rv-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 17px;
    margin-bottom: 22px;
}

.rv-stat {
    min-width: 0;
    padding: 23px;
    border: 1px solid #dfe7f1;
    border-radius: 16px;
    background: white;
}

.rv-stat-title {
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.rv-stat-value {
    margin-top: 15px;
    color: #ea580c;
    font-size: clamp(21px,2vw,29px);
    font-weight: 900;
    overflow-wrap: anywhere;
}

.rv-stat-value.green {
    color: #166534;
}

.rv-stat-value.blue {
    color: #0284c7;
}

.rv-muted {
    color: #64748b;
    font-size: 12px;
}

.rv-chart-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}

.rv-chart-head h2 {
    margin-bottom: 0;
}

.rv-chart-controls label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 800;
}

.rv-chart-controls select {
    min-width: 190px;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
}

.rv-chart-box {
    position: relative;
    width: 100%;
    height: 355px;
}

.rv-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.rv-table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
}

.rv-table th,
.rv-table td {
    padding: 14px 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}

.rv-table th {
    background: #f8fafc;
    color: #10213f;
    font-weight: 800;
    white-space: nowrap;
}

.rv-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.rv-money {
    color: #ea580c;
    font-weight: 900;
    white-space: nowrap;
}

/* ==================================
   TEN SAN PHAM - KHONG DAU CHAM
   ================================== */

.rv-product-line {
    display: block;
    padding: 4px 0;
    color: #153358;
    font-size: 13px;
    line-height: 1.5;
}

.rv-product-line + .rv-product-line {
    border-top: 1px solid #edf2f7;
}

/* ==================================
   SO LUONG LA COT RIENG
   ================================== */

.rv-quantity-line {
    display: block;
    padding: 4px 0;
    color: #166534;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.5;
}

.rv-quantity-line + .rv-quantity-line {
    border-top: 1px solid #edf2f7;
}

.rv-quantity-cell {
    text-align: center !important;
}

.rv-quantity-head {
    text-align: center !important;
}

.rv-empty {
    padding: 30px !important;
    color: #64748b;
    text-align: center !important;
    font-size: 13px;
}

.rv-warning {
    padding: 13px;
    margin-bottom: 18px;
    border-radius: 10px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 13px;
}

@media(max-width:1250px) {
    .rv-stats {
        grid-template-columns: repeat(2, minmax(0,1fr));
    }
}

@media(max-width:700px) {
    .rv-body {
        padding: 12px;
    }

    .rv-panel {
        padding: 15px;
    }

    .rv-stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="admin-body">

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main rv-main">

<header class="rv-head">
    <h1>
        <i class="fa-solid fa-chart-line"></i>
        DOANH THU & BÁO CÁO
    </h1>
</header>

<div class="rv-body">

    <?php foreach ($errors as $error): ?>
        <div class="rv-warning">
            <?= rvH($error) ?>
        </div>
    <?php endforeach; ?>

    <!-- BO LOC -->

    <section class="rv-panel">

        <form method="GET" class="rv-filters">

            <div class="rv-field">
                <label>Năm</label>

                <select name="year">
                    <?php for (
                        $y = 2025;
                        $y <= max(2030, (int)date('Y') + 2);
                        $y++
                    ): ?>
                        <option
                            value="<?= $y ?>"
                            <?= $year === $y ? 'selected' : '' ?>
                        >
                            <?= $y ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="rv-field">
                <label>Tháng</label>

                <select name="month">
                    <option value="0">Cả năm</option>

                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option
                            value="<?= $m ?>"
                            <?= $month === $m ? 'selected' : '' ?>
                        >
                            Tháng <?= $m ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="rv-field">
                <label>Nguồn doanh thu</label>

                <select name="source">
                    <option value="all"
                        <?= $source === 'all' ? 'selected' : '' ?>>
                        Tất cả
                    </option>

                    <option value="tour"
                        <?= $source === 'tour' ? 'selected' : '' ?>>
                        Tour trekking
                    </option>

                    <option value="shop"
                        <?= $source === 'shop' ? 'selected' : '' ?>>
                        Bán dụng cụ
                    </option>
                </select>
            </div>

            <div class="rv-field">
                <label>Tour</label>

                <select name="tour_id">
                    <option value="0">Tất cả tour</option>

                    <?php foreach ($tours as $tour): ?>
                        <option
                            value="<?= (int)$tour['id'] ?>"
                            <?= $filterTour === (int)$tour['id']
                                ? 'selected' : '' ?>
                        >
                            <?= rvH($tour['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" name="chart"
                   value="<?= rvH($chartSource) ?>">

            <button class="rv-submit" type="submit">
                <i class="fa-solid fa-filter"></i>
                Lọc dữ liệu
            </button>

        </form>
    </section>

    <!-- THONG KE -->

    <section class="rv-stats">

        <div class="rv-stat">
            <div class="rv-stat-title">
                Tổng doanh thu hoàn thành
            </div>

            <div class="rv-stat-value">
                <?= rvH(rvMoney($totalRevenue)) ?>
            </div>
        </div>

        <div class="rv-stat">
            <div class="rv-stat-title">
                Doanh thu tour
            </div>

            <div class="rv-stat-value green">
                <?= rvH(rvMoney($tourRevenue)) ?>
            </div>
        </div>

        <div class="rv-stat">
            <div class="rv-stat-title">
                Doanh thu bán hàng
            </div>

            <div class="rv-stat-value blue">
                <?= rvH(rvMoney($shopRevenue)) ?>
            </div>
        </div>

        <div class="rv-stat">
            <div class="rv-stat-title">
                Giá trị đơn đang xử lý
            </div>

            <div class="rv-stat-value">
                <?= rvH(rvMoney($processingValue)) ?>
            </div>

            <div class="rv-muted">
                Không tính vào doanh thu hoàn thành.
            </div>
        </div>

    </section>

    <!-- BIEU DO -->

    <section class="rv-panel">

        <div class="rv-chart-head">
            <h2>
                <i class="fa-solid fa-chart-column"></i>
                Biểu đồ doanh thu năm <?= $year ?>
            </h2>

            <div class="rv-chart-controls">
                <label>Hiển thị biểu đồ</label>

                <select id="rvChartSource">
                    <option value="all"
                        <?= $chartSource === 'all' ? 'selected' : '' ?>>
                        Tất cả
                    </option>

                    <option value="tour"
                        <?= $chartSource === 'tour' ? 'selected' : '' ?>>
                        Doanh thu Tour
                    </option>

                    <option value="shop"
                        <?= $chartSource === 'shop' ? 'selected' : '' ?>>
                        Doanh thu Bán Hàng
                    </option>
                </select>
            </div>
        </div>

        <div class="rv-chart-box">
            <canvas id="rvRevenueChart"></canvas>
        </div>

    </section>

    <!-- DOANH THU TOUR -->

    <?php if ($source !== 'shop'): ?>

        <section class="rv-panel">

            <h2>
                <i class="fa-solid fa-person-hiking"></i>
                Doanh thu tour trekking
            </h2>

            <div class="rv-table-wrap">
                <table class="rv-table" style="min-width:760px">

                    <thead>
                        <tr>
                            <th>Tên Tour</th>
                            <th>Lượt đăng ký</th>
                            <th>Số khách</th>
                            <th>Doanh thu</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($tourReport as $tour): ?>
                        <tr>
                            <td><?= rvH($tour['name']) ?></td>

                            <td>
                                <?= (int)$tour['bookings'] ?>
                            </td>

                            <td>
                                <?= (int)$tour['people'] ?>
                            </td>

                            <td class="rv-money">
                                <?= rvH(rvMoney($tour['revenue'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$tourReport): ?>
                        <tr>
                            <td colspan="4" class="rv-empty">
                                Chưa có doanh thu tour hoàn thành.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </section>

    <?php endif; ?>

    <!-- DOANH THU DON HANG -->

    <?php if ($source !== 'tour'): ?>

        <section class="rv-panel">

            <h2>
                <i class="fa-solid fa-box"></i>
                Doanh thu đơn hàng
            </h2>

            <div class="rv-table-wrap">

                <table class="rv-table">

                    <!-- CHIA LAI 6 COT -->

                    <colgroup>
                        <col style="width:16%">
                        <col style="width:30%">
                        <col style="width:9%">
                        <col style="width:19%">
                        <col style="width:13%">
                        <col style="width:13%">
                    </colgroup>

                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Tên sản phẩm</th>
                            <th class="rv-quantity-head">
                                Số lượng
                            </th>
                            <th>Khách hàng</th>
                            <th>Doanh thu</th>
                            <th>Ngày tạo</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    $hasCompletedOrders = false;

                    foreach ($orders as $order):

                        if (!in_array(
                            (string)($order['status'] ?? ''),
                            $completedOrderStatuses,
                            true
                        )) {
                            continue;
                        }

                        $hasCompletedOrders = true;

                        $orderId = (int)$order['id'];

                        $products = $orderProducts[$orderId] ?? [];
                    ?>

                        <tr>

                            <!-- MA DON -->

                            <td>
                                <?= rvH(
                                    $order['order_code'] ??
                                    'Đơn #' . $orderId
                                ) ?>
                            </td>

                            <!-- TEN SAN PHAM
                                 KHONG DAU CHAM
                                 KHONG NHAN SO LUONG -->

                            <td>

                                <?php if ($products): ?>

                                    <?php foreach ($products as $item): ?>

                                        <div class="rv-product-line">
                                            <?= rvH($item['name']) ?>
                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <span class="rv-muted">
                                        Chưa có chi tiết sản phẩm
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- COT SO LUONG RIENG -->

                            <td class="rv-quantity-cell">

                                <?php if ($products): ?>

                                    <?php foreach ($products as $item): ?>

                                        <div class="rv-quantity-line">
                                            <?= $item['quantity'] === null
                                                ? '—'
                                                : (int)$item['quantity'] ?>
                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <span class="rv-muted">—</span>

                                <?php endif; ?>

                            </td>

                            <!-- KHACH HANG -->

                            <td>
                                <?= rvH(
                                    $order['fullname'] ??
                                    $order['customer_name'] ??
                                    'Khách hàng'
                                ) ?>
                            </td>

                            <!-- DOANH THU -->

                            <td class="rv-money">
                                <?= rvH(
                                    rvMoney(
                                        $orderTotalColumn
                                            ? ($order[$orderTotalColumn] ?? 0)
                                            : 0
                                    )
                                ) ?>
                            </td>

                            <!-- NGAY TAO -->

                            <td>
                                <?= rvH(
                                    rvDate(
                                        $order['created_at'] ?? ''
                                    )
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$hasCompletedOrders): ?>
                        <tr>
                            <td colspan="6" class="rv-empty">
                                Chưa có đơn hàng hoàn thành
                                trong khoảng thời gian này.
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>

</div>
</main>

<script>
(function() {

    const select = document.getElementById(
        'rvChartSource'
    );

    const chartTour = <?= json_encode(
        array_values($chartTour),
        JSON_NUMERIC_CHECK
    ) ?>;

    const chartShop = <?= json_encode(
        array_values($chartShop),
        JSON_NUMERIC_CHECK
    ) ?>;

    const labels = <?= json_encode(
        $chartLabels,
        JSON_UNESCAPED_UNICODE
    ) ?>;

    function getData(source) {
        if (source === 'tour') {
            return chartTour;
        }

        if (source === 'shop') {
            return chartShop;
        }

        return chartTour.map(function(value, index) {
            return value + chartShop[index];
        });
    }

    function getLabel(source) {
        if (source === 'tour') {
            return 'Doanh thu Tour';
        }

        if (source === 'shop') {
            return 'Doanh thu Bán Hàng';
        }

        return 'Tổng doanh thu';
    }


    function getDatasets(source) {
        const common = {stack: 'revenue', maxBarThickness: 50};
        if (source === 'all') return [
            {...common, label: 'Doanh thu Tour', data: chartTour, backgroundColor: '#166534'},
            {...common, label: 'Doanh thu Đơn hàng', data: chartShop, backgroundColor: '#ea580c'}
        ];
        return [{...common, label: getLabel(source), data: getData(source), backgroundColor: source === 'tour' ? '#166534' : '#ea580c', borderRadius: 5}];
    }

    const canvas = document.getElementById(
        'rvRevenueChart'
    );

    if (
        !canvas ||
        !select ||
        typeof Chart === 'undefined'
    ) {
        return;
    }

    const chart = new Chart(canvas, {
        type: 'bar',

        data: {
            labels: labels,

            datasets: getDatasets(select.value)
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },

                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return (
                                context.dataset.label +
                                ': ' +
                                new Intl.NumberFormat('vi-VN')
                                    .format(context.parsed.y) +
                                ' đ'
                            );
                        }
                    }
                }
            },

            scales: {
                x: { stacked: true },
                y: {
                    stacked: true,
                    beginAtZero: true,

                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat(
                                'vi-VN'
                            ).format(value);
                        }
                    }
                }
            }
        }
    });

    select.addEventListener('change', function() {
        chart.data.datasets = getDatasets(select.value);
        chart.update();
    });

})();
</script>

</body>
</html>
