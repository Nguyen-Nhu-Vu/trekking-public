<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireStaff();

$current_page = 'bookings';
$page_title = 'Quản Lý Đăng Ký Tour - VNN Travel';

if (!function_exists('bk_h')) {
    function bk_h($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

// ====================================
// TRANG THAI BOOKING
// ====================================

$statuses = [
    'Chờ xác nhận',
    'Đã xác nhận',
    'Đã thanh toán',
    'Đã hoàn thành',
    'Đã hủy'
];

$error = '';
$success = '';

if (empty($_SESSION['bookings_csrf'])) {
    $_SESSION['bookings_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['bookings_csrf'];

$filter = trim((string)($_GET['status'] ?? ''));

if ($filter !== '' && !in_array($filter, $statuses, true)) {
    $filter = '';
}

// ====================================
// LUU TRANG THAI HANG LOAT
// ====================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_all_changes'])
) {
    try {
        if (!hash_equals(
            $csrf,
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ.'
            );
        }

        $postedStatuses = $_POST['status'] ?? [];

        if (!is_array($postedStatuses)) {
            throw new RuntimeException(
                'Dữ liệu trạng thái không hợp lệ.'
            );
        }

        if (count($postedStatuses) > 1000) {
            throw new RuntimeException(
                'Quá nhiều đăng ký trong một lần lưu.'
            );
        }

        $changes = [];

        foreach ($postedStatuses as $bookingId => $newStatus) {
            $bookingId = filter_var(
                $bookingId,
                FILTER_VALIDATE_INT
            );

            if (
                $bookingId === false ||
                $bookingId <= 0 ||
                !is_string($newStatus) ||
                !in_array($newStatus, $statuses, true)
            ) {
                throw new RuntimeException(
                    'Có dữ liệu cập nhật không hợp lệ.'
                );
            }

            $changes[$bookingId] = $newStatus;
        }

        $pdo->beginTransaction();

        $checkStmt = $pdo->prepare("
            SELECT status
            FROM tour_bookings
            WHERE id = ?
            FOR UPDATE
        ");

        $updateStmt = $pdo->prepare("
            UPDATE tour_bookings
            SET status = ?
            WHERE id = ?
        ");

        $updatedCount = 0;

        foreach ($changes as $bookingId => $newStatus) {
            $checkStmt->execute([$bookingId]);

            $oldStatus = $checkStmt->fetchColumn();
            $checkStmt->closeCursor();

            if ($oldStatus === false) {
                throw new RuntimeException(
                    'Không tìm thấy booking #' . $bookingId
                );
            }

            if ((string)$oldStatus === $newStatus) {
                continue;
            }

            $updateStmt->execute([
                $newStatus,
                $bookingId
            ]);

            $updatedCount++;
        }

        $pdo->commit();

        $_SESSION['bookings_notice'] = $updatedCount > 0
            ? 'Đã lưu thành công ' . $updatedCount . ' thay đổi.'
            : 'Không có trạng thái nào thay đổi.';

        $redirectFilter = trim(
            (string)($_POST['current_filter'] ?? '')
        );

        $redirect = 'index.php';

        if (
            $redirectFilter !== '' &&
            in_array($redirectFilter, $statuses, true)
        ) {
            $redirect .= '?status=' . rawurlencode($redirectFilter);
        }

        header('Location: ' . $redirect, true, 303);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Booking update: ' . $e->getMessage());

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể lưu thay đổi.';
    }
}

// ====================================
// THONG BAO
// ====================================

if (!empty($_SESSION['bookings_notice'])) {
    $success = $_SESSION['bookings_notice'];
    unset($_SESSION['bookings_notice']);
}

// ====================================
// LAY BOOKING VA LEADER CO DINH
// ====================================

$params = [];

$sql = "
    SELECT
        b.id,
        b.booking_code,
        b.user_id,
        b.tour_id,
        b.fullname,
        b.phone,
        b.number_people,
        b.departure_date,
        b.status,

        COALESCE(
            NULLIF(TRIM(b.fullname), ''),
            NULLIF(TRIM(u.fullname), ''),
            'Chưa có tên'
        ) AS customer_name,

        t.title AS tour_name,

        l.id AS leader_id,
        l.fullname AS leader_name

    FROM tour_bookings b

    INNER JOIN tours t
        ON t.id = b.tour_id

    LEFT JOIN users u
        ON u.id = b.user_id

    LEFT JOIN trekking_leaders l
        ON l.id = t.leader_id
";

if ($filter !== '') {
    $sql .= " WHERE b.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalBookings = count($bookings);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title><?= bk_h($page_title) ?></title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">

    <style>
        /* ===========================
           TRANG QUAN LY BOOKING
           =========================== */

        .bk-main {
            flex: 1;
            min-width: 0;
            background: #f3f6fa;
        }

        .bk-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .bk-content {
            padding: 22px;
        }

        .bk-alert {
            padding: 14px 17px;
            border-radius: 10px;
            margin-bottom: 17px;
            font-size: 13px;
        }

        .bk-alert.success {
            background: #dcfce7;
            color: #166534;
        }

        .bk-alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ===========================
           THANH CONG CU
           =========================== */

        .bk-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 14px 18px;
            margin-bottom: 17px;
            background: #eef3f9;
            border: 1px solid #e0e7f0;
            border-radius: 13px;
        }

        .bk-total {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 14px;
            color: #64748b;
        }

        .bk-total i {
            color: #166534;
        }

        .bk-save {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #166534;
            color: white;
            padding: 11px 18px;
            border: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .bk-save:hover {
            background: #14532d;
        }

        /* ===========================
           BANG CAN DOI CAC COT
           =========================== */

        .bk-table-wrap {
            width: 100%;
            overflow-x: auto;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
        }

        .bk-table {
            width: 100%;
            min-width: 1350px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .bk-col-code {
            width: 17%;
        }

        .bk-col-customer {
            width: 20%;
        }

        .bk-col-tour {
            width: 19%;
        }

        .bk-col-date {
            width: 12%;
        }

        .bk-col-people {
            width: 9%;
        }

        .bk-col-leader {
            width: 12%;
        }

        .bk-col-status {
            width: 11%;
        }

        .bk-table th {
            padding: 17px 13px;
            text-align: left;
            color: #0f172a;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .bk-table td {
            padding: 18px 13px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 13px;
        }

        .bk-table tbody tr:hover td {
            background: #f8fafc;
        }

        /* ===========================
           MA BOOKING: KHONG XUONG DONG
           =========================== */

        .bk-code {
            display: block;
            color: #17345f;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: -0.2px;
            white-space: nowrap;
        }

        /* ===========================
           TEN KHACH HANG
           =========================== */

        .bk-customer-name {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
            overflow-wrap: break-word;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
            color: #0f172a;
        }

        .bk-phone {
            display: block;
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
            white-space: nowrap;
        }

        /* ===========================
           TEN TOUR
           =========================== */

        .bk-tour-name {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
            overflow-wrap: break-word;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.5;
        }

        /* ===========================
           NGAY KHOI HANH
           =========================== */

        .bk-date {
            color: #ea580c;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        /* ===========================
           SO KHACH
           =========================== */

        .bk-people {
            font-size: 13px;
            white-space: nowrap;
        }

        .bk-people strong {
            color: #0f172a;
        }

        /* ===========================
           HDV: CHI HIEN THI TEN
           =========================== */

        .bk-leader-name {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
            color: #166534;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            line-height: 1.5;
        }

        .bk-leader-name:hover {
            text-decoration: underline;
        }

        .bk-leader-empty {
            color: #94a3b8;
            font-size: 12px;
        }

        /* ===========================
           TRANG THAI
           =========================== */

        .bk-status-select {
            width: 100%;
            padding: 10px 7px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            color: #334155;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
        }

        .bk-status-select.changed {
            background: #f0fdf4;
            border-color: #16a34a;
            color: #166534;
            font-weight: 800;
        }

        .bk-empty {
            text-align: center;
            padding: 45px 15px !important;
            color: #94a3b8 !important;
        }

        /* ===========================
           MAN HINH NHO
           =========================== */

        @media (max-width: 1200px) {
            .bk-content {
                padding: 14px;
            }
        }

        @media (max-width: 700px) {
            .bk-content {
                padding: 10px;
            }

            .bk-table th,
            .bk-table td {
                padding: 12px 9px;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main bk-main">

    <div class="admin-topbar bk-topbar">

        <strong>
            <i class="fa-solid fa-person-hiking"></i>
            QUẢN LÝ ĐĂNG KÝ TOUR TREKKING
        </strong>

        <form method="GET">
            <select
                name="status"
                class="form-control"
                onchange="this.form.submit()"
            >
                <option value="">
                    Tất cả trạng thái
                </option>

                <?php foreach ($statuses as $status): ?>
                    <option
                        value="<?= bk_h($status) ?>"
                        <?= $filter === $status ? 'selected' : '' ?>
                    >
                        <?= bk_h($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

    </div>

    <div class="bk-content">

        <?php if ($success): ?>
            <div class="bk-alert success" role="status">
                <i class="fa-solid fa-circle-check"></i>
                <?= bk_h($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bk-alert error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= bk_h($error) ?>
            </div>
        <?php endif; ?>

        <!-- FORM LUU TRANG THAI HANG LOAT -->

        <form method="POST" id="bkBulkForm">

            <input
                type="hidden"
                name="csrf"
                value="<?= bk_h($csrf) ?>"
            >

            <input
                type="hidden"
                name="current_filter"
                value="<?= bk_h($filter) ?>"
            >

            <div class="bk-toolbar">

                <div class="bk-total">
                    <i class="fa-solid fa-ticket"></i>
                    <span>
                        <?= $totalBookings ?> đăng ký tour
                    </span>
                </div>

                <button
                    type="submit"
                    name="save_all_changes"
                    value="1"
                    class="bk-save"
                    id="bkSaveButton"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Lưu thay đổi
                </button>

            </div>

            <!-- BANG BOOKING -->

            <div class="bk-table-wrap">

                <table class="bk-table">

                    <colgroup>
                        <col class="bk-col-code">
                        <col class="bk-col-customer">
                        <col class="bk-col-tour">
                        <col class="bk-col-date">
                        <col class="bk-col-people">
                        <col class="bk-col-leader">
                        <col class="bk-col-status">
                    </colgroup>

                    <thead>
                        <tr>
                            <th>Mã Booking</th>
                            <th>Khách Hàng</th>
                            <th>Tour</th>
                            <th>Ngày Khởi Hành</th>
                            <th>Số Khách</th>
                            <th>HDV</th>
                            <th>Trạng Thái</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($bookings): ?>

                            <?php foreach ($bookings as $booking): ?>

                                <?php
                                $bookingId = (int)$booking['id'];

                                $bookingCode = (string)(
                                    $booking['booking_code'] ?? ''
                                );

                                $customerName = trim(
                                    (string)(
                                        $booking['customer_name'] ?? ''
                                    )
                                );

                                $phone = trim(
                                    (string)(
                                        $booking['phone'] ?? ''
                                    )
                                );

                                $numberPeople = (int)(
                                    $booking['number_people'] ?? 0
                                );

                                $tourName = (string)(
                                    $booking['tour_name'] ?? ''
                                );

                                $leaderName = trim(
                                    (string)(
                                        $booking['leader_name'] ?? ''
                                    )
                                );

                                $leaderId = (int)(
                                    $booking['leader_id'] ?? 0
                                );

                                $leaderLink =
                                    rtrim(BASE_URL, '/') .
                                    '/pages/leader-detail.php?id=' .
                                    $leaderId;
                                ?>

                                <tr>

                                    <!-- MA BOOKING -->

                                    <td>
                                        <span
                                            class="bk-code"
                                            title="<?= bk_h($bookingCode) ?>"
                                        >
                                            <?= bk_h($bookingCode) ?>
                                        </span>
                                    </td>

                                    <!-- KHACH HANG -->

                                    <td>

                                        <span
                                            class="bk-customer-name"
                                            title="<?= bk_h($customerName) ?>"
                                        >
                                            <?= bk_h(
                                                $customerName !== ''
                                                    ? $customerName
                                                    : 'Chưa có tên'
                                            ) ?>
                                        </span>

                                        <?php if ($phone !== ''): ?>
                                            <span class="bk-phone">
                                                <?= bk_h($phone) ?>
                                            </span>
                                        <?php endif; ?>

                                    </td>

                                    <!-- TOUR -->

                                    <td>
                                        <span
                                            class="bk-tour-name"
                                            title="<?= bk_h($tourName) ?>"
                                        >
                                            <?= bk_h($tourName) ?>
                                        </span>
                                    </td>

                                    <!-- NGAY KHOI HANH -->

                                    <td>
                                        <span class="bk-date">
                                            <?php if (
                                                !empty($booking['departure_date'])
                                            ): ?>

                                                <?= date(
                                                    'd/m/Y',
                                                    strtotime(
                                                        $booking['departure_date']
                                                    )
                                                ) ?>

                                            <?php else: ?>

                                                Chưa chọn

                                            <?php endif; ?>
                                        </span>
                                    </td>

                                    <!-- SO KHACH -->

                                    <td>
                                        <span class="bk-people">
                                            <strong>
                                                <?= $numberPeople ?>
                                            </strong>
                                            người
                                        </span>
                                    </td>

                                    <!-- HDV: KHONG HIEN KINH NGHIEM -->

                                    <td>

                                        <?php if (
                                            $leaderName !== '' &&
                                            $leaderId > 0
                                        ): ?>

                                            <a
                                                class="bk-leader-name"
                                                href="<?= bk_h($leaderLink) ?>"
                                                title="<?= bk_h($leaderName) ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <?= bk_h($leaderName) ?>
                                            </a>

                                        <?php else: ?>

                                            <span class="bk-leader-empty">
                                                Chưa phân công
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- TRANG THAI -->

                                    <td>
                                        <select
                                            name="status[<?= $bookingId ?>]"
                                            class="bk-status-select"
                                            data-original="<?= bk_h(
                                                $booking['status']
                                            ) ?>"
                                        >

                                            <?php foreach (
                                                $statuses as $status
                                            ): ?>

                                                <option
                                                    value="<?= bk_h($status) ?>"
                                                    <?= $booking['status'] === $status
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= bk_h($status) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="bk-empty">
                                    Không có đăng ký tour phù hợp.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </form>

    </div>

</main>

<script>
(function() {
    const selects = document.querySelectorAll(
        '#bkBulkForm .bk-status-select'
    );

    const saveButton = document.getElementById(
        'bkSaveButton'
    );

    if (!saveButton) return;

    function updateChangedCount() {
        let changed = 0;

        selects.forEach(function(select) {
            const isChanged =
                select.value !== select.dataset.original;

            select.classList.toggle('changed', isChanged);

            if (isChanged) {
                changed++;
            }
        });

        saveButton.innerHTML = changed > 0
            ? '<i class="fa-solid fa-floppy-disk"></i> Lưu ' +
                changed + ' thay đổi'
            : '<i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi';
    }

    selects.forEach(function(select) {
        select.addEventListener(
            'change',
            updateChangedCount
        );
    });

    updateChangedCount();
})();
</script>

</body>
</html>
