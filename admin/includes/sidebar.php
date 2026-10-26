<?php
// VNN Travel - Admin Accounts & Activity History
// Luu truc tiep tren trang, khong dung header() redirect.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$current_page = 'system-users';

if (!function_exists('vnnSysEsc')) {
    function vnnSysEsc($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('vnnSysRows')) {
    function vnnSysRows(
        PDO $pdo,
        string $sql,
        array $params = []
    ): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('vnnSysWriteAudit')) {
    function vnnSysWriteAudit(
        PDO $pdo,
        string $action,
        int $targetId,
        string $oldValue,
        string $newValue
    ): void {
        $actorId = (int)($_SESSION['user_id'] ?? 0);

        $actorStmt = $pdo->prepare("
            SELECT fullname
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $actorStmt->execute([$actorId]);

        $actorName = (
            $actorStmt->fetchColumn() ?: 'Admin'
        );

        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_logs (
                actor_id,
                actor_name,
                category,
                action,
                target_type,
                target_id,
                old_value,
                new_value
            )
            VALUES (
                ?, ?, 'accounts', ?, 'users', ?, ?, ?
            )
        ");

        $stmt->execute([
            $actorId,
            $actorName,
            $action,
            (string)$targetId,
            $oldValue,
            $newValue
        ]);
    }
}

// =========================================
// TAB
// =========================================

$tab = (string)($_GET['tab'] ?? 'users');

if (!in_array($tab, ['users', 'history'], true)) {
    $tab = 'users';
}

$categories = [
    'accounts' => 'Tài khoản',
    'content' => 'Nội dung website',
    'transactions' => 'Giao dịch',
    'security' => 'Bảo mật'
];

$category = (string)(
    $_GET['category'] ?? 'accounts'
);

if (!isset($categories[$category])) {
    $category = 'accounts';
}

$current_page = 'system-' . $tab;

$page_title = $tab === 'users'
    ? 'Quản lý & Cấp quyền tài khoản - VNN Travel'
    : 'Lịch sử hoạt động - VNN Travel';

// =========================================
// CSRF
// =========================================

if (empty($_SESSION['admin_system_csrf'])) {
    $_SESSION['admin_system_csrf'] =
        bin2hex(random_bytes(32));
}

$csrf = $_SESSION['admin_system_csrf'];

$popup = null;

// =========================================
// XU LY THEM, KHOA, MO KHOA, XOA
// KHONG DUNG header() CHUYEN TRANG
// =========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $postedCsrf = (string)(
            $_POST['csrf'] ?? ''
        );

        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
            );
        }

        $action = (string)(
            $_POST['action'] ?? ''
        );

        // =================================
        // THEM TAI KHOAN
        // =================================

        if ($action === 'add_user') {

            $fullname = trim((string)(
                $_POST['fullname'] ?? ''
            ));

            $email = strtolower(trim((string)(
                $_POST['email'] ?? ''
            )));

            $phone = trim((string)(
                $_POST['phone'] ?? ''
            ));

            $password = (string)(
                $_POST['password'] ?? ''
            );

            $role = (string)(
                $_POST['role'] ?? 'staff'
            );

            if (
                $fullname === '' ||
                mb_strlen($fullname, 'UTF-8') > 255
            ) {
                throw new RuntimeException(
                    'Họ tên không hợp lệ.'
                );
            }

            if (
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                throw new RuntimeException(
                    'Email không hợp lệ.'
                );
            }

            if (strlen($password) < 8) {
                throw new RuntimeException(
                    'Mật khẩu phải có ít nhất 8 ký tự.'
                );
            }

            if (!in_array(
                $role,
                ['staff', 'customer'],
                true
            )) {
                throw new RuntimeException(
                    'Chỉ được tạo tài khoản nhân viên hoặc khách hàng.'
                );
            }

            $pdo->beginTransaction();

            $check = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $check->execute([$email]);

            if ($check->fetchColumn()) {
                throw new RuntimeException(
                    'Email này đã tồn tại.'
                );
            }

            $insert = $pdo->prepare("
                INSERT INTO users (
                    fullname,
                    email,
                    password,
                    phone,
                    role,
                    status
                )
                VALUES (?, ?, ?, ?, ?, 1)
            ");

            $insert->execute([
                $fullname,
                $email,
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                $phone,
                $role
            ]);

            $newId = (int)$pdo->lastInsertId();

            vnnSysWriteAudit(
                $pdo,
                'Tạo tài khoản',
                $newId,
                '',
                $fullname . ' | ' . $email .
                ' | ' . $role
            );

            $pdo->commit();

            $popup = [
                'type' => 'success',
                'title' => 'Đã tạo tài khoản',
                'message' => 'Tài khoản mới đã được thêm thành công.'
            ];

        // =================================
        // KHOA / MO KHOA TAI KHOAN
        // =================================

        } elseif ($action === 'toggle_user') {

            $userId = (int)(
                $_POST['user_id'] ?? 0
            );

            if ($userId <= 0) {
                throw new RuntimeException(
                    'Tài khoản không hợp lệ.'
                );
            }

            if (
                $userId ===
                (int)($_SESSION['user_id'] ?? 0)
            ) {
                throw new RuntimeException(
                    'Không thể khóa tài khoản đang đăng nhập.'
                );
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    fullname,
                    email,
                    role,
                    status
                FROM users
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$userId]);

            $user = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            $stmt->closeCursor();

            if (!$user) {
                throw new RuntimeException(
                    'Không tìm thấy tài khoản.'
                );
            }

            if ($user['role'] === 'admin') {
                throw new RuntimeException(
                    'Tài khoản Admin được bảo vệ.'
                );
            }

            $oldStatus = (int)$user['status'];

            $newStatus = $oldStatus === 1
                ? 0
                : 1;

            $update = $pdo->prepare("
                UPDATE users
                SET status = ?
                WHERE id = ?
            ");

            $update->execute([
                $newStatus,
                $userId
            ]);

            $actionName = $newStatus === 1
                ? 'Mở khóa tài khoản'
                : 'Khóa tài khoản';

            vnnSysWriteAudit(
                $pdo,
                $actionName,
                $userId,
                (string)$oldStatus,
                (string)$newStatus
            );

            $pdo->commit();

            $popup = [
                'type' => 'success',
                'title' => 'Đã lưu thay đổi',
                'message' => $newStatus === 1
                    ? 'Tài khoản đã được mở khóa.'
                    : 'Tài khoản đã được khóa.'
            ];

        // =================================
        // XOA TAI KHOAN
        // =================================

        } elseif ($action === 'delete_user') {

            $userId = (int)(
                $_POST['user_id'] ?? 0
            );

            if ($userId <= 0) {
                throw new RuntimeException(
                    'Tài khoản không hợp lệ.'
                );
            }

            if (
                $userId ===
                (int)($_SESSION['user_id'] ?? 0)
            ) {
                throw new RuntimeException(
                    'Không thể xóa tài khoản đang đăng nhập.'
                );
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    fullname,
                    email,
                    role,
                    status
                FROM users
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$userId]);

            $user = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            $stmt->closeCursor();

            if (!$user) {
                throw new RuntimeException(
                    'Tài khoản không còn tồn tại.'
                );
            }

            // Bao ve hai vai tro quan trong.
            if (in_array(
                $user['role'],
                ['admin', 'manager'],
                true
            )) {
                throw new RuntimeException(
                    'Không thể xóa tài khoản Admin hoặc Manager.'
                );
            }

            // Ghi lich su truoc khi xoa.
            vnnSysWriteAudit(
                $pdo,
                'Xóa tài khoản',
                $userId,
                $user['fullname'] . ' | ' .
                $user['email'] . ' | ' .
                $user['role'],
                'Đã xóa'
            );

            // Database se chan xoa neu user
            // con du lieu co khoa ngoai.
            // Khong tu dong xoa booking/don hang.
            $delete = $pdo->prepare("
                DELETE FROM users
                WHERE id = ?
                  AND role IN ('staff', 'customer')
            ");

            $delete->execute([$userId]);

            if ($delete->rowCount() !== 1) {
                throw new RuntimeException(
                    'Không thể xóa tài khoản này.'
                );
            }

            $pdo->commit();

            $popup = [
                'type' => 'success',
                'title' => 'Đã xóa tài khoản',
                'message' => 'Tài khoản đã được xóa thành công.'
            ];

        } else {

            throw new RuntimeException(
                'Thao tác không được hỗ trợ.'
            );
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Admin account action: ' .
            $e->getMessage()
        );

        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể thực hiện thao tác.';

        if (
            $e instanceof PDOException &&
            (string)$e->getCode() === '23000'
        ) {
            $message =
                'Tài khoản đang liên kết với đơn hàng, ' .
                'booking hoặc dữ liệu khác. ' .
                'Hãy khóa tài khoản thay vì xóa để ' .
                'bảo toàn lịch sử.';
        }

        $popup = [
            'type' => 'error',
            'title' => 'Thao tác không thành công',
            'message' => $message
        ];
    }

    // Sau POST van hien thi trang binh thuong.
    // Khong goi header() nen tranh loi
    // "headers already sent" o thao tac nay.
    $tab = 'users';
    $current_page = 'system-users';
}

// =========================================
// LAY DANH SACH TAI KHOAN
// =========================================

$users = vnnSysRows($pdo, "
    SELECT
        id,
        fullname,
        email,
        phone,
        role,
        status
    FROM users
    ORDER BY FIELD(
        role,
        'admin',
        'manager',
        'staff',
        'customer'
    ), id
");

$roleCounts = [
    'admin' => 0,
    'manager' => 0,
    'staff' => 0,
    'customer' => 0
];

$lockedCount = 0;

foreach ($users as $user) {

    $role = (string)$user['role'];

    if (isset($roleCounts[$role])) {
        $roleCounts[$role]++;
    }

    if ((int)$user['status'] === 0) {
        $lockedCount++;
    }
}

// =========================================
// LAY LICH SU HOAT DONG
// =========================================

$logs = [];
$logError = '';

if ($tab === 'history') {

    try {
        $logs = vnnSysRows($pdo, "
            SELECT
                id,
                actor_name,
                category,
                action,
                target_type,
                target_id,
                old_value,
                new_value,
                created_at
            FROM admin_audit_logs
            WHERE category = ?
            ORDER BY id DESC
            LIMIT 300
        ", [$category]);

    } catch (Throwable $e) {

        error_log(
            'Load audit history: ' .
            $e->getMessage()
        );

        $logError =
            'Không thể đọc lịch sử. ' .
            'Hãy kiểm tra bảng admin_audit_logs.';
    }
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= vnnSysEsc($page_title) ?></title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >

    <style>
        .vnn-system-main {
            flex: 1 1 auto;
            min-width: 0;
            background: #f1f5f9;
        }

        .vnn-system-content {
            padding: 22px;
        }

        .vnn-system-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .vnn-system-stat,
        .vnn-system-panel {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
        }

        .vnn-system-stat {
            padding: 20px;
        }

        .vnn-system-stat span {
            display: block;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }

        .vnn-system-stat strong {
            display: block;
            margin-top: 10px;
            font-size: 28px;
            color: #166534;
        }

        .vnn-system-panel {
            padding: 22px;
            margin-bottom: 20px;
        }

        .vnn-system-panel h2 {
            margin: 0 0 18px;
            color: #0f172a;
            font-size: 19px;
        }

        .vnn-system-fields {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap: 15px;
        }

        .vnn-system-field label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
        }

        .vnn-system-field input,
        .vnn-system-field select {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            font: inherit;
            font-size: 13px;
        }

        .vnn-system-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 15px;
            border: 0;
            border-radius: 9px;
            background: #166534;
            color: white;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .vnn-system-button:hover {
            background: #14532d;
        }

        .vnn-system-button.red {
            background: #dc2626;
        }

        .vnn-system-button.red:hover {
            background: #b91c1c;
        }

        .vnn-system-button.small {
            padding: 8px 12px;
        }

        .vnn-system-button.outline {
            background: white;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .vnn-system-button.outline:hover {
            background: #fef2f2;
        }

        .vnn-system-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .vnn-system-filters input,
        .vnn-system-filters select {
            flex: 1;
            min-width: 160px;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font: inherit;
            font-size: 13px;
        }

        .vnn-system-table-wrap {
            overflow-x: auto;
        }

        .vnn-system-table {
            width: 100%;
            min-width: 930px;
            border-collapse: collapse;
        }

        .vnn-system-table th,
        .vnn-system-table td {
            padding: 13px 11px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
            vertical-align: middle;
        }

        .vnn-system-table th {
            background: #f8fafc;
            color: #0f172a;
            font-weight: 800;
        }

        .vnn-system-table tr[hidden] {
            display: none !important;
        }

        .vnn-system-actions {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 8px;
        }

        .vnn-system-status {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 11px;
            font-weight: 800;
        }

        .vnn-system-status.locked {
            background: #fee2e2;
            color: #991b1b;
        }

        .vnn-system-muted {
            color: #64748b;
            font-size: 12px;
            line-height: 1.8;
        }

        .vnn-system-history-value {
            max-width: 300px;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
            font-size: 12px;
        }

        /* POPUP THONG BAO */

        .vnn-system-overlay {
            position: fixed;
            inset: 0;
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15,23,42,.50);
            box-sizing: border-box;
        }

        .vnn-system-overlay[hidden] {
            display: none !important;
        }

        .vnn-system-modal {
            width: 100%;
            max-width: 390px;
            padding: 30px;
            border-radius: 18px;
            background: white;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
            box-sizing: border-box;
            text-align: center;
        }

        .vnn-system-modal-icon {
            font-size: 43px;
            color: #166534;
        }

        .vnn-system-modal.is-error .vnn-system-modal-icon {
            color: #dc2626;
        }

        .vnn-system-modal h2 {
            color: #0f172a;
            font-size: 21px;
            margin: 13px 0;
        }

        .vnn-system-modal p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .vnn-system-modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 22px;
        }

        @media(max-width:900px) {
            .vnn-system-stats {
                grid-template-columns: repeat(2,1fr);
            }

            .vnn-system-content {
                padding: 12px;
            }
        }

        @media(max-width:650px) {
            .vnn-system-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="admin-main vnn-system-main">

    <div class="admin-topbar">

        <strong>
            <i class="fa-solid <?= $tab === 'history'
                ? 'fa-clock-rotate-left'
                : 'fa-users-gear' ?>"></i>

            <?= $tab === 'history'
                ? 'LỊCH SỬ HOẠT ĐỘNG'
                : 'QUẢN LÝ & CẤP QUYỀN TÀI KHOẢN' ?>
        </strong>

    </div>

    <div class="vnn-system-content">

        <?php if ($tab === 'users'): ?>

            <!-- THONG KE TAI KHOAN -->

            <div class="vnn-system-stats">

                <?php foreach ([
                    ['Tổng tài khoản', count($users)],
                    ['Nhân viên', $roleCounts['staff']],
                    ['Khách hàng', $roleCounts['customer']],
                    ['Đang khóa', $lockedCount]
                ] as $stat): ?>

                    <div class="vnn-system-stat">

                        <span>
                            <?= vnnSysEsc($stat[0]) ?>
                        </span>

                        <strong>
                            <?= (int)$stat[1] ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

            <!-- THEM TAI KHOAN -->

            <section class="vnn-system-panel">

                <h2>
                    <i class="fa-solid fa-user-plus"></i>
                    Thêm tài khoản mới
                </h2>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= vnnSysEsc($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="add_user"
                    >

                    <div class="vnn-system-fields">

                        <div class="vnn-system-field">
                            <label>Họ và tên</label>

                            <input
                                name="fullname"
                                maxlength="255"
                                required
                            >
                        </div>

                        <div class="vnn-system-field">
                            <label>Email</label>

                            <input
                                name="email"
                                type="email"
                                required
                            >
                        </div>

                        <div class="vnn-system-field">
                            <label>Số điện thoại</label>

                            <input name="phone">
                        </div>

                        <div class="vnn-system-field">
                            <label>Mật khẩu khởi tạo</label>

                            <input
                                name="password"
                                type="password"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="vnn-system-field">
                            <label>Vai trò</label>

                            <select name="role">
                                <option value="staff">
                                    Nhân viên
                                </option>

                                <option value="customer">
                                    Khách hàng
                                </option>
                            </select>
                        </div>

                    </div>

                    <button
                        type="submit"
                        class="vnn-system-button"
                        style="margin-top:18px"
                    >
                        <i class="fa-solid fa-user-plus"></i>
                        Tạo tài khoản
                    </button>

                </form>

            </section>

            <!-- DANH SACH TAI KHOAN -->

            <section class="vnn-system-panel">

                <h2>
                    <i class="fa-solid fa-users"></i>
                    Danh sách tài khoản
                </h2>

                <div class="vnn-system-filters">

                    <input
                        id="vnnUserSearch"
                        placeholder="Tìm tên hoặc email..."
                    >

                    <select id="vnnUserRole">

                        <option value="">
                            Tất cả vai trò
                        </option>

                        <option value="admin">
                            Admin
                        </option>

                        <option value="manager">
                            Manager
                        </option>

                        <option value="staff">
                            Staff
                        </option>

                        <option value="customer">
                            Customer
                        </option>

                    </select>

                    <select id="vnnUserStatus">

                        <option value="">
                            Tất cả trạng thái
                        </option>

                        <option value="1">
                            Hoạt động
                        </option>

                        <option value="0">
                            Đang khóa
                        </option>

                    </select>

                </div>

                <div class="vnn-system-table-wrap">

                    <table class="vnn-system-table">

                        <thead>
                            <tr>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th>Khóa / Mở khóa</th>
                                <th>Xóa tài khoản</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($users as $user): ?>

                                <?php
                                $id = (int)$user['id'];

                                $protected = in_array(
                                    $user['role'],
                                    ['admin', 'manager'],
                                    true
                                );
                                ?>

                                <tr
                                    class="vnn-user-row"
                                    data-search="<?= vnnSysEsc(
                                        mb_strtolower(
                                            $user['fullname'] . ' ' .
                                            $user['email'],
                                            'UTF-8'
                                        )
                                    ) ?>"
                                    data-role="<?= vnnSysEsc(
                                        $user['role']
                                    ) ?>"
                                    data-status="<?= (int)(
                                        $user['status']
                                    ) ?>"
                                >

                                    <!-- HO TEN -->

                                    <td>

                                        <strong>
                                            <?= vnnSysEsc(
                                                $user['fullname']
                                            ) ?>
                                        </strong>

                                    </td>

                                    <!-- EMAIL -->

                                    <td>
                                        <?= vnnSysEsc(
                                            $user['email']
                                        ) ?>
                                    </td>

                                    <!-- VAI TRO -->

                                    <td>
                                        <?= vnnSysEsc(
                                            strtoupper(
                                                $user['role']
                                            )
                                        ) ?>
                                    </td>

                                    <!-- TRANG THAI -->

                                    <td>

                                        <span class="vnn-system-status <?= (int)$user['status'] === 1 ? '' : 'locked' ?>">

                                            <?= (int)$user['status'] === 1
                                                ? 'Hoạt động'
                                                : 'Đang khóa' ?>

                                        </span>

                                    </td>

                                    <!-- KHOA / MO KHOA -->

                                    <td>

                                        <?php if (
                                            $user['role'] !== 'admin'
                                        ): ?>

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="csrf"
                                                    value="<?= vnnSysEsc($csrf) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="toggle_user"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?= $id ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="vnn-system-button small <?= (int)$user['status'] === 1 ? 'red' : '' ?>"
                                                >

                                                    <?= (int)$user['status'] === 1
                                                        ? 'Khóa'
                                                        : 'Mở khóa' ?>

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <span class="vnn-system-muted">
                                                Được bảo vệ
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- XOA TAI KHOAN -->

                                    <td>

                                        <?php if (!$protected): ?>

                                            <button
                                                type="button"
                                                class="vnn-system-button small outline vnn-delete-user"
                                                data-id="<?= $id ?>"
                                                data-name="<?= vnnSysEsc(
                                                    $user['fullname']
                                                ) ?>"
                                            >

                                                <i class="fa-solid fa-trash"></i>
                                                Xóa

                                            </button>

                                        <?php else: ?>

                                            <span class="vnn-system-muted">
                                                Không được xóa
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        <?php else: ?>

            <!-- LICH SU HOAT DONG -->

            <section class="vnn-system-panel">

                <h2>
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <?= vnnSysEsc(
                        $categories[$category]
                    ) ?>
                </h2>

                <p class="vnn-system-muted">
                    Chọn từng nhóm trong menu Lịch sử hoạt động
                    bên trái để xem các bản ghi tương ứng.
                    Chỉ những thao tác đã tích hợp ghi nhật ký
                    mới xuất hiện ở đây.
                </p>

                <?php if ($logError): ?>

                    <p style="color:#dc2626">
                        <?= vnnSysEsc($logError) ?>
                    </p>

                <?php endif; ?>

                <div class="vnn-system-filters">

                    <input
                        id="vnnHistorySearch"
                        placeholder="Tìm người thực hiện hoặc hành động..."
                    >

                    <input
                        type="date"
                        id="vnnHistoryDate"
                        aria-label="Lọc theo ngày"
                    >

                </div>

                <div class="vnn-system-table-wrap">

                    <table class="vnn-system-table">

                        <thead>
                            <tr>
                                <th>Thời gian</th>
                                <th>Người thực hiện</th>
                                <th>Hành động</th>
                                <th>Đối tượng</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($logs as $log): ?>

                                <tr
                                    class="vnn-log-row"
                                    data-date="<?= vnnSysEsc(
                                        substr(
                                            $log['created_at'],
                                            0,
                                            10
                                        )
                                    ) ?>"
                                    data-search="<?= vnnSysEsc(
                                        mb_strtolower(
                                            $log['actor_name'] . ' ' .
                                            $log['action'],
                                            'UTF-8'
                                        )
                                    ) ?>"
                                >

                                    <td>
                                        <?= vnnSysEsc(
                                            $log['created_at']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= vnnSysEsc(
                                            $log['actor_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= vnnSysEsc(
                                                $log['action']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= vnnSysEsc(
                                            $log['target_type']
                                        ) ?>

                                        #<?= vnnSysEsc(
                                            $log['target_id']
                                        ) ?>
                                    </td>

                                    <td>

                                        <details>

                                            <summary>
                                                Xem chi tiết
                                            </summary>

                                            <div class="vnn-system-history-value">

                                                <strong>Trước:</strong>
                                                <?= vnnSysEsc(
                                                    $log['old_value']
                                                ) ?>

                                                <strong>Sau:</strong>
                                                <?= vnnSysEsc(
                                                    $log['new_value']
                                                ) ?>

                                            </div>

                                        </details>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <p
                    id="vnnEmptyLog"
                    class="vnn-system-muted"
                    <?= $logs ? 'hidden' : '' ?>
                >
                    Chưa có nhật ký phù hợp.
                </p>

            </section>

        <?php endif; ?>

    </div>

</main>

<!-- ======================================
     FORM XOA TAI KHOAN
     ====================================== -->

<form
    method="POST"
    id="vnnDeleteForm"
    hidden
>
    <input
        type="hidden"
        name="csrf"
        value="<?= vnnSysEsc($csrf) ?>"
    >

    <input
        type="hidden"
        name="action"
        value="delete_user"
    >

    <input
        type="hidden"
        name="user_id"
        id="vnnDeleteId"
        value=""
    >
</form>

<!-- ======================================
     POPUP XAC NHAN XOA
     ====================================== -->

<div
    class="vnn-system-overlay"
    id="vnnConfirmOverlay"
    hidden
>

    <div class="vnn-system-modal">

        <div
            class="vnn-system-modal-icon"
            style="color:#dc2626"
        >
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2>Xác nhận xóa tài khoản</h2>

        <p>
            Bạn muốn xóa tài khoản
            <strong id="vnnDeleteName"></strong>?
        </p>

        <p>
            Thao tác này không thể hoàn tác.
            Nếu tài khoản có dữ liệu liên quan,
            hệ thống sẽ không xóa.
        </p>

        <div class="vnn-system-modal-buttons">

            <button
                type="button"
                class="vnn-system-button"
                id="vnnCancelDelete"
            >
                Hủy
            </button>

            <button
                type="button"
                class="vnn-system-button red"
                id="vnnConfirmDelete"
            >
                <i class="fa-solid fa-trash"></i>
                Xóa tài khoản
            </button>

        </div>

    </div>

</div>

<!-- ======================================
     POPUP KET QUA
     ====================================== -->

<div
    class="vnn-system-overlay"
    id="vnnResultOverlay"
    <?= $popup ? '' : 'hidden' ?>
>

    <div class="vnn-system-modal <?= ($popup['type'] ?? '') === 'error' ? 'is-error' : '' ?>">

        <div class="vnn-system-modal-icon">

            <i class="fa-solid <?= ($popup['type'] ?? '') === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>

        </div>

        <h2>
            <?= vnnSysEsc(
                $popup['title'] ?? 'Thông báo'
            ) ?>
        </h2>

        <p>
            <?= vnnSysEsc(
                $popup['message'] ?? ''
            ) ?>
        </p>

        <div class="vnn-system-modal-buttons">

            <button
                type="button"
                class="vnn-system-button"
                id="vnnCloseResult"
            >
                Đóng
            </button>

        </div>

    </div>

</div>

<script>
(function() {

    // ====================================
    // LOC TAI KHOAN
    // ====================================

    const search = document.getElementById(
        'vnnUserSearch'
    );

    const role = document.getElementById(
        'vnnUserRole'
    );

    const status = document.getElementById(
        'vnnUserStatus'
    );

    function filterUsers() {

        if (!search) {
            return;
        }

        const term = search.value
            .trim()
            .toLocaleLowerCase('vi');

        document.querySelectorAll(
            '.vnn-user-row'
        ).forEach(function(row) {

            const matchesSearch =
                row.dataset.search.includes(term);

            const matchesRole =
                !role.value ||
                row.dataset.role === role.value;

            const matchesStatus =
                !status.value ||
                row.dataset.status === status.value;

            row.hidden = !(
                matchesSearch &&
                matchesRole &&
                matchesStatus
            );
        });
    }

    [search, role, status].forEach(function(el) {

        if (!el) {
            return;
        }

        el.addEventListener(
            'input',
            filterUsers
        );

        el.addEventListener(
            'change',
            filterUsers
        );
    });

    // ====================================
    // LOC LICH SU
    // ====================================

    const historySearch = document.getElementById(
        'vnnHistorySearch'
    );

    const historyDate = document.getElementById(
        'vnnHistoryDate'
    );

    function filterHistory() {

        if (!historySearch) {
            return;
        }

        const term = historySearch.value
            .trim()
            .toLocaleLowerCase('vi');

        let visible = 0;

        document.querySelectorAll(
            '.vnn-log-row'
        ).forEach(function(row) {

            const matches =
                row.dataset.search.includes(term) &&
                (
                    !historyDate.value ||
                    row.dataset.date === historyDate.value
                );

            row.hidden = !matches;

            if (matches) {
                visible++;
            }
        });

        const empty = document.getElementById(
            'vnnEmptyLog'
        );

        if (empty) {
            empty.hidden = visible > 0;
        }
    }

    [historySearch, historyDate].forEach(function(el) {

        if (!el) {
            return;
        }

        el.addEventListener(
            'input',
            filterHistory
        );

        el.addEventListener(
            'change',
            filterHistory
        );
    });

    filterHistory();

    // ====================================
    // POPUP XAC NHAN XOA
    // ====================================

    const confirmOverlay = document.getElementById(
        'vnnConfirmOverlay'
    );

    const deleteId = document.getElementById(
        'vnnDeleteId'
    );

    const deleteName = document.getElementById(
        'vnnDeleteName'
    );

    const deleteForm = document.getElementById(
        'vnnDeleteForm'
    );

    document.querySelectorAll(
        '.vnn-delete-user'
    ).forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                deleteId.value =
                    button.dataset.id;

                deleteName.textContent =
                    button.dataset.name;

                confirmOverlay.hidden = false;
            }
        );
    });

    function closeConfirm() {
        confirmOverlay.hidden = true;
    }

    document.getElementById(
        'vnnCancelDelete'
    ).addEventListener(
        'click',
        closeConfirm
    );

    document.getElementById(
        'vnnConfirmDelete'
    ).addEventListener(
        'click',
        function() {

            if (!deleteId.value) {
                return;
            }

            deleteForm.requestSubmit();
        }
    );

    // ====================================
    // POPUP KET QUA
    // ====================================

    const resultOverlay = document.getElementById(
        'vnnResultOverlay'
    );

    const closeResultButton = document.getElementById(
        'vnnCloseResult'
    );

    function closeResult() {
        resultOverlay.hidden = true;
    }

    closeResultButton.addEventListener(
        'click',
        closeResult
    );

    [confirmOverlay, resultOverlay].forEach(
        function(overlay) {

            overlay.addEventListener(
                'click',
                function(event) {

                    if (event.target === overlay) {
                        overlay.hidden = true;
                    }
                }
            );
        }
    );

    document.addEventListener(
        'keydown',
        function(event) {

            if (event.key === 'Escape') {
                closeConfirm();
                closeResult();
            }
        }
    );

})();
</script>

</body>
</html>
