<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

$current_page = 'revenue';
$page_title = 'Doanh thu tổng hợp - VNN Travel';

function rh($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function queryRows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function queryNumber(PDO $pdo, string $sql, array $params = []): float
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float) $stmt->fetchColumn();
}

function tableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");

    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function firstColumn(array $columns, array $names): ?string
{
    foreach ($names as $name) {
        if (in_array($name, $columns, true)) {
            return $name;
        }
    }

    return null;
}

// ==================================================
// BO LOC
// ==================================================

$source = (string) ($_GET['source'] ?? 'all');
$chartSource = (string) ($_GET['chart_source'] ?? 'all');

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? 0);

$tourId = max(0, (int) ($_GET['tour_id'] ?? 0));
$categoryId = max(0, (int) ($_GET['category_id'] ?? 0));

if (!in_array($source, ['all', 'tour', 'shop'], true)) {
    $source = 'all';
}

if (!in_array($chartSource, ['all', 'tour', 'shop'], true)) {
    $chartSource = 'all';
}

if ($year < 2020 || $year > 2100) {
    $year = (int) date('Y');
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

// ==================================================
// DU LIEU BO LOC
// ==================================================

$tours = queryRows(
    $pdo,
    "SELECT id, title FROM tours ORDER BY title ASC"
);

$categories = queryRows(
    $pdo,
    "SELECT id, name FROM categories ORDER BY name ASC"
);

$validTourIds = array_map(
    'intval',
    array_column($tours, 'id')
);

$validCategoryIds = array_map(
    'intval',
    array_column($categories, 'id')
);

if (!in_array($tourId, $validTourIds, true)) {
    $tourId = 0;
}

if (!in_array($categoryId, $validCategoryIds, true)) {
    $categoryId = 0;
}

if ($source !== 'tour') {
    $tourId = 0;
}

if ($source !== 'shop') {
    $categoryId = 0;
}

// ==================================================
// TRANG THAI DOANH THU
// ==================================================

// Tour: chi tinh da thanh toan hoac hoan thanh.
$paidTourCondition = "
    b.status IN ('Đã thanh toán', 'Đã hoàn thành')
";

// Cua hang: chi tinh don da giao.
$paidShopCondition = "
    o.status = 'Đã giao'
";

// ==================================================
// FILTER TOUR
// ==================================================

$tourFilter = '';
$tourParams = [];

if ($tourId > 0) {
    $tourFilter = " AND b.tour_id = ?";
    $tourParams[] = $tourId;
}

// ==================================================
// DOANH THU TOUR HOAN THANH
// ==================================================

$tourRevenue = queryNumber(
    $pdo,
    "
        SELECT COALESCE(SUM(b.total_price), 0)
        FROM tour_bookings b
        WHERE $paidTourCondition
          AND b.created_at >= ?
          AND b.created_at < ?
          $tourFilter
    ",
    array_merge([$from, $to], $tourParams)
);

// ==================================================
// CẤU TRÚC CHI TIẾT ĐƠN HÀNG
// ==================================================

$itemColumns = tableColumns($pdo, 'order_items');

$orderIdColumn = firstColumn(
    $itemColumns,
    ['order_id']
);

$productIdColumn = firstColumn(
    $itemColumns,
    ['product_id']
);

$quantityColumn = firstColumn(
    $itemColumns,
    ['quantity', 'qty']
);

$priceColumn = firstColumn(
    $itemColumns,
    ['price', 'unit_price', 'product_price']
);

$totalColumn = firstColumn(
    $itemColumns,
    ['subtotal', 'total_price', 'line_total', 'total']
);

$categorySupported =
    $orderIdColumn !== null &&
    $productIdColumn !== null &&
    (
        $totalColumn !== null ||
        ($quantityColumn !== null && $priceColumn !== null)
    );

$categoryWarning = '';

if ($categoryId > 0 && !$categorySupported) {
    $categoryWarning =
        'Chưa thể thống kê theo danh mục vì bảng ' .
        'order_items chưa đủ dữ liệu giá bán, số lượng ' .
        'hoặc liên kết sản phẩm.';
}

$lineAmount = '';

if ($categorySupported) {
    $lineAmount = $totalColumn !== null
        ? "oi.`$totalColumn`"
        : "oi.`$quantityColumn` * oi.`$priceColumn`";
}

// ==================================================
// HAM TINH DOANH THU BAN HANG
// ==================================================

function shopRevenue(
    PDO $pdo,
    string $start,
    string $end,
    int $categoryId,
    bool $categorySupported,
    ?string $orderIdColumn,
    ?string $productIdColumn,
    string $lineAmount
): ?float {

    if ($categoryId === 0) {
        return queryNumber(
            $pdo,
            "
                SELECT COALESCE(SUM(o.total_amount), 0)
                FROM orders o
                WHERE o.status = 'Đã giao'
                  AND o.created_at >= ?
                  AND o.created_at < ?
            ",
            [$start, $end]
        );
    }

    if (!$categorySupported) {
        return null;
    }

    return queryNumber(
        $pdo,
        "
            SELECT COALESCE(SUM($lineAmount), 0)
            FROM order_items oi
            INNER JOIN orders o
                ON o.id = oi.`$orderIdColumn`
            INNER JOIN products p
                ON p.id = oi.`$productIdColumn`
            WHERE o.status = 'Đã giao'
              AND o.created_at >= ?
              AND o.created_at < ?
              AND p.category_id = ?
        ",
        [$start, $end, $categoryId]
    );
}

$shopRevenue = shopRevenue(
    $pdo,
    $from,
    $to,
    $categoryId,
    $categorySupported,
    $orderIdColumn,
    $productIdColumn,
    $lineAmount
);

// ==================================================
// TONG DOANH THU
// ==================================================

$displayTour = $source !== 'shop'
    ? $tourRevenue
    : 0;

$displayShop = $source !== 'tour'
    ? $shopRevenue
    : 0;

$totalRevenue = $displayShop === null
    ? null
    : $displayTour + $displayShop;

// ==================================================
// GIA TRI DON DANG XU LY
// ==================================================

// Hien thi rieng, KHONG cong vao doanh thu.
$tourPending = queryNumber(
    $pdo,
    "
        SELECT COALESCE(SUM(b.total_price), 0)
        FROM tour_bookings b
        WHERE b.status IN ('Chờ xác nhận', 'Đã xác nhận')
          AND b.created_at >= ?
          AND b.created_at < ?
          $tourFilter
    ",
    array_merge([$from, $to], $tourParams)
);

$shopPending = queryNumber(
    $pdo,
    "
        SELECT COALESCE(SUM(o.total_amount), 0)
        FROM orders o
        WHERE o.status IN (
            'Chờ xác nhận',
            'Đã xác nhận',
            'Đang giao'
        )
          AND o.created_at >= ?
          AND o.created_at < ?
    ",
    [$from, $to]
);

$pendingTotal = $source === 'tour'
    ? $tourPending
    : (
        $source === 'shop'
            ? $shopPending
            : $tourPending + $shopPending
    );

// ==================================================
// THONG KE TRANG THAI (DE KIEM TRA SO 0)
// ==================================================

$tourStatuses = queryRows(
    $pdo,
    "
        SELECT status, COUNT(*) AS quantity
        FROM tour_bookings
        WHERE created_at >= ?
          AND created_at < ?
        GROUP BY status
        ORDER BY quantity DESC
    ",
    [$from, $to]
);

$orderStatuses = queryRows(
    $pdo,
    "
        SELECT status, COUNT(*) AS quantity
        FROM orders
        WHERE created_at >= ?
          AND created_at < ?
        GROUP BY status
        ORDER BY quantity DESC
    ",
    [$from, $to]
);

// ==================================================
// BIEU DO 12 THANG
// ==================================================

$tourMonths = array_fill(1, 12, 0);
$shopMonths = array_fill(1, 12, 0);

// Tour theo thang
$tourChartRows = queryRows(
    $pdo,
    "
        SELECT
            MONTH(b.created_at) AS month_number,
            COALESCE(SUM(b.total_price), 0) AS revenue
        FROM tour_bookings b
        WHERE $paidTourCondition
          AND b.created_at >= ?
          AND b.created_at < ?
          $tourFilter
        GROUP BY MONTH(b.created_at)
    ",
    array_merge(
        [$yearStart, $yearEnd],
        $tourParams
    )
);

foreach ($tourChartRows as $row) {
    $m = (int) $row['month_number'];

    if ($m >= 1 && $m <= 12) {
        $tourMonths[$m] = (float) $row['revenue'];
    }
}

// Ban hang theo thang
if ($categoryId === 0) {

    $shopChartRows = queryRows(
        $pdo,
        "
            SELECT
                MONTH(o.created_at) AS month_number,
                COALESCE(SUM(o.total_amount), 0) AS revenue
            FROM orders o
            WHERE $paidShopCondition
              AND o.created_at >= ?
              AND o.created_at < ?
            GROUP BY MONTH(o.created_at)
        ",
        [$yearStart, $yearEnd]
    );

    foreach ($shopChartRows as $row) {
        $m = (int) $row['month_number'];

        if ($m >= 1 && $m <= 12) {
            $shopMonths[$m] = (float) $row['revenue'];
        }
    }

} elseif ($categorySupported) {

    for ($m = 1; $m <= 12; $m++) {

        $monthStart = sprintf(
            '%04d-%02d-01',
            $year,
            $m
        );

        $monthEnd = date(
            'Y-m-d',
            strtotime($monthStart . ' +1 month')
        );

        $shopMonths[$m] = shopRevenue(
            $pdo,
            $monthStart,
            $monthEnd,
            $categoryId,
            $categorySupported,
            $orderIdColumn,
            $productIdColumn,
            $lineAmount
        );
    }
}

// ==================================================
// CHI TIET THEO TOUR
// ==================================================

$tourDetails = [];

if ($source !== 'shop') {

    $tourDetails = queryRows(
        $pdo,
        "
            SELECT
                t.title AS tour_name,
                COUNT(b.id) AS booking_count,
                COALESCE(SUM(b.number_people), 0) AS guests,
                COALESCE(SUM(b.total_price), 0) AS revenue
            FROM tour_bookings b
            INNER JOIN tours t
                ON t.id = b.tour_id
            WHERE $paidTourCondition
              AND b.created_at >= ?
              AND b.created_at < ?
              $tourFilter
            GROUP BY t.id, t.title
            ORDER BY revenue DESC
        ",
        array_merge(
            [$from, $to],
            $tourParams
        )
    );
}

// ==================================================
// CHI TIET DON HANG
// ==================================================

$shopDetails = [];

if ($source !== 'tour') {

    if ($categoryId === 0) {

        $shopDetails = queryRows(
            $pdo,
            "
                SELECT
                    o.order_code,
                    o.fullname,
                    o.total_amount AS revenue,
                    o.created_at
                FROM orders o
                WHERE $paidShopCondition
                  AND o.created_at >= ?
                  AND o.created_at < ?
                ORDER BY o.created_at DESC
                LIMIT 200
            ",
            [$from, $to]
        );

    } elseif ($categorySupported) {

        $shopDetails = queryRows(
            $pdo,
            "
                SELECT
                    o.order_code,
                    o.fullname,
                    SUM($lineAmount) AS revenue,
                    o.created_at
                FROM order_items oi
                INNER JOIN orders o
                    ON o.id = oi.`$orderIdColumn`
                INNER JOIN products p
                    ON p.id = oi.`$productIdColumn`
                WHERE $paidShopCondition
                  AND o.created_at >= ?
                  AND o.created_at < ?
                  AND p.category_id = ?
                GROUP BY
                    o.id,
                    o.order_code,
                    o.fullname,
                    o.created_at
                ORDER BY o.created_at DESC
                LIMIT 200
            ",
            [$from, $to, $categoryId]
        );
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= rh($page_title) ?></title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

    <style>
        .revenue-page {
            min-width: 0;
            background: #f1f5f9;
        }

        .revenue-box {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .revenue-box h2 {
            font-size: 20px;
            color: #0f172a;
            margin: 0 0 20px;
        }

        .revenue-filters {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
        }

        .revenue-field label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #334155;
            margin-bottom: 8px;
        }

        .revenue-field select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            font: inherit;
        }

        .revenue-btn {
            padding: 12px 20px;
            border: 0;
            border-radius: 9px;
            background: #166534;
            color: white;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 18px;
        }

        .revenue-btn:hover {
            background: #15803d;
        }

        .revenue-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .revenue-stat {
            padding: 22px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            min-width: 0;
        }

        .revenue-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .revenue-value {
            margin-top: 15px;
            font-size: 26px;
            font-weight: 900;
            color: #166534;
            overflow-wrap: anywhere;
        }

        .revenue-value.orange {
            color: #ea580c;
        }

        .revenue-value.blue {
            color: #0284c7;
        }

        .revenue-value.yellow {
            color: #d97706;
        }

        .revenue-chart-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .revenue-chart-toolbar h2 {
            margin: 0;
        }

        .revenue-chart-select {
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            min-width: 180px;
            font: inherit;
        }

        .revenue-chart {
            position: relative;
            height: 350px;
        }

        .revenue-table-scroll {
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

        .revenue-notice {
            padding: 14px 17px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 13px;
            line-height: 1.7;
        }

        .revenue-note {
            color: #64748b;
            font-size: 13px;
            line-height: 1.8;
        }

        .revenue-status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .revenue-status-item {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        @media (max-width: 1200px) {
            .revenue-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {
            .revenue-filters,
            .revenue-status-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 550px) {
            .revenue-stats {
                grid-template-columns: 1fr;
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

    <div class="admin-content">

        <!-- BO LOC -->

        <section class="revenue-box">
            <h2>Bộ lọc doanh thu</h2>

            <form method="GET" id="revenueFilterForm">

                <div class="revenue-filters">

                    <div class="revenue-field">
                        <label for="source">
                            Nguồn doanh thu
                        </label>

                        <select
                            id="source"
                            name="source"
                            onchange="this.form.submit()"
                        >
                            <option
                                value="all"
                                <?= $source === 'all' ? 'selected' : '' ?>
                            >
                                Tất cả
                            </option>

                            <option
                                value="tour"
                                <?= $source === 'tour' ? 'selected' : '' ?>
                            >
                                Tour
                            </option>

                            <option
                                value="shop"
                                <?= $source === 'shop' ? 'selected' : '' ?>
                            >
                                Bán hàng
                            </option>
                        </select>
                    </div>

                    <div class="revenue-field">
                        <label for="month">
                            Tháng
                        </label>

                        <select name="month" id="month">
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

                    <div class="revenue-field">
                        <label for="year">
                            Năm
                        </label>

                        <select name="year" id="year">
                            <?php for ($y = 2020; $y <= 2100; $y++): ?>
                                <?php if ($y <= (int) date('Y') + 1 || $y === $year): ?>
                                    <option
                                        value="<?= $y ?>"
                                        <?= $year === $y ? 'selected' : '' ?>
                                    >
                                        <?= $y ?>
                                    </option>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <?php if ($source === 'tour'): ?>
                        <div class="revenue-field">
                            <label for="tour_id">
                                Lọc theo từng tour
                            </label>

                            <select name="tour_id" id="tour_id">
                                <option value="0">Tất cả tour</option>

                                <?php foreach ($tours as $tour): ?>
                                    <option
                                        value="<?= (int) $tour['id'] ?>"
                                        <?= $tourId === (int) $tour['id']
                                            ? 'selected' : '' ?>
                                    >
                                        <?= rh($tour['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($source === 'shop'): ?>
                        <div class="revenue-field">
                            <label for="category_id">
                                Danh mục hàng
                            </label>

                            <select name="category_id" id="category_id">
                                <option value="0">Tất cả danh mục</option>

                                <?php foreach ($categories as $category): ?>
                                    <option
                                        value="<?= (int) $category['id'] ?>"
                                        <?= $categoryId === (int) $category['id']
                                            ? 'selected' : '' ?>
                                    >
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
            <div class="revenue-notice">
                <?= rh($categoryWarning) ?>
            </div>
        <?php endif; ?>

        <!-- THE THONG KE -->

        <div class="revenue-stats">

            <div class="revenue-stat">
                <div class="revenue-label">
                    Tổng doanh thu hoàn thành
                </div>

                <div class="revenue-value orange">
                    <?= $totalRevenue === null
                        ? 'Chưa đủ dữ liệu'
                        : format_price($totalRevenue) ?>
                </div>
            </div>

            <div class="revenue-stat">
                <div class="revenue-label">
                    Doanh thu Tour
                </div>

                <div class="revenue-value">
                    <?= format_price($displayTour) ?>
                </div>
            </div>

            <div class="revenue-stat">
                <div class="revenue-label">
                    Doanh thu Bán Hàng
                </div>

                <div class="revenue-value blue">
                    <?= $displayShop === null
                        ? 'Chưa đủ dữ liệu'
                        : format_price($displayShop) ?>
                </div>
            </div>

            <div class="revenue-stat">
                <div class="revenue-label">
                    Giá trị đơn đang xử lý
                </div>

                <div class="revenue-value yellow">
                    <?= format_price($pendingTotal) ?>
                </div>

                <div class="revenue-note">
                    Chưa tính vào doanh thu đã hoàn thành.
                </div>
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
                    <label for="chartSource">
                        Hiển thị biểu đồ
                    </label>

                    <select
                        id="chartSource"
                        class="revenue-chart-select"
                    >
                        <option value="all">
                            Tất cả
                        </option>

                        <option value="tour">
                            Chỉ Tour
                        </option>

                        <option value="shop">
                            Chỉ Bán Hàng
                        </option>
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
                Nếu doanh thu bằng 0, xem trạng thái bên dưới
                để biết giao dịch chưa hoàn thành hay chưa có
                trong khoảng thời gian đã chọn.
            </p>

            <div class="revenue-status-grid">

                <div>
                    <h3>Đăng ký Tour</h3>

                    <?php foreach ($tourStatuses as $row): ?>
                        <div class="revenue-status-item">
                            <span><?= rh($row['status']) ?></span>
                            <strong><?= (int) $row['quantity'] ?></strong>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$tourStatuses): ?>
                        <p class="revenue-note">
                            Chưa có đăng ký trong kỳ.
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <h3>Đơn hàng</h3>

                    <?php foreach ($orderStatuses as $row): ?>
                        <div class="revenue-status-item">
                            <span><?= rh($row['status']) ?></span>
                            <strong><?= (int) $row['quantity'] ?></strong>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$orderStatuses): ?>
                        <p class="revenue-note">
                            Chưa có đơn hàng trong kỳ.
                        </p>
                    <?php endif; ?>
                </div>

            </div>

        </section>

        <!-- CHI TIET TOUR -->

        <?php if ($source !== 'shop'): ?>

            <section class="revenue-box">

                <h2>Doanh thu theo từng Tour</h2>

                <div class="revenue-table-scroll">
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

                                    <td>
                                        <?= (int) $row['booking_count'] ?>
                                    </td>

                                    <td>
                                        <?= (int) $row['guests'] ?>
                                    </td>

                                    <td class="revenue-money">
                                        <?= format_price($row['revenue']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$tourDetails): ?>
                                <tr>
                                    <td colspan="4">
                                        Không có doanh thu Tour hoàn thành
                                        trong kỳ.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>
                </div>

            </section>

        <?php endif; ?>

        <!-- CHI TIET BAN HANG -->

        <?php if ($source !== 'tour'): ?>

            <section class="revenue-box">

                <h2>Doanh thu đơn hàng</h2>

                <div class="revenue-table-scroll">

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
                                    <td>
                                        <?= rh($row['order_code']) ?>
                                    </td>

                                    <td>
                                        <?= rh($row['fullname']) ?>
                                    </td>

                                    <td class="revenue-money">
                                        <?= format_price($row['revenue']) ?>
                                    </td>

                                    <td>
                                        <?= rh($row['created_at']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$shopDetails): ?>
                                <tr>
                                    <td colspan="4">
                                        Không có doanh thu bán hàng hoàn thành
                                        hoặc chưa đủ dữ liệu danh mục.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>

                </div>

            </section>

        <?php endif; ?>

        <p class="revenue-note">
            Doanh thu hoàn thành được tính theo trạng thái
            hiện tại của giao dịch. Biểu đồ phân nhóm theo
            tháng tạo đơn. Để báo cáo chính xác theo ngày nhận
            tiền, cần lưu thêm ngày hoàn tất thanh toán.
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

const shopChartAvailable = <?= json_encode(
    $categoryId === 0 || $categorySupported
) ?>;

const sourceSelect = document.getElementById('chartSource');

const chartElement = document.getElementById('revenueChart');

let revenueChart = null;

function drawRevenueChart(type) {

    if (!chartElement || typeof Chart === 'undefined') {
        return;
    }

    if (revenueChart) {
        revenueChart.destroy();
    }

    const datasets = [];

    if (type === 'all' || type === 'tour') {
        datasets.push({
            label: 'Doanh thu Tour',
            data: tourMonths,
            backgroundColor: '#166534',
            borderColor: '#166534',
            borderWidth: 2,
            borderRadius: 5
        });
    }

    if (
        (type === 'all' || type === 'shop') &&
        shopChartAvailable
    ) {
        datasets.push({
            label: 'Doanh thu Bán Hàng',
            data: shopMonths,
            backgroundColor: '#ea580c',
            borderColor: '#ea580c',
            borderWidth: 2,
            borderRadius: 5
        });
    }

    revenueChart = new Chart(chartElement, {
        type: 'bar',

        data: {
            labels: [
                'Tháng 1',
                'Tháng 2',
                'Tháng 3',
                'Tháng 4',
                'Tháng 5',
                'Tháng 6',
                'Tháng 7',
                'Tháng 8',
                'Tháng 9',
                'Tháng 10',
                'Tháng 11',
                'Tháng 12'
            ],

            datasets: datasets
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false
            },

            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const amount = Number(context.parsed.y);

                            return context.dataset.label + ': ' +
                                amount.toLocaleString('vi-VN') + ' đ';
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,

                    ticks: {
                        callback: function(value) {
                            return Number(value)
                                .toLocaleString('vi-VN');
                        }
                    }
                }
            }
        }
    });
}

// Bieu do bat dau voi tat ca nguon doanh thu.
drawRevenueChart('all');

// Chon nguon doanh thu tren bieu do:
// Cap nhat ngay, khong can tai lai trang.
sourceSelect.addEventListener('change', function() {
    drawRevenueChart(this.value);
});
</script>

</body>
</html>
