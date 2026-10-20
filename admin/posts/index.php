<?php
// trekking/admin/posts/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireManager();

$msg = '';

// Xóa bài viết
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$del_id]);
    $msg = "Đã xóa bài viết thành công!";
}

// Truy vấn bảng posts không phụ thuộc cột author_id
$posts = $pdo->query("SELECT * FROM posts ORDER BY id DESC")->fetchAll();

$current_page = 'posts';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Bài Viết & Cẩm Nang - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ BÀI VIẾT & KINH NGHIỆM TREKKING</strong></div>
        </div>

        <div class="admin-content">
            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>Tiêu Đề Bài Viết</th>
                            <th>Lượt Xem</th>
                            <th>Ngày Đăng</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($posts) > 0): ?>
                            <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td>
                                        <img src="<?= htmlspecialchars($post['image'] ?? 'https://images.unsplash.com/photo-1551632811-561732d1e306?w=200') ?>" style="width:60px; height:45px; object-fit:cover; border-radius:4px;">
                                    </td>
                                    <td style="max-width:320px;">
                                        <strong><?= htmlspecialchars($post['title']) ?></strong>
                                    </td>
                                    <td><i class="fa-regular fa-eye"></i> <?= $post['views'] ?? 0 ?></td>
                                    <td><?= isset($post['created_at']) ? date('d/m/Y', strtotime($post['created_at'])) : '---' ?></td>
                                    <td>
                                        <?= ($post['status'] ?? 1) == 1 ? '<span class="badge" style="background:#16a34a;">Xuất bản</span>' : '<span class="badge" style="background:#64748b;">Bản nháp</span>' ?>
                                    </td>
                                    <td>
                                        <a href="index.php?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa bài viết này?');"><i class="fa-solid fa-trash"></i> Xóa</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:35px; color:#64748b;">Chưa có bài viết nào được đăng.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>