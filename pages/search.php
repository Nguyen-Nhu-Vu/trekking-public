<?php
// trekking/pages/search.php
$page_title = "Kết Quả Tìm Kiếm";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$q = sanitize($_GET['q'] ?? '');
$param = "%{$q}%";

// Tìm kiếm tour
$stmt_t = $pdo->prepare("SELECT * FROM tours WHERE status = 1 AND (title LIKE ? OR description LIKE ?)");
$stmt_t->execute([$param, $param]);
$tours = $stmt_t->fetchAll();

// Tìm kiếm sản phẩm
$stmt_p = $pdo->prepare("SELECT * FROM products WHERE status = 1 AND (name LIKE ? OR brand LIKE ? OR description LIKE ?)");
$stmt_p->execute([$param, $param, $param]);
$products = $stmt_p->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <h2 style="margin-bottom:25px;">Kết quả tìm kiếm cho: "<strong><?= htmlspecialchars($q) ?></strong>"</h2>

    <h3 style="color:var(--primary); margin-bottom:15px;"><i class="fa-solid fa-mountain"></i> Tour Leo Núi Tìm Thấy (<?= count($tours) ?>)</h3>
    <?php if (count($tours) > 0): ?>
        <div class="grid grid-3" style="margin-bottom:40px;">
            <?php foreach ($tours as $t): ?>
                <div class="tour-card">
                    <div class="card-img-wrap">
                        <img src="<?= htmlspecialchars($t['image']) ?>">
                    </div>
                    <div class="card-body">
                        <h3><a href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a></h3>
                        <div class="tour-footer">
                            <div class="price-tag"><?= format_price($t['price']) ?></div>
                            <a href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm">Xem chi tiết</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color:var(--gray); margin-bottom:40px;">Không tìm thấy chuyến tour nào phù hợp với từ khóa.</p>
    <?php endif; ?>

    <h3 style="color:var(--accent); margin-bottom:15px;"><i class="fa-solid fa-bag-shopping"></i> Dụng Cụ Dã Ngoại Tìm Thấy (<?= count($products) ?>)</h3>
    <?php if (count($products) > 0): ?>
        <div class="grid grid-4">
            <?php foreach ($products as $p): ?>
                <div class="prod-card">
                    <div class="prod-img">
                        <img src="<?= htmlspecialchars($p['image']) ?>">
                    </div>
                    <h3 class="prod-title"><a href="<?= BASE_URL ?>shop/product-detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></h3>
                    <div class="price-tag" style="margin-top:auto; font-size:1.1rem;"><?= format_price($p['price']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color:var(--gray);">Không tìm thấy sản phẩm nào phù hợp với từ khóa.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>