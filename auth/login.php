<?php
// trekking/auth/login.php
$page_title = "Đăng Nhập";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = "Vui lòng nhập đầy đủ email và mật khẩu!";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ((int)$user['status'] === 0) {
                $error = "Tài khoản của bạn hiện đang bị tạm khóa!";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if (in_array($user['role'], ['admin', 'manager', 'staff'], true)) {
                    header("Location: " . BASE_URL . "admin/index.php");
                } else {
                    header("Location: " . BASE_URL . "index.php");
                }
                exit;
            }
        } else {
            $error = "Email hoặc mật khẩu không chính xác! Bấm vào con mắt để kiểm tra lại.";
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div style="max-width:440px; margin:0 auto; background:white; padding:35px; border-radius:12px; box-shadow:var(--shadow-lg);">
        <h2 style="text-align:center; color:var(--primary); margin-bottom:20px;">ĐĂNG NHẬP</h2>
        
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error"><?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Địa chỉ Email</label>
                <input type="email" name="email" class="form-control" required placeholder="nhapmail@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Mật khẩu</label>
                <div style="position:relative; display:flex; align-items:center;">
                    <input type="password" id="login_password" name="password" class="form-control" required placeholder="Nhập mật khẩu" style="padding-right:45px;">
                    <button type="button" onclick="togglePasswordVisibility()" style="position:absolute; right:12px; background:none; border:none; color:#64748b; cursor:pointer; font-size:1.1rem; padding:4px;">
                        <i id="toggleEyeIcon" class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-size:1rem; margin-top:10px;">
                ĐĂNG NHẬP
            </button>
        </form>

        <div style="text-align:center; margin-top:20px; font-size:0.9rem;">
            Chưa có tài khoản? <a href="<?= BASE_URL ?>auth/register.php" style="color:var(--accent); font-weight:bold;">Đăng ký bằng Gmail</a>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('login_password');
    const eyeIcon = document.getElementById('toggleEyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.className = 'fa-regular fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        eyeIcon.className = 'fa-regular fa-eye';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>