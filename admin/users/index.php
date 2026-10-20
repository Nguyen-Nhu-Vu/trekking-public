<?php
// trekking/admin/users/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireAdmin();

$msg = '';
$error = '';

// Kiểm tra số lượng Admin và Quản lý hiện có
$admin_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$manager_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();

// Xử lý tạo tài khoản mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = strtolower(sanitize($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '123456');
    $role = sanitize($_POST['role'] ?? 'staff');
    $phone = sanitize($_POST['phone'] ?? '');

    // RÀNG BUỘC: CHỈ CHO PHÉP TỐI ĐA 1 ADMIN VÀ 1 QUẢN LÝ
    if ($role === 'admin' && $admin_count >= 1) {
        $error = "Hệ thống chỉ được phép tồn tại duy nhất 1 tài khoản Admin!";
    } elseif ($role === 'manager' && $manager_count >= 1) {
        $error = "Hệ thống chỉ được phép tồn tại duy nhất 1 tài khoản Quản lý!";
    } elseif (empty($fullname) || empty($email)) {
        $error = "Vui lòng nhập đầy đủ họ tên và email!";
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = "Email này đã tồn tại trên hệ thống!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, phone, role, status) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$fullname, $email, $hashed, $phone, $role]);
            $msg = "Cấp phát tài khoản thành công!";
            // Cập nhật lại số lượng sau khi thêm
            $admin_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            $manager_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
        }
    }
}

// Khóa hoặc mở khóa tài khoản
if (isset($_GET['toggle_id'])) {
    $uid = intval($_GET['toggle_id']);
    // Không cho phép tự khóa tài khoản Admin đang đăng nhập
    if ($uid != $_SESSION['user_id']) {
        $st = intval($_GET['status']) == 1 ? 0 : 1;
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$st, $uid]);
        header("Location: index.php");
        exit;
    }
}

// Xóa tài khoản
if (isset($_GET['delete_id'])) {
    $uid = intval($_GET['delete_id']);
    if ($uid != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        header("Location: index.php");
        exit;
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY FIELD(role, 'admin', 'manager', 'staff', 'customer'), id ASC")->fetchAll();
$current_page = 'users';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Tài Khoản & Phân Quyền - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN TRỊ TÀI KHOẢN HỆ THỐNG</strong></div>
        </div>

        <div class="admin-content">
            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <div class="grid grid-3" style="align-items:start;">
                <!-- Form cấp tài khoản mới -->
                <div style="background:white; padding:25px; border-radius:10px; box-shadow:var(--shadow);">
                    <h3 style="margin-bottom:12px; color:var(--dark);">Tạo Tài Khoản Mới</h3>
                    <p style="font-size:0.82rem; color:#64748b; margin-bottom:15px;">
                        Quy định: Chỉ <strong>1 Admin</strong>, <strong>1 Quản lý</strong>, không giới hạn <strong>Nhân viên</strong>.
                    </p>
                    <form action="" method="POST">
                        <input type="hidden" name="add_user" value="1">
                        <div class="form-group">
                            <label>Họ và tên *</label>
                            <input type="text" name="fullname" class="form-control" required placeholder="Nguyễn Văn A">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required placeholder="user@gmail.com">
                        </div>
                        <div class="form-group">
                            <label>Mật khẩu khởi tạo</label>
                            <input type="text" name="password" class="form-control" value="!Nguyenvu26" required>
                        </div>
                        <div class="form-group">
                            <label>Vai trò (Role) *</label>
                            <select name="role" class="form-control" required>
                                <option value="staff">Nhân viên (Có thể tạo nhiều)</option>
                                <option value="customer">Khách hàng (Có thể tạo nhiều)</option>
                                
                                <!-- Nếu đã có 1 Admin hoặc 1 Quản lý thì khóa tùy chọn đó -->
                                <?php if ($admin_count < 1): ?>
                                    <option value="admin">Admin tối cao</option>
                                <?php endif; ?>
                                <?php if ($manager_count < 1): ?>
                                    <option value="manager">Quản lý điều hành</option>
                                <?php endif; ?>
                            </select>
                            <?php if ($admin_count >= 1 && $manager_count >= 1): ?>
                                <small style="color:#15803d; font-size:0.75rem; margin-top:4px; display:block;">
                                    ✔ Đã đủ 1 Admin và 1 Quản lý. Giờ chỉ có thể cấp thêm Nhân viên hoặc Khách.
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Số điện thoại</label>
                            <input type="tel" name="phone" class="form-control" placeholder="09xxxxxxxx">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-user-plus"></i> CẤP TÀI KHOẢN</button>
                    </form>
                </div>

                <!-- Bảng danh sách tài khoản -->
                <div style="grid-column: span 2;" class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Họ Tên</th>
                                <th>Email</th>
                                <th>Vai Trò</th>
                                <th>Trạng Thái</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($u['fullname']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <?php
                                            $b_color = '#0284c7';
                                            if ($u['role'] === 'admin') $b_color = '#dc2626';
                                            elseif ($u['role'] === 'manager') $b_color = '#ea580c';
                                            elseif ($u['role'] === 'customer') $b_color = '#16a34a';
                                        ?>
                                        <span class="badge" style="background:<?= $b_color ?>;">
                                            <?= strtoupper($u['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $u['status'] == 1 ? '<span style="color:green; font-weight:600;">Hoạt động</span>' : '<span style="color:red; font-weight:600;">Đang khóa</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <a href="index.php?toggle_id=<?= $u['id'] ?>&status=<?= $u['status'] ?>" class="btn btn-sm <?= $u['status'] == 1 ? 'btn-danger' : 'btn-primary' ?>" style="padding:4px 8px;">
                                                <?= $u['status'] == 1 ? '<i class="fa-solid fa-lock"></i> Khóa' : '<i class="fa-solid fa-unlock"></i> Mở' ?>
                                            </a>
                                            <?php if ($u['role'] !== 'admin'): ?>
                                                <a href="index.php?delete_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline" style="padding:4px 8px; color:red; border-color:red;" onclick="return confirm('Bạn có chắc muốn xóa tài khoản này?');">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <small style="color:#64748b; font-style:italic;">(Đang dùng)</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>