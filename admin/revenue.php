<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

$current_page = 'revenue';
$page_title = 'Doanh Thu & Báo Cáo - VNN Travel';

if (!function_exists('revH')) {
    function revH($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('revMoney')) {
    function revMoney($number) {
        return number_format((float)$number, 0, ',', '.') . ' đ';
    }
}

if (!function_exists('revRows')) {
    function revRows(PDO $pdo, string $sql, array $params = []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('revColumns')) {
    function revColumns(PDO $pdo, string $table) {
        $allowed = [
            'orders', 'order_details', 'products',
            'tour_bookings', 'tours'
        ];

        if (!in_array($table, $allowed, true)) return [];

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

if (!function_exists('revColumn')) {
    function revColumn(array $columns, array $candidates) {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('revDate')) {
    function revDate($date) {
        $time = strtotime((string)$date);
        return $time ? date('d/m/Y H:i', $time) : '—';
    }
}

// =======================================
// BO LOC
// =======================================

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

$filterTour = filter_input(INPUT_GET, 'tour_id', FILTER_VALIDATE_INT);
$filterTour = $filterTour && $filterTour > 0 ? $filterTour : 0;

$startDate = sprintf('%04d-01-01 00:00:00', $year);
$endDate = sprintf('%04d-01-01 00:00:00', $year + 1);

if ($month) {
    $startDate = sprintf('%04d-%02d-01 00:00:00', $year, $month);

    $next = new DateTimeImmutable($startDate);
    $endDate = $next->modify('+1 month')->format('Y-m-d H:i:s');
}

// =======================================
// DOC CAU TRUC DATABASE
// =======================================

$orderCols = revColumns($pdo, 'orders');
$detailCols = revColumns($pdo, 'order_details');
$productCols = revColumns($pdo, 'products');
$bookingCols = revColumns($pdo, 'tour_bookings');

$detailQuantity = revColumn(
    $detailCols,
    ['quantity', 'qty', 'number', 'amount']
);

$productName = revColumn(
    $productCols,
    ['name', 'product_name', 'title']
);

$detailName = revColumn(
    $detailCols,
    ['product_name', 'name', 'title']
);

$bookingPeople = revColumn(
    $bookingCols,
    ['number_people', 'number_of_people', 'num_people',
     'total_people', 'people_count', 'quantity']
);

$bookingTotal = revColumn(
    $bookingCols,
    ['total_price', 'total_amount', 'amount']
);

$orderTotal = revColumn(
    $orderCols,
    ['total_amount', 'total_price', 'amount']
);

// =======================================
// DU LIEU MAC DINH
// =======================================

$tours = [];
$tourBookings = [];
$orders = [];
$orderProducts = [];

$tourRevenue = 0;
$shopRevenue = 0;
$processingValue = 0;

$chartTour = array_fill(1, 12, 0);
$chartShop = array_fill(1, 12, 0);

$tourReport = [];
$errors = [];

// Trang thai da hoan tat.
// Doanh thu khong bao gom don dang cho xu ly.
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

// =======================================
// LAY DANH SACH TOUR
// =======================================

try {
    $tours = revRows(
        $pdo,
        "SELECT id, title FROM tours ORDER BY title"
    );
} catch (Throwable $e) {
    error_log('Revenue tours: ' . $e->getMessage());
}

// =======================================
// DOANH THU TOUR
// =======================================

try {
    $sql = "
        SELECT
            b.*,
            t.title AS report_tour_name
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

    $tourBookings = revRows($pdo, $sql, $params);

    foreach ($tourBookings as $booking) {
        $status = (string)($booking['status'] ?? '');
        $amount = $bookingTotal
            ? (float)($booking[$bookingTotal] ?? 0)
            : 0;

        $isPaid = in_array($status, $paidTourStatuses, true);

        if ($isPaid) {
            $tourRevenue += $amount;

            $m = (int)date(
                'n',
                strtotime($booking['created_at'])
            );

            if ($m >= 1 && $m <= 12) {
                $chartTour[$m] += $amount;
            }

            $tourId = (int)$booking['tour_id'];

            if (!isset($tourReport[$tourId])) {
                $tourReport[$tourId] = [
                    'name' => $booking['report_tour_name'] ?: 'Tour #' . $tourId,
                    'bookings' => 0,
                    'people' => 0,
                    'revenue' => 0
                ];
            }

            $tourReport[$tourId]['bookings']++;

            if ($bookingPeople) {
                $tourReport[$tourId]['people'] +=
                    (int)($booking[$bookingPeople] ?? 0);
            }

            $tourReport[$tourId]['revenue'] += $amount;

        } elseif ($status !== 'Đã hủy') {
            $processingValue += $amount;
        }
    }

} catch (Throwable $e) {
    error_log('Revenue bookings: ' . $e->getMessage());
    $errors[] = 'Không thể tải dữ liệu doanh thu tour.';
}

// =======================================
// DOANH THU DON HANG
// =======================================

try {
    $orders = revRows(
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
        $amount = $orderTotal
            ? (float)($order[$orderTotal] ?? 0)
            : 0;

        if (in_array(
            (string)$order['status'],
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

        } elseif (($order['status'] ?? '') !== 'Đã hủy') {
            $processingValue += $amount;
        }
    }

} catch (Throwable $e) {
    error_log('Revenue orders: ' . $e->getMessage());
    $errors[] = 'Không thể tải dữ liệu đơn hàng.';
}

// =======================================
// LAY TEN SAN PHAM THEO TUNG DON
// =======================================

// Quan trong:
// Mot don co nhieu san pham.
// Truy van tat ca chi tiet mot lan,
// sau do gom theo order_id.
// Khong JOIN truc tiep vao truy van tinh doanh thu,
// tranh nhan doi tong tien don hang.

try {
    if (
        $detailCols &&
        in_array('order_id', $detailCols, true)
    ) {
        $selectName = "'Sản phẩm không còn trong danh mục'";

        $joinProduct = '';

        if (
            in_array('product_id', $detailCols, true) &&
            $productName
        ) {
            $joinProduct = "
                LEFT JOIN products p
                    ON p.id = d.product_id
            ";

            $selectName = "p.`$productName`";
        }

        $fallbackName = $detailName
            ? "d.`$detailName`"
            : "'Sản phẩm không còn trong danh mục'";

        $quantitySql = $detailQuantity
            ? "d.`$detailQuantity`"
            : "1";

        $productRows = revRows(
            $pdo,
            "
            SELECT
                d.order_id,
                COALESCE(
                    NULLIF($selectName, ''),
                    NULLIF($fallbackName, ''),
                    'Sản phẩm không còn trong danh mục'
                ) AS display_product_name,
                $quantitySql AS display_quantity
            FROM order_details d
            $joinProduct
            INNER JOIN orders o
                ON o.id = d.order_id
            WHERE o.created_at >= ?
              AND o.created_at < ?
            ORDER BY d.order_id, d.id
            ",
            [$startDate, $endDate]
        );

        foreach ($productRows as $row) {
            $orderId = (int)$row['order_id'];

            if (!isset($orderProducts[$orderId])) {
                $orderProducts[$orderId] = [];
            }

            $orderProducts[$orderId][] = [
                'name' => $row['display_product_name'],
                'quantity' => (int)$row['display_quantity']
            ];
        }
    }

} catch (Throwable $e) {
    error_log('Revenue product names: ' . $e->getMessage());
    $errors[] = 'Chưa tải được tên sản phẩm trong đơn hàng.';
}

$totalRevenue = $tourRevenue + $shopRevenue;

// =======================================
// BIEU DO
// "TAT CA" = MOT COT TONG / THANG
// =======================================

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

$baseUrl = rtrim(BASE_URL, '/') . '/';

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= revH($page_title) ?></title>

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
    padding: 23px 28px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}

.rv-head h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 900;
    color: #102d50;
}

.rv-body {
    padding: 24px;
    max-width: 1800px;
    margin: auto;
}

.rv-panel {
    padding: 23px;
    margin-bottom: 22px;
    background: white;
    border: 1px solid #dfe7f1;
    border-radius: 17px;
}

.rv-panel h2 {
    margin: 0 0 20px;
    color: #10213f;
    font-size: 19px;
    font-weight: 800;
}

.rv-filters {
    display: flex;
    gap: 13px;
    align-items: end;
    flex-wrap: wrap;
}

.rv-field {
    flex: 1 1 145px;
    min-width: 135px;
}

.rv-field label {
    display: block;
    margin-bottom: 7px;
    font-size: 12px;
    font-weight: 800;
    color: #475569;
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
    padding: 23px;
    border: 1px solid #dfe7f1;
    border-radius: 16px;
    background: white;
}

.rv-stat-title {
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
}

.rv-stat-value {
    margin-top: 15px;
    font-size: clamp(21px,2vw,29px);
    font-weight: 900;
    color: #ea580c;
    overflow-wrap: anywhere;
}

.rv-stat-value.green {
    color: #166534;
}

.rv-stat-value.blue {
    color: #0284c7;
}

.rv-table-wrap {
    overflow-x: auto;
}

.rv-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

.rv-table th,
.rv-table td {
    padding: 14px 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: middle;
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

.rv-product {
    margin: 0 0 7px;
    padding-left: 12px;
    position: relative;
    color: #153358;
    font-size: 13px;
    line-height: 1.55;
}

.rv-product:last-child {
    margin-bottom: 0;
}

.rv-product:before {
    content: "•";
    position: absolute;
    left: 0;
    color: #166534;
}

.rv-qty {
    font-weight: 800;
    color: #166534;
    white-space: nowrap;
}

.rv-muted {
    color: #64748b;
    font-size: 12px;
}

.rv-chart-head {
    display: flex;
    justify-content: space-between;
    align-items: end;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}

.rv-chart-head h2 {
    margin-bottom: 0;
}

.rv-chart-box {
    position: relative;
    width: 100%;
    height: 355px;
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
    border-radius: 9px;
    border: 1px solid #cbd5e1;
    background: white;
}

.rv-empty {
    padding: 30px;
    color: #64748b;
    text-align: center;
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
        grid-template-columns: repeat(2,minmax(0,1fr));
    }
}

@media(max-width:700px) {
    .rv-body {
        padding: 12px;
    }

    .rv-stats {
        grid-template-columns: 1fr;
    }

    .rv-panel {
        padding: 15px;
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
                <?= revH($error) ?>
            </div>
        <?php endforeach; ?>

        <!-- BO LOC -->

        <section class="rv-panel">
            <form method="GET" class="rv-filters">

                <div class="rv-field">
                    <label>Năm</label>

                    <select name="year">
                        <?php for ($y = 2025; $y <= max(2030, (int)date('Y') + 2); $y++): ?>
                            <option value="<?= $y ?>"
                                <?= $year === $y ? 'selected' : '' ?>>
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
                            <option value="<?= $m ?>"
                                <?= $month === $m ? 'selected' : '' ?>>
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
                            <option value="<?= (int)$tour['id'] ?>"
                                <?= $filterTour === (int)$tour['id']
                                    ? 'selected' : '' ?>>
                                <?= revH($tour['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" name="chart"
                       value="<?= revH($chartSource) ?>">

                <button class="rv-submit" type="submit">
                    <i class="fa-solid fa-filter"></i>
                    Lọc dữ liệu
                </button>
            </form>
        </section>

        <!-- TONG QUAN -->

        <section class="rv-stats">

            <div class="rv-stat">
                <div class="rv-stat-title">
                    Tổng doanh thu hoàn thành
                </div>

                <div class="rv-stat-value">
                    <?= revH(revMoney($totalRevenue)) ?>
                </div>
            </div>

            <div class="rv-stat">
                <div class="rv-stat-title">
                    Doanh thu tour
                </div>

                <div class="rv-stat-value green">
                    <?= revH(revMoney($tourRevenue)) ?>
                </div>
            </div>

            <div class="rv-stat">
                <div class="rv-stat-title">
                    Doanh thu bán hàng
                </div>

                <div class="rv-stat-value blue">
                    <?= revH(revMoney($shopRevenue)) ?>
                </div>
            </div>

            <div class="rv-stat">
                <div class="rv-stat-title">
                    Giá trị đơn đang xử lý
                </div>

                <div class="rv-stat-value">
                    <?= revH(revMoney($processingValue)) ?>
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

                <table class="rv-table">
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
                            <td><?= revH($tour['name']) ?></td>
                            <td><?= (int)$tour['bookings'] ?></td>
                            <td><?= (int)$tour['people'] ?></td>

                            <td class="rv-money">
                                <?= revH(revMoney($tour['revenue'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$tourReport): ?>
                        <tr>
                            <td colspan="4" class="rv-empty">
                                Chưa có doanh thu tour hoàn thành
                                trong khoảng thời gian này.
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

                    <thead>
                        <tr>
                            <th style="width:17%">Mã đơn</th>

                            <!-- COT MOI -->
                            <th style="width:32%">
                                Sản phẩm đã mua
                            </th>

                            <th style="width:22%">Khách hàng</th>
                            <th style="width:15%">Doanh thu</th>
                            <th style="width:14%">Ngày tạo</th>
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

                            <td>
                                <?= revH(
                                    $order['order_code'] ??
                                    'Đơn #' . $orderId
                                ) ?>
                            </td>

                            <!-- TEN SAN PHAM THAT THEO DON -->

                            <td>

                                <?php if ($products): ?>

                                    <?php foreach ($products as $item): ?>

                                        <div class="rv-product">

                                            <?= revH($item['name']) ?>

                                            <span class="rv-qty">
                                                × <?= (int)$item['quantity'] ?>
                                            </span>

                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <span class="rv-muted">
                                        Chưa có chi tiết sản phẩm
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= revH(
                                    $order['fullname'] ??
                                    $order['customer_name'] ??
                                    'Khách hàng'
                                ) ?>
                            </td>

                            <td class="rv-money">
                                <?= revH(
                                    revMoney(
                                        $orderTotal
                                            ? ($order[$orderTotal] ?? 0)
                                            : 0
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= revH(
                                    revDate($order['created_at'] ?? '')
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$hasCompletedOrders): ?>
                        <tr>
                            <td colspan="5" class="rv-empty">
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

    const select = document.getElementById('rvChartSource');

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

    function dataFor(source) {
        if (source === 'tour') return chartTour;
        if (source === 'shop') return chartShop;

        return chartTour.map(function(value, index) {
            return value + chartShop[index];
        });
    }

    function labelFor(source) {
        if (source === 'tour') return 'Doanh thu Tour';
        if (source === 'shop') return 'Doanh thu Bán Hàng';
        return 'Tổng doanh thu';
    }

    const ctx = document.getElementById('rvRevenueChart');

    if (!ctx || typeof Chart === 'undefined') return;

    const chart = new Chart(ctx, {
        type: 'bar',

        data: {
            labels: labels,

            datasets: [{
                label: labelFor(select.value),
                data: dataFor(select.value),
                backgroundColor: '#ea580c',
                borderRadius: 5,
                maxBarThickness: 50
            }]
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
                            return context.dataset.label + ': ' +
                                new Intl.NumberFormat('vi-VN')
                                    .format(context.parsed.y) + ' đ';
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,

                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('vi-VN')
                                .format(value);
                        }
                    }
                }
            }
        }
    });

    select.addEventListener('change', function() {
        const source = select.value;

        chart.data.datasets[0].label = labelFor(source);
        chart.data.datasets[0].data = dataFor(source);

        chart.data.datasets[0].backgroundColor =
            source === 'tour' ? '#166534' :
            source === 'shop' ? '#0284c7' :
            '#ea580c';

        chart.update();
    });

})();
</script>

</body>
</html>
