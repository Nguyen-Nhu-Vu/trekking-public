
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

if (!function_exists('vnnAdminLoginH')) {
    function vnnAdminLoginH($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE LOWER(email) = ? AND role = 'admin'
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $result = 'failed';
    $note = 'Email hoặc mật khẩu không hợp lệ';

    if ($user && password_verify($password, $user['password'])) {
        if ((int)$user['status'] === 0) {
            $result = 'locked';
            $note = 'Tài khoản bị khóa';
            $error = 'Tài khoản đang bị khóa.';
        } else {
            $result = 'success';
            $note = 'Đăng nhập Admin thành công';
        }
    } else {
        $error = 'Email hoặc mật khẩu Admin không chính xác.';
    }

    try {
        $log = $pdo->prepare("
            INSERT INTO login_audit_logs
            (
                user_id,account_email,account_name,
                account_role,ip_address,user_agent,result,note
            )
            VALUES (?,?,?,?,?,?,?,?)
        ");

        $log->execute([
            $user['id'] ?? null,
            $email,
            $user['fullname'] ?? null,
            $user['role'] ?? null,
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,1000),
            $result,
            $note
        ]);
    } catch (Throwable $e) {
        error_log('Admin login audit: '.$e->getMessage());
    }

    if ($result === 'success') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        header(
            'Location: '.rtrim(BASE_URL,'/').'/admin/system.php?tab=users',
            true,
            303
        );
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng nhập Admin - VNN Travel</title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 20px;
    background: linear-gradient(135deg,#0f172a,#173b2d);
    font-family: Arial,Helvetica,sans-serif;
}
.login-card {
    background: white;
    padding: 35px;
    width: 100%;
    max-width: 420px;
    border-radius: 16px;
    box-shadow: 0 25px 60px rgba(0,0,0,.25);
}
.login-brand {
    text-align: center;
    margin-bottom: 25px;
}
.login-brand i {
    color: #65a30d;
    font-size: 43px;
}
.login-brand h1 {
    font-size: 27px;
    color: #f97316;
    margin: 10px 0 5px;
    letter-spacing: 3px;
}
.login-brand p {
    color: #64748b;
    font-size: 13px;
}
.field { margin-bottom: 17px; }
.field label {
    display: block;
    font-weight: bold;
    color: #334155;
    font-size: 13px;
    margin-bottom: 8px;
}
.field input {
    width: 100%;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font-size: 14px;
}
button {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 9px;
    background: #166534;
    color: white;
    font-weight: 800;
    cursor: pointer;
}
.error {
    padding: 12px;
    margin-bottom: 17px;
    border-radius: 9px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 13px;
}
.back {
    display: block;
    margin-top: 20px;
    text-align: center;
    color: #166534;
    text-decoration: none;
    font-size: 13px;
}
</style>
</head>
<body>
    <main class="login-card">
        <div class="login-brand">
            <i class="fa-solid fa-mountain-sun"></i>
            <h1>VNN TRAVEL</h1>
            <p>Đăng nhập quản trị hệ thống</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= vnnAdminLoginH($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label>Email Admin</label>
                <input name="email" type="email" required
                       autocomplete="username"
                       placeholder="Nhập email Admin">
            </div>

            <div class="field">
                <label>Mật khẩu</label>
                <input name="password" type="password" required
                       autocomplete="current-password"
                       placeholder="Nhập mật khẩu">
            </div>

            <button type="submit">
                <i class="fa-solid fa-right-to-bracket"></i>
                ĐĂNG NHẬP
            </button>
        </form>

        <a class="back"
           href="<?= vnnAdminLoginH(rtrim(BASE_URL,'/').'/index.php') ?>">
            Quay về website
        </a>
    </main>
</body>
</html>
