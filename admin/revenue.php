<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

$current_page = 'revenue';
$page_title = 'Doanh thu tổng hợp - VNN Travel';

function revH($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function revColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");

    $stmt->execute([$table]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function revFirstColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $name) {
        if (in_array($name, $columns, true)) {
            return $name;
        }
    }

    return null;
}

function revSum(PDO $pdo, string $sql, array $params): float
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (float) $stmt->fetchColumn();
}

function revRows(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==========================================
// BO LOC
// ==========================================

$source = (string) ($_GET['source'] ?? 'all');

if (!in_array($source, ['all', 'tour', 'shop'], true)) {
    $source = 'all';
}

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? 0);
$tourId = max(0, (int) ($_GET['tour_id'] ?? 0));
$categoryId = max(0, (int) ($_GET['category_id'] ?? 0));

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

$tours = revRows(
    $pdo,
    "SELECT id, title FROM tours ORDER BY title",
    []
);

$categories = revRows(
    $pdo,
    "SELECT id, name FROM categories ORDER BY name",
    []
);

$tourIds = array_map('intval', array_column($tours, 'id'));
$categoryIds = array_map('intval', array_column($categories, 'id'));

if (!in_array($tourId, $tourIds, true)) {
    $tourId = 0;
}

if (!in_array($categoryId, $categoryIds, true)) {
    $categoryId = 0;
}

if ($source !== 'tour') {
    $tourId = 0;
}

if ($source !== 'shop') {
    $categoryId = 0;
}

// ==========================================
// DOANH THU TOUR
// ==========================================

// Chi tinh booking da thanh toan/hoan thanh.
// Neu database dung cot payment_status rieng,
// can dong bo dieu kien theo cot do.

$tourCondition = "
    b.status IN ('Đã thanh toán', 'Đã hoàn thành')
";

$tourFilter = '';
$tourParams = [];

if ($tourId > 0) {
    $tourFilter = ' AND b.tour_id = ?';
    $tourParams[] = $tourId;
}

$tourRevenue = revSum(
    $pdo,
    "
        SELECT COALESCE(SUM(b.total_price), 0)
        FROM tour_bookings b
        WHERE $tourCondition
          AND b.created_at >= ?
          AND b.created_at < ?
          $tourFilter
    ",
    array_merge([$from, $to], $tourParams)
);

// ==========================================
// DOANH THU CUA HANG
// ==========================================

$categorySupported = true;
$categoryWarning = '';
$categoryRevenueSql = '';
$categoryParams = [];

$itemColumns = revColumns($pdo, 'order_items');

$itemOrderColumn = revFirstColumn(
    $itemColumns,
    ['order_id']
);

$itemProductColumn = revFirstColumn(
    $itemColumns,
    ['product_id']
);

$itemQuantityColumn = revFirstColumn(
    $itemColumns,
    ['quantity', 'qty']
);

$itemPriceColumn = revFirstColumn(
    $itemColumns,
    ['price', 'unit_price', 'product_price']
);

$itemTotalColumn = revFirstColumn(
    $itemColumns,
    ['subtotal', 'total_price', 'line_total', 'total']
);

if ($categoryId > 0) {

    if (
        !$itemOrderColumn ||
        !$itemProductColumn ||
        (!$itemTotalColumn &&
         (!$itemQuantityColumn || !$itemPriceColumn))
    ) {
        $categorySupported = false;

        $categoryWarning =
            'Chưa thể tính doanh thu theo danh mục vì bảng ' .
            'order_items chưa có đủ cột lưu chi tiết giá bán. ' .
            'Cần kiểm tra cấu trúc bảng này trên Aiven.';
    } else {
        $lineAmount = $itemTotalColumn
            ? "oi.`$itemTotalColumn`"
            : "oi.`$itemQuantityColumn` * oi.`$itemPriceColumn`";

        $categoryRevenueSql = "
            SELECT
                COALESCE(SUM($lineAmount), 0)
            FROM order_items oi
            INNER JOIN orders o
                ON o.id = oi.`$itemOrderColumn`
            INNER JOIN products p
                ON p.id = oi.`$itemProductColumn`
            WHERE o.status = 'Đã giao'
              AND o.created_at >= ?
              AND o.created_at < ?
              AND p.category_id = ?
        ";

        $categoryParams = [$categoryId];
    }
}

function revShopRevenue(
    PDO $pdo,
    string $from,
    string $to,
    int $categoryId,
    bool $categorySupported,
    string $categoryRevenueSql,
    array $categoryParams
): ?float {
    if ($categoryId > 0 && !$categorySupported) {
        return null;
    }

    if ($categoryId > 0) {
        return revSum(
            $pdo,
            $categoryRevenueSql,
            array_merge([$from, $to], $categoryParams)
        );
    }

    return revSum(
        $pdo,
        "
            SELECT COALESCE(SUM(total_amount), 0)
            FROM orders
            WHERE status = 'Đã giao'
              AND created_at >= ?
              AND created_at < ?
        ",
        [$from, $to]
    );
}

$shopRevenue = revShopRevenue(
    $pdo,
    $from,
    $to,
    $categoryId,
    $categorySupported,
    $categoryRevenueSql,
    $categoryParams
);

$displayTourRevenue = $source !== 'shop'
    ? $tourRevenue
    : 0;

$displayShopRevenue = $source !== 'tour'
    ? $shopRevenue
    : 0;

$totalRevenue = $displayShopRevenue === null
    ? null
    : $displayTourRevenue + $displayShopRevenue;

// ==========================================
// THONG KE 12 THANG
// ==========================================

$tourMonths = array_fill(1, 12, 0);
$shopMonths = array_fill(1, 12, 0);

$tourChartRows = revRows(
    $pdo,
    "
        SELECT
            MONTH(b.created_at) AS month_number,
            SUM(b.total_price) AS revenue
        FROM tour_bookings b
        WHERE $tourCondition
          AND b.created_at >= ?
          AND b.created_at < ?
          $tourFilter
        GROUP BY MONTH(b.created_at)
    ",
    array_merge([$yearStart, $yearEnd], $tourParams)
);

foreach ($tourChartRows as $row) {
    $m = (int) $row['month_number'];

    if ($m >= 1 && $m <= 12) {
        $tourMonths[$m] = (float) $row['revenue'];
    }
}

if ($categoryId === 0) {
    $shopChartRows = revRows(
        $pdo,
        "
            SELECT
                MONTH(created_at) AS month_number,
                SUM(total_amount) AS revenue
            FROM orders
            WHERE status = 'Đã giao'
              AND created_at >= ?
              AND created_at < ?
            GROUP BY MONTH(created_at)
        ",
        [$yearStart, $yearEnd]
    );

} elseif ($categorySupported) {

    $chartSql = str_replace(
        'COALESCE(SUM(',
        'COALESCE(SUM(',
        $categoryRevenueSql
    );

    // Truy van rieng theo tung thang o vong lap duoi.
    $shopChartRows = [];

} else {
    $shopChartRows = [];
}

foreach ($shopChartRows as $row) {
    $m = (int) $row['month_number'];

    if ($m >= 1 && $m <= 12) {
        $shopMonths[$m] = (float) $row['revenue'];
    }
}

if ($categoryId > 0 && $categorySupported) {
    for ($m = 1; $m <= 12; $m++) {
        $monthStart = sprintf('%04d-%02d-01', $year, $m);
        $monthEnd = date(
            'Y-m-d',
            strtotime($monthStart . ' +1 month')
        );

        $shopMonths[$m] = revShopRevenue(
            $pdo,
            $monthStart,
            $monthEnd,
            $categoryId,
            true,
            $categoryRevenueSql,
            $categoryParams
        );
    }
}

// ==========================================
// BANG CHI TIET TOUR
// ==========================================

$tourDetails = [];

if ($source !== 'shop') {
    $tourDetails = revRows(
        $pdo,
        "
            SELECT
                t.title,
                COUNT(b.id) AS booking_count,
                COALESCE(SUM(b.number_people), 0) AS guests,
                COALESCE(SUM(b.total_price), 0) AS revenue
            FROM tour_bookings b
            INNER JOIN tours t ON t.id = b.tour_id
            WHERE $tourCondition
              AND b.created_at >= ?
              AND b.created_at < ?
              $tourFilter
            GROUP BY t.id, t.title
            ORDER BY revenue DESC
        ",
        array_merge([$from, $to], $tourParams)
    );
}

// ==========================================
// BANG CHI TIET CUA HANG
// ==========================================

$shopDetails = [];

if ($source !== 'tour') {

    if ($categoryId === 0) {
        $shopDetails = revRows(
            $pdo,
            "
                SELECT
                    order_code,
                    fullname,
                    total_amount AS revenue,
                    created_at
                FROM orders
                WHERE status = 'Đã giao'
                  AND created_at >= ?
                  AND created_at < ?
                ORDER BY created_at DESC
                LIMIT 200
            ",
            [$from, $to]
        );

    } elseif ($categorySupported) {

        $lineAmount = $itemTotalColumn
            ? "oi.`$itemTotalColumn`"
            : "oi.`$itemQuantityColumn` * oi.`$itemPriceColumn`";

        $shopDetails = revRows(
            $pdo,
            "
                SELECT
                    o.order_code,
                    o.fullname,
                    SUM($lineAmount) AS revenue,
                    o.created_at
                FROM order_items oi
                INNER JOIN orders o
                    ON o.id = oi.`$itemOrderColumn`
                INNER JOIN products p
                    ON p.id = oi.`$itemProductColumn`
                WHERE o.status = 'Đã giao'
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
    <title><?= revH($page_title) ?></title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet"
          href="../assets/css/style.css">

    <link rel="stylesheet"
          href="../assets/css/admin.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

    <style>
        .revenue-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 4px 16px rgba(15,23,42,.05);
        }

        .revenue-card h2 {
            margin: 0 0 20px;
            color: #0f172a;
            font-size: 20px;
        }

        .revenue-filters {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 15px;
        }

        .revenue-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .revenue-field select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            font: inherit;
        }

        .revenue-button {
            background: #166534;
            color: white;
            border: none;
            border-radius: 9px;
            padding: 12px 22px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 18px;
        }

        .revenue-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 18px;
            margin-bottom: 22px;
        }

        .revenue-stat {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
        }

        .revenue-stat-label {
            font-size: 13px;
            font-weight: 800;
            color: #64748b;
        }

        .revenue-stat-value {
            font-size: 27px;
            font-weight: 900;
            color: #166534;
            margin-top: 12px;
            overflow-wrap: anywhere;
        }

        .revenue-stat-value.orange {
            color: #ea580c;
        }

        .revenue-chart {
            position: relative;
            height: 330px;
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
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .revenue-table th {
            background: #f8fafc;
            color: #0f172a;
        }

        .revenue-money {
            color: #ea580c;
            font-weight: 800;
        }

        .revenue-warning {
            color: #92400e;
            background: #fef3c7;
            border: 1px solid #fcd34d;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .revenue-note {
            color: #64748b;
            font-size: 13px;
            line-height: 1.8;
        }

        @media (max-width: 900px) {
            .revenue-filters {
                grid-template-columns: repeat(2, minmax(0,1fr));
            }

            .revenue-stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 550px) {
            .revenue-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-body">

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main">

    <div class="admin-topbar">
        <strong>
            <i class="fa-solid fa-chart-line"></i>
            DOANH THU & BÁO CÁO
        </strong>
    </div>

    <div class="admin-content">

        <section class="revenue-card">
            <h2>Bộ lọc doanh thu</h2>

            <form method="GET">
                <div class="revenue-filters">

                    <div class="revenue-field">
                        <label for="source">Nguồn doanh thu</label>

                        <select
                            id="source"
                            name="source"
                            onchange="this.form.submit()"
                        >
                            <option value="all"
                                <?= $source === 'all' ? 'selected' : '' ?>>
                                Tất cả
                            </option>

                            <option value="tour"
                                <?= $source === 'tour' ? 'selected' : '' ?>>
                                Doanh thu Tour
                            </option>

                            <option value="shop"
                                <?= $source === 'shop' ? 'selected' : '' ?>>
                                Doanh thu Bán Hàng
                            </option>
                        </select>
                    </div>

                    <div class="revenue-field">
                        <label for="month">Tháng</label>

                        <select id="month" name="month">
                            <option value="0">Cả năm</option>

                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>"
                                    <?= $month === $m ? 'selected' : '' ?>>
                                    Tháng <?= $m ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="revenue-field">
                        <label for="year">Năm</label>

                        <select id="year" name="year">
                            <?php for ($y = 2020; $y <= 2100; $y++): ?>
                                <?php if ($y <= (int) date('Y') + 1 || $y === $year): ?>
                                    <option value="<?= $y ?>"
                                        <?= $year === $y ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <?php if ($source === 'tour'): ?>
                        <div class="revenue-field">
                            <label for="tour_id">Lọc theo từng tour</label>

                            <select name="tour_id" id="tour_id">
                                <option value="0">Tất cả tour</option>

                                <?php foreach ($tours as $tour): ?>
                                    <option
                                        value="<?= (int) $tour['id'] ?>"
                                        <?= $tourId === (int) $tour['id']
                                            ? 'selected' : '' ?>
                                    >
                                        <?= revH($tour['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($source === 'shop'): ?>
                        <div class="revenue-field">
                            <label for="category_id">
                                Lọc theo danh mục hàng
                            </label>

                            <select name="category_id" id="category_id">
                                <option value="0">Tất cả danh mục</option>

                                <?php foreach ($categories as $category): ?>
                                    <option
                                        value="<?= (int) $category['id'] ?>"
                                        <?= $categoryId === (int) $category['id']
                                            ? 'selected' : '' ?>
                                    >
                                        <?= revH($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                </div>

                <button class="revenue-button" type="submit">
                    <i class="fa-solid fa-filter"></i>
                    Xem thống kê
                </button>
            </form>
        </section>

        <?php if ($categoryWarning): ?>
            <div class="revenue-warning" role="alert">
                <?= revH($categoryWarning) ?>
            </div>
        <?php endif; ?>

        <div class="revenue-stats">

            <div class="revenue-stat">
                <div class="revenue-stat-label">
                    TỔNG DOANH THU
                </div>

                <div class="revenue-stat-value orange">
                    <?= $totalRevenue === null
                        ? 'Chưa đủ dữ liệu'
                        : format_price($totalRevenue) ?>
                </div>
            </div>

            <div class="revenue-stat">
                <div class="revenue-stat-label">
                    DOANH THU TOUR
                </div>

                <div class="revenue-stat-value">
                    <?= format_price($displayTourRevenue) ?>
                </div>
            </div>

            <div class="revenue-stat">
                <div class="revenue-stat-label">
                    DOANH THU BÁN HÀNG
                </div>

                <div class="revenue-stat-value">
                    <?= $displayShopRevenue === null
                        ? 'Chưa đủ dữ liệu'
                        : format_price($displayShopRevenue) ?>
                </div>
            </div>

        </div>

        <section class="revenue-card">
            <h2>
                <i class="fa-solid fa-chart-column"></i>
                Biểu đồ doanh thu năm <?= $year ?>
            </h2>

            <div class="revenue-chart">
                <canvas id="revenueChart"></canvas>
            </div>
        </section>

        <?php if ($source !== 'shop'): ?>
            <section class="revenue-card">
                <h2>Doanh thu theo từng tour</h2>

                <div class="revenue-table-wrap">
                    <table class="revenue-table">
                        <thead>
                            <tr>
                                <th>Tên tour</th>
                                <th>Lượt đăng ký</th>
                                <th>Số khách</th>
                                <th>Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tourDetails as $row): ?>
                                <tr>
                                    <td><?= revH($row['title']) ?></td>
                                    <td><?= (int) $row['booking_count'] ?></td>
                                    <td><?= (int) $row['guests'] ?></td>
                                    <td class="revenue-money">
                                        <?= format_price($row['revenue']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$tourDetails): ?>
                                <tr>
                                    <td colspan="4">
                                        Chưa có doanh thu trong kỳ.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($source !== 'tour'): ?>
            <section class="revenue-card">
                <h2>Chi tiết doanh thu bán hàng</h2>

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
                                    <td><?= revH($row['order_code']) ?></td>
                                    <td><?= revH($row['fullname']) ?></td>
                                    <td class="revenue-money">
                                        <?= format_price($row['revenue']) ?>
                                    </td>
                                    <td><?= revH($row['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$shopDetails): ?>
                                <tr>
                                    <td colspan="4">
                                        Không có đơn phù hợp hoặc chưa đủ
                                        dữ liệu chi tiết danh mục.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <p class="revenue-note">
            Báo cáo phân kỳ theo ngày tạo đơn/booking.
            Doanh thu Tour chỉ tính trạng thái đã thanh toán
            hoặc hoàn thành; doanh thu cửa hàng chỉ tính đơn
            đã giao. Doanh thu theo danh mục tính từ giá trị
            các dòng sản phẩm, không phân bổ phí giao hàng.
        </p>

    </div>
</main>

<script>
const source = <?= json_encode($source) ?>;
const validShop = <?= json_encode(
    $categorySupported || $categoryId === 0
) ?>;

const tourRevenue = <?= json_encode(
    array_values($tourMonths),
    JSON_NUMERIC_CHECK
) ?>;

const shopRevenue = <?= json_encode(
    array_values($shopMonths),
    JSON_NUMERIC_CHECK
) ?>;

const datasets = [];

if (source !== 'shop') {
    datasets.push({
        label: 'Doanh thu Tour',
        data: tourRevenue,
        backgroundColor: '#166534',
        borderColor: '#166534',
        borderWidth: 2,
        borderRadius: 5
    });
}

if (source !== 'tour' && validShop) {
    datasets.push({
        label: 'Doanh thu Bán Hàng',
        data: shopRevenue,
        backgroundColor: '#ea580c',
        borderColor: '#ea580c',
        borderWidth: 2,
        borderRadius: 5
    });
}

const chartCanvas = document.getElementById('revenueChart');

if (chartCanvas && window.Chart) {
    new Chart(chartCanvas, {
        type: 'bar',

        data: {
            labels: Array.from(
                { length: 12 },
                (_, i) => 'Tháng ' + (i + 1)
            ),
            datasets: datasets
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                intersect: false,
                mode: 'index'
            },

            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' +
                                Number(context.parsed.y)
                                    .toLocaleString('vi-VN') + ' đ';
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value =>
                            Number(value).toLocaleString('vi-VN')
                    }
                }
            }
        }
    });
}
</script>
</body>
</html>
