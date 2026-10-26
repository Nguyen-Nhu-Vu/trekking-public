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

if (!function_exists('bk_h')) {
    function bk_h($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

// =======================================
// DANH SACH TRANG THAI
// =======================================

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

// =======================================
// CAP NHAT TRANG THAI BOOKING
// KHONG SUA HDV CO DINH CUA TOUR
// =======================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $postedCsrf = (string)($_POST['csrf'] ?? '');

        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
            );
        }

        if (!isset($_POST['update_booking'])) {
            throw new RuntimeException(
                'Thao tác không hợp lệ.'
            );
        }

        $bookingId = (int)($_POST['booking_id'] ?? 0);

        $newStatus = trim(
            (string)($_POST['status'] ?? '')
        );

        if ($bookingId <= 0) {
            throw new RuntimeException(
                'Mã đăng ký tour không hợp lệ.'
            );
        }

        if (!in_array($newStatus, $statuses, true)) {
            throw new RuntimeException(
                'Trạng thái đăng ký không hợp lệ.'
            );
        }

        $check = $pdo->prepare("
            SELECT id
            FROM tour_bookings
            WHERE id = ?
            LIMIT 1
        ");

        $check->execute([$bookingId]);

        if (!$check->fetchColumn()) {
            throw new RuntimeException(
                'Không tìm thấy đăng ký tour.'
            );
        }

        // Chi cap nhat trang thai.
        // Khong thay doi guide_id hay leader_id.
        $update = $pdo->prepare("
            UPDATE tour_bookings
            SET status = ?
            WHERE id = ?
        ");

        $update->execute([
            $newStatus,
            $bookingId
        ]);

        $_SESSION['bookings_notice'] =
            'Đã cập nhật trạng thái đăng ký thành công.';

        $filterRedirect = trim(
            (string)($_POST['current_filter'] ?? '')
        );

        $redirect = 'index.php';

        if (
            $filterRedirect !== '' &&
            in_array($filterRedirect, $statuses, true)
        ) {
            $redirect .= '?status=' .
                rawurlencode($filterRedirect);
        }

        header('Location: ' . $redirect, true, 303);
        exit;

    } catch (Throwable $e) {

        error_log(
            'Booking update: ' . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể cập nhật đăng ký.';
    }
}

if (!empty($_SESSION['bookings_notice'])) {
    $success = $_SESSION['bookings_notice'];
    unset($_SESSION['bookings_notice']);
}

// =======================================
// BO LOC TRANG THAI
// =======================================

$filter = trim(
    (string)($_GET['status'] ?? '')
);

$params = [];

// =======================================
// LAY BOOKING + LEADER CO DINH CUA TOUR
// =======================================
//
// tours.leader_id
//       |
//       v
// trekking_leaders.id
//
// Khong lay HDV tu tour_bookings.guide_id
// nua vi Leader da co dinh theo tour.
//
// LEFT JOIN de booking cu van hien thi
// ke ca tour chua duoc phan Leader.
// =======================================

$sql = "
    SELECT
        b.*,

        t.title AS tour_name,
        t.leader_id AS fixed_leader_id,
        t.tour_guide AS old_guide_name,

        l.id AS leader_profile_id,
        l.fullname AS fixed_leader_name,
        l.nickname AS fixed_leader_nickname,
        l.image AS fixed_leader_image,
        l.experience_years AS leader_experience

    FROM tour_bookings b

    INNER JOIN tours t
        ON t.id = b.tour_id

    LEFT JOIN trekking_leaders l
        ON l.id = t.leader_id
";

if (
    $filter !== '' &&
    in_array($filter, $statuses, true)
) {

    $sql .= " WHERE b.status = ?";

    $params[] = $filter;
}

$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =======================================
// THONG KE
// =======================================

$totalBookings = count($bookings);

$withLeader = 0;

foreach ($bookings as $booking) {
    if (!empty($booking['fixed_leader_name'])) {
        $withLeader++;
    }
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

    <title>
        <?= bk_h($page_title) ?>
    </title>

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
           TRANG QUAN LY DANG KY TOUR
           ===================================== */

        .bk-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .bk-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .bk-stat {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 14px;
        }

        .bk-stat i {
            color: #166534;
        }

        .bk-success {
            background: #dcfce7;
            color: #166534;
            padding: 14px;
            border-radius: 9px;
            margin-bottom: 18px;
        }

        .bk-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px;
            border-radius: 9px;
            margin-bottom: 18px;
        }

        /* =====================================
           BANG BOOKING
           ===================================== */

        .bk-table-wrap {
            background: white;
            border-radius: 14px;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
        }

        .bk-table {
            width: 100%;
            min-width: 1150px;
            border-collapse: collapse;
        }

        .bk-table th {
            padding: 18px 14px;
            background: #f8fafc;
            color: #0f172a;
            font-weight: 800;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .bk-table td {
            padding: 17px 14px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 13px;
        }

        .bk-table tr:hover td {
            background: #f8fafc;
        }

        .bk-code {
            font-weight: 800;
            color: #1e3a5f;
            overflow-wrap: anywhere;
        }

        .bk-phone {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 7px;
        }

        .bk-date {
            color: #ea580c;
            font-weight: 800;
            white-space: nowrap;
        }

        /* =====================================
           LEADER CO DINH
           ===================================== */

        .bk-leader {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 170px;
        }

        .bk-leader-avatar {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            border-radius: 50%;
            object-fit: cover;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
        }

        .bk-leader-placeholder {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #dcfce7;
            color: #166534;
            font-size: 18px;
        }

        .bk-leader-info {
            min-width: 0;
        }

        .bk-leader-name {
            display: block;
            font-weight: 800;
            color: #166534;
            line-height: 1.5;
            text-decoration: none;
        }

        .bk-leader-name:hover {
            color: #ea580c;
            text-decoration: underline;
        }

        .bk-leader-sub {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            color: #64748b;
        }

        .bk-no-leader {
            color: #94a3b8;
            font-style: italic;
            font-size: 12px;
        }

        /* =====================================
           TRANG THAI VA NUT LUU
           ===================================== */

        .bk-select {
            width: 100%;
            min-width: 145px;
            max-width: 230px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 8px;
            background: white;
            font: inherit;
            font-size: 13px;
        }

        .bk-save {
            background: #166534;
            color: white;
            border: none;
            padding: 11px 17px;
            border-radius: 9px;
            font-weight: 800;
            cursor: pointer;
        }

        .bk-save:hover {
            background: #15803d;
        }

        .bk-form {
            margin: 0;
        }

        @media(max-width:700px) {
            .bk-table th,
            .bk-table td {
                padding: 12px;
            }

            .bk-toolbar {
                gap: 10px;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main">

    <!-- THANH TIEU DE -->

    <div class="admin-topbar bk-top">

        <strong>
            <i class="fa-solid fa-person-hiking"></i>
            QUẢN LÝ ĐĂNG KÝ TOUR TREKKING
        </strong>

        <!-- LOC TRANG THAI -->

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
                        <?= $filter === $status
                            ? 'selected'
                            : '' ?>
                    >
                        <?= bk_h($status) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </div>

    <!-- NOI DUNG QUAN LY -->

    <div class="admin-content">

        <!-- THONG BAO -->

        <?php if ($success): ?>

            <div class="bk-success" role="status">
                <i class="fa-solid fa-circle-check"></i>
                <?= bk_h($success) ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="bk-error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= bk_h($error) ?>
            </div>

        <?php endif; ?>

        <!-- THONG KE BOOKING -->

        <div class="bk-toolbar">

            <span class="bk-stat">
                <i class="fa-solid fa-ticket"></i>

                <?= $totalBookings ?>
                đăng ký tour
            </span>

            <span class="bk-stat">
                <i class="fa-solid fa-person-hiking"></i>

                <?= $withLeader ?>
                đăng ký có Leader cố định
            </span>

        </div>

        <!-- BANG QUAN LY -->

        <div class="bk-table-wrap">

            <table class="bk-table">

                <thead>
                    <tr>
                        <th>Mã Booking</th>
                        <th>Khách Hàng</th>
                        <th>Tour</th>
                        <th>Ngày Khởi Hành</th>
                        <th>Số Khách</th>
                        <th>HDV</th>
                        <th>Trạng Thái</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($bookings): ?>

                        <?php foreach ($bookings as $booking): ?>

                            <?php
                            $bookingId = (int)$booking['id'];

                            $formId = 'booking-form-' . $bookingId;

                            $leaderId = (int)(
                                $booking['leader_profile_id'] ?? 0
                            );

                            $leaderName = trim(
                                (string)(
                                    $booking['fixed_leader_name'] ?? ''
                                )
                            );

                            $leaderImage = trim(
                                (string)(
                                    $booking['fixed_leader_image'] ?? ''
                                )
                            );

                            $experience = (int)(
                                $booking['leader_experience'] ?? 0
                            );

                            $leaderUrl = rtrim(
                                BASE_URL,
                                '/'
                            ) . '/pages/leader-detail.php?id=' .
                                $leaderId;
                            ?>

                            <tr>

                                <!-- MA BOOKING -->

                                <td class="bk-code">

                                    <?= bk_h(
                                        $booking['booking_code'] ?? ''
                                    ) ?>

                                </td>

                                <!-- KHACH HANG -->

                                <td>

                                    <?= bk_h(
                                        $booking['fullname'] ?? ''
                                    ) ?>

                                    <small class="bk-phone">

                                        <?= bk_h(
                                            $booking['phone'] ?? ''
                                        ) ?>

                                    </small>

                                </td>

                                <!-- TOUR -->

                                <td>

                                    <strong>
                                        <?= bk_h(
                                            $booking['tour_name']
                                        ) ?>
                                    </strong>

                                </td>

                                <!-- NGAY KHOI HANH -->

                                <td class="bk-date">

                                    <?php if (
                                        !empty(
                                            $booking['departure_date']
                                        )
                                    ): ?>

                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $booking['departure_date']
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        Tự thỏa thuận

                                    <?php endif; ?>

                                </td>

                                <!-- SO KHACH -->

                                <td>

                                    <?= (int)(
                                        $booking['number_people'] ?? 0
                                    ) ?>

                                    người

                                </td>

                                <!-- HDV CO DINH CUA TOUR -->

                                <td>

                                    <?php if ($leaderName !== ''): ?>

                                        <div class="bk-leader">

                                            <?php if ($leaderImage !== ''): ?>

                                                <img
                                                    class="bk-leader-avatar"
                                                    src="<?= bk_h(
                                                        $leaderImage
                                                    ) ?>"
                                                    alt="<?= bk_h(
                                                        $leaderName
                                                    ) ?>"
                                                    loading="lazy"
                                                >

                                            <?php else: ?>

                                                <div class="bk-leader-placeholder">

                                                    <i class="fa-solid fa-person-hiking"></i>

                                                </div>

                                            <?php endif; ?>

                                            <div class="bk-leader-info">

                                                <a
                                                    href="<?= bk_h(
                                                        $leaderUrl
                                                    ) ?>"
                                                    class="bk-leader-name"
                                                    target="_blank"
                                                    rel="noopener"
                                                >

                                                    <?= bk_h(
                                                        $leaderName
                                                    ) ?>

                                                </a>

                                                <?php if ($experience > 0): ?>

                                                    <span class="bk-leader-sub">

                                                        <?= $experience ?>

                                                        năm kinh nghiệm

                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    <?php else: ?>

                                        <span class="bk-no-leader">

                                            Chưa gán HDV cho tour

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- TRANG THAI -->

                                <td>

                                    <select
                                        name="status"
                                        form="<?= bk_h($formId) ?>"
                                        class="bk-select"
                                    >

                                        <?php foreach (
                                            $statuses as $status
                                        ): ?>

                                            <option
                                                value="<?= bk_h(
                                                    $status
                                                ) ?>"
                                                <?= (
                                                    $booking['status']
                                                    === $status
                                                )
                                                    ? 'selected'
                                                    : '' ?>
                                            >

                                                <?= bk_h(
                                                    $status
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>

                                <!-- NUT LUU -->

                                <td>

                                    <form
                                        id="<?= bk_h($formId) ?>"
                                        method="POST"
                                        class="bk-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= bk_h($csrf) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?= $bookingId ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="current_filter"
                                            value="<?= bk_h($filter) ?>"
                                        >

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

                            <td
                                colspan="8"
                                style="
                                    text-align:center;
                                    padding:45px;
                                    color:#64748b;
                                "
                            >

                                Không có đăng ký tour phù hợp.

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
