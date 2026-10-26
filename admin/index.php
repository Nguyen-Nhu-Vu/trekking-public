<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();

$current_page = 'dashboard';
$page_title = 'Dashboard - VNN Travel';

if (!function_exists('vnnDashH')) {
    function vnnDashH($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('vnnDashCount')) {
    function vnnDashCount(PDO $pdo, string $sql): int {
        try {
            return (int)($pdo->query($sql)->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('Dashboard count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('vnnDashSum')) {
    function vnnDashSum(PDO $pdo, string $sql): float {
        try {
            return (float)($pdo->query($sql)->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('Dashboard sum: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('vnnDashMoney')) {
    function vnnDashMoney($value): string {
        return number_format((float)$value, 0, ',', '.') . ' đ';
    }
}

// =======================================
// THONG KE DASHBOARD
// =======================================

$customerCount = vnnDashCount(
    $pdo,
    "SELECT COUNT(*) FROM users WHERE role = 'customer'"
);

$tourCount = vnnDashCount(
    $pdo,
    "SELECT COUNT(*) FROM tours WHERE status = 1"
);

$productStockTotal = vnnDashSum(
    $pdo,
    "SELECT COALESCE(SUM(stock_quantity),0) FROM products"
);

$pendingTourCount = vnnDashCount(
    $pdo,
    "SELECT COUNT(*) FROM tour_bookings
     WHERE status = 'Chờ xác nhận'"
);

$pendingOrderCount = vnnDashCount(
    $pdo,
    "SELECT COUNT(*) FROM orders
     WHERE status = 'Chờ xác nhận'"
);

// So lieu doanh thu duoc lay nhu ban Dashboard gan nhat.
// Trang revenue.php la noi tong hop day du doanh thu tour va ban hang.
$revenueDone = vnnDashSum(
    $pdo,
    "SELECT COALESCE(SUM(total_amount),0)
     FROM orders
     WHERE status IN ('Đã giao','Đã hoàn thành','completed','delivered')"
);

// Duong dan
$baseUrl = rtrim(BASE_URL, '/') . '/';
$adminUrl = $baseUrl . 'admin/';

// Danh sach o Truy cap nhanh
$quickItems = [
    [
        'title' => 'Tư vấn khách hàng',
        'description' => 'Tiếp nhận và trả lời tin nhắn.',
        'icon' => 'fa-headset',
        'link' => $adminUrl . 'chat/'
    ],
    [
        'title' => 'Quản lý Tour',
        'description' => 'Quản lý thông tin các tour trekking.',
        'icon' => 'fa-person-hiking',
        'link' => $adminUrl . 'tours/'
    ],
    [
        'title' => 'Doanh thu & Báo cáo',
        'description' => 'Xem doanh thu và thống kê.',
        'icon' => 'fa-chart-line',
        'link' => $adminUrl . 'revenue.php'
    ],
    [
        'title' => 'Dụng cụ & Tồn kho',
        'description' => 'Quản lý sản phẩm và kho hàng.',
        'icon' => 'fa-warehouse',
        'link' => $adminUrl . 'products/'
    ],
    [
        'title' => 'Duyệt Tour',
        'description' => 'Xử lý đăng ký tour của khách.',
        'icon' => 'fa-ticket',
        'link' => $adminUrl . 'bookings/'
    ],
    [
        'title' => 'Đơn hàng',
        'description' => 'Xử lý đơn mua dụng cụ trekking.',
        'icon' => 'fa-box',
        'link' => $adminUrl . 'orders/'
    ]
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= vnnDashH($page_title) ?></title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

<style>
* {
    box-sizing: border-box;
}

.vnn-dashboard {
    flex: 1 1 auto;
    min-width: 0;
    background: #f0f5fa;
    color: #102744;
    font-family: inherit;
}

/* =================================
   THANH TREN CUNG
   ================================= */

.vnn-dashboard-header {
    min-height: 82px;
    padding: 19px 29px;
    background: white;
    border-bottom: 1px solid #e2e8f0;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.vnn-dashboard-header h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.4;
    color: #0e2d52;
    letter-spacing: 0;
}

.vnn-dashboard-logout {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;

    padding: 11px 18px;
    min-height: 42px;

    border-radius: 10px;
    background: #ef4444;
    color: white;

    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.vnn-dashboard-logout:hover {
    background: #dc2626;
    color: white;
}

/* =================================
   NOI DUNG CHINH
   ================================= */

.vnn-dashboard-body {
    padding: 25px 29px 35px;
    max-width: 1800px;
    margin: 0 auto;
}

/* =================================
   BANNER
   ================================= */

.vnn-dashboard-hero {
    min-height: 225px;
    padding: 31px 35px;

    border-radius: 20px;
    overflow: hidden;

    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    gap: 16px;

    background:
        linear-gradient(
            90deg,
            rgba(8, 25, 53, .87) 0%,
            rgba(12, 39, 64, .71) 44%,
            rgba(12, 93, 59, .56) 100%
        ),
        url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1400&q=80')
        center / cover no-repeat;
}

.vnn-dashboard-hero-label {
    display: inline-flex;
    align-items: center;
    padding: 9px 15px;
    border-radius: 25px;
    background: #f97316;
    color: white;
    font-size: 12px;
    font-weight: 800;
}

.vnn-dashboard-hero h2 {
    margin: 0;
    color: white;
    font-size: clamp(26px, 2.7vw, 36px);
    line-height: 1.2;
    font-weight: 800;
}

.vnn-dashboard-hero p {
    margin: 0;
    max-width: 880px;

    font-size: 14px;
    line-height: 1.7;
    color: #f8fafc;
    font-weight: 400;
}

/* =================================
   THONG BAO CHO XU LY
   ================================= */

.vnn-dashboard-notice {
    margin-top: 20px;
    padding: 16px 20px;

    border-radius: 14px;
    background: #fff7ed;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.vnn-dashboard-notice-text {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    color: #c2410c;
    font-size: 13px;
    line-height: 1.5;
}

.vnn-dashboard-notice-text strong {
    font-size: 13px;
    font-weight: 800;
}

.vnn-dashboard-notice-actions {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
}

.vnn-dashboard-notice-actions a {
    display: inline-flex;
    justify-content: center;
    align-items: center;

    min-height: 38px;
    padding: 9px 13px;

    border-radius: 9px;
    color: white;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.vnn-dashboard-notice-actions .btn-tour {
    background: #ea580c;
}

.vnn-dashboard-notice-actions .btn-order {
    background: #166534;
}

/* =================================
   4 O THONG KE
   ================================= */

.vnn-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.vnn-dashboard-stat {
    min-width: 0;
    padding: 19px 21px;
    border: 1px solid #dce6f0;
    border-radius: 15px;
    background: white;
}

.vnn-dashboard-stat-label {
    color: #5b718d;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.5;
    text-transform: uppercase;
}

.vnn-dashboard-stat-value {
    margin-top: 13px;
    margin-bottom: 7px;
    color: #102345;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.vnn-dashboard-stat-value.money {
    font-size: 25px;
}

.vnn-dashboard-stat-desc {
    color: #8292a8;
    font-size: 11px;
    line-height: 1.5;
}

/* =================================
   TRUY CAP NHANH DANG LUOI
   ================================= */

.vnn-dashboard-section {
    margin-top: 21px;
    padding: 21px;

    border: 1px solid #dce6f0;
    border-radius: 17px;
    background: white;
}

.vnn-dashboard-section h3 {
    margin: 0 0 18px;
    color: #102345;
    font-size: 19px;
    font-weight: 800;
}

/* 3 cot, 2 hang */

.vnn-dashboard-quick-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.vnn-dashboard-quick-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;

    min-width: 0;
    min-height: 135px;
    padding: 17px;

    border: 1px solid #e2e8f0;
    border-radius: 13px;
    background: #f9fbfd;

    text-decoration: none;
    transition:
        border-color .2s,
        background .2s,
        transform .2s;
}

.vnn-dashboard-quick-card:hover {
    background: #f0fdf4;
    border-color: #86efac;
    transform: translateY(-2px);
    text-decoration: none;
}

.vnn-dashboard-quick-icon {
    display: inline-flex;
    justify-content: center;
    align-items: center;

    width: 32px;
    height: 32px;

    border-radius: 9px;
    background: #e8f5ed;
    color: #166534;
    font-size: 15px;
}

.vnn-dashboard-quick-title {
    margin: 0;
    color: #153d2a;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.45;
}

.vnn-dashboard-quick-desc {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 11px;
    line-height: 1.5;
}

.vnn-dashboard-quick-open {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    padding: 6px 10px;
    border: 1px solid #bad8c7;
    border-radius: 7px;

    background: white;
    color: #166534;

    font-size: 11px;
    font-weight: 800;
}

/* =================================
   RESPONSIVE
   ================================= */

@media (max-width: 1200px) {
    .vnn-dashboard-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .vnn-dashboard-quick-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .vnn-dashboard-header {
        padding: 16px;
    }

    .vnn-dashboard-header h1 {
        font-size: 16px;
    }

    .vnn-dashboard-body {
        padding: 15px;
    }

    .vnn-dashboard-hero {
        min-height: 200px;
        padding: 22px;
    }

    .vnn-dashboard-hero h2 {
        font-size: 27px;
    }

    .vnn-dashboard-notice {
        align-items: flex-start;
    }

    .vnn-dashboard-stats {
        gap: 10px;
    }

    .vnn-dashboard-stat {
        padding: 15px;
    }

    .vnn-dashboard-stat-value {
        font-size: 25px;
    }

    .vnn-dashboard-stat-value.money {
        font-size: 19px;
    }

    .vnn-dashboard-section {
        padding: 15px;
    }

    .vnn-dashboard-quick-grid {
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .vnn-dashboard-stats,
    .vnn-dashboard-quick-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="admin-body">

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main vnn-dashboard">

    <!-- THANH TREN -->

    <header class="vnn-dashboard-header">

        <h1>HỆ THỐNG QUẢN LÝ VNN TRAVEL</h1>

        <a
            href="<?= vnnDashH($baseUrl . 'auth/logout.php') ?>"
            class="vnn-dashboard-logout"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
            Đăng xuất
        </a>

    </header>

    <div class="vnn-dashboard-body">

        <!-- BANNER -->

        <section class="vnn-dashboard-hero">

            <span class="vnn-dashboard-hero-label">
                TRANG QUẢN LÝ HỆ THỐNG
            </span>

            <h2>Chào mừng trở lại!</h2>

            <p>
                Điều hành tour trekking, kho hàng dã ngoại,
                đơn hàng và chăm sóc khách hàng của VNN Travel.
            </p>

        </section>

        <!-- THONG BAO CHO XU LY -->

        <section class="vnn-dashboard-notice">

            <div class="vnn-dashboard-notice-text">

                <i class="fa-solid fa-bell"></i>

                <span>
                    <strong>Cần xử lý:</strong>
                    <?= (int)$pendingTourCount ?> đăng ký tour
                    và <?= (int)$pendingOrderCount ?> đơn hàng
                    đang chờ xác nhận.
                </span>

            </div>

            <div class="vnn-dashboard-notice-actions">

                <a
                    class="btn-tour"
                    href="<?= vnnDashH($adminUrl . 'bookings/') ?>"
                >
                    Duyệt Tour
                </a>

                <a
                    class="btn-order"
                    href="<?= vnnDashH($adminUrl . 'orders/') ?>"
                >
                    Duyệt Đơn Hàng
                </a>

            </div>

        </section>

        <!-- 4 O THONG KE -->

        <section class="vnn-dashboard-stats">

            <div class="vnn-dashboard-stat">

                <div class="vnn-dashboard-stat-label">
                    Khách hàng
                </div>

                <div class="vnn-dashboard-stat-value">
                    <?= (int)$customerCount ?>
                </div>

                <div class="vnn-dashboard-stat-desc">
                    Tổng số tài khoản khách hàng
                </div>

            </div>

            <div class="vnn-dashboard-stat">

                <div class="vnn-dashboard-stat-label">
                    Tour hoạt động
                </div>

                <div class="vnn-dashboard-stat-value">
                    <?= (int)$tourCount ?>
                </div>

                <div class="vnn-dashboard-stat-desc">
                    Tour đang hoạt động
                </div>

            </div>

            <div class="vnn-dashboard-stat">

                <div class="vnn-dashboard-stat-label">
                    Tồn kho dụng cụ
                </div>

                <div class="vnn-dashboard-stat-value">
                    <?= (int)$productStockTotal ?>
                </div>

                <div class="vnn-dashboard-stat-desc">
                    Tổng số lượng sản phẩm tồn kho
                </div>

            </div>

            <div class="vnn-dashboard-stat">

                <div class="vnn-dashboard-stat-label">
                    Doanh thu hoàn thành
                </div>

                <div class="vnn-dashboard-stat-value money">
                    <?= vnnDashH(vnnDashMoney($revenueDone)) ?>
                </div>

                <div class="vnn-dashboard-stat-desc">
                    Doanh thu đơn hàng hoàn thành
                </div>

            </div>

        </section>

        <!-- TRUY CAP NHANH 6 O VUONG -->

        <section class="vnn-dashboard-section">

            <h3>
                <i class="fa-solid fa-table-cells-large"></i>
                Truy cập nhanh
            </h3>

            <div class="vnn-dashboard-quick-grid">

                <?php foreach ($quickItems as $item): ?>

                    <a
                        class="vnn-dashboard-quick-card"
                        href="<?= vnnDashH($item['link']) ?>"
                    >

                        <div class="vnn-dashboard-quick-icon">
                            <i class="fa-solid <?= vnnDashH($item['icon']) ?>"></i>
                        </div>

                        <div>

                            <h4 class="vnn-dashboard-quick-title">
                                <?= vnnDashH($item['title']) ?>
                            </h4>

                            <p class="vnn-dashboard-quick-desc">
                                <?= vnnDashH($item['description']) ?>
                            </p>

                        </div>

                        <span class="vnn-dashboard-quick-open">
                            Mở mục
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>

                    </a>

                <?php endforeach; ?>

            </div>

        </section>

    </div>

</main>

</body>
</html>
