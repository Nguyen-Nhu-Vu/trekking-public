<?php
// trekking/shop/product-detail.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.status = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: " . BASE_URL . "shop/shop.php");
    exit;
}

$page_title = $product['name'] . " - VNN Travel";
$active_menu = 'shop';

// PHÂN LOẠI SẢN PHẨM THÔNG MINH ĐỂ HIỂN THỊ ĐÚNG BẢNG SIZE
$cat_lower = mb_strtolower($product['cat_name'], 'UTF-8');
$name_lower = mb_strtolower($product['name'], 'UTF-8');

$is_footwear = (
    strpos($cat_lower, 'giày') !== false || strpos($cat_lower, 'dép') !== false ||
    strpos($name_lower, 'giày') !== false || strpos($name_lower, 'dép') !== false
);

$is_clothing = !$is_footwear && (
    strpos($cat_lower, 'trang phục') !== false || strpos($cat_lower, 'quần') !== false || strpos($cat_lower, 'áo') !== false ||
    strpos($name_lower, 'quần') !== false || strpos($name_lower, 'áo') !== false
);

$is_gloves = (
    strpos($cat_lower, 'găng tay') !== false || strpos($name_lower, 'găng') !== false
);

$has_size = $is_clothing || $is_footwear || $is_gloves;

// Xử lý thêm vào giỏ hàng (kèm Size theo đúng chủng loại)
$added = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $qty = max(1, intval($_POST['quantity'] ?? 1));
    $size = sanitize($_POST['size'] ?? '');

    if ($qty > 0 && $qty <= $product['stock']) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $cart_key = $has_size && !empty($size) ? $id . '_' . $size : $id;
        $item_name = $has_size && !empty($size) ? $product['name'] . " (Size: " . $size . ")" : $product['name'];

        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['quantity'] += $qty;
        } else {
            $_SESSION['cart'][$cart_key] = [
                'id' => $product['id'],
                'name' => $item_name,
                'price' => $product['price'],
                'image' => $product['image'],
                'size' => $size,
                'quantity' => $qty
            ];
        }
        $added = true;
    }
}

// Xử lý gửi đánh giá sản phẩm
$rev_msg = '';
$rev_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        $rev_error = "Vui lòng đăng nhập để gửi đánh giá sản phẩm!";
    } else {
        $rating = intval($_POST['rating'] ?? 5);
        $comment = sanitize($_POST['comment'] ?? '');

        if (empty($comment)) {
            $rev_error = "Vui lòng nhập lời nhận xét chi tiết về sản phẩm!";
        } else {
            $ins_rev = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, comment, status) VALUES (?, ?, ?, ?, 1)");
            $ins_rev->execute([$_SESSION['user_id'], $id, $rating, $comment]);
            $rev_msg = "Cảm ơn bạn đã gửi đánh giá! Nhận xét đã được xuất bản.";
        }
    }
}

// Lấy danh sách đánh giá
$rev_stmt = $pdo->prepare("SELECT r.*, u.fullname FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.status = 1 ORDER BY r.id DESC");
$rev_stmt->execute([$id]);
$reviews = $rev_stmt->fetchAll();

$avg_rating = 5.0;
if (count($reviews) > 0) {
    $sum_rating = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($sum_rating / count($reviews), 1);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* GIAO DIỆN CHI TIẾT SẢN PHẨM NÂNG CAO */
.prod-detail-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 35px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    margin-bottom: 35px;
}
.prod-image-box {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    height: 440px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
}
.prod-image-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.prod-tag-cat {
    font-size: 0.85rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #ea580c;
    letter-spacing: 1px;
    margin-bottom: 8px;
    display: inline-block;
}
.prod-main-title {
    font-size: 2rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.35;
    margin-bottom: 12px;
}
.prod-rating-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    color: #f59e0b;
    margin-bottom: 18px;
}
.prod-main-price {
    font-size: 2.3rem;
    font-weight: 900;
    color: #ea580c;
    margin-bottom: 20px;
}

/* NÚT CHỌN SIZE */
.size-selector-group {
    margin-bottom: 25px;
    background: #f8fafc;
    padding: 18px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
.size-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}
.size-option-label {
    cursor: pointer;
    user-select: none;
}
.size-option-label input[type="radio"] {
    display: none;
}
.size-btn-pill {
    display: inline-block;
    min-width: 44px;
    height: 40px;
    line-height: 40px;
    text-align: center;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.92rem;
    color: #334155;
    background: white;
    transition: all 0.2s ease;
    padding: 0 12px;
}
.size-option-label input[type="radio"]:checked + .size-btn-pill {
    background: #ea580c;
    color: white;
    border-color: #ea580c;
    box-shadow: 0 4px 10px rgba(234, 88, 12, 0.35);
}

/* BẢNG SIZE CHUẨN FORM */
.size-chart-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 0.92rem;
}
.size-chart-table th {
    background: #1e5631;
    color: white;
    padding: 12px 14px;
    text-align: center;
    font-weight: 700;
}
.size-chart-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
    text-align: center;
    color: #334155;
}
.size-chart-table tr:nth-child(even) {
    background: #f8fafc;
}

/* KHỐI NỘI DUNG */
.tab-section-block {
    background: white;
    padding: 35px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    margin-bottom: 30px;
}
.tab-section-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 18px;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}
</style>

<div class="container section">

    <?php if ($added): ?>
        <div class="alert alert-success" style="background:#dcfce7; border-color:#86efac; color:#166534; padding:15px 20px; border-radius:10px; margin-bottom:25px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <i class="fa-solid fa-circle-check" style="color:#16a34a; font-size:1.2rem; margin-right:8px;"></i>
                Đã thêm thành công <strong><?= htmlspecialchars($product['name']) ?></strong> vào giỏ hàng!
            </div>
            <a href="<?= BASE_URL ?>shop/cart.php" class="btn btn-primary btn-sm" style="border-radius:20px;">
                Xem giỏ hàng &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- KHU VỰC THÔNG TIN SẢN PHẨM CHÍNH -->
    <div class="prod-detail-card">
        <div class="grid grid-2" style="gap:40px; align-items:start;">
            <!-- CỘT TRÁI: ẢNH SẢN PHẨM -->
            <div class="prod-image-box">
                <img src="<?= htmlspecialchars($product['image']) ?>" 
                     alt="<?= htmlspecialchars($product['name']) ?>"
                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1510312305653-8ed496efae75?w=600';">
            </div>

            <!-- CỘT PHẢI: CHI TIẾT & CHỌN SIZE -->
            <div style="display:flex; flex-direction:column;">
                <span class="prod-tag-cat"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($product['brand']) ?> • <?= htmlspecialchars($product['cat_name']) ?></span>
                <h1 class="prod-main-title"><?= htmlspecialchars($product['name']) ?></h1>
                
                <div class="prod-rating-badge">
                    <span><?= str_repeat('★', (int)round($avg_rating)) ?></span>
                    <strong style="color:#334155; font-size:1.05rem;"><?= $avg_rating ?> / 5.0</strong>
                    <span style="color:#64748b; font-size:0.85rem;">(<?= count($reviews) ?> đánh giá)</span>
                </div>

                <div class="prod-main-price"><?= format_price($product['price']) ?></div>

                <div style="background:#f8fafc; padding:14px 18px; border-radius:8px; margin-bottom:20px; font-size:0.92rem; border:1px solid #e2e8f0;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <span>Tình trạng kho:</span>
                        <?php if ($product['stock'] > 0): ?>
                            <strong style="color:#16a34a;"><i class="fa-solid fa-check"></i> Còn hàng (<?= $product['stock'] ?> chiếc có sẵn)</strong>
                        <?php else: ?>
                            <strong style="color:#dc2626;"><i class="fa-solid fa-xmark"></i> Tạm hết hàng</strong>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Chính sách:</span>
                        <strong>1 đổi 1 trong 7 ngày nếu lỗi từ nhà sản xuất</strong>
                    </div>
                </div>

                <!-- FORM ĐẶT MUA -->
                <form action="" method="POST">
                    <input type="hidden" name="add_to_cart" value="1">

                    <!-- 1. CHỌN SIZE CHO QUẦN ÁO -->
                    <?php if ($is_clothing): ?>
                        <div class="size-selector-group">
                            <label style="font-weight:700; color:#0f172a; display:flex; justify-content:space-between; align-items:center;">
                                <span><i class="fa-solid fa-shirt" style="color:#ea580c;"></i> Chọn Kích Cỡ Áo/Quần:</span>
                                <a href="#size-chart" style="color:#ea580c; font-size:0.85rem; text-decoration:underline;">Bảng size chi tiết &darr;</a>
                            </label>
                            <div class="size-options">
                                <label class="size-option-label"><input type="radio" name="size" value="S"><span class="size-btn-pill">S</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="M"><span class="size-btn-pill">M</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="L" checked><span class="size-btn-pill">L</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="XL"><span class="size-btn-pill">XL</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="XXL"><span class="size-btn-pill">XXL</span></label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 2. CHỌN SIZE CHO GIÀY DÉP -->
                    <?php if ($is_footwear): ?>
                        <div class="size-selector-group">
                            <label style="font-weight:700; color:#0f172a; display:flex; justify-content:space-between; align-items:center;">
                                <span><i class="fa-solid fa-shoe-prints" style="color:#ea580c;"></i> Chọn Size Giày/Dép:</span>
                                <a href="#size-chart" style="color:#ea580c; font-size:0.85rem; text-decoration:underline;">Xem cách đo chân &darr;</a>
                            </label>
                            <div class="size-options">
                                <label class="size-option-label"><input type="radio" name="size" value="38"><span class="size-btn-pill">38</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="39"><span class="size-btn-pill">39</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="40" checked><span class="size-btn-pill">40</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="41"><span class="size-btn-pill">41</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="42"><span class="size-btn-pill">42</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="43"><span class="size-btn-pill">43</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="44"><span class="size-btn-pill">44</span></label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 3. CHỌN SIZE CHO GĂNG TAY -->
                    <?php if ($is_gloves): ?>
                        <div class="size-selector-group">
                            <label style="font-weight:700; color:#0f172a; display:flex; justify-content:space-between; align-items:center;">
                                <span><i class="fa-solid fa-mitten" style="color:#ea580c;"></i> Chọn Size Găng Tay:</span>
                                <a href="#size-chart" style="color:#ea580c; font-size:0.85rem; text-decoration:underline;">Bảng đo lòng bàn tay &darr;</a>
                            </label>
                            <div class="size-options">
                                <label class="size-option-label"><input type="radio" name="size" value="M"><span class="size-btn-pill">M (Nhỏ/Vừa)</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="L" checked><span class="size-btn-pill">L (Tiêu chuẩn)</span></label>
                                <label class="size-option-label"><input type="radio" name="size" value="XL"><span class="size-btn-pill">XL (Tay lớn)</span></label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($product['stock'] > 0): ?>
                        <div style="display:flex; gap:15px; align-items:center; margin-bottom:20px;">
                            <label style="font-weight:700; color:#334155;">Số lượng:</label>
                            <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>" class="form-control" style="width:90px; text-align:center; font-weight:bold; font-size:1.05rem;">
                            <button type="submit" class="btn btn-accent" style="padding:13px 30px; font-size:1.05rem; border-radius:30px; flex:1;">
                                <i class="fa-solid fa-cart-shopping"></i> THÊM VÀO GIỎ HÀNG
                            </button>
                        </div>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline" disabled style="width:100%; padding:14px; border-radius:30px;">TẠM THỜI HẾT HÀNG</button>
                    <?php endif; ?>
                </form>

                <div style="margin-top:15px; font-size:0.85rem; color:#64748b; line-height:1.6;">
                    <i class="fa-solid fa-truck-fast" style="color:#16a34a;"></i> Giao hàng tận nơi toàn quốc • Đồng kiểm hàng trước khi thanh toán COD.
                </div>
            </div>
        </div>
    </div>

    <!-- KHỐI GIỚI THIỆU SẢN PHẨM & TÍNH NĂNG CHUYÊN SÂU -->
    <div class="tab-section-block">
        <h3 class="tab-section-title"><i class="fa-solid fa-circle-info" style="color:#1e5631;"></i> Giới Thiệu & Tính Năng Chuyên Sâu</h3>
        <div style="line-height:1.85; color:#334155; font-size:1.02rem; margin-bottom:25px;">
            <?= nl2br(htmlspecialchars($product['description'] ?? 'Đang cập nhật mô tả chi tiết sản phẩm.')) ?>
        </div>

        <?php if (!empty($product['specs'])): ?>
            <h4 style="font-size:1.1rem; color:#0f172a; margin-bottom:10px;"><i class="fa-solid fa-microchip" style="color:#ea580c;"></i> Thông số kỹ thuật & Chất liệu:</h4>
            <div style="background:#f8fafc; padding:20px 24px; border-radius:10px; border:1px solid #e2e8f0; line-height:1.85; color:#334155; font-size:0.95rem;">
                <?= nl2br(htmlspecialchars($product['specs'])) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================= BẢNG SIZE THAM KHẢO CHUYÊN DỤNG TỪNG LOẠI ================= -->
    
    <!-- 1. BẢNG SIZE DÀNH CHO QUẦN ÁO -->
    <?php if ($is_clothing): ?>
        <div id="size-chart" class="tab-section-block">
            <h3 class="tab-section-title"><i class="fa-solid fa-ruler-combined" style="color:#ea580c;"></i> Bảng Size Tham Khảo Chuẩn Trang Phục Dã Ngoại</h3>
            <p style="color:#64748b; font-size:0.92rem; margin-bottom:15px;">
                Quần áo leo núi VNN Travel được may theo form Regular Fit thể thao, co giãn thoải mái khi vận động vượt dốc. Nếu số đo của bạn nằm giữa 2 size, khuyến nghị chọn size lớn hơn để mặc lót áo giữ nhiệt bên trong.
            </p>
            <div class="table-wrap">
                <table class="size-chart-table">
                    <thead>
                        <tr>
                            <th>Kích Thước (Size)</th>
                            <th>Chiều Cao Chuẩn</th>
                            <th>Cân Nặng Chuẩn</th>
                            <th>Vòng Ngực (cm)</th>
                            <th>Vòng Bụng / Eo (cm)</th>
                            <th>Chiều Dài (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Size S</strong></td><td>1m55 – 1m64</td><td>48 – 56 kg</td><td>86 – 90</td><td>72 – 76</td><td>66 – 98</td></tr>
                        <tr><td><strong>Size M</strong></td><td>1m65 – 1m70</td><td>57 – 65 kg</td><td>91 – 95</td><td>77 – 81</td><td>68 – 100</td></tr>
                        <tr><td><strong>Size L</strong></td><td>1m71 – 1m76</td><td>66 – 74 kg</td><td>96 – 101</td><td>82 – 86</td><td>70 – 102</td></tr>
                        <tr><td><strong>Size XL</strong></td><td>1m77 – 1m82</td><td>75 – 83 kg</td><td>102 – 107</td><td>87 – 92</td><td>72 – 104</td></tr>
                        <tr><td><strong>Size XXL</strong></td><td>1m83 – 1m90</td><td>84 – 95 kg</td><td>108 – 114</td><td>93 – 98</td><td>74 – 106</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. BẢNG SIZE DÀNH CHO GIÀY DÉP LEO NÚI -->
    <?php if ($is_footwear): ?>
        <div id="size-chart" class="tab-section-block">
            <h3 class="tab-section-title"><i class="fa-solid fa-shoe-prints" style="color:#ea580c;"></i> Bảng Quy Đổi Size Giày Trekking & Đo Chiều Dài Bàn Chân</h3>
            <p style="color:#64748b; font-size:0.92rem; margin-bottom:15px;">
                <strong>Mẹo chọn giày leo núi:</strong> Khi trekking xuống dốc đứng, bàn chân có xu hướng bị trượt dồn về phía mũi giày. Do đó, bạn nên chọn size giày <strong>lớn hơn từ 0.5 đến 1 size</strong> so với giày đi làm hằng ngày để đi tất dày và bảo vệ ngón chân không bị thâm tím.
            </p>
            <div class="table-wrap">
                <table class="size-chart-table">
                    <thead>
                        <tr>
                            <th>Size VN / EU</th>
                            <th>Chiều Dài Bàn Chân (cm)</th>
                            <th>Size US</th>
                            <th>Size UK</th>
                            <th>Độ Rộng Lòng Bàn Chân</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Size 38</strong></td><td>23.5 – 24.0 cm</td><td>6.0</td><td>5.5</td><td>Tiêu chuẩn</td></tr>
                        <tr><td><strong>Size 39</strong></td><td>24.1 – 24.5 cm</td><td>6.5</td><td>6.0</td><td>Tiêu chuẩn</td></tr>
                        <tr><td><strong>Size 40</strong></td><td>24.6 – 25.0 cm</td><td>7.5</td><td>7.0</td><td>Thoải mái</td></tr>
                        <tr><td><strong>Size 41</strong></td><td>25.1 – 25.5 cm</td><td>8.0</td><td>7.5</td><td>Thoải mái</td></tr>
                        <tr><td><strong>Size 42</strong></td><td>25.6 – 26.0 cm</td><td>8.5</td><td>8.0</td><td>Thoải mái</td></tr>
                        <tr><td><strong>Size 43</strong></td><td>26.1 – 26.5 cm</td><td>9.5</td><td>9.0</td><td>Form rộng</td></tr>
                        <tr><td><strong>Size 44</strong></td><td>26.6 – 27.2 cm</td><td>10.0</td><td>9.5</td><td>Form rộng</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. BẢNG SIZE CHO GĂNG TAY -->
    <?php if ($is_gloves): ?>
        <div id="size-chart" class="tab-section-block">
            <h3 class="tab-section-title"><i class="fa-solid fa-mitten" style="color:#ea580c;"></i> Bảng Hướng Dẫn Đo Size Găng Tay Dã Ngoại</h3>
            <p style="color:#64748b; font-size:0.92rem; margin-bottom:15px;">
                Dùng thước dây quấn quanh lòng bàn tay (không tính ngón tay cái) để xác định chu vi và chọn size ôm sát giúp cầm gậy leo núi chắc chắn:
            </p>
            <div class="table-wrap">
                <table class="size-chart-table">
                    <thead>
                        <tr>
                            <th>Kích Thước</th>
                            <th>Chu Vi Lòng Bàn Tay (cm)</th>
                            <th>Chiều Dài Bàn Tay (cm)</th>
                            <th>Khuyến Nghị Sử Dụng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Size M</strong></td><td>18 – 20 cm</td><td>17 – 18 cm</td><td>Nữ hoặc Nam có bàn tay nhỏ</td></tr>
                        <tr><td><strong>Size L</strong></td><td>20 – 22 cm</td><td>19 – 20 cm</td><td>Nam tiêu chuẩn phổ biến</td></tr>
                        <tr><td><strong>Size XL</strong></td><td>22 – 24 cm</td><td>21 – 22 cm</td><td>Bàn tay to dày</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- KHỐI ĐÁNH GIÁ SẢN PHẨM -->
    <div class="tab-section-block">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:2px solid #f1f5f9; padding-bottom:12px;">
            <h3 style="margin:0; font-size:1.35rem; font-weight:800; color:#0f172a;">
                <i class="fa-solid fa-star" style="color:#f59e0b;"></i> Đánh Giá Từ Khách Hàng (<?= count($reviews) ?>)
            </h3>
            <div style="font-size:1.15rem; font-weight:800; color:#ea580c;">
                ⭐ <?= $avg_rating ?> / 5.0
            </div>
        </div>

        <?php if ($rev_msg): ?><div class="alert alert-success" style="margin-bottom:20px;"><?= $rev_msg ?></div><?php endif; ?>
        <?php if ($rev_error): ?><div class="alert alert-error" style="margin-bottom:20px;"><?= $rev_error ?></div><?php endif; ?>

        <!-- FORM GỬI ĐÁNH GIÁ MỚI -->
        <?php if (isLoggedIn()): ?>
            <form action="" method="POST" style="background:#f8fafc; border:1px solid #e2e8f0; padding:22px; border-radius:12px; margin-bottom:30px;">
                <input type="hidden" name="submit_review" value="1">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-weight:700; color:#0f172a;">Đánh giá mức độ hài lòng:</label>
                    <select name="rating" class="form-control" style="width:180px; font-weight:600;">
                        <option value="5">⭐⭐⭐⭐⭐ 5 Sao (Rất tốt)</option>
                        <option value="4">⭐⭐⭐⭐ 4 Sao (Tốt)</option>
                        <option value="3">⭐⭐⭐ 3 Sao (Tạm ổn)</option>
                        <option value="2">⭐⭐ 2 Sao (Không hài lòng)</option>
                        <option value="1">⭐ 1 Sao (Tệ)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-weight:700; color:#0f172a;">Nhận xét chất lượng sản phẩm:</label>
                    <textarea name="comment" class="form-control" rows="3" required placeholder="Sản phẩm dùng bền không? Khả năng chống nước, chất liệu và kích thước có vừa vặn không?..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Gửi Đánh Giá Của Bạn</button>
            </form>
        <?php else: ?>
            <div style="background:#fff7ed; border-left:4px solid #ea580c; padding:15px; border-radius:6px; margin-bottom:25px; font-size:0.95rem; color:#7c2d12;">
                Vui lòng <a href="<?= BASE_URL ?>auth/login.php" style="color:#ea580c; font-weight:bold; text-decoration:underline;">đăng nhập tài khoản</a> để viết nhận xét và đánh giá sao cho sản phẩm này.
            </div>
        <?php endif; ?>

        <!-- DANH SÁCH BÌNH LUẬN ĐÁNH GIÁ -->
        <?php if (count($reviews) > 0): ?>
            <div>
                <?php foreach ($reviews as $rev): ?>
                    <div style="padding:16px 0; border-bottom:1px solid #f1f5f9;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <strong style="color:#0f172a; font-size:0.98rem;"><?= htmlspecialchars($rev['fullname']) ?></strong>
                            <span style="color:#f59e0b; font-size:0.95rem;"><?= str_repeat('★', (int)$rev['rating']) ?></span>
                        </div>
                        <p style="color:#475569; font-size:0.94rem; line-height:1.6; margin:0 0 6px 0;"><?= htmlspecialchars($rev['comment']) ?></p>
                        <small style="color:#94a3b8; font-size:0.8rem;"><i class="fa-regular fa-clock"></i> <?= date('d/m/Y H:i', strtotime($rev['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:35px 0; color:#94a3b8;">
                <i class="fa-regular fa-comments" style="font-size:2.5rem; color:#cbd5e1; margin-bottom:10px; display:block;"></i>
                Chưa có đánh giá nào cho sản phẩm này. Hãy là người đầu tiên trải nghiệm và chia sẻ!
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>