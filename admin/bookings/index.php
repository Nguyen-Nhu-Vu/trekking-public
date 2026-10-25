<?php
// VNN Travel - Quan ly dang ky tour
// Chi phan cong HDV la nhan vien co ten ca nhan.

ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

requireStaff();

$current_page = 'bookings';
$page_title = 'Quản Lý Đăng Ký Tour - VNN Travel';

function bk_h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Loai ten tai khoan la chuc vu / tai khoan he thong.
// Khong tu tao ten nguoi hoac tu suy ra vai tro.
function bk_person_name($name) {
    $name = trim((string)$name);

    if ($name === '') {
        return false;
    }

    $normalized = mb_strtolower($name, 'UTF-8');

    $blocked = [
        'admin',
        'administrator',
        'manager',
        'quản trị',
        'quản lý',
        'điều hành',
        'nhân viên',
        'staff',
        'hệ thống',
        'system',
        'test',
        'demo',
        'tài khoản'
    ];

    foreach ($blocked as $word) {
        if (mb_strpos($normalized, $word) !== false) {
            return false;
        }
    }

    // Ten nguoi thong thuong co it nhat hai tu.
    $words = preg_split('/\s+/u', $name);

    if (count($words) < 2) {
        return false;
    }

    // Khong hien tai khoan co ky hieu / so ma nhan vien.
    if (preg_match('/[0-9@()]/u', $name)) {
        return false;
    }

    return true;
}

$allowedStatuses = [
    'Chờ xác nhận',
    'Đã xác nhận',
    'Đã thanh toán',
    'Đã hoàn thành',
    'Đã hủy'
];

$error = '';
$success = '';

if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(16));
}

// ====================================
// LAY DANH SACH NHAN VIEN HDV
// ====================================

// Khong lay manager va admin.
// Chi lay nguoi co role staff va ten ca nhan.
$stmt = $pdo->query("
    SELECT id, fullname
    FROM users
    WHERE role = 'staff'
    ORDER BY fullname ASC
");

$staffUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$guides = [];

foreach ($staffUsers as $staff) {
    if (bk_person_name($staff['fullname'])) {
        $guides[] = $staff;
    }
}

$validGuideIds = array_map(
    'intval',
    array_column($guides, 'id')
);

// ====================================
// CAP NHAT BOOKING
// ====================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_booking'])
) {
    $token = (string)($_POST['csrf'] ?? '');

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = trim((string)($_POST['status'] ?? ''));

    $guideRaw = trim((string)($_POST['guide_id'] ?? ''));
    $guideId = $guideRaw === '' ? null : (int)$guideRaw;

    if (
        !hash_equals(
            (string)$_SESSION['booking_csrf'],
            $token
        )
    ) {
        $error = 'Phiên cập nhật không hợp lệ. Vui lòng tải lại trang.';

    } elseif ($bookingId <= 0) {
        $error = 'Mã đăng ký tour không hợp lệ.';

    } elseif (!in_array($newStatus, $allowedStatuses, true)) {
        $error = 'Trạng thái đăng ký không hợp lệ.';

    } elseif (
        $guideId !== null
        && !in_array($guideId, $validGuideIds, true)
    ) {
        $error = 'Chỉ được phân công nhân viên HDV hợp lệ, không được chọn tài khoản quản lý.';

    } else {
        try {
            $check = $pdo->prepare("
                SELECT id
                FROM tour_bookings
                WHERE id = ?
                LIMIT 1
            ");

            $check->execute([$bookingId]);

            if (!$check->fetchColumn()) {
                throw new RuntimeException(
                    'Không tìm thấy đơn đăng ký tour.'
                );
            }

            $update = $pdo->prepare("
                UPDATE tour_bookings
                SET status = ?, guide_id = ?
                WHERE id = ?
            ");

            $update->execute([
                $newStatus,
                $guideId,
                $bookingId
            ]);

            header(
                'Location: index.php?msg=updated',
                true,
                303
            );
            exit;

        } catch (Throwable $e) {
            error_log(
                'Booking assignment: ' . $e->getMessage()
            );

            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể cập nhật đăng ký. Vui lòng thử lại.';
        }
    }
}

// ====================================
// BO LOC TRANG THAI
// ====================================

$filterStatus = trim((string)($_GET['status'] ?? ''));

if (
    $filterStatus !== ''
    && $filterStatus !== 'all'
    && !in_array($filterStatus, $allowedStatuses, true)
) {
    $filterStatus = '';
}

$sql = "
    SELECT
        b.*,
        t.title AS tour_name,
        u.fullname AS guide_name
    FROM tour_bookings b
    JOIN tours t ON b.tour_id = t.id
    LEFT JOIN users u ON b.guide_id = u.id
";

$params = [];

if ($filterStatus !== '' && $filterStatus !== 'all') {
    $sql .= " WHERE b.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (($_GET['msg'] ?? '') === 'updated') {
    $success = 'Đã cập nhật thông tin đăng ký tour thành công!';
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

    <title><?= bk_h($page_title) ?></title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/admin.css"
    >

    <style>
        .booking-top-title {
            color:#0f172a;
            font-weight:800;
        }

        .booking-filter {
            display:flex;
            align-items:center;
            flex-wrap:wrap;
            gap:10px;
        }

        .booking-filter label {
            font-size:14px;
            color:#475569;
            font-weight:700;
        }

        .booking-table-wrap {
            background:white;
            border:1px solid #e2e8f0;
            border-radius:14px;
            overflow-x:auto;
            box-shadow:0 5px 20px rgba(0,0,0,.04);
        }

        .booking-table {
            width:100%;
            min-width:1120px;
            border-collapse:collapse;
        }

        .booking-table th {
            background:#f8fafc;
            color:#0f172a;
            font-size:14px;
            font-weight:800;
            padding:18px 15px;
            text-align:left;
            border-bottom:1px solid #e2e8f0;
        }

        .booking-table td {
            padding:18px 15px;
            border-bottom:1px solid #e2e8f0;
            vertical-align:middle;
            font-size:14px;
        }

        .booking-table tbody tr:hover {
            background:#f8fafc;
        }

        .booking-code {
            color:#1e3a5f;
            font-weight:800;
            white-space:nowrap;
        }

        .booking-phone {
            display:block;
            color:#64748b;
            font-size:13px;
            margin-top:6px;
        }

        .booking-date {
            color:#ea580c;
            font-weight:800;
            white-space:nowrap;
        }

        .booking-select {
            width:100%;
            max-width:190px;
            padding:9px 10px;
            border:1px solid #cbd5e1;
            border-radius:9px;
            background:white;
            color:#0f172a;
            font:inherit;
            font-size:13px;
        }

        .booking-select:focus {
            outline:none;
            border-color:#f97316;
            box-shadow:0 0 0 3px #ffedd5;
        }

        .booking-save {
            border:0;
            background:#166534;
            color:white;
            padding:10px 16px;
            border-radius:9px;
            font-weight:800;
            cursor:pointer;
            white-space:nowrap;
        }

        .booking-save:hover {
            background:#15803d;
        }

        .booking-alert {
            border-radius:10px;
            padding:15px 18px;
            margin-bottom:20px;
            font-size:14px;
            line-height:1.7;
        }

        .booking-alert.success {
            background:#dcfce7;
            color:#166534;
        }

        .booking-alert.error {
            background:#fee2e2;
            color:#991b1b;
        }

        .booking-empty {
            text-align:center;
            color:#64748b;
            padding:35px !important;
        }

        .booking-guide-note {
            color:#64748b;
            font-size:12px;
            margin-top:6px;
            line-height:1.5;
        }

        .booking-guide-warning {
            color:#b45309;
            font-size:12px;
            margin-top:6px;
        }

        .booking-help {
            background:#eff6ff;
            color:#1e40af;
            border:1px solid #bfdbfe;
            border-radius:10px;
            padding:14px 18px;
            margin-bottom:20px;
            font-size:14px;
            line-height:1.7;
        }

        .booking-cell-form {
            margin:0;
        }

        @media(max-width:700px) {
            .booking-top-title {
                font-size:14px;
            }

            .booking-table th,
            .booking-table td {
                padding:12px;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main">

    <div class="admin-topbar">

        <strong class="booking-top-title">
            <i class="fa-solid fa-person-hiking"></i>
            QUẢN LÝ ĐĂNG KÝ TOUR TREKKING
        </strong>

        <form method="GET" class="booking-filter">

            <label for="statusFilter">
                <i class="fa-solid fa-filter"></i>
                Lọc trạng thái:
            </label>

            <select
                id="statusFilter"
                name="status"
                class="form-control"
                style="min-width:170px"
                onchange="this.form.submit()"
            >

                <option value="all">
                    -- Hiện tất cả trạng thái --
                </option>

                <?php foreach ($allowedStatuses as $status): ?>

                    <option
                        value="<?= bk_h($status) ?>"
                        <?= $filterStatus === $status
                            ? 'selected' : '' ?>
                    >
                        <?= bk_h($status) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </div>

    <div class="admin-content">

        <?php if ($success !== ''): ?>

            <div class="booking-alert success" role="status">
                <i class="fa-solid fa-circle-check"></i>
                <?= bk_h($success) ?>
            </div>

        <?php endif; ?>

        <?php if ($error !== ''): ?>

            <div class="booking-alert error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= bk_h($error) ?>
            </div>

        <?php endif; ?>

        <?php if (!$guides): ?>

            <div class="booking-help">
                <i class="fa-solid fa-circle-info"></i>

                Chưa có hướng dẫn viên có tên cá nhân
                thuộc nhóm nhân viên trong hệ thống.
                Vui lòng thêm tài khoản nhân viên
                hướng dẫn viên trước khi phân công.
            </div>

        <?php endif; ?>

        <div class="booking-table-wrap">

            <table class="booking-table">

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
                        $bookingId = (int)$b['id'];
                        $formId = 'booking-form-' . $bookingId;

                        $currentGuide = isset($b['guide_id'])
                            ? (int)$b['guide_id']
                            : 0;

                        $guideValid = $currentGuide === 0
                            || in_array(
                                $currentGuide,
                                $validGuideIds,
                                true
                            );
                        ?>

                        <tr>

                            <td>
                                <strong class="booking-code">
                                    <?= bk_h($b['booking_code']) ?>
                                </strong>
                            </td>

                            <td>
                                <div>
                                    <?= bk_h($b['fullname']) ?>
                                </div>

                                <small class="booking-phone">
                                    <?= bk_h($b['phone']) ?>
                                </small>
                            </td>

                            <td>
                                <?= bk_h($b['tour_name']) ?>
                            </td>

                            <td>
                                <strong class="booking-date">
                                    <?= !empty($b['departure_date'])
                                        ? date(
                                            'd/m/Y',
                                            strtotime($b['departure_date'])
                                        )
                                        : 'Tự thỏa thuận' ?>
                                </strong>
                            </td>

                            <td>
                                <?= (int)$b['number_people'] ?> người
                            </td>

                            <td>
                                <select
                                    form="<?= bk_h($formId) ?>"
                                    name="guide_id"
                                    class="booking-select"
                                    aria-label="Chọn hướng dẫn viên"
                                >

                                    <option value="">
                                        -- Chọn Leader/HDV --
                                    </option>

                                    <?php foreach ($guides as $guide): ?>

                                        <option
                                            value="<?= (int)$guide['id'] ?>"
                                            <?= $currentGuide ===
                                                (int)$guide['id']
                                                ? 'selected' : '' ?>
                                        >
                                            <?= bk_h($guide['fullname']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <?php if (!$guideValid): ?>

                                    <div class="booking-guide-warning">
                                        Phân công cũ không hợp lệ.
                                        Hãy chọn lại HDV.
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>
                                <select
                                    form="<?= bk_h($formId) ?>"
                                    name="status"
                                    class="booking-select"
                                    aria-label="Trạng thái đăng ký"
                                >

                                    <?php foreach ($allowedStatuses as $status): ?>

                                        <option
                                            value="<?= bk_h($status) ?>"
                                            <?= $b['status'] === $status
                                                ? 'selected' : '' ?>
                                        >
                                            <?= bk_h($status) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </td>

                            <td>
                                <form
                                    id="<?= bk_h($formId) ?>"
                                    method="POST"
                                    class="booking-cell-form"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf"
                                        value="<?= bk_h(
                                            $_SESSION['booking_csrf']
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="booking_id"
                                        value="<?= $bookingId ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="update_booking"
                                        value="1"
                                        class="booking-save"
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
                        <td colspan="8" class="booking-empty">
                            Không có đăng ký tour nào phù hợp
                            với bộ lọc hiện tại.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p class="booking-guide-note" style="margin-top:16px">
            Danh sách phân công chỉ hiển thị nhân viên có tên
            cá nhân phù hợp. Tài khoản quản lý và quản trị
            hệ thống không được phép nhận phân công HDV.
        </p>

    </div>

</main>

</body>
</html>
