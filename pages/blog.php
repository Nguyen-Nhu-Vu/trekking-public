<?php
// trekking/pages/blog.php
$page_title = "Kinh Nghiệm & Cẩm Nang Trekking";
$active_menu = 'blog';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$posts = $pdo->query("SELECT * FROM posts WHERE status = 1 ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div class="section-header" style="text-align:left; margin-bottom:30px;">
        <span class="section-subtitle">Hành Trang Leo Núi</span>
        <h2 class="section-title">Kinh Nghiệm & Kỹ Năng Sinh Tồn</h2>
    </div>

    <div class="grid grid-3">
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $p): ?>
                <div class="tour-card">
                    <div class="card-img-wrap" style="height:200px;">
                        <img src="<?= htmlspecialchars($p['image'] ?? 'https://images.unsplash.com/photo-1551632811-561732d1e306?w=800') ?>">
                    </div>
                    <div class="card-body">
                        <small style="color:#94a3b8; font-size:0.8rem; margin-bottom:6px; display:block;">
                            <i class="fa-regular fa-calendar"></i> <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                        </small>
                        <h3 style="font-size:1.15rem;"><a href="<?= BASE_URL ?>pages/blog-detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></h3>
                        <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin-bottom:15px;">
                            <?= htmlspecialchars($p['excerpt'] ?? 'Những chia sẻ thực tế từ các leader giàu kinh nghiệm...') ?>
                        </p>
                        <a href="<?= BASE_URL ?>pages/blog-detail.php?id=<?= $p['id'] ?>" style="color:var(--primary); font-weight:bold; font-size:0.88rem; margin-top:auto;">
                            Đọc bài viết &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#64748b;">Chưa có bài viết cẩm nang nào được đăng tải.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>