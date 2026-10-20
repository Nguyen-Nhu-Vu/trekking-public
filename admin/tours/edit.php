<?php
// trekking/admin/tours/edit.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireManager(); // Chỉ Quản lý và Admin mới được phân công & sửa tour

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$stmt->execute([$id]);
$tour = $stmt->fetch();

if (!$tour) {
    header("Location: index.php");
    exit;
}

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $location_id = intval($_POST['location_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $duration = sanitize($_POST['duration'] ?? '2N1Đ');
    $difficulty = sanitize($_POST['difficulty'] ?? 'Vừa phải');
    $altitude = sanitize($_POST['altitude'] ?? '2500m');
    $distance = sanitize($_POST['distance'] ?? '20 km');
    $max_slots = intval($_POST['max_slots'] ?? 15);
    $available_slots = intval($_POST['available_slots'] ?? 15);
    $meeting_point = sanitize($_POST['meeting_point'] ?? 'Hà Nội');
    $transport = sanitize($_POST['transport'] ?? 'Xe ô tô');
    $tour_guide = sanitize($_POST['tour_guide'] ?? 'Leader TrekHub'); // Quản lý trực tiếp phân công Guide
    $description = sanitize($_POST['description'] ?? '');
    $schedule = sanitize($_POST['schedule'] ?? '');
    $includes = sanitize($_POST['includes'] ?? '');
    $excludes = sanitize($_POST['excludes'] ?? '');
    $requirements = sanitize($_POST['requirements'] ?? '');
    $safety_notes = sanitize($_POST['safety_notes'] ?? '');
    
    $image_path = $tour['image'];

    // Xử lý upload ảnh nếu có
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image_file']['tmp_name'];
        $file_name = $_FILES['image_file']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($ext, $allowed)) {
            $new_name = 'tour_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_dir = __DIR__ . '/../../uploads/tours/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $image_path = BASE_URL . 'uploads/tours/' . $new_name;
            }
        }
    }

    if (empty($title) || $location_id <= 0 || $price <= 0) {
        $error = "Vui lòng nhập đầy đủ các thông tin bắt buộc!";
    } else {
        $update = $pdo->prepare("UPDATE tours SET title = ?, location_id = ?, price = ?, duration = ?, difficulty = ?, altitude = ?, distance = ?, max_slots = ?, available_slots = ?, meeting_point = ?, transport = ?, tour_guide = ?, image = ?, description = ?, schedule = ?, includes = ?, excludes = ?, requirements = ?, safety_notes = ? WHERE id = ?");
        $update->execute([$title, $location_id, $price, $duration, $difficulty, $altitude, $distance, $max_slots, $available_slots, $meeting_point, $transport, $tour_guide, $image_path, $description, $schedule, $includes, $excludes, $requirements, $safety_notes, $id]);
        
        $msg = "Cập nhật tour và người dẫn tour thành công!";
        $stmt->execute([$id]);
        $tour = $stmt->fetch();
    }
}

$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chỉnh Sửa Tour & Phân Công Guide - Quản Lý</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">
    <aside class="admin-sidebar">
        <div class="admin-brand"><i class="fa-solid fa-mountain-sun"></i> TrekHub Manager</div>
        <ul class="admin-menu">
            <li><a href="../index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
            <li class="active"><a href="index.php"><i class="fa-solid fa-person-hiking"></i> Quản Lý Tour</a></li>
            <li><a href="../products/"><i class="fa-solid fa-boxes-stacked"></i> Hàng Hóa & Tồn Kho</a></li>
            <li><a href="../bookings/"><i class="fa-solid fa-ticket"></i> Duyệt Đăng Ký Tour</a></li>
            <li><a href="../orders/"><i class="fa-solid fa-box-open"></i> Duyệt Đơn Hàng</a></li>
        </ul>
    </aside>

    <main class="admin-main" style="padding:40px;">
        <div style="max-width:900px; margin:0 auto; background:white; padding:35px; border-radius:12px; box-shadow:var(--shadow);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
                <h2>CHỈNH SỬA TOUR & PHÂN CÔNG LEADER/GUIDE</h2>
                <a href="index.php" class="btn btn-outline btn-sm">&larr; Quay lại</a>
            </div>

            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Tên Tour *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($tour['title']) ?>" required>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Địa điểm *</label>
                        <select name="location_id" class="form-control" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>" <?= $loc['id'] == $tour['location_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Giá tour (VNĐ) *</label>
                        <input type="number" name="price" class="form-control" value="<?= $tour['price'] ?>" required>
                    </div>
                </div>

                <!-- QUẢN LÝ PHÂN CÔNG NGƯỜI DẪN TOUR -->
                <div class="grid grid-2" style="background:#fff7ed; padding:15px; border-radius:8px; border:1px solid #fed7aa;">
                    <div class="form-group" style="margin:0;">
                        <label style="color:#c2410c; font-weight:700;"><i class="fa-solid fa-user-ninja"></i> Người dẫn tour / Leader phân công:</label>
                        <input type="text" name="tour_guide" class="form-control" value="<?= htmlspecialchars($tour['tour_guide'] ?? 'Leader TrekHub') ?>" placeholder="Ví dụ: Hoàng Nam (0933445566)...">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="color:#c2410c; font-weight:700;"><i class="fa-solid fa-van-shuttle"></i> Phương tiện đưa đón:</label>
                        <input type="text" name="transport" class="form-control" value="<?= htmlspecialchars($tour['transport'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid grid-3" style="margin-top:15px;">
                    <div class="form-group">
                        <label>Thời lượng</label>
                        <input type="text" name="duration" class="form-control" value="<?= htmlspecialchars($tour['duration']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Độ khó</label>
                        <select name="difficulty" class="form-control">
                            <option value="Dễ" <?= $tour['difficulty'] === 'Dễ' ? 'selected' : '' ?>>Dễ</option>
                            <option value="Vừa phải" <?= $tour['difficulty'] === 'Vừa phải' ? 'selected' : '' ?>>Vừa phải</option>
                            <option value="Thử thách" <?= $tour['difficulty'] === 'Thử thách' ? 'selected' : '' ?>>Thử thách</option>
                            <option value="Khắc nghiệt" <?= $tour['difficulty'] === 'Khắc nghiệt' ? 'selected' : '' ?>>Khắc nghiệt</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Chỗ trống / Tổng</label>
                        <div style="display:flex; gap:8px;">
                            <input type="number" name="available_slots" class="form-control" value="<?= $tour['available_slots'] ?>">
                            <input type="number" name="max_slots" class="form-control" value="<?= $tour['max_slots'] ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Ảnh đại diện:</label>
                    <div style="margin-bottom:8px;">
                        <img src="<?= htmlspecialchars($tour['image']) ?>" style="height:90px; border-radius:6px; object-fit:cover;">
                    </div>
                    <input type="file" name="image_file" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Mô tả giới thiệu</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($tour['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Lịch trình chi tiết</label>
                    <textarea name="schedule" class="form-control" rows="4"><?= htmlspecialchars($tour['schedule'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label style="color:#16a34a; font-weight:700;">Dịch vụ bao gồm</label>
                        <textarea name="includes" class="form-control" rows="3"><?= htmlspecialchars($tour['includes'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label style="color:#dc2626; font-weight:700;">Không bao gồm</label>
                        <textarea name="excludes" class="form-control" rows="3"><?= htmlspecialchars($tour['excludes'] ?? '') ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding:12px 28px;"><i class="fa-solid fa-floppy-disk"></i> LƯU THAY ĐỔI</button>
            </form>
        </div>
    </main>
</body>
</html>