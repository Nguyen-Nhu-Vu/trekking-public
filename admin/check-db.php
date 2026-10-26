<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Chỉ quản lý mới được xem thông tin này
requireManager();

$databaseName = $pdo->query(
    "SELECT DATABASE()"
)->fetchColumn();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kiểm tra Database - VNN Travel</title>
</head>
<body style="
    font-family:Arial,sans-serif;
    background:#f1f5f9;
    padding:50px;
">
    <div style="
        max-width:550px;
        margin:auto;
        background:white;
        padding:30px;
        border-radius:15px;
        box-shadow:0 5px 20px #0001;
        text-align:center;
    ">
        <h2>DATABASE WEBSITE ĐANG SỬ DỤNG</h2>

        <h1 style="color:#166534">
            <?= htmlspecialchars(
                (string) $databaseName,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>

        <p>Đây là database MySQL mà website hiện đang kết nối.</p>
    </div>
</body>
</html>
