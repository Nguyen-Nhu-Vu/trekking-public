<?php
// trekking/auth/logout.php
require_once __DIR__ . '/../config/database.php';

// Hủy toàn bộ dữ liệu phiên đăng nhập
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Chuyển hướng an toàn về trang đăng nhập
header("Location: " . BASE_URL . "auth/login.php");
exit;