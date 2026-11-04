
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

if (!function_exists('activityH')) {
    function activityH($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('activityDateValid')) {
    function activityDateValid(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        return $date !== false
            && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('activityTypeName')) {
    function activityTypeName(string $type): string
    {
        return match ($type) {
            'orders' => 'Đơn hàng',
            'tour_bookings' => 'Đăng ký tour',
            'products' => 'Kho hàng',
            default => 'Khác'
        };
    }
}

if (!function_exists('activityActionClass')) {
    function activityActionClass(string $action): string
    {
        $action = mb_strtolower($action, 'UTF-8');

        if (str_contains($action, 'hủy')) {
            return 'danger';
        }

        if (
            str_contains($action, 'thanh toán') ||
            str_contains($action, 'hoàn tất') ||
            str_contains($action, 'hoàn thành')
        ) {
            return 'success';
        }

        if (
            str_contains($action, 'nhập thêm') ||
            str_contains($action, 'nhập kho')
        ) {
            return 'inventory';
        }

        return 'normal';
    }
}

if (!function_exists('activityRows')) {
    function activityRows(array $rows): void
    {
        if (empty($rows)) {
            echo '<tr><td colspan="7" class="ah-empty">';
            echo 'Chưa có lịch sử giao dịch phù hợp.';
            echo '</td></tr>';
            return;
        }

        foreach ($rows as $row) {
            $timestamp = strtotime(
                (string)($row['created_at'] ?? '')
            );

            $date = $timestamp
                ? date('d/m/Y', $timestamp)
                : '—';

            $time = $timestamp
                ? date('H:i:s', $timestamp)
                : '';

            $action = (string)($row['action'] ?? '');

            $type = (string)(
                $row['target_type'] ?? ''
            );

            $targetId = (string)(
                $row['target_id'] ?? ''
            );

            $actor = trim(
                (string)($row['actor_name'] ?? '')
            );

            if ($actor === '') {
                $actor = 'Không xác định';
            }

            $oldValue = (string)(
                $row['old_value'] ?? ''
            );

            $newValue = (string)(
                $row['new_value'] ?? ''
            );

            $badgeClass = activityActionClass($action);
            ?>
            <tr>
                <td class="ah-date-cell">
                    <strong><?= activityH($date) ?></strong>
                    <span><?= activityH($time) ?></span>
                </td>

                <td>
                    <div class="ah-actor">
                        <i class="fa-solid fa-user"></i>
                        <span><?= activityH($actor) ?></span>
                    </div>
                </td>

                <td>
                    <span class="ah-action <?= activityH($badgeClass) ?>">
                        <?= activityH($action) ?>
                    </span>
                </td>

                <td>
                    <span class="ah-type">
                        <?= activityH(activityTypeName($type)) ?>
                    </span>
                </td>

                <td class="ah-id">
                    #<?= activityH($targetId) ?>
                </td>

                <td>
                    <div class="ah-value old">
                        <?= $oldValue !== ''
                            ? activityH($oldValue)
                            : '—' ?>
                    </div>
                </td>

                <td>
                    <div class="ah-value new">
                        <?= $newValue !== ''
                            ? activityH($newValue)
                            : '—' ?>
                    </div>
                </td>
            </tr>
            <?php
        }
    }
}

/*
 * BO LOC
 *
 * Giu nguyen:
 * - Loc loai giao dich
 * - Tim kiem
 * - Loc ngay
 * - Phan trang
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
    !activityDateValid($start)
) {
    $start = '';
}

if (
    $end !== '' &&
    !activityDateValid($end)
) {
    $end = '';
}

/*
 * CHI HIEN THI LICH SU GIAO DICH
 *
 * Loai cac ban ghi co nguoi thuc hien
 * hien la Admin, hoac co ten ghi ro Admin.
 *
 * Khong xoa ban ghi nao khoi database.
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

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$pageSize = 20;
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
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT $pageSize
        OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        'Activity history: ' . $e->getMessage()
    );

    $loadError = 'Không thể tải lịch sử giao dịch.';
}

/*
 * DU LIEU LAM MOI DINH KY
 *
 * Xoa output buffer truoc khi tra HTML,
 * tranh Warning / HTML tu file include
 * chen vao ket qua AJAX.
 */

if (
    ($_GET['partial'] ?? '') === '1'
) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header(
        'Content-Type: text/html; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate'
    );

    if ($loadError !== '') {
        http_response_code(500);
        echo '<tr><td colspan="7" class="ah-empty">';
        echo activityH($loadError);
        echo '</td></tr>';
        exit;
    }

    activityRows($rows);
    exit;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title><?= activityH($page_title) ?></title>

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

        .ah-page {
            flex: 1;
            min-width: 0;
            padding: 24px;
            background: #f1f5f9;
            color: #173454;
            font-size: 13px;
        }

        .ah-panel {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 17px;
        }

        .ah-header h1 {
            margin: 0 0 10px;
            font-size: 21px;
            color: #102e52;
            font-weight: 800;
        }

        .ah-header h1 i {
            margin-right: 8px;
            color: #166534;
        }

        .ah-header p {
            margin: 0;
            color: #64748b;
            line-height: 1.7;
        }

        .ah-form {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
            align-items: flex-end;
        }

        .ah-field {
            flex: 1;
            min-width: 145px;
        }

        .ah-field.search {
            flex: 2;
            min-width: 220px;
        }

        .ah-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }

        .ah-input {
            display: block;
            width: 100%;
            height: 43px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            color: #173454;
            font: inherit;
            font-size: 13px;
        }

        .ah-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 43px;
            padding: 11px 16px;
            border: 0;
            border-radius: 9px;
            background: #166534;
            color: white !important;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .ah-btn.gray {
            background: #e2e8f0;
            color: #334155 !important;
        }

        .ah-table-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .ah-table-head strong {
            font-size: 15px;
            color: #102e52;
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

        .ah-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
        }

        .ah-table {
            width: 100%;
            min-width: 1220px;
            table-layout: fixed;
            border-collapse: collapse;
            background: white;
        }

        .ah-table th,
        .ah-table td {
            padding: 15px 13px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
            font-size: 12px;
            line-height: 1.6;
            overflow-wrap: break-word;
            word-break: normal;
        }

        .ah-table th {
            background: #f8fafc;
            color: #102e52;
            font-weight: 800;
            font-size: 12px;
            white-space: nowrap;
        }

        .ah-table tbody tr:hover {
            background: #f8fafc;
        }

        .ah-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .ah-date-cell strong {
            display: block;
            white-space: nowrap;
            font-weight: 800;
            color: #173454;
        }

        .ah-date-cell span {
            display: block;
            margin-top: 3px;
            color: #64748b;
            white-space: nowrap;
        }

        .ah-actor {
            display: flex;
            gap: 8px;
            align-items: center;
            font-weight: 700;
            color: #173454;
        }

        .ah-actor i {
            flex-shrink: 0;
            color: #64748b;
        }

        .ah-action {
            display: inline-block;
            max-width: 100%;
            padding: 7px 10px;
            border-radius: 8px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.5;
        }

        .ah-action.success {
            background: #dcfce7;
            color: #166534;
        }

        .ah-action.danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .ah-action.inventory {
            background: #fef3c7;
            color: #92400e;
        }

        .ah-type {
            color: #475569;
            font-weight: 700;
        }

        .ah-id {
            font-weight: 800;
            color: #173454;
            white-space: nowrap;
        }

        .ah-value {
            line-height: 1.6;
            padding: 9px 11px;
            border-radius: 8px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .ah-value.old {
            background: #f1f5f9;
            color: #475569;
        }

        .ah-value.new {
            background: #f0fdf4;
            color: #166534;
        }

        .ah-empty {
            padding: 35px 15px !important;
            text-align: center !important;
            color: #64748b;
        }

        .ah-error {
            padding: 14px 16px;
            margin-bottom: 16px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 9px;
        }

        .ah-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .ah-pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 11px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #173454;
            background: white;
            font-weight: 700;
            text-decoration: none;
        }

        .ah-pagination a[aria-current="page"] {
            background: #166534;
            color: white;
            border-color: #166534;
        }

        @media(max-width:750px) {
            .ah-page {
                padding: 12px;
            }

            .ah-panel {
                padding: 15px;
            }

            .ah-field,
            .ah-field.search {
                flex: 1 1 100%;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="admin-main ah-page">

    <section class="ah-panel ah-header">
        <h1>
            <i class="fa-solid fa-clock-rotate-left"></i>
            LỊCH SỬ XỬ LÝ GIAO DỊCH
        </h1>

        <p>
            Theo dõi lịch sử duyệt đơn hàng,
            xác nhận thanh toán, hủy đơn,
            xử lý đặt tour và nhập thêm hàng vào kho.
            Dữ liệu được làm mới tự động mỗi 10 giây.
        </p>
    </section>

    <section class="ah-panel">

        <form class="ah-form" method="GET">

            <div class="ah-field">
                <label for="type">
                    Loại giao dịch
                </label>

                <select
                    class="ah-input"
                    id="type"
                    name="type"
                >
                    <?php foreach ($types as $value => $label): ?>
                        <option
                            value="<?= activityH($value) ?>"
                            <?= $type === $value
                                ? 'selected'
                                : '' ?>
                        >
                            <?= activityH($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ah-field search">
                <label for="keyword">
                    Tìm kiếm lịch sử
                </label>

                <input
                    class="ah-input"
                    id="keyword"
                    name="keyword"
                    type="search"
                    placeholder="Người xử lý, mã đơn, trạng thái..."
                    value="<?= activityH($keyword) ?>"
                >
            </div>

            <div class="ah-field">
                <label for="start">
                    Từ ngày
                </label>

                <input
                    class="ah-input"
                    id="start"
                    name="start"
                    type="date"
                    value="<?= activityH($start) ?>"
                >
            </div>

            <div class="ah-field">
                <label for="end">
                    Đến ngày
                </label>

                <input
                    class="ah-input"
                    id="end"
                    name="end"
                    type="date"
                    value="<?= activityH($end) ?>"
                >
            </div>

            <button type="submit" class="ah-btn">
                <i class="fa-solid fa-filter"></i>
                Lọc
            </button>

            <a href="activity-history.php" class="ah-btn gray">
                Xóa lọc
            </a>

        </form>

    </section>

    <section class="ah-panel">

        <div class="ah-table-head">
            <strong>
                <i class="fa-solid fa-list"></i>
                <?= number_format($total, 0, ',', '.') ?>
                bản ghi
            </strong>

            <span class="ah-sync" id="lastSync">
                <i class="fa-solid fa-circle-check"></i>
                Dữ liệu hiện tại
            </span>
        </div>

        <?php if ($loadError !== ''): ?>
            <div class="ah-error">
                <?= activityH($loadError) ?>
            </div>
        <?php endif; ?>

        <div class="ah-table-wrap">

            <table class="ah-table">

                <colgroup>
                    <col style="width:12%">
                    <col style="width:16%">
                    <col style="width:19%">
                    <col style="width:12%">
                    <col style="width:7%">
                    <col style="width:17%">
                    <col style="width:17%">
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

                <tbody id="activityRows">
                    <?php activityRows($rows); ?>
                </tbody>

            </table>

        </div>

        <?php if ($pageCount > 1): ?>

            <nav
                class="ah-pagination"
                aria-label="Phân trang lịch sử"
            >

                <?php for ($n = 1; $n <= $pageCount; $n++): ?>

                    <?php
                    $queryParams = $_GET;
                    unset($queryParams['partial']);
                    $queryParams['page'] = $n;

                    $url = '?' . http_build_query(
                        $queryParams
                    );
                    ?>

                    <a
                        href="<?= activityH($url) ?>"
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
    const tbody = document.getElementById('activityRows');
    const sync = document.getElementById('lastSync');

    let loading = false;

    async function refreshHistory() {

        if (
            document.hidden ||
            loading ||
            !tbody
        ) {
            return;
        }

        loading = true;

        try {
            const url = new URL(
                window.location.href
            );

            url.searchParams.set('partial', '1');

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
                    'Không tải được lịch sử.'
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

            tbody.innerHTML = html;

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
