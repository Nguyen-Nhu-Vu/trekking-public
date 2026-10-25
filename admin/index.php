<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();

$current_page = 'dashboard';
$page_title = 'Hệ thống quản lý VNN Travel';

function dbh($v)
{
    return htmlspecialchars(
        (string) $v,
        ENT_QUOTES,
        'UTF-8'
    );
}

function dbnum(PDO $pdo, string $sql): float
{
    try {
        return (float) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log('Dashboard: ' . $e->getMessage());
        return 0;
    }
}

// THỐNG KÊ KHÁCH HÀNG, TOUR VÀ KHO HÀNG

$customers = dbnum(
    $pdo,
    "SELECT COUNT(*) FROM users WHERE role = 'customer'"
);

$tours = dbnum(
    $pdo,
    'SELECT COUNT(*) FROM tours'
);

$stock = dbnum(
    $pdo,
    'SELECT COALESCE(SUM(stock),0) FROM products'
);

$lowStock = dbnum(
    $pdo,
    'SELECT COUNT(*) FROM products WHERE stock <= 5'
);

// ĐƠN CHỜ XÁC NHẬN

$pendingTours = dbnum(
    $pdo,
    "SELECT COUNT(*) FROM tour_bookings
     WHERE status = 'Chờ xác nhận'"
);

$pendingOrders = dbnum(
    $pdo,
    "SELECT COUNT(*) FROM orders
     WHERE status = 'Chờ xác nhận'"
);

// DOANH THU HOÀN THÀNH

$tourRevenue = dbnum(
    $pdo,
    "SELECT COALESCE(SUM(total_price),0)
     FROM tour_bookings
     WHERE status IN ('Đã thanh toán','Đã hoàn thành')"
);

$shopRevenue = dbnum(
    $pdo,
    "SELECT COALESCE(SUM(total_amount),0)
     FROM orders
     WHERE status = 'Đã giao'"
);

$totalRevenue = $tourRevenue + $shopRevenue;

$adminRoot = rtrim(BASE_URL, '/') . '/admin/';
$siteRoot = rtrim(BASE_URL, '/') . '/';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= dbh($page_title) ?></title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= dbh($siteRoot) ?>assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="<?= dbh($siteRoot) ?>assets/css/admin.css"
    >

    <style>
        .vd-main {
            min-width: 0;
            flex: 1;
            background: #f1f5f9;
            min-height: 100vh;
        }

        .vd-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            background: #fff;
            padding: 18px 26px;
            border-bottom: 1px solid #e2e8f0;
        }

        .vd-head strong {
            color: #16324f;
            font-size: 16px;
        }

        .vd-head a {
            display: inline-block;
            text-decoration: none;
            border: 1px solid #166534;
            border-radius: 12px;
            padding: 10px 16px;
            color: #166534;
            font-weight: 700;
        }

        .vd-head a.logout {
            background: #ef4444;
            border-color: #ef4444;
            color: #fff;
        }

        .vd-content {
            padding: 28px;
            max-width: 1600px;
            margin: auto;
        }

        .vd-hero {
            background:
                linear-gradient(
                    105deg,
                    rgba(15, 23, 42, .9),
                    rgba(22, 101, 52, .8)
                ),
                url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1500&q=80')
                center / cover;

            border-radius: 18px;
            padding: 38px;
            color: white;
            margin-bottom: 25px;
        }

        .vd-hero small {
            display: inline-block;
            background: #ea580c;
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: 800;
        }

        .vd-hero h1 {
            font-size: clamp(24px, 3vw, 36px);
            margin: 20px 0 12px;
        }

        .vd-hero p {
            line-height: 1.7;
            max-width: 650px;
        }

        .vd-alert {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            background: #fff7ed;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 25px;
            color: #c2410c;
        }

        .vd-alert a {
            display: inline-block;
            text-decoration: none;
            background: #166534;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            padding: 10px 14px;
            border-radius: 9px;
            margin: 3px;
        }

        .vd-alert a:first-child {
            background: #ea580c;
        }

        .vd-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .vd-stat,
        .vd-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            padding: 22px;
            min-width: 0;
        }

        .vd-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 800;
            text-transform: uppercase;
        }

        .vd-value {
            font-size: clamp(21px, 2vw, 30px);
            color: #0f172a;
            font-weight: 900;
            margin-top: 12px;
            overflow-wrap: anywhere;
        }

        .vd-muted {
            font-size: 12px;
            color: #64748b;
            margin-top: 7px;
        }

        .vd-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .vd-card h3 {
            font-size: 17px;
            color: #0f172a;
            margin: 0 0 12px;
        }

        .vd-card h3 i {
            color: #166534;
            margin-right: 7px;
        }

        .vd-card p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.65;
            min-height: 68px;
        }

        .vd-card a {
            display: inline-block;
            text-decoration: none;
            border: 1px solid #166534;
            border-radius: 9px;
            padding: 10px 15px;
            color: #166534;
            font-weight: 800;
            font-size: 13px;
        }

        .vd-card a:hover {
            background: #166534;
            color: #fff;
        }

        @media (max-width: 1100px) {
            .vd-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .vd-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .vd-content {
                padding: 14px;
            }

            .vd-stats,
            .vd-grid {
                grid-template-columns: 1fr;
            }

            .vd-hero {
                padding: 23px;
            }
        }
    </style>
</head>

<body class="admin-body">

    <?php
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <main class="admin-main vd-main">

        <!-- THANH MENU TRÊN CÙNG -->

        <header class="vd-head">

            <strong>
                HỆ THỐNG VẬN HÀNH VNN TRAVEL
            </strong>

            <div>
                <a
                    href="<?= dbh($siteRoot) ?>index.php"
                    target="_blank"
                    rel="noopener"
                >
                    Xem Website
                </a>

                <a
                    class="logout"
                    href="<?= dbh($adminRoot) ?>logout.php"
                >
                    Đăng xuất
                </a>
            </div>

        </header>

        <div class="vd-content">

            <!-- BANNER CHÀO MỪNG -->

            <section class="vd-hero">

                <small>
                    TRANG QUẢN LÝ HỆ THỐNG
                </small>

                <h1>
                    Chào mừng trở lại!
                </h1>

                <p>
                    Điều hành tour trekking, kho hàng dã ngoại,
                    đơn hàng và chăm sóc khách hàng của VNN Travel.
                </p>

            </section>

            <!-- THÔNG BÁO CHỜ XỬ LÝ -->

            <section class="vd-alert">

                <div>
                    <i class="fa-solid fa-bell"></i>

                    <strong>Cần xử lý:</strong>

                    <?= (int) $pendingTours ?>
                    đăng ký tour và

                    <?= (int) $pendingOrders ?>
                    đơn hàng đang chờ xác nhận.
                </div>

                <div>
                    <a href="<?= dbh($adminRoot) ?>bookings/">
                        Duyệt Tour
                    </a>

                    <a href="<?= dbh($adminRoot) ?>orders/">
                        Duyệt Đơn Hàng
                    </a>
                </div>

            </section>

            <!-- THỐNG KÊ TỔNG QUAN -->

            <section class="vd-stats">

                <div class="vd-stat">
                    <div class="vd-label">
                        Khách hàng
                    </div>

                    <div class="vd-value">
                        <?= (int) $customers ?>
                    </div>
                </div>

                <div class="vd-stat">
                    <div class="vd-label">
                        Tour hoạt động
                    </div>

                    <div class="vd-value">
                        <?= (int) $tours ?>
                    </div>
                </div>

                <div class="vd-stat">
                    <div class="vd-label">
                        Tồn kho dụng cụ
                    </div>

                    <div class="vd-value">
                        <?= (int) $stock ?>
                    </div>

                    <div class="vd-muted">
                        <?= (int) $lowStock ?>
                        sản phẩm sắp hết
                    </div>
                </div>

                <div class="vd-stat">
                    <div class="vd-label">
                        Doanh thu hoàn thành
                    </div>

                    <div class="vd-value">
                        <?= format_price($totalRevenue) ?>
                    </div>

                    <div class="vd-muted">
                        Chỉ tính đơn hoàn tất và tour đã thanh toán.
                    </div>
                </div>

            </section>

            <!-- CÁC CHỨC NĂNG QUẢN LÝ -->

            <h2>Chức năng quản lý</h2>

            <section class="vd-grid">

                <div class="vd-card">
                    <h3>
                        <i class="fa-solid fa-headset"></i>
                        Tư vấn khách hàng
                    </h3>

                    <p>
                        Trao đổi và hỗ trợ trực tuyến
                        cho khách hàng.
                    </p>

                    <a href="<?= dbh($adminRoot) ?>chat/">
                        Mở chat
                    </a>
                </div>

                <div class="vd-card">
                    <h3>
                        <i class="fa-solid fa-person-hiking"></i>
                        Quản lý Tour
                    </h3>

                    <p>
                        Cập nhật nội dung các chuyến trekking
                        và lịch trình.
                    </p>

                    <a href="<?= dbh($adminRoot) ?>tours/">
                        Quản lý tour
                    </a>
                </div>

                <div class="vd-card">
                    <h3>
                        <i class="fa-solid fa-chart-line"></i>
                        Doanh thu & Báo cáo
                    </h3>

                    <p>
                        Xem tổng doanh thu, lọc theo thời gian,
                        tour và danh mục hàng.
                    </p>

                    <a href="<?= dbh($adminRoot) ?>revenue.php">
                        Xem báo cáo
                    </a>
                </div>

                <div class="vd-card">
                    <h3>
                        <i class="fa-solid fa-warehouse"></i>
                        Dụng cụ & Tồn kho
                    </h3>

                    <p>
                        Quản lý sản phẩm, tồn kho và danh mục.
                    </p>

                    <a href="<?= dbh($adminRoot) ?>products/">
                        Quản lý kho
                    </a>
                </div>

                <div class="vd-card">
                    <h3>
                        <i class="fa-solid fa-ticket"></i>
                        Duyệt tour
                    </h3>

                    <p>
                        Duyệt đăng ký, phân công hướng dẫn viên
                        và xác nhận thanh toán.
                    </p>

                    <a href="<?= dbh($adminRoot) ?>bookings/">
                        Duyệt tour
                    </a>
                </div>

                <div class="vd-card">
                    <h3>
                        <i class="fa-solid fa-truck"></i>
                        Đơn hàng
                    </h3>

                    <p>
                        Quản lý đơn mua hàng
                        và trạng thái giao hàng.
                    </p>

                    <a href="<?= dbh($adminRoot) ?>orders/">
                        Duyệt đơn hàng
                    </a>
                </div>

            </section>

        </div>

    </main>

</body>
</html>
