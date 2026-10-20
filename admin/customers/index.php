<?php
// trekking/admin/customers/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireAdmin();

// Khóa hoặc mở khóa tài khoản
if (isset($_GET['toggle_id'])) {
    $uid = intval($_GET['toggle_id']);
    $cur_status = intval($_GET['status']);
    $new_status = $cur_status == 1 ? 0 : 1;
    $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'customer'")->execute([$new_status, $uid]);
    header("Location: index.php?msg=success");
    exit;
}

$customers = $pdo->query("SELECT * FROM users WHERE role = 'customer' ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Khách Hàng - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">
    <aside class="admin-sidebar">
        <div class="admin-brand"><i class="fa-solid fa-mountain-sun"></i> TrekHub Admin</div>
        <ul class="admin-menu">
            <li><a href="../index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
            <li><a href="../tours/"><i class="fa-solid fa-person-hiking"></i> Quản lý Tour</a></li>
            <li><a href="../bookings/"><i class="fa-solid fa-ticket"></i> Đăng ký Tour</a></li>
            <li><a href="../products/"><i class="fa-solid fa-campground"></i> Sản phẩm</a></li>
            <li><a href="../categories/"><i class="fa-solid fa-layer-group"></i> Danh mục</a></li>
            <li><a href="../orders/"><i class="fa-solid fa-box-open"></i> Đơn hàng</a></li>
            <li class="active"><a href="index.php"><i class="fa-solid fa-users"></i> Khách hàng</a></li>
            <li><a href="../locations/"><i class="fa-solid fa-map-location-dot"></i> Địa điểm</a></li>
            <li><a href="../posts/"><i class="fa-solid fa-newspaper"></i> Bài viết</a></li>
            <li><a href="../reviews/"><i class="fa-solid fa-star"></i> Đánh giá</a></li>
            <li><a href="../contacts/"><i class="fa-solid fa-envelope"></i> Liên hệ</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ DANH SÁCH KHÁCH HÀNG</strong></div>
        </div>

        <div class="admin-content">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Họ và Tên</th>
                            <th>Email</th>
                            <th>Số Điện Thoại</th>
                            <th>Địa Chỉ</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['fullname']) ?></strong></td>
                                <td><?= htmlspecialchars($c['email']) ?></td>
                                <td><?= htmlspecialchars($c['phone'] ?? '---') ?></td>
                                <td><?= htmlspecialchars($c['address'] ?? '---') ?></td>
                                <td>
                                    <?php if ($c['status'] == 1): ?>
                                        <span class="badge" style="background:#16a34a;">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#dc2626;">Đang khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="index.php?toggle_id=<?= $c['id'] ?>&status=<?= $c['status'] ?>" class="btn btn-sm <?= $c['status'] == 1 ? 'btn-danger' : 'btn-primary' ?>" onclick="return confirm('Bạn có chắc muốn đổi trạng thái tài khoản này?');">
                                        <?= $c['status'] == 1 ? '<i class="fa-solid fa-lock"></i> Khóa' : '<i class="fa-solid fa-unlock"></i> Mở' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>