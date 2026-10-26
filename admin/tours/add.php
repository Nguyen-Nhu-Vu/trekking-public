<?php
// trekking/admin/tours/add.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireManager();

$current_page = 'tours';
$leaderOptions = $pdo->query("SELECT id,fullname FROM trekking_leaders WHERE status=1 ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $leader_id = (int)($_POST['leader_id'] ?? 0);
    $location_id = intval($_POST['location_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $duration = sanitize($_POST['duration'] ?? '2N1Đ');
    $difficulty = sanitize($_POST['difficulty'] ?? 'Vừa phải');
    $altitude = sanitize($_POST['altitude'] ?? '2500m');
    $distance = sanitize($_POST['distance'] ?? '20 km');
    $max_slots = intval($_POST['max_slots'] ?? 15);
    $meeting_point = sanitize($_POST['meeting_point'] ?? 'Hà Nội');
    $transport = sanitize($_POST['transport'] ?? 'Xe ô tô');
    $description = sanitize($_POST['description'] ?? '');
    $schedule = sanitize($_POST['schedule'] ?? '');
    $includes = sanitize($_POST['includes'] ?? '');
    $excludes = sanitize($_POST['excludes'] ?? '');
    $requirements = sanitize($_POST['requirements'] ?? '');
    $safety_notes = sanitize($_POST['safety_notes'] ?? '');

    $image_path = 'https://images.unsplash.com/photo-1544735716-392fe2489ffa?w=800';
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
            } else {
                $error = "Không thể lưu file ảnh vào thư mục uploads/tours!";
            }
        } else {
            $error = "Định dạng file ảnh không hợp lệ!";
        }
    }

    if (empty($title) || $location_id <= 0 || $price <= 0 || $leader_id <= 0) {
        $error = "Vui lòng điền các trường bắt buộc!";
    } elseif (empty($error)) {
        $guideStmt = $pdo->prepare("SELECT fullname FROM trekking_leaders WHERE id=? AND status=1");
        $guideStmt->execute([$leader_id]);
        $guideName = $guideStmt->fetchColumn();
        if (!$guideName) {
            $error = 'Leader không hợp lệ hoặc đang bị ẩn.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO tours (title, location_id, leader_id, tour_guide, price, duration, difficulty, altitude, distance, max_slots, available_slots, meeting_point, transport, image, description, schedule, includes, excludes, requirements, safety_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$title, $location_id, $leader_id, $guideName, $price, $duration, $difficulty, $altitude, $distance, $max_slots, $max_slots, $meeting_point, $transport, $image_path, $description, $schedule, $includes, $excludes, $requirements, $safety_notes]);
        }
        if (!$error) {
            header("Location: index.php?msg=added");
            exit;
        }
    }
}

$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Tour Mới - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">
    <main class="admin-main" style="padding:40px;">
        <div style="max-width:900px; margin:0 auto; background:white; padding:35px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
            <div style="display:flex; justify-content:space-between; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
                <h2>THÊM TOUR TREKKING MỚI</h2>
                <a href="index.php" class="btn btn-outline btn-sm">&larr; Quay lại danh sách</a>
            </div>

            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group" style="padding:16px;background:#f0fdf4;border:1px solid #86efac;border-radius:10px">
                    <label>Leader cố định cho tour *</label>
                    <select name="leader_id" class="form-control" required>
                        <option value="">-- Chọn hướng dẫn viên --</option>
                        <?php foreach ($leaderOptions as $leader): ?>
                        <option value="<?= (int)$leader['id'] ?>" <?= (int)($_POST['leader_id'] ?? 0)===(int)$leader['id']?'selected':'' ?>><?= htmlspecialchars($leader['fullname'],ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Áp dụng cho tất cả các đợt khởi hành.</small>
                </div>
                <div class="form-group">
                    <label>Tên Tour *</label>
                    <input type="text" name="title" class="form-control" required placeholder="Chinh phục đỉnh núi...">
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Địa điểm *</label>
                        <select name="location_id" class="form-control" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Giá tour (VNĐ) *</label>
                        <input type="number" name="price" class="form-control" required placeholder="3500000">
                    </div>
                </div>

                <div class="grid grid-3">
                    <div class="form-group">
                        <label>Thời lượng</label>
                        <input type="text" name="duration" class="form-control" value="2N1Đ">
                    </div>
                    <div class="form-group">
                        <label>Độ khó</label>
                        <select name="difficulty" class="form-control">
                            <option value="Dễ">Dễ</option>
                            <option value="Vừa phải" selected>Vừa phải</option>
                            <option value="Thử thách">Thử thách</option>
                            <option value="Khắc nghiệt">Khắc nghiệt</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Số chỗ tối đa</label>
                        <input type="number" name="max_slots" class="form-control" value="15">
                    </div>
                </div>

                <div class="grid grid-3">
                    <div class="form-group">
                        <label>Độ cao</label>
                        <input type="text" name="altitude" class="form-control" value="2800m">
                    </div>
                    <div class="form-group">
                        <label>Quãng đường</label>
                        <input type="text" name="distance" class="form-control" value="18 km">
                    </div>
                    <div class="form-group">
                        <label>Phương tiện di chuyển</label>
                        <input type="text" name="transport" class="form-control" value="Xe giường nằm">
                    </div>
                </div>

                <div class="form-group">
                    <label>Điểm tập trung</label>
                    <input type="text" name="meeting_point" class="form-control" value="Hà Nội">
                </div>

                <div class="form-group" style="background:#f8fafc; padding:15px; border-radius:6px; border:1px dashed #cbd5e1;">
                    <label style="font-weight:bold; color:var(--primary);"><i class="fa-solid fa-cloud-arrow-up"></i> Tải ảnh đại diện từ máy tính</label>
                    <input type="file" name="image_file" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Mô tả giới thiệu tour</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Lịch trình chi tiết</label>
                    <textarea name="schedule" class="form-control" rows="4"></textarea>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label style="color:#16a34a; font-weight:700;">Dịch vụ bao gồm (mỗi ý 1 dòng)</label>
                        <textarea name="includes" class="form-control" rows="4" placeholder="Xe đưa đón khứ hồi&#10;Porter gùi đồ&#10;Bảo hiểm 100tr..."></textarea>
                    </div>
                    <div class="form-group">
                        <label style="color:#dc2626; font-weight:700;">Không bao gồm (mỗi ý 1 dòng)</label>
                        <textarea name="excludes" class="form-control" rows="4" placeholder="Đồ uống ngoài menu&#10;Chi phí cá nhân..."></textarea>
                    </div>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label style="color:#ea580c; font-weight:700;">Đồ khách cần chuẩn bị</label>
                        <textarea name="requirements" class="form-control" rows="3" placeholder="Giày bám đá&#10;Balo trợ lực..."></textarea>
                    </div>
                    <div class="form-group">
                        <label style="color:#eab308; font-weight:700;">Lưu ý hoàn / huỷ tour</label>
                        <textarea name="safety_notes" class="form-control" rows="3" placeholder="Hủy trước 7 ngày hoàn 100%..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding:12px 30px;"><i class="fa-solid fa-plus"></i> TẠO TOUR MỚI</button>
            </form>
        </div>
    </main>
</body>
</html>
