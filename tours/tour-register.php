<?php
// trekking/tours/tour-register.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$tour_id = intval($_GET['id'] ?? 0);
$selected_departure = sanitize($_GET['departure'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ? AND status = 1");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();

if (!$tour || $tour['available_slots'] <= 0) {
    header("Location: " . BASE_URL . "tours/tour.php");
    exit;
}

$page_title = "Đăng Ký Tour: " . $tour['title'];
$error = '';
$user = null;

if (isLoggedIn()) {
    $u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $u_stmt->execute([$_SESSION['user_id']]);
    $user = $u_stmt->fetch();
}

// Lấy danh sách các ngày khởi hành thực tế của tour này
$departures_stmt = $pdo->prepare("SELECT * FROM tour_departures WHERE tour_id = ? AND departure_date >= CURDATE() AND status = 1 ORDER BY departure_date ASC");
$departures_stmt->execute([$tour_id]);
$departures = $departures_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $emergency = sanitize($_POST['emergency_contact'] ?? '');
    $departure_date = sanitize($_POST['departure_date'] ?? date('Y-m-d'));
    $number_people = intval($_POST['number_people'] ?? 1);
    $notes = sanitize($_POST['notes'] ?? '');

    if (empty($fullname) || empty($phone) || empty($email) || empty($emergency)) {
        $error = "Vui lòng nhập đầy đủ các thông tin bắt buộc!";
    } elseif ($number_people <= 0) {
        $error = "Số người đăng ký phải lớn hơn 0!";
    } elseif ($number_people > $tour['available_slots']) {
        $error = "Tour chỉ còn trống {$tour['available_slots']} chỗ!";
    } else {
        $total_price = $tour['price'] * $number_people;
        $booking_code = 'TB-' . date('Ymd') . '-' . rand(1000, 9999);

        // Khách chưa login vẫn đăng ký được, tự động liên kết user
        $current_user_id = $_SESSION['user_id'] ?? null;
        if (!$current_user_id) {
            $check_u = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
            $check_u->execute([$email]);
            $exist_user = $check_u->fetch();
            if ($exist_user) {
                $current_user_id = $exist_user['id'];
            } else {
                $pwd_rand = password_hash(bin2hex(random_bytes(6)), PASSWORD_DEFAULT);
                $ins_u = $pdo->prepare("INSERT INTO users (fullname, email, password, phone, role, status) VALUES (?, ?, ?, ?, 'customer', 1)");
                $ins_u->execute([$fullname, strtolower($email), $pwd_rand, $phone]);
                $current_user_id = $pdo->lastInsertId();
            }
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO tour_bookings (booking_code, user_id, tour_id, departure_date, fullname, phone, email, emergency_contact, number_people, total_price, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xác nhận')");
            $ins->execute([$booking_code, $current_user_id, $tour['id'], $departure_date, $fullname, $phone, $email, $emergency, $number_people, $total_price, $notes]);

            $up = $pdo->prepare("UPDATE tours SET available_slots = available_slots - ? WHERE id = ?");
            $up->execute([$number_people, $tour['id']]);

            $pdo->commit();
            header("Location: " . BASE_URL . "tours/my-tours.php?registered=1&code=" . $booking_code);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Lỗi đặt tour: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div style="max-width:700px; margin:0 auto; background:white; padding:35px; border-radius:12px; box-shadow:var(--shadow-lg);">
        <h2 style="color:var(--primary); margin-bottom:8px; text-align:center;">ĐĂNG KÝ THAM GIA TOUR</h2>
        <p style="text-align:center; color:var(--gray); margin-bottom:25px;">Hành trình: <strong><?= htmlspecialchars($tour['title']) ?></strong></p>

        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <form action="" method="POST">
            <!-- CHỌN NGÀY XUẤT PHÁT -->
            <div class="form-group" style="background:#fff7ed; padding:15px; border-radius:8px; border:1px solid #fed7aa; margin-bottom:20px;">
                <label style="font-weight:700; color:#c2410c; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-calendar-check"></i> CHỌN NGÀY KHỞI HÀNH (CÁC ĐỢT TOUR CÓ SẴN):
                </label>
                <?php if (count($departures) > 0): ?>
                    <select name="departure_date" class="form-control" style="font-weight:600; margin-top:6px;">
                        <?php foreach ($departures as $dep): ?>
                            <option value="<?= $dep['departure_date'] ?>" <?= ($selected_departure === $dep['departure_date']) ? 'selected' : '' ?>>
                                Đợt khởi hành: <?= date('d/m/Y', strtotime($dep['departure_date'])) ?> (Còn <?= $dep['available_slots'] ?> chỗ)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="date" name="departure_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" min="<?= date('Y-m-d') ?>" style="margin-top:6px;" required>
                    <small style="color:#64748b;">(Quý khách có thể tự chọn ngày dự kiến, TrekHub sẽ liên hệ chốt đợt)</small>
                <?php endif; ?>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label>Họ và tên *</label>
                    <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required placeholder="Nguyễn Văn A">
                </div>
                <div class="form-group">
                    <label>Số điện thoại *</label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required placeholder="09xxxxxxxx">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label>Địa chỉ Email *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required placeholder="email@gmail.com">
                </div>
                <div class="form-group">
                    <label>SĐT liên hệ khẩn cấp (Người thân) *</label>
                    <input type="text" name="emergency_contact" class="form-control" required placeholder="09xxxxxxxx (Bố/Mẹ/Bạn thân)">
                </div>
            </div>

            <div class="form-group">
                <label>Số người tham gia * (Còn trống <?= $tour['available_slots'] ?> chỗ)</label>
                <input type="number" id="num_people" name="number_people" class="form-control" value="1" min="1" max="<?= $tour['available_slots'] ?>" required>
            </div>

            <div class="form-group">
                <label>Ghi chú (Chế độ ăn chay, kích thước áo, lưu ý sức khỏe...)</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>

            <div style="background:#f8fafc; border:1px solid var(--border); padding:20px; border-radius:8px; margin:25px 0;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span>Đơn giá / người:</span>
                    <strong><?= format_price($tour['price']) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:1.25rem; font-weight:800; color:var(--accent);">
                    <span>TỔNG TIỀN DỰ KIẾN:</span>
                    <span id="display_total"><?= format_price($tour['price']) ?></span>
                </div>
            </div>

            <button type="submit" class="btn btn-accent" style="width:100%; padding:14px; font-size:1.1rem;">XÁC NHẬN ĐĂNG KÝ TOUR</button>
        </form>
    </div>
</div>

<script>
const unitPrice = <?= $tour['price'] ?>;
const numInput = document.getElementById('num_people');
const displayTotal = document.getElementById('display_total');

numInput.addEventListener('input', function() {
    let count = parseInt(this.value) || 1;
    displayTotal.textContent = (count * unitPrice).toLocaleString('vi-VN') + ' ₫';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>