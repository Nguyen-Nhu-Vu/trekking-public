
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if (getRole() !== 'manager') {
    http_response_code(403);
    exit('Chỉ quản lý được xem lịch sử giao dịch.');
}

$current_page = 'activity-history';
$page_title = 'Lịch sử xử lý giao dịch - VNN Travel';

if (!function_exists('vnnAH')) {
    function vnnAH($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('vnnAHDateValid')) {
    function vnnAHDateValid(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        return $date !== false
            && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('vnnAHTargetName')) {
    function vnnAHTargetName(string $target): string
    {
        return match ($target) {
            'orders' => 'Đơn hàng',
            'tour_bookings' => 'Đăng ký tour',
            'products' => 'Kho hàng',
            default => 'Khác'
        };
    }
}

if (!function_exists('vnnAHActionClass')) {
    function vnnAHActionClass(string $action): string
    {
        $action = mb_strtolower($action, 'UTF-8');

        if (str_contains($action, 'hủy')) {
            return 'cancel';
        }

        if (
            str_contains($action, 'nhập thêm') ||
            str_contains($action, 'nhập kho')
        ) {
            return 'stock';
        }

        if (
            str_contains($action, 'thanh toán') ||
            str_contains($action, 'hoàn thành') ||
            str_contains($action, 'hoàn tất')
        ) {
            return 'payment';
        }

        return 'update';
    }
}

/*
 * HIEN THI GIA TRI TRUOC / SAU
 *
 * Du lieu nhap kho thuong co dang:
 *
 * Truoc:
 * Ten san pham | Ton truoc: 100
 *
 * Sau:
 * Nhap them: 5 | Ton sau: 105
 *
 * Tach cac phan thanh tung dong rieng.
 * Khong thay doi du lieu goc trong CSDL.
 */
if (!function_exists('vnnAHValue')) {
    function vnnAHValue($value, string $type): string
    {
        $text = trim((string)($value ?? ''));

        if ($text === '') {
            return '<span class="ah-value-line">—</span>';
        }

        $parts = explode(' | ', $text);
        $html = '';

        foreach ($parts as $index => $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $class = 'ah-value-line';

            $lower = mb_strtolower($part, 'UTF-8');

            if (
                str_starts_with($lower, 'tồn trước:') ||
                str_starts_with($lower, 'tồn sau:')
            ) {
                $class .= ' ah-stock-number';
            }

            if (str_starts_with($lower, 'nhập thêm:')) {
                $class .= ' ah-stock-added';
            }

            if ($type === 'products' && $index === 0
                && !str_contains($part, ':')) {
                $class .= ' ah-product-name';
            }

            $html .= '<span class="' . $class . '">'
                . vnnAH($part)
                . '</span>';
        }

        return $html !== ''
            ? $html
            : '<span class="ah-value-line">—</span>';
    }
}

/*
 * BO LOC GIAO DICH
 */

$types = [
    '' => 'Tất cả giao dịch',
    'orders' => 'Đơn hàng',
    'tour_bookings' => 'Đăng ký tour',
    'products' => 'Kho hàng'
];

$type = (string)($_GET['type'] ?? '');

if (!array_key_exists($type, $types)) {
    $type = '';
}

$keyword = mb_substr(
    trim((string)($_GET['keyword'] ?? '')),
    0,
    100,
    'UTF-8'
);

$start = (string)($_GET['start'] ?? '');
$end = (string)($_GET['end'] ?? '');

if ($start !== '' && !vnnAHDateValid($start)) {
    $start = '';
}

if ($end !== '' && !vnnAHDateValid($end)) {
    $end = '';
}

/*
 * CHI HIEN THI LICH SU DON HANG,
 * TOUR VA KHO.
 *
 * KHONG HIEN THI CAC BAN GHI DUOC
 * NHAN DIEN LA THAO TAC CUA ADMIN.
 *
 * KHONG XOA BAT KY BAN GHI NAO.
 */

$where = [
    "a.category IN ('transactions', 'inventory')",

    "a.target_type IN (
        'orders',
        'tour_bookings',
        'products'
    )",

    "NOT EXISTS (
        SELECT 1
        FROM users u
        WHERE u.id = a.actor_id
          AND u.role = 'admin'
    )",

    "LOWER(COALESCE(a.actor_name, ''))
        NOT LIKE '%(admin)%'",

    "LOWER(COALESCE(a.actor_name, ''))
        NOT LIKE '%quản trị viên%'"
];

$params = [];

if ($type !== '') {
    $where[] = 'a.target_type = ?';
    $params[] = $type;
}

if ($keyword !== '') {
    $where[] = "(
        a.actor_name LIKE ?
        OR a.action LIKE ?
        OR a.target_id LIKE ?
        OR a.old_value LIKE ?
        OR a.new_value LIKE ?
    )";

    for ($i = 0; $i < 5; $i++) {
        $params[] = '%' . $keyword . '%';
    }
}

if ($start !== '') {
    $where[] = 'a.created_at >= ?';
    $params[] = $start . ' 00:00:00';
}

if ($end !== '') {
    $where[] = 'a.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $end;
}

$whereSql = implode(' AND ', $where);

/*
 * PHAN TRANG
 */

$pageSize = 20;

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$total = 0;
$pageCount = 1;
$rows = [];
$loadError = '';

try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM admin_audit_logs a
        WHERE $whereSql
    ");

    $countStmt->execute($params);

    $total = (int)$countStmt->fetchColumn();

    $pageCount = max(
        1,
        (int)ceil($total / $pageSize)
    );

    $page = min($page, $pageCount);

    $offset = ($page - 1) * $pageSize;

    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.actor_id,
            a.actor_name,
            a.action,
            a.target_type,
            a.target_id,
            a.old_value,
            a.new_value,
            a.created_at
        FROM admin_audit_logs a
        WHERE $whereSql
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT $pageSize
        OFFSET $offset
    ");

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        'Activity history error: ' .
        $e->getMessage()
    );

    $loadError = 'Không thể tải lịch sử giao dịch.';
}

if (!function_exists('vnnAHPageUrl')) {
    function vnnAHPageUrl(int $number): string
    {
        $query = $_GET;
        unset($query['partial']);

        $query['page'] = $number;

        return 'activity-history.php?' .
            http_build_query($query);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width,initial-scale=1">

<title><?= vnnAH($page_title) ?></title>

<link rel="stylesheet"
      href="../assets/css/style.css">

<link rel="stylesheet"
      href="../assets/css/admin.css">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
* {
    box-sizing: border-box;
}

.vnn-history-page {
    flex: 1 1 auto;
    min-width: 0;
    max-width: 100%;
    min-height: 100vh;
    padding: 24px;
    background: #f1f5f9;
    color: #173454;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
}

.vnn-history-page * {
    box-sizing: border-box;
}

.vnn-history-page input,
.vnn-history-page select,
.vnn-history-page button {
    font-family: inherit;
}

.ah-panel {
    width: 100%;
    min-width: 0;
    padding: 22px;
    margin-bottom: 18px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
}

.ah-heading {
    margin: 0 0 10px;
    color: #102e52;
    font-size: 21px;
    font-weight: 800;
    line-height: 1.5;
}

.ah-heading i {
    color: #166534;
    margin-right: 8px;
}

.ah-description {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

/* BO LOC */

.ah-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
}

.ah-field {
    flex: 1 1 150px;
    min-width: 150px;
}

.ah-field.search {
    flex: 2 1 250px;
    min-width: 220px;
}

.ah-field label {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
}

.ah-input {
    display: block;
    width: 100%;
    height: 43px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #173454;
    font-size: 13px;
}

.ah-input:focus {
    outline: none;
    border-color: #16a34a;
}

.ah-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 43px;
    padding: 0 17px;
    border: none;
    border-radius: 8px;
    background: #166534;
    color: #fff !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
}

.ah-button.reset {
    background: #e2e8f0;
    color: #334155 !important;
}

/* THANH THONG TIN */

.ah-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 17px;
}

.ah-total {
    color: #173454;
    font-size: 16px;
    font-weight: 800;
}

.ah-sync {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #64748b;
    font-size: 12px;
}

.ah-sync i {
    color: #16a34a;
}

/* BANG LICH SU */

.ah-scroll {
    display: block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
}

.vnn-history-page .ah-table {
    width: 100% !important;
    min-width: 1250px !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
    border-spacing: 0 !important;
    margin: 0 !important;
    background: #fff !important;
}

.vnn-history-page .ah-table thead th {
    padding: 16px 14px !important;
    border: 0 !important;
    border-bottom: 1px solid #e2e8f0 !important;
    background: #f8fafc !important;
    color: #102e52 !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    line-height: 1.6 !important;
    text-align: left !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
}

.vnn-history-page .ah-table tbody td {
    min-width: 0 !important;
    max-width: none !important;
    padding: 15px 14px !important;
    border: 0 !important;
    border-bottom: 1px solid #e2e8f0 !important;
    color: #334155 !important;
    background: transparent !important;
    font-size: 12px !important;
    line-height: 1.6 !important;
    font-weight: 400 !important;
    text-align: left !important;
    vertical-align: middle !important;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
}

.vnn-history-page .ah-table tbody tr:last-child td {
    border-bottom: 0 !important;
}

.vnn-history-page .ah-table tbody tr:hover {
    background: #f8fafc;
}

/* DO RONG COT CO DINH */

.ah-col-time {
    width: 12%;
}

.ah-col-actor {
    width: 15%;
}

.ah-col-action {
    width: 18%;
}

.ah-col-type {
    width: 11%;
}

.ah-col-id {
    width: 7%;
}

.ah-col-before {
    width: 19%;
}

.ah-col-after {
    width: 18%;
}

/* COT THOI GIAN */

.ah-date {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    text-align: left;
}

.ah-date strong {
    display: block;
    color: #173454;
    font-weight: 800;
    white-space: nowrap;
}

.ah-date small {
    display: block;
    color: #64748b;
    font-size: 12px;
    white-space: nowrap;
}

/* COT NGUOI THUC HIEN */

.ah-actor {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 8px;
    width: 100%;
    text-align: left;
    font-weight: 700;
    color: #173454;
}

.ah-actor i {
    flex-shrink: 0;
    margin-top: 4px;
    color: #64748b;
}

.ah-actor span {
    min-width: 0;
    overflow-wrap: break-word;
}

/* COT HANH DONG */

.ah-action {
    display: inline-block;
    max-width: 100%;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.6;
    text-align: left !important;
    white-space: normal;
    overflow-wrap: break-word;
}

.ah-action.update {
    color: #1d4ed8;
    background: #eff6ff;
}

.ah-action.cancel {
    color: #b91c1c;
    background: #fee2e2;
}

.ah-action.payment {
    color: #166534;
    background: #dcfce7;
}

.ah-action.stock {
    color: #92400e;
    background: #fef3c7;
}

/* COT DOI TUONG VA MA */

.ah-type-name {
    display: block;
    font-weight: 700;
    text-align: left;
    color: #475569;
}

.ah-target-id {
    display: block;
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
    color: #173454;
    text-align: left;
}

/*
 * SUA LOI LECH KHUNG
 *
 * Hai cot truoc / sau:
 * - Luon can trai
 * - Khong cat chu
 * - Khong de noi dung tran
 * - Ten san pham nam rieng
 * - So luong nam dong rieng
 */

.vnn-history-page .ah-table td.ah-data-cell {
    text-align: left !important;
    vertical-align: middle !important;
    padding: 13px 12px !important;
}

.vnn-history-page .ah-value-box {
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-items: flex-start !important;

    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    height: auto !important;
    min-height: 64px !important;

    margin: 0 !important;
    padding: 11px 13px !important;

    border: none !important;
    border-radius: 9px !important;

    overflow: visible !important;
    text-align: left !important;
    text-indent: 0 !important;

    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: anywhere !important;

    font-size: 12px !important;
    line-height: 1.65 !important;
}

.vnn-history-page .ah-value-box.before {
    background: #f1f5f9 !important;
    color: #475569 !important;
}

.vnn-history-page .ah-value-box.after {
    background: #f0fdf4 !important;
    color: #166534 !important;
}

.vnn-history-page .ah-value-line {
    display: block !important;
    flex: 0 0 auto !important;

    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;

    margin: 0 !important;
    padding: 0 !important;

    position: static !important;
    float: none !important;

    text-align: left !important;
    text-indent: 0 !important;

    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: anywhere !important;

    line-height: 1.65 !important;
}

.vnn-history-page .ah-value-line + .ah-value-line {
    margin-top: 6px !important;
}

.vnn-history-page .ah-product-name {
    color: #334155 !important;
    font-weight: 600 !important;
}

.vnn-history-page .ah-stock-number {
    font-weight: 800 !important;
}

.vnn-history-page .ah-stock-added {
    font-weight: 700 !important;
}

/* THONG BAO VA PHAN TRANG */

.ah-empty {
    padding: 35px 15px !important;
    text-align: center !important;
    color: #64748b !important;
}

.ah-error {
    margin-bottom: 17px;
    padding: 13px 15px;
    border-radius: 8px;
    background: #fee2e2;
    color: #991b1b;
}

.ah-pages {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 20px;
}

.ah-pages a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    color: #173454;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.ah-pages a[aria-current="page"] {
    color: white;
    background: #166534;
    border-color: #166534;
}

@media(max-width:750px) {
    .vnn-history-page {
        padding: 12px;
    }

    .ah-panel {
        padding: 15px;
    }

    .ah-field,
    .ah-field.search {
        flex: 1 1 100%;
        min-width: 0;
    }

    .ah-heading {
        font-size: 18px;
    }
}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="admin-main vnn-history-page">

    <section class="ah-panel">

        <h1 class="ah-heading">
            <i class="fa-solid fa-clock-rotate-left"></i>
            LỊCH SỬ XỬ LÝ GIAO DỊCH
        </h1>

        <p class="ah-description">
            Theo dõi xử lý đơn hàng, duyệt tour,
            xác nhận thanh toán, hủy đơn và nhập kho.
            Dữ liệu tự động cập nhật mỗi 10 giây.
        </p>

    </section>

    <section class="ah-panel">

        <form
            class="ah-filter"
            method="GET"
            action="activity-history.php"
        >

            <div class="ah-field">
                <label for="type">
                    Loại giao dịch
                </label>

                <select
                    class="ah-input"
                    name="type"
                    id="type"
                >
                    <?php foreach ($types as $value => $label): ?>

                        <option
                            value="<?= vnnAH($value) ?>"
                            <?= $type === $value
                                ? 'selected'
                                : '' ?>
                        >
                            <?= vnnAH($label) ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ah-field search">

                <label for="keyword">
                    Tìm kiếm
                </label>

                <input
                    class="ah-input"
                    type="search"
                    id="keyword"
                    name="keyword"
                    value="<?= vnnAH($keyword) ?>"
                    placeholder="Người xử lý, mã đơn, trạng thái..."
                >

            </div>

            <div class="ah-field">

                <label for="start">
                    Từ ngày
                </label>

                <input
                    class="ah-input"
                    type="date"
                    id="start"
                    name="start"
                    value="<?= vnnAH($start) ?>"
                >

            </div>

            <div class="ah-field">

                <label for="end">
                    Đến ngày
                </label>

                <input
                    class="ah-input"
                    type="date"
                    id="end"
                    name="end"
                    value="<?= vnnAH($end) ?>"
                >

            </div>

            <button
                class="ah-button"
                type="submit"
            >
                <i class="fa-solid fa-filter"></i>
                Lọc
            </button>

            <a
                href="activity-history.php"
                class="ah-button reset"
            >
                Xóa lọc
            </a>

        </form>

    </section>

    <section class="ah-panel">

        <div class="ah-table-header">

            <strong class="ah-total" id="historyTotal">
                <?= number_format($total, 0, ',', '.') ?>
                bản ghi
            </strong>

            <span class="ah-sync" id="historySync">
                <i class="fa-solid fa-circle-check"></i>
                Dữ liệu hiện tại
            </span>

        </div>

        <?php if ($loadError !== ''): ?>

            <div class="ah-error">
                <?= vnnAH($loadError) ?>
            </div>

        <?php endif; ?>

        <div class="ah-scroll">

            <table class="ah-table">

                <colgroup>
                    <col class="ah-col-time">
                    <col class="ah-col-actor">
                    <col class="ah-col-action">
                    <col class="ah-col-type">
                    <col class="ah-col-id">
                    <col class="ah-col-before">
                    <col class="ah-col-after">
                </colgroup>

                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Người thực hiện</th>
                        <th>Hành động</th>
                        <th>Đối tượng</th>
                        <th>Mã</th>
                        <th>Trước</th>
                        <th>Sau</th>
                    </tr>
                </thead>

                <tbody id="historyRows">

                <?php if (empty($rows)): ?>

                    <tr>
                        <td colspan="7" class="ah-empty">
                            Chưa có lịch sử giao dịch phù hợp.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($rows as $row): ?>

                        <?php
                        $timestamp = strtotime(
                            (string)($row['created_at'] ?? '')
                        );

                        $date = $timestamp
                            ? date('d/m/Y', $timestamp)
                            : '—';

                        $time = $timestamp
                            ? date('H:i:s', $timestamp)
                            : '';

                        $actor = trim(
                            (string)($row['actor_name'] ?? '')
                        );

                        if ($actor === '') {
                            $actor = 'Không xác định';
                        }

                        $action = (string)(
                            $row['action'] ?? ''
                        );

                        $target = (string)(
                            $row['target_type'] ?? ''
                        );

                        $targetId = (string)(
                            $row['target_id'] ?? ''
                        );
                        ?>

                        <tr>

                            <td>
                                <div class="ah-date">
                                    <strong>
                                        <?= vnnAH($date) ?>
                                    </strong>

                                    <small>
                                        <?= vnnAH($time) ?>
                                    </small>
                                </div>
                            </td>

                            <td>
                                <div class="ah-actor">
                                    <i class="fa-solid fa-user"></i>

                                    <span>
                                        <?= vnnAH($actor) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <span class="ah-action <?= vnnAH(
                                    vnnAHActionClass($action)
                                ) ?>">
                                    <?= vnnAH($action) ?>
                                </span>
                            </td>

                            <td>
                                <span class="ah-type-name">
                                    <?= vnnAH(
                                        vnnAHTargetName($target)
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span class="ah-target-id">
                                    #<?= vnnAH($targetId) ?>
                                </span>
                            </td>

                            <td class="ah-data-cell">
                                <div class="ah-value-box before">

                                    <?= vnnAHValue(
                                        $row['old_value'] ?? '',
                                        $target
                                    ) ?>

                                </div>
                            </td>

                            <td class="ah-data-cell">
                                <div class="ah-value-box after">

                                    <?= vnnAHValue(
                                        $row['new_value'] ?? '',
                                        $target
                                    ) ?>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

        <?php if ($pageCount > 1): ?>

            <nav
                class="ah-pages"
                id="historyPagination"
                aria-label="Phân trang lịch sử"
            >

                <?php for ($n = 1; $n <= $pageCount; $n++): ?>

                    <?php
                    $query = $_GET;
                    unset($query['partial']);
                    $query['page'] = $n;

                    $url = 'activity-history.php?' .
                        http_build_query($query);
                    ?>

                    <a
                        href="<?= vnnAH($url) ?>"
                        <?= $n === $page
                            ? 'aria-current="page"'
                            : '' ?>
                    >
                        <?= $n ?>
                    </a>

                <?php endfor; ?>

            </nav>

        <?php endif; ?>

    </section>

</main>

<script>
(function() {
    'use strict';

    const rows = document.getElementById(
        'historyRows'
    );

    const total = document.getElementById(
        'historyTotal'
    );

    const sync = document.getElementById(
        'historySync'
    );

    const pagination = document.getElementById(
        'historyPagination'
    );

    let loading = false;

    async function refreshHistory() {

        if (
            document.hidden ||
            loading ||
            !rows
        ) {
            return;
        }

        loading = true;

        try {
            const url = new URL(
                window.location.href
            );

            url.searchParams.delete('partial');

            const response = await fetch(
                url.toString(),
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'text/html'
                    }
                }
            );

            if (!response.ok) {
                throw new Error(
                    'Không thể tải lịch sử.'
                );
            }

            const html = await response.text();

            if (
                html.includes('Warning:') ||
                html.includes('Fatal error:')
            ) {
                throw new Error(
                    'Máy chủ trả về cảnh báo PHP.'
                );
            }

            const parser = new DOMParser();

            const updated = parser.parseFromString(
                html,
                'text/html'
            );

            const updatedRows = updated.getElementById(
                'historyRows'
            );

            const updatedTotal = updated.getElementById(
                'historyTotal'
            );

            const updatedPages = updated.getElementById(
                'historyPagination'
            );

            if (!updatedRows || !updatedTotal) {
                throw new Error(
                    'Phản hồi không hợp lệ.'
                );
            }

            rows.innerHTML = updatedRows.innerHTML;
            total.textContent = updatedTotal.textContent;

            if (pagination) {
                pagination.innerHTML = updatedPages
                    ? updatedPages.innerHTML
                    : '';
            }

            sync.innerHTML =
                '<i class="fa-solid fa-circle-check"></i>' +
                ' Cập nhật: ' +
                new Date().toLocaleTimeString('vi-VN');

        } catch (error) {

            sync.innerHTML =
                '<i class="fa-solid fa-triangle-exclamation"></i>' +
                ' Chưa thể làm mới dữ liệu';

        } finally {
            loading = false;
        }
    }

    setInterval(refreshHistory, 10000);

    document.addEventListener(
        'visibilitychange',
        function() {
            if (!document.hidden) {
                refreshHistory();
            }
        }
    );

})();
</script>

</body>
</html>
