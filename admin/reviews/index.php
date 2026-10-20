<?php
// trekking/admin/reviews/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireStaff(); // Cả Staff và Manager đều có quyền xem đánh giá

$msg = '';

// Thay đổi trạng thái ẩn/hiện đánh giá
if (isset($_GET['toggle'])) {
    $rid = intval($_GET['toggle']);
    $st = intval($_GET['status']) == 1 ? 0 : 1;
    $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?")->execute([$st, $rid]);
    $msg = "Đã cập nhật trạng thái hiển thị đánh giá!";
}

// Xóa đánh giá
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$del_id]);
    $msg = "Đã xóa đánh giá thành công!";
}

$stmt = $pdo->query("SELECT r.*, u.fullname, t.title as tour_name, p.name as product_name 
                     FROM reviews r 
                     JOIN users u ON r.user_id = u.id 
                     LEFT JOIN tours t ON r.tour_id = t.id 
                     LEFT JOIN products p ON r.product_id = p.id 
                     ORDER BY r.id DESC");
$reviews = $stmt->fetchAll();

$current_page = 'reviews';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Đánh Giá Từ Khách - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ ĐÁNH GIÁ TỪ KHÁCH HÀNG</strong></div>
        </div>

        <div class="admin-content">
            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Khách Hàng</th>
                            <th>Mục Đánh Giá</th>
                            <th>Số Sao</th>
                            <th>Nội Dung</th>
                            <th>Ngày Gửi</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reviews) > 0): ?>
                            <?php foreach ($reviews as $r): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['fullname']) ?></strong></td>
                                    <td>
                                        <?php if ($r['tour_name']): ?>
                                            <span class="badge" style="background:#1e5631;">Tour: <?= htmlspecialchars($r['tour_name']) ?></span>
                                        <?php else: ?>
                                            <span class="badge" style="background:#e67e22;">SP: <?= htmlspecialchars($r['product_name'] ?? 'Sản phẩm') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:#f59e0b;"><?= str_repeat('★', (int)$r['rating']) ?></td>
                                    <td style="max-width:280px;"><?= htmlspecialchars($r['comment']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                                    <td>
                                        <?= $r['status'] == 1 ? '<span class="badge" style="background:#16a34a;">Hiển thị</span>' : '<span class="badge" style="background:#dc2626;">Đang ẩn</span>' ?>
                                    </td>
                                    <td>
                                        <a href="index.php?toggle=<?= $r['id'] ?>&status=<?= $r['status'] ?>" class="btn btn-sm btn-outline">
                                            <?= $r['status'] == 1 ? '<i class="fa-solid fa-eye-slash"></i> Ẩn' : '<i class="fa-solid fa-eye"></i> Hiện' ?>
                                        </a>
                                        <a href="index.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa đánh giá này?');"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:35px; color:#64748b;">Chưa có đánh giá nào từ khách hàng.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>