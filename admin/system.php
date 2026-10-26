
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if (!function_exists('vnnSysEsc')) {
    function vnnSysEsc($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vnnSysRows')) {
    function vnnSysRows(
        PDO $pdo,
        string $sql,
        array $params = []
    ) {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Khong dat ten ham la sysLog()
// vi trung voi syslog() co san trong PHP.
if (!function_exists('vnnSysAuditWrite')) {
    function vnnSysAuditWrite(
        PDO $pdo,
        string $action,
        int $targetId,
        string $old,
        string $new
    ) {
        $actorId = (int)($_SESSION['user_id'] ?? 0);

        $nameSt = $pdo->prepare(
            'SELECT fullname FROM users WHERE id=?'
        );

        $nameSt->execute([$actorId]);

        $actor = (string)(
            $nameSt->fetchColumn() ?: 'Admin'
        );

        $st = $pdo->prepare("
            INSERT INTO admin_audit_logs
            (
                actor_id,
                actor_name,
                category,
                action,
                target_type,
                target_id,
                old_value,
                new_value
            )
            VALUES (?, ?, 'accounts', ?, 'users', ?, ?, ?)
        ");

        $st->execute([
            $actorId,
            $actor,
            $action,
            (string)$targetId,
            $old,
            $new
        ]);
    }
}

$tab = (string)($_GET['tab'] ?? 'users');

if (!in_array($tab, ['users', 'history'], true)) {
    $tab = 'users';
}

$groups = [
    'accounts' => 'Tài khoản',
    'content' => 'Nội dung website',
    'transactions' => 'Giao dịch',
    'security' => 'Bảo mật'
];

$category = (string)($_GET['category'] ?? 'accounts');

if (!isset($groups[$category])) {
    $category = 'accounts';
}

$current_page = 'system-' . $tab;

$page_title = (
    $tab === 'users'
        ? 'Quản lý & Cấp quyền tài khoản'
        : 'Lịch sử hoạt động'
) . ' - VNN Travel';

if (empty($_SESSION['admin_system_csrf'])) {
    $_SESSION['admin_system_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['admin_system_csrf'];

$popup = $_SESSION['admin_system_popup'] ?? null;
unset($_SESSION['admin_system_popup']);

// =========================================
// XU LY TAI KHOAN
// =========================================

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

        if ($action === 'add_user') {
            $name = trim((string)($_POST['fullname'] ?? ''));
            $email = strtolower(
                trim((string)($_POST['email'] ?? ''))
            );
            $phone = trim((string)($_POST['phone'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'staff');

            if (
                $name === '' ||
                mb_strlen($name) > 255 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new RuntimeException(
                    'Tên hoặc email không hợp lệ.'
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
                    'Chỉ được tạo tài khoản Nhân viên hoặc Khách hàng tại đây.'
                );
            }

            $pdo->beginTransaction();

            $check = $pdo->prepare(
                'SELECT id FROM users WHERE email=? LIMIT 1'
            );

            $check->execute([$email]);

            if ($check->fetchColumn()) {
                throw new RuntimeException(
                    'Email đã tồn tại.'
                );
            }

            $insert = $pdo->prepare("
                INSERT INTO users
                    (fullname, email, password, phone, role, status)
                VALUES (?, ?, ?, ?, ?, 1)
            ");

            $insert->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $phone,
                $role
            ]);

            $id = (int)$pdo->lastInsertId();

            vnnSysAuditWrite(
                $pdo,
                'Tạo tài khoản',
                $id,
                '',
                $name . ' (' . $role . ')'
            );

            $pdo->commit();

        } elseif ($action === 'toggle_user') {
            $id = (int)($_POST['user_id'] ?? 0);

            if (
                $id <= 0 ||
                $id === (int)($_SESSION['user_id'] ?? 0)
            ) {
                throw new RuntimeException(
                    'Không thể khóa tài khoản hiện tại.'
                );
            }

            $pdo->beginTransaction();

            $st = $pdo->prepare("
                SELECT id, fullname, role, status
                FROM users
                WHERE id=?
                FOR UPDATE
            ");

            $st->execute([$id]);
            $user = $st->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException(
                    'Không tìm thấy tài khoản.'
                );
            }

            if ($user['role'] === 'admin') {
                throw new RuntimeException(
                    'Không thể khóa tài khoản Admin.'
                );
            }

            $old = (int)$user['status'];
            $new = $old === 1 ? 0 : 1;

            $update = $pdo->prepare(
                'UPDATE users SET status=? WHERE id=?'
            );

            $update->execute([$new, $id]);

            vnnSysAuditWrite(
                $pdo,
                $new === 1
                    ? 'Mở khóa tài khoản'
                    : 'Khóa tài khoản',
                $id,
                (string)$old,
                (string)$new
            );

            $pdo->commit();

        } else {
            throw new RuntimeException(
                'Thao tác không được hỗ trợ.'
            );
        }

        $_SESSION['admin_system_popup'] = [
            'type' => 'success',
            'title' => 'Đã lưu thay đổi',
            'message' => 'Thao tác đã hoàn tất thành công.'
        ];

        header(
            'Location: system.php?tab=users',
            true,
            303
        );
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Admin system: ' . $e->getMessage()
        );

        $popup = [
            'type' => 'error',
            'title' => 'Không thể lưu thay đổi',
            'message' => $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Có lỗi xảy ra.'
        ];
    }
}

// =========================================
// LAY DANH SACH TAI KHOAN
// =========================================

$users = vnnSysRows($pdo, "
    SELECT id, fullname, email, phone, role, status
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

$locked = 0;

foreach ($users as $u) {
    if (isset($roleCounts[$u['role']])) {
        $roleCounts[$u['role']]++;
    }

    if ((int)$u['status'] === 0) {
        $locked++;
    }
}

// =========================================
// LAY LICH SU
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
        $logError =
            'Chưa có bảng nhật ký admin_audit_logs. ' .
            'Hãy chạy SQL tạo bảng trước.';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title><?= vnnSysEsc($page_title) ?></title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">

<style>
.vnn-sys-main {
    flex: 1;
    min-width: 0;
    background: #f1f5f9;
}

.vnn-sys-content {
    padding: 24px;
}

.vnn-sys-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 20px;
}

.vnn-sys-card h2 {
    margin: 0 0 18px;
    color: #0f172a;
    font-size: 19px;
}

.vnn-sys-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.vnn-sys-stat {
    background: white;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #e2e8f0;
}

.vnn-sys-stat span {
    color: #64748b;
    font-size: 12px;
}

.vnn-sys-stat b {
    display: block;
    color: #166534;
    font-size: 28px;
    margin-top: 8px;
}

.vnn-sys-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 14px;
}

.vnn-sys-field label {
    display: block;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 7px;
}

.vnn-sys-field input,
.vnn-sys-field select,
.vnn-sys-filter input,
.vnn-sys-filter select {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    font: inherit;
    font-size: 13px;
}

.vnn-sys-field.wide {
    grid-column: 1/-1;
}

.vnn-sys-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #166534;
    border: 0;
    border-radius: 9px;
    padding: 11px 16px;
    color: white;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.vnn-sys-button.danger {
    background: #dc2626;
}

.vnn-sys-button.small {
    padding: 8px 12px;
    font-size: 12px;
}

.vnn-sys-filter {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.vnn-sys-filter > * {
    flex: 1;
    min-width: 155px;
}

.vnn-sys-table-wrap {
    overflow-x: auto;
}

.vnn-sys-table {
    width: 100%;
    min-width: 800px;
    border-collapse: collapse;
}

.vnn-sys-table th,
.vnn-sys-table td {
    padding: 13px 11px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
}

.vnn-sys-table th {
    background: #f8fafc;
    color: #0f172a;
}

.vnn-sys-table tr[hidden] {
    display: none;
}

.vnn-sys-pill {
    display: inline-block;
    border-radius: 15px;
    padding: 6px 10px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 800;
}

.vnn-sys-pill.off {
    background: #fee2e2;
    color: #991b1b;
}

.vnn-sys-hint {
    font-size: 13px;
    line-height: 1.8;
    color: #64748b;
}

.vnn-sys-details {
    max-width: 330px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    font-size: 12px;
    color: #475569;
}

.vnn-sys-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    padding: 20px;
}

.vnn-sys-overlay[hidden] {
    display: none !important;
}

.vnn-sys-popup {
    background: white;
    border-radius: 17px;
    padding: 30px;
    max-width: 380px;
    width: 100%;
    text-align: center;
}

.vnn-sys-popup i {
    font-size: 34px;
    color: #166534;
}

.vnn-sys-popup.error i {
    color: #dc2626;
}

.vnn-sys-popup h2 {
    font-size: 21px;
}

.vnn-sys-popup p {
    font-size: 13px;
    color: #64748b;
}

@media(max-width:850px) {
    .vnn-sys-stats {
        grid-template-columns: repeat(2,1fr);
    }

    .vnn-sys-content {
        padding: 12px;
    }

    .vnn-sys-form {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="admin-body">

<?php require __DIR__.'/includes/sidebar.php'; ?>

<main class="admin-main vnn-sys-main">

    <div class="admin-topbar">
        <strong>
            <i class="fa-solid <?= $tab==='history'
                ? 'fa-clock-rotate-left'
                : 'fa-users-gear' ?>"></i>

            <?= $tab === 'history'
                ? 'LỊCH SỬ HOẠT ĐỘNG'
                : 'QUẢN LÝ & CẤP QUYỀN TÀI KHOẢN' ?>
        </strong>
    </div>

    <div class="vnn-sys-content">

    <?php if ($tab === 'users'): ?>

        <!-- THONG KE TAI KHOAN -->

        <div class="vnn-sys-stats">

            <?php foreach ([
                ['Tổng tài khoản', count($users)],
                ['Nhân viên', $roleCounts['staff']],
                ['Khách hàng', $roleCounts['customer']],
                ['Đang khóa', $locked]
            ] as $stat): ?>

                <div class="vnn-sys-stat">
                    <span><?= vnnSysEsc($stat[0]) ?></span>
                    <b><?= (int)$stat[1] ?></b>
                </div>

            <?php endforeach; ?>

        </div>

        <!-- THEM TAI KHOAN -->

        <section class="vnn-sys-card">
            <h2>Thêm tài khoản mới</h2>

            <p class="vnn-sys-hint">
                Admin và Manager được bảo vệ.
                Không tạo thêm tài khoản cùng vai trò tại đây.
            </p>

            <form method="POST" class="vnn-sys-form">

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

                <div class="vnn-sys-field">
                    <label>Họ tên</label>
                    <input
                        name="fullname"
                        required
                        maxlength="255"
                    >
                </div>

                <div class="vnn-sys-field">
                    <label>Email</label>
                    <input
                        name="email"
                        type="email"
                        required
                    >
                </div>

                <div class="vnn-sys-field">
                    <label>Số điện thoại</label>
                    <input name="phone">
                </div>

                <div class="vnn-sys-field">
                    <label>Mật khẩu khởi tạo (ít nhất 8 ký tự)</label>
                    <input
                        name="password"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="vnn-sys-field">
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

                <div
                    class="vnn-sys-field"
                    style="display:flex;align-items:end"
                >
                    <button class="vnn-sys-button" type="submit">
                        <i class="fa-solid fa-user-plus"></i>
                        Tạo tài khoản
                    </button>
                </div>

            </form>
        </section>

        <!-- DANH SACH TAI KHOAN -->

        <section class="vnn-sys-card">
            <h2>Danh sách tài khoản</h2>

            <div class="vnn-sys-filter">
                <input
                    id="vnnUserSearch"
                    placeholder="Tìm tên hoặc email..."
                >

                <select id="vnnUserRole">
                    <option value="">Tất cả vai trò</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="staff">Staff</option>
                    <option value="customer">Customer</option>
                </select>

                <select id="vnnUserStatus">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1">Hoạt động</option>
                    <option value="0">Đang khóa</option>
                </select>
            </div>

            <div class="vnn-sys-table-wrap">
                <table class="vnn-sys-table">

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

                        <tr
                            class="vnn-user-row"
                            data-search="<?= vnnSysEsc(
                                mb_strtolower(
                                    $user['fullname'].' '.$user['email'],
                                    'UTF-8'
                                )
                            ) ?>"
                            data-role="<?= vnnSysEsc($user['role']) ?>"
                            data-status="<?= (int)$user['status'] ?>"
                        >

                            <td>
                                <strong>
                                    <?= vnnSysEsc($user['fullname']) ?>
                                </strong>
                            </td>

                            <td><?= vnnSysEsc($user['email']) ?></td>

                            <td>
                                <?= vnnSysEsc(
                                    strtoupper($user['role'])
                                ) ?>
                            </td>

                            <td>
                                <span class="vnn-sys-pill <?= (int)$user['status']===1?'':'off' ?>">
                                    <?= (int)$user['status']===1
                                        ? 'Hoạt động'
                                        : 'Đang khóa' ?>
                                </span>
                            </td>

                            <td>

                            <?php if (
                                $user['role'] !== 'admin' &&
                                (int)$user['id'] !==
                                (int)($_SESSION['user_id'] ?? 0)
                            ): ?>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Xác nhận thay đổi trạng thái tài khoản này?')"
                                >

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
                                        value="<?= (int)$user['id'] ?>"
                                    >

                                    <button
                                        class="vnn-sys-button small <?= (int)$user['status']===1?'danger':'' ?>"
                                        type="submit"
                                    >
                                        <?= (int)$user['status']===1
                                            ? 'Khóa'
                                            : 'Mở khóa' ?>
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="vnn-sys-hint">
                                    Được bảo vệ
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

        <section class="vnn-sys-card">

            <h2><?= vnnSysEsc($groups[$category]) ?></h2>

            <p class="vnn-sys-hint">
                Chọn nhóm trong menu “Lịch sử hoạt động” bên trái
                để mở rộng và xem từng loại nhật ký.
                Nhật ký chỉ hiển thị các thao tác đã được tích hợp
                ghi nhận sau khi triển khai.
            </p>

            <?php if ($logError): ?>
                <p style="color:#b91c1c">
                    <?= vnnSysEsc($logError) ?>
                </p>
            <?php endif; ?>

            <div class="vnn-sys-filter">

                <input
                    id="vnnHistorySearch"
                    placeholder="Tìm người thực hiện hoặc hành động..."
                >

                <input
                    type="date"
                    id="vnnHistoryDate"
                    aria-label="Lọc ngày"
                >

            </div>

            <div class="vnn-sys-table-wrap">
                <table class="vnn-sys-table">

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
                                substr($log['created_at'], 0, 10)
                            ) ?>"
                            data-search="<?= vnnSysEsc(
                                mb_strtolower(
                                    $log['actor_name'].' '.$log['action'],
                                    'UTF-8'
                                )
                            ) ?>"
                        >

                            <td>
                                <?= vnnSysEsc($log['created_at']) ?>
                            </td>

                            <td>
                                <?= vnnSysEsc($log['actor_name']) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= vnnSysEsc($log['action']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= vnnSysEsc($log['target_type']) ?>
                                #<?= vnnSysEsc($log['target_id']) ?>
                            </td>

                            <td>
                                <details>
                                    <summary>Xem chi tiết</summary>

                                    <div class="vnn-sys-details">
                                        <b>Trước:</b>
                                        <?= vnnSysEsc($log['old_value']) ?>

                                        <b>Sau:</b>
                                        <?= vnnSysEsc($log['new_value']) ?>
                                    </div>
                                </details>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

            <p
                class="vnn-sys-hint"
                id="vnnEmptyLog"
                <?= !$logs?'':'hidden' ?>
            >
                Chưa có nhật ký phù hợp.
            </p>

        </section>

    <?php endif; ?>

    </div>
</main>

<!-- POPUP THONG BAO -->

<div
    class="vnn-sys-overlay"
    id="vnnSystemPopup"
    <?= $popup?'':'hidden' ?>
>

    <div class="vnn-sys-popup <?= ($popup['type']??'')==='error'?'error':'' ?>">

        <i class="fa-solid <?= ($popup['type']??'')==='error'
            ? 'fa-circle-xmark'
            : 'fa-circle-check' ?>"></i>

        <h2>
            <?= vnnSysEsc($popup['title']??'Thông báo') ?>
        </h2>

        <p>
            <?= vnnSysEsc($popup['message']??'') ?>
        </p>

        <button
            class="vnn-sys-button"
            id="vnnClosePopup"
            type="button"
        >
            Đóng
        </button>

    </div>

</div>

<script>
(function() {

    const q = document.getElementById('vnnUserSearch');
    const role = document.getElementById('vnnUserRole');
    const status = document.getElementById('vnnUserStatus');

    function filterUsers() {
        if (!q) return;

        const term = q.value.toLocaleLowerCase('vi').trim();

        document.querySelectorAll('.vnn-user-row').forEach(row => {
            row.hidden = !(
                row.dataset.search.includes(term) &&
                (!role.value || role.value === row.dataset.role) &&
                (!status.value || status.value === row.dataset.status)
            );
        });
    }

    [q, role, status].forEach(el => {
        if (el) {
            el.addEventListener('input', filterUsers);
            el.addEventListener('change', filterUsers);
        }
    });

    const hq = document.getElementById('vnnHistorySearch');
    const hd = document.getElementById('vnnHistoryDate');

    function filterHistory() {
        if (!hq) return;

        let n = 0;
        const term = hq.value.toLocaleLowerCase('vi').trim();

        document.querySelectorAll('.vnn-log-row').forEach(row => {
            row.hidden = !(
                row.dataset.search.includes(term) &&
                (!hd.value || hd.value === row.dataset.date)
            );

            if (!row.hidden) n++;
        });

        const empty = document.getElementById('vnnEmptyLog');

        if (empty) {
            empty.hidden = n > 0;
        }
    }

    [hq, hd].forEach(el => {
        if (el) {
            el.addEventListener('input', filterHistory);
            el.addEventListener('change', filterHistory);
        }
    });

    filterHistory();

    const pop = document.getElementById('vnnSystemPopup');

    function closePopup() {
        if (pop) pop.hidden = true;
    }

    const btn = document.getElementById('vnnClosePopup');

    if (btn) {
        btn.addEventListener('click', closePopup);
    }

    if (pop) {
        pop.addEventListener('click', function(event) {
            if (event.target === pop) {
                closePopup();
            }
        });
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closePopup();
        }
    });

})();
</script>

</body>
</html>
