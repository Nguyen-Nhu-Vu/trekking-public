<?php
// trekking/admin/bookings/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireStaff();

// Cập nhật trạng thái hoặc phân công HDV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    $bid = intval($_POST['booking_id']);
    $new_status = sanitize($_POST['status']);
    $guide_id = !empty($_POST['guide_id']) ? intval($_POST['guide_id']) : null;

    $stmt_up = $pdo->prepare("UPDATE tour_bookings SET status = ?, guide_id = ? WHERE id = ?");
    $stmt_up->execute([$new_status, $guide_id, $bid]);
    header("Location: index.php?msg=updated");
    exit;
}

// Lọc trạng thái
$filter_status = sanitize($_GET['status'] ?? '');
$sql = "SELECT b.*, t.title as tour_name, u.fullname as guide_name 
        FROM tour_bookings b 
        JOIN tours t ON b.tour_id = t.id 
        LEFT JOIN users u ON b.guide_id = u.id";
if (!empty($filter_status) && $filter_status !== 'all') {
    $sql .= " WHERE b.status = " . $pdo->quote($filter_status);
}
$sql .= " ORDER BY b.id DESC";
$bookings = $pdo->query($sql)->fetchAll();

// Lấy danh sách HDV / Staff
$guides = $pdo->query("SELECT id, fullname FROM users WHERE role IN ('staff', 'manager', 'admin')")->fetchAll();

$current_page = 'bookings';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Đăng Ký Tour - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ ĐĂNG KÝ TOUR TREKKING</strong></div>
            <form action="" method="GET" style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:0.85rem; color:#475569; font-weight:600;"><i class="fa-solid fa-filter"></i> Lọc trạng thái:</label>
                <select name="status" class="form-control" onchange="this.form.submit()" style="padding:6px 12px; font-size:0.85rem; min-width:180px;">
                    <option value="all" <?= ($filter_status === 'all' || empty($filter_status)) ? 'selected' : '' ?>>-- Hiện tất cả trạng thái --</option>
                    <option value="Chờ xác nhận" <?= $filter_status === 'Chờ xác nhận' ? 'selected' : '' ?>>Chờ xác nhận</option>
                    <option value="Đã xác nhận" <?= $filter_status === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
                    <option value="Đã thanh toán" <?= $filter_status === 'Đã thanh toán' ? 'selected' : '' ?>>Đã thanh toán</option>
                    <option value="Đã hoàn thành" <?= $filter_status === 'Đã hoàn thành' ? 'selected' : '' ?>>Đã hoàn thành</option>
                    <option value="Đã hủy" <?= $filter_status === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
                </select>
                <?php if (!empty($filter_status) && $filter_status !== 'all'): ?>
                    <a href="index.php" class="btn btn-outline btn-sm" title="Hiện tất cả"><i class="fa-solid fa-rotate-left"></i> Tất cả</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-content">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
                <div class="alert alert-success">Cập nhật thông tin đăng ký tour thành công!</div>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Mã Booking</th>
                            <th>Khách Hàng</th>
                            <th>Tour</th>
                            <th>Ngày Khởi Hành</th>
                            <th>Số Khách</th>
                            <th>Phân Công HDV</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bookings) > 0): ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($b['fullname']) ?><br>
                                        <small style="color:#64748b;"><?= htmlspecialchars($b['phone']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($b['tour_name']) ?></td>
                                    <td>
                                        <strong style="color:#ea580c;">
                                            <?= !empty($b['departure_date']) ? date('d/m/Y', strtotime($b['departure_date'])) : 'Tự thỏa thuận' ?>
                                        </strong>
                                    </td>
                                    <td><?= $b['number_people'] ?> người</td>
                                    <form action="" method="POST">
                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="update_booking" value="1">
                                        <td>
                                            <select name="guide_id" class="form-control" style="font-size:0.85rem; padding:4px 8px;">
                                                <option value="">-- Chọn Leader/HDV --</option>
                                                <?php foreach ($guides as $g): ?>
                                                    <option value="<?= $g['id'] ?>" <?= $b['guide_id'] == $g['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($g['fullname']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="status" class="form-control" style="font-size:0.85rem; padding:4px 8px; width:135px;">
                                                <option value="Chờ xác nhận" <?= $b['status'] === 'Chờ xác nhận' ? 'selected' : '' ?>>Chờ xác nhận</option>
                                                <option value="Đã xác nhận" <?= $b['status'] === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
                                                <option value="Đã thanh toán" <?= $b['status'] === 'Đã thanh toán' ? 'selected' : '' ?>>Đã thanh toán</option>
                                                <option value="Đã hoàn thành" <?= $b['status'] === 'Đã hoàn thành' ? 'selected' : '' ?>>Đã hoàn thành</option>
                                                <option value="Đã hủy" <?= $b['status'] === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Lưu</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:35px; color:#64748b;">
                                    Không có đăng ký tour nào phù hợp với bộ lọc.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>