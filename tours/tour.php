<?php
// trekking/tours/tour.php
$page_title = "Danh Sách Tour Trekking Chuyên Nghiệp";
$active_menu = 'tour';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$search = sanitize($_GET['q'] ?? '');
$difficulty = sanitize($_GET['difficulty'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'id_asc');

$sql = "SELECT t.*, l.name as loc_name FROM tours t JOIN locations l ON t.location_id = l.id WHERE t.status = 1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (!empty($difficulty)) {
    $sql .= " AND t.difficulty LIKE ?";
    $params[] = "%{$difficulty}%";
}

if ($sort === 'price_asc') {
    $sql .= " ORDER BY t.price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY t.price DESC";
} else {
    $sql .= " ORDER BY t.id ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div class="section-header" style="text-align:left; margin-bottom:25px;">
        <span class="section-subtitle">Chinh Phục Đại Ngàn</span>
        <h2 class="section-title">Tất Cả Các Cung Đường Trekking & Leo Núi</h2>
    </div>

    <!-- BỘ LỌC TÌM KIẾM TOUR -->
    <form action="" method="GET" style="background:white; padding:20px; border-radius:12px; box-shadow:var(--shadow); margin-bottom:35px; border:1px solid #e2e8f0;">
        <div class="grid grid-3" style="gap:15px; align-items:center;">
            <div>
                <input type="text" name="q" class="form-control" placeholder="Tìm tên đỉnh núi (Fansipan, Lảo Thẩn...)" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div>
                <select name="difficulty" class="form-control">
                    <option value="">-- Tất cả độ khó --</option>
                    <option value="Dễ" <?= $difficulty === 'Dễ' ? 'selected' : '' ?>>Dễ (Người mới)</option>
                    <option value="Vừa phải" <?= $difficulty === 'Vừa phải' ? 'selected' : '' ?>>Vừa phải</option>
                    <option value="Khó" <?= $difficulty === 'Khó' ? 'selected' : '' ?>>Khó</option>
                    <option value="Khắc nghiệt" <?= $difficulty === 'Khắc nghiệt' ? 'selected' : '' ?>>Khắc nghiệt 10/10</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <select name="sort" class="form-control">
                    <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>Thứ tự mặc định</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá từ thấp đến cao</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá từ cao đến thấp</option>
                </select>
                <button type="submit" class="btn btn-primary" style="padding:8px 20px;"><i class="fa-solid fa-filter"></i> Lọc</button>
            </div>
        </div>
    </form>

    <div class="grid grid-3">
        <?php if (count($tours) > 0): ?>
            <?php foreach ($tours as $t): ?>
                <div class="tour-card">
                    <div class="card-img-wrap">
                        <img src="<?= htmlspecialchars($t['image']) ?>" alt="<?= htmlspecialchars($t['title']) ?>">
                        <span class="tour-badge"><?= htmlspecialchars($t['difficulty']) ?></span>
                        <span class="tour-slots">Còn <?= $t['available_slots'] ?> chỗ</span>
                    </div>
                    <div class="card-body">
                        <span style="font-size:0.8rem; color:var(--primary); font-weight:bold;"><i class="fa-solid fa-mountain"></i> <?= htmlspecialchars($t['altitude']) ?> • <?= htmlspecialchars($t['duration']) ?></span>
                        <h3><a href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a></h3>
                        <p style="font-size:0.88rem; color:#64748b; line-height:1.6; margin-bottom:15px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                            <?= htmlspecialchars($t['description']) ?>
                        </p>
                        <div class="tour-footer">
                            <span class="price-tag"><?= format_price($t['price']) ?></span>
                            <a href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= $t['id'] ?>" class="btn btn-accent btn-sm">Xem chi tiết</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: span 3; text-align:center; padding:60px 0; color:#64748b;">
                <i class="fa-solid fa-magnifying-glass" style="font-size:2.5rem; color:#cbd5e1; margin-bottom:12px; display:block;"></i>
                Không tìm thấy chuyến trekking nào phù hợp với từ khóa của bạn.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>