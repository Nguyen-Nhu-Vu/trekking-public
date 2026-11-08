<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

$current_page = 'revenue';
$page_title = 'Doanh Thu & Báo Cáo - VNN Travel';

if (!function_exists('rvH')) {
    function rvH($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rvMoney')) {
    function rvMoney($v) {
        return number_format((float)$v, 0, ',', '.') . ' đ';
    }
}

if (!function_exists('rvRows')) {
    function rvRows(PDO $pdo, string $sql, array $params = []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('rvColumns')) {
    function rvColumns(PDO $pdo, string $table) {
        if (!in_array($table, [
            'orders', 'order_details', 'products',
            'tour_bookings', 'tours'
        ], true)) {
            return [];
        }

        try {
            return array_column(
                $pdo->query("SHOW COLUMNS FROM `$table`")
                    ->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('rvFindColumn')) {
    function rvFindColumn(array $columns, array $names) {
        foreach ($names as $name) {
            if (in_array($name, $columns, true)) {
                return $name;
            }
        }
        return null;
    }
}

if (!function_exists('rvDate')) {
    function rvDate($date) {
        $time = strtotime((string)$date);
        return $time ? date('d/m/Y H:i', $time) : '—';
    }
}

// =========================================
// BO LOC
// =========================================

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);

if (!$year || $year < 2020 || $year > 2100) {
    $year = (int)date('Y');
}

$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

if (!$month || $month < 1 || $month > 12) {
    $month = 0;
}

$source = (string)($_GET['source'] ?? 'all');

if (!in_array($source, ['all', 'tour', 'shop'], true)) {
    $source = 'all';
}

// Một bộ lọc duy nhất điều khiển cả thống kê, biểu đồ và bảng.
$chartSource = $source;

$filterTour = filter_input(
    INPUT_GET,
    'tour_id',
    FILTER_VALIDATE_INT
);

$filterTour = $filterTour > 0 ? $filterTour : 0;

$startDate = sprintf('%04d-01-01 00:00:00', $year);
$endDate = sprintf('%04d-01-01 00:00:00', $year + 1);

if ($month) {
    $startDate = sprintf(
        '%04d-%02d-01 00:00:00',
        $year,
        $month
    );

    $endDate = (new DateTimeImmutable($startDate))
        ->modify('+1 month')
        ->format('Y-m-d H:i:s');
}

// =========================================
// CAU TRUC DATABASE
// =========================================

$orderCols = rvColumns($pdo, 'orders');
$detailCols = rvColumns($pdo, 'order_details');
$productCols = rvColumns($pdo, 'products');
$bookingCols = rvColumns($pdo, 'tour_bookings');

$quantityColumn = rvFindColumn(
    $detailCols,
    ['quantity', 'qty', 'number']
);

$productNameColumn = rvFindColumn(
    $productCols,
    ['name', 'product_name', 'title']
);

$detailNameColumn = rvFindColumn(
    $detailCols,
    ['product_name', 'name', 'title']
);

$orderTotalColumn = rvFindColumn(
    $orderCols,
    ['total_amount', 'total_price', 'amount']
);

$bookingTotalColumn = rvFindColumn(
    $bookingCols,
    ['total_price', 'total_amount', 'amount']
);

$bookingPeopleColumn = rvFindColumn(
    $bookingCols,
    [
        'number_people',
        'number_of_people',
        'num_people',
        'total_people',
        'people_count',
        'quantity'
    ]
);

// =========================================
// BIEN DU LIEU
// =========================================

$tours = [];
$tourBookings = [];
$orders = [];
$orderProducts = [];
$tourReport = [];
$errors = [];

$tourRevenue = 0;
$shopRevenue = 0;
$processingValue = 0;
$tourProcessingValue = 0;
$shopProcessingValue = 0;

$chartTour = array_fill(1, 12, 0);
$chartShop = array_fill(1, 12, 0);

$paidTourStatuses = [
    'Đã thanh toán',
    'Đã hoàn thành'
];

$completedOrderStatuses = [
    'Đã giao',
    'Đã hoàn thành',
    'Hoàn thành',
    'completed',
    'delivered'
];

// =========================================
// TOUR
// =========================================

try {
    $tours = rvRows(
        $pdo,
        "SELECT id, title FROM tours ORDER BY title"
    );
} catch (Throwable $e) {
    error_log('Revenue tours: ' . $e->getMessage());
}

// =========================================
// DOANH THU TOUR
// =========================================

try {
    $sql = "
        SELECT b.*, t.title AS report_tour_name
        FROM tour_bookings b
        LEFT JOIN tours t ON t.id = b.tour_id
        WHERE b.created_at >= ?
          AND b.created_at < ?
    ";

    $params = [$startDate, $endDate];

    if ($filterTour > 0) {
        $sql .= " AND b.tour_id = ? ";
        $params[] = $filterTour;
    }

    $sql .= " ORDER BY b.created_at DESC ";

    $tourBookings = rvRows($pdo, $sql, $params);

    foreach ($tourBookings as $booking) {
        $status = (string)($booking['status'] ?? '');

        $amount = $bookingTotalColumn
            ? (float)($booking[$bookingTotalColumn] ?? 0)
            : 0;

        if (in_array($status, $paidTourStatuses, true)) {
            $tourRevenue += $amount;

            $m = (int)date(
                'n',
                strtotime($booking['created_at'])
            );

            if ($m >= 1 && $m <= 12) {
                $chartTour[$m] += $amount;
            }

            $id = (int)$booking['tour_id'];

            if (!isset($tourReport[$id])) {
                $tourReport[$id] = [
                    'name' => $booking['report_tour_name']
                        ?: 'Tour #' . $id,
                    'bookings' => 0,
                    'people' => 0,
                    'revenue' => 0
                ];
            }

            $tourReport[$id]['bookings']++;

            if ($bookingPeopleColumn) {
                $tourReport[$id]['people'] +=
                    (int)($booking[$bookingPeopleColumn] ?? 0);
            }

            $tourReport[$id]['revenue'] += $amount;

        } elseif ($status !== 'Đã hủy') {
            $tourProcessingValue += $amount;
        }
    }

} catch (Throwable $e) {
    error_log('Revenue bookings: ' . $e->getMessage());
    $errors[] = 'Không thể tải dữ liệu doanh thu tour.';
}

// =========================================
// DOANH THU DON HANG
// =========================================

try {
    $orders = rvRows(
        $pdo,
        "
        SELECT *
        FROM orders
        WHERE created_at >= ?
          AND created_at < ?
        ORDER BY created_at DESC
        ",
        [$startDate, $endDate]
    );

    foreach ($orders as $order) {
        $amount = $orderTotalColumn
            ? (float)($order[$orderTotalColumn] ?? 0)
            : 0;

        $status = (string)($order['status'] ?? '');

        if (in_array(
            $status,
            $completedOrderStatuses,
            true
        )) {
            $shopRevenue += $amount;

            $m = (int)date(
                'n',
                strtotime($order['created_at'])
            );

            if ($m >= 1 && $m <= 12) {
                $chartShop[$m] += $amount;
            }

        } elseif ($status !== 'Đã hủy') {
            $shopProcessingValue += $amount;
        }
    }

} catch (Throwable $e) {
    error_log('Revenue orders: ' . $e->getMessage());
    $errors[] = 'Không thể tải dữ liệu đơn hàng.';
}

// =========================================
// TEN SAN PHAM + SO LUONG
// =========================================

// Lay cac dong chi tiet don hang.
// Khong nhan ban ghi order khi tinh doanh thu.

try {
    if (in_array('order_id', $detailCols, true)) {
        $productJoin = '';
        $nameParts = [];

        if (
            in_array('product_id', $detailCols, true) &&
            $productNameColumn
        ) {
            $productJoin = "
                LEFT JOIN products p
                    ON p.id = d.product_id
            ";

            $nameParts[] = "NULLIF(p.`$productNameColumn`, '')";
        }

        if ($detailNameColumn) {
            $nameParts[] = "NULLIF(d.`$detailNameColumn`, '')";
        }

        $nameParts[] = "'Sản phẩm không còn trong danh mục'";

        $nameExpression = 'COALESCE(' .
            implode(', ', $nameParts) . ')';

        $quantityExpression = $quantityColumn
            ? "d.`$quantityColumn`"
            : "NULL";

        $items = rvRows(
            $pdo,
            "
            SELECT
                d.order_id,
                $nameExpression AS item_name,
                $quantityExpression AS item_quantity
            FROM order_details d
            $productJoin
            INNER JOIN orders o ON o.id = d.order_id
            WHERE o.created_at >= ?
              AND o.created_at < ?
            ORDER BY d.order_id, d.id
            ",
            [$startDate, $endDate]
        );

        foreach ($items as $item) {
            $orderId = (int)$item['order_id'];

            if (!isset($orderProducts[$orderId])) {
                $orderProducts[$orderId] = [];
            }

            $orderProducts[$orderId][] = [
                'name' => $item['item_name'],
                'quantity' => $item['item_quantity'] === null
                    ? null
                    : (int)$item['item_quantity']
            ];
        }
    }

} catch (Throwable $e) {
    error_log('Revenue products: ' . $e->getMessage());
    $errors[] = 'Không thể tải chi tiết sản phẩm trong đơn hàng.';
}

// Số liệu tổng hợp phải khớp với nguồn doanh thu đang chọn.
if ($source === 'tour') {
    $shopRevenue = 0;
    $chartShop = array_fill(1, 12, 0);
    $processingValue = $tourProcessingValue;
} elseif ($source === 'shop') {
    $tourRevenue = 0;
    $chartTour = array_fill(1, 12, 0);
    $processingValue = $shopProcessingValue;
} else {
    $processingValue = $tourProcessingValue + $shopProcessingValue;
}
$totalRevenue = $tourRevenue + $shopRevenue;

// =========================================
// BIEU DO MOT COT TONG
// =========================================

$chartLabels = [];
$chartValues = [];

for ($i = 1; $i <= 12; $i++) {
    $chartLabels[] = 'Tháng ' . $i;

    if ($chartSource === 'tour') {
        $chartValues[] = $chartTour[$i];
    } elseif ($chartSource === 'shop') {
        $chartValues[] = $chartShop[$i];
    } else {
        $chartValues[] = $chartTour[$i] + $chartShop[$i];
    }
}

// ============================================================
// BANG GIAO DICH + XEP HANG: su dung bo loc rieng, giu
// nguyen loc Nam/Thang/Nguon/Tour va du lieu bieu do cu.
// ============================================================
$txType = (string)($_GET['tx_type'] ?? 'all');
$txPeriod = (string)($_GET['tx_period'] ?? 'all');
$txStatus = (string)($_GET['tx_status'] ?? 'paid');
$txAmount = (string)($_GET['tx_amount'] ?? 'all');
$txSort = (string)($_GET['tx_sort'] ?? 'new');
$txSearch = trim((string)($_GET['tx_q'] ?? ''));
$txFrom = (string)($_GET['tx_from'] ?? '');
$txTo = (string)($_GET['tx_to'] ?? '');
$txPage = max(1, (int)($_GET['tx_page'] ?? 1));
$rankType = (string)($_GET['rank_type'] ?? 'tour');
$rankBy = (string)($_GET['rank_by'] ?? 'revenue');
$rankLimit = (int)($_GET['rank_limit'] ?? 5);
if (!in_array($txType, ['all','tour','shop'], true)) $txType = 'all';
if (!in_array($txPeriod, ['all','today','7','30','month','year','custom'], true)) $txPeriod = 'all';
if (!in_array($txStatus, ['all','paid','pending'], true)) $txStatus = 'paid';
if (!in_array($txAmount, ['all','under1','1to5','5to20','over20'], true)) $txAmount = 'all';
if (!in_array($txSort, ['new','old','high','low'], true)) $txSort = 'new';
if (!in_array($rankType, ['tour','product','customer'], true)) $rankType = 'tour';
if (!in_array($rankBy, ['revenue','count','quantity'], true)) $rankBy = 'revenue';
if (!in_array($rankLimit, [5,10], true)) $rankLimit = 5;
$txDateValid = static function(string $d): bool {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
};
$today = new DateTimeImmutable('today');
$txStart = null;
$txEnd = null;
if ($txPeriod === 'today') {
    $txStart = $today->format('Y-m-d'); $txEnd = $txStart;
} elseif ($txPeriod === '7') {
    $txStart = $today->modify('-6 days')->format('Y-m-d');
    $txEnd = $today->format('Y-m-d');
} elseif ($txPeriod === '30') {
    $txStart = $today->modify('-29 days')->format('Y-m-d');
    $txEnd = $today->format('Y-m-d');
} elseif ($txPeriod === 'month') {
    $txStart = $today->format('Y-m-01');
    $txEnd = $today->format('Y-m-d');
} elseif ($txPeriod === 'year') {
    $txStart = $today->format('Y-01-01');
    $txEnd = $today->format('Y-m-d');
} elseif ($txPeriod === 'custom') {
    $txStart = $txDateValid($txFrom) ? $txFrom : null;
    $txEnd = $txDateValid($txTo) ? $txTo : null;
}
// Giao dich ghi nhan doanh thu theo created_at (database chua co paid_at).
$allTransactions = [];
foreach ($tourBookings as $b) {
    $status = (string)($b['status'] ?? '');
    if ($status === 'Đã hủy') continue;
    $paid = in_array($status, $paidTourStatuses, true);
    $allTransactions[] = [
        'type' => 'tour', 'id' => (int)($b['id'] ?? 0),
        'code' => (string)($b['booking_code'] ?? ('Tour #'.($b['id'] ?? ''))),
        'name' => (string)($b['report_tour_name'] ?? 'Tour'),
        'customer' => (string)($b['fullname'] ?? 'Khách hàng'),
        'customer_key' => !empty($b['user_id']) ? 'u'.$b['user_id'] : 'e'.strtolower(trim((string)($b['email'] ?? ''))),
        'quantity' => max(1,(int)($b[$bookingPeopleColumn ?? 'number_people'] ?? 1)),
        'amount' => (float)($b[$bookingTotalColumn ?? 'total_price'] ?? 0),
        'date' => (string)($b['created_at'] ?? ''),
        'status' => $status,'paid' => $paid,
        'tour_id' => (int)($b['tour_id'] ?? 0)
    ];
}
foreach ($orders as $o) {
    $status = (string)($o['status'] ?? '');
    if ($status === 'Đã hủy') continue;
    $paid = in_array($status, $completedOrderStatuses, true);
    $orderId = (int)($o['id'] ?? 0);
    $names = array_column($orderProducts[$orderId] ?? [], 'name');
    $quantities = array_column($orderProducts[$orderId] ?? [], 'quantity');
    $allTransactions[] = [
        'type' => 'shop', 'id' => $orderId,
        'code' => (string)($o['order_code'] ?? ('Đơn #'.$orderId)),
        'name' => $names ? implode(', ',array_unique($names)) : 'Đơn hàng #'.$orderId,
        'customer' => (string)($o['fullname'] ?? 'Khách hàng'),
        'customer_key' => !empty($o['user_id']) ? 'u'.$o['user_id'] : 'e'.strtolower(trim((string)($o['email'] ?? ''))),
        'quantity' => array_sum(array_map('intval',$quantities)),
        'amount' => (float)($o[$orderTotalColumn ?? 'total_amount'] ?? 0),
        'date' => (string)($o['created_at'] ?? ''),
        'status' => $status, 'paid' => $paid,
        'tour_id' => 0
    ];
}
$transactionsFiltered = array_values(array_filter($allTransactions, static function(array $r) use ($txType,$txStatus,$txAmount,$txSearch,$txStart,$txEnd): bool {
    if ($txType !== 'all' && $r['type'] !== $txType) return false;
    if ($txStatus === 'paid' && !$r['paid']) return false;
    if ($txStatus === 'pending' && $r['paid']) return false;
    $date = substr($r['date'],0,10);
    if ($txStart !== null && $date < $txStart) return false;
    if ($txEnd !== null && $date > $txEnd) return false;
    if ($txSearch !== '' && stripos($r['name'].' '.$r['code'].' '.$r['customer'], $txSearch) === false) return false;
    $v = $r['amount'];
    if ($txAmount === 'under1' && $v >= 1000000) return false;
    if ($txAmount === '1to5' && ($v < 1000000 || $v >= 5000000)) return false;
    if ($txAmount === '5to20' && ($v < 5000000 || $v >= 20000000)) return false;
    if ($txAmount === 'over20' && $v < 20000000) return false;
    return true;
}));
usort($transactionsFiltered, static function($a,$b) use ($txSort) {
    if ($txSort === 'high') return $b['amount'] <=> $a['amount'];
    if ($txSort === 'low') return $a['amount'] <=> $b['amount'];
    if ($txSort === 'old') return strcmp($a['date'],$b['date']);
    return strcmp($b['date'],$a['date']);
});
$txCount = count($transactionsFiltered);
$txPageCount = max(1, (int)ceil($txCount / 10));
$txPage = min($txPage, $txPageCount);
$txPageRows = array_slice($transactionsFiltered, ($txPage - 1)*10, 10);
$txPageUrl = static function($page) {
    $q = $_GET; $q['tx_page'] = $page;
    return '?'.http_build_query($q);
};
// Xep hang theo bo loc bieu do cu (nam/thang/nguon/tour), khong tinh giao dich cho xu ly.
$rankTours = []; $rankCustomers = []; $rankProducts = [];
foreach ($allTransactions as $r) {
    if (!$r['paid'] || ($source !== 'all' && $r['type'] !== $source)) continue;
    if ($r['type'] === 'tour') {
        $key = $r['tour_id'];
        if (!isset($rankTours[$key])) $rankTours[$key] = ['name'=>$r['name'],'count'=>0,'quantity'=>0,'revenue'=>0];
        $rankTours[$key]['count']++;
        $rankTours[$key]['quantity'] += $r['quantity'];
        $rankTours[$key]['revenue'] += $r['amount'];
    }
    $key = $r['customer_key'] ?: 'g'.$r['id'].$r['type'];
    if (!isset($rankCustomers[$key])) $rankCustomers[$key] = ['name'=>$r['customer'],'count'=>0,'quantity'=>0,'revenue'=>0];
    $rankCustomers[$key]['count']++;
    $rankCustomers[$key]['quantity'] += $r['quantity'];
    $rankCustomers[$key]['revenue'] += $r['amount'];
}
// Tong doanh thu san pham tinh theo dong order_details, khong nhan ban don hang.
try {
    if ($source !== 'tour' && $quantityColumn && in_array('product_id',$detailCols,true)) {
        $rankProductRows = rvRows($pdo, "SELECT d.product_id, COALESCE(p.name, CONCAT('Sản phẩm #', d.product_id)) AS name,
            SUM(d.quantity) AS quantity, SUM(d.total) AS revenue, COUNT(DISTINCT d.order_id) AS order_count
            FROM order_details d INNER JOIN orders o ON o.id = d.order_id
            LEFT JOIN products p ON p.id = d.product_id
            WHERE o.created_at >= ? AND o.created_at < ?
            AND o.status IN ('Đã giao','Đã hoàn thành','Hoàn thành','completed','delivered')
            GROUP BY d.product_id, p.name", [$startDate,$endDate]);
        foreach ($rankProductRows as $r) $rankProducts[] = [
            'name'=>$r['name'], 'count'=>(int)$r['order_count'],
            'quantity'=>(int)$r['quantity'], 'revenue'=>(float)$r['revenue']
        ];
    }
} catch (Throwable $e) { error_log('Revenue ranking products: '.$e->getMessage()); }
$rankData = $rankType === 'product' ? array_values($rankProducts) : ($rankType === 'customer' ? array_values($rankCustomers) : array_values($rankTours));
if ($rankBy === 'quantity' && $rankType === 'customer') $rankBy = 'count';
usort($rankData, static function($a,$b) use ($rankBy) {
    return ($b[$rankBy] <=> $a[$rankBy]) ?: ($b['revenue'] <=> $a['revenue']);
});
$rankData = array_slice($rankData,0,$rankLimit);

// Phan trang doc lap cho 2 bang doanh thu (10 dong/trang).
$tourPage = max(1, (int)($_GET['tour_page'] ?? 1));
$shopPage = max(1, (int)($_GET['shop_page'] ?? 1));
$pageSize = 10;

$tourReportRows = array_values($tourReport);
$tourPageCount = max(1, (int)ceil(count($tourReportRows) / $pageSize));
$tourPage = min($tourPage, $tourPageCount);
$tourReportPage = array_slice($tourReportRows, ($tourPage - 1) * $pageSize, $pageSize);

$completedOrders = array_values(array_filter($orders, static function ($order) use ($completedOrderStatuses) {
    return in_array((string)($order['status'] ?? ''), $completedOrderStatuses, true);
}));
$shopPageCount = max(1, (int)ceil(count($completedOrders) / $pageSize));
$shopPage = min($shopPage, $shopPageCount);
$ordersPage = array_slice($completedOrders, ($shopPage - 1) * $pageSize, $pageSize);

// Giữ nguyên tất cả tham số lọc biểu đồ và xếp hạng khi chuyển trang.
$revenuePageUrl = static function (string $pageKey, int $page) {
    $query = $_GET;
    $query[$pageKey] = $page;
    return '?' . http_build_query($query);
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= rvH($page_title) ?></title>

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
* {
    box-sizing: border-box;
}

.rv-main {
    flex: 1 1 auto;
    min-width: 0;
    background: #f1f5f9;
    color: #122544;
}

.rv-head {
    padding: 22px 27px;
    background: white;
    border-bottom: 1px solid #e2e8f0;
}

.rv-head h1 {
    margin: 0;
    color: #102d50;
    font-size: 20px;
    font-weight: 800;
}

.rv-body {
    max-width: 1800px;
    margin: auto;
    padding: 24px;
}

.rv-panel {
    padding: 23px;
    margin-bottom: 22px;
    border: 1px solid #dfe7f1;
    border-radius: 17px;
    background: white;
}

.rv-panel h2 {
    margin: 0 0 20px;
    color: #10213f;
    font-size: 19px;
    font-weight: 800;
}

.rv-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 13px;
}

.rv-field {
    flex: 1 1 145px;
    min-width: 135px;
}

.rv-field label {
    display: block;
    margin-bottom: 7px;
    color: #475569;
    font-size: 12px;
    font-weight: 800;
}

.rv-field select {
    width: 100%;
    height: 43px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    font: inherit;
    font-size: 13px;
}

.rv-submit {
    height: 43px;
    padding: 0 22px;
    border: 0;
    border-radius: 9px;
    background: #166534;
    color: white;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.rv-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 17px;
    margin-bottom: 22px;
}

.rv-stat {
    min-width: 0;
    padding: 23px;
    border: 1px solid #dfe7f1;
    border-radius: 16px;
    background: white;
}

.rv-stat-title {
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.rv-stat-value {
    margin-top: 15px;
    color: #ea580c;
    font-size: clamp(21px,2vw,29px);
    font-weight: 900;
    overflow-wrap: anywhere;
}

.rv-stat-value.green {
    color: #166534;
}

.rv-stat-value.blue {
    color: #0284c7;
}

.rv-muted {
    color: #64748b;
    font-size: 12px;
}

.rv-chart-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}

.rv-chart-head h2 {
    margin-bottom: 0;
}

.rv-chart-controls label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 800;
}

.rv-chart-controls select {
    min-width: 190px;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
}

.rv-chart-box {
    position: relative;
    width: 100%;
    height: 355px;
}

.rv-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.rv-table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
}

.rv-table th,
.rv-table td {
    padding: 14px 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}

.rv-table th {
    background: #f8fafc;
    color: #10213f;
    font-weight: 800;
    white-space: nowrap;
}

.rv-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.rv-money {
    color: #ea580c;
    font-weight: 900;
    white-space: nowrap;
}

/* ==================================
   TEN SAN PHAM - KHONG DAU CHAM
   ================================== */

.rv-product-line {
    display: block;
    padding: 4px 0;
    color: #153358;
    font-size: 13px;
    line-height: 1.5;
}

.rv-product-line + .rv-product-line {
    border-top: 1px solid #edf2f7;
}

/* ==================================
   SO LUONG LA COT RIENG
   ================================== */

.rv-quantity-line {
    display: block;
    padding: 4px 0;
    color: #166534;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.5;
}

.rv-quantity-line + .rv-quantity-line {
    border-top: 1px solid #edf2f7;
}

.rv-quantity-cell {
    text-align: center !important;
}

.rv-quantity-head {
    text-align: center !important;
}

.rv-empty {
    padding: 30px !important;
    color: #64748b;
    text-align: center !important;
    font-size: 13px;
}

.rv-warning {
    padding: 13px;
    margin-bottom: 18px;
    border-radius: 10px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 13px;
}

@media(max-width:1250px) {
    .rv-stats {
        grid-template-columns: repeat(2, minmax(0,1fr));
    }
}

@media(max-width:700px) {
    .rv-body {
        padding: 12px;
    }

    .rv-panel {
        padding: 15px;
    }

    .rv-stats {
        grid-template-columns: 1fr;
    }
}

/* Dashboard layout bo sung, khong can thiep menu admin */
.rv-chart-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:18px}
.rv-chart-panel{min-width:0}
.rv-chart-grid .rv-chart-box{height:290px}
.rv-donut-layout{display:grid;grid-template-columns:minmax(140px,1fr) minmax(150px,1fr);gap:16px;align-items:center;min-height:290px}
.rv-donut-box{height:230px;position:relative;min-width:0}
.rv-donut-center{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:7px;pointer-events:none;text-align:center;padding:0 21%;color:#122544}
.rv-donut-center span{font-size:11px;font-weight:800;white-space:nowrap}
.rv-donut-center strong{font-size:clamp(12px,1.15vw,18px);font-weight:900;color:#166534;overflow-wrap:anywhere;line-height:1.3}
#rvTourTable,#rvShopTable{scroll-margin-top:18px}
.rv-donut-text{display:flex;flex-direction:column;gap:10px;font-size:13px;color:#475569;min-width:0}
.rv-donut-text>strong{color:#122544}.rv-donut-text>b{font-size:19px;color:#166534;overflow-wrap:anywhere}
.rv-donut-text>div{border-top:1px solid #e2e8f0;padding-top:10px;line-height:1.7}
.rv-donut-text>div b{display:block;color:#122544;font-size:13px;overflow-wrap:anywhere}
.rv-dot{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:6px}
.rv-section-row{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:15px}
.rv-section-row h2{margin:0}
.rv-tx-panel{background:linear-gradient(115deg,#f2fbf6,#fff)}
.rv-tx-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}
.rv-tx-form .rv-field{min-width:0;flex:auto}
.rv-tx-form .rv-field select,.rv-tx-form .rv-field input{width:100%;height:42px;background:white;border:1px solid #ced9e8;border-radius:9px;padding:0 10px;color:#122544;font-size:13px}
.rv-tx-search{grid-column:span 2}
.rv-tx-actions{display:flex;align-items:center;gap:10px;grid-column:span 2}
.rv-clear{display:inline-flex;align-items:center;justify-content:center;height:43px;padding:0 16px;text-decoration:none;background:#e2e8f0;border-radius:9px;font-size:13px;font-weight:800;color:#193252}
.rv-transaction-table th,.rv-transaction-table td{padding:12px 10px;font-size:12px}
.rv-transaction-table strong{overflow-wrap:anywhere}.rv-transaction-table td:nth-child(4){min-width:175px}
.rv-pill{display:inline-block;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:800;white-space:nowrap}
.rv-pill-paid{background:#dcfce7;color:#166534}.rv-pill-pending{background:#fef3c7;color:#92400e}
.rv-pagination{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding-top:15px;font-size:12px;color:#64748b}
.rv-pagination>div{display:flex;gap:6px;flex-wrap:wrap}
.rv-pagination a{padding:7px 11px;border:1px solid #d7e2ec;border-radius:8px;text-decoration:none;color:#164466;font-weight:700}
.rv-pagination a.active{background:#16803d;color:white;border-color:#16803d}
.rv-rank-form{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:15px}
.rv-rank-tabs{display:flex;gap:8px;flex-wrap:wrap}
.rv-rank-tabs button{background:#edf2f7;color:#183551;border:none;border-radius:9px;padding:10px 16px;font-size:12px;font-weight:800;cursor:pointer}
.rv-rank-tabs button.active{background:#16803d;color:white}
.rv-rank-options{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.rv-rank-options label{font-size:12px;color:#64748b;font-weight:800}
.rv-rank-options select{margin-left:6px;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 9px;background:#fff;color:#122544}
.rv-ranking-table{min-width:630px}
@media(max-width:1050px){.rv-chart-grid{grid-template-columns:1fr}.rv-tx-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.rv-tx-form{grid-template-columns:1fr}.rv-tx-search,.rv-tx-actions{grid-column:span 1}.rv-donut-layout{grid-template-columns:1fr}.rv-donut-box{max-width:260px;width:100%;margin:auto}.rv-rank-form{align-items:stretch}}

</style>
</head>

<body class="admin-body">

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main rv-main">

<header class="rv-head">
    <h1>
        <i class="fa-solid fa-chart-line"></i>
        DOANH THU & BÁO CÁO
    </h1>
</header>

<div class="rv-body">

    <?php foreach ($errors as $error): ?>
        <div class="rv-warning">
            <?= rvH($error) ?>
        </div>
    <?php endforeach; ?>

    <!-- BO LOC -->

    <section class="rv-panel">

        <form method="GET" class="rv-filters">

            <div class="rv-field">
                <label>Năm</label>

                <select name="year">
                    <?php for (
                        $y = 2025;
                        $y <= max(2030, (int)date('Y') + 2);
                        $y++
                    ): ?>
                        <option
                            value="<?= $y ?>"
                            <?= $year === $y ? 'selected' : '' ?>
                        >
                            <?= $y ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="rv-field">
                <label>Tháng</label>

                <select name="month">
                    <option value="0">Cả năm</option>

                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option
                            value="<?= $m ?>"
                            <?= $month === $m ? 'selected' : '' ?>
                        >
                            Tháng <?= $m ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="rv-field">
                <label>Nguồn doanh thu</label>

                <select name="source">
                    <option value="all"
                        <?= $source === 'all' ? 'selected' : '' ?>>
                        Tất cả
                    </option>

                    <option value="tour"
                        <?= $source === 'tour' ? 'selected' : '' ?>>
                        Tour trekking
                    </option>

                    <option value="shop"
                        <?= $source === 'shop' ? 'selected' : '' ?>>
                        Bán dụng cụ
                    </option>
                </select>
            </div>

            <div class="rv-field">
                <label>Tour</label>

                <select name="tour_id">
                    <option value="0">Tất cả tour</option>

                    <?php foreach ($tours as $tour): ?>
                        <option
                            value="<?= (int)$tour['id'] ?>"
                            <?= $filterTour === (int)$tour['id']
                                ? 'selected' : '' ?>
                        >
                            <?= rvH($tour['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="rv-submit" type="submit">
                <i class="fa-solid fa-filter"></i>
                Lọc dữ liệu
            </button>

        </form>
    </section>

    <!-- THONG KE -->

    <section class="rv-stats">

        <div class="rv-stat">
            <div class="rv-stat-title">
                Tổng doanh thu hoàn thành
            </div>

            <div class="rv-stat-value">
                <?= rvH(rvMoney($totalRevenue)) ?>
            </div>
        </div>

        <div class="rv-stat">
            <div class="rv-stat-title">
                Doanh thu tour
            </div>

            <div class="rv-stat-value green">
                <?= rvH(rvMoney($tourRevenue)) ?>
            </div>
        </div>

        <div class="rv-stat">
            <div class="rv-stat-title">
                Doanh thu bán hàng
            </div>

            <div class="rv-stat-value blue">
                <?= rvH(rvMoney($shopRevenue)) ?>
            </div>
        </div>

        <div class="rv-stat">
            <div class="rv-stat-title">
                Giá trị đơn đang xử lý
            </div>

            <div class="rv-stat-value">
                <?= rvH(rvMoney($processingValue)) ?>
            </div>

            <div class="rv-muted">
                Không tính vào doanh thu hoàn thành.
            </div>
        </div>

    </section>

    <!-- HAI BIEU DO - GIU NGUYEN BO LOC CU -->
    <div class="rv-chart-grid">
      <section class="rv-panel rv-chart-panel">
        <div class="rv-chart-head">
            <h2><i class="fa-solid fa-chart-line"></i> Xu hướng doanh thu <?= (int)$year ?></h2>
            <div class="rv-chart-controls">
                <label for="rvChartSource">Hiển thị biểu đồ</label>
                <select id="rvChartSource">
                    <option value="all" <?= $chartSource === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="tour" <?= $chartSource === 'tour' ? 'selected' : '' ?>>Doanh thu Tour</option>
                    <option value="shop" <?= $chartSource === 'shop' ? 'selected' : '' ?>>Doanh thu Bán Hàng</option>
                </select>
            </div>
        </div>
        <div class="rv-chart-box"><canvas id="rvRevenueChart"></canvas></div>
      </section>
      <section class="rv-panel rv-chart-panel">
        <div class="rv-chart-head"><h2><i class="fa-solid fa-chart-pie"></i> Cơ cấu doanh thu</h2></div>
        <div class="rv-donut-layout">
          <div class="rv-donut-box"><canvas id="rvShareChart"></canvas><div class="rv-donut-center"><span>Tổng doanh thu</span><strong><?= rvH(rvMoney($totalRevenue)) ?></strong></div></div>
          <div class="rv-donut-text">
             <div><span class="rv-dot" style="background:#16803d"></span> Tour Trekking <b><?= rvH(rvMoney($tourRevenue)) ?></b></div>
             <div><span class="rv-dot" style="background:#3b82f6"></span> Cửa hàng <b><?= rvH(rvMoney($shopRevenue)) ?></b></div>
          </div>
        </div>
      </section>
    </div>

    <!-- DOANH THU TOUR -->

    <?php if ($source !== 'shop'): ?>

        <section class="rv-panel" id="rvTourTable">

            <h2>
                <i class="fa-solid fa-person-hiking"></i>
                Doanh thu tour trekking
            </h2>

            <div class="rv-table-wrap">
                <table class="rv-table" style="min-width:760px">

                    <thead>
                        <tr>
                            <th>Tên Tour</th>
                            <th>Lượt đăng ký</th>
                            <th>Số khách</th>
                            <th>Doanh thu</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($tourReportPage as $tour): ?>
                        <tr>
                            <td><?= rvH($tour['name']) ?></td>

                            <td>
                                <?= (int)$tour['bookings'] ?>
                            </td>

                            <td>
                                <?= (int)$tour['people'] ?>
                            </td>

                            <td class="rv-money">
                                <?= rvH(rvMoney($tour['revenue'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$tourReportRows): ?>
                        <tr>
                            <td colspan="4" class="rv-empty">
                                Chưa có doanh thu tour hoàn thành.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>

                </table>
            </div>
            <nav class="rv-pagination" aria-label="Phân trang doanh thu tour">
                <span>Trang <?= $tourPage ?> / <?= $tourPageCount ?> · <?= count($tourReportRows) ?> tour · 10 dòng/trang</span>
                <div>
                    <?php if ($tourPage > 1): ?><a href="<?= rvH($revenuePageUrl('tour_page', $tourPage - 1)) ?>#rvTourTable">‹ Trước</a><?php endif; ?>
                    <?php for ($i = max(1, $tourPage - 2); $i <= min($tourPageCount, $tourPage + 2); $i++): ?>
                        <a class="<?= $i === $tourPage ? 'active' : '' ?>" href="<?= rvH($revenuePageUrl('tour_page', $i)) ?>#rvTourTable"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($tourPage < $tourPageCount): ?><a href="<?= rvH($revenuePageUrl('tour_page', $tourPage + 1)) ?>#rvTourTable">Sau ›</a><?php endif; ?>
                </div>
            </nav>
        </section>

    <?php endif; ?>

    <!-- DOANH THU DON HANG -->

    <?php if ($source !== 'tour'): ?>

        <section class="rv-panel" id="rvShopTable">

            <h2>
                <i class="fa-solid fa-box"></i>
                Doanh thu đơn hàng
            </h2>

            <div class="rv-table-wrap">

                <table class="rv-table">

                    <!-- CHIA LAI 6 COT -->

                    <colgroup>
                        <col style="width:16%">
                        <col style="width:30%">
                        <col style="width:9%">
                        <col style="width:19%">
                        <col style="width:13%">
                        <col style="width:13%">
                    </colgroup>

                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Tên sản phẩm</th>
                            <th class="rv-quantity-head">
                                Số lượng
                            </th>
                            <th>Khách hàng</th>
                            <th>Doanh thu</th>
                            <th>Ngày tạo</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    foreach ($ordersPage as $order):
                        $orderId = (int)$order['id'];

                        $products = $orderProducts[$orderId] ?? [];
                    ?>

                        <tr>

                            <!-- MA DON -->

                            <td>
                                <?= rvH(
                                    $order['order_code'] ??
                                    'Đơn #' . $orderId
                                ) ?>
                            </td>

                            <!-- TEN SAN PHAM
                                 KHONG DAU CHAM
                                 KHONG NHAN SO LUONG -->

                            <td>

                                <?php if ($products): ?>

                                    <?php foreach ($products as $item): ?>

                                        <div class="rv-product-line">
                                            <?= rvH($item['name']) ?>
                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <span class="rv-muted">
                                        Chưa có chi tiết sản phẩm
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- COT SO LUONG RIENG -->

                            <td class="rv-quantity-cell">

                                <?php if ($products): ?>

                                    <?php foreach ($products as $item): ?>

                                        <div class="rv-quantity-line">
                                            <?= $item['quantity'] === null
                                                ? '—'
                                                : (int)$item['quantity'] ?>
                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <span class="rv-muted">—</span>

                                <?php endif; ?>

                            </td>

                            <!-- KHACH HANG -->

                            <td>
                                <?= rvH(
                                    $order['fullname'] ??
                                    $order['customer_name'] ??
                                    'Khách hàng'
                                ) ?>
                            </td>

                            <!-- DOANH THU -->

                            <td class="rv-money">
                                <?= rvH(
                                    rvMoney(
                                        $orderTotalColumn
                                            ? ($order[$orderTotalColumn] ?? 0)
                                            : 0
                                    )
                                ) ?>
                            </td>

                            <!-- NGAY TAO -->

                            <td>
                                <?= rvH(
                                    rvDate(
                                        $order['created_at'] ?? ''
                                    )
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$completedOrders): ?>
                        <tr>
                            <td colspan="6" class="rv-empty">
                                Chưa có đơn hàng hoàn thành
                                trong khoảng thời gian này.
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>

                </table>

            </div>
            <nav class="rv-pagination" aria-label="Phân trang doanh thu đơn hàng">
                <span>Trang <?= $shopPage ?> / <?= $shopPageCount ?> · <?= count($completedOrders) ?> đơn hàng · 10 dòng/trang</span>
                <div>
                    <?php if ($shopPage > 1): ?><a href="<?= rvH($revenuePageUrl('shop_page', $shopPage - 1)) ?>#rvShopTable">‹ Trước</a><?php endif; ?>
                    <?php for ($i = max(1, $shopPage - 2); $i <= min($shopPageCount, $shopPage + 2); $i++): ?>
                        <a class="<?= $i === $shopPage ? 'active' : '' ?>" href="<?= rvH($revenuePageUrl('shop_page', $i)) ?>#rvShopTable"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($shopPage < $shopPageCount): ?><a href="<?= rvH($revenuePageUrl('shop_page', $shopPage + 1)) ?>#rvShopTable">Sau ›</a><?php endif; ?>
                </div>
            </nav>
        </section>

    <?php endif; ?>

    <!-- XEP HANG KINH DOANH - SAU CAC BANG DOANH THU -->
    <section class="rv-panel">
      <div class="rv-section-row"><h2><i class="fa-solid fa-trophy"></i> Xếp hạng kinh doanh</h2>
        <span class="rv-muted">Theo kỳ và nguồn doanh thu đã chọn ở bộ lọc biểu đồ</span></div>
      <form method="get" class="rv-rank-form">
        <input type="hidden" name="year" value="<?= (int)$year ?>"><input type="hidden" name="month" value="<?= (int)$month ?>">
        <input type="hidden" name="source" value="<?= rvH($source) ?>"><input type="hidden" name="tour_id" value="<?= (int)$filterTour ?>">
        <?php foreach (['tx_q'=>$txSearch,'tx_type'=>$txType,'tx_period'=>$txPeriod,'tx_from'=>$txFrom,'tx_to'=>$txTo,'tx_status'=>$txStatus,'tx_amount'=>$txAmount,'tx_sort'=>$txSort] as $k=>$v): ?>
          <input type="hidden" name="<?= rvH($k) ?>" value="<?= rvH($v) ?>">
        <?php endforeach; ?>
        <div class="rv-rank-tabs">
          <?php foreach (['tour'=>'Tour Trekking','product'=>'Sản phẩm','customer'=>'Khách hàng'] as $k=>$label): ?>
            <button type="submit" name="rank_type" value="<?= $k ?>" class="<?= $rankType===$k?'active':'' ?>"><?= rvH($label) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="rv-rank-options">
          <label>Tiêu chí <select name="rank_by" onchange="rvSubmitRank(this.form)">
            <option value="revenue" <?= $rankBy==='revenue'?'selected':'' ?>>Doanh thu cao nhất</option>
            <option value="count" <?= $rankBy==='count'?'selected':'' ?>>Số lượt giao dịch</option>
            <option value="quantity" <?= $rankBy==='quantity'?'selected':'' ?>>Số lượng khách / sản phẩm</option>
          </select></label><label>Top <select name="rank_limit" onchange="rvSubmitRank(this.form)">
            <option value="5" <?= $rankLimit===5?'selected':'' ?>>5</option><option value="10" <?= $rankLimit===10?'selected':'' ?>>10</option>
          </select></label>
        </div>
      </form>
      <div class="rv-table-wrap"><table class="rv-table rv-ranking-table"><thead><tr>
        <th>Hạng</th><th><?= $rankType==='tour'?'Tên tour':($rankType==='product'?'Tên sản phẩm':'Khách hàng') ?></th>
        <th><?= $rankType==='tour'?'Lượt đặt':'Lượt mua' ?></th><th><?= $rankType==='tour'?'Số khách':($rankType==='product'?'Số lượng bán':'Số lượng') ?></th><th>Doanh thu</th></tr></thead><tbody>
        <?php foreach ($rankData as $i=>$r): ?><tr><td><?= $i < 3 ? ['🥇','🥈','🥉'][$i] : $i+1 ?></td>
          <td><strong><?= rvH($r['name']) ?></strong></td><td><?= (int)$r['count'] ?></td>
          <td><?= (int)$r['quantity'] ?></td><td class="rv-money"><?= rvH(rvMoney($r['revenue'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$rankData): ?><tr><td colspan="5" class="rv-empty">Chưa có dữ liệu xếp hạng phù hợp.</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>


</div>
</main>

<script>
(function(){
  const select=document.getElementById('rvChartSource');
  const labels=<?= json_encode($chartLabels,JSON_UNESCAPED_UNICODE) ?>;
  const tour=<?= json_encode(array_values($chartTour),JSON_NUMERIC_CHECK) ?>;
  const shop=<?= json_encode(array_values($chartShop),JSON_NUMERIC_CHECK) ?>;
  const money=v=>new Intl.NumberFormat('vi-VN').format(v)+' đ';
  if(typeof Chart!=='undefined'){
    const line=document.getElementById('rvRevenueChart');
    if(line){new Chart(line,{type:'line',data:{labels,datasets:[
      {label:'Tour Trekking',data:tour,borderColor:'#16803d',backgroundColor:'rgba(22,128,61,.13)',fill:true,tension:.3,pointRadius:3,borderWidth:2.5},
      {label:'Cửa hàng',data:shop,borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,.12)',fill:true,tension:.3,pointRadius:3,borderWidth:2.5}
    ]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top',labels:{boxWidth:12}},tooltip:{callbacks:{label:c=>c.dataset.label+': '+money(c.parsed.y)}}},scales:{y:{beginAtZero:true,ticks:{callback:v=>new Intl.NumberFormat('vi-VN',{notation:'compact'}).format(v)}},x:{ticks:{maxTicksLimit:7}}}}});}
    const donut=document.getElementById('rvShareChart');
    if(donut){new Chart(donut,{type:'doughnut',data:{labels:['Tour Trekking','Cửa hàng'],datasets:[{data:[<?= json_encode((float)$tourRevenue) ?>,<?= json_encode((float)$shopRevenue) ?>],backgroundColor:['#16803d','#3b82f6'],borderWidth:2,borderColor:'#fff',hoverOffset:5}]},options:{responsive:true,maintainAspectRatio:false,cutout:'70%',plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>c.label+': '+money(c.parsed)}}}}});}
  }
  if(select){select.addEventListener('change',function(){const u=new URL(location.href);u.searchParams.set('source',select.value);u.searchParams.delete('chart');if(select.value==='shop')u.searchParams.delete('tour_id');location.assign(u.toString());});}
  window.rvSubmitRank=function(form){let inp=form.querySelector('input[name=rank_type]');if(!inp){inp=document.createElement('input');inp.type='hidden';inp.name='rank_type';form.appendChild(inp);}inp.value=<?= json_encode($rankType) ?>;form.submit();};
  const period=document.getElementById('rvTxPeriod');
  if(period){const update=()=>document.querySelectorAll('.rv-custom-date').forEach(e=>e.style.display=period.value==='custom'?'block':'none');period.addEventListener('change',update);update();}
})();
</script>

</body>
</html>
