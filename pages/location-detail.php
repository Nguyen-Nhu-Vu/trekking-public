<?php
// trekking/pages/location-detail.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
$stmt->execute([$id]);
$location = $stmt->fetch();

if (!$location) {
    header("Location: " . BASE_URL . "pages/locations.php");
    exit;
}

$page_title = $location['name'];
$active_menu = 'location';

// Lấy các tour thuộc địa điểm này
$tours_stmt = $pdo->prepare("SELECT * FROM tours WHERE location_id = ? AND status = 1");
$tours_stmt->execute([$id]);
$tours = $tours_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:var(--shadow); margin-bottom:40px;">
        <img src="<?= htmlspecialchars($location['image'] ?? 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200') ?>" style="width:100%; height:380px; object-fit:cover;">
        <div style="padding:30px;">
            <span class="badge" style="background:var(--accent); font-size:0.9rem;"><?= htmlspecialchars($location['region']) ?></span>
            <h1 style="font-size:2.2rem; margin:12px 0; color:#0f172a;"><?= htmlspecialchars($location['name']) ?></h1>
            <p style="font-size:1.05rem; line-height:1.8; color:#334155;">
                <?= nl2br(htmlspecialchars($location['description'] ?? 'Đang cập nhật thông tin giới thiệu chi tiết.')) ?>
            </p>
        </div>
    </div>

    <h2 style="margin-bottom:20px; color:#0f172a;"><i class="fa-solid fa-mountain"></i> Các Tour Khởi Hành Tại <?= htmlspecialchars($location['name']) ?></h2>
    <div class="grid grid-3">
        <?php if (count($tours) > 0): ?>
            <?php foreach ($tours as $t): ?>
                <div class="tour-card">
                    <div class="card-img-wrap">
                        <img src="<?= htmlspecialchars($t['image']) ?>">
                        <span class="tour-badge"><?= htmlspecialchars($t['difficulty']) ?></span>
                    </div>
                    <div class="card-body">
                        <h3><a href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a></h3>
                        <div class="tour-footer">
                            <span class="price-tag"><?= format_price($t['price']) ?></span>
                            <a href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm">Xem chi tiết</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#64748b;">Hiện chưa có lịch khởi hành tour mới tại địa điểm này.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>