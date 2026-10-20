<?php
require_once 'config/database.php';
$password_hash = password_hash('admin123', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@trekking.vn'")->execute([$password_hash]);
echo "<h2>Đã cập nhật mật khẩu thành công!</h2><p>Mật khẩu tài khoản admin@trekking.vn hiện tại là: <strong>admin123</strong></p><p><a href='login.php'>Bấm vào đây để đăng nhập ngay</a></p>";
?>