<?php
// trekking/admin/locations/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireManager();

$msg = '';
$error = '';

// Thêm địa điểm mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    $name = sanitize($_POST['name'] ?? '');
    $region = sanitize($_POST['region'] ?? 'Tây Bắc');
    $description = sanitize($_POST['description'] ?? '');

    if (empty($name)) {
        $error = "Vui lòng nhập tên địa điểm/đỉnh núi!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO locations (name, region, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $region, $description]);
        $msg = "Thêm địa điểm thành công!";
    }
}

// Xóa địa điểm
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $count = $pdo->prepare("SELECT COUNT(*) FROM tours WHERE location_id = ?");
    $count->execute([$del_id]);
    if ($count->fetchColumn() > 0) {
        $error = "Không thể xóa địa điểm này vì đang có tour trekking liên kết!";
    } else {
        $pdo->prepare("DELETE FROM locations WHERE id = ?")->execute([$del_id]);
        $msg = "Đã xóa địa điểm thành công!";
    }
}

$locations = $pdo->query("SELECT l.*, COUNT(t.id) as total_tours 
                          FROM locations l 
                          LEFT JOIN tours t ON l.id = t.location_id 
                          GROUP BY l.id 
                          ORDER BY l.id DESC")->fetchAll();

$current_page = 'locations';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Điểm Đến Trekking - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ ĐỊA ĐIỂM & ĐỈNH NÚI TREKKING</strong></div>
        </div>

        <div class="admin-content">
            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <div class="grid grid-3" style="align-items:start;">
                <!-- Form thêm địa điểm -->
                <div style="background:white; padding:25px; border-radius:10px; box-shadow:var(--shadow);">
                    <h4 style="margin-bottom:15px; color:#1e293b;"><i class="fa-solid fa-map-pin"></i> Thêm Điểm Đến Mới</h4>
                    <form action="" method="POST">
                        <input type="hidden" name="add_location" value="1">
                        <div class="form-group">
                            <label>Tên địa điểm / Đỉnh núi *</label>
                            <input type="text" name="name" class="form-control" required placeholder="Ví dụ: Fansipan, Lảo Thẩn...">
                        </div>
                        <div class="form-group">
                            <label>Khu vực</label>
                            <select name="region" class="form-control">
                                <option value="Tây Bắc">Tây Bắc</option>
                                <option value="Đông Bắc">Đông Bắc</option>
                                <option value="Miền Trung">Miền Trung</option>
                                <option value="Tây Nguyên">Tây Nguyên</option>
                                <option value="Miền Nam">Miền Nam</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Mô tả ngắn</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Đặc điểm địa hình, độ cao..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-plus"></i> THÊM ĐỊA ĐIỂM</button>
                    </form>
                </div>

                <!-- Bảng danh sách địa điểm -->
                <div style="grid-column: span 2;" class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên Điểm Đến</th>
                                <th>Khu Vực</th>
                                <th>Mô Tả</th>
                                <th>Số Tour</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($locations) > 0): ?>
                                <?php foreach ($locations as $loc): ?>
                                    <tr>
                                        <td>#<?= $loc['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($loc['name']) ?></strong></td>
                                        <td><span class="badge" style="background:#16a34a;"><?= htmlspecialchars($loc['region']) ?></span></td>
                                        <td><?= htmlspecialchars($loc['description'] ?? '---') ?></td>
                                        <td><span class="badge" style="background:#ea580c;"><?= $loc['total_tours'] ?> Tour</span></td>
                                        <td>
                                            <a href="index.php?delete=<?= $loc['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa địa điểm này?');">
                                                <i class="fa-solid fa-trash"></i> Xóa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:30px; color:#64748b;">Chưa có địa điểm nào.</td>
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