<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

function sysH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sysRows(PDO $pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sysCount(PDO $pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function sysLog(
    PDO $pdo,
    $category,
    $action,
    $targetType,
    $targetId,
    $oldValue,
    $newValue
) {
    $stmt = $pdo->prepare("
        INSERT INTO admin_audit_logs
        (
            actor_id, actor_name, category,
            action, target_type, target_id,
            old_value, new_value
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $actorId = (int)($_SESSION['user_id'] ?? 0);

    $actor = $pdo->prepare(
        "SELECT fullname FROM users WHERE id = ?"
    );
    $actor->execute([$actorId]);

    $actorName = $actor->fetchColumn() ?: 'Admin';

    $stmt->execute([
        $actorId,
        $actorName,
        $category,
        $action,
        $targetType,
        (string)$targetId,
        $oldValue,
        $newValue
    ]);
}

$allowedTabs = ['overview', 'users', 'content', 'history'];
$tab = (string)($_GET['tab'] ?? 'overview');

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

$current_page = 'system-' . $tab;

$tabTitles = [
    'overview' => 'Tổng Quan Hệ Thống',
    'users' => 'Quản Lý Tài Khoản',
    'content' => 'Nội Dung Website',
    'history' => 'Lịch Sử Hoạt Động'
];

$page_title = $tabTitles[$tab] . ' - VNN Travel';

$root = rtrim(BASE_URL, '/') . '/admin/';
$selfUrl = $root . 'system.php';

if (empty($_SESSION['system_csrf'])) {
    $_SESSION['system_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['system_csrf'];

$error = '';
$popup = $_SESSION['system_popup'] ?? null;
unset($_SESSION['system_popup']);

// ======================================
// CAC TRUONG CAU HINH NOI DUNG
// ======================================

$settingLabels = [
    'home_heading' => 'Tiêu đề lớn trang chủ',
    'home_description' => 'Lời giới thiệu trang chủ',
    'home_banner' => 'URL ảnh banner trang chủ',
    'site_logo' => 'URL logo website',
    'footer_description' => 'Giới thiệu ở chân trang'
];

// ======================================
// XU LY TAO TAI KHOAN, KHOA/MO,
// LUU CAU HINH NOI DUNG
// ======================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            $csrf,
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ.'
            );
        }

        $action = (string)($_POST['action'] ?? '');
        $nextTab = 'users';

        if ($action === 'add_user') {
            $fullname = trim((string)($_POST['fullname'] ?? ''));
            $email = strtolower(trim(
                (string)($_POST['email'] ?? '')
            ));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'staff');

            if (
                $fullname === '' ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new RuntimeException(
                    'Họ tên hoặc email không hợp lệ.'
                );
            }

            if (strlen($password) < 8) {
                throw new RuntimeException(
                    'Mật khẩu phải có ít nhất 8 ký tự.'
                );
            }

            if (!in_array(
                $role,
                ['staff', 'customer', 'manager', 'admin'],
                true
            )) {
                throw new RuntimeException('Vai trò không hợp lệ.');
            }

            $pdo->beginTransaction();

            if (in_array($role, ['admin', 'manager'], true)) {
                $lock = $pdo->query("
                    SELECT id
                    FROM users
                    WHERE role IN ('admin', 'manager')
                    FOR UPDATE
                ");

                $lock->fetchAll();

                $existing = sysCount(
                    $pdo,
                    "SELECT COUNT(*) FROM users WHERE role = ?",
                    [$role]
                );

                if ($existing > 0) {
                    throw new RuntimeException(
                        'Hệ thống chỉ cho phép một tài khoản ' .
                        strtoupper($role) . '.'
                    );
                }
            }

            if (sysCount(
                $pdo,
                "SELECT COUNT(*) FROM users WHERE email = ?",
                [$email]
            ) > 0) {
                throw new RuntimeException(
                    'Email này đã được đăng ký.'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO users
                    (fullname, email, password, phone, role, status)
                VALUES (?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $fullname,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $phone,
                $role
            ]);

            $newId = (int)$pdo->lastInsertId();

            sysLog(
                $pdo,
                'accounts',
                'Tạo tài khoản',
                'users',
                $newId,
                '',
                $fullname . ' - ' . $role
            );

            $pdo->commit();

        } elseif ($action === 'toggle_user') {
            $id = (int)($_POST['user_id'] ?? 0);

            if ($id <= 0 || $id === (int)$_SESSION['user_id']) {
                throw new RuntimeException(
                    'Không thể thay đổi tài khoản này.'
                );
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, fullname, role, status
                FROM users
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException(
                    'Không tìm thấy tài khoản.'
                );
            }

            if ($user['role'] === 'admin') {
                throw new RuntimeException(
                    'Không được khóa tài khoản Admin.'
                );
            }

            $newStatus = (int)$user['status'] === 1 ? 0 : 1;

            $stmt = $pdo->prepare(
                "UPDATE users SET status = ? WHERE id = ?"
            );

            $stmt->execute([$newStatus, $id]);

            sysLog(
                $pdo,
                'accounts',
                $newStatus ? 'Mở khóa tài khoản' : 'Khóa tài khoản',
                'users',
                $id,
                (string)$user['status'],
                (string)$newStatus
            );

            $pdo->commit();

        } elseif ($action === 'save_content') {
            $nextTab = 'content';

            $pdo->beginTransaction();

            foreach ($settingLabels as $key => $label) {
                $value = trim((string)($_POST[$key] ?? ''));

                if (mb_strlen($value, 'UTF-8') > 10000) {
                    throw new RuntimeException(
                        'Nội dung quá dài: ' . $label
                    );
                }

                if (
                    in_array($key, ['home_banner', 'site_logo'], true)
                    && $value !== ''
                    && (
                        !filter_var($value, FILTER_VALIDATE_URL)
                        || !in_array(
                            parse_url($value, PHP_URL_SCHEME),
                            ['https'],
                            true
                        )
                    )
                ) {
                    throw new RuntimeException(
                        'Ảnh phải dùng URL HTTPS hợp lệ.'
                    );
                }

                $stmt = $pdo->prepare("
                    SELECT setting_value
                    FROM site_settings
                    WHERE setting_key = ?
                    FOR UPDATE
                ");
                $stmt->execute([$key]);

                $old = $stmt->fetchColumn();
                $oldValue = $old === false ? '' : (string)$old;

                if ($oldValue === $value) {
                    continue;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO site_settings
                        (setting_key, setting_value, updated_by)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        updated_by = VALUES(updated_by)
                ");

                $stmt->execute([
                    $key,
                    $value,
                    (int)$_SESSION['user_id']
                ]);

                sysLog(
                    $pdo,
                    'content',
                    'Chỉnh sửa: ' . $label,
                    'site_settings',
                    $key,
                    $oldValue,
                    $value
                );
            }

            $pdo->commit();

        } else {
            throw new RuntimeException('Thao tác không hợp lệ.');
        }

        $_SESSION['system_popup'] = [
            'type' => 'success',
            'title' => 'Đã lưu thay đổi',
            'message' => 'Thông tin đã được cập nhật thành công.'
        ];

        header(
            'Location: system.php?tab=' . $nextTab,
            true,
            303
        );
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('System admin: ' . $e->getMessage());

        $popup = [
            'type' => 'error',
            'title' => 'Không thể lưu thay đổi',
            'message' => $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Có lỗi xảy ra. Vui lòng thử lại.'
        ];
    }
}

// ======================================
// THONG KE
// ======================================

$totalUsers = sysCount($pdo, "SELECT COUNT(*) FROM users");
$staffCount = sysCount(
    $pdo,
    "SELECT COUNT(*) FROM users WHERE role = 'staff'"
);
$customerCount = sysCount(
    $pdo,
    "SELECT COUNT(*) FROM users WHERE role = 'customer'"
);
$lockedCount = sysCount(
    $pdo,
    "SELECT COUNT(*) FROM users WHERE status = 0"
);
$activeCount = sysCount(
    $pdo,
    "SELECT COUNT(*) FROM users WHERE status = 1"
);

$adminCount = sysCount(
    $pdo,
    "SELECT COUNT(*) FROM users WHERE role = 'admin'"
);
$managerCount = sysCount(
    $pdo,
    "SELECT COUNT(*) FROM users WHERE role = 'manager'"
);

$users = sysRows($pdo, "
    SELECT id, fullname, email, phone, role, status
    FROM users
    ORDER BY FIELD(role, 'admin', 'manager', 'staff', 'customer'), id
");

$settingRows = sysRows(
    $pdo,
    "SELECT setting_key, setting_value FROM site_settings"
);

$settings = array_fill_keys(array_keys($settingLabels), '');

foreach ($settingRows as $row) {
    if (array_key_exists($row['setting_key'], $settings)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$logs = sysRows($pdo, "
    SELECT *
    FROM admin_audit_logs
    ORDER BY id DESC
    LIMIT 300
");

$historyGroups = [
    'accounts' => 'Tài khoản',
    'content' => 'Nội dung',
    'transactions' => 'Giao dịch',
    'security' => 'Bảo mật'
];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= sysH($page_title) ?></title>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">

<style>
.sys-main {
    flex: 1;
    min-width: 0;
    background: #f1f5f9;
}

.sys-content {
    padding: 24px;
    min-width: 0;
}

.sys-title {
    color: #0f172a;
    font-size: 22px;
    font-weight: 900;
}

.sys-muted {
    color: #64748b;
    line-height: 1.8;
    font-size: 13px;
}

.sys-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 17px;
    margin-bottom: 23px;
}

.sys-card,
.sys-panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 23px;
    min-width: 0;
}

.sys-card .number {
    color: #166534;
    font-size: 29px;
    font-weight: 900;
    margin-top: 10px;
}

.sys-card .name {
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
}

.sys-panel {
    margin-bottom: 22px;
}

.sys-panel h2 {
    color: #0f172a;
    font-size: 19px;
    margin: 0 0 18px;
}

.sys-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 17px;
}

.sys-field label {
    display: block;
    font-size: 13px;
    color: #334155;
    font-weight: 800;
    margin-bottom: 8px;
}

.sys-field input,
.sys-field select,
.sys-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: #fff;
    font: inherit;
    font-size: 13px;
}

.sys-field textarea {
    min-height: 110px;
    resize: vertical;
}

.sys-full {
    grid-column: 1 / -1;
}

.sys-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 9px;
    padding: 11px 17px;
    background: #166534;
    color: white;
    text-decoration: none;
    font-weight: 800;
    font-size: 13px;
    cursor: pointer;
}

.sys-btn:hover {
    background: #14532d;
    color: white;
}

.sys-btn.red {
    background: #dc2626;
}

.sys-btn.gray {
    background: #475569;
}

.sys-btn.small {
    padding: 8px 11px;
    font-size: 12px;
}

.sys-table-wrap {
    overflow-x: auto;
}

.sys-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.sys-table th,
.sys-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
}

.sys-table th {
    background: #f8fafc;
    color: #0f172a;
    font-weight: 800;
}

.sys-pill {
    display: inline-block;
    border-radius: 18px;
    padding: 6px 10px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 800;
}

.sys-pill.locked {
    background: #fee2e2;
    color: #991b1b;
}

.sys-tools {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.sys-tools input,
.sys-tools select {
    min-width: 160px;
    flex: 1;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font: inherit;
    font-size: 13px;
}

.sys-tabs {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 10px;
    margin-bottom: 23px;
}

.sys-tab {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 8px;
    background: #f8fafc;
    font-weight: 800;
    font-size: 13px;
    cursor: pointer;
}

.sys-tab.active {
    background: #166534;
    color: white;
}

.sys-alert {
    padding: 15px;
    margin-bottom: 18px;
    border-radius: 10px;
    background: #fff7ed;
    color: #9a3412;
    line-height: 1.8;
    font-size: 13px;
}

.sys-history-value {
    max-width: 260px;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    font-size: 12px;
    color: #64748b;
}

.sys-modal {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15,23,42,.5);
}

.sys-modal[hidden] {
    display: none !important;
}

.sys-modal-card {
    width: 100%;
    max-width: 370px;
    box-sizing: border-box;
    border-radius: 18px;
    padding: 30px;
    background: white;
    text-align: center;
}

.sys-modal-card h2 {
    font-size: 22px;
    margin: 10px 0;
}

.sys-modal-card p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.8;
}

.sys-modal-icon {
    font-size: 38px;
    color: #166534;
}

@media(max-width:1050px) {
    .sys-stats {
        grid-template-columns: repeat(2,1fr);
    }
}

@media(max-width:750px) {
    .sys-content {
        padding: 12px;
    }

    .sys-grid {
        grid-template-columns: 1fr;
    }

    .sys-tabs {
        grid-template-columns: repeat(2,1fr);
    }
}
</style>
</head>

<body class="admin-body">

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main sys-main">

    <div class="admin-topbar">
        <strong>
            <i class="fa-solid fa-shield-halved"></i>
            <?= sysH(mb_strtoupper($tabTitles[$tab], 'UTF-8')) ?>
        </strong>
    </div>

    <div class="sys-content">

        <?php if ($tab === 'overview'): ?>

            <div class="sys-stats">

                <?php foreach ([
                    ['Tổng tài khoản', $totalUsers],
                    ['Nhân viên', $staffCount],
                    ['Khách hàng', $customerCount],
                    ['Tài khoản bị khóa', $lockedCount]
                ] as $stat): ?>

                    <div class="sys-card">
                        <div class="name"><?= sysH($stat[0]) ?></div>
                        <div class="number"><?= (int)$stat[1] ?></div>
                    </div>

                <?php endforeach; ?>

            </div>

            <section class="sys-panel">
                <h2>Tổng quan quản trị</h2>

                <p class="sys-muted">
                    Hệ thống hiện có
                    <strong><?= $adminCount ?></strong> Admin,
                    <strong><?= $managerCount ?></strong> Manager,
                    <strong><?= $staffCount ?></strong> Staff và
                    <strong><?= $customerCount ?></strong> Customer.
                </p>

                <p class="sys-muted">
                    Có <?= $activeCount ?> tài khoản hoạt động
                    và <?= $lockedCount ?> tài khoản đang khóa.
                </p>
            </section>

            <div class="sys-grid">

                <section class="sys-panel">
                    <h2>Quản lý tài khoản</h2>
                    <p class="sys-muted">
                        Tạo tài khoản mới, tìm kiếm, lọc và khóa/mở khóa.
                    </p>
                    <a class="sys-btn" href="?tab=users">
                        Mở quản lý tài khoản
                    </a>
                </section>

                <section class="sys-panel">
                    <h2>Lịch sử hoạt động</h2>
                    <p class="sys-muted">
                        Xem các thay đổi được ghi nhận trong nhật ký Admin.
                    </p>
                    <a class="sys-btn" href="?tab=history">
                        Xem lịch sử
                    </a>
                </section>

            </div>

        <?php elseif ($tab === 'users'): ?>

            <section class="sys-panel">
                <h2>Thống kê tài khoản</h2>

                <div class="sys-stats">
                    <?php foreach ([
                        ['Tổng tài khoản', $totalUsers],
                        ['Nhân viên', $staffCount],
                        ['Khách hàng', $customerCount],
                        ['Đang khóa', $lockedCount]
                    ] as $stat): ?>

                        <div class="sys-card">
                            <div class="name"><?= sysH($stat[0]) ?></div>
                            <div class="number"><?= (int)$stat[1] ?></div>
                        </div>

                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sys-panel">

                <h2>Thêm tài khoản mới</h2>

                <form method="POST">
                    <input type="hidden" name="csrf"
                           value="<?= sysH($csrf) ?>">
                    <input type="hidden" name="action"
                           value="add_user">

                    <div class="sys-grid">

                        <div class="sys-field">
                            <label>Họ và tên</label>
                            <input name="fullname" required>
                        </div>

                        <div class="sys-field">
                            <label>Email</label>
                            <input name="email" type="email" required>
                        </div>

                        <div class="sys-field">
                            <label>Số điện thoại</label>
                            <input name="phone">
                        </div>

                        <div class="sys-field">
                            <label>Mật khẩu khởi tạo</label>
                            <input name="password"
                                   type="password"
                                   minlength="8"
                                   autocomplete="new-password"
                                   required>
                        </div>

                        <div class="sys-field">
                            <label>Vai trò</label>
                            <select name="role">
                                <option value="staff">Nhân viên</option>
                                <option value="customer">Khách hàng</option>

                                <?php if ($managerCount === 0): ?>
                                    <option value="manager">Manager</option>
                                <?php endif; ?>

                                <?php if ($adminCount === 0): ?>
                                    <option value="admin">Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>

                    </div>

                    <button class="sys-btn" type="submit"
                            style="margin-top:18px">
                        <i class="fa-solid fa-user-plus"></i>
                        Tạo tài khoản
                    </button>
                </form>
            </section>

            <section class="sys-panel">
                <h2>Danh sách tài khoản</h2>

                <div class="sys-tools">
                    <input id="sysUserSearch"
                           placeholder="Tìm tên hoặc email...">

                    <select id="sysRoleFilter">
                        <option value="">Tất cả vai trò</option>
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="staff">Staff</option>
                        <option value="customer">Customer</option>
                    </select>

                    <select id="sysStatusFilter">
                        <option value="">Tất cả trạng thái</option>
                        <option value="1">Hoạt động</option>
                        <option value="0">Đang khóa</option>
                    </select>
                </div>

                <div class="sys-table-wrap">
                    <table class="sys-table">
                        <thead>
                            <tr>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($users as $user): ?>

                                <tr class="sys-user-row"
                                    data-search="<?= sysH(
                                        mb_strtolower(
                                            $user['fullname'] . ' ' . $user['email'],
                                            'UTF-8'
                                        )
                                    ) ?>"
                                    data-role="<?= sysH($user['role']) ?>"
                                    data-status="<?= (int)$user['status'] ?>">

                                    <td>
                                        <strong>
                                            <?= sysH($user['fullname']) ?>
                                        </strong>
                                    </td>

                                    <td><?= sysH($user['email']) ?></td>
                                    <td><?= sysH(strtoupper($user['role'])) ?></td>

                                    <td>
                                        <span class="sys-pill <?= (int)$user['status'] === 1 ? '' : 'locked' ?>">
                                            <?= (int)$user['status'] === 1
                                                ? 'Hoạt động'
                                                : 'Đang khóa' ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (
                                            $user['role'] !== 'admin' &&
                                            (int)$user['id'] !== (int)$_SESSION['user_id']
                                        ): ?>

                                            <form method="POST"
                                                  onsubmit="return confirm('Xác nhận thay đổi trạng thái tài khoản?')">

                                                <input type="hidden" name="csrf"
                                                       value="<?= sysH($csrf) ?>">
                                                <input type="hidden" name="action"
                                                       value="toggle_user">
                                                <input type="hidden" name="user_id"
                                                       value="<?= (int)$user['id'] ?>">

                                                <button class="sys-btn small <?= (int)$user['status'] === 1 ? 'red' : '' ?>"
                                                        type="submit">
                                                    <?= (int)$user['status'] === 1
                                                        ? 'Khóa'
                                                        : 'Mở khóa' ?>
                                                </button>
                                            </form>

                                        <?php else: ?>
                                            <span class="sys-muted">Được bảo vệ</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($tab === 'content'): ?>

            <div class="sys-alert">
                Đây là khu vực <strong>lưu cấu hình nội dung website</strong>.
                Các trường đã được lưu vào database và ghi lịch sử.
                Để banner, logo hoặc phần giới thiệu thực sự thay đổi
                trên trang chủ, cần cập nhật mã giao diện trang chủ
                và header để đọc từ bảng <code>site_settings</code>.
            </div>

            <section class="sys-panel">
                <h2>Chỉnh sửa nội dung website</h2>

                <form method="POST">

                    <input type="hidden" name="csrf"
                           value="<?= sysH($csrf) ?>">
                    <input type="hidden" name="action"
                           value="save_content">

                    <div class="sys-grid">

                        <?php foreach ($settingLabels as $key => $label): ?>

                            <div class="sys-field sys-full">
                                <label for="setting-<?= sysH($key) ?>">
                                    <?= sysH($label) ?>
                                </label>

                                <?php if (in_array(
                                    $key,
                                    ['home_description', 'footer_description'],
                                    true
                                )): ?>

                                    <textarea
                                        id="setting-<?= sysH($key) ?>"
                                        name="<?= sysH($key) ?>"
                                    ><?= sysH($settings[$key]) ?></textarea>

                                <?php else: ?>

                                    <input
                                        id="setting-<?= sysH($key) ?>"
                                        name="<?= sysH($key) ?>"
                                        value="<?= sysH($settings[$key]) ?>"
                                    >

                                <?php endif; ?>
                            </div>

                        <?php endforeach; ?>

                    </div>

                    <button class="sys-btn"
                            type="submit"
                            style="margin-top:20px">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Lưu thay đổi
                    </button>

                </form>
            </section>

        <?php else: ?>

            <section class="sys-panel">

                <h2>Lịch sử hoạt động</h2>

                <p class="sys-muted">
                    Chọn một trong bốn loại lịch sử để kiểm tra.
                </p>

                <div class="sys-tabs">

                    <?php $n = 1; ?>

                    <?php foreach ($historyGroups as $key => $label): ?>

                        <button
                            type="button"
                            class="sys-tab"
                            data-history-tab="<?= sysH($key) ?>"
                        >
                            <?= $n++ ?>. <?= sysH($label) ?>
                        </button>

                    <?php endforeach; ?>

                </div>

                <div class="sys-tools">
                    <input
                        id="sysHistorySearch"
                        placeholder="Tìm người thực hiện, thao tác..."
                    >

                    <input
                        type="date"
                        id="sysHistoryDate"
                        aria-label="Lọc theo ngày"
                    >
                </div>

                <div class="sys-table-wrap">
                    <table class="sys-table">
                        <thead>
                            <tr>
                                <th>Thời gian</th>
                                <th>Người thực hiện</th>
                                <th>Thao tác</th>
                                <th>Đối tượng</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($logs as $log): ?>

                                <tr
                                    class="sys-history-row"
                                    data-category="<?= sysH($log['category']) ?>"
                                    data-date="<?= sysH(substr($log['created_at'], 0, 10)) ?>"
                                    data-search="<?= sysH(
                                        mb_strtolower(
                                            $log['actor_name'] . ' ' .
                                            $log['action'] . ' ' .
                                            $log['target_type'],
                                            'UTF-8'
                                        )
                                    ) ?>"
                                >
                                    <td><?= sysH($log['created_at']) ?></td>
                                    <td><?= sysH($log['actor_name']) ?></td>
                                    <td><strong><?= sysH($log['action']) ?></strong></td>

                                    <td>
                                        <?= sysH($log['target_type']) ?>
                                        #<?= sysH($log['target_id']) ?>
                                    </td>

                                    <td>
                                        <details>
                                            <summary>Xem chi tiết</summary>
                                            <div class="sys-history-value">
                                                <strong>Trước:</strong>
                                                <?= sysH($log['old_value']) ?>

                                                <strong>Sau:</strong>
                                                <?= sysH($log['new_value']) ?>
                                            </div>
                                        </details>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p id="sysHistoryEmpty"
                   class="sys-muted"
                   hidden>
                    Chưa có nhật ký phù hợp trong nhóm này.
                </p>

            </section>

        <?php endif; ?>

    </div>
</main>

<!-- POPUP -->

<div
    class="sys-modal"
    id="sysModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="sysModalTitle"
    <?= $popup ? '' : 'hidden' ?>
>
    <div class="sys-modal-card">
        <div class="sys-modal-icon">
            <i class="fa-solid <?= ($popup['type'] ?? '') === 'error'
                ? 'fa-circle-xmark'
                : 'fa-circle-check' ?>"></i>
        </div>

        <h2 id="sysModalTitle">
            <?= sysH($popup['title'] ?? 'Thông báo') ?>
        </h2>

        <p><?= sysH($popup['message'] ?? '') ?></p>

        <button
            id="sysModalClose"
            class="sys-btn"
            type="button"
        >
            Đóng
        </button>
    </div>
</div>

<script>
(function() {
    const search = document.getElementById('sysUserSearch');
    const role = document.getElementById('sysRoleFilter');
    const status = document.getElementById('sysStatusFilter');

    function filterUsers() {
        if (!search) return;

        const query = search.value.trim().toLocaleLowerCase('vi');

        document.querySelectorAll('.sys-user-row').forEach(row => {
            row.hidden = !(
                row.dataset.search.includes(query) &&
                (!role.value || row.dataset.role === role.value) &&
                (!status.value || row.dataset.status === status.value)
            );
        });
    }

    [search, role, status].forEach(el => {
        if (el) {
            el.addEventListener('input', filterUsers);
            el.addEventListener('change', filterUsers);
        }
    });

    const historyTabs = document.querySelectorAll(
        '[data-history-tab]'
    );

    const historySearch = document.getElementById(
        'sysHistorySearch'
    );

    const historyDate = document.getElementById(
        'sysHistoryDate'
    );

    let activeHistory = 'accounts';

    function filterHistory() {
        if (!historySearch) return;

        const keyword = historySearch.value
            .trim()
            .toLocaleLowerCase('vi');

        const day = historyDate.value;
        let shown = 0;

        historyTabs.forEach(button => {
            button.classList.toggle(
                'active',
                button.dataset.historyTab === activeHistory
            );
        });

        document.querySelectorAll('.sys-history-row').forEach(row => {
            const visible =
                row.dataset.category === activeHistory &&
                row.dataset.search.includes(keyword) &&
                (!day || row.dataset.date === day);

            row.hidden = !visible;

            if (visible) shown++;
        });

        const empty = document.getElementById('sysHistoryEmpty');
        if (empty) empty.hidden = shown > 0;
    }

    historyTabs.forEach(button => {
        button.addEventListener('click', function() {
            activeHistory = button.dataset.historyTab;
            filterHistory();
        });
    });

    if (historySearch) {
        historySearch.addEventListener('input', filterHistory);
        historyDate.addEventListener('change', filterHistory);
        filterHistory();
    }

    const modal = document.getElementById('sysModal');
    const close = document.getElementById('sysModalClose');

    function closeModal() {
        modal.hidden = true;
    }

    if (close) {
        close.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', event => {
            if (event.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal) {
            closeModal();
        }
    });
})();
</script>

</body>
</html>
