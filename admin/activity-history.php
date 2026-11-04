
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

if (!function_exists('vnnActivityEscape')) {
    function vnnActivityEscape($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('vnnActivityValidDate')) {
    function vnnActivityValidDate(string $value): bool
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

if (!function_exists('vnnActivityTargetName')) {
    function vnnActivityTargetName(string $target): string
    {
        return match ($target) {
            'orders' => 'Đơn hàng',
            'tour_bookings' => 'Đăng ký tour',
            'products' => 'Kho hàng',
            default => 'Khác'
        };
    }
}

if (!function_exists('vnnActivityActionStyle')) {
    function vnnActivityActionStyle(string $action): string
    {
        $action = mb_strtolower($action, 'UTF-8');

        if (str_contains($action, 'hủy')) {
            return 'cancel';
        }

        if (str_contains($action, 'nhập thêm')) {
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

if (!function_exists('vnnActivityTime')) {
    function vnnActivityTime($value): array
    {
        $timestamp = strtotime((string)$value);

        if (!$timestamp) {
            return [
                'date' => '—',
                'time' => ''
            ];
        }

        return [
            'date' => date('d/m/Y', $timestamp),
            'time' => date('H:i:s', $timestamp)
        ];
    }
}

/*
 * BO LOC
 *
 * Giu nguyen chuc nang loc loai giao dich,
 * tim kiem va loc theo khoang ngay.
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

if (
    $start !== '' &&
    !vnnActivityValidDate($start)
) {
    $start = '';
}

if (
    $end !== '' &&
    !vnnActivityValidDate($end)
) {
    $end = '';
}

/*
 * LOC LICH SU
 *
 * - Chi lay giao dich don hang, tour va kho.
 * - Khong hien thi ban ghi cua tai khoan
 *   dang co vai tro Admin.
 * - Khong xoa du lieu lich su cu.
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
 * LAY DU LIEU VA PHAN TRANG
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
    $countSql = "
        SELECT COUNT(*)
        FROM admin_audit_logs a
        WHERE $whereSql
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);

    $total = (int)$countStmt->fetchColumn();

    $pageCount = max(
        1,
        (int)ceil($total / $pageSize)
    );

    $page = min($page, $pageCount);

    $offset = ($page - 1) * $pageSize;

    $sql = "
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
        ORDER BY
            a.created_at DESC,
            a.id DESC
        LIMIT $pageSize
        OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        'VNN activity history: ' .
        $e->getMessage()
    );

    $loadError = 'Không thể tải lịch sử giao dịch.';
}

/*
 * TAO LINK PHAN TRANG
 */

if (!function_exists('vnnActivityPageUrl')) {
    function vnnActivityPageUrl(int $number): string
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= vnnActivityEscape($page_title) ?></title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

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

        .vnn-history-page,
        .vnn-history-page input,
        .vnn-history-page select,
        .vnn-history-page button {
            font-family: inherit;
        }

        .vnn-history-panel {
            width: 100%;
            min-width: 0;
            margin-bottom: 18px;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 13px;
        }

        .vnn-history-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 10px;
            font-size: 21px;
            line-height: 1.4;
            font-weight: 800;
            color: #102e52;
        }

        .vnn-history-title i {
            color: #166534;
        }

        .vnn-history-description {
            margin: 0;
            line-height: 1.7;
            color: #64748b;
            font-size: 13px;
        }

        /* BO LOC */

        .vnn-history-filter {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .vnn-history-field {
            flex: 1 1 150px;
            min-width: 150px;
        }

        .vnn-history-field.search {
            flex: 2 1 260px;
            min-width: 230px;
        }

        .vnn-history-field label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }

        .vnn-history-input {
            display: block;
            width: 100%;
            height: 43px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #173454;
            font-size: 13px;
            outline: none;
        }

        .vnn-history-input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22,163,74,.1);
        }

        .vnn-history-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 43px;
            padding: 0 17px;
            border: none;
            border-radius: 8px;
            background: #166534;
            color: #ffffff !important;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .vnn-history-button.reset {
            background: #e2e8f0;
            color: #334155 !important;
        }

        /* THONG KE VA CAP NHAT */

        .vnn-history-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 17px;
        }

        .vnn-history-total {
            color: #173454;
            font-size: 16px;
            font-weight: 800;
        }

        .vnn-history-sync {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #64748b;
            font-size: 12px;
        }

        .vnn-history-sync i {
            color: #16a34a;
        }

        /* BANG LICH SU */

        .vnn-history-scroll {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
        }

        .vnn-history-table {
            width: 100%;
            min-width: 1240px;
            margin: 0;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
            background: #ffffff;
        }

        .vnn-history-table th,
        .vnn-history-table td {
            margin: 0;
            padding: 15px 13px;
            text-align: left;
            vertical-align: middle;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            line-height: 1.6;
            color: #334155;
            font-weight: 400;
        }

        .vnn-history-table th {
            background: #f8fafc;
            color: #173454;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .vnn-history-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .vnn-history-table tbody tr:hover {
            background: #f8fafc;
        }

        .vnn-history-table .col-time {
            width: 13%;
        }

        .vnn-history-table .col-actor {
            width: 15%;
        }

        .vnn-history-table .col-action {
            width: 18%;
        }

        .vnn-history-table .col-target {
            width: 12%;
        }

        .vnn-history-table .col-id {
            width: 7%;
        }

        .vnn-history-table .col-before {
            width: 17.5%;
        }

        .vnn-history-table .col-after {
            width: 17.5%;
        }

        .vnn-history-date {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .vnn-history-date strong {
            color: #173454;
            font-weight: 800;
            white-space: nowrap;
        }

        .vnn-history-date small {
            color: #64748b;
            font-size: 12px;
            white-space: nowrap;
        }

        .vnn-history-actor {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            color: #173454;
            font-size: 12px;
            font-weight: 700;
        }

        .vnn-history-actor i {
            flex-shrink: 0;
            margin-top: 4px;
            color: #64748b;
        }

        .vnn-history-actor span {
            min-width: 0;
            overflow-wrap: break-word;
        }

        .vnn-history-action {
            display: inline-block;
            max-width: 100%;
            padding: 7px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.55;
            overflow-wrap: break-word;
        }

        .vnn-history-action.update {
            color: #1d4ed8;
            background: #eff6ff;
        }

        .vnn-history-action.payment {
            color: #166534;
            background: #dcfce7;
        }

        .vnn-history-action.cancel {
            color: #b91c1c;
            background: #fee2e2;
        }

        .vnn-history-action.stock {
            color: #92400e;
            background: #fef3c7;
        }

        .vnn-history-target {
            display: block;
            color: #475569;
            font-weight: 700;
        }

        .vnn-history-id {
            color: #173454;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .vnn-history-value {
            display: block;
            width: 100%;
            max-width: 100%;
            padding: 10px 11px;
            border-radius: 7px;
            line-height: 1.65;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            word-break: normal;
            font-size: 12px;
            text-align: left;
        }

        .vnn-history-value.before {
            color: #475569;
            background: #f1f5f9;
        }

        .vnn-history-value.after {
            color: #166534;
            background: #f0fdf4;
        }

        .vnn-history-empty {
            padding: 35px 15px !important;
            text-align: center !important;
            color: #64748b !important;
        }

        .vnn-history-error {
            margin-bottom: 18px;
            padding: 13px 15px;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
        }

        /* PHAN TRANG */

        .vnn-history-pages {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
        }

        .vnn-history-pages a {
            display: inline-flex;
            justify-content: center;
            align-items: center;
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

        .vnn-history-pages a[aria-current="page"] {
            background: #166534;
            border-color: #166534;
            color: white;
        }

        @media (max-width: 750px) {
            .vnn-history-page {
                padding: 12px;
            }

            .vnn-history-panel {
                padding: 15px;
            }

            .vnn-history-field,
            .vnn-history-field.search {
                flex: 1 1 100%;
                min-width: 0;
            }

            .vnn-history-title {
                font-size: 17px;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="admin-main vnn-history-page">

    <section class="vnn-history-panel">

        <h1 class="vnn-history-title">
            <i class="fa-solid fa-clock-rotate-left"></i>
            LỊCH SỬ XỬ LÝ GIAO DỊCH
        </h1>

        <p class="vnn-history-description">
            Theo dõi xử lý đơn hàng, duyệt tour,
            thanh toán, hủy đơn và nhập thêm hàng vào kho.
            Dữ liệu tự động làm mới sau mỗi 10 giây.
        </p>

    </section>

    <section class="vnn-history-panel">

        <form
            class="vnn-history-filter"
            method="GET"
            action="activity-history.php"
        >

            <div class="vnn-history-field">

                <label for="historyType">
                    Loại giao dịch
                </label>

                <select
                    class="vnn-history-input"
                    id="historyType"
                    name="type"
                >

                    <?php foreach ($types as $value => $label): ?>

                        <option
                            value="<?= vnnActivityEscape($value) ?>"
                            <?= $type === $value
                                ? 'selected'
                                : '' ?>
                        >
                            <?= vnnActivityEscape($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="vnn-history-field search">

                <label for="historyKeyword">
                    Tìm kiếm
                </label>

                <input
                    class="vnn-history-input"
                    type="search"
                    id="historyKeyword"
                    name="keyword"
                    placeholder="Người xử lý, mã đơn, trạng thái..."
                    value="<?= vnnActivityEscape($keyword) ?>"
                >

            </div>

            <div class="vnn-history-field">

                <label for="historyStart">
                    Từ ngày
                </label>

                <input
                    class="vnn-history-input"
                    type="date"
                    id="historyStart"
                    name="start"
                    value="<?= vnnActivityEscape($start) ?>"
                >

            </div>

            <div class="vnn-history-field">

                <label for="historyEnd">
                    Đến ngày
                </label>

                <input
                    class="vnn-history-input"
                    type="date"
                    id="historyEnd"
                    name="end"
                    value="<?= vnnActivityEscape($end) ?>"
                >

            </div>

            <button
                class="vnn-history-button"
                type="submit"
            >
                <i class="fa-solid fa-filter"></i>
                Lọc
            </button>

            <a
                class="vnn-history-button reset"
                href="activity-history.php"
            >
                Xóa lọc
            </a>

        </form>

    </section>

    <section class="vnn-history-panel">

        <div class="vnn-history-table-header">

            <strong
                class="vnn-history-total"
                id="historyTotal"
            >
                <?= number_format(
                    $total,
                    0,
                    ',',
                    '.'
                ) ?> bản ghi
            </strong>

            <span
                class="vnn-history-sync"
                id="historySync"
            >
                <i class="fa-solid fa-circle-check"></i>
                Dữ liệu hiện tại
            </span>

        </div>

        <?php if ($loadError !== ''): ?>

            <div class="vnn-history-error">
                <?= vnnActivityEscape($loadError) ?>
            </div>

        <?php endif; ?>

        <div class="vnn-history-scroll">

            <table class="vnn-history-table">

                <colgroup>
                    <col class="col-time">
                    <col class="col-actor">
                    <col class="col-action">
                    <col class="col-target">
                    <col class="col-id">
                    <col class="col-before">
                    <col class="col-after">
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
                            <td
                                colspan="7"
                                class="vnn-history-empty"
                            >
                                Chưa có lịch sử giao dịch phù hợp.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($rows as $row): ?>

                            <?php
                            $datetime = vnnActivityTime(
                                $row['created_at'] ?? ''
                            );

                            $action = (string)(
                                $row['action'] ?? ''
                            );

                            $actor = trim(
                                (string)(
                                    $row['actor_name'] ?? ''
                                )
                            );

                            if ($actor === '') {
                                $actor = 'Không xác định';
                            }

                            $target = (string)(
                                $row['target_type'] ?? ''
                            );

                            $targetId = (string)(
                                $row['target_id'] ?? ''
                            );

                            $before = (string)(
                                $row['old_value'] ?? ''
                            );

                            $after = (string)(
                                $row['new_value'] ?? ''
                            );
                            ?>

                            <tr>

                                <td>
                                    <div class="vnn-history-date">
                                        <strong>
                                            <?= vnnActivityEscape(
                                                $datetime['date']
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= vnnActivityEscape(
                                                $datetime['time']
                                            ) ?>
                                        </small>
                                    </div>
                                </td>

                                <td>
                                    <div class="vnn-history-actor">
                                        <i class="fa-solid fa-user"></i>

                                        <span>
                                            <?= vnnActivityEscape($actor) ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span
                                        class="vnn-history-action <?= vnnActivityEscape(
                                            vnnActivityActionStyle($action)
                                        ) ?>"
                                    >
                                        <?= vnnActivityEscape($action) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="vnn-history-target">
                                        <?= vnnActivityEscape(
                                            vnnActivityTargetName($target)
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="vnn-history-id">
                                        #<?= vnnActivityEscape($targetId) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="vnn-history-value before">
                                        <?= $before !== ''
                                            ? vnnActivityEscape($before)
                                            : '—' ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="vnn-history-value after">
                                        <?= $after !== ''
                                            ? vnnActivityEscape($after)
                                            : '—' ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <?php if ($pageCount > 1): ?>

            <nav
                class="vnn-history-pages"
                id="historyPagination"
                aria-label="Phân trang lịch sử"
            >

                <?php for ($n = 1; $n <= $pageCount; $n++): ?>

                    <a
                        href="<?= vnnActivityEscape(
                            vnnActivityPageUrl($n)
                        ) ?>"
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

            /*
             * Lay toan bo trang HTML.
             *
             * Khong su dung ?partial=1,
             * khong yeu cau PHP gui header AJAX,
             * tranh loi headers already sent.
             */

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
                    'Không thể tải dữ liệu lịch sử.'
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

            const updatedDocument = parser.parseFromString(
                html,
                'text/html'
            );

            const updatedRows =
                updatedDocument.getElementById(
                    'historyRows'
                );

            const updatedTotal =
                updatedDocument.getElementById(
                    'historyTotal'
                );

            const updatedPagination =
                updatedDocument.getElementById(
                    'historyPagination'
                );

            if (!updatedRows || !updatedTotal) {
                throw new Error(
                    'Không tìm thấy dữ liệu lịch sử.'
                );
            }

            /*
             * Chi cap nhat noi dung bang,
             * khong tai lai menu va giao dien.
             */

            rows.innerHTML = updatedRows.innerHTML;

            total.textContent =
                updatedTotal.textContent;

            /*
             * Dong bo phan trang neu tong so
             * ban ghi thay doi.
             */

            if (pagination && updatedPagination) {
                pagination.innerHTML =
                    updatedPagination.innerHTML;
            }

            if (pagination && !updatedPagination) {
                pagination.innerHTML = '';
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

    /*
     * Tu dong cap nhat moi 10 giay.
     */

    setInterval(
        refreshHistory,
        10000
    );

    /*
     * Khi quay lai tab, cap nhat ngay.
     */

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
