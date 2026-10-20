<?php
// trekking/debug_login.php
require_once 'config/database.php';

$raw_pass = '!Nguyenvu26';
// Tự sinh hash chuẩn xác trên chính máy chủ PHP XAMPP hiện tại
$current_hash = password_hash($raw_pass, PASSWORD_DEFAULT);

$accounts = [
    'nnvu12345@gmail.com' => ['name' => 'Admin Quản Trị Hệ Thống', 'role' => 'admin'],
    'nguyennhuvu262005@gmail.com' => ['name' => 'Quản Lý Hệ Thống', 'role' => 'manager'],
    'nguyennhuvu2605@gmail.com' => ['name' => 'Nhân Viên Điều Hành', 'role' => 'staff'],
];

echo "<div style='font-family: Arial, sans-serif; max-width: 650px; margin: 30px auto; padding: 25px; border-radius: 10px; background: #f8fafc; border: 1px solid #cbd5e1;'>";
echo "<h2 style='color: #0f172a; margin-top: 0;'>TIẾN TRÌNH XỬ LÝ ĐĂNG NHẬP</h2>";

foreach ($accounts as $email => $info) {
    // 1. Kiểm tra tồn tại
    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $up = $pdo->prepare("UPDATE users SET password = ?, role = ?, status = 1 WHERE id = ?");
        $up->execute([$current_hash, $info['role'], $user['id']]);
        echo "<p style='color: #15803d;'>✔ Đã cập nhật mật khẩu cho: <strong>$email</strong> (Vai trò: {$info['role']})</p>";
    } else {
        $ins = $pdo->prepare("INSERT INTO users (fullname, email, password, role, status) VALUES (?, ?, ?, ?, 1)");
        $ins->execute([$info['name'], strtolower($email), $current_hash, $info['role']]);
        echo "<p style='color: #2563eb;'>✔ Đã tạo mới hoàn toàn tài khoản: <strong>$email</strong> (Vai trò: {$info['role']})</p>";
    }

    // 2. Tự kiểm tra lại độ khớp của password_verify ngay tại chỗ
    $verify_stmt = $pdo->prepare("SELECT password FROM users WHERE LOWER(email) = LOWER(?)");
    $verify_stmt->execute([$email]);
    $saved_hash = $verify_stmt->fetchColumn();

    if (password_verify($raw_pass, $saved_hash)) {
        echo "<span style='background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;'>Xác thực mật khẩu '!Nguyenvu26' THÀNH CÔNG</span><br><br>";
    } else {
        echo "<span style='background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;'>Cảnh báo: Không thể xác thực mật khẩu</span><br><br>";
    }
}

echo "<hr style='border: none; border-top: 1px solid #e2e8f0; margin: 15px 0;'>";
echo "<p><a href='login.php' style='display: inline-block; background: #16a34a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Bấm vào đây để về trang Đăng Nhập</a></p>";
echo "</div>";