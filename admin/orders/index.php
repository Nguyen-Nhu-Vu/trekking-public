<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireStaff();

$current_page = 'orders';
$page_title = 'Quản Lý Đơn Hàng - VNN Travel';

if (!function_exists('order_h')) {
    function order_h($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('order_color')) {
    function order_color($status) {
        return match ($status) {
            'Chờ xác nhận' => '#eab308',
            'Đã xác nhận' => '#0284c7',
            'Đang giao' => '#f97316',
            'Đã giao' => '#16a34a',
            'Đã hủy' => '#dc2626',
            default => '#64748b'
        };
    }
}

$statuses = [
    'Chờ xác nhận',
    'Đã xác nhận',
    'Đang giao',
    'Đã giao',
    'Đã hủy'
];

// ======================================
// MA BAO VE FORM
// ======================================

if (empty($_SESSION['orders_csrf'])) {
    $_SESSION['orders_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['orders_csrf'];

// ======================================
// BO LOC DON HANG
// ======================================

$statusFilter = trim(
    (string)($_GET['status'] ?? '')
);

if (
    $statusFilter !== '' &&
    $statusFilter !== 'all' &&
    !in_array($statusFilter, $statuses, true)
) {
    $statusFilter = '';
}

// ======================================
// POPUP THONG BAO
// ======================================

$popup = null;

if (!empty($_SESSION['orders_popup'])) {
    $popup = $_SESSION['orders_popup'];
    unset($_SESSION['orders_popup']);
}

// ======================================
// CAP NHAT HANG LOAT
// ======================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['bulk_action'])
) {
    try {
        $postedCsrf = (string)($_POST['csrf'] ?? '');

        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
            );
        }

        $selected = $_POST['order_ids'] ?? [];
        $target = trim(
            (string)($_POST['target_status'] ?? '')
        );

        if (!is_array($selected)) {
            throw new RuntimeException(
                'Danh sách đơn hàng không hợp lệ.'
            );
        }

        if (count($selected) > 1000) {
            throw new RuntimeException(
                'Số đơn hàng được chọn vượt quá giới hạn.'
            );
        }

        $ids = [];

        foreach ($selected as $value) {
            $id = filter_var(
                $value,
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id <= 0) {
                throw new RuntimeException(
                    'Có mã đơn hàng không hợp lệ.'
                );
            }

            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            throw new RuntimeException(
                'Vui lòng chọn ít nhất một đơn hàng.'
            );
        }

        if (!in_array($target, $statuses, true)) {
            throw new RuntimeException(
                'Trạng thái được chọn không hợp lệ.'
            );
        }

        // ======================================
        // CAP NHAT TRONG MOT TRANSACTION
        // ======================================

        $pdo->beginTransaction();

        $placeholders = implode(
            ',',
            array_fill(0, count($ids), '?')
        );

        $checkStmt = $pdo->prepare("
            SELECT id
            FROM orders
            WHERE id IN ($placeholders)
            FOR UPDATE
        ");

        $checkStmt->execute($ids);

        $foundIds = $checkStmt->fetchAll(
            PDO::FETCH_COLUMN
        );

        $checkStmt->closeCursor();

        if (count($foundIds) !== count($ids)) {
            throw new RuntimeException(
                'Một hoặc nhiều đơn hàng không còn tồn tại.'
            );
        }

        $updateStmt = $pdo->prepare("
            UPDATE orders
            SET status = ?
            WHERE id IN ($placeholders)
        ");

        $updateStmt->execute(
            array_merge([$target], $ids)
        );

        $pdo->commit();

        $_SESSION['orders_popup'] = [
            'type' => 'success',
            'title' => 'Đã lưu thay đổi',
            'message' => 'Trạng thái các đơn hàng đã chọn đã được lưu thành công.'
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Orders bulk update: ' . $e->getMessage()
        );

        $_SESSION['orders_popup'] = [
            'type' => 'error',
            'title' => 'Không thể lưu thay đổi',
            'message' => $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Đã xảy ra lỗi khi cập nhật đơn hàng.'
        ];
    }

    // Giu lai bo loc hien tai sau khi luu
    $returnFilter = trim(
        (string)($_POST['current_filter'] ?? '')
    );

    $redirect = 'index.php';

    if (in_array($returnFilter, $statuses, true)) {
        $redirect .= '?status=' .
            rawurlencode($returnFilter);
    }

    header('Location: ' . $redirect, true, 303);
    exit;
}

// ======================================
// HO TRO LINK THANH CONG TU BAN CU
// ======================================

if (
    isset($_GET['msg']) &&
    $_GET['msg'] === 'bulk_updated' &&
    !$popup
) {
    $popup = [
        'type' => 'success',
        'title' => 'Đã lưu thay đổi',
        'message' => 'Trạng thái các đơn hàng đã được cập nhật.'
    ];
}

// ======================================
// LAY DANH SACH DON HANG
// ======================================

$sql = "SELECT * FROM orders";
$params = [];

if (
    $statusFilter !== '' &&
    $statusFilter !== 'all'
) {
    $sql .= " WHERE status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders = count($orders);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title><?= order_h($page_title) ?></title>

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
        /* ======================================
           NOI DUNG TRANG
           ====================================== */

        .vnn-orders-main {
            flex: 1;
            min-width: 0;
            max-width: 100%;
            overflow-x: hidden;
            background: #f3f6fa;
        }

        .vnn-orders-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .vnn-orders-title {
            color: #0f172a;
            font-weight: 800;
        }

        .vnn-orders-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .vnn-orders-filter select {
            min-width: 205px;
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            font: inherit;
            font-size: 13px;
        }

        .vnn-orders-content {
            min-width: 0;
            max-width: 100%;
            padding: 23px;
        }

        /* ======================================
           THANH THAO TAC HANG LOAT
           ====================================== */

        .vnn-order-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 18px 20px;
            margin-bottom: 20px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 13px;
        }

        .vnn-bulk-fields {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .vnn-bulk-fields strong {
            color: #17345f;
            font-size: 14px;
        }

        .vnn-target-select {
            min-width: 170px;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            font: inherit;
            font-size: 13px;
        }

        .vnn-update-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            border: none;
            border-radius: 9px;
            background: #166534;
            color: white;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .vnn-update-button:hover {
            background: #14532d;
        }

        .vnn-update-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        .vnn-order-total {
            color: #64748b;
            font-size: 13px;
        }

        /* ======================================
           BANG DON HANG
           ====================================== */

        .vnn-order-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: white;
        }

        .vnn-order-table {
            width: 100%;
            min-width: 930px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .vnn-order-table th {
            padding: 18px 14px;
            text-align: left;
            background: #f8fafc;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
            border-bottom: 1px solid #e2e8f0;
        }

        .vnn-order-table td {
            padding: 18px 14px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 13px;
            color: #334155;
        }

        .vnn-order-table tbody tr:hover td {
            background: #f8fafc;
        }

        .vnn-order-code {
            color: #17345f;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .vnn-order-customer {
            color: #334155;
            line-height: 1.5;
        }

        .vnn-order-phone {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
        }

        .vnn-order-price {
            color: #ea580c;
            font-weight: 800;
            white-space: nowrap;
        }

        .vnn-order-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            color: white;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .vnn-order-date {
            color: #475569;
            white-space: nowrap;
        }

        /* ======================================
           POPUP THONG BAO
           ====================================== */

        .vnn-popup-overlay {
            position: fixed;
            inset: 0;
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .46);
            box-sizing: border-box;
        }

        .vnn-popup-overlay[hidden] {
            display: none !important;
        }

        .vnn-popup-card {
            width: 100%;
            max-width: 370px;
            padding: 30px 26px;
            border-radius: 18px;
            background: white;
            box-shadow: 0 20px 65px rgba(0,0,0,.22);
            text-align: center;
        }

        .vnn-popup-icon {
            width: 65px;
            height: 65px;
            display: grid;
            place-items: center;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #dcfce7;
            color: #166534;
            font-size: 29px;
        }

        .vnn-popup-overlay.is-error .vnn-popup-icon {
            background: #fee2e2;
            color: #dc2626;
        }

        .vnn-popup-card h2 {
            margin: 0 0 10px;
            color: #0f172a;
            font-size: 21px;
        }

        .vnn-popup-card p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.8;
        }

        .vnn-popup-close {
            width: 100%;
            margin-top: 22px;
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            background: #166534;
            color: white;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }

        .vnn-popup-overlay.is-error .vnn-popup-close {
            background: #dc2626;
        }

        @media(max-width:750px) {
            .vnn-orders-content {
                padding: 12px;
            }

            .vnn-order-tools {
                padding: 14px;
            }

            .vnn-bulk-fields {
                width: 100%;
            }

            .vnn-order-table th,
            .vnn-order-table td {
                padding: 13px 10px;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main vnn-orders-main">

    <!-- TIEU DE VA BO LOC -->

    <div class="admin-topbar vnn-orders-topbar">

        <strong class="vnn-orders-title">
            QUẢN LÝ ĐƠN HÀNG DỤNG CỤ TREKKING
        </strong>

        <form
            method="GET"
            class="vnn-orders-filter"
        >

            <label for="orderStatusFilter">
                <i class="fa-solid fa-filter"></i>
                Lọc đơn:
            </label>

            <select
                id="orderStatusFilter"
                name="status"
                onchange="this.form.submit()"
            >

                <option value="all">
                    -- Hiện tất cả trạng thái --
                </option>

                <?php foreach ($statuses as $status): ?>

                    <option
                        value="<?= order_h($status) ?>"
                        <?= $statusFilter === $status
                            ? 'selected'
                            : '' ?>
                    >
                        <?= order_h($status) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </div>

    <div class="admin-content vnn-orders-content">

        <!-- FORM CAP NHAT HANG LOAT -->

        <form
            method="POST"
            id="vnnOrderBulkForm"
        >

            <input
                type="hidden"
                name="csrf"
                value="<?= order_h($csrf) ?>"
            >

            <input
                type="hidden"
                name="current_filter"
                value="<?= order_h($statusFilter) ?>"
            >

            <div class="vnn-order-tools">

                <div class="vnn-bulk-fields">

                    <strong>
                        <i class="fa-solid fa-list-check"></i>
                        Thao tác hàng loạt:
                    </strong>

                    <select
                        name="target_status"
                        class="vnn-target-select"
                        id="vnnTargetStatus"
                    >
                        <option value="Đã xác nhận">
                            Đã xác nhận
                        </option>

                        <option value="Đang giao">
                            Đang giao
                        </option>

                        <option value="Đã giao">
                            Đã giao
                        </option>

                        <option value="Đã hủy">
                            Hủy đơn
                        </option>
                    </select>

                    <button
                        type="submit"
                        name="bulk_action"
                        value="1"
                        id="vnnBulkSave"
                        class="vnn-update-button"
                    >
                        <i class="fa-solid fa-check-double"></i>
                        Cập nhật mục đã chọn
                    </button>

                </div>

                <div class="vnn-order-total">
                    Tổng số hiển thị:
                    <strong><?= $totalOrders ?></strong>
                    đơn hàng
                </div>

            </div>

            <!-- BANG DON HANG -->

            <div class="vnn-order-table-wrap">

                <table class="vnn-order-table">

                    <colgroup>
                        <col style="width:4%">
                        <col style="width:19%">
                        <col style="width:25%">
                        <col style="width:18%">
                        <col style="width:16%">
                        <col style="width:18%">
                    </colgroup>

                    <thead>
                        <tr>
                            <th>
                                <input
                                    type="checkbox"
                                    id="checkAll"
                                    aria-label="Chọn tất cả đơn hàng"
                                >
                            </th>

                            <th>Mã Đơn</th>
                            <th>Khách Hàng</th>
                            <th>Tổng Tiền</th>
                            <th>Trạng Thái</th>
                            <th>Ngày Đặt</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($orders): ?>

                            <?php foreach ($orders as $order): ?>

                                <tr>

                                    <td>
                                        <input
                                            type="checkbox"
                                            name="order_ids[]"
                                            class="order-cb"
                                            value="<?= (int)$order['id'] ?>"
                                        >
                                    </td>

                                    <td>
                                        <strong class="vnn-order-code">
                                            <?= order_h(
                                                $order['order_code']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <div class="vnn-order-customer">
                                            <?= order_h(
                                                $order['fullname']
                                            ) ?>
                                        </div>

                                        <small class="vnn-order-phone">
                                            <?= order_h(
                                                $order['phone']
                                            ) ?>
                                        </small>
                                    </td>

                                    <td class="vnn-order-price">
                                        <?= format_price(
                                            $order['total_amount']
                                        ) ?>
                                    </td>

                                    <td>
                                        <span
                                            class="vnn-order-badge"
                                            style="background:<?= order_color(
                                                $order['status']
                                            ) ?>"
                                        >
                                            <?= order_h(
                                                $order['status']
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="vnn-order-date">
                                            <?= !empty(
                                                $order['created_at']
                                            )
                                                ? date(
                                                    'd/m/Y H:i',
                                                    strtotime(
                                                        $order['created_at']
                                                    )
                                                )
                                                : '—' ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td
                                    colspan="6"
                                    style="text-align:center;padding:40px"
                                >
                                    Không có đơn hàng nào phù hợp.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </form>

    </div>

</main>

<!-- POPUP THONG BAO -->

<div
    id="vnnPopupOverlay"
    class="vnn-popup-overlay <?= (
        $popup &&
        ($popup['type'] ?? '') === 'error'
    ) ? 'is-error' : '' ?>"
    role="dialog"
    aria-modal="true"
    aria-labelledby="vnnPopupTitle"
    <?= $popup ? '' : 'hidden' ?>
>

    <div class="vnn-popup-card">

        <div class="vnn-popup-icon">
            <i
                class="fa-solid <?= (
                    $popup &&
                    ($popup['type'] ?? '') === 'error'
                )
                    ? 'fa-xmark'
                    : 'fa-check' ?>"
            ></i>
        </div>

        <h2 id="vnnPopupTitle">
            <?= order_h(
                $popup['title'] ?? 'Thông báo'
            ) ?>
        </h2>

        <p>
            <?= order_h(
                $popup['message'] ?? ''
            ) ?>
        </p>

        <button
            type="button"
            id="vnnPopupClose"
            class="vnn-popup-close"
        >
            Đóng
        </button>

    </div>

</div>

<script>
(function() {
    const checkAll = document.getElementById('checkAll');

    const checkboxes = Array.from(
        document.querySelectorAll('.order-cb')
    );

    const form = document.getElementById(
        'vnnOrderBulkForm'
    );

    const saveButton = document.getElementById(
        'vnnBulkSave'
    );

    const popup = document.getElementById(
        'vnnPopupOverlay'
    );

    const closeButton = document.getElementById(
        'vnnPopupClose'
    );

    // CHON TAT CA

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }

    // DONG BO O CHON TAT CA

    function updateCheckAll() {
        if (!checkAll) return;

        const selected = checkboxes.filter(
            checkbox => checkbox.checked
        ).length;

        checkAll.checked =
            checkboxes.length > 0 &&
            selected === checkboxes.length;

        checkAll.indeterminate =
            selected > 0 &&
            selected < checkboxes.length;
    }

    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener(
            'change',
            updateCheckAll
        );
    });

    // CHAN GUI NEU CHUA CHON DON

    if (form) {
        form.addEventListener('submit', function(event) {
            const selected = checkboxes.some(
                checkbox => checkbox.checked
            );

            // Neu chua chon, de server tra popup loi.
            // Khong vo hieu hoa nut luc nay.
            if (!selected) {
                return;
            }

            if (saveButton) {
                saveButton.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';
            }
        });
    }

    // DONG POPUP

    function closePopup() {
        if (!popup) return;

        popup.hidden = true;

        const url = new URL(window.location.href);
        url.searchParams.delete('msg');

        window.history.replaceState(
            {},
            '',
            url.pathname + url.search + url.hash
        );
    }

    if (closeButton) {
        closeButton.addEventListener(
            'click',
            closePopup
        );
    }

    if (popup) {
        popup.addEventListener('click', function(event) {
            if (event.target === popup) {
                closePopup();
            }
        });
    }

    document.addEventListener('keydown', function(event) {
        if (
            event.key === 'Escape' &&
            popup &&
            !popup.hidden
        ) {
            closePopup();
        }
    });

    updateCheckAll();
})();
</script>

</body>
</html>
