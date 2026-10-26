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

// ========================================
// TRANG THAI BOOKING
// ========================================

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

// ========================================
// BO LOC
// ========================================

$filter = trim((string)($_GET['status'] ?? ''));

if ($filter !== '' && !in_array($filter, $statuses, true)) {
    $filter = '';
}

// ========================================
// LUU NHIEU TRANG THAI CUNG LUC
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_all_changes'])
) {
    try {
        $postedCsrf = (string)($_POST['csrf'] ?? '');

        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
            );
        }

        $postedStatuses = $_POST['status'] ?? [];

        if (!is_array($postedStatuses)) {
            throw new RuntimeException(
                'Danh sách trạng thái không hợp lệ.'
            );
        }

        // Kiem tra toan bo du lieu truoc khi cap nhat
        $updates = [];

        foreach ($postedStatuses as $bookingId => $newStatus) {
            $id = filter_var(
                $bookingId,
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id <= 0) {
                throw new RuntimeException(
                    'Có mã booking không hợp lệ.'
                );
            }

            if (!is_string($newStatus)) {
                throw new RuntimeException(
                    'Dữ liệu trạng thái không hợp lệ.'
                );
            }

            $newStatus = trim($newStatus);

            if (!in_array($newStatus, $statuses, true)) {
                throw new RuntimeException(
                    'Có trạng thái không được hỗ trợ.'
                );
            }

            $updates[$id] = $newStatus;
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

        foreach ($updates as $bookingId => $newStatus) {
            $checkStmt->execute([$bookingId]);

            $oldStatus = $checkStmt->fetchColumn();
            $checkStmt->closeCursor();

            if ($oldStatus === false) {
                throw new RuntimeException(
                    'Không tìm thấy booking #' . $bookingId
                );
            }

            // Neu khong thay doi thi giu nguyen
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
            ? "Đã lưu thành công {$updatedCount} thay đổi."
            : 'Không có trạng thái nào thay đổi.';

        $postedFilter = trim(
            (string)($_POST['current_filter'] ?? '')
        );

        $redirect = 'index.php';

        if (
            $postedFilter !== ''
            && in_array($postedFilter, $statuses, true)
        ) {
            $redirect .= '?status=' .
                rawurlencode($postedFilter);
        }

        header('Location: ' . $redirect, true, 303);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Bulk booking update: ' . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể lưu trạng thái. Vui lòng thử lại.';
    }
}

// ========================================
// THONG BAO
// ========================================

if (!empty($_SESSION['bookings_notice'])) {
    $success = $_SESSION['bookings_notice'];
    unset($_SESSION['bookings_notice']);
}

// ========================================
// LAY BOOKING + LEADER CO DINH
// ========================================

$params = [];

$sql = "
    SELECT
        b.id,
        b.booking_code,
        b.fullname,
        b.phone,
        b.tour_id,
        b.departure_date,
        b.number_people,
        b.status,

        t.title AS tour_name,

        l.id AS leader_id,
        l.fullname AS leader_name,
        l.experience_years AS leader_experience

    FROM tour_bookings b

    INNER JOIN tours t
        ON t.id = b.tour_id

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
        /* =========================================
           TRANG QUAN LY BOOKING
           ========================================= */

        .bk-main {
            min-width: 0;
            flex: 1;
            background: #f3f6fa;
        }

        .bk-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .bk-content {
            padding: 24px;
        }

        .bk-alert {
            padding: 13px 17px;
            margin-bottom: 17px;
            border-radius: 10px;
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

        /* =========================================
           THANH CONG CU
           ========================================= */

        .bk-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 17px;
            padding: 15px 19px;
            background: #eef3f9;
            border: 1px solid #e0e7f0;
            border-radius: 13px;
        }

        .bk-total {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #64748b;
            font-size: 14px;
        }

        .bk-total i {
            color: #166534;
            font-size: 16px;
        }

        .bk-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 9px;
            padding: 12px 19px;
            background: #166534;
            color: white;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .bk-save:hover {
            background: #14532d;
        }

        .bk-save:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        /* =========================================
           BANG - CAN DOI CAC COT
           ========================================= */

        .bk-table-wrap {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow-x: auto;
        }

        .bk-table {
            width: 100%;
            min-width: 1060px;
            table-layout: fixed;
            border-collapse: collapse;
        }

        /* Ty le 7 cot */

        .bk-col-code {
            width: 15%;
        }

        .bk-col-customer {
            width: 19%;
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

        .bk-col-guide {
            width: 14%;
        }

        .bk-col-status {
            width: 12%;
        }

        .bk-table th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
            text-align: left;
            padding: 17px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .bk-table td {
            padding: 17px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #334155;
            font-size: 13px;
        }

        .bk-table tbody tr:hover td {
            background: #f8fafc;
        }

        /* =========================================
           MA BOOKING
           ========================================= */

        .bk-code {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
            overflow-wrap: anywhere;
            color: #17345f;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.6;
        }

        /* =========================================
           KHACH HANG
           ========================================= */

        .bk-customer {
            min-width: 0;
        }

        .bk-customer-name {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
            overflow-wrap: anywhere;
            color: #1e293b;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.5;
        }

        .bk-customer-phone {
            display: block;
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
            white-space: nowrap;
        }

        /* =========================================
           TEN TOUR
           ========================================= */

        .bk-tour-name {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
            overflow-wrap: anywhere;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.5;
        }

        /* =========================================
           NGAY KHOI HANH
           ========================================= */

        .bk-date {
            color: #ea580c;
            font-weight: 800;
            white-space: nowrap;
            font-size: 13px;
        }

        /* =========================================
           SO KHACH
           ========================================= */

        .bk-people {
            font-size: 13px;
            white-space: nowrap;
            color: #334155;
        }

        .bk-people strong {
            color: #0f172a;
        }

        /* =========================================
           HDV CO DINH
           ========================================= */

        .bk-leader-name {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
            overflow-wrap: anywhere;
            color: #166534;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.5;
        }

        .bk-leader-years {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 11px;
            white-space: nowrap;
        }

        .bk-leader-empty {
            color: #94a3b8;
            font-size: 12px;
        }

        /* =========================================
           TRANG THAI
           ========================================= */

        .bk-status-select {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
            padding: 11px 7px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            color: #334155;
            font-size: 12px;
            font-family: inherit;
            cursor: pointer;
        }

        .bk-status-select.changed {
            border-color: #16a34a;
            background: #f0fdf4;
            color: #166534;
            font-weight: 800;
        }

        .bk-empty {
            text-align: center;
            padding: 45px 15px !important;
            color: #94a3b8 !important;
        }

        /* =========================================
           RESPONSIVE
           ========================================= */

        @media(max-width: 1200px) {
            .bk-content {
                padding: 16px;
            }

            .bk-table {
                min-width: 1060px;
            }
        }

        @media(max-width: 700px) {
            .bk-content {
                padding: 10px;
            }

            .bk-toolbar {
                padding: 12px;
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

    <!-- THANH TIEU DE -->

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

    <!-- NOI DUNG -->

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

        <!-- FORM LUU HANG LOAT -->

        <form
            method="POST"
            id="bkBulkForm"
        >

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

            <!-- THANH CONG CU -->

            <div class="bk-toolbar">

                <div class="bk-total">
                    <i class="fa-solid fa-ticket"></i>
                    <span>
                        <?= $totalBookings ?>
                        đăng ký tour
                    </span>
                </div>

                <button
                    class="bk-save"
                    type="submit"
                    name="save_all_changes"
                    value="1"
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
                        <col class="bk-col-guide">
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

                                $customerName = trim(
                                    (string)(
                                        $booking['fullname'] ?? ''
                                    )
                                );

                                $phone = (string)(
                                    $booking['phone'] ?? ''
                                );

                                $people = (int)(
                                    $booking['number_people'] ?? 0
                                );

                                $leaderName = trim(
                                    (string)(
                                        $booking['leader_name'] ?? ''
                                    )
                                );

                                $leaderYears = (int)(
                                    $booking['leader_experience'] ?? 0
                                );
                                ?>

                                <tr>

                                    <!-- MA BOOKING -->

                                    <td>
                                        <span
                                            class="bk-code"
                                            title="<?= bk_h(
                                                $booking['booking_code']
                                            ) ?>"
                                        >
                                            <?= bk_h(
                                                $booking['booking_code']
                                            ) ?>
                                        </span>
                                    </td>

                                    <!-- KHACH HANG -->

                                    <td>
                                        <div class="bk-customer">

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

                                            <span class="bk-customer-phone">
                                                <?= bk_h($phone) ?>
                                            </span>

                                        </div>
                                    </td>

                                    <!-- TEN TOUR -->

                                    <td>
                                        <span
                                            class="bk-tour-name"
                                            title="<?= bk_h(
                                                $booking['tour_name']
                                            ) ?>"
                                        >
                                            <?= bk_h(
                                                $booking['tour_name']
                                            ) ?>
                                        </span>
                                    </td>

                                    <!-- NGAY KHOI HANH -->

                                    <td>
                                        <span class="bk-date">

                                            <?php if (!empty(
                                                $booking['departure_date']
                                            )): ?>

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
                                                <?= $people ?>
                                            </strong>
                                            người
                                        </span>
                                    </td>

                                    <!-- LEADER -->

                                    <td>

                                        <?php if ($leaderName !== ''): ?>

                                            <span
                                                class="bk-leader-name"
                                                title="<?= bk_h(
                                                    $leaderName
                                                ) ?>"
                                            >
                                                <?= bk_h($leaderName) ?>
                                            </span>

                                            <?php if ($leaderYears > 0): ?>

                                                <small class="bk-leader-years">
                                                    <?= $leaderYears ?>
                                                    năm kinh nghiệm
                                                </small>

                                            <?php endif; ?>

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
                                                    value="<?= bk_h(
                                                        $status
                                                    ) ?>"
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
                                    Không có đăng ký nào phù hợp.
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

    function updateChanged() {
        let changed = 0;

        selects.forEach(function(select) {
            const isChanged =
                select.value !== select.dataset.original;

            select.classList.toggle(
                'changed',
                isChanged
            );

            if (isChanged) {
                changed++;
            }
        });

        if (changed > 0) {
            saveButton.innerHTML =
                '<i class="fa-solid fa-floppy-disk"></i> ' +
                'Lưu ' + changed + ' thay đổi';
        } else {
            saveButton.innerHTML =
                '<i class="fa-solid fa-floppy-disk"></i> ' +
                'Lưu thay đổi';
        }
    }

    selects.forEach(function(select) {
        select.addEventListener(
            'change',
            updateChanged
        );
    });

    updateChanged();
})();
</script>

</body>
</html>
