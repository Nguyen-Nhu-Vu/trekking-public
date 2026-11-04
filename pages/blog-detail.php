
<?php
// trekking/pages/blog-detail.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id = intval($_GET['id'] ?? 0);

// Tăng lượt xem bài viết
$pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$id]);

$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND status = 1");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: " . BASE_URL . "pages/blog.php");
    exit;
}

$page_title = $post['title'];
$active_menu = 'blog';

// Noi dung moi co the la van ban thuong, bai cu co the chua HTML.
// Van ban thuong duoc escape va giu nguyen xuong dong.
$content = (string)($post['content'] ?? '');
if (trim($content) === '') {
    $renderedContent = 'Nội dung bài viết đang được cập nhật...';
} elseif (preg_match('~<(?:p|div|h[1-6]|br|ul|ol|li|strong|b|em|i|blockquote|img|table|a)(?:\s|/?>)~i', $content)) {
    // Bao toan noi dung HTML cu - tuong tu cach hien thi truoc day.
    $renderedContent = $content;
} else {
    $renderedContent = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'), false);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Định dạng bài viết chuẩn văn bản báo chí / cẩm nang */
.blog-content-body {
    font-size: 1.05rem;
    line-height: 1.85;
    color: #334155;
}
.blog-content-body p {
    margin-bottom: 16px;
}
.blog-content-body ul, .blog-content-body ol {
    margin: 14px 0 18px 24px;
    padding-left: 10px;
}
.blog-content-body li {
    margin-bottom: 10px;
    line-height: 1.7;
}
.blog-content-body strong {
    color: #0f172a;
    font-weight: 700;
}
</style>

<div class="container section">
    <div style="max-width:850px; margin:0 auto; background:white; padding:40px; border-radius:12px; box-shadow:var(--shadow);">
        <small style="color:#64748b; font-size:0.85rem;">
            <i class="fa-regular fa-clock"></i> Ngày đăng: <?= date('d/m/Y', strtotime($post['created_at'])) ?> | 
            <i class="fa-regular fa-eye"></i> <?= $post['views'] ?> lượt xem
        </small>
        
        <h1 style="font-size:2.2rem; margin:15px 0 20px; color:#0f172a; line-height:1.35;">
            <?= htmlspecialchars($post['title']) ?>
        </h1>

        <div style="border-radius:10px; overflow:hidden; margin-bottom:30px;">
            <img src="<?= htmlspecialchars($post['image'] ?? 'https://images.unsplash.com/photo-1551632811-561732d1e306?w=1000') ?>" alt="<?= htmlspecialchars($post['title']) ?>" style="width:100%; max-height:420px; object-fit:cover;">
        </div>

        <!-- Ho tro van ban thuong xuong dong va giu dinh dang HTML bai cu -->
        <div class="blog-content-body">
            <?= $renderedContent ?>
        </div>

        <div style="margin-top:40px; padding-top:20px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <a href="<?= BASE_URL ?>pages/blog.php" class="btn btn-outline btn-sm">&larr; Quay lại danh sách bài viết</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
