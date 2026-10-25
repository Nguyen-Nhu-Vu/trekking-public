<?php
// ==================================================
// VNN TRAVEL - TRANG QUAN LY TONG QUAN
// File: admin/index.php
// ==================================================

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();

$current_page = 'dashboard';
$page_title = 'Hệ Thống Quản Lý - VNN Travel';

// ==================================================
// HAM HO TRO
// ==================================================

function dashboardEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function dashboardCount(PDO $pdo, string $sql)
{
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log(
            'Dashboard count: ' . $e->getMessage()
        );
        return 0;
    }
}

function dashboardRevenue(PDO $pdo, string $sql)
{
    try {
        return (float) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log(
            'Dashboard revenue: ' . $e->getMessage()
        );
        return 0;
    }
}

// ==================================================
// THONG KE KHACH HANG
// ==================================================

$totalCustomers = dashboardCount(
    $pdo,
    "SELECT COUNT(*)
     FROM users
     WHERE role = 'customer'"
);

// ==================================================
// THONG KE TOUR
// ==================================================

$totalTours = dashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM tours"
);

$pendingBookings = dashboardCount(
    $pdo,
    "SELECT COUNT(*)
     FROM tour_bookings
     WHERE status = 'Chờ xác nhận'"
);

// ==================================================
// THONG KE DON HANG
// ==================================================

$pendingOrders = dashboardCount(
    $pdo,
    "SELECT COUNT(*)
     FROM orders
     WHERE status = 'Chờ xác nhận'"
);

// ==================================================
// THONG KE KHO
// ==================================================

$totalProducts = dashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM products"
);

$totalStock = dashboardCount(
    $pdo,
    "SELECT COALESCE(SUM(stock), 0)
     FROM products"
);

$lowStock = dashboardCount(
    $pdo,
    "SELECT COUNT(*)
     FROM products
     WHERE stock <= 5"
);

// ==================================================
// DOANH THU DA HOAN THANH
// ==================================================

// Don hang chi tinh khi da giao.
// Tour chi tinh khi da thanh toan hoac hoan thanh.

$tourRevenue = dashboardRevenue(
    $pdo,
    "SELECT COALESCE(SUM(total_price), 0)
     FROM tour_bookings
     WHERE status IN (
         'Đã thanh toán',
         'Đã hoàn thành'
     )"
);

$orderRevenue = dashboardRevenue(
    $pdo,
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM orders
     WHERE status = 'Đã giao'"
);

$totalRevenue = $tourRevenue + $orderRevenue;

$adminBase = rtrim(BASE_URL, '/') . '/admin/';

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= dashboardEscape($page_title) ?>
    </title>

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

    <style>
        .vnn-dashboard {
            background: #f1f5f9;
            min-height: 100vh;
        }

        .vnn-dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .vnn-dashboard-header h2 {
            font-size: 17px;
            color: #1e3a5f;
            font-weight: 800;
            margin: 0;
        }

        .vnn-header-actions {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .vnn-header-actions a {
            text-decoration: none;
        }

        .vnn-visit-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            border: 2px solid #166534;
            border-radius: 25px;
            color: #166534;
            font-size: 13px;
            font-weight: 800;
            background: white;
        }

        .vnn-logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ef4444;
            color: white;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 13px;
        }

        .vnn-welcome {
            position: relative;
            border-radius: 20px;
            margin-bottom: 30px;
            padding: 43px 38px;
            min-height: 245px;
            color: white;
            overflow: hidden;
            background:
                linear-gradient(
                    100deg,
                    rgba(15, 23, 42, .94),
                    rgba(22, 101, 52, .75)
                ),
                url("https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1600&q=85")
                center / cover;
        }

        .vnn-welcome-badge {
            display: inline-block;
            padding: 9px 16px;
            background: #f97316;
            border-radius: 25px;
            color: white;
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 17px;
        }

        .vnn-welcome h1 {
            font-size: clamp(25px, 3vw, 37px);
            margin: 0 0 14px;
            line-height: 1.3;
            font-weight: 900;
        }

        .vnn-welcome p {
            margin: 0;
            font-size: 15px;
            line-height: 1.9;
            max-width: 730px;
            color: #f1f5f9;
        }

        .vnn-alert-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 23px;
            background: #fff7ed;
            border-radius: 15px;
            margin-bottom: 27px;
        }

        .vnn-alert-panel p {
            margin: 0;
            color: #c2410c;
            font-size: 15px;
        }

        .vnn-alert-panel i {
            margin-right: 7px;
        }

        .vnn-alert-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .vnn-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 11px 17px;
            border-radius: 10px;
            background: #166534;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
        }

        .vnn-action-btn.orange {
            background: #ea580c;
        }

        .vnn-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 21px;
            margin-bottom: 30px;
        }

        .vnn-stat-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            min-width: 0;
            padding: 25px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
        }

        .vnn-stat-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .vnn-stat-value {
            color: #0f172a;
            font-size: 29px;
            font-weight: 900;
            margin-top: 12px;
            overflow-wrap: anywhere;
        }

        .vnn-stat-value.red {
            color: #dc2626;
            font-size: 25px;
        }

        .vnn-stat-icon {
            width: 55px;
            height: 55px;
            flex-shrink: 0;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .vnn-stat-icon.blue {
            background: #e0f2fe;
            color: #0284c7;
        }

        .vnn-stat-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .vnn-stat-icon.orange {
            background: #ffedd5;
            color: #ea580c;
        }

        .vnn-stat-icon.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .vnn-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
            margin-bottom: 30px;
        }

        .vnn-feature-card {
            padding: 28px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            min-width: 0;
        }

        .vnn-feature-card h3 {
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 15px;
        }

        .vnn-feature-card h3 i {
            color: #16a34a;
            margin-right: 8px;
        }

        .vnn-feature-card p {
            color: #64748b;
            line-height: 1.8;
            font-size: 14px;
            min-height: 80px;
        }

        .vnn-feature-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            padding: 11px 17px;
            color: #166534;
            background: white;
            border: 2px solid #166534;
            border-radius: 10px;
            font-weight: 800;
            font-size: 13px;
        }

        .vnn-feature-link:hover {
            background: #166534;
            color: white;
        }

        .vnn-section-title {
            color: #0f172a;
            font-size: 21px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .vnn-small-note {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
        }

        @media (max-width: 1300px) {
            .vnn-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .vnn-dashboard-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {
            .vnn-stats,
            .vnn-dashboard-grid {
                grid-template-columns: 1fr;
            }

            .vnn-welcome {
                padding: 25px;
            }

            .vnn-feature-card p {
                min-height: 0;
            }
        }
    </style>

</head>

<body class="admin-body">

    <?php
    // MENU QUAN LY CHUNG
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <main class="admin-main vnn-dashboard">

        <!-- THANH TREN CUNG -->

        <header class="admin-topbar vnn-dashboard-header">

            <h2>
                HỆ THỐNG VẬN HÀNH VNN TRAVEL
            </h2>

            <div class="vnn-header-actions">

                <a
                    class="vnn-visit-btn"
                    href="<?= dashboardEscape(rtrim(BASE_URL, '/') . '/index.php') ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    Xem Website
                </a>

                <span>
                    Xin chào,
                    <strong>Quản Lý Hệ Thống</strong>
                </span>

                <a
                    class="vnn-logout-btn"
                    href="<?= dashboardEscape($adminBase . 'logout.php') ?>"
                >
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Đăng xuất
                </a>

            </div>

        </header>

        <div class="admin-content">

            <!-- BANNER CHAO MUNG -->

            <section class="vnn-welcome">

                <div class="vnn-welcome-badge">
                    TRANG QUẢN LÝ HỆ THỐNG
                </div>

                <h1>
                    Chào mừng trở lại, Quản Lý Hệ Thống!
                </h1>

                <p>
                    Hệ thống điều hành và giám sát toàn bộ
                    hoạt động tour trekking, kho hàng dã ngoại
                    và chăm sóc khách hàng của
                    <strong>VNN Travel.</strong>
                </p>

            </section>

            <!-- THONG BAO CHO XU LY -->

            <section class="vnn-alert-panel">

                <p>
                    <i class="fa-solid fa-bell"></i>

                    <strong>Cần xử lý:</strong>

                    Có
                    <strong><?= $pendingBookings ?></strong>
                    đăng ký tour mới và

                    <strong><?= $pendingOrders ?></strong>
                    đơn hàng đang chờ xác nhận!
                </p>

                <div class="vnn-alert-actions">

                    <a
                        href="<?= dashboardEscape($adminBase . 'bookings/') ?>"
                        class="vnn-action-btn orange"
                    >
                        <i class="fa-solid fa-ticket"></i>
                        Duyệt Tour
                    </a>

                    <a
                        href="<?= dashboardEscape($adminBase . 'orders/') ?>"
                        class="vnn-action-btn"
                    >
                        <i class="fa-solid fa-box"></i>
                        Duyệt Đơn Hàng
                    </a>

                </div>

            </section>

            <!-- THONG KE TONG QUAN -->

            <div class="vnn-stats">

                <div class="vnn-stat-card">

                    <div>
                        <div class="vnn-stat-label">
                            Khách hàng
                        </div>

                        <div class="vnn-stat-value">
                            <?= $totalCustomers ?>
                        </div>
                    </div>

                    <div class="vnn-stat-icon blue">
                        <i class="fa-solid fa-users"></i>
                    </div>

                </div>

                <div class="vnn-stat-card">

                    <div>
                        <div class="vnn-stat-label">
                            Tour hoạt động
                        </div>

                        <div class="vnn-stat-value">
                            <?= $totalTours ?>
                        </div>
                    </div>

                    <div class="vnn-stat-icon green">
                        <i class="fa-solid fa-mountain"></i>
                    </div>

                </div>

                <div class="vnn-stat-card">

                    <div>
                        <div class="vnn-stat-label">
                            Tồn kho dụng cụ
                        </div>

                        <div class="vnn-stat-value">
                            <?= $totalStock ?>
                        </div>

                        <div class="vnn-small-note">
                            <?= $totalProducts ?> sản phẩm
                            (<?= $lowStock ?> sắp hết)
                        </div>
                    </div>

                    <div class="vnn-stat-icon orange">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>

                </div>

                <div class="vnn-stat-card">

                    <div>
                        <div class="vnn-stat-label">
                            Tổng doanh thu
                        </div>

                        <div class="vnn-stat-value red">
                            <?= format_price($totalRevenue) ?>
                        </div>
                    </div>

                    <div class="vnn-stat-icon red">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>

                </div>

            </div>

            <!-- CHUC NANG NHANH -->

            <h2 class="vnn-section-title">
                Chức năng quản lý
            </h2>

            <div class="vnn-dashboard-grid">

                <!-- CHAT -->

                <section class="vnn-feature-card">

                    <h3>
                        <i class="fa-solid fa-headset"></i>
                        Tư Vấn Khách Hàng
                    </h3>

                    <p>
                        Trực chatbox 1-1 hỗ trợ tư vấn
                        các cung đường trekking và trang
                        thiết bị dã ngoại cho khách.
                    </p>

                    <a
                        href="<?= dashboardEscape($adminBase . 'chat/') ?>"
                        class="vnn-feature-link"
                    >
                        Mở Chat Trực Tuyến
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </section>

                <!-- QUAN LY TOUR -->

                <section class="vnn-feature-card">

                    <h3>
                        <i class="fa-solid fa-person-hiking"></i>
                        Quản Lý Cung Đường
                    </h3>

                    <p>
                        Cập nhật thông tin các đỉnh núi,
                        điều chỉnh lịch trình chi tiết
                        và quản lý các tour trekking.
                    </p>

                    <a
                        href="<?= dashboardEscape($adminBase . 'tours/') ?>"
                        class="vnn-feature-link"
                    >
                        Quản Lý Tour
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </section>

                <!-- DOANH THU -->

                <section class="vnn-feature-card">

                    <h3>
                        <i class="fa-solid fa-chart-column"></i>
                        Doanh Thu Báo Cáo
                    </h3>

                    <p>
                        Xem thống kê tổng hợp doanh thu
                        từ tour trekking và bán dụng cụ,
                        lọc theo tháng, năm và nguồn doanh thu.
                    </p>

                    <a
                        href="<?= dashboardEscape($adminBase . 'revenue.php') ?>"
                        class="vnn-feature-link"
                    >
                        Xem Báo Cáo
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </section>

                <!-- KHO HANG -->

                <section class="vnn-feature-card">

                    <h3>
                        <i class="fa-solid fa-warehouse"></i>
                        Kho Dụng Cụ Trekking
                    </h3>

                    <p>
                        Quản lý danh mục dụng cụ, kiểm tra tồn kho,
                        thêm sản phẩm mới và điều chỉnh số lượng
                        hàng hóa trực tiếp.
                    </p>

                    <a
                        href="<?= dashboardEscape($adminBase . 'products/') ?>"
                        class="vnn-feature-link"
                    >
                        Quản Lý Kho
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </section>

                <!-- DUYET TOUR -->

                <section class="vnn-feature-card">

                    <h3>
                        <i class="fa-solid fa-ticket"></i>
                        Quản Lý Đăng Ký Tour
                    </h3>

                    <p>
                        Kiểm tra đăng ký từ khách hàng,
                        phân công hướng dẫn viên,
                        xác nhận đặt tour và trạng thái thanh toán.
                    </p>

                    <a
                        href="<?= dashboardEscape($adminBase . 'bookings/') ?>"
                        class="vnn-feature-link"
                    >
                        Duyệt Đăng Ký Tour
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </section>

                <!-- DON HANG -->

                <section class="vnn-feature-card">

                    <h3>
                        <i class="fa-solid fa-truck-fast"></i>
                        Quản Lý Đơn Hàng
                    </h3>

                    <p>
                        Theo dõi đơn đặt mua dụng cụ dã ngoại,
                        xác nhận đơn hàng và cập nhật trạng thái
                        giao hàng cho khách.
                    </p>

                    <a
                        href="<?= dashboardEscape($adminBase . 'orders/') ?>"
                        class="vnn-feature-link"
                    >
                        Duyệt Đơn Hàng
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </section>

            </div>

        </div>

    </main>

</body>
</html>
