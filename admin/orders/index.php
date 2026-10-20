<?php
// trekking/admin/orders/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireStaff(); // Cả nhân viên, quản lý và admin đều có thể xem và xử lý đơn

// 1. Xử lý thao tác hàng loạt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected_ids = $_POST['order_ids'] ?? [];
    $target_status = sanitize($_POST['target_status'] ?? '');
    if (!empty($selected_ids) && !empty($target_status)) {
        $in = str_repeat('?,', count($selected_ids) - 1) . '?';
        $stmt_bulk = $pdo->prepare("UPDATE orders SET status = ? WHERE id IN ($in)");
        $stmt_bulk->execute(array_merge([$target_status], $selected_ids));
        header("Location: index.php?msg=bulk_updated");
        exit;
    }
}

// 2. Xử lý bộ lọc trạng thái (Hỗ trợ lọc 'Tất cả trạng thái')
$status_filter = sanitize($_GET['status'] ?? '');
$sql = "SELECT * FROM orders";
if (!empty($status_filter) && $status_filter !== 'all') {
    $sql .= " WHERE status = " . $pdo->quote($status_filter);
}
$sql .= " ORDER BY id DESC";
$orders = $pdo->query($sql)->fetchAll();

// Đếm số lượng booking tour & đơn hàng chờ duyệt
$pending_bookings = $pdo->query("SELECT COUNT(*) FROM tour_bookings WHERE status = 'Chờ xác nhận'")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Chờ xác nhận'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Đơn Hàng - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        /* CSS Accordion Menu mở rộng thanh tác vụ */
        .sidebar-group-title {
            padding: 12px 20px;
            color: #cbd5e1;
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            border-left: 4px solid transparent;
            transition: 0.25s;
        }
        .sidebar-group-title:hover {
            background: #243248;
            color: white;
        }
        .sidebar-group-title i.chevron {
            transition: transform 0.25s ease;
            font-size: 0.8rem;
        }
        .sidebar-sub-menu {
            display: none;
            list-style: none;
            padding-left: 10px;
            background: #141d2b;
        }
        .sidebar-group.open .sidebar-sub-menu {
            display: block;
        }
        .sidebar-group.open .sidebar-group-title i.chevron {
            transform: rotate(180deg);
        }
        .sidebar-sub-menu li a {
            padding: 9px 20px 9px 30px !important;
            font-size: 0.88rem !important;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #94a3b8;
        }
        .sidebar-sub-menu li a:hover, .sidebar-sub-menu li.active a {
            color: #38bdf8 !important;
            background: #1e293b !important;
        }
    </style>
</head>
<body class="admin-body">

    <!-- SIDEBAR MENU CHÍNH MỞ RỘNG (ACCORDION) -->
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <i class="fa-solid fa-mountain-sun"></i> TrekHub System
        </div>
        <ul class="admin-menu" style="padding:0;">
            <li style="border-bottom: 1px solid #334155;">
                <a href="../index.php" style="padding: 14px 20px; color:#e2e8f0; font-weight:bold;">
                    <i class="fa-solid fa-gauge"></i> Dashboard
                </a>
            </li>

            <!-- NHÓM 1: XỬ LÝ KHÁCH HÀNG (Dành cho Nhân viên & Quản lý) -->
            <?php if (isStaff() || isManager()): ?>
            <li class="sidebar-group open">
                <div class="sidebar-group-title" onclick="toggleSidebarGroup(this)">
                    <span><i class="fa-solid fa-bell-concierge" style="color:#38bdf8; margin-right:8px;"></i> XỬ LÝ KHÁCH HÀNG</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </div>
                <ul class="sidebar-sub-menu">
                    <li><a href="../bookings/"><i class="fa-solid fa-ticket"></i> Duyệt Tour (<?= $pending_bookings ?>)</a></li>
                    <li class="active"><a href="index.php"><i class="fa-solid fa-box-open"></i> Duyệt Đơn Hàng (<?= $pending_orders ?>)</a></li>
                    <li><a href="../reviews/"><i class="fa-solid fa-star"></i> Đánh Giá Từ Khách</a></li>
                    <li><a href="../contacts/"><i class="fa-solid fa-envelope"></i> Tin Nhắn Liên Hệ</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- NHÓM 2: QUẢN LÝ NỘI DUNG & KHO (Dành cho Quản lý) -->
            <?php if (isManager()): ?>
            <li class="sidebar-group open">
                <div class="sidebar-group-title" onclick="toggleSidebarGroup(this)">
                    <span><i class="fa-solid fa-boxes-stacked" style="color:#ea580c; margin-right:8px;"></i> NỘI DUNG & KHO</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </div>
                <ul class="sidebar-sub-menu">
                    <li><a href="../tours/"><i class="fa-solid fa-person-hiking"></i> Quản Lý Tour</a></li>
                    <li><a href="../products/"><i class="fa-solid fa-campground"></i> Dụng Cụ & Tồn Kho</a></li>
                    <li><a href="../categories/"><i class="fa-solid fa-layer-group"></i> Danh Mục Hàng</a></li>
                    <li><a href="../locations/"><i class="fa-solid fa-map-location-dot"></i> Điểm Đến Trekking</a></li>
                    <li><a href="../posts/"><i class="fa-solid fa-newspaper"></i> Bài Viết & Cẩm Nang</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- NHÓM 3: HỆ THỐNG ADMIN (Dành cho Admin) -->
            <?php if (isAdmin()): ?>
            <li class="sidebar-group open">
                <div class="sidebar-group-title" onclick="toggleSidebarGroup(this)">
                    <span><i class="fa-solid fa-shield-halved" style="color:#dc2626; margin-right:8px;"></i> QUẢN TRỊ HỆ THỐNG</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </div>
                <ul class="sidebar-sub-menu">
                    <li><a href="../users/"><i class="fa-solid fa-user-shield"></i> Cấp Quyền & Tài Khoản</a></li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
    </aside>

    <!-- KHU VỰC BẢN LÀM VIỆC CHÍNH -->
    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ ĐƠN HÀNG DỤNG CỤ TREKKING</strong></div>
            
            <!-- FORM BỘ LỌC ĐẦY ĐỦ CÁC TRẠNG THÁI + TẤT CẢ TRẠNG THÁI -->
            <form action="" method="GET" style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:0.85rem; color:#475569; font-weight:600;"><i class="fa-solid fa-filter"></i> Lọc đơn:</label>
                <select name="status" class="form-control" onchange="this.form.submit()" style="padding:6px 12px; font-size:0.85rem; min-width:180px;">
                    <option value="all" <?= ($status_filter === 'all' || empty($status_filter)) ? 'selected' : '' ?>>-- Hiện tất cả trạng thái --</option>
                    <option value="Chờ xác nhận" <?= $status_filter === 'Chờ xác nhận' ? 'selected' : '' ?>>Chờ xác nhận</option>
                    <option value="Đã xác nhận" <?= $status_filter === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
                    <option value="Đang giao" <?= $status_filter === 'Đang giao' ? 'selected' : '' ?>>Đang giao</option>
                    <option value="Đã giao" <?= $status_filter === 'Đã giao' ? 'selected' : '' ?>>Đã giao</option>
                    <option value="Đã hủy" <?= $status_filter === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
                </select>
                <?php if (!empty($status_filter) && $status_filter !== 'all'): ?>
                    <a href="index.php" class="btn btn-outline btn-sm" title="Bỏ lọc, hiện tất cả"><i class="fa-solid fa-rotate-left"></i> Tất cả</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-content">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'bulk_updated'): ?>
                <div class="alert alert-success">Đã cập nhật trạng thái các đơn hàng được chọn thành công!</div>
            <?php endif; ?>

            <form action="" method="POST">
                <!-- KHỐI THAO TÁC HÀNG LOẠT -->
                <div style="background:white; padding:15px 20px; border-radius:10px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; box-shadow:var(--shadow);">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="font-weight:600; color:#334155;"><i class="fa-solid fa-list-check"></i> Thao tác hàng loạt:</span>
                        <select name="target_status" class="form-control" style="width:170px; padding:6px 10px;">
                            <option value="Đã xác nhận">Đã xác nhận</option>
                            <option value="Đang giao">Đang giao</option>
                            <option value="Đã giao">Đã giao</option>
                            <option value="Đã hủy">Hủy đơn</option>
                        </select>
                        <button type="submit" name="bulk_action" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-check-double"></i> Cập nhật mục đã chọn
                        </button>
                    </div>

                    <div style="font-size:0.9rem; color:#64748b;">
                        Tổng số hiển thị: <strong><?= count($orders) ?></strong> đơn hàng
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:45px; text-align:center;">
                                    <input type="checkbox" id="checkAll" onclick="toggleCheckAll(this)" title="Chọn tất cả">
                                </th>
                                <th>Mã Đơn</th>
                                <th>Khách Hàng</th>
                                <th>Tổng Tiền</th>
                                <th>Trạng Thái</th>
                                <th>Ngày Đặt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($orders) > 0): ?>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="order_ids[]" value="<?= $o['id'] ?>" class="order-cb">
                                        </td>
                                        <td><strong><?= htmlspecialchars($o['order_code']) ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($o['fullname']) ?><br>
                                            <small style="color:#64748b;"><?= htmlspecialchars($o['phone']) ?></small>
                                        </td>
                                        <td style="color:var(--accent); font-weight:bold;"><?= format_price($o['total_amount']) ?></td>
                                        <td>
                                            <?php
                                                $badge_bg = '#64748b';
                                                if ($o['status'] === 'Chờ xác nhận') $badge_bg = '#eab308';
                                                elseif ($o['status'] === 'Đã xác nhận') $badge_bg = '#0284c7';
                                                elseif ($o['status'] === 'Đang giao') $badge_bg = '#f97316';
                                                elseif ($o['status'] === 'Đã giao') $badge_bg = '#16a34a';
                                                elseif ($o['status'] === 'Đã hủy') $badge_bg = '#dc2626';
                                            ?>
                                            <span class="badge" style="background:<?= $badge_bg ?>; color:white; padding:4px 10px;">
                                                <?= htmlspecialchars($o['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:35px; color:#64748b;">
                                        Không có đơn hàng nào khớp với trạng thái được chọn.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </main>

<script>
// Hàm chọn tất cả / bỏ chọn tất cả
function toggleCheckAll(master) {
    document.querySelectorAll('.order-cb').forEach(cb => cb.checked = master.checked);
}

// Hàm mở rộng / thu gọn từng nhóm menu tác vụ
function toggleSidebarGroup(headerElement) {
    const parentGroup = headerElement.parentElement;
    parentGroup.classList.toggle('open');
}
</script>
</body>
</html>