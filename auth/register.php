
<?php
// trekking/auth/register.php
$page_title = "Đăng Ký Tài Khoản Khách Hàng";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = sanitize($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $repassword = (string)($_POST['repassword'] ?? '');

    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ các trường bắt buộc!";
    } elseif ($password !== $repassword) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
        $chk->execute([$email]);

        if ($chk->fetch()) {
            $error = "Email này đã được đăng ký tài khoản!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users
                (fullname, email, password, phone, role, status)
                VALUES (?, ?, ?, ?, 'customer', 1)
            ");

            $stmt->execute([
                $fullname,
                $email,
                $hashed,
                $phone
            ]);

            // Đăng ký thành công: tự động sang trang đăng nhập.
            header("Location: " . rtrim(BASE_URL, '/') . "/auth/login.php", true, 303);
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div style="max-width:480px; margin:0 auto; background:white; padding:35px; border-radius:12px; box-shadow:var(--shadow-lg);">
        <h2 style="text-align:center; color:var(--primary); margin-bottom:20px;">ĐĂNG KÝ THÀNH VIÊN</h2>

        <?php if ($msg): ?>
            <div class="alert alert-success">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Họ và tên *</label>
                <input
                    type="text"
                    name="fullname"
                    class="form-control"
                    required
                    placeholder="Nguyễn Văn A"
                >
            </div>

            <div class="form-group">
                <label>Địa chỉ Email Google (Gmail) *</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    required
                    placeholder="taikhoan@gmail.com"
                >
            </div>

            <div class="form-group">
                <label>Số điện thoại</label>
                <input
                    type="tel"
                    name="phone"
                    class="form-control"
                    placeholder="09xxxxxxxx"
                >
            </div>

            <div class="form-group">
                <label>Mật khẩu *</label>
                <input
                    type="password"
                    name="password"
                    class="form-control"
                    required
                    placeholder="Tối thiểu 6 ký tự"
                >
            </div>

            <div class="form-group">
                <label>Nhập lại mật khẩu *</label>
                <input
                    type="password"
                    name="repassword"
                    class="form-control"
                    required
                    placeholder="Nhập lại mật khẩu"
                >
            </div>

            <button
                type="submit"
                class="btn btn-accent"
                style="width:100%; padding:12px; font-size:1.05rem; margin-top:10px;"
            >
                ĐĂNG KÝ NGAY
            </button>
        </form>

        <div style="text-align:center; margin-top:20px; font-size:0.9rem;">
            Đã có tài khoản?
            <a
                href="<?= BASE_URL ?>auth/login.php"
                style="color:var(--primary); font-weight:bold;"
            >
                Đăng nhập tại đây
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
