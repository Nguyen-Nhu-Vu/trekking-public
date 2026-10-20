<?php
// trekking/pages/locations.php
$page_title = "Các Đỉnh Núi & Điểm Đến Trekking";
$active_menu = 'location';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$locations = $pdo->query("SELECT l.*, COUNT(t.id) as total_tours 
                          FROM locations l 
                          LEFT JOIN tours t ON l.id = t.location_id 
                          GROUP BY l.id 
                          ORDER BY l.id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div class="section-header" style="text-align:left; margin-bottom:30px;">
        <span class="section-subtitle">Đại Ngàn Hùng Vĩ</span>
        <h2 class="section-title">Những Cung Đường Núi Nổi Tiếng</h2>
    </div>

    <div class="grid grid-3">
        <?php foreach ($locations as $loc): ?>
            <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:var(--shadow); border:1px solid #e2e8f0; display:flex; flex-direction:column;">
                <div style="height:200px; overflow:hidden;">
                    <img src="<?= htmlspecialchars($loc['image'] ?? 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800') ?>" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div style="padding:20px; flex:1; display:flex; flex-direction:column;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span class="badge" style="background:var(--primary);"><?= htmlspecialchars($loc['region']) ?></span>
                        <small style="color:#64748b; font-weight:bold;"><?= $loc['total_tours'] ?> Tour hoạt động</small>
                    </div>
                    <h3 style="margin:6px 0 10px; color:#0f172a;"><?= htmlspecialchars($loc['name']) ?></h3>
                    <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin-bottom:15px;">
                        <?= htmlspecialchars($loc['description'] ?? 'Khám phá biển mây và cảnh sắc thiên nhiên kỳ vĩ.') ?>
                    </p>
                    <a href="<?= BASE_URL ?>pages/location-detail.php?id=<?= $loc['id'] ?>" class="btn btn-outline btn-sm" style="margin-top:auto; text-align:center;">
                        Xem Chi Tiết Điểm Đến &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>