<?php
// VNN Travel - Chi tiet san pham
// shop/product-detail.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS cat_name
    FROM products p
    INNER JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.status = 1
    LIMIT 1
");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/shop/shop.php');
    exit;
}

function pdh($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$base = rtrim(BASE_URL, '/') . '/';
$page_title = $product['name'] . ' - VNN Travel';
$active_menu = 'shop';

$cat = mb_strtolower((string) $product['cat_name'], 'UTF-8');
$name = mb_strtolower((string) $product['name'], 'UTF-8');

$is_footwear = (
    str_contains($cat, 'giày') ||
    str_contains($cat, 'dép') ||
    str_contains($name, 'giày') ||
    str_contains($name, 'dép')
);

$is_clothing = !$is_footwear && (
    str_contains($cat, 'trang phục') ||
    str_contains($cat, 'quần') ||
    str_contains($cat, 'áo') ||
    str_contains($name, 'quần') ||
    str_contains($name, 'áo')
);

$is_gloves = (
    str_contains($cat, 'găng tay') ||
    str_contains($name, 'găng')
);

$has_size = $is_footwear || $is_clothing || $is_gloves;

$sizes = [];

if ($is_footwear) {
    $sizes = ['38', '39', '40', '41', '42', '43', '44'];
} elseif ($is_gloves) {
    $sizes = ['M', 'L', 'XL'];
} elseif ($is_clothing) {
    $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
}

// Bang size rieng cua san pham: neu rong thi khong hien bang size.
$sizeChartText = trim((string)($product['size_chart'] ?? ''));
$sizeChartRows = [];
$sizeChartIsTable = false;
if ($sizeChartText !== '') {
    $lines = preg_split('/\R/u', $sizeChartText) ?: [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $sizeChartRows[] = array_map('trim', explode('|', $line));
    }
    // Chi coi la bang khi co dong tieu de va it nhat mot dong du lieu.
    $sizeChartIsTable = count($sizeChartRows) >= 2 && count($sizeChartRows[0]) >= 2;
}

$defaultSize = $is_footwear ? '40' : 'L';
$added = false;
$cartError = '';
require_once __DIR__ . '/../includes/review-eligibility.php';
$canReviewProduct = isLoggedIn() && vnnCanReviewProduct($pdo, (int)$_SESSION['user_id'], $id);
$reviewMessage = '';
$reviewError = '';

// Them vao gio hang
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_to_cart'])
) {
    $qty = filter_var(
        $_POST['quantity'] ?? null,
        FILTER_VALIDATE_INT
    );

    $size = trim((string) ($_POST['size'] ?? ''));

    if ($qty === false || $qty < 1) {
        $cartError = 'Số lượng không hợp lệ.';
    } elseif ($qty > (int) $product['stock']) {
        $cartError = 'Sản phẩm không đủ số lượng trong kho.';
    } elseif ($has_size && !in_array($size, $sizes, true)) {
        $cartError = 'Vui lòng chọn kích cỡ hợp lệ.';
    } else {
        if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $key = $has_size ? $id . '_' . $size : (string) $id;

        $currentQty = (int) (
            $_SESSION['cart'][$key]['quantity'] ?? 0
        );

        if ($currentQty + $qty > (int) $product['stock']) {
            $cartError = 'Tổng số lượng trong giỏ vượt quá tồn kho.';
        } else {
            $itemName = $product['name'];

            if ($has_size) {
                $itemName .= ' (Size: ' . $size . ')';
            }

            $_SESSION['cart'][$key] = [
                'id' => $id,
                'name' => $itemName,
                'price' => $product['price'],
                'image' => $product['image'],
                'size' => $has_size ? $size : '',
                'quantity' => $currentQty + $qty
            ];

            $added = true;
        }
    }
}

// Gui danh gia
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['submit_review'])
) {
    if (!isLoggedIn()) {
        $reviewError = 'Vui lòng đăng nhập để gửi đánh giá.';
    } elseif (!$canReviewProduct) {
        $reviewError = 'Chỉ khách hàng đã mua sản phẩm này (đơn đã thanh toán hoặc giao thành công) mới được đánh giá.';
    } else {
        $rating = (int) ($_POST['rating'] ?? 5);
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($rating < 1 || $rating > 5 || $comment === '') {
            $reviewError = 'Vui lòng chọn số sao và nhập nhận xét.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO reviews
                        (user_id, product_id, rating, comment, status)
                    VALUES (?, ?, ?, ?, 1)
                ");

                $stmt->execute([
                    (int) $_SESSION['user_id'],
                    $id,
                    $rating,
                    $comment
                ]);

                $reviewMessage = 'Đánh giá của bạn đã được ghi nhận.';
            } catch (Throwable $e) {
                error_log('Review: ' . $e->getMessage());
                $reviewError = 'Không thể gửi đánh giá. Vui lòng thử lại.';
            }
        }
    }
}

// Danh gia hien co
$reviewStmt = $pdo->prepare("
    SELECT r.*, u.fullname
    FROM reviews r
    INNER JOIN users u ON u.id = r.user_id
    WHERE r.product_id = ? AND r.status = 1
    ORDER BY r.id DESC
");
$reviewStmt->execute([$id]);
$reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

$avgRating = 5.0;

if ($reviews) {
    $avgRating = round(
        array_sum(array_column($reviews, 'rating')) / count($reviews),
        1
    );
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.pd-page {
    padding-top: 26px;
    padding-bottom: 50px;
}
.pd-back {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: #166534;
    color: white !important;
    padding: 12px 19px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 800;
    margin-bottom: 23px;
    transition: background .2s;
}
.pd-back:hover {
    background: #15803d;
}
.pd-card,
.pd-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 28px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, .05);
}
.pd-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 35px;
    align-items: start;
}
.pd-image {
    width: 100%;
    height: 430px;
    border-radius: 12px;
    object-fit: cover;
    background: #f8fafc;
}
.pd-tag {
    color: #ea580c;
    font-weight: 800;
    font-size: 13px;
    text-transform: uppercase;
}
.pd-title {
    font-size: 29px;
    color: #0f172a;
    line-height: 1.4;
    margin: 12px 0;
}
.pd-rating {
    color: #f59e0b;
    font-weight: 700;
    margin-bottom: 14px;
}
.pd-price {
    font-size: 32px;
    color: #ea580c;
    font-weight: 900;
    margin: 16px 0 22px;
}
.pd-stock {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    line-height: 1.8;
    margin-bottom: 20px;
}
.pd-size {
    background: #f8fafc;
    padding: 18px;
    border-radius: 11px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}
.pd-sizes {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 13px;
}
.pd-size-option {
    cursor: pointer;
}
.pd-size-option input {
    position: absolute;
    opacity: 0;
}
.pd-size-option span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 48px;
    height: 42px;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    color: #334155;
    font-weight: 800;
    padding: 0 11px;
}
.pd-size-option input:checked + span {
    color: white;
    background: #ea580c;
    border-color: #ea580c;
}
.pd-size-option input:focus-visible + span {
    outline: 3px solid #93c5fd;
}
.pd-cart-row {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 15px;
}
.pd-quantity {
    width: 85px;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font: inherit;
}
.pd-add-button {
    flex: 1;
    background: #ea580c;
    border: 0;
    border-radius: 30px;
    color: white;
    padding: 14px;
    font-weight: 800;
    cursor: pointer;
}
.pd-add-button:hover {
    background: #c2410c;
}
.pd-add-button:disabled {
    opacity: .6;
    cursor: not-allowed;
}
.pd-message {
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 18px;
    line-height: 1.7;
}
.pd-message.success {
    color: #166534;
    background: #dcfce7;
}
.pd-message.error {
    color: #991b1b;
    background: #fee2e2;
}
.pd-message a {
    display: inline-block;
    margin-left: 12px;
    font-weight: 800;
}
.pd-section h2 {
    color: #0f172a;
    font-size: 22px;
    margin: 0 0 19px;
    padding-bottom: 13px;
    border-bottom: 2px solid #f1f5f9;
}
.pd-description {
    color: #334155;
    line-height: 1.9;
}
.pd-table-wrap {
    overflow-x: auto;
    width: 100%;
}
.pd-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 570px;
}
.pd-table th {
    background: #166534;
    color: white;
}
.pd-table th,
.pd-table td {
    text-align: center;
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
}
.pd-review {
    padding: 18px 0;
    border-bottom: 1px solid #e2e8f0;
}
.pd-review-header {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.pd-review-form {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.pd-field {
    margin-bottom: 15px;
}
.pd-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
}
.pd-field select,
.pd-field textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font: inherit;
}
.pd-review-button {
    background: #166534;
    color: white;
    border: 0;
    padding: 12px 18px;
    border-radius: 9px;
    font-weight: 800;
    cursor: pointer;
}
@media (max-width: 800px) {
    .pd-grid {
        grid-template-columns: 1fr;
    }
    .pd-image {
        height: 300px;
    }
    .pd-card,
    .pd-section {
        padding: 18px;
    }
    .pd-title {
        font-size: 24px;
    }
}
</style>

<div class="container section pd-page">

    <!-- NUT QUAY LAI CUA HANG -->

    <a href="<?= pdh($base) ?>shop/shop.php" class="pd-back">
        <i class="fa-solid fa-arrow-left"></i>
        Quay lại danh sách sản phẩm
    </a>

    <?php if ($added): ?>
        <div class="pd-message success" role="status">
            <i class="fa-solid fa-circle-check"></i>
            Đã thêm
            <strong><?= pdh($product['name']) ?></strong>
            vào giỏ hàng.

            <a href="<?= pdh($base) ?>shop/cart.php">
                Xem giỏ hàng →
            </a>
        </div>
    <?php endif; ?>

    <?php if ($cartError): ?>
        <div class="pd-message error" role="alert">
            <?= pdh($cartError) ?>
        </div>
    <?php endif; ?>

    <!-- CHI TIET SAN PHAM -->

    <section class="pd-card">

        <div class="pd-grid">

            <div>
                <img
                    class="pd-image"
                    src="<?= pdh($product['image']) ?>"
                    alt="<?= pdh($product['name']) ?>"
                    onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1510312305653-8ed496efae75?w=600';"
                >
            </div>

            <div>
                <div class="pd-tag">
                    <i class="fa-solid fa-tag"></i>
                    <?= pdh($product['brand'] ?? '') ?>
                    · <?= pdh($product['cat_name']) ?>
                </div>

                <h1 class="pd-title">
                    <?= pdh($product['name']) ?>
                </h1>

                <div class="pd-rating">
                    <?= str_repeat('★', (int) round($avgRating)) ?>
                    <span style="color:#334155">
                        <?= $avgRating ?> / 5.0
                    </span>
                    <span style="color:#64748b">
                        (<?= count($reviews) ?> đánh giá)
                    </span>
                </div>

                <div class="pd-price">
                    <?= format_price($product['price']) ?>
                </div>

                <div class="pd-stock">
                    <div>
                        Tình trạng kho:

                        <?php if ((int) $product['stock'] > 0): ?>
                            <strong style="color:#16a34a">
                                <i class="fa-solid fa-check"></i>
                                Còn hàng
                                (<?= (int) $product['stock'] ?> chiếc)
                            </strong>
                        <?php else: ?>
                            <strong style="color:#dc2626">
                                Tạm hết hàng
                            </strong>
                        <?php endif; ?>
                    </div>

                    <div>
                        Chính sách:
                        <strong>
                            1 đổi 1 trong 7 ngày nếu lỗi nhà sản xuất
                        </strong>
                    </div>
                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="add_to_cart"
                        value="1"
                    >

                    <?php if ($has_size): ?>

                        <div class="pd-size">

                            <strong>
                                <i class="fa-solid fa-ruler"></i>
                                <?php if ($is_footwear): ?>
                                    Chọn size giày/dép
                                <?php elseif ($is_gloves): ?>
                                    Chọn size găng tay
                                <?php else: ?>
                                    Chọn kích cỡ áo/quần
                                <?php endif; ?>
                            </strong>

                            <div class="pd-sizes">
                                <?php foreach ($sizes as $size): ?>

                                    <label class="pd-size-option">

                                        <input
                                            type="radio"
                                            name="size"
                                            value="<?= pdh($size) ?>"
                                            <?= $size === $defaultSize
                                                ? 'checked'
                                                : '' ?>
                                            required
                                        >

                                        <span><?= pdh($size) ?></span>

                                    </label>

                                <?php endforeach; ?>
                            </div>

                            <?php if ($sizeChartText !== ''): ?>
                            <a
                                href="#size-chart"
                                style="display:inline-block;margin-top:12px;color:#ea580c"
                            >
                                Xem bảng size tham khảo ↓
                            </a>
                            <?php endif; ?>
                        </div>

                    <?php endif; ?>

                    <?php if ((int) $product['stock'] > 0): ?>

                        <div class="pd-cart-row">

                            <label for="pdQty">
                                Số lượng
                            </label>

                            <input
                                id="pdQty"
                                class="pd-quantity"
                                type="number"
                                name="quantity"
                                min="1"
                                max="<?= (int) $product['stock'] ?>"
                                value="1"
                                required
                            >

                            <button
                                type="submit"
                                class="pd-add-button"
                            >
                                <i class="fa-solid fa-cart-shopping"></i>
                                THÊM VÀO GIỎ HÀNG
                            </button>

                        </div>

                    <?php else: ?>

                        <button
                            type="button"
                            class="pd-add-button"
                            disabled
                        >
                            TẠM HẾT HÀNG
                        </button>

                    <?php endif; ?>

                </form>

                <p style="color:#64748b;font-size:13px;line-height:1.8">
                    <i
                        class="fa-solid fa-truck-fast"
                        style="color:#16a34a"
                    ></i>
                    Giao hàng toàn quốc. Kiểm tra hàng và thanh toán
                    khi nhận (COD).
                </p>

            </div>

        </div>

    </section>

    <!-- MO TA SAN PHAM -->

    <section class="pd-section">

        <h2>
            <i
                class="fa-solid fa-circle-info"
                style="color:#166534"
            ></i>
            Giới thiệu & Tính năng sản phẩm
        </h2>

        <div class="pd-description">
            <?= nl2br(pdh(
                $product['description']
                ?: 'Đang cập nhật mô tả chi tiết sản phẩm.'
            )) ?>
        </div>

        <?php if (!empty($product['specs'])): ?>

            <h3 style="margin-top:25px">
                Thông số kỹ thuật & Chất liệu
            </h3>

            <div class="pd-description">
                <?= nl2br(pdh($product['specs'])) ?>
            </div>

        <?php endif; ?>

    </section>

    <!-- BANG SIZE DO QUAN LY NHAP (NEU CO) -->

    <?php if ($sizeChartText !== ''): ?>
        <section class="pd-section" id="size-chart">
            <h2>
                <i class="fa-solid fa-ruler-combined" style="color:#ea580c"></i>
                Bảng size tham khảo
            </h2>

            <?php if ($sizeChartIsTable): ?>
                <div class="pd-table-wrap">
                    <table class="pd-table">
                        <thead><tr>
                            <?php foreach ($sizeChartRows[0] as $heading): ?>
                                <th><?= pdh($heading) ?></th>
                            <?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                            <?php foreach (array_slice($sizeChartRows, 1) as $cells): ?>
                                <tr>
                                    <?php foreach ($sizeChartRows[0] as $columnIndex => $heading): ?>
                                        <td><?= pdh($cells[$columnIndex] ?? '') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="pd-description"><?= nl2br(pdh($sizeChartText)) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- DANH GIA SAN PHAM -->

    <section class="pd-section">

        <h2>
            <i
                class="fa-solid fa-star"
                style="color:#f59e0b"
            ></i>
            Đánh giá từ khách hàng
            (<?= count($reviews) ?>)
        </h2>

        <p class="pd-rating">
            <?= str_repeat('★', (int) round($avgRating)) ?>
            <?= $avgRating ?> / 5.0
        </p>

        <?php if ($reviewMessage): ?>
            <div class="pd-message success">
                <?= pdh($reviewMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($reviewError): ?>
            <div class="pd-message error">
                <?= pdh($reviewError) ?>
            </div>
        <?php endif; ?>

        <?php if ($canReviewProduct): ?>

            <form method="POST" class="pd-review-form">

                <input
                    type="hidden"
                    name="submit_review"
                    value="1"
                >

                <div class="pd-field">
                    <label for="pdRating">
                        Mức độ hài lòng
                    </label>

                    <select id="pdRating" name="rating" required>
                        <option value="5">5 sao — Rất tốt</option>
                        <option value="4">4 sao — Tốt</option>
                        <option value="3">3 sao — Bình thường</option>
                        <option value="2">2 sao — Chưa hài lòng</option>
                        <option value="1">1 sao — Không hài lòng</option>
                    </select>
                </div>

                <div class="pd-field">
                    <label for="pdComment">
                        Nhận xét sản phẩm
                    </label>

                    <textarea
                        id="pdComment"
                        name="comment"
                        rows="4"
                        required
                        placeholder="Chia sẻ trải nghiệm sử dụng sản phẩm..."
                    ></textarea>
                </div>

                <button
                    class="pd-review-button"
                    type="submit"
                >
                    <i class="fa-solid fa-paper-plane"></i>
                    Gửi đánh giá
                </button>

            </form>

        <?php elseif (isLoggedIn()): ?>
            <div class="pd-message">Bạn chỉ có thể đánh giá sản phẩm này khi đã mua bằng tài khoản hiện tại và đơn hàng đã thanh toán hoặc giao thành công.</div>
        <?php else: ?>

            <div class="pd-message">
                Vui lòng
                <a href="<?= pdh($base) ?>auth/login.php">
                    đăng nhập
                </a>
                để đánh giá sản phẩm.
            </div>

        <?php endif; ?>

        <?php if ($reviews): ?>

            <?php foreach ($reviews as $review): ?>

                <div class="pd-review">

                    <div class="pd-review-header">
                        <strong><?= pdh($review['fullname']) ?></strong>

                        <span style="color:#f59e0b">
                            <?= str_repeat('★', (int) $review['rating']) ?>
                        </span>
                    </div>

                    <p style="color:#475569;line-height:1.7">
                        <?= nl2br(pdh($review['comment'])) ?>
                    </p>

                    <?php if (trim((string)($review['admin_reply'] ?? '')) !== ''): ?>
                        <div style="margin:10px 0;padding:12px 14px;background:#f0fdf4;border-left:3px solid #16a34a;border-radius:8px">
                            <strong style="color:#166534">Phản hồi từ VNN Travel</strong>
                            <p style="margin:6px 0 0"><?= nl2br(pdh($review['admin_reply'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <small style="color:#94a3b8">
                        <?= date(
                            'd/m/Y H:i',
                            strtotime($review['created_at'])
                        ) ?>
                    </small>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p style="color:#64748b">
                Chưa có đánh giá nào cho sản phẩm này.
            </p>

        <?php endif; ?>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
