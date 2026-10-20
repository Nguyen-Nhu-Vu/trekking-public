<?php
// trekking/admin/login.php
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['fullname'] = $admin['fullname'];
        $_SESSION['email'] = $admin['email'];
        $_SESSION['role'] = $admin['role'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Tài khoản hoặc mật khẩu quản trị không chính xác!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng Nhập Quản Trị Hệ Thống - TrekHub</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background:#0f172a; display:flex; align-items:center; justify-content:center; min-height:100vh;">
    <div style="background:white; padding:40px; border-radius:12px; width:100%; max-width:420px; box-shadow:0 15px 30px rgba(0,0,0,0.3);">
        <div style="text-align:center; margin-bottom:25px;">
            <div style="font-size:2rem; color:var(--primary); font-weight:800;"><i class="fa-solid fa-mountain-sun"></i> TREK<span style="color:var(--accent);">HUB</span></div>
            <p style="color:var(--gray); font-size:0.9rem; margin-top:5px;">Hệ Thống Quản Trị Điều Hành</p>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Email Admin</label>
                <input type="email" name="email" class="form-control" required placeholder="admin@trekking.vn">
            </div>
            <div class="form-group">
                <label>Mật khẩu quản trị</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-size:1rem;">ĐĂNG NHẬP ADMIN</button>
        </form>

        <div style="text-align:center; margin-top:20px;">
            <a href="../index.php" style="color:var(--primary); font-size:0.85rem;">&larr; Về trang chủ website</a>
        </div>
    </div>
</body>
</html>