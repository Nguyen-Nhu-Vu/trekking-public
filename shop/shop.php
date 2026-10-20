<?php
// trekking/shop/shop.php
$page_title = "Cửa Hàng Dụng Cụ Dã Ngoại - VNN Travel";
$active_menu = 'shop';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$search = sanitize($_GET['q'] ?? '');
$cat_id = intval($_GET['cat'] ?? 0);
$sort = sanitize($_GET['sort'] ?? 'newest');

$sql = "SELECT p.*, c.name as cat_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.brand LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($cat_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $cat_id;
}

if ($sort === 'price_asc') {
    $sql .= " ORDER BY p.price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY p.price DESC";
} else {
    $sql .= " ORDER BY p.id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* =======================================================
   BỘ STYLE CHỐNG CẮT CHỮ TUYỆT ĐỐI CHO TRANG CỬA HÀNG
   ======================================================= */
.shop-product-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
}
@media (max-width: 992px) {
    .shop-product-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 576px) {
    .shop-product-grid {
        grid-template-columns: 1fr;
    }
}

.shop-prod-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.shop-prod-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}

.shop-prod-thumb {
    height: 195px;
    border-radius: 8px;
    overflow: hidden;
    background: #f8fafc;
    margin-bottom: 12px;
}
.shop-prod-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.shop-prod-cat {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #94a3b8;
    letter-spacing: 0.8px;
    margin-bottom: 6px;
    display: block;
}

/* XÓA BỎ MỌI GIỚI HẠN HEIGHT GÂY CẮT CHÂN CHỮ */
.shop-prod-name {
    font-size: 0.96rem !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    line-height: 1.5 !important;
    margin: 0 0 14px 0 !important;
    padding: 0 !important;
    height: auto !important;
    max-height: none !important;
    min-height: 58px; /* Đảm bảo đủ chiều cao cho 2-3 dòng chữ và dấu tiếng Việt */
    display: block !important;
    word-break: break-word;
}
.shop-prod-name a {
    color: #0f172a;
    text-decoration: none;
    display: inline;
}
.shop-prod-name a:hover {
    color: #ea580c;
}

.shop-prod-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #f1f5f9;
    margin-top: auto;
}
.shop-price-value {
    color: #ea580c;
    font-size: 1.15rem;
    font-weight: 800;
}
.shop-btn-cart {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #ea580c;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: background 0.2s ease;
}
.shop-btn-cart:hover {
    background: #c2410c;
}
</style>

<div class="container section">
    <div class="section-header" style="text-align:left; margin-bottom:25px;">
        <span class="section-subtitle">VNN TRAVEL GEAR</span>
        <h2 class="section-title">Trang Bị Trekking & Dã Ngoại Chuyên Nghiệp</h2>
    </div>

    <!-- BỘ LỌC TÌM KIẾM TRANG BỊ -->
    <form action="" method="GET" style="background:white; padding:18px 20px; border-radius:12px; box-shadow:var(--shadow); margin-bottom:35px; border:1px solid #e2e8f0;">
        <div class="grid grid-3" style="gap:15px; align-items:center;">
            <div>
                <input type="text" name="q" class="form-control" placeholder="Tìm tên trang bị..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div>
                <select name="cat" class="form-control">
                    <option value="0">-- Tất cả danh mục --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cat_id == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <select name="sort" class="form-control">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                </select>
                <button type="submit" class="btn btn-primary" style="padding:8px 18px;"><i class="fa-solid fa-filter"></i></button>
            </div>
        </div>
    </form>

    <!-- DANH SÁCH SẢN PHẨM KHÔNG BỊ CẮT CHỮ -->
    <div class="shop-product-grid">
        <?php if (count($products) > 0): ?>
            <?php foreach ($products as $p): ?>
                <div class="shop-prod-card">
                    <div>
                        <div class="shop-prod-thumb">
                            <a href="<?= BASE_URL ?>shop/product-detail.php?id=<?= $p['id'] ?>">
                                <img src="<?= htmlspecialchars($p['image']) ?>" 
                                     alt="<?= htmlspecialchars($p['name']) ?>"
                                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1510312305653-8ed496efae75?w=500';">
                            </a>
                        </div>
                        <span class="shop-prod-cat"><?= htmlspecialchars($p['brand']) ?> • <?= htmlspecialchars($p['cat_name']) ?></span>
                        <h3 class="shop-prod-name">
                            <a href="<?= BASE_URL ?>shop/product-detail.php?id=<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['name']) ?>
                            </a>
                        </h3>
                    </div>

                    <div class="shop-prod-bottom">
                        <div class="shop-price-value"><?= format_price($p['price']) ?></div>
                        <a href="<?= BASE_URL ?>shop/product-detail.php?id=<?= $p['id'] ?>" class="shop-btn-cart" title="Xem chi tiết & Đặt mua">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: span 4; text-align:center; padding:60px 0; color:#64748b;">
                <i class="fa-solid fa-box-open" style="font-size:2.5rem; color:#cbd5e1; margin-bottom:12px; display:block;"></i>
                Chưa có sản phẩm nào phù hợp với bộ lọc tìm kiếm.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>