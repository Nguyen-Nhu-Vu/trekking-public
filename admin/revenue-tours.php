<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

$current_page = 'revenue_tours';
$page_title = 'Doanh thu Tour - VNN Travel';

function rt_h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| BỘ LỌC THÁNG / NĂM
|--------------------------------------------------------------------------
*/

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? 0);

if ($year < 2020 || $year > 2100) {
    $year = (int) date('Y');
}

if ($month < 0 || $month > 12) {
    $month = 0;
}

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
| QUY TẮC DOANH THU TOUR
|--------------------------------------------------------------------------
*/

// Chỉ tính các đăng ký đã thanh toán hoặc hoàn thành.
// Đã xác nhận đơn không đồng nghĩa với đã nhận tiền.

$paidCondition = "
    status IN ('Đã thanh toán', 'Đã hoàn thành')
";

/*
|--------------------------------------------------------------------------
| TỔNG DOANH THU VÀ SỐ ĐĂNG KÝ ĐÃ THU TIỀN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total_price), 0) AS revenue,
        COUNT(*) AS paid_bookings,
        COALESCE(SUM(number_people), 0) AS paid_guests
    FROM tour_bookings
    WHERE $paidCondition
      AND created_at >= ?
      AND created_at < ?
");

$stmt->execute([$from, $to]);

$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$revenue = (float) ($summary['revenue'] ?? 0);
$paidBookings = (int) ($summary['paid_bookings'] ?? 0);
$paidGuests = (int) ($summary['paid_guests'] ?? 0);

/*
|--------------------------------------------------------------------------
| ĐĂNG KÝ CHỜ XÁC NHẬN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM tour_bookings
    WHERE status = 'Chờ xác nhận'
      AND created_at >= ?
      AND created_at < ?
");

$stmt->execute([$from, $to]);

$pendingBookings = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| BIỂU ĐỒ DOANH THU 12 THÁNG
|--------------------------------------------------------------------------
*/

$monthlyRevenue = array_fill(1, 12, 0);

$stmt = $pdo->prepare("
    SELECT
        MONTH(created_at) AS report_month,
        SUM(total_price) AS revenue
    FROM tour_bookings
    WHERE $paidCondition
      AND created_at >= ?
      AND created_at < ?
    GROUP BY MONTH(created_at)
    ORDER BY report_month
");

$stmt->execute([
    sprintf('%04d-01-01', $year),
    sprintf('%04d-01-01', $year + 1)
]);

$monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($monthlyRows as $row) {
    $reportMonth = (int) $row['report_month'];

    if ($reportMonth >= 1 && $reportMonth <= 12) {
        $monthlyRevenue[$reportMonth] = (float) $row['revenue'];
    }
}

/*
|--------------------------------------------------------------------------
| DOANH THU THEO TỪNG TOUR
|--------------------------------------------------------------------------
*/

// Phải sử dụng b.status thay vì ghép b. trước
// toàn bộ chuỗi điều kiện SQL.

$stmt = $pdo->prepare("
    SELECT
        t.id AS tour_id,
        t.title AS tour_name,
        COUNT(b.id) AS bookings,
        COALESCE(SUM(b.number_people), 0) AS guests,
        COALESCE(SUM(b.total_price), 0) AS revenue
    FROM tour_bookings b
    INNER JOIN tours t
        ON t.id = b.tour_id
    WHERE b.status IN (
        'Đã thanh toán',
        'Đã hoàn thành'
    )
      AND b.created_at >= ?
      AND b.created_at < ?
    GROUP BY t.id, t.title
    ORDER BY revenue DESC
    LIMIT 50
");

$stmt->execute([$from, $to]);

$tourRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| DANH SÁCH BOOKING ĐÃ THU TIỀN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.booking_code,
        b.fullname,
        b.number_people,
        b.departure_date,
        b.total_price,
        b.status,
        b.created_at,
        t.title AS tour_name
    FROM tour_bookings b
    INNER JOIN tours t
        ON t.id = b.tour_id
    WHERE b.status IN (
        'Đã thanh toán',
        'Đã hoàn thành'
    )
      AND b.created_at >= ?
      AND b.created_at < ?
    ORDER BY b.id DESC
    LIMIT 200
");

$stmt->execute([$from, $to]);

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= rt_h($page_title) ?></title>

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
        .tour-revenue-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 3px 16px rgba(15, 23, 42, .04);
        }

        .tour-revenue-card h2 {
            margin: 0 0 20px;
            color: #0f172a;
            font-size: 20px;
        }

        .tour-revenue-filter {
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .tour-revenue-filter label {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .tour-revenue-filter select {
            min-width: 150px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            padding: 11px;
            background: #ffffff;
            font: inherit;
        }

        .tour-revenue-button {
            padding: 12px 20px;
            border: 0;
            border-radius: 9px;
            background: #166534;
            color: #ffffff;
            font-weight: 800;
            cursor: pointer;
        }

        .tour-revenue-button:hover {
            background: #15803d;
        }

        .tour-revenue-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 22px;
        }

        .tour-revenue-stat {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 23px;
            min-width: 0;
        }

        .tour-revenue-stat-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .tour-revenue-stat-value {
            font-size: 27px;
            font-weight: 900;
            color: #166534;
            margin-top: 13px;
            overflow-wrap: anywhere;
        }

        .tour-revenue-stat-value.orange {
            color: #ea580c;
        }

        .tour-revenue-stat-value.blue {
            color: #0284c7;
        }

        .tour-revenue-muted {
            color: #64748b;
            font-size: 12px;
            margin-top: 9px;
            line-height: 1.7;
        }

        .tour-chart-container {
            position: relative;
            height: 320px;
        }

        .tour-revenue-scroll {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
        }

        .tour-revenue-table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }

        .tour-revenue-table th,
        .tour-revenue-table td {
            text-align: left;
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .tour-revenue-table th {
            background: #f8fafc;
            color: #0f172a;
            font-weight: 800;
        }

        .tour-revenue-price {
            color: #ea580c;
            font-weight: 800;
            white-space: nowrap;
        }

        .tour-revenue-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 800;
        }

        .tour-revenue-note {
            color: #64748b;
            font-size: 13px;
            line-height: 1.8;
            padding-bottom: 20px;
        }

        @media (max-width: 900px) {
            .tour-revenue-stats {
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
                <i class="fa-solid fa-chart-line"></i>
                THỐNG KÊ DOANH THU TOUR TREKKING
            </strong>

        </div>

        <div class="admin-content">

            <!-- BỘ LỌC -->

            <section class="tour-revenue-card">

                <h2>
                    <i class="fa-solid fa-filter"></i>
                    Lọc doanh thu theo thời gian
                </h2>

                <form
                    method="GET"
                    class="tour-revenue-filter"
                >

                    <label>
                        Chọn tháng

                        <select name="month">

                            <option value="0">
                                Cả năm
                            </option>

                            <?php for ($m = 1; $m <= 12; $m++): ?>

                                <option
                                    value="<?= $m ?>"
                                    <?= $month === $m
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Tháng <?= $m ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                    </label>

                    <label>
                        Chọn năm

                        <select name="year">

                            <?php
                            for ($y = (int) date('Y') + 1; $y >= 2020; $y--):
                            ?>

                                <option
                                    value="<?= $y ?>"
                                    <?= $year === $y
                                        ? 'selected'
                                        : '' ?>
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

                    <button
                        type="submit"
                        class="tour-revenue-button"
                    >
                        <i class="fa-solid fa-chart-line"></i>
                        Xem thống kê
                    </button>

                </form>

            </section>

            <!-- THỐNG KÊ TỔNG QUAN -->

            <div class="tour-revenue-stats">

                <div class="tour-revenue-stat">

                    <div class="tour-revenue-stat-label">
                        Tổng doanh thu đã thu
                    </div>

                    <div class="tour-revenue-stat-value orange">
                        <?= format_price($revenue) ?>
                    </div>

                    <p class="tour-revenue-muted">
                        Chỉ tính booking đã thanh toán hoặc đã hoàn thành.
                    </p>

                </div>

                <div class="tour-revenue-stat">

                    <div class="tour-revenue-stat-label">
                        Lượt đăng ký đã thanh toán
                    </div>

                    <div class="tour-revenue-stat-value">
                        <?= $paidBookings ?>
                    </div>

                    <p class="tour-revenue-muted">
                        Tổng số khách:
                        <strong><?= $paidGuests ?></strong>
                    </p>

                </div>

                <div class="tour-revenue-stat">

                    <div class="tour-revenue-stat-label">
                        Đăng ký chờ xác nhận
                    </div>

                    <div class="tour-revenue-stat-value blue">
                        <?= $pendingBookings ?>
                    </div>

                    <p class="tour-revenue-muted">
                        Không cộng vào doanh thu.
                    </p>

                </div>

            </div>

            <!-- BIỂU ĐỒ -->

            <section class="tour-revenue-card">

                <h2>
                    <i class="fa-solid fa-chart-column"></i>
                    Biểu đồ doanh thu Tour theo tháng - <?= $year ?>
                </h2>

                <div class="tour-chart-container">

                    <canvas
                        id="tourRevenueChart"
                        aria-label="Biểu đồ doanh thu tour theo tháng"
                    ></canvas>

                </div>

            </section>

            <!-- TOP TOUR DOANH THU -->

            <section class="tour-revenue-card">

                <h2>
                    <i class="fa-solid fa-mountain-sun"></i>
                    Doanh thu theo từng tour
                </h2>

                <div class="tour-revenue-scroll">

                    <table class="tour-revenue-table">

                        <thead>
                            <tr>
                                <th>Tên tour</th>
                                <th>Lượt đăng ký</th>
                                <th>Số khách</th>
                                <th>Doanh thu đã thu</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($tourRevenue as $row): ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= rt_h($row['tour_name']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= (int) $row['bookings'] ?>
                                    </td>

                                    <td>
                                        <?= (int) $row['guests'] ?>
                                    </td>

                                    <td class="tour-revenue-price">
                                        <?= format_price($row['revenue']) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            <?php if (!$tourRevenue): ?>

                                <tr>
                                    <td
                                        colspan="4"
                                        style="text-align:center;padding:30px"
                                    >
                                        Chưa có doanh thu tour trong kỳ.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

            <!-- DANH SÁCH BOOKING ĐÃ THANH TOÁN -->

            <section class="tour-revenue-card">

                <h2>
                    <i class="fa-solid fa-receipt"></i>
                    Chi tiết đăng ký đã thanh toán
                </h2>

                <div class="tour-revenue-scroll">

                    <table class="tour-revenue-table">

                        <thead>
                            <tr>
                                <th>Mã Booking</th>
                                <th>Khách hàng</th>
                                <th>Tour</th>
                                <th>Ngày khởi hành</th>
                                <th>Số khách</th>
                                <th>Số tiền</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($bookings as $b): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= rt_h($b['booking_code']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= rt_h($b['fullname']) ?>
                                    </td>

                                    <td>
                                        <?= rt_h($b['tour_name']) ?>
                                    </td>

                                    <td>
                                        <?= !empty($b['departure_date'])
                                            ? date(
                                                'd/m/Y',
                                                strtotime($b['departure_date'])
                                            )
                                            : '—' ?>
                                    </td>

                                    <td>
                                        <?= (int) $b['number_people'] ?>
                                    </td>

                                    <td class="tour-revenue-price">
                                        <?= format_price($b['total_price']) ?>
                                    </td>

                                    <td>
                                        <span class="tour-revenue-badge">
                                            <?= rt_h($b['status']) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <?php if (!$bookings): ?>

                                <tr>
                                    <td
                                        colspan="7"
                                        style="text-align:center;padding:30px"
                                    >
                                        Chưa có booking đã thanh toán trong kỳ.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

            <p class="tour-revenue-note">
                Báo cáo chỉ ghi nhận các booking có trạng thái
                đã thanh toán hoặc đã hoàn thành. Đăng ký
                chờ xác nhận không được tính vào doanh thu.

                Dữ liệu hiện phân kỳ theo ngày tạo booking.
                Để thống kê đúng ngày nhận tiền, cần lưu
                thêm thời điểm quản lý xác nhận thanh toán.
            </p>

        </div>

    </main>

    <script>
        const tourRevenueData = <?= json_encode(
            array_values($monthlyRevenue),
            JSON_NUMERIC_CHECK
        ) ?>;

        const tourRevenueLabels = Array.from(
            { length: 12 },
            (_, index) => 'Tháng ' + (index + 1)
        );

        const tourChartElement = document.getElementById(
            'tourRevenueChart'
        );

        if (tourChartElement && window.Chart) {

            new Chart(tourChartElement, {
                type: 'line',

                data: {
                    labels: tourRevenueLabels,

                    datasets: [{
                        label: 'Doanh thu tour (VNĐ)',
                        data: tourRevenueData,
                        borderColor: '#166534',
                        backgroundColor: 'rgba(22,101,52,.12)',
                        pointBackgroundColor: '#166534',
                        pointRadius: 4,
                        tension: 0.3,
                        fill: true
                    }]
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
                                    const value = Number(
                                        context.parsed.y
                                    );

                                    return value.toLocaleString('vi-VN') + ' đ';
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
