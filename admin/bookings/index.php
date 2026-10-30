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

if (!function_exists('bkEsc')) {
    function bkEsc($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('bkColumns')) {
    function bkColumns(PDO $pdo, string $table): array {
        if (!in_array($table, ['tour_bookings', 'users'], true)) {
            return [];
        }

        return array_column(
            $pdo->query("SHOW COLUMNS FROM `$table`")
                ->fetchAll(PDO::FETCH_ASSOC),
            'Field'
        );
    }
}

if (!function_exists('bkFirst')) {
    function bkFirst(array $row, array $names, $default = '') {
        foreach ($names as $name) {
            if (
                array_key_exists($name, $row) &&
                $row[$name] !== null &&
                trim((string)$row[$name]) !== ''
            ) {
                return $row[$name];
            }
        }

        return $default;
    }
}

if (!function_exists('bkPeople')) {
    function bkPeople(array $booking): ?int {

        // Uu tien cac cot luu tong so nguoi
        $priority = [
            'number_of_people',
            'number_of_persons',
            'number_of_participants',
            'number_of_guests',
            'participants_count',
            'participant_count',
            'people_count',
            'persons_count',
            'guest_count',
            'guests_count',
            'total_people',
            'total_guests',
            'total_persons',
            'total_participants',
            'num_people',
            'num_persons',
            'num_guests',
            'num_participants',
            'so_nguoi',
            'so_khach',
            'people',
            'persons',
            'participants',
            'guests',
            'quantity',
            'qty',
            'adults',
            'adult_count'
        ];

        // Khong phan biet chu hoa / chu thuong
        $normalized = [];

        foreach ($booking as $key => $value) {
            $normalized[strtolower((string)$key)] = $value;
        }

        foreach ($priority as $name) {
            if (
                array_key_exists($name, $normalized) &&
                is_numeric($normalized[$name])
            ) {
                $number = (int)$normalized[$name];

                if ($number >= 1) {
                    return $number;
                }
            }
        }

        // Tim cac ten cot co y nghia tuong duong
        foreach ($normalized as $key => $value) {
            if (!is_numeric($value) || (int)$value < 1) {
                continue;
            }

            $looksLikeCount = preg_match(
                '/(people|person|guest|participant|adult|traveler|traveller|so_nguoi|so_khach)/i',
                $key
            );

            $notACount = preg_match(
                '/(id|price|amount|cost|fee|date|time|phone|code|status|child|remaining|available|max|min)/i',
                $key
            );

            if ($looksLikeCount && !$notACount) {
                return (int)$value;
            }
        }

        // Ho tro truong hop luu rieng nguoi lon va tre em
        $adult = bkFirst(
            $normalized,
            ['adult_count', 'adults_count', 'num_adults'],
            null
        );

        $child = bkFirst(
            $normalized,
            ['child_count', 'children_count', 'num_children'],
            null
        );

        if ($adult !== null && is_numeric($adult)) {
            return max(0, (int)$adult) +
                ($child !== null && is_numeric($child)
                    ? max(0, (int)$child)
                    : 0);
        }

        // Neu khong tim duoc cot that:
        // KHONG tu ghi "0 nguoi"
        return null;
    }
}

if (!function_exists('bkDate')) {
    function bkDate($value): string {
        if (!$value) {
            return '—';
        }

        $timestamp = strtotime((string)$value);

        return $timestamp
            ? date('d/m/Y', $timestamp)
            : (string)$value;
    }
}

if (!function_exists('bkLog')) {
    function bkLog(
        PDO $pdo,
        int $id,
        string $old,
        string $new
    ): void {
        try {
            $actorId = (int)($_SESSION['user_id'] ?? 0);
            $actorName = (string)(
                $_SESSION['fullname'] ?? 'Nhân viên'
            );

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
                (string)$id,
                $old,
                $new
            ]);

        } catch (Throwable $e) {
            error_log('Booking audit: ' . $e->getMessage());
        }
    }
}

// ======================================
// TRANG THAI
// ======================================

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

if (empty($_SESSION['bookings_csrf'])) {
    $_SESSION['bookings_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['bookings_csrf'];
$popup = null;

// ======================================
// LUU TRANG THAI HANG LOAT
// ======================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_changes'])
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

        $submitted = $_POST['statuses'] ?? [];

        if (!is_array($submitted)) {
            throw new RuntimeException(
                'Dữ liệu trạng thái không hợp lệ.'
            );
        }

        $updatedCount = 0;

        $pdo->beginTransaction();

        $select = $pdo->prepare("
            SELECT id, status
            FROM tour_bookings
            WHERE id = ?
            FOR UPDATE
        ");

        $update = $pdo->prepare("
            UPDATE tour_bookings
            SET status = ?
            WHERE id = ?
        ");

        foreach ($submitted as $id => $status) {
            $id = filter_var($id, FILTER_VALIDATE_INT);

            if (!$id || !is_string($status)) {
                throw new RuntimeException(
                    'Dữ liệu booking không hợp lệ.'
                );
            }

            $status = trim($status);

            if (!in_array($status, $statuses, true)) {
                throw new RuntimeException(
                    'Trạng thái không hợp lệ.'
                );
            }

            $select->execute([$id]);
            $current = $select->fetch(PDO::FETCH_ASSOC);
            $select->closeCursor();

            if (!$current) {
                throw new RuntimeException(
                    'Không tìm thấy booking #' . $id
                );
            }

            $old = trim((string)$current['status']);

            if ($old === $status) {
                continue;
            }

            $update->execute([$status, $id]);

            if ($update->rowCount() > 0) {
                $updatedCount++;
                bkLog($pdo, $id, $old, $status);
            }
        }

        $pdo->commit();

        $popup = [
            'type' => 'success',
            'title' => 'Đã lưu thay đổi',
            'message' => $updatedCount > 0
                ? 'Đã cập nhật ' . $updatedCount . ' đăng ký tour.'
                : 'Các trạng thái đã được lưu.'
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Booking save: ' . $e->getMessage());

        $popup = [
            'type' => 'error',
            'title' => 'Không thể lưu thay đổi',
            'message' => $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Có lỗi xảy ra khi lưu trạng thái.'
        ];
    }
}

// ======================================
// LAY DANH SACH BOOKING
// ======================================

$bookings = [];
$error = '';

try {
    $bookingColumns = bkColumns($pdo, 'tour_bookings');
    $userColumns = bkColumns($pdo, 'users');

    $hasUserJoin = in_array(
        'user_id',
        $bookingColumns,
        true
    );

    $sql = "
        SELECT
            b.*,
            t.title AS tour_title,
            l.fullname AS leader_name
    ";

    if ($hasUserJoin) {
        $sql .= ",
            u.fullname AS account_fullname
        ";

        if (in_array('phone', $userColumns, true)) {
            $sql .= ", u.phone AS account_phone ";
        }
    }

    $sql .= "
        FROM tour_bookings b
        LEFT JOIN tours t
            ON t.id = b.tour_id
        LEFT JOIN trekking_leaders l
            ON l.id = t.leader_id
    ";

    if ($hasUserJoin) {
        $sql .= "
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

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Booking load: ' . $e->getMessage());
    $error = 'Không thể tải danh sách đăng ký tour.';
}

$total = count($bookings);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= bkEsc($page_title) ?></title>

<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>
* {
    box-sizing: border-box;
}

.bk-main {
    flex: 1 1 auto;
    min-width: 0;
    background: #f1f5f9;
}

.bk-header {
    min-height: 75px;
    padding: 17px 26px;
    background: white;
    border-bottom: 1px solid #e2e8f0;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.bk-header h1 {
    margin: 0;
    color: #173458;
    font-size: 18px;
    font-weight: 800;
}

.bk-header h1 i {
    margin-right: 7px;
    color: #166534;
}

.bk-filter {
    flex: 0 0 210px;
    height: 44px;
    padding: 0 13px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: white;
    font: inherit;
    font-size: 13px;
    color: #334155;
}

.bk-content {
    padding: 23px;
    min-width: 0;
}

.bk-tools {
    padding: 17px 19px;
    border: 1px solid #e1e8f0;
    border-radius: 14px;
    background: #edf3fa;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.bk-count {
    color: #64748b;
    font-size: 13px;
}

.bk-count i {
    margin-right: 8px;
    color: #166534;
}

.bk-save {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    min-height: 43px;
    padding: 10px 17px;

    border: none;
    border-radius: 9px;
    background: #166534;
    color: white;

    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.bk-save:disabled {
    opacity: .6;
}

.bk-panel {
    margin-top: 19px;
    border: 1px solid #dce5ef;
    border-radius: 14px;
    background: white;
    overflow: hidden;
}

.bk-scroll {
    width: 100%;
    overflow-x: auto;
}

.bk-table {
    width: 100%;
    min-width: 1260px;
    border-collapse: collapse;
    table-layout: fixed;
}

.bk-table th,
.bk-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: middle;
    font-size: 12px;
    line-height: 1.5;
}

.bk-table th {
    background: #f8fafc;
    color: #102c50;
    font-weight: 800;
    white-space: nowrap;
}

.bk-table tr:last-child td {
    border-bottom: none;
}

.bk-table tbody tr:nth-child(even) {
    background: #fbfcfe;
}

.bk-table tbody tr.changed {
    background: #f0fdf4 !important;
}

/* GIU MA BOOKING GON TREN MOT HANG */

.bk-code {
    display: block;
    color: #0d2e5d;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.bk-name {
    color: #102c50;
    font-weight: 800;
}

.bk-phone {
    margin-top: 5px;
    color: #7188a5;
    font-size: 11px;
}

.bk-tour {
    color: #102c50;
    font-weight: 800;
}

.bk-date {
    color: #ea580c;
    font-weight: 800;
    white-space: nowrap;
}

.bk-people {
    color: #334155;
    white-space: nowrap;
}

.bk-leader {
    color: #166534;
    font-weight: 800;
}

/* SUA COT TRANG THAI:
   TANG CHIEU RONG VA KHONG CAT CHU */

.bk-status {
    display: block;
    width: 100%;
    min-width: 150px;
    height: 44px;
    padding: 0 9px;

    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    color: #334155;

    font: inherit;
    font-size: 12px;
    cursor: pointer;
}

.bk-status:focus {
    outline: none;
    border-color: #16a34a;
}

.bk-empty {
    padding: 40px 15px;
    text-align: center;
    color: #64748b;
    font-size: 13px;
}

.bk-error {
    padding: 14px 16px;
    border-radius: 10px;
    background: #fee2e2;
    color: #991b1b;
    margin-bottom: 17px;
    font-size: 13px;
}

/* POPUP */

.bk-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 20px;
    background: rgba(15,23,42,.48);
}

.bk-overlay[hidden] {
    display: none !important;
}

.bk-popup {
    width: 100%;
    max-width: 400px;
    padding: 29px;

    border-radius: 18px;
    background: white;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
}

.bk-popup-icon {
    color: #16a34a;
    font-size: 44px;
}

.bk-popup.error .bk-popup-icon {
    color: #dc2626;
}

.bk-popup h2 {
    color: #0f172a;
    font-size: 20px;
    margin: 14px 0 10px;
}

.bk-popup p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

.bk-popup button {
    margin-top: 20px;
    padding: 11px 23px;
    border: 0;
    border-radius: 9px;
    background: #166534;
    color: white;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

@media(max-width:900px) {
    .bk-header {
        padding: 15px;
    }

    .bk-header h1 {
        font-size: 15px;
    }

    .bk-content {
        padding: 13px;
    }
}

@media(max-width:600px) {
    .bk-header {
        flex-wrap: wrap;
    }

    .bk-filter {
        flex: 1 1 100%;
    }
}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main bk-main">

    <header class="bk-header">

        <h1>
            <i class="fa-solid fa-person-hiking"></i>
            QUẢN LÝ ĐĂNG KÝ TOUR TREKKING
        </h1>

        <select
            class="bk-filter"
            id="bkFilter"
            aria-label="Lọc trạng thái"
        >

            <option value="" <?= $filter === '' ? 'selected' : '' ?>>
                Tất cả trạng thái
            </option>

            <?php foreach ($statuses as $status): ?>

                <option
                    value="<?= bkEsc($status) ?>"
                    <?= $filter === $status ? 'selected' : '' ?>
                >
                    <?= bkEsc($status) ?>
                </option>

            <?php endforeach; ?>

        </select>

    </header>

    <div class="bk-content">
        <div style="padding:13px 17px;border:1px solid #fed7aa;background:#fff7ed;border-radius:10px;margin-bottom:16px;line-height:1.8;color:#9a3412;font-size:13px">
            <strong><i class="fa-solid fa-bell"></i> Cần xử lý:</strong>
            <strong><?= (int)($vnnCounts['pending'] ?? 0) ?></strong> tour chờ xác nhận;
            <strong><?= (int)($vnnCounts['unpaid'] ?? 0) ?></strong> tour đã xác nhận, chưa thanh toán.
        </div>


        <?php if ($error): ?>

            <div class="bk-error">
                <?= bkEsc($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" id="bkForm">

            <input
                type="hidden"
                name="csrf"
                value="<?= bkEsc($csrf) ?>"
            >

            <input
                type="hidden"
                name="current_filter"
                value="<?= bkEsc($filter) ?>"
            >

            <input
                type="hidden"
                name="save_changes"
                value="1"
            >

            <div class="bk-tools">

                <div class="bk-count">

                    <i class="fa-solid fa-ticket"></i>

                    <?= (int)$total ?> đăng ký tour

                </div>

                <button
                    type="submit"
                    class="bk-save"
                    id="bkSave"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Lưu thay đổi
                </button>

            </div>

            <section class="bk-panel">

                <div class="bk-scroll">

                    <table class="bk-table">

                        <!-- CHIA LAI COT:
                             TRANG THAI 16% THAY VI 10% -->

                        <colgroup>
                            <col style="width:16%">
                            <col style="width:20%">
                            <col style="width:18%">
                            <col style="width:12%">
                            <col style="width:8%">
                            <col style="width:10%">
                            <col style="width:16%">
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
                            $id = (int)$booking['id'];

                            $code = bkFirst(
                                $booking,
                                ['booking_code', 'code'],
                                'Booking #' . $id
                            );

                            $customer = bkFirst(
                                $booking,
                                [
                                    'customer_name',
                                    'full_name',
                                    'fullname',
                                    'contact_name',
                                    'guest_name',
                                    'name',
                                    'account_fullname'
                                ],
                                'Khách hàng'
                            );

                            $phone = bkFirst(
                                $booking,
                                [
                                    'customer_phone',
                                    'phone',
                                    'phone_number',
                                    'contact_phone',
                                    'account_phone'
                                ]
                            );

                            $tour = bkFirst(
                                $booking,
                                ['tour_title'],
                                'Tour trekking'
                            );

                            $date = bkFirst(
                                $booking,
                                [
                                    'departure_date',
                                    'start_date',
                                    'tour_date',
                                    'departure',
                                    'booking_date'
                                ]
                            );

                            $people = bkPeople($booking);

                            $leader = bkFirst(
                                $booking,
                                ['leader_name'],
                                'Chưa cập nhật'
                            );

                            $currentStatus = trim(
                                (string)($booking['status'] ?? '')
                            );
                            ?>

                            <tr class="bk-row">

                                <td>
                                    <span class="bk-code">
                                        <?= bkEsc($code) ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="bk-name">
                                        <?= bkEsc($customer) ?>
                                    </div>

                                    <?php if ($phone !== ''): ?>

                                        <div class="bk-phone">
                                            <?= bkEsc($phone) ?>
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <div class="bk-tour">
                                        <?= bkEsc($tour) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="bk-date">
                                        <?= bkEsc(bkDate($date)) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="bk-people">
                                        <?= $people === null
                                            ? '—'
                                            : (int)$people . ' người' ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="bk-leader">
                                        <?= bkEsc($leader) ?>
                                    </span>
                                </td>

                                <td>

                                    <select
                                        class="bk-status"
                                        name="statuses[<?= $id ?>]"
                                        data-original="<?= bkEsc($currentStatus) ?>"
                                    >

                                        <?php
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
                                                value="<?= bkEsc($currentStatus) ?>"
                                                selected
                                            >
                                                <?= bkEsc($currentStatus) ?>
                                            </option>

                                        <?php endif; ?>

                                        <?php foreach ($statuses as $status): ?>

                                            <option
                                                value="<?= bkEsc($status) ?>"
                                                <?= $currentStatus === $status
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= bkEsc($status) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <?php if (!$error && !$bookings): ?>

                    <div class="bk-empty">
                        Không có đăng ký tour phù hợp.
                    </div>

                <?php endif; ?>

            </section>

        </form>

    </div>

</main>

<!-- POPUP KET QUA -->

<div
    id="bkPopup"
    class="bk-overlay"
    <?= $popup ? '' : 'hidden' ?>
>

    <div class="bk-popup <?= ($popup['type'] ?? '') === 'error'
        ? 'error'
        : '' ?>">

        <div class="bk-popup-icon">

            <i class="fa-solid <?= ($popup['type'] ?? '') === 'error'
                ? 'fa-circle-xmark'
                : 'fa-circle-check' ?>"></i>

        </div>

        <h2><?= bkEsc($popup['title'] ?? '') ?></h2>

        <p><?= bkEsc($popup['message'] ?? '') ?></p>

        <button
            type="button"
            id="bkClosePopup"
        >
            Đóng
        </button>

    </div>

</div>

<script>
(function() {

    const filter = document.getElementById('bkFilter');
    const form = document.getElementById('bkForm');
    const save = document.getElementById('bkSave');
    const popup = document.getElementById('bkPopup');

    let submitting = false;

    // KHONG AN DONG KHI DOI TRANG THAI

    document.querySelectorAll('.bk-status')
        .forEach(function(select) {

            select.addEventListener('change', function() {

                const row = select.closest('.bk-row');

                if (!row) return;

                row.classList.toggle(
                    'changed',
                    select.value !== select.dataset.original
                );
            });
        });

    // LUU BANG POST

    if (form) {
        form.addEventListener('submit', function(event) {

            if (submitting) {
                event.preventDefault();
                return;
            }

            submitting = true;

            if (save) {
                save.disabled = true;
                save.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';
            }
        });
    }

    // LOC THEO TRANG THAI DA LUU

    filter?.addEventListener('change', function() {

        const changed = Array.from(
            document.querySelectorAll('.bk-status')
        ).some(select =>
            select.value !== select.dataset.original
        );

        if (changed) {
            const leave = window.confirm(
                'Bạn có trạng thái chưa lưu. ' +
                'Chuyển bộ lọc sẽ bỏ các thay đổi đó. Tiếp tục?'
            );

            if (!leave) {
                filter.value =
                    <?= json_encode($filter, JSON_UNESCAPED_UNICODE) ?>;
                return;
            }
        }

        const url = new URL(window.location.href);

        url.searchParams.delete('status');

        if (filter.value) {
            url.searchParams.set('status', filter.value);
        }

        window.location.assign(url.toString());
    });

    // DONG POPUP

    function closePopup() {
        if (popup) popup.hidden = true;
    }

    document.getElementById('bkClosePopup')
        ?.addEventListener('click', closePopup);

    popup?.addEventListener('click', function(event) {
        if (event.target === popup) closePopup();
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') closePopup();
    });

})();
</script>

</body>
</html>
