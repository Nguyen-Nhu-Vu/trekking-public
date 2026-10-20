<?php
// trekking/admin/index.php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireStaff(); // Cho phép Staff, Manager, Admin vào khu vực quản trị

$role = getRole();

// Đếm đơn chờ duyệt
$pending_bookings = $pdo->query("SELECT COUNT(*) FROM tour_bookings WHERE status = 'Chờ xác nhận'")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Chờ xác nhận'")->fetchColumn();

// Thống kê tổng quan
$total_customers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$total_tours = $pdo->query("SELECT COUNT(*) FROM tours")->fetchColumn();
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_stock = $pdo->query("SELECT IFNULL(SUM(stock), 0) FROM products")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 5")->fetchColumn();

// Thống kê doanh thu
$tour_revenue = $pdo->query("SELECT IFNULL(SUM(total_price), 0) FROM tour_bookings WHERE status != 'Đã hủy'")->fetchColumn();
$order_revenue = $pdo->query("SELECT IFNULL(SUM(total_amount), 0) FROM orders WHERE status != 'Đã hủy'")->fetchColumn();
$total_revenue = $tour_revenue + $order_revenue;

$current_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ Thống Quản Lý - VNN Travel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* BANNER HÌNH NÚI TRANG TRỌNG DÀNH CHO TRANG QUẢN LÝ */
        .admin-hero-banner {
            position: relative;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.75), rgba(30, 86, 49, 0.8)), url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1600') center/cover no-repeat;
            border-radius: 16px;
            padding: 40px 35px;
            color: #ffffff;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
            overflow: hidden;
        }
        .admin-hero-badge {
            display: inline-block;
            background: #ea580c;
            color: white;
            font-size: 0.8rem;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 20px;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .admin-hero-title {
            font-size: 2.2rem;
            font-weight: 900;
            line-height: 1.25;
            margin-bottom: 8px;
        }
        .admin-hero-desc {
            font-size: 1rem;
            color: #e2e8f0;
            max-width: 650px;
            line-height: 1.6;
        }

        /* THẺ THỐNG KÊ HIỆN ĐẠI */
        .stat-grid-modern {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        @media (max-width: 1024px) {
            .stat-grid-modern { grid-template-columns: repeat(2, 1fr); }
        }
        .stat-card-m {
            background: #ffffff;
            border-radius: 14px;
            padding: 22px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-card-m h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0f172a;
            margin: 4px 0 0 0;
        }
        .stat-card-m p {
            font-size: 0.82rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin: 0;
        }
        .stat-icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        /* LỐI TẮT NHANH */
        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 900px) {
            .quick-action-grid { grid-template-columns: 1fr; }
        }
        .quick-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            transition: transform 0.2s ease;
        }
        .quick-card:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <strong>HỆ THỐNG VẬN HÀNH VNN TRAVEL</strong>
            </div>
            <div style="display:flex; align-items:center; gap:15px;">
                <a href="../index.php" target="_blank" class="btn btn-outline btn-sm" style="border-radius:20px;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem Website
                </a>
                <span>Xin chào, <strong><?= htmlspecialchars($_SESSION['fullname']) ?></strong> (<?= ucfirst($role) ?>)</span>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
            </div>
        </div>

        <div class="admin-content">
            <!-- BANNER NỀN NÚI CHUYÊN NGHIỆP TRANG QUẢN LÝ -->
            <div class="admin-hero-banner">
                <span class="admin-hero-badge">
                    <?= isAdmin() ? 'HỆ THỐNG QUẢN TRỊ ADMIN' : (isManager() ? 'TRANG QUẢN LÝ HỆ THỐNG' : 'BÀN LÀM VIỆC NHÂN VIÊN') ?>
                </span>
                <h1 class="admin-hero-title">Chào mừng trở lại, <?= htmlspecialchars($_SESSION['fullname']) ?>!</h1>
                <p class="admin-hero-desc">
                    Hệ thống điều hành và giám sát toàn bộ hoạt động tour trekking, kho hàng dã ngoại và chăm sóc khách hàng của <strong>VNN Travel</strong>.
                </p>
            </div>

            <!-- THÔNG BÁO XỬ LÝ ĐƠN CHỜ DUYỆT -->
            <?php if ($pending_bookings > 0 || $pending_orders > 0): ?>
                <div class="alert alert-error" style="background:#fff7ed; border-color:#fdba74; color:#c2410c; display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; border-radius:12px; padding:18px 22px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <i class="fa-solid fa-bell" style="font-size:1.4rem;"></i>
                        <div>
                            <strong>Cần xử lý ngay:</strong> Có <strong><?= $pending_bookings ?></strong> đăng ký tour mới và <strong><?= $pending_orders ?></strong> đơn hàng đang chờ xác nhận!
                        </div>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <a href="bookings/" class="btn btn-sm btn-accent"><i class="fa-solid fa-ticket"></i> Duyệt Tour</a>
                        <a href="orders/" class="btn btn-sm btn-primary"><i class="fa-solid fa-box-open"></i> Duyệt Đơn Hàng</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 4 THẺ THỐNG KÊ TỔNG QUAN -->
            <div class="stat-grid-modern">
                <div class="stat-card-m">
                    <div>
                        <p>Khách Hàng</p>
                        <h3><?= $total_customers ?></h3>
                    </div>
                    <div class="stat-icon-circle" style="background:#e0f2fe; color:#0284c7;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>

                <div class="stat-card-m">
                    <div>
                        <p>Tour Hoạt Động</p>
                        <h3><?= $total_tours ?></h3>
                    </div>
                    <div class="stat-icon-circle" style="background:#dcfce7; color:#16a34a;">
                        <i class="fa-solid fa-mountain"></i>
                    </div>
                </div>

                <div class="stat-card-m">
                    <div>
                        <p>Tồn Kho Dụng Cụ</p>
                        <h3><?= $total_stock ?> <small style="font-size:0.85rem; color:#dc2626;">(<?= $low_stock ?> sắp hết)</small></h3>
                    </div>
                    <div class="stat-icon-circle" style="background:#ffedd5; color:#ea580c;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>

                <div class="stat-card-m">
                    <div>
                        <p>Tổng Doanh Thu</p>
                        <h3 style="font-size:1.35rem; color:#dc2626;"><?= format_price($total_revenue) ?></h3>
                    </div>
                    <div class="stat-icon-circle" style="background:#fee2e2; color:#dc2626;">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
            </div>

            <!-- CÁC LỐI TẮT NGHIỆP VỤ NHANH -->
            <div class="quick-action-grid">
                <div class="quick-card">
                    <h4 style="color:#0f172a; margin-bottom:8px;"><i class="fa-solid fa-headset" style="color:#16a34a;"></i> Tư Vấn Khách Hàng</h4>
                    <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin-bottom:18px;">
                        Trực chatbox 1-1 hỗ trợ tư vấn các cung đường trekking và trang thiết bị dã ngoại cho khách.
                    </p>
                    <a href="chat/" class="btn btn-outline btn-sm" style="border-radius:20px;"><i class="fa-solid fa-comments"></i> Vào phòng chat</a>
                </div>

                <div class="quick-card">
                    <h4 style="color:#0f172a; margin-bottom:8px;"><i class="fa-solid fa-person-hiking" style="color:#ea580c;"></i> Quản Lý Cung Đường</h4>
                    <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin-bottom:18px;">
                        Cập nhật thông tin các đỉnh núi, điều chỉnh lịch trình chi tiết và phân công Leader dẫn đoàn.
                    </p>
                    <a href="tours/" class="btn btn-outline btn-sm" style="border-radius:20px;"><i class="fa-solid fa-list-check"></i> Quản lý tour</a>
                </div>

                <div class="quick-card">
                    <h4 style="color:#0f172a; margin-bottom:8px;"><i class="fa-solid fa-money-bill-trend-up" style="color:#0284c7;"></i> Doanh Thu Báo Cáo</h4>
                    <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin-bottom:18px;">
                        Xem thống kê chi tiết tách biệt giữa nguồn thu từ tour leo núi và nguồn thu bán dụng cụ.
                    </p>
                    <a href="revenue-tours.php" class="btn btn-outline btn-sm" style="border-radius:20px;"><i class="fa-solid fa-chart-pie"></i> Xem báo cáo</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>