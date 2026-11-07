<?php
// VNN Travel - Danh sach tour: bo loc ket hop tren MySQL.
$page_title = "Danh Sách Tour Trekking Chuyên Nghiệp";
$active_menu = 'tour';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function tourFilterEsc($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$search = trim((string)($_GET['q'] ?? ''));
$difficulty = trim((string)($_GET['difficulty'] ?? ''));
$departureDate = trim((string)($_GET['departure_date'] ?? ''));
$duration = trim((string)($_GET['duration'] ?? ''));
$priceRange = trim((string)($_GET['price_range'] ?? ''));
$seatStatus = trim((string)($_GET['seat_status'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'id_asc'));

$allowedDifficulties = ['Dễ', 'Vừa phải', 'Thử thách', 'Khắc nghiệt'];
$allowedDurations = ['day', '2', '3', '4', '5plus'];
$allowedPrices = ['under1', '1to3', '3to5', '5to10', 'over10'];
$allowedSeats = ['available', 'low', 'full'];
$allowedSorts = ['id_asc', 'price_asc', 'price_desc', 'name_asc', 'newest'];
if (!in_array($difficulty, $allowedDifficulties, true)) $difficulty = '';
if (!in_array($duration, $allowedDurations, true)) $duration = '';
if (!in_array($priceRange, $allowedPrices, true)) $priceRange = '';
if (!in_array($seatStatus, $allowedSeats, true)) $seatStatus = '';
if (!in_array($sort, $allowedSorts, true)) $sort = 'id_asc';
if ($departureDate !== '') {
    $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $departureDate);
    if (!$validDate || $validDate->format('Y-m-d') !== $departureDate) $departureDate = '';
}

$sql = "SELECT t.*, l.name AS loc_name FROM tours t
        JOIN locations l ON t.location_id = l.id
        WHERE t.status = 1";
$params = [];

if ($search !== '') {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ? OR l.name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($difficulty !== '') {
    $sql .= " AND t.difficulty = ?";
    $params[] = $difficulty;
}
if ($duration !== '') {
    // Database duration co ca 'Trong ngay', '2N1D', '3N2D' va dang chu khac.
    // Kiem tra so ngay dau tien, khong gop nham 12 ngay thanh 2 ngay.
    switch ($duration) {
        case 'day':
            $sql .= " AND (LOWER(t.duration) LIKE '%trong ngày%' OR LOWER(t.duration) LIKE '%trong ngay%' OR t.duration REGEXP '^1[[:space:]]*(N|n|ngày|ngay)')";
            break;
        case '2': case '3': case '4':
            $sql .= " AND t.duration REGEXP ?";
            $params[] = '^' . $duration . '[[:space:]]*(N|n|ngày|ngay)';
            break;
        case '5plus':
            $sql .= " AND t.duration REGEXP '^[[:space:]]*([5-9]|[1-9][0-9]+)[[:space:]]*(N|n|ngày|ngay)'";
            break;
    }
}
if ($priceRange !== '') {
    $bounds = [
        'under1' => [null, 1000000],
        '1to3' => [1000000, 3000000],
        '3to5' => [3000000, 5000000],
        '5to10' => [5000000, 10000000],
        'over10' => [10000000, null],
    ];
    [$minPrice, $maxPrice] = $bounds[$priceRange];
    if ($minPrice !== null) { $sql .= ' AND t.price >= ?'; $params[] = $minPrice; }
    if ($maxPrice !== null) { $sql .= ' AND t.price < ?'; $params[] = $maxPrice; }
}

// Tinh so cho cua TUNG lich: suc chua lich - tong khach da dat, tru booking Da huy.
// Dung chung 1 EXISTS de ngay khoi hanh va tinh trang cho cung thuoc MOT lich.
if ($departureDate !== '' || $seatStatus !== '') {
    $sql .= " AND EXISTS (
        SELECT 1 FROM tour_departures d
        WHERE d.tour_id = t.id
          AND d.status = 1
          AND d.departure_date >= CURDATE()";
    if ($departureDate !== '') {
        $sql .= ' AND d.departure_date = ?';
        $params[] = $departureDate;
    }
    if ($seatStatus !== '') {
        $remaining = "(COALESCE(d.available_slots, 0) - COALESCE((
            SELECT SUM(b.number_people) FROM tour_bookings b
            WHERE b.tour_id = d.tour_id AND b.departure_date = d.departure_date
              AND LOWER(TRIM(b.status)) IN (
                  'đã xác nhận', 'đã thanh toán', 'đã hoàn thành',
                  'confirmed', 'paid', 'completed'
              )
        ), 0))";
        switch ($seatStatus) {
            case 'available': $sql .= " AND $remaining > 0"; break;
            case 'low': $sql .= " AND $remaining BETWEEN 1 AND 5"; break;
            case 'full': $sql .= " AND $remaining <= 0"; break;
        }
    }
    $sql .= ')';
}

switch ($sort) {
    case 'price_asc': $sql .= ' ORDER BY t.price ASC, t.id ASC'; break;
    case 'price_desc': $sql .= ' ORDER BY t.price DESC, t.id ASC'; break;
    case 'name_asc': $sql .= ' ORDER BY t.title ASC, t.id ASC'; break;
    case 'newest': $sql .= ' ORDER BY t.created_at DESC, t.id DESC'; break;
    default: $sql .= ' ORDER BY t.id ASC'; break;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .tour-filter-box { background:#fff; padding:24px; border-radius:16px;
        box-shadow:0 8px 24px rgba(15,23,42,.06); border:1px solid #e2e8f0; margin-bottom:35px; }
    .tour-filter-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; align-items:end; }
    .tour-filter-field { min-width:0; }
    .tour-filter-field label { display:block; font-size:15px; font-weight:700; color:#0f172a; margin-bottom:8px; }
    .tour-filter-field .form-control { width:100%; min-width:0; height:52px; border-radius:12px;
        border:1px solid #cbd5e1; padding:0 14px; font-size:15px; box-sizing:border-box;
        background:#fff; color:#0f172a; }
    .tour-filter-field .form-control:focus { outline:2px solid rgba(31,143,77,.2); border-color:#1f8f4d; }
    .tour-filter-actions { display:flex; justify-content:flex-end; gap:10px; align-items:center; flex-wrap:wrap; margin-top:20px; }
    .tour-filter-actions .btn { min-height:48px; padding:0 22px; border-radius:12px; border:0;
        display:inline-flex; align-items:center; justify-content:center; gap:8px;
        font-size:15px; font-weight:700; text-decoration:none; white-space:nowrap; cursor:pointer; }
    .tour-filter-actions .btn-filter { background:#1f8f4d; color:#fff; }
    .tour-filter-actions .btn-reset { background:#e2e8f0; color:#1e293b; }
    @media(max-width:900px) { .tour-filter-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:600px) { .tour-filter-box { padding:16px; } .tour-filter-grid { grid-template-columns:1fr; gap:14px; }
        .tour-filter-actions .btn { flex:1; } }
</style>

<div class="container section">
    <div class="section-header" style="text-align:left; margin-bottom:25px;">
        <span class="section-subtitle">Chinh Phục Đại Ngàn</span>
        <h2 class="section-title">Tất Cả Các Cung Đường Trekking & Leo Núi</h2>
    </div>

    <form method="GET" action="" class="tour-filter-box">
        <div class="tour-filter-grid">
            <div class="tour-filter-field">
                <label for="tour-search">Tìm kiếm</label>
                <input type="text" id="tour-search" name="q" class="form-control"
                    placeholder="Tên tour, đỉnh núi, địa điểm..." value="<?= tourFilterEsc($search) ?>">
            </div>
            <div class="tour-filter-field">
                <label for="tour-difficulty">Độ khó</label>
                <select id="tour-difficulty" name="difficulty" class="form-control">
                    <option value="">Tất cả độ khó</option>
                    <?php foreach ($allowedDifficulties as $level): ?>
                        <option value="<?= tourFilterEsc($level) ?>" <?= $difficulty === $level ? 'selected' : '' ?>><?= tourFilterEsc($level) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tour-filter-field">
                <label for="tour-date">Ngày khởi hành</label>
                <input type="date" name="departure_date" id="tour-date" class="form-control"
                    value="<?= tourFilterEsc($departureDate) ?>" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="tour-filter-field">
                <label for="tour-price">Khoảng giá</label>
                <select id="tour-price" name="price_range" class="form-control">
                    <option value="">Tất cả mức giá</option>
                    <?php foreach (['under1'=>'Dưới 1 triệu', '1to3'=>'1–3 triệu', '3to5'=>'3–5 triệu', '5to10'=>'5–10 triệu', 'over10'=>'Từ 10 triệu'] as $key=>$label): ?>
                        <option value="<?= $key ?>" <?= $priceRange === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tour-filter-field">
                <label for="tour-duration">Thời gian tour</label>
                <select id="tour-duration" name="duration" class="form-control">
                    <option value="">Tất cả thời gian</option>
                    <?php foreach (['day'=>'Trong ngày','2'=>'2N1Đ','3'=>'3N2Đ','4'=>'4N3Đ','5plus'=>'Từ 5 ngày'] as $key=>$label): ?>
                        <option value="<?= $key ?>" <?= $duration === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tour-filter-field">
                <label for="tour-seats">Tình trạng chỗ</label>
                <select id="tour-seats" name="seat_status" class="form-control">
                    <option value="">Tất cả tình trạng</option>
                    <option value="available" <?= $seatStatus === 'available' ? 'selected' : '' ?>>Còn chỗ</option>
                    <option value="low" <?= $seatStatus === 'low' ? 'selected' : '' ?>>Sắp hết chỗ (1–5)</option>
                    <option value="full" <?= $seatStatus === 'full' ? 'selected' : '' ?>>Hết chỗ</option>
                </select>
            </div>
            <div class="tour-filter-field">
                <label for="tour-sort">Sắp xếp</label>
                <select id="tour-sort" name="sort" class="form-control">
                    <?php foreach (['id_asc'=>'Mặc định','price_asc'=>'Giá thấp đến cao','price_desc'=>'Giá cao đến thấp','name_asc'=>'Tên A–Z','newest'=>'Mới nhất'] as $key=>$label): ?>
                        <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="tour-filter-actions">
            <button type="submit" class="btn btn-filter"><i class="fa-solid fa-filter"></i> Lọc</button>
            <a href="<?= tourFilterEsc(rtrim(BASE_URL, '/') . '/tours/tour.php') ?>" class="btn btn-reset">Xóa lọc</a>
        </div>
    </form>

    <div class="grid grid-3">

        <?php if (!empty($tours)): ?>
            <?php foreach ($tours as $t): ?>
                <div class="tour-card">

                    <div class="card-img-wrap">
                        <img
                            src="<?= htmlspecialchars((string)$t['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars((string)$t['title'], ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy"
                        >

                        <span class="tour-badge">
                            <?= htmlspecialchars((string)$t['difficulty'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="card-body">

                        <span style="font-size:.8rem; color:var(--primary); font-weight:bold;">
                            <i class="fa-solid fa-mountain"></i>
                            <?= htmlspecialchars((string)$t['altitude'], ENT_QUOTES, 'UTF-8') ?>
                            •
                            <?= htmlspecialchars((string)$t['duration'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <h3>
                            <a href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= (int)$t['id'] ?>">
                                <?= htmlspecialchars((string)$t['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h3>

                        <p style="
                            font-size:.88rem;
                            color:#64748b;
                            line-height:1.6;
                            margin-bottom:15px;
                            display:-webkit-box;
                            -webkit-line-clamp:2;
                            -webkit-box-orient:vertical;
                            overflow:hidden;
                        ">
                            <?= htmlspecialchars((string)$t['description'], ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div class="tour-footer">
                            <span class="price-tag">
                                <?= format_price($t['price']) ?>
                            </span>

                            <a
                                href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= (int)$t['id'] ?>"
                                class="btn btn-accent btn-sm"
                            >
                                Xem chi tiết
                            </a>
                        </div>

                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column:span 3; text-align:center; padding:60px 0; color:#64748b;">
                <i class="fa-solid fa-magnifying-glass"
                   style="font-size:2.5rem; color:#cbd5e1; margin-bottom:12px; display:block;"></i>
                Không tìm thấy chuyến trekking nào phù hợp.
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
