<?php
// trekking/admin/tours/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireManager();

// Xóa tour
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM tours WHERE id = ?")->execute([$del_id]);
    header("Location: index.php?msg=deleted");
    exit;
}

$stmt = $pdo->query("SELECT t.*, l.name as loc_name FROM tours t JOIN locations l ON t.location_id = l.id ORDER BY t.id DESC");
$tours = $stmt->fetchAll();

$current_page = 'tours';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Tour Trekking - Quản Lý</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <!-- NHÚNG SIDEBAR ACCORDION[cite: 18] -->
    <?php require_once '../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ DANH SÁCH TOUR LEO NÚI</strong></div>
            <a href="add.php" class="btn btn-accent btn-sm"><i class="fa-solid fa-plus"></i> Thêm Tour Mới</a>
        </div>

        <div class="admin-content">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success">Thực hiện thao tác thành công!</div>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>Tên Tour</th>
                            <th>Địa Điểm</th>
                            <th>Độ Khó</th>
                            <th>Thời Gian</th>
                            <th>Giá Vé</th>
                            <th>Chỗ Trống</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tours as $t): ?>
                            <tr>
                                <td><img src="<?= htmlspecialchars($t['image']) ?>" style="width:55px; height:55px; object-fit:cover; border-radius:6px;"></td>
                                <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
                                <td><?= htmlspecialchars($t['loc_name']) ?></td>
                                <td><span class="badge" style="background:#059669;"><?= htmlspecialchars($t['difficulty']) ?></span></td>
                                <td><?= htmlspecialchars($t['duration']) ?></td>
                                <td style="color:var(--accent); font-weight:bold;"><?= format_price($t['price']) ?></td>
                                <td><?= $t['available_slots'] ?> / <?= $t['max_slots'] ?></td>
                                <td>
                                    <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="index.php?delete=<?= $t['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc muốn xóa tour này?');"><i class="fa-solid fa-trash"></i></a>
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