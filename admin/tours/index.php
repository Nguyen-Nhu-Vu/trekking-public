
<?php
ob_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireManager();

$current_page = 'tours';
$page_title = 'Quản lý Tour Trekking - VNN Travel';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['tour_list_csrf'])) {
    $_SESSION['tour_list_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['tour_list_csrf'];

function tourListH($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Giữ chức năng xóa tour, chuyển sang POST để tránh xóa nhầm bằng một đường link.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Phiên làm việc không hợp lệ.');
    }
    $deleteId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$deleteId || $deleteId < 1) {
        http_response_code(400);
        exit('Mã tour không hợp lệ.');
    }
    try {
        $stmt = $pdo->prepare('DELETE FROM tours WHERE id = ?');
        $stmt->execute([$deleteId]);
        $_SESSION['tour_list_notice'] = 'Đã xóa tour thành công.';
    } catch (Throwable $e) {
        error_log('Delete tour: ' . $e->getMessage());
        $_SESSION['tour_list_notice'] = 'Không thể xóa tour. Có thể tour đang liên kết với lịch hoặc đơn đặt.';
    }
    header('Location: index.php', true, 303);
    exit;
}

$notice = (string)($_SESSION['tour_list_notice'] ?? '');
unset($_SESSION['tour_list_notice']);
if (($_GET['msg'] ?? '') === 'deleted') $notice = 'Đã xóa tour thành công.';

$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 120, 'UTF-8');
$difficulty = (string)($_GET['difficulty'] ?? '');
$duration = (string)($_GET['duration'] ?? '');
$price = (string)($_GET['price'] ?? '');
$seats = (string)($_GET['seats'] ?? '');
$date = (string)($_GET['date'] ?? '');
$sort = (string)($_GET['sort'] ?? 'new');

$difficulties = ['Dễ', 'Vừa phải', 'Thử thách', 'Khắc nghiệt'];
$prices = [
    '' => 'Tất cả mức giá',
    'under1' => 'Dưới 1.000.000đ',
    '1to3' => '1.000.000 – dưới 3.000.000đ',
    '3to5' => '3.000.000 – dưới 5.000.000đ',
    '5to10' => '5.000.000 – dưới 10.000.000đ',
    'over10' => 'Từ 10.000.000đ trở lên',
];
$seatChoices = [
    '' => 'Tất cả tình trạng',
    'empty' => 'Hết chỗ (0)',
    'low' => 'Sắp hết chỗ (1–9)',
    'available' => 'Còn nhiều chỗ (từ 10)',
];
$sortChoices = [
    'new' => 'Tour mới nhất',
    'price_asc' => 'Giá thấp đến cao',
    'price_desc' => 'Giá cao đến thấp',
    'name' => 'Tên Tour A–Z',
    'seats' => 'Chỗ trống nhiều nhất',
];
if (!in_array($difficulty, $difficulties, true)) $difficulty = '';
if (!array_key_exists($price, $prices)) $price = '';
if (!array_key_exists($seats, $seatChoices)) $seats = '';
if (!array_key_exists($sort, $sortChoices)) $sort = 'new';
$dateValid = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if ($date === '' || !$dateValid || $dateValid->format('Y-m-d') !== $date) $date = '';

$error = '';
$durations = [];
$tours = [];
$total = 0;
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$pageCount = 1;

try {
    // Chỉ dùng các giá trị thời lượng thực sự có trong CSDL.
    $durations = $pdo->query("SELECT DISTINCT duration FROM tours WHERE duration IS NOT NULL AND duration <> '' ORDER BY duration")
        ->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($duration, $durations, true)) $duration = '';

    /*
     * tour_departures.available_slots là TỔNG SỨC CHỨA của một ngày;
     * số còn nhận = tổng sức chứa - tổng số khách đặt chưa bị hủy.
     * Khi chọn ngày, mọi điều kiện liên quan đến chỗ đều được xét trên
     * CHÍNH NGÀY ĐÓ; không dùng chỗ của một lịch khác cùng tour.
     * Khi chưa chọn ngày, xét các lịch đang mở chưa diễn ra và chọn
     * tổng số chỗ còn nhận trên các lịch đang mở.
     */
    $dateCondition = $date !== '' ? 'd.departure_date = ?' : 'd.departure_date >= CURDATE()';
    $sqlFrom = "
        FROM tours t
        LEFT JOIN locations l ON l.id = t.location_id
        LEFT JOIN (
            SELECT s.tour_id,
                   SUM(s.remaining_slots) AS free_slots,
                   SUM(s.capacity) AS total_capacity,
                   SUM(s.booked) AS total_booked,
                   COUNT(*) AS open_departures
            FROM (
                SELECT d.tour_id, d.available_slots AS capacity, COALESCE(b.booked,0) AS booked,
                       GREATEST(0, d.available_slots - COALESCE(b.booked, 0)) AS remaining_slots
                FROM tour_departures d
                LEFT JOIN (
                    SELECT tour_id, departure_date,
                           SUM(number_people) AS booked
                    FROM tour_bookings
                    WHERE LOWER(TRIM(status)) NOT IN
                        ('đã hủy','đã huỷ','hủy','huỷ','cancelled','canceled','rejected','từ chối')
                    GROUP BY tour_id, departure_date
                ) b ON b.tour_id = d.tour_id AND b.departure_date = d.departure_date
                WHERE d.status = 1 AND {$dateCondition}
            ) s
            GROUP BY s.tour_id
        ) availability ON availability.tour_id = t.id
    ";
    $params = $date !== '' ? [$date] : [];
    $where = ['1=1'];
    if ($search !== '') {
        $where[] = '(t.title LIKE ? OR l.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($difficulty !== '') {
        $where[] = 't.difficulty = ?';
        $params[] = $difficulty;
    }
    if ($duration !== '') {
        $where[] = 't.duration = ?';
        $params[] = $duration;
    }
    $priceRanges = [
        'under1' => [0, 1000000],
        '1to3' => [1000000, 3000000],
        '3to5' => [3000000, 5000000],
        '5to10' => [5000000, 10000000],
    ];
    if (isset($priceRanges[$price])) {
        $where[] = 't.price >= ? AND t.price < ?';
        array_push($params, ...$priceRanges[$price]);
    } elseif ($price === 'over10') {
        $where[] = 't.price >= ?';
        $params[] = 10000000;
    }
    if ($date !== '') $where[] = 'availability.open_departures > 0';
    if ($seats === 'empty') $where[] = 'availability.free_slots = 0';
    if ($seats === 'low') $where[] = 'availability.free_slots BETWEEN 1 AND 9';
    if ($seats === 'available') $where[] = 'availability.free_slots >= 10';
    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare('SELECT COUNT(*) ' . $sqlFrom . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pageCount = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    $orderBy = match ($sort) {
        'price_asc' => 't.price ASC, t.id DESC',
        'price_desc' => 't.price DESC, t.id DESC',
        'name' => 't.title ASC, t.id DESC',
        'seats' => 'availability.free_slots DESC, t.id DESC',
        default => 't.id DESC',
    };
    $query = 'SELECT t.*, l.name AS loc_name, availability.free_slots, availability.total_capacity, availability.total_booked, availability.open_departures '
        . $sqlFrom . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . $pageSize . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Tour list filters: ' . $e->getMessage());
    $error = 'Không thể tải danh sách Tour. Vui lòng kiểm tra kết nối và cấu trúc CSDL.';
}

function tourListPageUrl(int $n): string {
    $params = $_GET;
    $params['page'] = $n;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= tourListH($page_title) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
.tour-list-main{flex:1;min-width:0;background:#f1f5f9}
.tour-list-content{padding:24px;min-width:0}
.tour-list-topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.tour-list-topbar strong{color:#102e52}
.tour-filter-panel,.tour-table-panel{background:#fff;border:1px solid #e0e8f1;border-radius:14px;margin-bottom:18px;overflow:hidden}
.tour-filter-panel{padding:20px}
.tour-filter-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}
.tour-filter-field{min-width:0}
.tour-filter-field.wide{grid-column:span 3}
.tour-filter-field label{display:block;margin-bottom:7px;color:#173454;font-size:13px;font-weight:700}
.tour-filter-control{display:block;width:100%;height:43px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#334155;padding:9px 12px;font:inherit;font-size:13px;min-width:0}
.tour-filter-control:focus{outline:none;border-color:#1d4ed8;box-shadow:0 0 0 3px #dbeafe}
.tour-filter-actions{display:flex;gap:10px;margin-top:17px;flex-wrap:wrap;align-items:center}
.tour-filter-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:10px 18px;border:0;border-radius:9px;background:#1d4ed8;color:#fff;text-decoration:none;font-size:13px;font-weight:800;cursor:pointer}
.tour-filter-btn.clear{background:#e2e8f0;color:#334155}
.tour-result-count{margin-left:auto;color:#64748b;font-size:13px}
.tour-table-scroll{overflow-x:auto;max-width:100%}
.tour-table{width:100%;min-width:1040px;border-collapse:collapse;table-layout:fixed}
.tour-table th,.tour-table td{padding:14px 13px;text-align:left;vertical-align:middle;border-bottom:1px solid #e2e8f0;font-size:13px;line-height:1.6;overflow-wrap:break-word}
.tour-table th{background:#f8fafc;font-weight:800;color:#102e52}
.tour-table tbody tr:hover{background:#f8fafc}
.tour-table img{display:block;width:55px;height:55px;object-fit:cover;border-radius:7px;background:#e2e8f0}
.tour-difficulty{display:inline-block;padding:5px 8px;border-radius:7px;background:#059669;color:#fff;font-size:12px;font-weight:700}
.tour-price{color:#ea580c;font-weight:800;white-space:nowrap}
.tour-seats{display:block;font-weight:700;color:#166534}
.tour-seats.low{color:#ca8a04}.tour-seats.empty{color:#dc2626}.tour-seats.none{color:#64748b;font-weight:400}
.tour-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.tour-actions form{margin:0}
.tour-icon-btn{display:inline-flex;width:38px;height:36px;justify-content:center;align-items:center;border:0;border-radius:9px;color:white;text-decoration:none;cursor:pointer}
.tour-icon-btn.edit{background:#166534}.tour-icon-btn.delete{background:#ef4444}
.tour-pages{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:7px;padding:20px}
.tour-pages a{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;text-decoration:none;color:#173454}
.tour-pages a[aria-current=page]{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
.tour-message{padding:14px 17px;border-radius:9px;margin-bottom:15px;background:#dcfce7;color:#166534}
.tour-message.error{background:#fee2e2;color:#991b1b}
@media(max-width:950px){.tour-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.tour-filter-field.wide{grid-column:span 2}}
@media(max-width:600px){.tour-list-content{padding:12px}.tour-filter-grid{grid-template-columns:1fr}.tour-filter-field.wide{grid-column:span 1}}
</style>
</head>
<body class="admin-body">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="admin-main tour-list-main">
    <div class="admin-topbar tour-list-topbar">
        <strong>QUẢN LÝ DANH SÁCH TOUR LEO NÚI</strong>
        <a href="add.php" class="btn btn-accent btn-sm"><i class="fa-solid fa-plus"></i> Thêm Tour Mới</a>
    </div>
    <div class="admin-content tour-list-content">
        <?php if ($notice !== ''): ?><div class="tour-message"><?= tourListH($notice) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="tour-message error"><?= tourListH($error) ?></div><?php endif; ?>
        <section class="tour-filter-panel">
            <form method="GET" action="index.php">
                <div class="tour-filter-grid">
                    <div class="tour-filter-field wide">
                        <label for="q">Tìm kiếm Tour</label>
                        <input id="q" name="q" class="tour-filter-control" type="search" maxlength="120" placeholder="Nhập tên Tour hoặc địa điểm..." value="<?= tourListH($search) ?>">
                    </div>
                    <div class="tour-filter-field">
                        <label for="difficulty">Độ khó</label>
                        <select id="difficulty" name="difficulty" class="tour-filter-control">
                            <option value="">Tất cả độ khó</option>
                            <?php foreach ($difficulties as $item): ?>
                                <option value="<?= tourListH($item) ?>" <?= $difficulty === $item ? 'selected' : '' ?>><?= tourListH($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tour-filter-field">
                        <label for="duration">Thời gian Tour</label>
                        <select id="duration" name="duration" class="tour-filter-control">
                            <option value="">Tất cả thời gian</option>
                            <?php foreach ($durations as $item): ?>
                                <option value="<?= tourListH($item) ?>" <?= $duration === $item ? 'selected' : '' ?>><?= tourListH($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tour-filter-field">
                        <label for="price">Khoảng giá vé</label>
                        <select id="price" name="price" class="tour-filter-control">
                            <?php foreach ($prices as $value => $label): ?>
                                <option value="<?= tourListH($value) ?>" <?= $price === $value ? 'selected' : '' ?>><?= tourListH($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tour-filter-field">
                        <label for="seats">Tình trạng chỗ</label>
                        <select id="seats" name="seats" class="tour-filter-control">
                            <?php foreach ($seatChoices as $value => $label): ?>
                                <option value="<?= tourListH($value) ?>" <?= $seats === $value ? 'selected' : '' ?>><?= tourListH($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tour-filter-field">
                        <label for="date">Ngày khởi hành</label>
                        <input id="date" name="date" type="date" class="tour-filter-control" value="<?= tourListH($date) ?>" onclick="if(this.showPicker) try{this.showPicker()}catch(e){}">
                    </div>
                    <div class="tour-filter-field">
                        <label for="sort">Sắp xếp theo</label>
                        <select id="sort" name="sort" class="tour-filter-control">
                            <?php foreach ($sortChoices as $value => $label): ?>
                                <option value="<?= tourListH($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= tourListH($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="tour-filter-actions">
                    <button class="tour-filter-btn" type="submit"><i class="fa-solid fa-filter"></i> Lọc</button>
                    <a class="tour-filter-btn clear" href="index.php">Xóa lọc</a>
                    <span class="tour-result-count"><?= (int)$total ?> Tour phù hợp</span>
                </div>
            </form>
        </section>
        <section class="tour-table-panel">
            <div class="tour-table-scroll">
                <table class="tour-table">
                    <colgroup>
                        <col style="width:7%"><col style="width:22%"><col style="width:17%"><col style="width:11%">
                        <col style="width:11%"><col style="width:12%"><col style="width:12%"><col style="width:8%">
                    </colgroup>
                    <thead><tr><th>Ảnh</th><th>Tên Tour</th><th>Địa Điểm</th><th>Độ Khó</th><th>Thời Gian</th><th>Giá Vé</th><th>Chỗ Trống</th><th>Thao Tác</th></tr></thead>
                    <tbody>
                        <?php foreach ($tours as $tour): ?>
                            <?php
                                $free = $tour['free_slots'];
                                $seatClass = $free === null ? 'none' : ((int)$free === 0 ? 'empty' : ((int)$free < 10 ? 'low' : ''));
                                $seatLabel = $free === null ? 'Chưa có lịch mở' : (string)(int)$free;
                            ?>
                            <tr>
                                <td><img src="<?= tourListH($tour['image']) ?>" alt="<?= tourListH($tour['title']) ?>" loading="lazy"></td>
                                <td><strong><?= tourListH($tour['title']) ?></strong></td>
                                <td><?= tourListH($tour['loc_name'] ?? '—') ?></td>
                                <td><span class="tour-difficulty"><?= tourListH($tour['difficulty']) ?></span></td>
                                <td><?= tourListH($tour['duration']) ?></td>
                                <td class="tour-price"><?= format_price($tour['price']) ?></td>
                                <td><span class="tour-seats <?= $seatClass ?>"><?= tourListH($seatLabel) ?></span></td>
                                <td><div class="tour-actions">
                                    <a class="tour-icon-btn edit" href="edit.php?id=<?= (int)$tour['id'] ?>" title="Sửa Tour" aria-label="Sửa Tour"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <form class="tour-delete-form" method="POST" action="index.php" onsubmit="return confirm('Bạn có chắc muốn xóa Tour này?');">
                                        <input type="hidden" name="csrf" value="<?= tourListH($csrf) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$tour['id'] ?>">
                                        <button type="submit" class="tour-icon-btn delete" title="Xóa Tour" aria-label="Xóa Tour"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$tours): ?><tr><td colspan="8" style="text-align:center;padding:32px;color:#64748b">Không có Tour phù hợp.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pageCount > 1): ?>
                <nav class="tour-pages" aria-label="Phân trang">
                    <?php for ($n = 1; $n <= $pageCount; $n++): ?>
                        <a href="<?= tourListH(tourListPageUrl($n)) ?>" <?= $n === $page ? 'aria-current="page"' : '' ?>><?= $n ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
