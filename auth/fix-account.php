<?php
// trekking/fix_account.php
require_once 'config/database.php';

// Mật khẩu bạn yêu cầu
$raw_pass = '!Nguyenvu26';
$hashed = password_hash($raw_pass, PASSWORD_BCRYPT);

// Danh sách 3 tài khoản chuẩn
$accounts = [
    [
        'fullname' => 'Admin Quản Trị Hệ Thống',
        'email' => 'nnvu12345@gmail.com',
        'role' => 'admin'
    ],
    [
        'fullname' => 'Quản Lý Hệ Thống',
        'email' => 'nguyennhuvu262005@gmail.com',
        'role' => 'manager'
    ],
    [
        'fullname' => 'Nhân Viên Điều Hành',
        'email' => 'nguyennhuvu2605@gmail.com',
        'role' => 'staff'
    ]
];

echo "<div style='font-family:sans-serif; max-width:600px; margin:40px auto; padding:20px; border:1px solid #16a34a; border-radius:8px; background:#f0fdf4;'>";
echo "<h2 style='color:#166534; margin-top:0;'>Đang cập nhật mật khẩu...</h2>";

foreach ($accounts as $acc) {
    // Kiểm tra xem đã có email chưa
    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $chk->execute([$acc['email']]);
    $u = $chk->fetch();

    if ($u) {
        $up = $pdo->prepare("UPDATE users SET password = ?, role = ?, status = 1 WHERE email = ?");
        $up->execute([$hashed, $acc['role'], $acc['email']]);
        echo "<p>✔ Đã cập nhật mật khẩu cho: <strong>{$acc['email']}</strong> ({$acc['role']})</p>";
    } else {
        $ins = $pdo->prepare("INSERT INTO users (fullname, email, password, role, status) VALUES (?, ?, ?, ?, 1)");
        $ins->execute([$acc['fullname'], $acc['email'], $hashed, $acc['role']]);
        echo "<p>✔ Đã tạo mới tài khoản: <strong>{$acc['email']}</strong> ({$acc['role']})</p>";
    }
}

echo "<hr style='border:none; border-top:1px solid #bbf7d0; margin:15px 0;'>";
echo "<p style='color:#15803d; font-weight:bold;'>Mật khẩu chung đã được đặt chính xác thành: <span style='background:#ffffff; padding:2px 8px; border-radius:4px; border:1px solid #86efac;'>!Nguyenvu26</span></p>";
echo "<p><a href='login.php' style='display:inline-block; background:#16a34a; color:white; padding:10px 18px; text-decoration:none; border-radius:6px; font-weight:bold;'>Bấm vào đây để đăng nhập ngay &rarr;</a></p>";
echo "</div>";