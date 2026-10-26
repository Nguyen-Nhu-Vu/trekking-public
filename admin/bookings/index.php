<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

requireStaff();

$current_page = 'bookings';
$page_title   = 'Quản Lý Đăng Ký Tour - VNN Travel';

if (!function_exists('bk_h')) {
    function bk_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================================================
   DANH SÁCH TRẠNG THÁI
========================================================= */
$statuses = ['Chờ xác nhận', 'Đã xác nhận', 'Đã thanh toán', 'Đã hoàn thành', 'Đã hủy'];

$error   = '';
$success = '';

/* =========================================================
   CSRF
========================================================= */
if (empty($_SESSION['bookings_csrf'])) {
    $_SESSION['bookings_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['bookings_csrf'];

/* =========================================================
   LƯU THAY ĐỔI HÀNG LOẠT
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_changes'])) {
    try {
        $postedCsrf = (string)($_POST['csrf'] ?? '');
        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');
        }

        $postedStatuses = $_POST['status'] ?? [];

        if (!is_array($postedStatuses)) {
            throw new RuntimeException('Dữ liệu cập nhật không hợp lệ.');
        }

        $pdo->beginTransaction();

        $checkStmt  = $pdo->prepare("SELECT status FROM tour_bookings WHERE id = ? LIMIT 1");
        $updateStmt = $pdo->prepare("UPDATE tour_bookings SET status = ? WHERE id = ?");

        $updatedCount = 0;

        foreach ($postedStatuses as $bookingId => $newStatus) {
            $bookingId = (int)$bookingId;
            $newStatus = trim((string)$newStatus);

            if ($bookingId <= 0) {
                continue;
            }

            if (!in_array($newStatus, $statuses, true)) {
                continue;
            }

            $checkStmt->execute([$bookingId]);
            $oldStatus = $checkStmt->fetchColumn();

            if ($oldStatus === false) {
                continue;
            }

            if ($oldStatus !== $newStatus) {
                $updateStmt->execute([$newStatus, $bookingId]);
                $updatedCount++;
            }
        }

        $pdo->commit();

        $_SESSION['bookings_notice'] = $updatedCount > 0
            ? "Đã lưu {$updatedCount} thay đổi thành công."
            : "Không có thay đổi nào để lưu.";

        $filterRedirect = trim((string)($_POST['current_filter'] ?? ''));
        if ($filterRedirect !== '' && in_array($filterRedirect, $statuses, true)) {
            header('Location: index.php?status=' . rawurlencode($filterRedirect));
        } else {
            header('Location: index.php');
        }
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Bookings bulk update error: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Không thể lưu thay đổi. Vui lòng thử lại.';
    }
}

if (!empty($_SESSION['bookings_notice'])) {
    $success = $_SESSION['bookings_notice'];
    unset($_SESSION['bookings_notice']);
}

/* =========================================================
   BỘ LỌC TRẠNG THÁI
========================================================= */
$filter = trim((string)($_GET['status'] ?? ''));
$params = [];

$sql = "
    SELECT
        b.*,
        t.title AS tour_name,
        t.leader_id AS fixed_leader_id,
        l.fullname AS fixed_leader_name,
        l.nickname AS fixed_leader_nickname,
        l.image AS fixed_leader_image,
        l.experience_years AS leader_experience
    FROM tour_bookings b
    INNER JOIN tours t ON t.id = b.tour_id
    LEFT JOIN trekking_leaders l ON l.id = t.leader_id
";

if ($filter !== '' && in_array($filter, $statuses, true)) {
    $sql .= " WHERE b.status = ? ";
    $params[] = $filter;
}

$sql .= " ORDER BY b.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalBookings = count($bookings);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= bk_h($page_title) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        *{
            box-sizing:border-box;
        }
        body{
            margin:0;
            font-family:Arial, Helvetica, sans-serif;
            background:#f4f7fb;
            color:#1f2d3d;
        }
        .admin-layout{
            display:flex;
            min-height:100vh;
        }
        .admin-main{
            flex:1;
            padding:24px;
        }
        .page-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            margin-bottom:18px;
        }
        .page-header h1{
            margin:0;
            font-size:20px;
            font-weight:800;
            color:#17345f;
        }
        .filter-box form{
            display:flex;
            gap:10px;
            align-items:center;
        }
        .filter-box select{
            min-width:180px;
            padding:12px 14px;
            border:1px solid #d9e2ef;
            border-radius:12px;
            background:#fff;
            font-size:15px;
        }

        .alert{
            padding:14px 16px;
            border-radius:12px;
            margin-bottom:16px;
            font-size:14px;
            line-height:1.5;
        }
        .alert-success{
            background:#eaf8ee;
            color:#1c7a35;
            border:1px solid #bfe8c8;
        }
        .alert-error{
            background:#fff1f1;
            color:#c0392b;
            border:1px solid #f1c0c0;
        }

        .toolbar{
            display:flex;
            flex-wrap:wrap;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            margin-bottom:16px;
            padding:18px 20px;
            background:#eef3f9;
            border-radius:18px;
            border:1px solid #dfe7f1;
        }
        .toolbar-left{
            display:flex;
            align-items:center;
            gap:20px;
            flex-wrap:wrap;
        }
        .booking-count{
            display:flex;
            align-items:center;
            gap:10px;
            font-size:18px;
            color:#566b86;
        }
        .booking-count i{
            color:#146c43;
            font-size:20px;
        }

        .btn{
            border:none;
            border-radius:12px;
            padding:12px 18px;
            font-size:15px;
            font-weight:700;
            cursor:pointer;
            transition:.2s;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            gap:8px;
        }
        .btn-save{
            background:#166534;
            color:#fff;
        }
        .btn-save:hover{
            background:#14532d;
        }

        .table-card{
            background:#fff;
            border-radius:20px;
            overflow:hidden;
            border:1px solid #e3eaf3;
        }

        table{
            width:100%;
            border-collapse:collapse;
        }
        thead th{
            background:#ffffff;
            color:#0f274e;
            font-size:18px;
            font-weight:800;
            padding:26px 18px;
            text-align:left;
            border-bottom:1px solid #e6edf5;
        }
        tbody td{
            padding:22px 18px;
            border-bottom:1px solid #edf2f7;
            vertical-align:middle;
            font-size:15px;
            color:#243b53;
        }
        tbody tr:hover{
            background:#fafcff;
        }

        .booking-code{
            font-weight:800;
            font-size:18px;
            line-height:1.45;
            color:#17345f;
            word-break:break-word;
        }
        .customer-name{
            font-size:18px;
            line-height:1.4;
            color:#243b53;
            margin-bottom:10px;
        }
        .customer-phone{
            color:#74839a;
            font-size:15px;
        }
        .tour-name{
            font-size:18px;
            font-weight:700;
            color:#243b53;
            line-height:1.4;
        }
        .depart-date{
            color:#f35b04;
            font-weight:800;
            font-size:18px;
        }
        .guest-count{
            font-size:18px;
            color:#2b3e55;
            line-height:1.5;
        }

        .leader-name{
            font-size:18px;
            font-weight:800;
            color:#166534;
            margin-bottom:8px;
            line-height:1.4;
        }
        .leader-exp{
            color:#6b7c93;
            font-size:15px;
        }
        .leader-empty{
            color:#9aa8b8;
            font-style:italic;
            font-size:15px;
        }

        .status-select{
            min-width:180px;
            padding:12px 14px;
            border:1px solid #ccd8e6;
            border-radius:12px;
            background:#fff;
            font-size:15px;
            color:#22354a;
        }

        .empty-box{
            padding:40px 20px;
            text-align:center;
            color:#7a8ca5;
            font-size:16px;
        }

        @media (max-width: 1200px){
            .table-card{
                overflow-x:auto;
            }
            table{
                min-width:1200px;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fa-solid fa-person-hiking"></i> QUẢN LÝ ĐĂNG KÝ TOUR TREKKING</h1>

            <div class="filter-box">
                <form method="get">
                    <select name="status" onchange="this.form.submit()">
                        <option value="">Tất cả trạng thái</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= bk_h($status) ?>" <?= $filter === $status ? 'selected' : '' ?>>
                                <?= bk_h($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= bk_h($success) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= bk_h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= bk_h($csrf) ?>">
            <input type="hidden" name="current_filter" value="<?= bk_h($filter) ?>">

            <div class="toolbar">
                <div class="toolbar-left">
                    <div class="booking-count">
                        <i class="fa-regular fa-credit-card"></i>
                        <span><?= $totalBookings ?> đăng ký tour</span>
                    </div>
                </div>

                <button type="submit" name="save_all_changes" class="btn btn-save">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Lưu thay đổi
                </button>
            </div>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th style="width:180px;">Mã Booking</th>
                            <th style="width:260px;">Khách Hàng</th>
                            <th style="width:280px;">Tour</th>
                            <th style="width:150px;">Ngày Khởi Hành</th>
                            <th style="width:120px;">Số Khách</th>
                            <th style="width:260px;">HDV</th>
                            <th style="width:210px;">Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <div class="booking-code">
                                        <?= nl2br(bk_h(chunk_split((string)$booking['booking_code'], 12, "\n"))) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="customer-name">
                                        <?= bk_h($booking['full_name'] ?? $booking['name'] ?? '---') ?>
                                    </div>
                                    <div class="customer-phone">
                                        <?= bk_h($booking['phone'] ?? '---') ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="tour-name">
                                        <?= bk_h($booking['tour_name'] ?? '---') ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="depart-date">
                                        <?= !empty($booking['departure_date']) ? bk_h(date('d/m/Y', strtotime($booking['departure_date']))) : '---' ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="guest-count">
                                        <?= (int)($booking['people_count'] ?? 0) ?> người
                                    </div>
                                </td>

                                <td>
                                    <?php if (!empty($booking['fixed_leader_name'])): ?>
                                        <div class="leader-name">
                                            <?= bk_h($booking['fixed_leader_name']) ?>
                                        </div>
                                        <div class="leader-exp">
                                            <?= (int)($booking['leader_experience'] ?? 0) ?> năm kinh nghiệm
                                        </div>
                                    <?php else: ?>
                                        <div class="leader-empty">Chưa gán HDV cho tour</div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <select name="status[<?= (int)$booking['id'] ?>]" class="status-select">
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= bk_h($status) ?>" <?= (($booking['status'] ?? '') === $status) ? 'selected' : '' ?>>
                                                <?= bk_h($status) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-box">Không có đăng ký tour nào.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </main>
</div>
</body>
</html>
