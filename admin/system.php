<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if (!function_exists('vnnSysH')) {
    function vnnSysH($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vnnSysQuery')) {
    function vnnSysQuery(PDO $pdo, string $sql, array $params = []): array {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
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
        $st = $pdo->prepare("SELECT fullname FROM users WHERE id=?");
        $st->execute([$actorId]);
        $actorName = (string)($st->fetchColumn() ?: 'Admin');

        $st = $pdo->prepare("
            INSERT INTO admin_audit_logs
            (actor_id,actor_name,category,action,target_type,target_id,old_value,new_value)
            VALUES (?,?,'accounts',?,?,?,?,?)
        ");
        $st->execute([
            $actorId, $actorName, $action, $type, $id, $old, $new
        ]);
    }
}

$tab = (string)($_GET['tab'] ?? 'users');
if (!in_array($tab, ['users','logins','edits'], true)) {
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
// THEM / KHOA / MO KHOA / XOA
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

            if ($name === '' || mb_strlen($name) > 255) {
                throw new RuntimeException('Họ tên không hợp lệ.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Email không hợp lệ.');
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('Mật khẩu cần ít nhất 8 ký tự.');
            }
            if (!in_array($role, ['staff','customer'], true)) {
                throw new RuntimeException('Vai trò không hợp lệ.');
            }

            $pdo->beginTransaction();

            $st = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $st->execute([$email]);
            if ($st->fetchColumn()) {
                throw new RuntimeException('Email đã tồn tại.');
            }

            $st = $pdo->prepare("
                INSERT INTO users (fullname,email,phone,password,role,status)
                VALUES (?,?,?,?,?,1)
            ");
            $st->execute([
                $name, $email, $phone,
                password_hash($password, PASSWORD_DEFAULT), $role
            ]);

            $id = (int)$pdo->lastInsertId();

            vnnSysAudit(
                $pdo, 'Tạo tài khoản', 'Tài khoản',
                (string)$id, '', $name.' | '.$email.' | '.$role
            );

            $pdo->commit();

            $popup = ['type'=>'success','title'=>'Đã tạo tài khoản',
                'message'=>'Tài khoản mới đã được tạo thành công.'];

        } elseif (in_array($action, ['toggle','delete'], true)) {
            $id = (int)($_POST['user_id'] ?? 0);

            if ($id <= 0 || $id === (int)($_SESSION['user_id'] ?? 0)) {
                throw new RuntimeException('Không thể thay đổi tài khoản này.');
            }

            $pdo->beginTransaction();

            $st = $pdo->prepare("
                SELECT id,fullname,email,role,status
                FROM users WHERE id=? FOR UPDATE
            ");
            $st->execute([$id]);
            $user = $st->fetch(PDO::FETCH_ASSOC);
            $st->closeCursor();

            if (!$user) {
                throw new RuntimeException('Không tìm thấy tài khoản.');
            }
            if ($user['role'] === 'admin') {
                throw new RuntimeException('Tài khoản Admin được bảo vệ.');
            }

            if ($action === 'toggle') {
                $next = (int)$user['status'] === 1 ? 0 : 1;

                $st = $pdo->prepare("UPDATE users SET status=? WHERE id=?");
                $st->execute([$next, $id]);

                vnnSysAudit(
                    $pdo,
                    $next ? 'Mở khóa tài khoản' : 'Khóa tài khoản',
                    'Tài khoản',
                    (string)$id,
                    (string)$user['status'],
                    (string)$next
                );

                $popup = [
                    'type'=>'success',
                    'title'=>'Đã lưu thay đổi',
                    'message'=>$next ? 'Tài khoản đã được mở khóa.' :
                        'Tài khoản đã được khóa.'
                ];
            } else {
                if ($user['role'] === 'manager') {
                    throw new RuntimeException(
                        'Tài khoản Manager được bảo vệ, không thể xóa.'
                    );
                }

                // Ghi nhat ky truoc khi xoa.
                vnnSysAudit(
                    $pdo, 'Xóa tài khoản', 'Tài khoản',
                    (string)$id,
                    $user['fullname'].' | '.$user['email'].' | '.$user['role'],
                    'Đã xóa'
                );

                // Khong tu dong xoa cac booking/don hang lien quan.
                $st = $pdo->prepare("
                    DELETE FROM users
                    WHERE id=? AND role IN ('staff','customer')
                ");
                $st->execute([$id]);

                if ($st->rowCount() !== 1) {
                    throw new RuntimeException('Không thể xóa tài khoản.');
                }

                $popup = [
                    'type'=>'success',
                    'title'=>'Đã xóa tài khoản',
                    'message'=>'Tài khoản đã được xóa thành công.'
                ];
            }

            $pdo->commit();

        } else {
            throw new RuntimeException('Thao tác không hợp lệ.');
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        error_log('VNN Admin: '.$e->getMessage());

        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể xử lý yêu cầu.';

        if ($e instanceof PDOException && (string)$e->getCode() === '23000') {
            $message = 'Tài khoản có dữ liệu liên quan. Nên khóa thay vì xóa để giữ lịch sử.';
        }

        $popup = [
            'type'=>'error',
            'title'=>'Thao tác không thành công',
            'message'=>$message
        ];
    }

    $tab = 'users';
    $current_page = 'system-users';
}

// ====================================
// LAY DU LIEU
// ====================================

$users = [];
$loginRows = [];
$editRows = [];
$dataError = '';

try {
    if ($tab === 'users') {
        $users = vnnSysQuery($pdo, "
            SELECT id,fullname,email,phone,role,status
            FROM users
            ORDER BY FIELD(role,'admin','manager','staff','customer'),id
        ");
    }

    if ($tab === 'logins') {
        $loginRows = vnnSysQuery($pdo, "
            SELECT id,user_id,account_email,account_name,account_role,
                   login_time,ip_address,user_agent,result,note
            FROM login_audit_logs
            ORDER BY id DESC LIMIT 1000
        ");
    }

    if ($tab === 'edits') {
        $editRows = vnnSysQuery($pdo, "
            SELECT id,actor_id,actor_name,category,action,
                   target_type,target_id,old_value,new_value,created_at
            FROM admin_audit_logs
            ORDER BY id DESC LIMIT 1000
        ");
    }
} catch (Throwable $e) {
    error_log('VNN Admin load: '.$e->getMessage());
    $dataError = 'Không thể tải dữ liệu. Hãy kiểm tra SQL tạo bảng nhật ký.';
}

$roleCounts = ['admin'=>0,'manager'=>0,'staff'=>0,'customer'=>0];
$locked = 0;
foreach ($users as $u) {
    if (isset($roleCounts[$u['role']])) $roleCounts[$u['role']]++;
    if ((int)$u['status'] === 0) $locked++;
}

function vnnLoginDevice($ua): string {
    $ua = (string)$ua;
    $os = 'Không xác định';
    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'iPhone') !== false) $os = 'iPhone';
    elseif (stripos($ua, 'Mac OS') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

    $browser = 'Khác';
    if (stripos($ua, 'Edg/') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'Chrome/') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'Firefox/') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'Safari/') !== false) $browser = 'Safari';

    return $os.' / '.$browser;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= vnnSysH($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">

<style>
.vs-main {
    flex: 1 1 auto;
    min-width: 0;
    background: #f2f6fb;
}
.vs-head {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 25px 28px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}
.vs-head i { font-size: 26px; color: #1681ec; }
.vs-head h1 {
    margin: 0;
    color: #10203d;
    font-size: 23px;
    font-weight: 900;
}
.vs-head p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 13px;
}
.vs-content { padding: 24px; min-width: 0; }
.vs-panel {
    background: #fff;
    border: 1px solid #e3eaf3;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 18px;
}
.vs-panel h2 {
    margin: 0 0 16px;
    color: #0f172a;
    font-size: 18px;
}
.vs-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 17px;
    align-items: center;
}
.vs-toolbar input,
.vs-toolbar select,
.vs-field input,
.vs-field select {
    min-width: 0;
    padding: 11px 12px;
    box-sizing: border-box;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    font: inherit;
    font-size: 13px;
}
.vs-toolbar input,
.vs-toolbar select {
    flex: 1;
    min-width: 155px;
}
.vs-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 14px;
    border: 0;
    border-radius: 8px;
    background: #1677e9;
    color: white;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}
.vs-button:hover { filter: brightness(.93); }
.vs-button.red { background: #dc2626; }
.vs-button.green { background: #166534; }
.vs-button.outline {
    background: white;
    color: #dc2626;
    border: 1px solid #ef4444;
}
.vs-button.soft {
    background: #eff6ff;
    color: #1677e9;
    border: 1px solid #bfdbfe;
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
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.vs-table-scroll { overflow-x: auto; }
.vs-table {
    width: 100%;
    min-width: 970px;
    border-collapse: collapse;
}
.vs-table th,
.vs-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #e8edf5;
    text-align: left;
    vertical-align: middle;
    font-size: 12px;
}
.vs-table th {
    color: #10203d;
    background: #f7f9fc;
    font-weight: 900;
    white-space: nowrap;
}
.vs-table tr:nth-child(even) td { background: #fbfcfe; }
.vs-table tr[hidden] { display: none !important; }
.vs-strong { font-weight: 800; color: #172b4d; }
.vs-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}
.vs-badge.blue { background: #dbeafe; color: #1d4ed8; }
.vs-badge.red { background: #fee2e2; color: #b91c1c; }
.vs-badge.orange { background: #ffedd5; color: #c2410c; }
.vs-role { font-weight: 800; }
.vs-action { display: flex; gap: 8px; align-items: center; }
.vs-muted { font-size: 12px; color: #64748b; line-height: 1.7; }
.vs-count {
    margin-top: 14px;
    color: #64748b;
    font-size: 12px;
}
.vs-field-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 14px;
}
.vs-field label {
    display: block;
    margin-bottom: 6px;
    font-weight: 800;
    font-size: 12px;
}
.vs-field input,
.vs-field select { width: 100%; }
.vs-quickadd {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 18px;
    background: #f8fafc;
}
.vs-quickadd[hidden] { display: none !important; }
.vs-modal-layer {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    padding: 20px;
    box-sizing: border-box;
    background: rgba(15,23,42,.48);
}
.vs-modal-layer[hidden] { display: none !important; }
.vs-modal {
    background: white;
    border-radius: 17px;
    width: 100%;
    max-width: 430px;
    padding: 28px;
    box-sizing: border-box;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    text-align: center;
}
.vs-modal h2 { font-size: 20px; color: #0f172a; }
.vs-modal p {
    font-size: 13px;
    color: #64748b;
    line-height: 1.7;
    overflow-wrap: anywhere;
}
.vs-modal-icon { font-size: 43px; color: #16a34a; }
.vs-modal-icon.red { color: #dc2626; }
.vs-modal-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 18px;
}
.vs-detail-value {
    max-height: 180px;
    overflow-y: auto;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    text-align: left;
    background: #f8fafc;
    border-radius: 8px;
    padding: 10px;
    font-size: 12px;
}
.vs-pager {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-top: 15px;
}
.vs-page {
    padding: 7px 11px;
    border: 1px solid #dbe4f0;
    border-radius: 6px;
    background: white;
    cursor: pointer;
}
.vs-page.active { background: #1677e9; color: white; }
@media(max-width:800px) {
    .vs-content { padding: 12px; }
    .vs-head { padding: 17px; }
    .vs-field-grid { grid-template-columns: 1fr; }
}
</style>
</head>

<body class="admin-body">
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main vs-main">
    <header class="vs-head">
        <i class="fa-solid <?= $tab==='users'?'fa-users-gear':($tab==='logins'?'fa-clock-rotate-left':'fa-file-pen') ?>"></i>
        <div>
            <h1><?= vnnSysH($names[$tab]) ?></h1>
            <p>
                <?php if ($tab==='users'): ?>
                    Quản lý người dùng, phân quyền và kiểm soát truy cập hệ thống.
                <?php elseif ($tab==='logins'): ?>
                    Theo dõi thời gian, địa chỉ IP và thiết bị đăng nhập.
                <?php else: ?>
                    Theo dõi lịch sử các thay đổi nội dung được hệ thống ghi nhận.
                <?php endif; ?>
            </p>
        </div>
    </header>

    <div class="vs-content">

    <?php if ($dataError): ?>
        <div class="vs-panel" style="color:#b91c1c">
            <?= vnnSysH($dataError) ?>
        </div>
    <?php endif; ?>

    <?php if ($tab==='users'): ?>

        <section class="vs-panel">
            <div class="vs-panel-top">
                <h2 style="margin:0">Danh sách tài khoản</h2>
                <button type="button" class="vs-button" id="vsShowAdd">
                    <i class="fa-solid fa-plus"></i>
                    Thêm tài khoản mới
                </button>
            </div>

            <div class="vs-quickadd" id="vsAddPanel" hidden>
                <h2>Thêm tài khoản</h2>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= vnnSysH($csrf) ?>">
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

                    <div style="margin-top:16px">
                        <button type="submit" class="vs-button green">
                            <i class="fa-solid fa-user-plus"></i>
                            Tạo tài khoản
                        </button>
                        <button type="button" class="vs-button soft"
                                id="vsCancelAdd">Hủy</button>
                    </div>
                </form>
            </div>

            <div class="vs-toolbar">
                <input id="vsSearch" placeholder="Tìm tên hoặc email...">
                <select id="vsRoleFilter">
                    <option value="">Tất cả vai trò</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="staff">Staff</option>
                    <option value="customer">Customer</option>
                </select>
                <select id="vsStatusFilter">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1">Hoạt động</option>
                    <option value="0">Đang khóa</option>
                </select>
                <button type="button" class="vs-button soft" id="vsReset">
                    <i class="fa-solid fa-rotate"></i> Làm mới
                </button>
            </div>

            <div class="vs-table-scroll">
                <table class="vs-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Họ tên</th><th>Email</th>
                            <th>Vai trò</th><th>Trạng thái</th>
                            <th>Khóa / Mở khóa</th><th>Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $index=>$u): ?>
                            <?php
                            $protected = in_array($u['role'], ['admin','manager'], true);
                            $roleColor = $u['role']==='admin'?'red':
                                ($u['role']==='manager'?'blue':'');
                            ?>
                            <tr class="vs-data-row"
                                data-search="<?= vnnSysH(mb_strtolower($u['fullname'].' '.$u['email'],'UTF-8')) ?>"
                                data-role="<?= vnnSysH($u['role']) ?>"
                                data-status="<?= (int)$u['status'] ?>">
                                <td><?= $index+1 ?></td>
                                <td class="vs-strong"><?= vnnSysH($u['fullname']) ?></td>
                                <td><?= vnnSysH($u['email']) ?></td>
                                <td>
                                    <span class="vs-badge <?= $roleColor ?>">
                                        <?= vnnSysH(strtoupper($u['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="vs-badge <?= (int)$u['status']===1?'':'red' ?>">
                                        <?= (int)$u['status']===1?'Hoạt động':'Đang khóa' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['role']==='admin'): ?>
                                        <button class="vs-button" disabled>Được bảo vệ</button>
                                    <?php else: ?>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?= vnnSysH($csrf) ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit"
                                                class="vs-button <?= (int)$u['status']===1?'red':'green' ?>">
                                                <i class="fa-solid fa-lock"></i>
                                                <?= (int)$u['status']===1?'Khóa':'Mở khóa' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button"
                                        class="vs-button outline vs-delete"
                                        data-id="<?= (int)$u['id'] ?>"
                                        data-name="<?= vnnSysH($u['fullname']) ?>"
                                        <?= $protected?'disabled':'' ?>>
                                        <i class="fa-solid fa-trash"></i> Xóa
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

    <?php elseif ($tab==='logins'): ?>

        <section class="vs-panel">
            <div class="vs-toolbar">
                <div class="vs-field">
                    <label>Từ ngày</label>
                    <input type="date" id="vsFrom">
                </div>
                <div class="vs-field">
                    <label>Đến ngày</label>
                    <input type="date" id="vsTo">
                </div>
                <select id="vsAccountFilter">
                    <option value="">Tất cả tài khoản</option>
                    <?php
                    $accountList = [];
                    foreach ($loginRows as $entry) {
                        $accountList[$entry['account_email']] = true;
                    }
                    foreach (array_keys($accountList) as $email):
                    ?>
                        <option value="<?= vnnSysH($email) ?>">
                            <?= vnnSysH($email) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="vsResultFilter">
                    <option value="">Tất cả kết quả</option>
                    <option value="success">Thành công</option>
                    <option value="failed">Thất bại</option>
                    <option value="locked">Bị khóa</option>
                </select>
                <button type="button" id="vsReset" class="vs-button">
                    <i class="fa-solid fa-magnifying-glass"></i> Lọc
                </button>
            </div>

            <div class="vs-table-scroll">
                <table class="vs-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Tài khoản</th><th>Vai trò</th>
                            <th>Thời gian</th><th>IP</th>
                            <th>Thiết bị / Trình duyệt</th><th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loginRows as $i=>$r): ?>
                        <?php $device=vnnLoginDevice($r['user_agent']); ?>
                        <tr class="vs-data-row"
                            data-date="<?= vnnSysH(substr($r['login_time'],0,10)) ?>"
                            data-email="<?= vnnSysH($r['account_email']) ?>"
                            data-result="<?= vnnSysH($r['result']) ?>"
                            data-search="<?= vnnSysH(mb_strtolower(
                                $r['account_email'].' '.$r['account_name'].' '.$r['ip_address'],
                                'UTF-8'
                            )) ?>">
                            <td><?= $i+1 ?></td>
                            <td>
                                <span class="vs-strong">
                                    <?= vnnSysH($r['account_name'] ?: $r['account_email']) ?>
                                </span>
                                <div class="vs-muted">
                                    <?= vnnSysH($r['account_email']) ?>
                                </div>
                            </td>
                            <td><?= vnnSysH(strtoupper((string)$r['account_role'])) ?></td>
                            <td><?= vnnSysH(date('d/m/Y H:i:s',strtotime($r['login_time']))) ?></td>
                            <td><?= vnnSysH($r['ip_address']) ?></td>
                            <td title="<?= vnnSysH($r['user_agent']) ?>">
                                <?= vnnSysH($device) ?>
                            </td>
                            <td>
                                <span class="vs-badge <?= $r['result']==='success'?'':'red' ?>">
                                    <?= $r['result']==='success'?'Thành công':
                                        ($r['result']==='locked'?'Bị khóa':'Thất bại') ?>
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

        <section class="vs-panel">
            <div class="vs-toolbar">
                <div class="vs-field">
                    <label>Từ ngày</label>
                    <input type="date" id="vsFrom">
                </div>
                <div class="vs-field">
                    <label>Đến ngày</label>
                    <input type="date" id="vsTo">
                </div>
                <select id="vsTypeFilter">
                    <option value="">Tất cả loại nội dung</option>
                    <option value="Tài khoản">Tài khoản</option>
                    <option value="Tour">Tour</option>
                    <option value="Sản phẩm">Sản phẩm</option>
                    <option value="Bài viết">Bài viết</option>
                    <option value="Đơn hàng">Đơn hàng</option>
                    <option value="Khác">Khác</option>
                </select>
                <input id="vsSearch" placeholder="Tìm người thực hiện, hành động...">
                <button type="button" id="vsReset" class="vs-button">
                    <i class="fa-solid fa-magnifying-glass"></i> Lọc
                </button>
            </div>

            <div class="vs-table-scroll">
                <table class="vs-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Người thực hiện</th>
                            <th>Loại nội dung</th><th>Đối tượng</th>
                            <th>Hành động</th><th>Thời gian</th><th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($editRows as $i=>$r): ?>
                        <?php
                        $type = match ((string)$r['target_type']) {
                            'users','Tài khoản' => 'Tài khoản',
                            'tours','Tour' => 'Tour',
                            'products','Sản phẩm' => 'Sản phẩm',
                            'posts','Bài viết' => 'Bài viết',
                            'orders','Đơn hàng' => 'Đơn hàng',
                            default => 'Khác'
                        };
                        ?>
                        <tr class="vs-data-row"
                            data-date="<?= vnnSysH(substr($r['created_at'],0,10)) ?>"
                            data-type="<?= vnnSysH($type) ?>"
                            data-search="<?= vnnSysH(mb_strtolower(
                                $r['actor_name'].' '.$r['action'].' '.$r['target_type'].' '.$r['target_id'],
                                'UTF-8'
                            )) ?>">
                            <td><?= $i+1 ?></td>
                            <td class="vs-strong"><?= vnnSysH($r['actor_name']) ?></td>
                            <td><?= vnnSysH($type) ?></td>
                            <td><?= vnnSysH($r['target_type'].' #'.$r['target_id']) ?></td>
                            <td><span class="vs-badge blue"><?= vnnSysH($r['action']) ?></span></td>
                            <td><?= vnnSysH(date('d/m/Y H:i:s',strtotime($r['created_at']))) ?></td>
                            <td>
                                <button type="button" class="vs-button soft vs-detail"
                                    data-action="<?= vnnSysH($r['action']) ?>"
                                    data-before="<?= vnnSysH($r['old_value']) ?>"
                                    data-after="<?= vnnSysH($r['new_value']) ?>">
                                    <i class="fa-solid fa-eye"></i> Xem
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

<!-- FORM XOA -->
<form method="POST" id="vsDeleteForm" hidden>
    <input type="hidden" name="csrf" value="<?= vnnSysH($csrf) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="vsDeleteId">
</form>

<!-- XAC NHAN XOA -->
<div class="vs-modal-layer" id="vsConfirm" hidden>
    <div class="vs-modal">
        <div class="vs-modal-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h2>Xác nhận xóa tài khoản</h2>
        <p>Bạn có chắc chắn muốn xóa <strong id="vsDeleteName"></strong>?</p>
        <p>Thao tác này không thể hoàn tác.</p>
        <div class="vs-modal-actions">
            <button type="button" class="vs-button soft" id="vsCancelDelete">Hủy</button>
            <button type="button" class="vs-button red" id="vsConfirmDelete">Xóa tài khoản</button>
        </div>
    </div>
</div>

<!-- POPUP KET QUA -->
<div class="vs-modal-layer" id="vsResult" <?= $popup?'':'hidden' ?>>
    <div class="vs-modal">
        <div class="vs-modal-icon <?= ($popup['type']??'')==='error'?'red':'' ?>">
            <i class="fa-solid <?= ($popup['type']??'')==='error'?'fa-circle-xmark':'fa-circle-check' ?>"></i>
        </div>
        <h2><?= vnnSysH($popup['title']??'Thông báo') ?></h2>
        <p><?= vnnSysH($popup['message']??'') ?></p>
        <button type="button" class="vs-button green" id="vsCloseResult">Đóng</button>
    </div>
</div>

<!-- CHI TIET LICH SU -->
<div class="vs-modal-layer" id="vsDetailModal" hidden>
    <div class="vs-modal" style="max-width:540px">
        <h2 id="vsDetailTitle">Chi tiết chỉnh sửa</h2>
        <p style="text-align:left;font-weight:800">Trước khi sửa</p>
        <div class="vs-detail-value" id="vsBefore"></div>
        <p style="text-align:left;font-weight:800">Sau khi sửa</p>
        <div class="vs-detail-value" id="vsAfter"></div>
        <div style="margin-top:18px">
            <button type="button" class="vs-button" id="vsCloseDetail">Đóng</button>
        </div>
    </div>
</div>

<script>
(function() {
    const $ = id => document.getElementById(id);
    const rows = Array.from(document.querySelectorAll('.vs-data-row'));
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
            return (!search || (d.search || '').includes(search)) &&
                (!role || d.role === role) &&
                (!status || d.status === status) &&
                (!from || (d.date || '') >= from) &&
                (!to || (d.date || '') <= to) &&
                (!email || d.email === email) &&
                (!result || d.result === result) &&
                (!type || d.type === type);
        });
    }

    function render() {
        const found = filteredRows();
        const pages = Math.max(1, Math.ceil(found.length / pageSize));
        page = Math.min(page, pages);

        rows.forEach(r => r.hidden = true);

        found.slice((page - 1) * pageSize, page * pageSize)
            .forEach(r => r.hidden = false);

        if ($('vsCount')) {
            $('vsCount').textContent =
                'Hiển thị ' + (found.length ? (page-1)*pageSize+1 : 0) +
                ' - ' + Math.min(page*pageSize, found.length) +
                ' trong ' + found.length + ' bản ghi';
        }

        const pager = $('vsPager');
        if (!pager) return;
        pager.replaceChildren();

        for (let p = 1; p <= pages; p++) {
            if (pages > 9 && Math.abs(p-page) > 2 &&
                p !== 1 && p !== pages) continue;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'vs-page' + (p===page?' active':'');
            b.textContent = String(p);
            b.addEventListener('click', () => {
                page = p;
                render();
            });
            pager.appendChild(b);
        }
    }

    document.querySelectorAll(
        '#vsSearch,#vsRoleFilter,#vsStatusFilter,#vsFrom,#vsTo,'+
        '#vsAccountFilter,#vsResultFilter,#vsTypeFilter'
    ).forEach(el => {
        el.addEventListener('input', () => { page=1; render(); });
        el.addEventListener('change', () => { page=1; render(); });
    });

    $('vsReset')?.addEventListener('click', () => {
        document.querySelectorAll(
            '#vsSearch,#vsRoleFilter,#vsStatusFilter,#vsFrom,#vsTo,'+
            '#vsAccountFilter,#vsResultFilter,#vsTypeFilter'
        ).forEach(el => el.value = '');
        page = 1;
        render();
    });

    $('vsShowAdd')?.addEventListener('click', () => {
        $('vsAddPanel').hidden = false;
    });
    $('vsCancelAdd')?.addEventListener('click', () => {
        $('vsAddPanel').hidden = true;
    });

    document.querySelectorAll('.vs-delete').forEach(btn => {
        btn.addEventListener('click', () => {
            $('vsDeleteId').value = btn.dataset.id;
            $('vsDeleteName').textContent = btn.dataset.name;
            $('vsConfirm').hidden = false;
        });
    });
    $('vsCancelDelete').addEventListener('click', () => {
        $('vsConfirm').hidden = true;
    });
    $('vsConfirmDelete').addEventListener('click', () => {
        $('vsDeleteForm').requestSubmit();
    });

    document.querySelectorAll('.vs-detail').forEach(btn => {
        btn.addEventListener('click', () => {
            $('vsDetailTitle').textContent = btn.dataset.action;
            $('vsBefore').textContent = btn.dataset.before || 'Không có dữ liệu';
            $('vsAfter').textContent = btn.dataset.after || 'Không có dữ liệu';
            $('vsDetailModal').hidden = false;
        });
    });
    $('vsCloseDetail').addEventListener('click', () => {
        $('vsDetailModal').hidden = true;
    });
    $('vsCloseResult').addEventListener('click', () => {
        $('vsResult').hidden = true;
    });

    ['vsConfirm','vsResult','vsDetailModal'].forEach(id => {
        $(id).addEventListener('click', e => {
            if (e.target === $(id)) $(id).hidden = true;
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            ['vsConfirm','vsResult','vsDetailModal'].forEach(id => {
                $(id).hidden = true;
            });
        }
    });

    render();
})();
</script>
</body>
</html>
