<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

requireStaff();

$current_page = 'bookings';
$page_title = 'Quản Lý Đăng Ký Tour - VNN Travel';

function bk_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function bk_is_person($name) {
    $name = trim((string)$name);
    if ($name === '') return false;

    $lower = mb_strtolower($name, 'UTF-8');

    foreach ([
        'admin', 'manager', 'quản lý',
        'quản trị', 'hệ thống', 'điều hành',
        'nhân viên', 'staff', 'demo', 'test'
    ] as $blocked) {
        if (mb_strpos($lower, $blocked) !== false) {
            return false;
        }
    }

    return count(preg_split('/\s+/u', $name)) >= 2;
}

$statuses = [
    'Chờ xác nhận',
    'Đã xác nhận',
    'Đã thanh toán',
    'Đã hoàn thành',
    'Đã hủy'
];

$sampleGuides = [
    ['Nguyễn Văn Hùng', 'hdv.nguyen-van-hung@example.invalid'],
    ['Lê Minh Tuấn', 'hdv.le-minh-tuan@example.invalid'],
    ['Phạm Đức Anh', 'hdv.pham-duc-anh@example.invalid'],
    ['Hoàng Thị Mai', 'hdv.hoang-thi-mai@example.invalid'],
    ['Đỗ Quang Huy', 'hdv.do-quang-huy@example.invalid'],
    ['Bùi Ngọc Hà', 'hdv.bui-ngoc-ha@example.invalid']
];

$error = '';
$success = '';

if (empty($_SESSION['bookings_csrf'])) {
    $_SESSION['bookings_csrf'] = bin2hex(random_bytes(16));
}

$csrf = $_SESSION['bookings_csrf'];
$selfId = (int)($_SESSION['user_id'] ?? 0);

// Xac dinh quyen tao nhan su mau.
$currentRole = '';

if ($selfId > 0) {
    $stmt = $pdo->prepare(
        "SELECT role FROM users WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$selfId]);
    $currentRole = (string)$stmt->fetchColumn();
}

$canSeed = in_array($currentRole, ['admin', 'manager'], true);

// ======================================
// XU LY POST
// ======================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedCsrf = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($csrf, $postedCsrf)) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';

    } elseif (isset($_POST['add_sample_guides'])) {

        if (!$canSeed) {
            $error = 'Chỉ quản lý mới được thêm HDV mẫu.';
        } else {
            try {
                // Kiem tra cau truc users truoc khi INSERT.
                $stmt = $pdo->query("
                    SELECT COLUMN_NAME, IS_NULLABLE,
                           COLUMN_DEFAULT, EXTRA
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'users'
                ");

                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = array_column($columns, 'COLUMN_NAME');

                foreach (['fullname', 'email', 'password', 'role'] as $required) {
                    if (!in_array($required, $columnNames, true)) {
                        throw new RuntimeException(
                            'Bảng users thiếu cột ' . $required
                        );
                    }
                }

                // Bao dam cac truong bat buoc khac duoc
                // database cung cap gia tri mac dinh.
                $provided = [
                    'fullname', 'email', 'password',
                    'role', 'phone', 'status'
                ];

                foreach ($columns as $col) {
                    if (
                        !in_array($col['COLUMN_NAME'], $provided, true) &&
                        $col['IS_NULLABLE'] === 'NO' &&
                        $col['COLUMN_DEFAULT'] === null &&
                        stripos((string)$col['EXTRA'], 'auto_increment') === false &&
                        stripos((string)$col['EXTRA'], 'generated') === false
                    ) {
                        throw new RuntimeException(
                            'Bảng users có thêm cột bắt buộc: ' .
                            $col['COLUMN_NAME']
                        );
                    }
                }

                $fields = ['fullname', 'email', 'password', 'role'];

                if (in_array('phone', $columnNames, true)) {
                    $fields[] = 'phone';
                }

                if (in_array('status', $columnNames, true)) {
                    $fields[] = 'status';
                }

                $sql = "INSERT INTO users (`" .
                    implode("`,`", $fields) .
                    "`) VALUES (" .
                    implode(',', array_fill(0, count($fields), '?')) .
                    ")";

                $insert = $pdo->prepare($sql);
                $exists = $pdo->prepare(
                    "SELECT id FROM users WHERE email = ? LIMIT 1"
                );

                $pdo->beginTransaction();
                $added = 0;

                foreach ($sampleGuides as [$name, $email]) {
                    $exists->execute([$email]);

                    if ($exists->fetchColumn()) {
                        continue;
                    }

                    // Mat khau ngau nhien, khong cong bo;
                    // tai khoan chi phuc vu demo phan cong.
                    $password = password_hash(
                        bin2hex(random_bytes(32)),
                        PASSWORD_DEFAULT
                    );

                    $values = [$name, $email, $password, 'staff'];

                    if (in_array('phone', $fields, true)) {
                        $values[] = null;
                    }

                    if (in_array('status', $fields, true)) {
                        $values[] = 1;
                    }

                    $insert->execute($values);
                    $added++;
                }

                $pdo->commit();

                $_SESSION['bookings_notice'] =
                    $added > 0
                    ? 'Đã thêm ' . $added . ' hướng dẫn viên mẫu.'
                    : 'Các hướng dẫn viên mẫu đã có trong hệ thống.';

                header('Location: index.php', true, 303);
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Seed guide error: ' . $e->getMessage());

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Không thể thêm HDV. Vui lòng kiểm tra database.';
            }
        }

    } elseif (isset($_POST['update_booking'])) {

        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $guideId = (int)($_POST['guide_id'] ?? 0);

        if ($bookingId <= 0 ||
            !in_array($status, $statuses, true)) {

            $error = 'Thông tin cập nhật không hợp lệ.';

        } else {
            try {
                if ($guideId > 0) {
                    $check = $pdo->prepare("
                        SELECT fullname FROM users
                        WHERE id = ? AND role = 'staff'
                        LIMIT 1
                    ");
                    $check->execute([$guideId]);
                    $guideName = $check->fetchColumn();

                    if (!$guideName || !bk_is_person($guideName)) {
                        throw new RuntimeException(
                            'HDV không hợp lệ. Không thể phân công tài khoản quản lý.'
                        );
                    }
                }

                $stmt = $pdo->prepare("
                    UPDATE tour_bookings
                    SET status = ?, guide_id = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $status,
                    $guideId > 0 ? $guideId : null,
                    $bookingId
                ]);

                $_SESSION['bookings_notice'] =
                    'Đã lưu phân công hướng dẫn viên thành công.';

                header('Location: index.php', true, 303);
                exit;

            } catch (Throwable $e) {
                error_log('Booking update error: ' . $e->getMessage());

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Không thể cập nhật booking.';
            }
        }
    }
}

if (isset($_SESSION['bookings_notice'])) {
    $success = $_SESSION['bookings_notice'];
    unset($_SESSION['bookings_notice']);
}

// ======================================
// LAY DANH SACH HUONG DAN VIEN
// ======================================

$stmt = $pdo->query("
    SELECT id, fullname
    FROM users
    WHERE role = 'staff'
    ORDER BY fullname ASC
");

$guides = array_values(array_filter(
    $stmt->fetchAll(PDO::FETCH_ASSOC),
    fn($row) => bk_is_person($row['fullname'])
));

// ======================================
// LAY BOOKINGS
// ======================================

$filter = trim((string)($_GET['status'] ?? ''));
$params = [];

$sql = "
    SELECT b.*, t.title AS tour_name,
           u.fullname AS guide_name
    FROM tour_bookings b
    JOIN tours t ON t.id = b.tour_id
    LEFT JOIN users u ON u.id = b.guide_id
";

if ($filter !== '' && in_array($filter, $statuses, true)) {
    $sql .= " WHERE b.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title><?= bk_h($page_title) ?></title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.bk-top {
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:15px;
}

.bk-toolbar {
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
    margin-bottom:20px;
}

.bk-add {
    border:0;
    background:#166534;
    color:white;
    border-radius:9px;
    padding:12px 17px;
    font-weight:800;
    cursor:pointer;
}

.bk-add:hover {background:#15803d}

.bk-info {
    background:#eff6ff;
    color:#1e40af;
    border:1px solid #bfdbfe;
    border-radius:9px;
    padding:12px 15px;
    margin-bottom:18px;
}

.bk-success {
    background:#dcfce7;
    color:#166534;
    padding:14px;
    border-radius:9px;
    margin-bottom:18px;
}

.bk-error {
    background:#fee2e2;
    color:#991b1b;
    padding:14px;
    border-radius:9px;
    margin-bottom:18px;
}

.bk-table-wrap {
    background:white;
    border-radius:14px;
    overflow-x:auto;
    border:1px solid #e2e8f0;
}

.bk-table {
    width:100%;
    min-width:1100px;
    border-collapse:collapse;
}

.bk-table th {
    padding:18px 14px;
    background:#f8fafc;
    color:#0f172a;
    font-weight:800;
    text-align:left;
    border-bottom:1px solid #e2e8f0;
}

.bk-table td {
    padding:17px 14px;
    border-bottom:1px solid #e2e8f0;
    vertical-align:middle;
}

.bk-table tr:hover td {
    background:#f8fafc;
}

.bk-code {
    font-weight:800;
    color:#1e3a5f;
}

.bk-select {
    width:100%;
    min-width:140px;
    max-width:230px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    padding:10px 8px;
    background:white;
    font:inherit;
    font-size:13px;
}

.bk-save {
    background:#166534;
    color:white;
    border:0;
    padding:11px 17px;
    border-radius:9px;
    font-weight:800;
    cursor:pointer;
}

.bk-phone {
    display:block;
    color:#64748b;
    font-size:12px;
    margin-top:7px;
}

.bk-date {
    color:#ea580c;
    font-weight:800;
    white-space:nowrap;
}

.bk-form {
    margin:0;
}

@media(max-width:700px) {
    .bk-table th,.bk-table td {padding:12px}
}
</style>
</head>

<body class="admin-body">

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">

    <div class="admin-topbar bk-top">

        <strong>
            <i class="fa-solid fa-person-hiking"></i>
            QUẢN LÝ ĐĂNG KÝ TOUR TREKKING
        </strong>

        <form method="GET">
            <select name="status"
                    class="form-control"
                    onchange="this.form.submit()">

                <option value="">Tất cả trạng thái</option>

                <?php foreach ($statuses as $s): ?>
                    <option
                        value="<?= bk_h($s) ?>"
                        <?= $filter === $s ? 'selected' : '' ?>
                    >
                        <?= bk_h($s) ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </form>

    </div>

    <div class="admin-content">

        <?php if ($success): ?>
            <div class="bk-success">
                <i class="fa-solid fa-circle-check"></i>
                <?= bk_h($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bk-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= bk_h($error) ?>
            </div>
        <?php endif; ?>

        <div class="bk-toolbar">

            <?php if ($canSeed): ?>
            <form method="POST" class="bk-form">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= bk_h($csrf) ?>"
                >

                <button
                    type="submit"
                    name="add_sample_guides"
                    value="1"
                    class="bk-add"
                    onclick="return confirm('Thêm 6 HDV mẫu vào Aiven? Các tài khoản này chỉ phục vụ demo phân công tour.');"
                >
                    <i class="fa-solid fa-user-plus"></i>
                    Thêm 6 HDV mẫu
                </button>

            </form>
            <?php endif; ?>

            <span style="color:#64748b">
                <i class="fa-solid fa-users"></i>
                Có <?= count($guides) ?> nhân viên HDV trong danh sách
            </span>

        </div>

        <div class="bk-info">
            <i class="fa-solid fa-circle-info"></i>
            Danh sách phân công chỉ hiển thị tên cá nhân.
            Tài khoản Admin và Manager không được phép
            làm hướng dẫn viên.
        </div>

        <div class="bk-table-wrap">

        <table class="bk-table">

            <thead>
                <tr>
                    <th>Mã Booking</th>
                    <th>Khách Hàng</th>
                    <th>Tour</th>
                    <th>Ngày Khởi Hành</th>
                    <th>Số Khách</th>
                    <th>Phân Công HDV</th>
                    <th>Trạng Thái</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($bookings): ?>

                <?php foreach ($bookings as $b): ?>

                <?php
                $bid = (int)$b['id'];
                $formId = 'bk-form-' . $bid;
                $guideId = (int)($b['guide_id'] ?? 0);
                ?>

                <tr>

                    <td class="bk-code">
                        <?= bk_h($b['booking_code']) ?>
                    </td>

                    <td>
                        <?= bk_h($b['fullname']) ?>

                        <small class="bk-phone">
                            <?= bk_h($b['phone']) ?>
                        </small>
                    </td>

                    <td>
                        <?= bk_h($b['tour_name']) ?>
                    </td>

                    <td class="bk-date">
                        <?= !empty($b['departure_date'])
                            ? date('d/m/Y', strtotime($b['departure_date']))
                            : 'Tự thỏa thuận' ?>
                    </td>

                    <td>
                        <?= (int)$b['number_people'] ?> người
                    </td>

                    <td>
                        <select
                            name="guide_id"
                            form="<?= bk_h($formId) ?>"
                            class="bk-select"
                        >
                            <option value="">
                                -- Chọn Leader/HDV --
                            </option>

                            <?php foreach ($guides as $guide): ?>

                                <option
                                    value="<?= (int)$guide['id'] ?>"
                                    <?= $guideId === (int)$guide['id']
                                        ? 'selected' : '' ?>
                                >
                                    <?= bk_h($guide['fullname']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </td>

                    <td>
                        <select
                            name="status"
                            form="<?= bk_h($formId) ?>"
                            class="bk-select"
                        >

                            <?php foreach ($statuses as $s): ?>

                                <option
                                    value="<?= bk_h($s) ?>"
                                    <?= $b['status'] === $s
                                        ? 'selected' : '' ?>
                                >
                                    <?= bk_h($s) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </td>

                    <td>
                        <form
                            id="<?= bk_h($formId) ?>"
                            method="POST"
                            class="bk-form"
                        >

                            <input type="hidden"
                                   name="csrf"
                                   value="<?= bk_h($csrf) ?>">

                            <input type="hidden"
                                   name="booking_id"
                                   value="<?= $bid ?>">

                            <button
                                class="bk-save"
                                name="update_booking"
                                value="1"
                                type="submit"
                            >
                                <i class="fa-solid fa-floppy-disk"></i>
                                Lưu
                            </button>

                        </form>
                    </td>

                </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8"
                        style="text-align:center;padding:40px;color:#64748b">
                        Không có booking nào phù hợp.
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
