<?php
// trekking/admin/categories/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireManager();

$msg = '';
$error = '';

// Thêm danh mục mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    if (empty($name)) {
        $error = "Vui lòng nhập tên danh mục!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $msg = "Thêm danh mục thành công!";
    }
}

// Xóa danh mục
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    // Kiểm tra xem có sản phẩm thuộc danh mục này không
    $count = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $count->execute([$del_id]);
    if ($count->fetchColumn() > 0) {
        $error = "Không thể xóa vì vẫn còn sản phẩm thuộc danh mục này!";
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$del_id]);
        $msg = "Đã xóa danh mục thành công!";
    }
}

$categories = $pdo->query("SELECT c.*, COUNT(p.id) as total_products 
                           FROM categories c 
                           LEFT JOIN products p ON c.id = p.category_id 
                           GROUP BY c.id 
                           ORDER BY c.id DESC")->fetchAll();

$current_page = 'categories';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Danh Mục Hàng - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ DANH MỤC DỤNG CỤ DÃ NGOẠI</strong></div>
        </div>

        <div class="admin-content">
            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <div class="grid grid-3" style="align-items:start;">
                <!-- Form thêm danh mục -->
                <div style="background:white; padding:25px; border-radius:10px; box-shadow:var(--shadow);">
                    <h4 style="margin-bottom:15px; color:#1e293b;"><i class="fa-solid fa-plus-circle"></i> Thêm Danh Mục Mới</h4>
                    <form action="" method="POST">
                        <input type="hidden" name="add_category" value="1">
                        <div class="form-group">
                            <label>Tên danh mục *</label>
                            <input type="text" name="name" class="form-control" required placeholder="Ví dụ: Balo & Túi, Lều trại...">
                        </div>
                        <div class="form-group">
                            <label>Mô tả ngắn</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Mô tả danh mục..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-plus"></i> THÊM DANH MỤC</button>
                    </form>
                </div>

                <!-- Bảng danh sách danh mục -->
                <div style="grid-column: span 2;" class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên Danh Mục</th>
                                <th>Mô Tả</th>
                                <th>Số Sản Phẩm</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($categories) > 0): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>#<?= $cat['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($cat['description'] ?? '---') ?></td>
                                        <td><span class="badge" style="background:#0284c7;"><?= $cat['total_products'] ?> SP</span></td>
                                        <td>
                                            <a href="index.php?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa danh mục này?');">
                                                <i class="fa-solid fa-trash"></i> Xóa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:30px; color:#64748b;">Chưa có danh mục nào.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>