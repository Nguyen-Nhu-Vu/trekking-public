<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

$current_page = 'revenue_shop';
$page_title = 'Doanh thu bán hàng - VNN Travel';

function rs_h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? 0);

if ($year < 2020 || $year > 2100) {
    $year = (int) date('Y');
}

if ($month < 0 || $month > 12) {
    $month = 0;
}

// KHOẢNG THỜI GIAN BÁO CÁO

$startMonth = $month > 0 ? $month : 1;

$from = sprintf(
    '%04d-%02d-01',
    $year,
    $startMonth
);

if ($month > 0) {
    $to = date(
        'Y-m-d',
        strtotime($from . ' +1 month')
    );
} else {
    $to = sprintf(
        '%04d-01-01',
        $year + 1
    );
}

/*
|--------------------------------------------------------------------------
| DOANH THU HOÀN THÀNH
|--------------------------------------------------------------------------
*/

// Chỉ tính đơn đã giao, không tính đơn đang vận chuyển.

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total_amount), 0) AS revenue,
        COUNT(*) AS completed_orders
    FROM orders
    WHERE status = 'Đã giao'
      AND created_at >= ?
      AND created_at < ?
");

$stmt->execute([$from, $to]);

$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$revenue = (float) ($summary['revenue'] ?? 0);
$completedOrders = (int) ($summary['completed_orders'] ?? 0);

/*
|--------------------------------------------------------------------------
| ĐƠN ĐANG GIAO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM orders
    WHERE status = 'Đang giao'
      AND created_at >= ?
      AND created_at < ?
");

$stmt->execute([$from, $to]);

$shippingOrders = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| BIỂU ĐỒ DOANH THU 12 THÁNG
|--------------------------------------------------------------------------
*/

$monthlyRevenue = array_fill(1, 12, 0);

$stmt = $pdo->prepare("
    SELECT
        MONTH(created_at) AS report_month,
        SUM(total_amount) AS amount
    FROM orders
    WHERE status = 'Đã giao'
      AND created_at >= ?
      AND created_at < ?
    GROUP BY MONTH(created_at)
    ORDER BY report_month
");

$stmt->execute([
    sprintf('%04d-01-01', $year),
    sprintf('%04d-01-01', $year + 1)
]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $reportMonth = (int) $row['report_month'];

    if ($reportMonth >= 1 && $reportMonth <= 12) {
        $monthlyRevenue[$reportMonth] = (float) $row['amount'];
    }
}

/*
|--------------------------------------------------------------------------
| DANH SÁCH ĐƠN ĐÃ GIAO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        order_code,
        fullname,
        total_amount,
        created_at,
        status
    FROM orders
    WHERE status = 'Đã giao'
      AND created_at >= ?
      AND created_at < ?
    ORDER BY id DESC
    LIMIT 200
");

$stmt->execute([$from, $to]);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= rs_h($page_title) ?></title>

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
        .revenue-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 3px 16px rgba(15, 23, 42, .04);
        }

        .revenue-card h2 {
            margin: 0 0 20px;
            font-size: 20px;
            color: #0f172a;
        }

        .revenue-filter {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .revenue-filter label {
            display: flex;
            flex-direction: column;
            gap: 7px;
            font-weight: 700;
            color: #334155;
            font-size: 13px;
        }

        .revenue-filter select {
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            min-width: 145px;
        }

        .revenue-filter button {
            padding: 12px 20px;
            background: #166534;
            color: #ffffff;
            border: 0;
            border-radius: 9px;
            font-weight: 800;
            cursor: pointer;
        }

        .revenue-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 22px;
        }

        .revenue-stat {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            min-width: 0;
        }

        .revenue-stat-label {
            font-size: 13px;
            font-weight: 800;
            color: #64748b;
        }

        .revenue-stat-number {
            color: #166534;
            font-size: 27px;
            font-weight: 900;
            margin-top: 13px;
            overflow-wrap: anywhere;
        }

        .revenue-stat-number.orange {
            color: #ea580c;
        }

        .revenue-stat-number.blue {
            color: #0284c7;
        }

        .revenue-stat-note {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 10px;
            line-height: 1.6;
        }

        .revenue-chart {
            height: 320px;
            position: relative;
        }

        .revenue-table-wrap {
            max-width: 100%;
            overflow-x: auto;
        }

        .revenue-table {
            width: 100%;
            min-width: 700px;
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
            font-weight: 800;
        }

        .revenue-price {
            color: #ea580c;
            font-weight: 800;
        }

        .revenue-tag {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 800;
        }

        .revenue-note {
            color: #64748b;
            font-size: 13px;
            line-height: 1.8;
        }

        @media (max-width: 900px) {
            .revenue-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="admin-body">

    <?php
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <main class="admin-main">

        <div class="admin-topbar">
            <strong>
                <i class="fa-solid fa-chart-column"></i>
                DOANH THU BÁN HÀNG
            </strong>
        </div>

        <div class="admin-content">

            <!-- LỌC THÁNG / NĂM -->

            <section class="revenue-card">

                <h2>
                    <i class="fa-solid fa-filter"></i>
                    Bộ lọc thống kê
                </h2>

                <form method="GET" class="revenue-filter">

                    <label>
                        Tháng

                        <select name="month">

                            <option value="0">
                                Cả năm
                            </option>

                            <?php for ($m = 1; $m <= 12; $m++): ?>

                                <option
                                    value="<?= $m ?>"
                                    <?= $month === $m ? 'selected' : '' ?>
                                >
                                    Tháng <?= $m ?>
                                </option>

                            <?php endfor; ?>

                        </select>
                    </label>

                    <label>
                        Năm

                        <select name="year">

                            <?php
                            for ($y = (int) date('Y') + 1; $y >= 2020; $y--):
                            ?>

                                <option
                                    value="<?= $y ?>"
                                    <?= $year === $y ? 'selected' : '' ?>
                                >
                                    <?= $y ?>
                                </option>

                            <?php endfor; ?>

                            <?php if ($year > (int) date('Y') + 1): ?>
                                <option value="<?= $year ?>" selected>
                                    <?= $year ?>
                                </option>
                            <?php endif; ?>

                        </select>
                    </label>

                    <button type="submit">
                        <i class="fa-solid fa-chart-line"></i>
                        Xem thống kê
                    </button>

                </form>

            </section>

            <!-- THẺ THỐNG KÊ -->

            <div class="revenue-stats">

                <div class="revenue-stat">
                    <div class="revenue-stat-label">
                        DOANH THU ĐÃ HOÀN THÀNH
                    </div>

                    <div class="revenue-stat-number orange">
                        <?= format_price($revenue) ?>
                    </div>

                    <div class="revenue-stat-note">
                        Chỉ cộng đơn hàng có trạng thái Đã giao.
                    </div>
                </div>

                <div class="revenue-stat">
                    <div class="revenue-stat-label">
                        TỔNG ĐƠN ĐÃ GIAO
                    </div>

                    <div class="revenue-stat-number">
                        <?= $completedOrders ?>
                    </div>

                    <div class="revenue-stat-note">
                        Đơn hàng hoàn tất giao nhận trong hệ thống.
                    </div>
                </div>

                <div class="revenue-stat">
                    <div class="revenue-stat-label">
                        ĐƠN ĐANG GIAO
                    </div>

                    <div class="revenue-stat-number blue">
                        <?= $shippingOrders ?>
                    </div>

                    <div class="revenue-stat-note">
                        Chưa cộng doanh thu vì chưa giao thành công.
                    </div>
                </div>

            </div>

            <!-- BIỂU ĐỒ -->

            <section class="revenue-card">

                <h2>
                    <i class="fa-solid fa-chart-bar"></i>
                    Biểu đồ doanh thu từng tháng - <?= $year ?>
                </h2>

                <div class="revenue-chart">
                    <canvas id="shopRevenueChart"></canvas>
                </div>

            </section>

            <!-- DANH SÁCH ĐƠN -->

            <section class="revenue-card">

                <h2>
                    <i class="fa-solid fa-list-check"></i>
                    Danh sách đơn hàng đã giao
                </h2>

                <div class="revenue-table-wrap">

                    <table class="revenue-table">

                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Số tiền</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($orders as $order): ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= rs_h($order['order_code']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= rs_h($order['fullname']) ?>
                                    </td>

                                    <td class="revenue-price">
                                        <?= format_price($order['total_amount']) ?>
                                    </td>

                                    <td>
                                        <span class="revenue-tag">
                                            Đã giao
                                        </span>
                                    </td>

                                    <td>
                                        <?= rs_h($order['created_at']) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            <?php if (!$orders): ?>

                                <tr>
                                    <td colspan="5" style="text-align:center;padding:30px">
                                        Chưa có đơn đã giao trong kỳ.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

            <p class="revenue-note">
                Lưu ý: báo cáo hiện lọc theo ngày tạo đơn.
                Khi hệ thống có cột thời điểm giao thành công,
                nên chuyển sang thống kê theo ngày hoàn tất giao hàng.
            </p>

        </div>

    </main>

    <script>
        const shopRevenueData = <?= json_encode(
            array_values($monthlyRevenue),
            JSON_NUMERIC_CHECK
        ) ?>;

        const shopRevenueLabels = Array.from(
            { length: 12 },
            (_, index) => 'Tháng ' + (index + 1)
        );

        const shopChartElement = document.getElementById(
            'shopRevenueChart'
        );

        if (shopChartElement && window.Chart) {
            new Chart(shopChartElement, {
                type: 'bar',

                data: {
                    labels: shopRevenueLabels,

                    datasets: [{
                        label: 'Doanh thu bán hàng (VNĐ)',
                        data: shopRevenueData,
                        backgroundColor: '#166534',
                        borderRadius: 6
                    }]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return Number(
                                        context.parsed.y
                                    ).toLocaleString('vi-VN') + ' đ';
                                }
                            }
                        }
                    },

                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    </script>

</body>
</html>
