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

$statuses = [
    'Chờ xác nhận',
    'Đã xác nhận',
    'Đã thanh toán',
    'Đã hoàn thành',
    'Đã hủy'
];

$error = '';

if (empty($_SESSION['bookings_csrf'])) {
    $_SESSION['bookings_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['bookings_csrf'];

// ========================================
// LUU TRANG THAI HANG LOAT
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_all_changes'])
) {
    try {
        $postedCsrf = (string)($_POST['csrf'] ?? '');

        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ.'
            );
        }

        $postedStatuses = $_POST['status'] ?? [];

        if (!is_array($postedStatuses)) {
            throw new RuntimeException(
                'Dữ liệu cập nhật không hợp lệ.'
            );
        }

        if (count($postedStatuses) > 1000) {
            throw new RuntimeException(
                'Quá nhiều đơn trong một lần cập nhật.'
            );
        }

        $updates = [];

        foreach ($postedStatuses as $bookingId => $status) {
            $id = filter_var(
                $bookingId,
                FILTER_VALIDATE_INT
            );

            if (
                $id === false ||
                $id <= 0 ||
                !is_string($status) ||
                !in_array($status, $statuses, true)
            ) {
                throw new RuntimeException(
                    'Có dữ liệu trạng thái không hợp lệ.'
                );
            }

            $updates[$id] = $status;
        }

        $pdo->beginTransaction();

        $check = $pdo->prepare("
            SELECT status
            FROM tour_bookings
            WHERE id = ?
            FOR UPDATE
        ");

        $update = $pdo->prepare("
            UPDATE tour_bookings
            SET status = ?
            WHERE id = ?
        ");

        foreach ($updates as $bookingId => $newStatus) {
            $check->execute([$bookingId]);

            $oldStatus = $check->fetchColumn();
            $check->closeCursor();

            if ($oldStatus === false) {
                throw new RuntimeException(
                    'Không tìm thấy đơn #' . $bookingId
                );
            }

            if ((string)$oldStatus !== $newStatus) {
                $update->execute([
                    $newStatus,
                    $bookingId
                ]);
            }
        }

        $pdo->commit();

        // Luon thong bao luu thanh cong,
        // ke ca khi cac trang thai giu nguyen.
        $_SESSION['bookings_notice'] = 'Đã lưu thay đổi';

        header('Location: index.php?saved=1', true, 303);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Booking bulk update: ' . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể lưu thay đổi.';
    }
}

// ========================================
// THONG BAO POPUP
// ========================================

$showSuccess = false;

if (
    isset($_GET['saved']) &&
    $_GET['saved'] === '1' &&
    !empty($_SESSION['bookings_notice'])
) {
    $showSuccess = true;
    unset($_SESSION['bookings_notice']);
}

// ========================================
// LAY TOAN BO BOOKING
// LOC BANG JAVASCRIPT TREN TRANG
// ========================================

$sql = "
    SELECT
        b.id,
        b.booking_code,
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

    ORDER BY b.id DESC
";

$stmt = $pdo->query($sql);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalBookings = count($bookings);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
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
/* =====================================
   GIAO DIEN CHUNG
   ===================================== */

.bk-main {
    flex: 1 1 0%;
    min-width: 0 !important;
    max-width: 100%;
    overflow-x: hidden !important;
    background: #f3f6fa;
}

.bk-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.bk-content {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding: 20px;
    box-sizing: border-box;
}

.bk-filter {
    min-width: 175px;
    padding: 11px 12px;
    border: 1px solid #d7e0eb;
    border-radius: 9px;
    background: white;
    color: #334155;
    font: inherit;
    font-size: 13px;
}

.bk-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 17px;
    font-size: 13px;
}

/* =====================================
   THANH CONG CU
   ===================================== */

.bk-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    width: 100%;
    padding: 14px 17px;
    margin-bottom: 17px;
    border: 1px solid #e0e7f0;
    border-radius: 13px;
    background: #eef3f9;
    box-sizing: border-box;
}

.bk-total {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: #64748b;
    font-size: 13px;
}

.bk-total i {
    color: #166534;
}

.bk-save {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 18px;
    background: #166534;
    color: white;
    border: 0;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}

.bk-save:hover {
    background: #14532d;
}

/* =====================================
   BANG KHONG TRAN NGANG TRANG
   ===================================== */

.bk-table-wrap {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: white;
    box-sizing: border-box;
}

.bk-table {
    width: 100%;
    min-width: 940px;
    table-layout: fixed;
    border-collapse: collapse;
}

.bk-col-code {
    width: 17%;
}

.bk-col-customer {
    width: 20%;
}

.bk-col-tour {
    width: 18%;
}

.bk-col-date {
    width: 13%;
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
    padding: 17px 10px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #0f172a;
    font-size: 12px;
    font-weight: 800;
    text-align: left;
}

.bk-table td {
    padding: 17px 10px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
    color: #334155;
    font-size: 12px;
}

.bk-table tbody tr:hover td {
    background: #f8fafc;
}

.bk-table tbody tr[hidden] {
    display: none !important;
}

/* =====================================
   NOI DUNG CAC COT
   ===================================== */

.bk-code {
    display: block;
    color: #17345f;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
    letter-spacing: -0.4px;
}

.bk-two-lines {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
    overflow-wrap: break-word;
    line-height: 1.5;
}

.bk-customer {
    color: #0f172a;
    font-weight: 700;
}

.bk-phone {
    display: block;
    margin-top: 6px;
    color: #64748b;
    font-size: 11px;
    white-space: nowrap;
}

.bk-tour {
    color: #0f172a;
    font-weight: 800;
}

.bk-date {
    color: #ea580c;
    font-weight: 800;
    white-space: nowrap;
}

.bk-people {
    white-space: nowrap;
}

.bk-leader {
    color: #166534;
    font-weight: 800;
}

.bk-leader a {
    color: inherit;
    text-decoration: none;
}

.bk-leader a:hover {
    text-decoration: underline;
}

.bk-status {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 10px 5px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    color: #334155;
    font-family: inherit;
    font-size: 11px;
    cursor: pointer;
}

.bk-status.changed {
    background: #f0fdf4;
    border-color: #16a34a;
    color: #166534;
    font-weight: 800;
}

.bk-empty {
    padding: 40px !important;
    text-align: center;
    color: #64748b;
}

@media (max-width: 1100px) {
    .bk-content {
        padding: 11px;
    }

    .bk-table {
        min-width: 940px;
    }
}

/* =====================================
   POPUP GIUA MAN HINH
   ===================================== */

.bk-popup-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    background: rgba(15,23,42,.45);
    box-sizing: border-box;
}

.bk-popup-overlay[hidden] {
    display: none !important;
}

.bk-popup {
    width: 100%;
    max-width: 360px;
    padding: 30px 25px;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    text-align: center;
}

.bk-popup-icon {
    width: 65px;
    height: 65px;
    display: grid;
    place-items: center;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: #dcfce7;
    color: #166534;
    font-size: 31px;
}

.bk-popup h2 {
    margin: 0 0 12px;
    color: #0f172a;
    font-size: 21px;
}

.bk-popup p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

.bk-popup-button {
    width: 100%;
    margin-top: 19px;
    padding: 12px;
    border: 0;
    border-radius: 9px;
    background: #166534;
    color: white;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
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

        <select
            id="bkStatusFilter"
            class="bk-filter"
            aria-label="Lọc trạng thái đăng ký"
        >
            <option value="">Tất cả trạng thái</option>

            <?php foreach ($statuses as $status): ?>
                <option value="<?= bk_h($status) ?>">
                    <?= bk_h($status) ?>
                </option>
            <?php endforeach; ?>
        </select>

    </div>

    <div class="bk-content">

        <?php if ($error): ?>
            <div class="bk-error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= bk_h($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            id="bkBulkForm"
            action="index.php"
        >

            <input
                type="hidden"
                name="csrf"
                value="<?= bk_h($csrf) ?>"
            >

            <!-- THANH CONG CU -->

            <div class="bk-toolbar">

                <div class="bk-total">
                    <i class="fa-solid fa-ticket"></i>
                    <span id="bkVisibleCount">
                        <?= $totalBookings ?> đăng ký tour
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

            <!-- BANG -->

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

                    <tbody id="bkTableBody">

                        <?php foreach ($bookings as $booking): ?>

                            <?php
                            $bookingId = (int)$booking['id'];

                            $customer = trim(
                                (string)$booking['customer_name']
                            );

                            $leaderName = trim(
                                (string)($booking['leader_name'] ?? '')
                            );

                            $leaderId = (int)(
                                $booking['leader_id'] ?? 0
                            );

                            $leaderLink =
                                rtrim(BASE_URL, '/') .
                                '/pages/leader-detail.php?id=' .
                                $leaderId;
                            ?>

                            <tr class="bk-booking-row">

                                <td>
                                    <span
                                        class="bk-code"
                                        title="<?= bk_h($booking['booking_code']) ?>"
                                    >
                                        <?= bk_h($booking['booking_code']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span
                                        class="bk-customer bk-two-lines"
                                        title="<?= bk_h($customer) ?>"
                                    >
                                        <?= bk_h($customer) ?>
                                    </span>

                                    <small class="bk-phone">
                                        <?= bk_h($booking['phone'] ?? '') ?>
                                    </small>
                                </td>

                                <td>
                                    <span class="bk-tour bk-two-lines">
                                        <?= bk_h($booking['tour_name']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="bk-date">
                                        <?php if (!empty($booking['departure_date'])): ?>
                                            <?= date(
                                                'd/m/Y',
                                                strtotime($booking['departure_date'])
                                            ) ?>
                                        <?php else: ?>
                                            Chưa chọn
                                        <?php endif; ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="bk-people">
                                        <?= (int)$booking['number_people'] ?>
                                        người
                                    </span>
                                </td>

                                <td>
                                    <span class="bk-leader bk-two-lines">
                                        <?php if (
                                            $leaderName !== '' &&
                                            $leaderId > 0
                                        ): ?>
                                            <a
                                                href="<?= bk_h($leaderLink) ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <?= bk_h($leaderName) ?>
                                            </a>
                                        <?php else: ?>
                                            Chưa phân công
                                        <?php endif; ?>
                                    </span>
                                </td>

                                <td>
                                    <select
                                        name="status[<?= $bookingId ?>]"
                                        class="bk-status"
                                        data-original="<?= bk_h($booking['status']) ?>"
                                    >
                                        <?php foreach ($statuses as $status): ?>
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

                        <?php if (!$bookings): ?>
                            <tr id="bkNoData">
                                <td colspan="7" class="bk-empty">
                                    Chưa có đăng ký tour.
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr id="bkNoData" hidden>
                                <td colspan="7" class="bk-empty">
                                    Không có đăng ký nào phù hợp bộ lọc.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </form>

    </div>

</main>

<!-- POPUP LUU THANH CONG -->

<div
    class="bk-popup-overlay"
    id="bkSuccessOverlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="bkPopupTitle"
    <?= $showSuccess ? '' : 'hidden' ?>
>

    <div class="bk-popup">

        <div class="bk-popup-icon">
            <i class="fa-solid fa-check"></i>
        </div>

        <h2 id="bkPopupTitle">
            Đã lưu thay đổi
        </h2>

        <p>
            Các trạng thái đăng ký tour đã được lưu thành công.
        </p>

        <button
            type="button"
            class="bk-popup-button"
            id="bkPopupClose"
        >
            Đóng
        </button>

    </div>

</div>

<script>
(function() {
    const filter = document.getElementById(
        'bkStatusFilter'
    );

    const rows = Array.from(
        document.querySelectorAll('.bk-booking-row')
    );

    const count = document.getElementById(
        'bkVisibleCount'
    );

    const noData = document.getElementById(
        'bkNoData'
    );

    const saveButton = document.getElementById(
        'bkSaveButton'
    );

    const form = document.getElementById(
        'bkBulkForm'
    );

    const overlay = document.getElementById(
        'bkSuccessOverlay'
    );

    const closeButton = document.getElementById(
        'bkPopupClose'
    );

    const selects = Array.from(
        document.querySelectorAll('.bk-status')
    );

    // =====================================
    // LOC TRANG THAI NGAY TAI TRANG
    // =====================================

    function applyFilter() {
        const chosen = filter.value;

        let visible = 0;

        rows.forEach(function(row) {
            const select = row.querySelector('.bk-status');

            const matches = (
                chosen === '' ||
                (select && select.value === chosen)
            );

            row.hidden = !matches;

            if (matches) {
                visible++;
            }
        });

        count.textContent = visible + ' đăng ký tour';

        if (noData) {
            noData.hidden = visible !== 0;
        }
    }

    // =====================================
    // DEM CAC TRANG THAI DA DOI
    // =====================================

    function updateChanges() {
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

        // Nút lưu giữ một tên cố định theo yêu cầu.
        saveButton.innerHTML =
            '<i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi';

        applyFilter();
    }

    filter.addEventListener(
        'change',
        applyFilter
    );

    selects.forEach(function(select) {
        select.addEventListener(
            'change',
            updateChanges
        );
    });

    // =====================================
    // DONG POPUP
    // =====================================

    function closePopup() {
        overlay.hidden = true;

        // Xoa saved=1 khoi URL
        // de tai lai trang khong hien popup lan nua.
        const url = new URL(window.location.href);
        url.searchParams.delete('saved');

        window.history.replaceState(
            {},
            '',
            url.pathname + url.search
        );
    }

    if (closeButton) {
        closeButton.addEventListener(
            'click',
            closePopup
        );
    }

    overlay.addEventListener('click', function(event) {
        if (event.target === overlay) {
            closePopup();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !overlay.hidden) {
            closePopup();
        }
    });

    form.addEventListener('submit', function() {
        saveButton.disabled = true;
        saveButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';
    });

    // Khi trang vua mo, ap dung bo loc.
    applyFilter();
})();
</script>

</body>
</html>
