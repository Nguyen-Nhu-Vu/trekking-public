<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireStaff();

$current_page = 'bookings';
$page_title = 'Quản Lý Đăng Ký Tour - VNN Travel';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!function_exists('vnnBkH')) {
    function vnnBkH($value) {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('vnnBkValue')) {
    function vnnBkValue(array $row, array $keys, $default = '') {
        foreach ($keys as $key) {
            if (
                array_key_exists($key, $row) &&
                $row[$key] !== null &&
                trim((string)$row[$key]) !== ''
            ) {
                return $row[$key];
            }
        }

        return $default;
    }
}

if (!function_exists('vnnBkColumns')) {
    function vnnBkColumns(PDO $pdo, string $table): array {
        $allowed = [
            'tour_bookings',
            'tours',
            'users'
        ];

        if (!in_array($table, $allowed, true)) {
            return [];
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_column($rows, 'Field');
    }
}

if (!function_exists('vnnBkDate')) {
    function vnnBkDate($value): string {
        if (empty($value)) {
            return '—';
        }

        $time = strtotime((string)$value);

        if ($time === false) {
            return (string)$value;
        }

        return date('d/m/Y', $time);
    }
}

if (!function_exists('vnnBkPeople')) {
    function vnnBkPeople(array $booking): int {
        $fields = [
            'number_of_people',
            'num_people',
            'people_count',
            'guest_count',
            'guests',
            'participants',
            'quantity',
            'so_nguoi',
            'number_of_guests',
            'adults',
            'total_people'
        ];

        foreach ($fields as $field) {
            if (
                isset($booking[$field]) &&
                is_numeric($booking[$field])
            ) {
                return max(0, (int)$booking[$field]);
            }
        }

        return 0;
    }
}

if (!function_exists('vnnBkAudit')) {
    function vnnBkAudit(
        PDO $pdo,
        int $bookingId,
        string $code,
        string $oldStatus,
        string $newStatus
    ): void {
        try {
            $actorId = (int)($_SESSION['user_id'] ?? 0);
            $actorName = trim((string)(
                $_SESSION['fullname'] ?? ''
            ));

            if ($actorName === '') {
                $stmt = $pdo->prepare(
                    "SELECT fullname FROM users WHERE id = ? LIMIT 1"
                );

                $stmt->execute([$actorId]);
                $actorName = (string)(
                    $stmt->fetchColumn() ?: 'Nhân viên'
                );
            }

            $stmt = $pdo->prepare("
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $actorId ?: null,
                $actorName,
                'transactions',
                'Cập nhật trạng thái đăng ký tour',
                'tour_bookings',
                (string)$bookingId,
                $code . ' | ' . $oldStatus,
                $code . ' | ' . $newStatus
            ]);

        } catch (Throwable $e) {
            // Nhat ky chua hoan thien khong duoc
            // lam that bai thao tac cap nhat booking.
            error_log(
                'Booking audit: ' . $e->getMessage()
            );
        }
    }
}

// ========================================
// DANH SACH TRANG THAI
// ========================================

$statuses = [
    'Chờ xác nhận',
    'Đã xác nhận',
    'Đã thanh toán',
    'Đã hoàn thành',
    'Đã hủy'
];

$filter = trim((string)(
    $_GET['status'] ??
    $_POST['current_filter'] ??
    ''
));

if (
    $filter !== '' &&
    !in_array($filter, $statuses, true)
) {
    $filter = '';
}

// ========================================
// CSRF
// ========================================

if (empty($_SESSION['vnn_booking_csrf'])) {
    $_SESSION['vnn_booking_csrf'] =
        bin2hex(random_bytes(32));
}

$csrf = $_SESSION['vnn_booking_csrf'];

$popup = null;
$saveSuccess = false;

// ========================================
// LUU HANG LOAT TRANG THAI
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_changes'])
) {
    try {
        $postedCsrf = (string)(
            $_POST['csrf'] ?? ''
        );

        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
            );
        }

        $postedStatuses = $_POST['statuses'] ?? [];

        if (!is_array($postedStatuses)) {
            throw new RuntimeException(
                'Dữ liệu trạng thái không hợp lệ.'
            );
        }

        $changedCount = 0;

        $pdo->beginTransaction();

        $selectStmt = $pdo->prepare("
            SELECT id, status
            FROM tour_bookings
            WHERE id = ?
            FOR UPDATE
        ");

        $updateStmt = $pdo->prepare("
            UPDATE tour_bookings
            SET status = ?
            WHERE id = ?
        ");

        foreach ($postedStatuses as $id => $newStatus) {
            $bookingId = filter_var(
                $id,
                FILTER_VALIDATE_INT
            );

            if (
                $bookingId === false ||
                $bookingId <= 0
            ) {
                throw new RuntimeException(
                    'Mã đăng ký không hợp lệ.'
                );
            }

            if (!is_string($newStatus)) {
                throw new RuntimeException(
                    'Trạng thái không hợp lệ.'
                );
            }

            $newStatus = trim($newStatus);

            if (!in_array(
                $newStatus,
                $statuses,
                true
            )) {
                throw new RuntimeException(
                    'Trạng thái đăng ký không được hỗ trợ.'
                );
            }

            $selectStmt->execute([$bookingId]);

            $current = $selectStmt->fetch(
                PDO::FETCH_ASSOC
            );

            $selectStmt->closeCursor();

            if (!$current) {
                throw new RuntimeException(
                    'Không tìm thấy đăng ký #' . $bookingId
                );
            }

            $oldStatus = trim(
                (string)$current['status']
            );

            // Chi UPDATE khi du lieu thuc su thay doi.
            if ($oldStatus === $newStatus) {
                continue;
            }

            $updateStmt->execute([
                $newStatus,
                $bookingId
            ]);

            if ($updateStmt->rowCount() > 0) {
                $changedCount++;

                vnnBkAudit(
                    $pdo,
                    $bookingId,
                    'Booking #' . $bookingId,
                    $oldStatus,
                    $newStatus
                );
            }
        }

        $pdo->commit();

        $saveSuccess = true;

        $popup = [
            'type' => 'success',
            'title' => 'Đã lưu thay đổi',
            'message' => $changedCount > 0
                ? 'Đã cập nhật thành công ' .
                  $changedCount . ' đăng ký tour.'
                : 'Các trạng thái đã được lưu.'
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Booking bulk update: ' .
            $e->getMessage()
        );

        $popup = [
            'type' => 'error',
            'title' => 'Không thể lưu thay đổi',
            'message' => $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Có lỗi xảy ra khi cập nhật dữ liệu.'
        ];
    }
}

// ========================================
// LAY DU LIEU THEO DATABASE
// ========================================

$bookings = [];
$dataError = '';

try {
    $bookingCols = vnnBkColumns(
        $pdo,
        'tour_bookings'
    );

    $tourCols = vnnBkColumns(
        $pdo,
        'tours'
    );

    $userCols = vnnBkColumns(
        $pdo,
        'users'
    );

    // Truong tour_id da duoc su dung trong
    // database va cac phan booking truoc do.
    $sql = "
        SELECT
            b.*,
            t.title AS vnn_tour_title,
            l.fullname AS vnn_leader_name
        FROM tour_bookings b
        LEFT JOIN tours t
            ON t.id = b.tour_id
        LEFT JOIN trekking_leaders l
            ON l.id = t.leader_id
    ";

    // Neu booking co user_id, lay them ten/SDT
    // cua tai khoan khi booking khong luu rieng.
    if (
        in_array('user_id', $bookingCols, true) &&
        in_array('fullname', $userCols, true)
    ) {
        $sql = "
            SELECT
                b.*,
                t.title AS vnn_tour_title,
                l.fullname AS vnn_leader_name,
                u.fullname AS vnn_user_fullname,
                " .
                (
                    in_array('phone', $userCols, true)
                    ? "u.phone"
                    : "NULL"
                ) .
                " AS vnn_user_phone
            FROM tour_bookings b
            LEFT JOIN tours t
                ON t.id = b.tour_id
            LEFT JOIN trekking_leaders l
                ON l.id = t.leader_id
            LEFT JOIN users u
                ON u.id = b.user_id
        ";
    }

    $params = [];

    if ($filter !== '') {
        $sql .= " WHERE b.status = ? ";
        $params[] = $filter;
    }

    $sql .= " ORDER BY b.id DESC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $bookings = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {
    error_log(
        'Booking query: ' . $e->getMessage()
    );

    $dataError =
        'Không thể tải đăng ký tour. ' .
        'Vui lòng kiểm tra kết nối và cấu trúc database.';
}

$totalBookings = count($bookings);

// ========================================
// THONG TIN CHO GIAO DIEN
// ========================================

$baseUrl = rtrim(BASE_URL, '/') . '/';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title><?= vnnBkH($page_title) ?></title>

<link
    rel="stylesheet"
    href="../../assets/css/style.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/admin.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>
* {
    box-sizing: border-box;
}

.vnn-bookings-main {
    flex: 1 1 auto;
    min-width: 0;
    width: 0;
    background: #f1f5f9;
}

.vnn-bookings-header {
    min-height: 76px;
    padding: 17px 28px;
    border-bottom: 1px solid #e2e8f0;
    background: white;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.vnn-bookings-header h1 {
    margin: 0;
    color: #18365d;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.4;
}

.vnn-bookings-header h1 i {
    color: #166534;
    margin-right: 7px;
}

.vnn-bookings-header select {
    width: 210px;
    max-width: 100%;
    height: 45px;
    padding: 0 13px;

    border: 1px solid #cbd5e1;
    border-radius: 10px;

    background: white;
    color: #334155;
    font: inherit;
    font-size: 13px;
    cursor: pointer;
}

.vnn-bookings-body {
    padding: 23px;
    min-width: 0;
}

.vnn-bookings-tools {
    padding: 17px 19px;
    border: 1px solid #e0e8f1;
    border-radius: 14px;
    background: #edf3fa;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.vnn-bookings-count {
    color: #64748b;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.vnn-bookings-count i {
    color: #166534;
}

.vnn-bookings-save {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    min-height: 43px;
    padding: 11px 18px;
    border: none;
    border-radius: 10px;

    background: #166534;
    color: white;
    font-size: 13px;
    font-weight: 800;

    cursor: pointer;
    white-space: nowrap;
}

.vnn-bookings-save:hover {
    background: #14532d;
}

.vnn-bookings-save:disabled {
    opacity: .65;
    cursor: wait;
}

.vnn-bookings-panel {
    margin-top: 19px;
    border: 1px solid #dce5ef;
    border-radius: 14px;
    overflow: hidden;
    background: white;
}

.vnn-bookings-scroll {
    overflow-x: auto;
    width: 100%;
}

.vnn-bookings-table {
    width: 100%;
    min-width: 1140px;
    border-collapse: collapse;
    table-layout: fixed;
}

.vnn-bookings-table th,
.vnn-bookings-table td {
    text-align: left;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 12px;
    font-size: 12px;
    line-height: 1.5;
}

.vnn-bookings-table th {
    height: 62px;
    background: #f8fafc;
    color: #0f274b;
    font-weight: 800;
    font-size: 12px;
    white-space: nowrap;
}

.vnn-bookings-table tr:last-child td {
    border-bottom: none;
}

.vnn-bookings-table tbody tr:nth-child(even) {
    background: #fbfcfe;
}

.vnn-bookings-table tr.vnn-changed {
    background: #f0fdf4 !important;
}

.vnn-bookings-table .code {
    color: #0d2e5d;
    font-weight: 800;
    font-size: 11px;
    white-space: nowrap;
}

.vnn-bookings-table .customer {
    color: #112c50;
    font-weight: 800;
}

.vnn-bookings-table .phone {
    margin-top: 5px;
    color: #68809e;
    font-size: 11px;
}

.vnn-bookings-table .tour {
    color: #102d53;
    font-weight: 800;
    line-height: 1.5;
}

.vnn-bookings-table .departure {
    color: #ea580c;
    font-weight: 800;
    white-space: nowrap;
}

.vnn-bookings-table .people {
    color: #334155;
    white-space: nowrap;
}

.vnn-bookings-table .leader {
    color: #166534;
    font-weight: 800;
}

.vnn-bookings-table select {
    width: 100%;
    min-height: 42px;
    padding: 7px 9px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    color: #334155;
    font: inherit;
    font-size: 12px;
    cursor: pointer;
}

.vnn-bookings-table select:focus {
    outline: none;
    border-color: #16a34a;
}

.vnn-bookings-empty {
    padding: 45px 20px;
    text-align: center;
    color: #64748b;
    font-size: 14px;
}

.vnn-bookings-error {
    padding: 14px 18px;
    border-radius: 10px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 13px;
    margin-bottom: 16px;
}

.vnn-bookings-hint {
    margin-top: 11px;
    color: #64748b;
    font-size: 12px;
}

/* =================================
   POPUP THONG BAO
   ================================= */

.vnn-booking-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;

    display: flex;
    justify-content: center;
    align-items: center;

    padding: 20px;
    background: rgba(15,23,42,.5);
}

.vnn-booking-overlay[hidden] {
    display: none !important;
}

.vnn-booking-popup {
    width: 100%;
    max-width: 390px;
    padding: 28px;
    border-radius: 18px;
    background: white;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
}

.vnn-booking-popup-icon {
    font-size: 43px;
    color: #16a34a;
}

.vnn-booking-popup.error
.vnn-booking-popup-icon {
    color: #dc2626;
}

.vnn-booking-popup h2 {
    margin: 14px 0 9px;
    color: #0f172a;
    font-size: 21px;
    font-weight: 800;
}

.vnn-booking-popup p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

.vnn-booking-popup button {
    margin-top: 23px;
    padding: 11px 25px;
    border: 0;
    border-radius: 9px;
    background: #166534;
    color: white;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

@media(max-width:950px) {
    .vnn-bookings-header {
        padding: 15px;
    }

    .vnn-bookings-body {
        padding: 13px;
    }

    .vnn-bookings-header h1 {
        font-size: 15px;
    }
}

@media(max-width:600px) {
    .vnn-bookings-header {
        flex-wrap: wrap;
    }

    .vnn-bookings-header select {
        width: 100%;
    }

    .vnn-bookings-tools {
        padding: 12px;
    }

    .vnn-bookings-save {
        font-size: 11px;
        padding: 9px 11px;
    }
}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main vnn-bookings-main">

    <!-- HEADER -->

    <header class="vnn-bookings-header">

        <h1>
            <i class="fa-solid fa-person-hiking"></i>
            QUẢN LÝ ĐĂNG KÝ TOUR TREKKING
        </h1>

        <!-- LOC TRANG THAI
             CHI LOC DU LIEU DA LUU -->

        <select
            id="vnnBookingFilter"
            aria-label="Lọc trạng thái đăng ký"
        >

            <option
                value=""
                <?= $filter === '' ? 'selected' : '' ?>
            >
                Tất cả trạng thái
            </option>

            <?php foreach ($statuses as $status): ?>

                <option
                    value="<?= vnnBkH($status) ?>"
                    <?= $filter === $status ? 'selected' : '' ?>
                >
                    <?= vnnBkH($status) ?>
                </option>

            <?php endforeach; ?>

        </select>

    </header>

    <div class="vnn-bookings-body">

        <?php if ($dataError): ?>

            <div class="vnn-bookings-error">
                <?= vnnBkH($dataError) ?>
            </div>

        <?php endif; ?>

        <!-- FORM DUY NHAT CHO TOAN BO BANG -->

        <form
            id="vnnBookingBulkForm"
            method="POST"
        >

            <input
                type="hidden"
                name="csrf"
                value="<?= vnnBkH($csrf) ?>"
            >

            <input
                type="hidden"
                name="current_filter"
                value="<?= vnnBkH($filter) ?>"
            >

            <input
                type="hidden"
                name="save_changes"
                value="1"
            >

            <div class="vnn-bookings-tools">

                <div class="vnn-bookings-count">

                    <i class="fa-solid fa-ticket"></i>

                    <span>
                        <?= (int)$totalBookings ?>
                        đăng ký tour
                    </span>

                </div>

                <button
                    id="vnnBookingSave"
                    type="submit"
                    class="vnn-bookings-save"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    Lưu thay đổi

                </button>

            </div>

            <section class="vnn-bookings-panel">

                <div class="vnn-bookings-scroll">

                    <table class="vnn-bookings-table">

                        <colgroup>
                            <col style="width:17%">
                            <col style="width:20%">
                            <col style="width:19%">
                            <col style="width:13%">
                            <col style="width:9%">
                            <col style="width:12%">
                            <col style="width:10%">
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

                        <?php foreach ($bookings as $booking): ?>

                            <?php
                            $bookingId = (int)$booking['id'];

                            $bookingCode = vnnBkValue(
                                $booking,
                                [
                                    'booking_code',
                                    'code',
                                    'ma_booking'
                                ],
                                'Booking #' . $bookingId
                            );

                            $customer = vnnBkValue(
                                $booking,
                                [
                                    'customer_name',
                                    'fullname',
                                    'full_name',
                                    'contact_name',
                                    'guest_name',
                                    'name',
                                    'vnn_user_fullname'
                                ],
                                'Khách hàng'
                            );

                            $phone = vnnBkValue(
                                $booking,
                                [
                                    'customer_phone',
                                    'phone',
                                    'phone_number',
                                    'contact_phone',
                                    'vnn_user_phone'
                                ],
                                ''
                            );

                            $tourName = vnnBkValue(
                                $booking,
                                ['vnn_tour_title'],
                                'Tour trekking'
                            );

                            $departure = vnnBkValue(
                                $booking,
                                [
                                    'departure_date',
                                    'start_date',
                                    'tour_date',
                                    'departure',
                                    'booking_date'
                                ],
                                ''
                            );

                            $people = vnnBkPeople($booking);

                            $leaderName = vnnBkValue(
                                $booking,
                                ['vnn_leader_name'],
                                'Chưa cập nhật'
                            );

                            $currentStatus = trim(
                                (string)($booking['status'] ?? '')
                            );
                            ?>

                            <tr
                                class="vnn-booking-row"
                                data-booking-id="<?= $bookingId ?>"
                            >

                                <td>
                                    <span class="code">
                                        <?= vnnBkH($bookingCode) ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="customer">
                                        <?= vnnBkH($customer) ?>
                                    </div>

                                    <?php if ($phone !== ''): ?>

                                        <div class="phone">
                                            <?= vnnBkH($phone) ?>
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <div class="tour">
                                        <?= vnnBkH($tourName) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="departure">
                                        <?= vnnBkH(
                                            vnnBkDate($departure)
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="people">
                                        <?= (int)$people ?> người
                                    </span>
                                </td>

                                <td>
                                    <span class="leader">
                                        <?= vnnBkH($leaderName) ?>
                                    </span>
                                </td>

                                <td>

                                    <select
                                        class="vnn-booking-status"
                                        name="statuses[<?= $bookingId ?>]"
                                        data-original="<?= vnnBkH($currentStatus) ?>"
                                        aria-label="Trạng thái booking <?= vnnBkH($bookingCode) ?>"
                                    >

                                        <?php
                                        // Giu gia tri trang thai cu neu
                                        // du lieu chua nam trong danh sach.
                                        if (
                                            $currentStatus !== '' &&
                                            !in_array(
                                                $currentStatus,
                                                $statuses,
                                                true
                                            )
                                        ):
                                        ?>

                                            <option
                                                value="<?= vnnBkH($currentStatus) ?>"
                                                selected
                                            >
                                                <?= vnnBkH($currentStatus) ?>
                                            </option>

                                        <?php endif; ?>

                                        <?php foreach ($statuses as $status): ?>

                                            <option
                                                value="<?= vnnBkH($status) ?>"
                                                <?= $currentStatus === $status
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= vnnBkH($status) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <?php if (!$dataError && !$bookings): ?>

                    <div class="vnn-bookings-empty">
                        Không có đăng ký tour phù hợp với bộ lọc.
                    </div>

                <?php endif; ?>

            </section>

        </form>

    </div>

</main>

<!-- =================================
     POPUP THONG BAO
     ================================= -->

<div
    id="vnnBookingPopup"
    class="vnn-booking-overlay"
    <?= $popup ? '' : 'hidden' ?>
>

    <div
        class="vnn-booking-popup <?= ($popup['type'] ?? '') === 'error'
            ? 'error'
            : '' ?>"
    >

        <div class="vnn-booking-popup-icon">

            <i
                class="fa-solid <?= ($popup['type'] ?? '') === 'error'
                    ? 'fa-circle-xmark'
                    : 'fa-circle-check' ?>"
            ></i>

        </div>

        <h2>
            <?= vnnBkH(
                $popup['title'] ?? 'Thông báo'
            ) ?>
        </h2>

        <p>
            <?= vnnBkH(
                $popup['message'] ?? ''
            ) ?>
        </p>

        <button
            type="button"
            id="vnnBookingClosePopup"
        >
            Đóng
        </button>

    </div>

</div>

<script>
(function() {

    const filter = document.getElementById(
        'vnnBookingFilter'
    );

    const form = document.getElementById(
        'vnnBookingBulkForm'
    );

    const saveButton = document.getElementById(
        'vnnBookingSave'
    );

    const statusSelects = document.querySelectorAll(
        '.vnn-booking-status'
    );

    const popup = document.getElementById(
        'vnnBookingPopup'
    );

    const popupClose = document.getElementById(
        'vnnBookingClosePopup'
    );

    let submitting = false;

    // ===================================
    // CHON TRANG THAI
    //
    // KHONG AN DONG KHI THAY DOI SELECT.
    // CHI DANH DAU DONG DA SUA.
    // ===================================

    statusSelects.forEach(function(select) {

        select.addEventListener(
            'change',
            function() {

                const row = select.closest(
                    '.vnn-booking-row'
                );

                if (!row) {
                    return;
                }

                const changed =
                    select.value !==
                    select.dataset.original;

                row.classList.toggle(
                    'vnn-changed',
                    changed
                );
            }
        );
    });

    // ===================================
    // LUU TOAN BO STATUS DANG HIEN THI
    // ===================================

    if (form) {

        form.addEventListener(
            'submit',
            function(event) {

                if (submitting) {
                    event.preventDefault();
                    return;
                }

                submitting = true;

                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';
                }

                // Khong ngan form submit.
                // PHP se nhan:
                //
                // statuses[booking_id] = trang_thai_moi
                //
                // Sau do UPDATE vao database.
            }
        );
    }

    // ===================================
    // LOC TRANG THAI
    //
    // CHI LOC TU DATABASE.
    //
    // KHONG LOC THEO GIA TRI VUA CHON
    // NHUNG CHUA LUU.
    // ===================================

    if (filter) {

        filter.addEventListener(
            'change',
            function() {

                const selected = filter.value;

                const url = new URL(
                    window.location.href
                );

                url.searchParams.delete('status');

                if (selected !== '') {
                    url.searchParams.set(
                        'status',
                        selected
                    );
                }

                // Chuyen bang GET.
                // Khong submit form POST.
                window.location.assign(url.toString());
            }
        );
    }

    // ===================================
    // DONG POPUP
    // ===================================

    function closePopup() {
        if (popup) {
            popup.hidden = true;
        }
    }

    if (popupClose) {
        popupClose.addEventListener(
            'click',
            closePopup
        );
    }

    if (popup) {
        popup.addEventListener(
            'click',
            function(event) {
                if (event.target === popup) {
                    closePopup();
                }
            }
        );
    }

    document.addEventListener(
        'keydown',
        function(event) {
            if (event.key === 'Escape') {
                closePopup();
            }
        }
    );

})();
</script>

</body>
</html>
