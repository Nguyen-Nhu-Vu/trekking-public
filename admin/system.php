<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if (!function_exists('vnnSysH')) {
    function vnnSysH($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vnnSysQuery')) {
    function vnnSysQuery(PDO $pdo, string $sql, array $params = []): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('vnnSysAudit')) {
    function vnnSysAudit(
        PDO $pdo,
        string $action,
        string $type,
        string $id,
        string $old,
        string $new
    ): void {
        $actorId = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT fullname FROM users WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$actorId]);
        $actorName = (string)($stmt->fetchColumn() ?: 'Admin');

        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_logs
            (
                actor_id, actor_name, category, action,
                target_type, target_id, old_value, new_value
            )
            VALUES (?, ?, 'accounts', ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $actorId, $actorName, $action,
            $type, $id, $old, $new
        ]);
    }
}

$tab = (string)($_GET['tab'] ?? 'users');

if (!in_array($tab, ['users', 'logins', 'edits'], true)) {
    $tab = 'users';
}

$current_page = match ($tab) {
    'logins' => 'system-login-history',
    'edits' => 'system-edit-history',
    default => 'system-users'
};

$names = [
    'users' => 'Quản lý & Cấp quyền tài khoản',
    'logins' => 'Lịch sử đăng nhập',
    'edits' => 'Lịch sử chỉnh sửa'
];

$page_title = $names[$tab] . ' - VNN Travel';

if (empty($_SESSION['vnn_sys_csrf'])) {
    $_SESSION['vnn_sys_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['vnn_sys_csrf'];
$popup = null;

// ====================================
// XU LY TAI KHOAN
// ====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Phiên làm việc không hợp lệ.');
        }

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add') {
            $name = trim((string)($_POST['fullname'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'staff');

            if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
                throw new RuntimeException('Họ tên không hợp lệ.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Email không hợp lệ.');
            }

            if (strlen($password) < 8) {
                throw new RuntimeException('Mật khẩu phải có ít nhất 8 ký tự.');
            }

            if (!in_array($role, ['staff', 'customer'], true)) {
                throw new RuntimeException('Vai trò không hợp lệ.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id FROM users WHERE email = ? LIMIT 1
            ");
            $stmt->execute([$email]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException('Email này đã tồn tại.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO users
                (fullname, email, phone, password, role, status)
                VALUES (?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $name,
                $email,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $role
            ]);

            $id = (int)$pdo->lastInsertId();

            vnnSysAudit(
                $pdo,
                'Tạo tài khoản',
                'Tài khoản',
                (string)$id,
                '',
                $name . ' | ' . $email . ' | ' . $role
            );

            $pdo->commit();

            $popup = [
                'type' => 'success',
                'title' => 'Đã tạo tài khoản',
                'message' => 'Tài khoản mới đã được tạo thành công.'
            ];

        } elseif (in_array($action, ['toggle', 'delete'], true)) {
            $id = (int)($_POST['user_id'] ?? 0);

            if (
                $id <= 0 ||
                $id === (int)($_SESSION['user_id'] ?? 0)
            ) {
                throw new RuntimeException(
                    'Không thể thay đổi tài khoản hiện tại.'
                );
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, fullname, email, role, status
                FROM users
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$user) {
                throw new RuntimeException('Không tìm thấy tài khoản.');
            }

            if ($user['role'] === 'admin') {
                throw new RuntimeException(
                    'Không thể thay đổi tài khoản Admin.'
                );
            }

            if ($action === 'toggle') {
                $next = (int)$user['status'] === 1 ? 0 : 1;

                $stmt = $pdo->prepare("
                    UPDATE users SET status = ? WHERE id = ?
                ");
                $stmt->execute([$next, $id]);

                vnnSysAudit(
                    $pdo,
                    $next ? 'Mở khóa tài khoản' : 'Khóa tài khoản',
                    'Tài khoản',
                    (string)$id,
                    (string)$user['status'],
                    (string)$next
                );

                $popup = [
                    'type' => 'success',
                    'title' => 'Đã lưu thay đổi',
                    'message' => $next
                        ? 'Tài khoản đã được mở khóa.'
                        : 'Tài khoản đã được khóa.'
                ];

            } else {
                if ($user['role'] === 'manager') {
                    throw new RuntimeException(
                        'Không thể xóa tài khoản Manager.'
                    );
                }

                vnnSysAudit(
                    $pdo,
                    'Xóa tài khoản',
                    'Tài khoản',
                    (string)$id,
                    $user['fullname'] . ' | ' . $user['email'],
                    'Đã xóa'
                );

                $stmt = $pdo->prepare("
                    DELETE FROM users
                    WHERE id = ?
                      AND role IN ('staff', 'customer')
                ");
                $stmt->execute([$id]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Không thể xóa tài khoản.'
                    );
                }

                $popup = [
                    'type' => 'success',
                    'title' => 'Đã xóa tài khoản',
                    'message' => 'Tài khoản đã được xóa thành công.'
                ];
            }

            $pdo->commit();

        } else {
            throw new RuntimeException('Thao tác không hợp lệ.');
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('VNN Admin: ' . $e->getMessage());

        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể xử lý yêu cầu.';

        if (
            $e instanceof PDOException &&
            (string)$e->getCode() === '23000'
        ) {
            $message = 'Tài khoản còn dữ liệu liên quan. ' .
                       'Hãy khóa thay vì xóa để bảo toàn lịch sử.';
        }

        $popup = [
            'type' => 'error',
            'title' => 'Thao tác không thành công',
            'message' => $message
        ];
    }

    $tab = 'users';
    $current_page = 'system-users';
}

// ====================================
// DU LIEU
// ====================================

$users = [];
$loginRows = [];
$editRows = [];
$dataError = '';

try {
    if ($tab === 'users') {
        $users = vnnSysQuery($pdo, "
            SELECT id, fullname, email, phone, role, status
            FROM users
            ORDER BY
                FIELD(role, 'admin', 'manager', 'staff', 'customer'),
                id
        ");
    }

    if ($tab === 'logins') {
        $loginRows = vnnSysQuery($pdo, "
            SELECT
                id, user_id, account_email, account_name,
                account_role, login_time, ip_address,
                user_agent, result, note
            FROM login_audit_logs
            ORDER BY id DESC
            LIMIT 1000
        ");
    }

    if ($tab === 'edits') {
        $editRows = vnnSysQuery($pdo, "
            SELECT
                id, actor_id, actor_name, category,
                action, target_type, target_id,
                old_value, new_value, created_at
            FROM admin_audit_logs
            ORDER BY id DESC
            LIMIT 1000
        ");
    }
} catch (Throwable $e) {
    error_log('VNN Admin load: ' . $e->getMessage());
    $dataError = 'Không thể tải dữ liệu từ database.';
}

function vnnLoginDevice($ua): string {
    $ua = (string)$ua;
    $os = 'Không xác định';

    if (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false) {
        $os = 'iPhone';
    } elseif (stripos($ua, 'Mac OS') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    $browser = 'Khác';

    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    }

    return $os . ' / ' . $browser;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= vnnSysH($page_title) ?></title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">

<style>
/* ===================================
   GIAO DIEN CHUNG
   =================================== */

.vs-main {
    flex: 1 1 auto;
    min-width: 0;
    background: #f2f6fb;
}

.vs-head {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 25px 28px;
    background: white;
    border-bottom: 1px solid #e2e8f0;
}

.vs-head > i {
    color: #1877ee;
    font-size: 27px;
}

.vs-head h1 {
    margin: 0;
    color: #10203d;
    font-size: 23px;
    font-weight: 900;
}

.vs-head p {
    margin: 7px 0 0;
    color: #64748b;
    font-size: 13px;
}

.vs-content {
    padding: 24px;
    min-width: 0;
}

.vs-panel {
    padding: 23px;
    margin-bottom: 18px;
    border: 1px solid #e3eaf3;
    border-radius: 15px;
    background: white;
}

.vs-panel h2 {
    margin: 0 0 18px;
    color: #0f172a;
    font-size: 18px;
}

/* ===================================
   BO LOC - CAN THANG HANG
   =================================== */

.vs-toolbar {
    display: grid;
    grid-template-columns:
        minmax(145px, 1fr)
        minmax(145px, 1fr)
        minmax(180px, 1.5fr)
        minmax(180px, 1.5fr)
        90px;
    gap: 14px;
    align-items: end;
    margin-bottom: 20px;
    width: 100%;
}

/* Moi o loc duoc dat trong cung mot khoi */

.vs-filter-field {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    min-width: 0;
    gap: 8px;
}

/* Nhan loc co chieu cao dong nhat */

.vs-filter-field label {
    display: flex;
    align-items: center;
    height: 18px;
    min-height: 18px;
    margin: 0;
    color: #17345f;
    font-size: 12px;
    font-weight: 800;
    line-height: 18px;
}

/* Label an nhung van giu cho */

.vs-filter-field label.vs-label-placeholder {
    visibility: hidden;
}

/* Tat ca input / select chung chieu cao */

.vs-filter-field input,
.vs-filter-field select {
    width: 100%;
    height: 51px;
    min-width: 0;
    box-sizing: border-box;
    padding: 0 13px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: white;
    color: #334155;
    font: inherit;
    font-size: 13px;
    line-height: normal;
}

.vs-filter-field input:focus,
.vs-filter-field select:focus {
    outline: none;
    border-color: #1877ee;
    box-shadow: 0 0 0 3px rgba(24,119,238,.09);
}

.vs-filter-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    height: 51px;
    box-sizing: border-box;
    border: 0;
    border-radius: 10px;
    background: #1877ee;
    color: white;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.vs-filter-button:hover {
    background: #1266d4;
}

/* Bo loc tai khoan gom 4 cot */

.vs-toolbar.vs-toolbar-users {
    grid-template-columns:
        minmax(220px, 2fr)
        minmax(150px, 1fr)
        minmax(150px, 1fr)
        100px;
}

/* ===================================
   CAC THANH PHAN KHAC
   =================================== */

.vs-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 14px;
    border: 0;
    border-radius: 8px;
    background: #1877ee;
    color: white;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}

.vs-button.red {
    background: #dc2626;
}

.vs-button.green {
    background: #166534;
}

.vs-button.outline {
    border: 1px solid #ef4444;
    background: white;
    color: #dc2626;
}

.vs-button.soft {
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1877ee;
}

.vs-button:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
}

.vs-panel-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 17px;
}

.vs-table-scroll {
    width: 100%;
    overflow-x: auto;
}

.vs-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.vs-table th,
.vs-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #e8edf5;
    text-align: left;
    vertical-align: middle;
    font-size: 12px;
}

.vs-table th {
    background: #f7f9fc;
    color: #10203d;
    font-weight: 900;
    white-space: nowrap;
}

.vs-table tr:nth-child(even) td {
    background: #fbfcfe;
}

.vs-table tr[hidden] {
    display: none !important;
}

.vs-strong {
    color: #172b4d;
    font-weight: 800;
}

.vs-muted {
    color: #64748b;
    font-size: 12px;
    line-height: 1.7;
}

.vs-badge {
    display: inline-block;
    padding: 7px 11px;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.vs-badge.blue {
    background: #dbeafe;
    color: #1d4ed8;
}

.vs-badge.red {
    background: #fee2e2;
    color: #b91c1c;
}

.vs-action {
    display: flex;
    gap: 8px;
}

.vs-count {
    margin-top: 15px;
    color: #64748b;
    font-size: 12px;
}

.vs-pager {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 15px;
}

.vs-page {
    padding: 8px 12px;
    border: 1px solid #dbe4f0;
    border-radius: 7px;
    background: white;
    cursor: pointer;
}

.vs-page.active {
    background: #1877ee;
    color: white;
}

.vs-quickadd {
    padding: 18px;
    margin-bottom: 18px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.vs-quickadd[hidden] {
    display: none !important;
}

.vs-field-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.vs-field label {
    display: block;
    margin-bottom: 7px;
    font-size: 12px;
    font-weight: 800;
}

.vs-field input,
.vs-field select {
    width: 100%;
    box-sizing: border-box;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    font: inherit;
    font-size: 13px;
}

/* ===================================
   POPUP
   =================================== */

.vs-modal-layer {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .48);
    box-sizing: border-box;
}

.vs-modal-layer[hidden] {
    display: none !important;
}

.vs-modal {
    width: 100%;
    max-width: 430px;
    box-sizing: border-box;
    padding: 28px;
    border-radius: 17px;
    background: white;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
}

.vs-modal h2 {
    color: #0f172a;
    font-size: 20px;
}

.vs-modal p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

.vs-modal-icon {
    color: #16a34a;
    font-size: 43px;
}

.vs-modal-icon.red {
    color: #dc2626;
}

.vs-modal-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 18px;
}

.vs-detail-value {
    max-height: 180px;
    overflow-y: auto;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    padding: 12px;
    border-radius: 9px;
    background: #f8fafc;
    text-align: left;
    font-size: 12px;
}

/* ===================================
   RESPONSIVE
   =================================== */

@media (max-width: 1250px) {
    .vs-toolbar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .vs-toolbar .vs-filter-action {
        grid-column: 1 / -1;
    }

    .vs-toolbar-users {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 700px) {
    .vs-content {
        padding: 12px;
    }

    .vs-head {
        padding: 18px;
    }

    .vs-head h1 {
        font-size: 19px;
    }

    .vs-toolbar,
    .vs-toolbar-users {
        grid-template-columns: 1fr !important;
    }

    .vs-filter-action {
        grid-column: auto !important;
    }

    .vs-field-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="admin-body">

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main vs-main">

<header class="vs-head">
    <i class="fa-solid <?= $tab === 'users'
        ? 'fa-users-gear'
        : ($tab === 'logins'
            ? 'fa-clock-rotate-left'
            : 'fa-file-pen') ?>"></i>

    <div>
        <h1><?= vnnSysH($names[$tab]) ?></h1>

        <p>
            <?php if ($tab === 'users'): ?>
                Quản lý người dùng, phân quyền và kiểm soát truy cập hệ thống.
            <?php elseif ($tab === 'logins'): ?>
                Theo dõi thời gian, địa chỉ IP và thiết bị đăng nhập.
            <?php else: ?>
                Theo dõi các thay đổi nội dung được hệ thống ghi nhận.
            <?php endif; ?>
        </p>
    </div>
</header>

<div class="vs-content">

<?php if ($dataError): ?>
    <section class="vs-panel" style="color:#b91c1c">
        <?= vnnSysH($dataError) ?>
    </section>
<?php endif; ?>

<?php if ($tab === 'users'): ?>

    <!-- ===================================
         QUAN LY TAI KHOAN
         =================================== -->

    <section class="vs-panel">

        <div class="vs-panel-top">
            <h2 style="margin:0">Danh sách tài khoản</h2>

            <button
                type="button"
                class="vs-button"
                id="vsShowAdd"
            >
                <i class="fa-solid fa-plus"></i>
                Thêm tài khoản mới
            </button>
        </div>

        <div class="vs-quickadd" id="vsAddPanel" hidden>

            <h2>Thêm tài khoản</h2>

            <form method="POST">

                <input type="hidden" name="csrf"
                    value="<?= vnnSysH($csrf) ?>">

                <input type="hidden" name="action" value="add">

                <div class="vs-field-grid">

                    <div class="vs-field">
                        <label>Họ tên</label>
                        <input name="fullname" required maxlength="255">
                    </div>

                    <div class="vs-field">
                        <label>Email</label>
                        <input name="email" type="email" required>
                    </div>

                    <div class="vs-field">
                        <label>Số điện thoại</label>
                        <input name="phone">
                    </div>

                    <div class="vs-field">
                        <label>Mật khẩu khởi tạo</label>
                        <input name="password" type="password"
                            minlength="8" required>
                    </div>

                    <div class="vs-field">
                        <label>Vai trò</label>
                        <select name="role">
                            <option value="staff">Nhân viên</option>
                            <option value="customer">Khách hàng</option>
                        </select>
                    </div>

                </div>

                <div style="margin-top:17px">
                    <button class="vs-button green" type="submit">
                        Tạo tài khoản
                    </button>

                    <button class="vs-button soft" type="button"
                        id="vsCancelAdd">
                        Hủy
                    </button>
                </div>

            </form>
        </div>

        <div class="vs-toolbar vs-toolbar-users">

            <div class="vs-filter-field">
                <label for="vsSearch">Tìm kiếm</label>
                <input
                    id="vsSearch"
                    placeholder="Tìm tên hoặc email..."
                >
            </div>

            <div class="vs-filter-field">
                <label for="vsRoleFilter">Vai trò</label>
                <select id="vsRoleFilter">
                    <option value="">Tất cả vai trò</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="staff">Staff</option>
                    <option value="customer">Customer</option>
                </select>
            </div>

            <div class="vs-filter-field">
                <label for="vsStatusFilter">Trạng thái</label>
                <select id="vsStatusFilter">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1">Hoạt động</option>
                    <option value="0">Đang khóa</option>
                </select>
            </div>

            <div class="vs-filter-field vs-filter-action">
                <label class="vs-label-placeholder">Thao tác</label>
                <button type="button" id="vsReset"
                    class="vs-filter-button">
                    <i class="fa-solid fa-rotate"></i>
                    Làm mới
                </button>
            </div>

        </div>

        <div class="vs-table-scroll">
            <table class="vs-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Khóa / Mở khóa</th>
                        <th>Xóa</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($users as $index => $user): ?>

                    <?php
                    $protected = in_array(
                        $user['role'],
                        ['admin', 'manager'],
                        true
                    );

                    $roleColor = $user['role'] === 'admin'
                        ? 'red'
                        : ($user['role'] === 'manager' ? 'blue' : '');
                    ?>

                    <tr
                        class="vs-data-row"
                        data-search="<?= vnnSysH(
                            mb_strtolower(
                                $user['fullname'] . ' ' . $user['email'],
                                'UTF-8'
                            )
                        ) ?>"
                        data-role="<?= vnnSysH($user['role']) ?>"
                        data-status="<?= (int)$user['status'] ?>"
                    >

                        <td><?= $index + 1 ?></td>

                        <td class="vs-strong">
                            <?= vnnSysH($user['fullname']) ?>
                        </td>

                        <td><?= vnnSysH($user['email']) ?></td>

                        <td>
                            <span class="vs-badge <?= $roleColor ?>">
                                <?= vnnSysH(strtoupper($user['role'])) ?>
                            </span>
                        </td>

                        <td>
                            <span class="vs-badge <?= (int)$user['status'] === 1
                                ? ''
                                : 'red' ?>">
                                <?= (int)$user['status'] === 1
                                    ? 'Hoạt động'
                                    : 'Đang khóa' ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <button class="vs-button" disabled>
                                    Được bảo vệ
                                </button>
                            <?php else: ?>

                                <form method="POST">
                                    <input type="hidden" name="csrf"
                                        value="<?= vnnSysH($csrf) ?>">

                                    <input type="hidden" name="action"
                                        value="toggle">

                                    <input type="hidden" name="user_id"
                                        value="<?= (int)$user['id'] ?>">

                                    <button
                                        type="submit"
                                        class="vs-button <?= (int)$user['status'] === 1
                                            ? 'red'
                                            : 'green' ?>"
                                    >
                                        <?= (int)$user['status'] === 1
                                            ? 'Khóa'
                                            : 'Mở khóa' ?>
                                    </button>
                                </form>

                            <?php endif; ?>
                        </td>

                        <td>
                            <button
                                type="button"
                                class="vs-button outline vs-delete"
                                data-id="<?= (int)$user['id'] ?>"
                                data-name="<?= vnnSysH($user['fullname']) ?>"
                                <?= $protected ? 'disabled' : '' ?>
                            >
                                <i class="fa-solid fa-trash"></i>
                                Xóa
                            </button>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <div class="vs-count" id="vsCount"></div>
        <div class="vs-pager" id="vsPager"></div>

    </section>

<?php elseif ($tab === 'logins'): ?>

    <!-- ===================================
         LICH SU DANG NHAP
         =================================== -->

    <section class="vs-panel">

        <div class="vs-toolbar">

            <div class="vs-filter-field">
                <label for="vsFrom">Từ ngày</label>
                <input id="vsFrom" type="date">
            </div>

            <div class="vs-filter-field">
                <label for="vsTo">Đến ngày</label>
                <input id="vsTo" type="date">
            </div>

            <div class="vs-filter-field">
                <label for="vsAccountFilter">Tài khoản</label>
                <select id="vsAccountFilter">
                    <option value="">Tất cả tài khoản</option>

                    <?php
                    $accounts = [];

                    foreach ($loginRows as $entry) {
                        $accounts[$entry['account_email']] = true;
                    }

                    foreach (array_keys($accounts) as $account):
                    ?>
                        <option value="<?= vnnSysH($account) ?>">
                            <?= vnnSysH($account) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="vs-filter-field">
                <label for="vsResultFilter">Kết quả đăng nhập</label>
                <select id="vsResultFilter">
                    <option value="">Tất cả kết quả</option>
                    <option value="success">Thành công</option>
                    <option value="failed">Thất bại</option>
                    <option value="locked">Bị khóa</option>
                </select>
            </div>

            <div class="vs-filter-field vs-filter-action">
                <label class="vs-label-placeholder">Thao tác</label>
                <button
                    type="button"
                    id="vsReset"
                    class="vs-filter-button"
                >
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Lọc
                </button>
            </div>

        </div>

        <div class="vs-table-scroll">
            <table class="vs-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tài khoản</th>
                        <th>Vai trò</th>
                        <th>Thời gian</th>
                        <th>IP</th>
                        <th>Thiết bị / Trình duyệt</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($loginRows as $index => $row): ?>

                    <tr
                        class="vs-data-row"
                        data-date="<?= vnnSysH(substr($row['login_time'], 0, 10)) ?>"
                        data-email="<?= vnnSysH($row['account_email']) ?>"
                        data-result="<?= vnnSysH($row['result']) ?>"
                        data-search="<?= vnnSysH(
                            mb_strtolower(
                                $row['account_email'] . ' ' .
                                $row['account_name'] . ' ' .
                                $row['ip_address'],
                                'UTF-8'
                            )
                        ) ?>"
                    >

                        <td><?= $index + 1 ?></td>

                        <td>
                            <span class="vs-strong">
                                <?= vnnSysH(
                                    $row['account_name']
                                        ?: $row['account_email']
                                ) ?>
                            </span>

                            <div class="vs-muted">
                                <?= vnnSysH($row['account_email']) ?>
                            </div>
                        </td>

                        <td>
                            <?= vnnSysH(
                                strtoupper((string)$row['account_role'])
                            ) ?>
                        </td>

                        <td>
                            <?= vnnSysH(
                                date(
                                    'd/m/Y H:i:s',
                                    strtotime($row['login_time'])
                                )
                            ) ?>
                        </td>

                        <td><?= vnnSysH($row['ip_address']) ?></td>

                        <td title="<?= vnnSysH($row['user_agent']) ?>">
                            <?= vnnSysH(
                                vnnLoginDevice($row['user_agent'])
                            ) ?>
                        </td>

                        <td>
                            <span class="vs-badge <?= $row['result'] === 'success'
                                ? ''
                                : 'red' ?>">
                                <?= $row['result'] === 'success'
                                    ? 'Thành công'
                                    : ($row['result'] === 'locked'
                                        ? 'Bị khóa'
                                        : 'Thất bại') ?>
                            </span>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <div class="vs-count" id="vsCount"></div>
        <div class="vs-pager" id="vsPager"></div>

    </section>

<?php else: ?>

    <!-- ===================================
         LICH SU CHINH SUA
         =================================== -->

    <section class="vs-panel">

        <div class="vs-toolbar">

            <div class="vs-filter-field">
                <label for="vsFrom">Từ ngày</label>
                <input id="vsFrom" type="date">
            </div>

            <div class="vs-filter-field">
                <label for="vsTo">Đến ngày</label>
                <input id="vsTo" type="date">
            </div>

            <div class="vs-filter-field">
                <label for="vsTypeFilter">Loại nội dung</label>

                <select id="vsTypeFilter">
                    <option value="">Tất cả loại nội dung</option>
                    <option value="Tài khoản">Tài khoản</option>
                    <option value="Tour">Tour</option>
                    <option value="Sản phẩm">Sản phẩm</option>
                    <option value="Bài viết">Bài viết</option>
                    <option value="Đơn hàng">Đơn hàng</option>
                    <option value="Khác">Khác</option>
                </select>
            </div>

            <div class="vs-filter-field">
                <label for="vsSearch">Tìm kiếm</label>
                <input
                    id="vsSearch"
                    placeholder="Tìm người thực hiện, hành động..."
                >
            </div>

            <div class="vs-filter-field vs-filter-action">
                <label class="vs-label-placeholder">Thao tác</label>

                <button
                    type="button"
                    id="vsReset"
                    class="vs-filter-button"
                >
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Lọc
                </button>
            </div>

        </div>

        <div class="vs-table-scroll">

            <table class="vs-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Người thực hiện</th>
                        <th>Loại nội dung</th>
                        <th>Đối tượng</th>
                        <th>Hành động</th>
                        <th>Thời gian</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($editRows as $index => $row): ?>

                    <?php
                    $type = match ((string)$row['target_type']) {
                        'users', 'Tài khoản' => 'Tài khoản',
                        'tours', 'Tour' => 'Tour',
                        'products', 'Sản phẩm' => 'Sản phẩm',
                        'posts', 'Bài viết' => 'Bài viết',
                        'orders', 'Đơn hàng' => 'Đơn hàng',
                        default => 'Khác'
                    };
                    ?>

                    <tr
                        class="vs-data-row"
                        data-date="<?= vnnSysH(substr($row['created_at'], 0, 10)) ?>"
                        data-type="<?= vnnSysH($type) ?>"
                        data-search="<?= vnnSysH(
                            mb_strtolower(
                                $row['actor_name'] . ' ' .
                                $row['action'] . ' ' .
                                $row['target_type'] . ' ' .
                                $row['target_id'],
                                'UTF-8'
                            )
                        ) ?>"
                    >

                        <td><?= $index + 1 ?></td>

                        <td class="vs-strong">
                            <?= vnnSysH($row['actor_name']) ?>
                        </td>

                        <td><?= vnnSysH($type) ?></td>

                        <td>
                            <?= vnnSysH(
                                $row['target_type'] . ' #' .
                                $row['target_id']
                            ) ?>
                        </td>

                        <td>
                            <span class="vs-badge blue">
                                <?= vnnSysH($row['action']) ?>
                            </span>
                        </td>

                        <td>
                            <?= vnnSysH(
                                date(
                                    'd/m/Y H:i:s',
                                    strtotime($row['created_at'])
                                )
                            ) ?>
                        </td>

                        <td>
                            <button
                                type="button"
                                class="vs-button soft vs-detail"
                                data-action="<?= vnnSysH($row['action']) ?>"
                                data-before="<?= vnnSysH($row['old_value']) ?>"
                                data-after="<?= vnnSysH($row['new_value']) ?>"
                            >
                                <i class="fa-solid fa-eye"></i>
                                Xem
                            </button>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        </div>

        <div class="vs-count" id="vsCount"></div>
        <div class="vs-pager" id="vsPager"></div>

    </section>

<?php endif; ?>

</div>
</main>

<!-- FORM XOA TAI KHOAN -->

<form method="POST" id="vsDeleteForm" hidden>
    <input type="hidden" name="csrf"
        value="<?= vnnSysH($csrf) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="vsDeleteId">
</form>

<!-- POPUP XAC NHAN XOA -->

<div class="vs-modal-layer" id="vsConfirm" hidden>
    <div class="vs-modal">

        <div class="vs-modal-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2>Xác nhận xóa tài khoản</h2>

        <p>
            Bạn có chắc chắn muốn xóa
            <strong id="vsDeleteName"></strong>?
        </p>

        <p>Thao tác này không thể hoàn tác.</p>

        <div class="vs-modal-actions">

            <button type="button" class="vs-button soft"
                id="vsCancelDelete">
                Hủy
            </button>

            <button type="button" class="vs-button red"
                id="vsConfirmDelete">
                Xóa tài khoản
            </button>

        </div>
    </div>
</div>

<!-- POPUP KET QUA -->

<div class="vs-modal-layer" id="vsResult"
    <?= $popup ? '' : 'hidden' ?>>

    <div class="vs-modal">

        <div class="vs-modal-icon <?= ($popup['type'] ?? '') === 'error'
            ? 'red'
            : '' ?>">
            <i class="fa-solid <?= ($popup['type'] ?? '') === 'error'
                ? 'fa-circle-xmark'
                : 'fa-circle-check' ?>"></i>
        </div>

        <h2><?= vnnSysH($popup['title'] ?? 'Thông báo') ?></h2>

        <p><?= vnnSysH($popup['message'] ?? '') ?></p>

        <button type="button" class="vs-button green"
            id="vsCloseResult">
            Đóng
        </button>
    </div>
</div>

<!-- POPUP CHI TIET -->

<div class="vs-modal-layer" id="vsDetailModal" hidden>
    <div class="vs-modal" style="max-width:540px">

        <h2 id="vsDetailTitle">Chi tiết chỉnh sửa</h2>

        <p style="text-align:left;font-weight:800">
            Trước khi sửa
        </p>

        <div class="vs-detail-value" id="vsBefore"></div>

        <p style="text-align:left;font-weight:800">
            Sau khi sửa
        </p>

        <div class="vs-detail-value" id="vsAfter"></div>

        <div style="margin-top:18px">
            <button type="button" class="vs-button"
                id="vsCloseDetail">
                Đóng
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    const $ = id => document.getElementById(id);

    const rows = Array.from(
        document.querySelectorAll('.vs-data-row')
    );

    const pageSize = 10;
    let page = 1;

    function filteredRows() {
        const search = ($('vsSearch')?.value || '')
            .trim().toLocaleLowerCase('vi');

        const role = $('vsRoleFilter')?.value || '';
        const status = $('vsStatusFilter')?.value || '';
        const from = $('vsFrom')?.value || '';
        const to = $('vsTo')?.value || '';
        const email = $('vsAccountFilter')?.value || '';
        const result = $('vsResultFilter')?.value || '';
        const type = $('vsTypeFilter')?.value || '';

        return rows.filter(row => {
            const d = row.dataset;

            return (
                (!search || (d.search || '').includes(search)) &&
                (!role || d.role === role) &&
                (!status || d.status === status) &&
                (!from || (d.date || '') >= from) &&
                (!to || (d.date || '') <= to) &&
                (!email || d.email === email) &&
                (!result || d.result === result) &&
                (!type || d.type === type)
            );
        });
    }

    function render() {
        const found = filteredRows();
        const pages = Math.max(
            1,
            Math.ceil(found.length / pageSize)
        );

        page = Math.min(page, pages);

        rows.forEach(row => {
            row.hidden = true;
        });

        found
            .slice((page - 1) * pageSize, page * pageSize)
            .forEach(row => {
                row.hidden = false;
            });

        const count = $('vsCount');

        if (count) {
            const start = found.length
                ? (page - 1) * pageSize + 1
                : 0;

            const end = Math.min(
                page * pageSize,
                found.length
            );

            count.textContent =
                'Hiển thị ' + start + ' - ' + end +
                ' trong ' + found.length + ' bản ghi';
        }

        const pager = $('vsPager');

        if (!pager) return;

        pager.replaceChildren();

        for (let p = 1; p <= pages; p++) {
            if (
                pages > 9 &&
                Math.abs(p - page) > 2 &&
                p !== 1 &&
                p !== pages
            ) {
                continue;
            }

            const button = document.createElement('button');

            button.type = 'button';
            button.className =
                'vs-page' + (p === page ? ' active' : '');

            button.textContent = String(p);

            button.addEventListener('click', function() {
                page = p;
                render();
            });

            pager.appendChild(button);
        }
    }

    // BO LOC

    document.querySelectorAll(
        '#vsSearch,#vsRoleFilter,#vsStatusFilter,' +
        '#vsFrom,#vsTo,#vsAccountFilter,' +
        '#vsResultFilter,#vsTypeFilter'
    ).forEach(el => {
        el.addEventListener('input', function() {
            page = 1;
            render();
        });

        el.addEventListener('change', function() {
            page = 1;
            render();
        });
    });

    // LAM MOI BO LOC

    $('vsReset')?.addEventListener('click', function() {
        document.querySelectorAll(
            '#vsSearch,#vsRoleFilter,#vsStatusFilter,' +
            '#vsFrom,#vsTo,#vsAccountFilter,' +
            '#vsResultFilter,#vsTypeFilter'
        ).forEach(el => {
            el.value = '';
        });

        page = 1;
        render();
    });

    // THEM TAI KHOAN

    $('vsShowAdd')?.addEventListener('click', function() {
        $('vsAddPanel').hidden = false;
    });

    $('vsCancelAdd')?.addEventListener('click', function() {
        $('vsAddPanel').hidden = true;
    });

    // XOA TAI KHOAN

    document.querySelectorAll('.vs-delete').forEach(button => {
        button.addEventListener('click', function() {
            $('vsDeleteId').value = button.dataset.id;
            $('vsDeleteName').textContent = button.dataset.name;
            $('vsConfirm').hidden = false;
        });
    });

    $('vsCancelDelete')?.addEventListener('click', function() {
        $('vsConfirm').hidden = true;
    });

    $('vsConfirmDelete')?.addEventListener('click', function() {
        $('vsDeleteForm').requestSubmit();
    });

    // XEM CHI TIET LICH SU

    document.querySelectorAll('.vs-detail').forEach(button => {
        button.addEventListener('click', function() {
            $('vsDetailTitle').textContent =
                button.dataset.action;

            $('vsBefore').textContent =
                button.dataset.before || 'Không có dữ liệu';

            $('vsAfter').textContent =
                button.dataset.after || 'Không có dữ liệu';

            $('vsDetailModal').hidden = false;
        });
    });

    $('vsCloseDetail')?.addEventListener('click', function() {
        $('vsDetailModal').hidden = true;
    });

    $('vsCloseResult')?.addEventListener('click', function() {
        $('vsResult').hidden = true;
    });

    // DONG POPUP

    ['vsConfirm', 'vsResult', 'vsDetailModal'].forEach(id => {
        const overlay = $(id);

        if (!overlay) return;

        overlay.addEventListener('click', function(event) {
            if (event.target === overlay) {
                overlay.hidden = true;
            }
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            ['vsConfirm', 'vsResult', 'vsDetailModal'].forEach(id => {
                const overlay = $(id);

                if (overlay) {
                    overlay.hidden = true;
                }
            });
        }
    });

    render();
})();
</script>

</body>
</html>
