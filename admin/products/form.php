<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = 'products';
$page_title = 'Thêm / Sửa sản phẩm - VNN Travel';

function productFormH($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

if (empty($_SESSION['products_csrf'])) {
    $_SESSION['products_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['products_csrf'];

$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? 0,
    FILTER_VALIDATE_INT
) ?: 0;

$isEdit = $id > 0;
$error = '';

$product = [
    'name' => '',
    'category_id' => '',
    'brand' => 'TrekGear',
    'price' => '',
    'stock' => '0',
    'image' => '',
    'specs' => '',
    'description' => '',
    'status' => 1
];

$categories = [];

// =====================================
// THU VIEN ANH CO SAN TREN GITHUB
// =====================================

$imageDirectory = dirname(__DIR__, 2)
    . '/assets/images/products';

$availableImages = [];

if (is_dir($imageDirectory)) {
    $files = scandir($imageDirectory);

    foreach ($files ?: [] as $filename) {
        if (!preg_match(
            '/\.(jpg|jpeg|png|webp)$/i',
            $filename
        )) {
            continue;
        }

        $fullPath = $imageDirectory
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!is_file($fullPath)) {
            continue;
        }

        $availableImages[] = [
            'name' => $filename,
            'path' => '/assets/images/products/'
                . rawurlencode($filename)
        ];
    }

    usort(
        $availableImages,
        static function($a, $b) {
            return strnatcasecmp(
                $a['name'],
                $b['name']
            );
        }
    );
}

$allowedImages = array_column(
    $availableImages,
    'path'
);

// =====================================
// DANH MUC
// =====================================

try {
    $categories = $pdo->query("
        SELECT id, name
        FROM categories
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Product categories: ' . $e->getMessage());
    $error = 'Không thể tải danh mục sản phẩm.';
}

// =====================================
// DU LIEU SAN PHAM KHI SUA
// =====================================

$record = null;

if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM products
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        http_response_code(404);
        exit('Không tìm thấy sản phẩm.');
    }

    $product = array_merge($product, $record);
}

// =====================================
// LUU SAN PHAM
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            (string)$csrf,
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ.'
            );
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $brand = trim((string)($_POST['brand'] ?? ''));
        $specs = trim((string)($_POST['specs'] ?? ''));
        $description = trim(
            (string)($_POST['description'] ?? '')
        );

        $categoryId = filter_var(
            $_POST['category_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $price = filter_var(
            $_POST['price'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $stock = filter_var(
            $_POST['stock'] ?? null,
            FILTER_VALIDATE_INT
        );

        $status = isset($_POST['status']) ? 1 : 0;

        $selectedImage = trim(
            (string)($_POST['image'] ?? '')
        );

        $product = array_merge($product, [
            'name' => $name,
            'brand' => $brand,
            'specs' => $specs,
            'description' => $description,
            'category_id' => $_POST['category_id'] ?? '',
            'price' => $_POST['price'] ?? '',
            'stock' => $_POST['stock'] ?? '',
            'status' => $status
        ]);

        if ($name === '') {
            throw new RuntimeException(
                'Vui lòng nhập tên sản phẩm.'
            );
        }

        if (mb_strlen($name) > 255) {
            throw new RuntimeException(
                'Tên sản phẩm quá dài.'
            );
        }

        if (!$categoryId || $categoryId <= 0) {
            throw new RuntimeException(
                'Vui lòng chọn danh mục sản phẩm.'
            );
        }

        if ($price === false || $price < 0) {
            throw new RuntimeException(
                'Giá bán không hợp lệ.'
            );
        }

        if ($stock === false || $stock < 0) {
            throw new RuntimeException(
                'Tồn kho phải từ 0 trở lên.'
            );
        }

        $checkCategory = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
            LIMIT 1
        ");

        $checkCategory->execute([$categoryId]);

        if (!$checkCategory->fetchColumn()) {
            throw new RuntimeException(
                'Danh mục không tồn tại.'
            );
        }

        // Anh cu duoc giu nguyen neu khong doi anh.
        $currentImage = $isEdit
            ? (string)($record['image'] ?? '')
            : '';

        if ($selectedImage === '' && $isEdit) {
            $selectedImage = $currentImage;
        }

        if ($selectedImage === '') {
            throw new RuntimeException(
                'Vui lòng chọn ảnh sản phẩm.'
            );
        }

        $isLibraryImage = in_array(
            $selectedImage,
            $allowedImages,
            true
        );

        $isExistingImage = (
            $isEdit &&
            $currentImage !== '' &&
            hash_equals($currentImage, $selectedImage)
        );

        if (!$isLibraryImage && !$isExistingImage) {
            throw new RuntimeException(
                'Ảnh được chọn không tồn tại trong thư viện.'
            );
        }

        $product['image'] = $selectedImage;

        if ($isEdit) {
            $stmt = $pdo->prepare("
                UPDATE products SET
                    name = ?,
                    category_id = ?,
                    brand = ?,
                    price = ?,
                    stock = ?,
                    image = ?,
                    specs = ?,
                    description = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $categoryId,
                $brand,
                $price,
                $stock,
                $selectedImage,
                $specs,
                $description,
                $status,
                $id
            ]);

            $_SESSION['product_notice'] =
                'Đã cập nhật sản phẩm thành công.';

        } else {
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name,
                    category_id,
                    brand,
                    price,
                    stock,
                    image,
                    specs,
                    description,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $categoryId,
                $brand,
                $price,
                $stock,
                $selectedImage,
                $specs,
                $description,
                $status
            ]);

            $_SESSION['product_notice'] =
                'Đã thêm sản phẩm mới thành công.';
        }

        header('Location: index.php', true, 303);
        exit;

    } catch (Throwable $e) {
        error_log('Product form: ' . $e->getMessage());

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể lưu sản phẩm.';
    }
}

$selectedImage = (string)($product['image'] ?? '');

$selectedFilename = $selectedImage
    ? basename(rawurldecode($selectedImage))
    : 'Chưa chọn ảnh';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= productFormH($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
* {
    box-sizing: border-box;
}

.pf-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    background: #f1f5f9;
    padding: 24px;
    color: #173454;
    font-size: 14px;
}

.pf-header,
.pf-panel {
    max-width: 1200px;
    margin: 0 auto 18px;
    padding: 22px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

.pf-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.pf-header h1 {
    font-size: 20px;
    color: #102e52;
    margin: 0;
}

.pf-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.pf-field {
    min-width: 0;
}

.pf-field.full {
    grid-column: 1 / -1;
}

.pf-field label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 800;
}

.pf-input {
    display: block;
    width: 100%;
    padding: 12px 13px;
    min-height: 44px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font: inherit;
    font-size: 13px;
    background: white;
    color: #173454;
}

.pf-input:focus {
    outline: none;
    border-color: #166534;
    box-shadow: 0 0 0 3px rgba(22,101,52,.1);
}

textarea.pf-input {
    min-height: 115px;
    resize: vertical;
    line-height: 1.7;
}

.pf-section {
    margin-bottom: 25px;
}

.pf-section h2 {
    margin: 0 0 18px;
    font-size: 18px;
    color: #102e52;
}

.pf-divider {
    border: none;
    border-top: 1px solid #e2e8f0;
    margin: 28px 0;
}

.pf-note {
    font-size: 12px;
    color: #64748b;
    line-height: 1.8;
    margin-bottom: 16px;
}

.pf-gallery-tools {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 17px;
}

.pf-gallery-tools .pf-input {
    flex: 1;
    min-width: 220px;
}

.pf-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    min-height: 43px;
    padding: 11px 17px;
    border: none;
    border-radius: 9px;
    background: #166534;
    color: white !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.pf-btn:hover {
    background: #14532d;
}

.pf-btn.gray {
    background: #e2e8f0;
    color: #334155 !important;
}

.pf-btn.blue {
    background: #1d4ed8;
}

.pf-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.pf-gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 13px;
}

.pf-photo {
    display: block;
    position: relative;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    border-radius: 11px;
    cursor: pointer;
    background: white;
}

.pf-photo[hidden] {
    display: none !important;
}

.pf-photo:hover {
    border-color: #86efac;
}

.pf-photo:has(input:checked) {
    border-color: #166534;
    background: #f0fdf4;
}

.pf-photo input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.pf-photo img {
    display: block;
    width: 100%;
    height: 160px;
    object-fit: cover;
    background: #f1f5f9;
}

.pf-photo-name {
    padding: 10px;
    font-size: 12px;
    color: #334155;
    font-weight: 700;
    text-align: center;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.pf-check {
    position: absolute;
    right: 9px;
    top: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 29px;
    height: 29px;
    border-radius: 50%;
    background: #fff;
    color: #94a3b8;
    box-shadow: 0 2px 8px #0002;
}

.pf-photo:has(input:checked) .pf-check {
    background: #166534;
    color: white;
}

.pf-selected {
    margin-top: 16px;
    padding: 13px;
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    border-radius: 9px;
    font-size: 13px;
}

.pf-current-image {
    width: 170px;
    height: 145px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    display: block;
    margin: 12px 0 20px;
}

.pf-empty {
    padding: 20px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    border-radius: 10px;
    line-height: 1.8;
    font-size: 13px;
}

.pf-error {
    margin-bottom: 19px;
    padding: 14px 16px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 10px;
    font-size: 13px;
}

.pf-status {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 13px;
    font-weight: 700;
}

.pf-status input {
    width: 17px;
    height: 17px;
    accent-color: #166534;
}

.pf-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    flex-wrap: wrap;
}

@media(max-width:750px) {
    .pf-page {
        padding: 12px;
    }

    .pf-header,
    .pf-panel {
        padding: 15px;
    }

    .pf-grid {
        grid-template-columns: 1fr;
    }

    .pf-gallery {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
</head>

<body class="admin-body">

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main pf-page">

    <div class="pf-header">

        <h1>
            <i class="fa-solid fa-box"></i>

            <?= $isEdit
                ? 'CHỈNH SỬA SẢN PHẨM'
                : 'THÊM HÀNG HÓA MỚI' ?>
        </h1>

        <a href="index.php" class="pf-btn gray">
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại danh sách
        </a>

    </div>

    <section class="pf-panel">

        <?php if ($error): ?>
            <div class="pf-error" role="alert">
                <?= productFormH($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="productForm">

            <input type="hidden"
                   name="csrf"
                   value="<?= productFormH($csrf) ?>">

            <input type="hidden"
                   name="id"
                   value="<?= (int)$id ?>">

            <div class="pf-section">

                <h2>Thông tin sản phẩm</h2>

                <div class="pf-grid">

                    <div class="pf-field">
                        <label for="name">Tên sản phẩm *</label>
                        <input class="pf-input"
                               type="text"
                               name="name"
                               id="name"
                               maxlength="255"
                               value="<?= productFormH($product['name']) ?>"
                               required>
                    </div>

                    <div class="pf-field">
                        <label for="category_id">Danh mục *</label>
                        <select class="pf-input"
                                name="category_id"
                                id="category_id"
                                required>

                            <option value="">-- Chọn danh mục --</option>

                            <?php foreach ($categories as $c): ?>
                                <option
                                    value="<?= (int)$c['id'] ?>"
                                    <?= (int)$product['category_id'] ===
                                        (int)$c['id'] ? 'selected' : '' ?>
                                >
                                    <?= productFormH($c['name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="pf-field">
                        <label for="brand">Thương hiệu</label>
                        <input class="pf-input"
                               type="text"
                               name="brand"
                               id="brand"
                               value="<?= productFormH($product['brand']) ?>">
                    </div>

                    <div class="pf-field">
                        <label for="price">Giá bán (VNĐ) *</label>
                        <input class="pf-input"
                               type="number"
                               min="0"
                               step="1"
                               name="price"
                               id="price"
                               value="<?= productFormH($product['price']) ?>"
                               required>
                    </div>

                    <div class="pf-field">
                        <label for="stock">Số lượng tồn kho *</label>
                        <input class="pf-input"
                               type="number"
                               min="0"
                               name="stock"
                               id="stock"
                               value="<?= productFormH($product['stock']) ?>"
                               required>
                    </div>

                    <div class="pf-field full">
                        <label for="specs">Thông số kỹ thuật</label>
                        <textarea class="pf-input"
                                  name="specs"
                                  id="specs"
                                  rows="4"><?= productFormH($product['specs']) ?></textarea>
                    </div>

                    <div class="pf-field full">
                        <label for="description">Mô tả sản phẩm</label>
                        <textarea class="pf-input"
                                  name="description"
                                  id="description"
                                  rows="5"><?= productFormH($product['description']) ?></textarea>
                    </div>

                </div>
            </div>

            <hr class="pf-divider">

            <div class="pf-section">

                <h2>
                    <i class="fa-regular fa-images"></i>
                    Chọn ảnh sản phẩm
                </h2>

                <p class="pf-note">
                    Ảnh được lấy từ thư mục
                    <strong>assets/images/products/</strong>
                    trong GitHub.
                    Mặc định chỉ hiển thị 6 ảnh.
                    Có thể tìm theo tên file hoặc xem toàn bộ.
                </p>

                <?php if ($isEdit && $selectedImage): ?>

                    <strong style="font-size:13px">
                        Ảnh sản phẩm hiện tại
                    </strong>

                    <img class="pf-current-image"
                         src="<?= productFormH($selectedImage) ?>"
                         alt="Ảnh sản phẩm hiện tại"
                         onerror="this.style.display='none'">

                <?php endif; ?>

                <?php if ($availableImages): ?>

                    <div class="pf-gallery-tools">

                        <input class="pf-input"
                               type="search"
                               id="photoSearch"
                               placeholder="Tìm tên ảnh... Ví dụ: balo, giay, ao..."
                               autocomplete="off">

                        <button type="button"
                                class="pf-btn blue"
                                id="togglePhotos">
                            Xem tất cả ảnh
                        </button>
                    </div>

                    <div class="pf-gallery" id="photoGallery">

                        <?php foreach ($availableImages as $photo): ?>

                            <label class="pf-photo"
                                   data-name="<?= productFormH($photo['name']) ?>">

                                <input
                                    type="radio"
                                    name="image"
                                    value="<?= productFormH($photo['path']) ?>"
                                    <?= $selectedImage === $photo['path']
                                        ? 'checked' : '' ?>
                                >

                                <img src="<?= productFormH($photo['path']) ?>"
                                     alt="<?= productFormH($photo['name']) ?>"
                                     loading="lazy">

                                <span class="pf-check">
                                    <i class="fa-solid fa-check"></i>
                                </span>

                                <div class="pf-photo-name"
                                     title="<?= productFormH($photo['name']) ?>">
                                    <?= productFormH($photo['name']) ?>
                                </div>

                            </label>

                        <?php endforeach; ?>

                    </div>

                    <p class="pf-note"
                       id="photoCount"
                       style="margin-top:12px"></p>

                    <div class="pf-empty"
                         id="photoNoResults"
                         hidden>
                        Không tìm thấy ảnh phù hợp.
                    </div>

                    <div class="pf-selected">
                        <i class="fa-solid fa-circle-check"></i>
                        Ảnh đã chọn:
                        <strong id="selectedPhoto">
                            <?= productFormH($selectedFilename) ?>
                        </strong>
                    </div>

                <?php else: ?>

                    <div class="pf-empty">
                        Chưa có ảnh trong thư mục
                        <strong>assets/images/products/</strong>.
                        Hãy tải ảnh lên GitHub và chờ Vercel
                        triển khai xong rồi tải lại trang.
                        <?php if ($isEdit && $selectedImage): ?>
                            Bạn vẫn có thể giữ ảnh hiện tại.
                        <?php endif; ?>
                    </div>

                <?php endif; ?>

            </div>

            <hr class="pf-divider">

            <label class="pf-status">
                <input type="checkbox"
                       name="status"
                       value="1"
                       <?= (int)$product['status'] === 1
                           ? 'checked' : '' ?>>

                Hiển thị sản phẩm trên cửa hàng
            </label>

            <div class="pf-actions">

                <button type="submit"
                        class="pf-btn"
                        id="saveProduct"
                        <?= !$availableImages && !$selectedImage
                            ? 'disabled' : '' ?>>

                    <i class="fa-solid fa-floppy-disk"></i>

                    <?= $isEdit
                        ? 'Lưu thay đổi'
                        : 'Thêm sản phẩm' ?>
                </button>

                <a href="index.php" class="pf-btn gray">
                    Hủy
                </a>

            </div>

        </form>

    </section>
</main>

<script>
(function() {
    const search = document.getElementById('photoSearch');
    const gallery = document.getElementById('photoGallery');
    const toggle = document.getElementById('togglePhotos');
    const count = document.getElementById('photoCount');
    const noResults = document.getElementById('photoNoResults');
    const selected = document.getElementById('selectedPhoto');

    const cards = gallery
        ? Array.from(gallery.querySelectorAll('.pf-photo'))
        : [];

    let showAll = false;

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'd')
            .toLowerCase();
    }

    function updatePhotos() {
        const keyword = normalize(search?.value.trim() || '');

        const matching = cards.filter(function(card) {
            return normalize(card.dataset.name).includes(keyword);
        });

        const limit = keyword || showAll
            ? matching.length
            : 6;

        cards.forEach(function(card) {
            const index = matching.indexOf(card);
            card.hidden = index < 0 || index >= limit;
        });

        if (count) {
            count.textContent =
                'Hiển thị ' +
                Math.min(limit, matching.length) +
                '/' + matching.length +
                ' ảnh phù hợp.';
        }

        if (noResults) {
            noResults.hidden = matching.length !== 0;
        }

        if (toggle) {
            toggle.style.display = keyword ? 'none' : '';
            toggle.textContent = showAll
                ? 'Thu gọn ảnh'
                : 'Xem tất cả ảnh';
        }
    }

    search?.addEventListener('input', updatePhotos);

    toggle?.addEventListener('click', function() {
        showAll = !showAll;
        updatePhotos();
    });

    gallery?.querySelectorAll('input[name="image"]')
        .forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (selected && radio.checked) {
                    selected.textContent = decodeURIComponent(
                        radio.value.split('/').pop()
                    );
                }
            });
        });

    updatePhotos();

    const form = document.getElementById('productForm');

    form?.addEventListener('submit', function(event) {
        const chosen = form.querySelector(
            'input[name="image"]:checked'
        );

        const oldImage = <?= json_encode(
            $isEdit ? (string)($record['image'] ?? '') : '',
            JSON_UNESCAPED_UNICODE |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) ?>;

        if (!chosen && !oldImage) {
            event.preventDefault();
            alert('Vui lòng chọn ảnh sản phẩm.');
            return;
        }

        if (form.checkValidity()) {
            const button = document.getElementById('saveProduct');

            if (button) {
                button.disabled = true;
                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> ĐANG LƯU...';
            }
        }
    });
})();
</script>

</body>
</html>
