<?php
// trekking/pages/contact.php
$page_title = "Liên Hệ & Hỗ Trợ";
$active_menu = 'contact';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (empty($fullname) || empty($email) || empty($message)) {
        $error = "Vui lòng nhập đầy đủ họ tên, email và nội dung cần hỗ trợ!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO contacts (fullname, email, phone, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fullname, $email, $phone, $message]);
        $msg = "Tin nhắn của bạn đã được gửi thành công! Đội ngũ tư vấn TrekHub sẽ liên hệ lại sớm nhất.";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div class="grid grid-2" style="background:white; padding:40px; border-radius:12px; box-shadow:var(--shadow);">
        <div>
            <h2 style="color:var(--primary); margin-bottom:15px;">LIÊN HỆ VỚI CHÚNG TÔI</h2>
            <p style="color:#64748b; line-height:1.7; margin-bottom:25px;">
                Bạn có câu hỏi về lịch khởi hành, yêu cầu thiết kế tour riêng cho cơ quan / nhóm bạn hoặc cần tư vấn chọn giày, balo? Đừng ngần ngại để lại lời nhắn!
            </p>

            <div style="margin-bottom:15px; font-size:0.95rem;">
                <i class="fa-solid fa-location-dot" style="color:var(--accent); width:24px;"></i> Số 18 Hoàng Quốc Việt, Cầu Giấy, Hà Nội
            </div>
            <div style="margin-bottom:15px; font-size:0.95rem;">
                <i class="fa-solid fa-phone" style="color:var(--accent); width:24px;"></i> Hotline: 0988.888.888
            </div>
            <div style="margin-bottom:15px; font-size:0.95rem;">
                <i class="fa-solid fa-envelope" style="color:var(--accent); width:24px;"></i> contact@trekhub.vn
            </div>
            <div style="margin-bottom:15px; font-size:0.95rem;">
                <i class="fa-solid fa-clock" style="color:var(--accent); width:24px;"></i> 08:00 - 21:00 (Từ Thứ Hai đến Chủ Nhật)
            </div>
        </div>

        <div>
            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Họ và tên *</label>
                    <input type="text" name="fullname" class="form-control" required placeholder="Nguyễn Văn A">
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Địa chỉ Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="email@gmail.com">
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại</label>
                        <input type="tel" name="phone" class="form-control" placeholder="09xxxxxxxx">
                    </div>
                </div>
                <div class="form-group">
                    <label>Nội dung cần hỗ trợ *</label>
                    <textarea name="message" class="form-control" rows="4" required placeholder="Nhập câu hỏi của bạn..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;">GỬI TIN NHẮN LIÊN HỆ</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>