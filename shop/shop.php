<?php
// trekking/shop/shop.php
$page_title = "Cửa Hàng Dụng Cụ Dã Ngoại - VNN Travel";
$active_menu = 'shop';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Các điều kiện được lọc kết hợp (AND) trực tiếp trong MySQL.
$search = trim((string)($_GET['q'] ?? ''));
$cat_id = max(0, (int)($_GET['cat'] ?? 0));
$brand = trim((string)($_GET['brand'] ?? ''));
$price_range = (string)($_GET['price'] ?? 'all');
$stock_status = (string)($_GET['stock'] ?? 'all');
$min_rating = (int)($_GET['rating'] ?? 0);
$sort = (string)($_GET['sort'] ?? 'newest');

if (!in_array($price_range, ['all', 'under500', '500to1000', '1000to2000', '2000to5000', 'over5000'], true)) $price_range = 'all';
if (!in_array($stock_status, ['all', 'available', 'soldout'], true)) $stock_status = 'all';
if ($min_rating < 1 || $min_rating > 4) $min_rating = 0;
if (!in_array($sort, ['newest', 'price_asc', 'price_desc', 'name_asc', 'rating_desc'], true)) $sort = 'newest';

// Dùng LEFT JOIN để sản phẩm chưa có đánh giá vẫn hiển thị khi không lọc theo sao.
// Chỉ tính các đánh giá đã được duyệt/đang hiển thị.
$sql = "SELECT p.*, c.name AS cat_name, rv.avg_rating, COALESCE(rv.review_count, 0) AS review_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN (
            SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
            FROM reviews
            WHERE status = 1 AND product_id IS NOT NULL
            GROUP BY product_id
        ) rv ON rv.product_id = p.id
        WHERE p.status = 1";
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.name LIKE :search_name OR p.brand LIKE :search_brand)';
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_brand'] = '%' . $search . '%';
}
if ($cat_id > 0) {
    $sql .= ' AND p.category_id = :cat';
    $params[':cat'] = $cat_id;
}
if ($brand !== '') {
    $sql .= ' AND p.brand = :brand';
    $params[':brand'] = $brand;
}

switch ($price_range) {
    case 'under500':
        $sql .= ' AND p.price < 500000'; break;
    case '500to1000':
        $sql .= ' AND p.price >= 500000 AND p.price < 1000000'; break;
    case '1000to2000':
        $sql .= ' AND p.price >= 1000000 AND p.price < 2000000'; break;
    case '2000to5000':
        $sql .= ' AND p.price >= 2000000 AND p.price < 5000000'; break;
    case 'over5000':
        $sql .= ' AND p.price >= 5000000'; break;
}

if ($stock_status === 'available') {
    $sql .= ' AND p.stock > 0';
} elseif ($stock_status === 'soldout') {
    $sql .= ' AND p.stock <= 0';
}

if ($min_rating > 0) {
    $sql .= ' AND rv.avg_rating >= :min_rating';
    $params[':min_rating'] = $min_rating;
}

switch ($sort) {
    case 'price_asc': $sql .= ' ORDER BY p.price ASC, p.id DESC'; break;
    case 'price_desc': $sql .= ' ORDER BY p.price DESC, p.id DESC'; break;
    case 'name_asc': $sql .= ' ORDER BY p.name ASC, p.id DESC'; break;
    case 'rating_desc': $sql .= ' ORDER BY (rv.avg_rating IS NULL) ASC, rv.avg_rating DESC, rv.review_count DESC, p.id DESC'; break;
    default: $sql .= ' ORDER BY p.id DESC';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE status = 1 AND brand IS NOT NULL AND TRIM(brand) <> '' ORDER BY brand ASC")->fetchAll(PDO::FETCH_COLUMN);

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

/* Bộ lọc nhỏ gọn, đồng bộ với trang quản lý tour */
.shop-filter-panel {
    background: #fff;
    padding: 20px 22px;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .06);
    margin-bottom: 30px;
}
.shop-filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    column-gap: 16px;
    row-gap: 14px;
}
.shop-filter-field { min-width: 0; }
.shop-filter-field label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #142944;
    margin: 0 0 6px;
}
.shop-filter-field .form-control {
    display: block;
    width: 100%;
    height: 43px;
    min-height: 43px;
    padding: 8px 12px;
    border-radius: 9px;
    font-size: 14px;
    border: 1px solid #d3ddea;
    background-color: #fff;
    box-sizing: border-box;
}
.shop-filter-field .form-control:focus {
    border-color: #268452;
    outline: 2px solid rgba(34, 139, 86, .12);
}
.shop-filter-actions {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 14px;
    margin-top: 15px;
}
.shop-filter-sort { width: min(100%, calc((100% - 32px) / 3)); }
.shop-filter-buttons { display: flex; align-items: center; gap: 9px; }
.shop-filter-buttons .shop-filter-button {
    height: 43px;
    border-radius: 9px;
    border: none;
    font-size: 14px;
    font-weight: 700;
    padding: 0 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
}
.shop-filter-apply { background: #188d4b; color: #fff; }
.shop-filter-apply:hover { background: #116a37; color: #fff; }
.shop-filter-reset { background: #e2e8f0; color: #142944; }
.shop-filter-reset:hover { background: #cbd5e1; color: #142944; }
@media (max-width: 900px) {
    .shop-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .shop-filter-sort { width: calc((100% - 16px) / 2); }
}
@media (max-width: 600px) {
    .shop-filter-panel { padding: 15px; }
    .shop-filter-grid { grid-template-columns: 1fr; row-gap: 11px; }
    .shop-filter-actions { flex-direction: column; align-items: stretch; }
    .shop-filter-sort { width: 100%; }
    .shop-filter-buttons { justify-content: flex-end; flex-wrap: wrap; }
}
</style>

<div class="container section">
    <div class="section-header" style="text-align:left; margin-bottom:25px;">
        <span class="section-subtitle">VNN TRAVEL GEAR</span>
        <h2 class="section-title">Trang Bị Trekking & Dã Ngoại Chuyên Nghiệp</h2>
    </div>

    <!-- BỘ LỌC SẢN PHẨM: KẾT HỢP NHIỀU ĐIỀU KIỆN -->
    <form action="" method="GET" class="shop-filter-panel">
        <div class="shop-filter-grid">
            <div class="shop-filter-field">
                <label for="shop-search">Tìm kiếm</label>
                <input id="shop-search" type="text" name="q" class="form-control" placeholder="Tên sản phẩm..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="shop-filter-field">
                <label for="shop-category">Danh mục</label>
                <select id="shop-category" name="cat" class="form-control">
                    <option value="0">Tất cả danh mục</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $cat_id === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="shop-filter-field">
                <label for="shop-brand">Thương hiệu</label>
                <select id="shop-brand" name="brand" class="form-control">
                    <option value="">Tất cả thương hiệu</option>
                    <?php foreach ($brands as $brand_option): ?>
                        <option value="<?= htmlspecialchars($brand_option, ENT_QUOTES, 'UTF-8') ?>" <?= $brand === $brand_option ? 'selected' : '' ?>><?= htmlspecialchars($brand_option, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="shop-filter-field">
                <label for="shop-price">Khoảng giá</label>
                <select id="shop-price" name="price" class="form-control">
                    <option value="all" <?= $price_range === 'all' ? 'selected' : '' ?>>Tất cả mức giá</option>
                    <option value="under500" <?= $price_range === 'under500' ? 'selected' : '' ?>>Dưới 500.000đ</option>
                    <option value="500to1000" <?= $price_range === '500to1000' ? 'selected' : '' ?>>500.000đ – dưới 1 triệu</option>
                    <option value="1000to2000" <?= $price_range === '1000to2000' ? 'selected' : '' ?>>1 – dưới 2 triệu</option>
                    <option value="2000to5000" <?= $price_range === '2000to5000' ? 'selected' : '' ?>>2 – dưới 5 triệu</option>
                    <option value="over5000" <?= $price_range === 'over5000' ? 'selected' : '' ?>>Từ 5 triệu trở lên</option>
                </select>
            </div>
            <div class="shop-filter-field">
                <label for="shop-stock">Tình trạng hàng</label>
                <select id="shop-stock" name="stock" class="form-control">
                    <option value="all" <?= $stock_status === 'all' ? 'selected' : '' ?>>Tất cả tình trạng</option>
                    <option value="available" <?= $stock_status === 'available' ? 'selected' : '' ?>>Còn hàng</option>
                    <option value="soldout" <?= $stock_status === 'soldout' ? 'selected' : '' ?>>Hết hàng</option>
                </select>
            </div>
            <div class="shop-filter-field">
                <label for="shop-rating">Đánh giá sản phẩm</label>
                <select id="shop-rating" name="rating" class="form-control">
                    <option value="0" <?= $min_rating === 0 ? 'selected' : '' ?>>Tất cả đánh giá</option>
                    <option value="4" <?= $min_rating === 4 ? 'selected' : '' ?>>★★★★☆ 4 sao trở lên</option>
                    <option value="3" <?= $min_rating === 3 ? 'selected' : '' ?>>★★★☆☆ 3 sao trở lên</option>
                    <option value="2" <?= $min_rating === 2 ? 'selected' : '' ?>>★★☆☆☆ 2 sao trở lên</option>
                    <option value="1" <?= $min_rating === 1 ? 'selected' : '' ?>>★☆☆☆☆ 1 sao trở lên</option>
                </select>
            </div>
        </div>
        <div class="shop-filter-actions">
            <div class="shop-filter-field shop-filter-sort">
                <label for="shop-sort">Sắp xếp theo</label>
                <select id="shop-sort" name="sort" class="form-control">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá thấp đến cao</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá cao đến thấp</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Tên A–Z</option>
                    <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Đánh giá cao nhất</option>
                </select>
            </div>
            <div class="shop-filter-buttons">
                <button type="submit" class="shop-filter-button shop-filter-apply"><i class="fa-solid fa-filter"></i> Lọc</button>
                <a href="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/shop/shop.php', ENT_QUOTES, 'UTF-8') ?>" class="shop-filter-button shop-filter-reset">Xóa lọc</a>
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
