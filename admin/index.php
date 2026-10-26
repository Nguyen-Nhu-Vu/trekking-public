<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();

if (!function_exists('adm_h')) {
    function adm_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$current_page = 'dashboard';
$page_title   = 'Dashboard - VNN Travel';

function dashboardCount(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Dashboard count error: ' . $e->getMessage());
        return 0;
    }
}

function dashboardSum(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Dashboard sum error: ' . $e->getMessage());
        return 0;
    }
}

function money_vnd($number): string
{
    return number_format((float)$number, 0, ',', '.') . ' đ';
}

$customerCount     = dashboardCount($pdo, "SELECT COUNT(*) FROM users WHERE role = 'customer'");
$tourCount         = dashboardCount($pdo, "SELECT COUNT(*) FROM tours WHERE status = 1");
$productStockTotal = dashboardSum($pdo, "SELECT COALESCE(SUM(stock_quantity), 0) FROM products");
$revenueDone       = dashboardSum($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status IN ('completed','delivered','Đã giao','Đã hoàn thành')");
$pendingTourCount  = dashboardCount($pdo, "SELECT COUNT(*) FROM tour_bookings WHERE status IN ('pending','Chờ xác nhận')");
$pendingOrderCount = dashboardCount($pdo, "SELECT COUNT(*) FROM orders WHERE status IN ('pending','Chờ xác nhận')");

$recentActivities = [];

try {
    $stmt = $pdo->query("
        SELECT 'booking' AS item_type,
               booking_code AS code,
               customer_name AS name,
               created_at
        FROM tour_bookings
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $recentActivities = array_merge($recentActivities, $rows);
    }
} catch (Throwable $e) {
    error_log('Dashboard recent booking error: ' . $e->getMessage());
}

try {
    $stmt = $pdo->query("
        SELECT 'order' AS item_type,
               order_code AS code,
               customer_name AS name,
               created_at
        FROM orders
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $recentActivities = array_merge($recentActivities, $rows);
    }
} catch (Throwable $e) {
    error_log('Dashboard recent order error: ' . $e->getMessage());
}

usort($recentActivities, function ($a, $b) {
    return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});

$recentActivities = array_slice($recentActivities, 0, 5);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adm_h($page_title) ?></title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * { box-sizing: border-box; }

        .dashboard-main-wrap{
            flex: 1;
            min-width: 0;
            background: #eef3f8;
        }

        .dashboard-topbar{
            background: #ffffff;
            border-bottom: 1px solid #e5ebf3;
            padding: 22px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .dashboard-topbar h1{
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            color: #0f2e57;
            letter-spacing: 0.2px;
        }

        .dashboard-topbar-right{
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logout-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 16px;
            background: #ef4444;
            color: #fff;
            text-decoration: none;
            font-weight: 800;
            font-size: 16px;
            border: none;
            transition: all 0.2s ease;
        }

        .logout-btn:hover{
            background: #dc2626;
            transform: translateY(-1px);
        }

        .dashboard-content{
            padding: 28px 32px 36px;
        }

        .hero-box{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            min-height: 320px;
            padding: 48px 46px;
            background:
                linear-gradient(90deg, rgba(7, 25, 55, 0.82) 0%, rgba(10, 42, 71, 0.70) 34%, rgba(12, 88, 56, 0.54) 100%),
                url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1400&q=80') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 18px;
        }

        .hero-badge{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            padding: 13px 20px;
            border-radius: 999px;
            background: #f97316;
            color: #fff;
            font-size: 16px;
            font-weight: 900;
        }

        .hero-title{
            margin: 0;
            color: #fff;
            font-size: 58px;
            line-height: 1.08;
            font-weight: 900;
            max-width: 760px;
        }

        .hero-desc{
            margin: 0;
            color: rgba(255,255,255,0.95);
            font-size: 20px;
            line-height: 1.7;
            max-width: 900px;
            font-weight: 500;
        }

        .notice-box{
            margin-top: 26px;
            background: #f8f1e7;
            border-radius: 22px;
            padding: 24px 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .notice-text{
            color: #c4550d;
            font-size: 18px;
            line-height: 1.6;
            font-weight: 700;
        }

        .notice-text strong{
            font-size: 19px;
        }

        .notice-actions{
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .notice-btn{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 15px 28px;
            border-radius: 14px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            transition: all 0.2s ease;
        }

        .notice-btn.orange{
            background: #f97316;
        }

        .notice-btn.green{
            background: #166534;
        }

        .notice-btn:hover{
            transform: translateY(-1px);
            opacity: 0.95;
        }

        .stats-grid{
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .stat-card{
            background: #fff;
            border: 1px solid #dbe5f0;
            border-radius: 22px;
            padding: 28px 28px 22px;
            min-height: 170px;
        }

        .stat-label{
            color: #58708f;
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .stat-value{
            color: #0b1f44;
            font-size: 54px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 10px;
        }

        .stat-value.money{
            font-size: 33px;
            line-height: 1.2;
            word-break: break-word;
        }

        .stat-sub{
            color: #7c8ea8;
            font-size: 14px;
            font-weight: 600;
        }

        .section-grid{
            margin-top: 28px;
            display: grid;
            grid-template-columns: 1.35fr 0.95fr;
            gap: 22px;
        }

        .panel-card{
            background: #fff;
            border: 1px solid #dbe5f0;
            border-radius: 22px;
            padding: 24px;
        }

        .panel-title{
            margin: 0 0 18px;
            color: #0b1f44;
            font-size: 22px;
            font-weight: 900;
        }

        .quick-list{
            display: grid;
            gap: 14px;
        }

        .quick-item{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 18px;
            border: 1px solid #e6edf5;
            border-radius: 16px;
            background: #fafcff;
        }

        .quick-item strong{
            color: #17335c;
            font-size: 16px;
            font-weight: 800;
        }

        .quick-item span{
            display: block;
            color: #71819a;
            font-size: 14px;
            margin-top: 5px;
        }

        .mini-btn{
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 12px;
            text-decoration: none;
            background: #0f766e;
            color: #fff;
            font-size: 14px;
            font-weight: 800;
        }

        .activity-list{
            display: grid;
            gap: 14px;
        }

        .activity-item{
            padding: 16px 18px;
            border: 1px solid #e6edf5;
            border-radius: 16px;
            background: #fafcff;
        }

        .activity-item-top{
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .activity-code{
            color: #17335c;
            font-weight: 900;
            font-size: 15px;
        }

        .activity-type{
            font-size: 12px;
            font-weight: 800;
            padding: 7px 10px;
            border-radius: 999px;
            color: #fff;
        }

        .activity-type.booking{
            background: #ea580c;
        }

        .activity-type.order{
            background: #166534;
        }

        .activity-name{
            color: #4f627d;
            font-size: 14px;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .activity-time{
            color: #8da0b7;
            font-size: 13px;
            font-weight: 600;
        }

        @media (max-width: 1400px){
            .stats-grid{
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .section-grid{
                grid-template-columns: 1fr;
            }

            .hero-title{
                font-size: 48px;
            }
        }

        @media (max-width: 900px){
            .dashboard-topbar{
                padding: 18px 16px;
                flex-direction: column;
                align-items: flex-start;
            }

            .dashboard-content{
                padding: 16px;
            }

            .hero-box{
                padding: 30px 22px;
                min-height: 260px;
            }

            .hero-title{
                font-size: 38px;
            }

            .hero-desc{
                font-size: 17px;
            }

            .stats-grid{
                grid-template-columns: 1fr;
            }

            .notice-box{
                align-items: flex-start;
            }

            .notice-actions{
                width: 100%;
            }

            .notice-btn{
                width: 100%;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="dashboard-main-wrap">

    <header class="dashboard-topbar">
        <h1>HỆ THỐNG QUẢN LÝ VNN TRAVEL</h1>

        <div class="dashboard-topbar-right">
            <a href="../auth/logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                Đăng xuất
            </a>
        </div>
    </header>

    <div class="dashboard-content">

        <section class="hero-box">
            <div class="hero-badge">TRANG QUẢN LÝ HỆ THỐNG</div>

            <h2 class="hero-title">Chào mừng trở lại!</h2>

            <p class="hero-desc">
                Điều hành tour trekking, kho hàng dã ngoại, đơn hàng và chăm sóc khách hàng của VNN Travel.
            </p>
        </section>

        <section class="notice-box">
            <div class="notice-text">
                <strong><i class="fa-solid fa-bell"></i> Cần xử lý:</strong>
                <?= (int)$pendingTourCount ?> đăng ký tour và
                <?= (int)$pendingOrderCount ?> đơn hàng đang chờ xác nhận.
            </div>

            <div class="notice-actions">
                <a href="bookings/" class="notice-btn orange">Duyệt Tour</a>
                <a href="orders/" class="notice-btn green">Duyệt Đơn Hàng</a>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Khách hàng</div>
                <div class="stat-value"><?= (int)$customerCount ?></div>
                <div class="stat-sub">Tổng số tài khoản khách hàng</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Tour hoạt động</div>
                <div class="stat-value"><?= (int)$tourCount ?></div>
                <div class="stat-sub">Số tour đang hiển thị / hoạt động</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Tồn kho dụng cụ</div>
                <div class="stat-value"><?= (int)$productStockTotal ?></div>
                <div class="stat-sub">Tổng số lượng sản phẩm trong kho</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Doanh thu hoàn thành</div>
                <div class="stat-value money"><?= adm_h(money_vnd($revenueDone)) ?></div>
                <div class="stat-sub">Tổng giá trị đơn / giao dịch đã hoàn tất</div>
            </div>
        </section>

        <section class="section-grid">
            <div class="panel-card">
                <h3 class="panel-title">Truy cập nhanh</h3>

                <div class="quick-list">
                    <div class="quick-item">
                        <div>
                            <strong>Duyệt đăng ký tour</strong>
                            <span>Xử lý các yêu cầu đăng ký tour từ khách hàng.</span>
                        </div>
                        <a href="bookings/" class="mini-btn">Mở</a>
                    </div>

                    <div class="quick-item">
                        <div>
                            <strong>Duyệt đơn hàng</strong>
                            <span>Xác nhận và cập nhật trạng thái đơn hàng.</span>
                        </div>
                        <a href="orders/" class="mini-btn">Mở</a>
                    </div>

                    <div class="quick-item">
                        <div>
                            <strong>Quản lý tour</strong>
                            <span>Thêm, sửa, ẩn hiện và theo dõi danh sách tour.</span>
                        </div>
                        <a href="tours/" class="mini-btn">Mở</a>
                    </div>

                    <div class="quick-item">
                        <div>
                            <strong>Dụng cụ, kho & danh mục</strong>
                            <span>Quản lý sản phẩm trekking và số lượng tồn kho.</span>
                        </div>
                        <a href="products/" class="mini-btn">Mở</a>
                    </div>
                </div>
            </div>

            <div class="panel-card">
                <h3 class="panel-title">Hoạt động gần đây</h3>

                <div class="activity-list">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $item): ?>
                            <div class="activity-item">
                                <div class="activity-item-top">
                                    <div class="activity-code">
                                        <?= adm_h($item['code'] ?? '') ?>
                                    </div>

                                    <div class="activity-type <?= ($item['item_type'] ?? '') === 'booking' ? 'booking' : 'order' ?>">
                                        <?= ($item['item_type'] ?? '') === 'booking' ? 'Đăng ký tour' : 'Đơn hàng' ?>
                                    </div>
                                </div>

                                <div class="activity-name">
                                    <?= adm_h($item['name'] ?? 'Khách hàng') ?>
                                </div>

                                <div class="activity-time">
                                    <?=
                                        !empty($item['created_at'])
                                        ? adm_h(date('d/m/Y H:i', strtotime($item['created_at'])))
                                        : ''
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-name">Chưa có hoạt động gần đây.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </div>
</main>

</body>
</html>
