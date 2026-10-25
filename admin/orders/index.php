<?php
// VNN Travel - Quan ly don hang

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireStaff();

$current_page = 'orders';
$page_title = 'Quản Lý Đơn Hàng - VNN Travel';

$statuses = [
    'Chờ xác nhận',
    'Đã xác nhận',
    'Đang giao',
    'Đã giao',
    'Đã hủy'
];

$error = '';
$success = '';

function order_h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// XU LY CAP NHAT TRANG THAI
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['bulk_action'])
) {
    $selected = $_POST['order_ids'] ?? [];
    $target = (string)($_POST['target_status'] ?? '');

    $ids = array_values(array_unique(array_filter(
        array_map('intval', is_array($selected) ? $selected : []),
        fn($id) => $id > 0
    )));

    if (!$ids) {
        $error = 'Vui lòng chọn ít nhất một đơn hàng.';
    } elseif (!in_array($target, $statuses, true)) {
        $error = 'Trạng thái được chọn không hợp lệ.';
    } else {
        try {
            $placeholders = implode(
                ',',
                array_fill(0, count($ids), '?')
            );

            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = ?
                WHERE id IN ($placeholders)
            ");

            $stmt->execute(
                array_merge([$target], $ids)
            );

            header(
                'Location: index.php?msg=bulk_updated',
                true,
                303
            );
            exit;

        } catch (Throwable $e) {
            error_log('Orders update: ' . $e->getMessage());
            $error = 'Không thể cập nhật đơn hàng. Vui lòng thử lại.';
        }
    }
}

if (($_GET['msg'] ?? '') === 'bulk_updated') {
    $success = 'Đã cập nhật trạng thái các đơn hàng được chọn.';
}

// BO LOC
$statusFilter = (string)($_GET['status'] ?? '');

if (
    $statusFilter !== '' &&
    $statusFilter !== 'all' &&
    !in_array($statusFilter, $statuses, true)
) {
    $statusFilter = '';
}

$sql = "SELECT * FROM orders";
$params = [];

if ($statusFilter && $statusFilter !== 'all') {
    $sql .= " WHERE status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width,initial-scale=1">

    <title><?= order_h($page_title) ?></title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet"
          href="../../assets/css/style.css">

    <link rel="stylesheet"
          href="../../assets/css/admin.css">

    <style>
        .vnn-order-tools {
            background:white;
            padding:18px 20px;
            border-radius:12px;
            margin-bottom:20px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:15px;
            box-shadow:0 4px 15px #0f172a0d;
        }

        .vnn-bulk-fields {
            display:flex;
            align-items:center;
            flex-wrap:wrap;
            gap:12px;
        }

        .vnn-orders-title {
            font-weight:800;
            color:#0f172a;
        }

        .vnn-orders-filter {
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        }

        .vnn-order-table {
            width:100%;
            border-collapse:collapse;
            background:white;
        }

        .vnn-order-table th {
            background:#f8fafc;
            color:#0f172a;
            font-weight:800;
            padding:16px;
            text-align:left;
            white-space:nowrap;
        }

        .vnn-order-table td {
            padding:16px;
            border-top:1px solid #e2e8f0;
            vertical-align:middle;
        }

        .vnn-order-table tr:hover td {
            background:#f8fafc;
        }

        .vnn-order-badge {
            display:inline-block;
            border-radius:20px;
            color:white;
            padding:5px 11px;
            font-size:12px;
            font-weight:800;
            white-space:nowrap;
        }

        .vnn-order-phone {
            color:#64748b;
            font-size:13px;
            margin-top:6px;
        }

        .vnn-order-price {
            color:#ea580c;
            font-weight:800;
            white-space:nowrap;
        }

        .vnn-order-alert {
            padding:14px 18px;
            border-radius:10px;
            margin-bottom:18px;
        }

        .vnn-order-alert.error {
            background:#fee2e2;
            color:#991b1b;
        }

        .vnn-order-alert.success {
            background:#dcfce7;
            color:#166534;
        }

        @media(max-width:750px) {
            .vnn-order-tools {
                align-items:stretch;
            }
            .vnn-orders-filter {
                width:100%;
            }
        }
    </style>
</head>

<body class="admin-body">

    <?php
    // SIDEBAR CHUNG VNN TRAVEL
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>

    <main class="admin-main">

        <div class="admin-topbar">

            <strong class="vnn-orders-title">
                QUẢN LÝ ĐƠN HÀNG DỤNG CỤ TREKKING
            </strong>

            <form method="GET"
                  class="vnn-orders-filter">

                <label for="orderStatusFilter">
                    <i class="fa-solid fa-filter"></i>
                    Lọc đơn:
                </label>

                <select
                    id="orderStatusFilter"
                    name="status"
                    class="form-control"
                    onchange="this.form.submit()"
                    style="min-width:190px"
                >
                    <option value="all">
                        -- Hiện tất cả trạng thái --
                    </option>

                    <?php foreach ($statuses as $status): ?>

                        <option
                            value="<?= order_h($status) ?>"
                            <?= $statusFilter === $status
                                ? 'selected' : '' ?>
                        >
                            <?= order_h($status) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (
                    $statusFilter &&
                    $statusFilter !== 'all'
                ): ?>

                    <a href="index.php"
                       class="btn btn-outline btn-sm">
                        Tất cả
                    </a>

                <?php endif; ?>

            </form>

        </div>

        <div class="admin-content">

            <?php if ($error): ?>

                <div class="vnn-order-alert error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= order_h($error) ?>
                </div>

            <?php endif; ?>

            <?php if ($success): ?>

                <div class="vnn-order-alert success" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <?= order_h($success) ?>
                </div>

            <?php endif; ?>

            <form method="POST">

                <div class="vnn-order-tools">

                    <div class="vnn-bulk-fields">

                        <strong>
                            <i class="fa-solid fa-list-check"></i>
                            Thao tác hàng loạt:
                        </strong>

                        <select
                            name="target_status"
                            class="form-control"
                            style="width:180px"
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
                            class="btn btn-primary btn-sm"
                        >
                            <i class="fa-solid fa-check-double"></i>
                            Cập nhật mục đã chọn
                        </button>

                    </div>

                    <div style="color:#64748b">
                        Tổng số hiển thị:
                        <strong><?= count($orders) ?></strong>
                        đơn hàng
                    </div>

                </div>

                <div class="table-wrap">

                    <table class="vnn-order-table">

                        <thead>
                            <tr>
                                <th style="width:45px">
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
                                            <strong>
                                                <?= order_h($order['order_code']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <div>
                                                <?= order_h($order['fullname']) ?>
                                            </div>

                                            <div class="vnn-order-phone">
                                                <?= order_h($order['phone']) ?>
                                            </div>
                                        </td>

                                        <td class="vnn-order-price">
                                            <?= format_price(
                                                $order['total_amount']
                                            ) ?>
                                        </td>

                                        <td>
                                            <span
                                                class="vnn-order-badge"
                                                style="background:<?= order_color($order['status']) ?>"
                                            >
                                                <?= order_h($order['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= !empty($order['created_at'])
                                                ? date(
                                                    'd/m/Y H:i',
                                                    strtotime($order['created_at'])
                                                )
                                                : '—' ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="6"
                                        style="text-align:center;padding:35px">
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

    <script>
    document.getElementById('checkAll')
        ?.addEventListener('change', function() {

        document.querySelectorAll('.order-cb')
            .forEach(cb => {
                cb.checked = this.checked;
            });
    });
    </script>

</body>
</html>
