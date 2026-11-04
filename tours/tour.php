
<?php
// VNN Travel - Danh sach tour

$page_title = "Danh Sách Tour Trekking Chuyên Nghiệp";
$active_menu = 'tour';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$search = sanitize($_GET['q'] ?? '');
$difficulty = sanitize($_GET['difficulty'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'id_asc');
$departureDate = trim((string)($_GET['departure_date'] ?? ''));

// Chỉ chấp nhận ngày hợp lệ theo định dạng YYYY-MM-DD
if ($departureDate !== '') {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $departureDate);
    if (!$date || $date->format('Y-m-d') !== $departureDate) {
        $departureDate = '';
    }
}

$sql = "
    SELECT t.*, l.name AS loc_name
    FROM tours t
    JOIN locations l ON t.location_id = l.id
    WHERE t.status = 1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($difficulty !== '') {
    $sql .= " AND t.difficulty LIKE ?";
    $params[] = "%{$difficulty}%";
}

if ($departureDate !== '') {
    $sql .= " AND EXISTS (
        SELECT 1
        FROM tour_departures d
        WHERE d.tour_id = t.id
          AND d.status = 1
          AND d.departure_date = ?
    )";
    $params[] = $departureDate;
}

switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY t.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY t.price DESC";
        break;
    default:
        $sql .= " ORDER BY t.id ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .tour-filter-box {
        background: #ffffff;
        padding: 24px;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        border: 1px solid #e2e8f0;
        margin-bottom: 35px;
    }

    .tour-filter-grid {
        display: grid;
        grid-template-columns: 1.2fr 1.2fr 1.2fr 1.3fr;
        gap: 18px;
        align-items: end;
    }

    .tour-filter-field label {
        display: block;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .tour-filter-field .form-control {
        width: 100%;
        height: 54px;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        padding: 0 16px;
        font-size: 16px;
        box-sizing: border-box;
        background: #fff;
    }

    .tour-filter-actions {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        flex-wrap: nowrap;
    }

    .tour-filter-actions .form-control {
        flex: 1;
        min-width: 0;
        height: 54px;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        padding: 0 16px;
        font-size: 16px;
        box-sizing: border-box;
        background: #fff;
    }

    .tour-filter-actions .btn {
        height: 54px;
        padding: 0 20px;
        border-radius: 12px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 16px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }

    .tour-filter-actions .btn-filter {
        background: #1f8f4d;
        color: #fff;
    }

    .tour-filter-actions .btn-reset {
        background: #e2e8f0;
        color: #1e293b;
    }

    @media (max-width: 1200px) {
        .tour-filter-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .tour-filter-grid {
            grid-template-columns: 1fr;
        }

        .tour-filter-actions {
            flex-wrap: wrap;
        }

        .tour-filter-actions .form-control,
        .tour-filter-actions .btn {
            width: 100%;
            flex: unset;
        }
    }
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
                <input
                    type="text"
                    id="tour-search"
                    name="q"
                    class="form-control"
                    placeholder="Tìm tên đỉnh núi (Fansipan, Lảo Thẩn...)"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <div class="tour-filter-field">
                <label for="tour-difficulty">Độ khó</label>
                <select name="difficulty" id="tour-difficulty" class="form-control">
                    <option value="">-- Tất cả độ khó --</option>
                    <?php foreach (['Dễ', 'Vừa phải', 'Khó', 'Khắc nghiệt'] as $level): ?>
                        <option
                            value="<?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $difficulty === $level ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tour-filter-field">
                <label for="departure-date-filter">Ngày khởi hành</label>
                <input
                    type="date"
                    name="departure_date"
                    id="departure-date-filter"
                    class="form-control"
                    value="<?= htmlspecialchars($departureDate, ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <div class="tour-filter-field">
                <label for="tour-sort">Sắp xếp & thao tác</label>
                <div class="tour-filter-actions">
                    <select name="sort" id="tour-sort" class="form-control">
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>
                            Thứ tự mặc định
                        </option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>
                            Giá từ thấp đến cao
                        </option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>
                            Giá từ cao đến thấp
                        </option>
                    </select>

                    <button type="submit" class="btn btn-filter">
                        <i class="fa-solid fa-filter"></i>
                        Lọc
                    </button>

                    <a
                        href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>tours/tour.php"
                        class="btn btn-reset"
                    >
                        Xóa lọc
                    </a>
                </div>
            </div>

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
