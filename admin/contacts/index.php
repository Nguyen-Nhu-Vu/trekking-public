<?php
// trekking/admin/contacts/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Cho phép cả Staff và Manager truy cập (thay vì chỉ Admin)
requireStaff();

$msg = '';

// Xóa tin nhắn liên hệ
if (isset($_GET['del'])) {
    $del = intval($_GET['del']);
    $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$del]);
    $msg = "Đã xóa tin nhắn liên hệ thành công!";
}

$contacts = $pdo->query("SELECT * FROM contacts ORDER BY id DESC")->fetchAll();

$current_page = 'contacts';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hộp Thư Khách Hàng - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <!-- Nhúng thanh sidebar Accordion dùng chung -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>HỘP THƯ TIN NHẮN TỪ KHÁCH HÀNG</strong></div>
        </div>

        <div class="admin-content">
            <?php if ($msg): ?>
                <div class="alert alert-success"><?= $msg ?></div>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Họ Tên</th>
                            <th>Email</th>
                            <th>Số Điện Thoại</th>
                            <th>Nội Dung Thư</th>
                            <th>Ngày Gửi</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($contacts) > 0): ?>
                            <?php foreach ($contacts as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['fullname']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['email']) ?></td>
                                    <td><?= htmlspecialchars($c['phone'] ?? '---') ?></td>
                                    <td style="max-width: 380px;"><?= nl2br(htmlspecialchars($c['message'])) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                                    <td>
                                        <a href="index.php?del=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc muốn xóa tin nhắn này?');">
                                            <i class="fa-solid fa-trash"></i> Xóa
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:35px; color:#64748b;">
                                    Hộp thư hiện đang trống, chưa có tin nhắn liên hệ mới nào.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>