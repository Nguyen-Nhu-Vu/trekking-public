
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

if (!function_exists('pfh')) {
    function pfh($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('pfJson')) {
    function pfJson(bool $success, string $message): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            http_response_code($success ? 200 : 422);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        echo json_encode([
            'success' => $success,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}

/* =====================================
   CSRF
   ===================================== */

if (empty($_SESSION['products_csrf'])) {
    $_SESSION['products_csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string)$_SESSION['products_csrf'];

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
    'stock' => 0,
    'image' => '',
    'specs' => '',
    'description' => '',
    'status' => 1
];

$categories = [];
$record = null;
$availableImages = [];

/* =====================================
   TAI DANH MUC
   ===================================== */

try {
    $categories = $pdo->query("
        SELECT id, name
        FROM categories
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Load categories: ' . $e->getMessage());
    $error = 'Không thể tải danh mục hàng hóa.';
}

/* =====================================
   LAY THONG TIN KHI SUA
   ===================================== */

if ($isEdit) {
    try {
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

    } catch (Throwable $e) {
        error_log('Load product: ' . $e->getMessage());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            pfJson(false, 'Không thể tải sản phẩm cần chỉnh sửa.');
        }

        $error = 'Không thể tải thông tin sản phẩm.';
    }
}

/* =====================================
   XU LY LUU THEM / SUA BANG AJAX
   ===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        if (!hash_equals(
            $csrf,
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
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

        /* KIEM TRA DU LIEU */

        if ($name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException(
                'Tên sản phẩm không hợp lệ.'
            );
        }

        if (!$categoryId || $categoryId <= 0) {
            throw new RuntimeException(
                'Vui lòng chọn danh mục.'
            );
        }

        if ($price === false || $price < 0) {
            throw new RuntimeException(
                'Giá bán không hợp lệ.'
            );
        }

        if ($stock === false || $stock < 0) {
            throw new RuntimeException(
                'Số lượng tồn kho không hợp lệ.'
            );
        }

        $checkCategory = $pdo->prepare("
            SELECT COUNT(*)
            FROM categories
            WHERE id = ?
        ");

        $checkCategory->execute([$categoryId]);

        if (!(int)$checkCategory->fetchColumn()) {
            throw new RuntimeException(
                'Danh mục không tồn tại.'
            );
        }

        /* KIEM TRA ANH */

        $oldImage = $isEdit
            ? (string)($record['image'] ?? '')
            : '';

        if ($selectedImage === '' && $isEdit) {
            $selectedImage = $oldImage;
        }

        if ($selectedImage === '') {
            throw new RuntimeException(
                'Vui lòng chọn ảnh sản phẩm.'
            );
        }

        if ($selectedImage === '') {
            throw new RuntimeException('Vui lòng nhập URL ảnh.');
        }

        if (!filter_var($selectedImage, FILTER_VALIDATE_URL) ||
            !in_array(strtolower((string)parse_url($selectedImage, PHP_URL_SCHEME)), ['http','https'], true)) {
            throw new RuntimeException('URL ảnh phải bắt đầu bằng http:// hoặc https:// và hợp lệ.');
        }

        if (strlen($selectedImage) > 2000) {
            throw new RuntimeException('URL ảnh quá dài.');
        }

        /* CAP NHAT SAN PHAM */

        if ($isEdit) {

            $stmt = $pdo->prepare("
                UPDATE products
                SET
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

            require_once __DIR__ . '/../includes/audit.php';

            vnnAuditChange(
                $pdo,
                'inventory',
                'Sửa sản phẩm',
                'products',
                $id,
                (string)($record['name'] ?? ''),
                $name
            );

            pfJson(
                true,
                'Cập nhật sản phẩm thành công!'
            );

        } else {

            /* THEM SAN PHAM MOI */

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

            require_once __DIR__ . '/../includes/audit.php';

            vnnAuditChange(
                $pdo,
                'inventory',
                'Thêm sản phẩm',
                'products',
                (int)$pdo->lastInsertId(),
                '',
                $name
            );

            pfJson(
                true,
                'Thêm sản phẩm thành công!'
            );
        }

    } catch (Throwable $e) {

        error_log(
            'Save product: ' . $e->getMessage()
        );

        pfJson(
            false,
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể lưu sản phẩm. Vui lòng thử lại.'
        );
    }
}

$currentImage = (string)($product['image'] ?? '');

$currentFilename = $currentImage !== ''
    ? basename(rawurldecode($currentImage))
    : 'Chưa chọn ảnh';
?>
<!DOCTYPE html>
<html lang="vi">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title><?= pfh($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet"
 href="../../assets/css/style.css">

<link rel="stylesheet"
 href="../../assets/css/admin.css">

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
    padding: 24px;
    background: #fff;
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
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #102e52;
}

.pf-section {
    margin-bottom: 25px;
}

.pf-section h2 {
    margin: 0 0 18px;
    font-size: 18px;
    color: #102e52;
}

.pf-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.pf-field {
    min-width: 0;
    margin-bottom: 7px;
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
    min-height: 44px;
    padding: 12px 13px;
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
    min-height: 116px;
    resize: vertical;
    line-height: 1.7;
}

.pf-note {
    margin-bottom: 16px;
    font-size: 12px;
    line-height: 1.8;
    color: #64748b;
}

.pf-divider {
    border: 0;
    border-top: 1px solid #e2e8f0;
    margin: 28px 0;
}

.pf-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 43px;
    padding: 11px 17px;
    border: none;
    border-radius: 9px;
    background: #166534;
    color: #fff !important;
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
    opacity: .55;
    cursor: not-allowed;
}

.pf-gallery-tools {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.pf-gallery-tools .pf-input {
    flex: 1;
    min-width: 200px;
}

.pf-gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 13px;
}

.pf-photo {
    position: relative;
    display: block;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    border-radius: 11px;
    cursor: pointer;
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
    height: 165px;
    object-fit: cover;
    background: #f1f5f9;
}

.pf-photo-name {
    display: block;
    padding: 10px;
    font-size: 12px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pf-check {
    position: absolute;
    right: 8px;
    top: 8px;
    background: #166534;
    color: white;
    padding: 7px;
    border-radius: 50%;
    display: none;
}

.pf-photo:has(input:checked) .pf-check {
    display: block;
}

.pf-current {
    display: block;
    width: 170px;
    height: 145px;
    object-fit: cover;
    border-radius: 10px;
    margin: 10px 0 20px;
}

.pf-selected {
    margin-top: 16px;
    padding: 13px;
    border: 1px solid #bbf7d0;
    border-radius: 9px;
    background: #f0fdf4;
    color: #166534;
}

.pf-empty {
    padding: 17px;
    border-radius: 10px;
    background: #fff7ed;
    color: #9a3412;
}

.pf-empty[hidden] {
    display: none !important;
}

.pf-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.pf-status {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
}

.pf-status input {
    width: 17px;
    height: 17px;
    accent-color: #166534;
}

.pf-error {
    padding: 14px;
    margin-bottom: 18px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 9px;
}

/* =====================================
   POPUP GIUA MAN HINH
   ===================================== */

.pf-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15,23,42,.6);
}

.pf-overlay[hidden] {
    display: none !important;
}

.pf-modal {
    width: 100%;
    max-width: 420px;
    padding: 29px 25px;
    border-radius: 17px;
    background: white;
    text-align: center;
    box-shadow: 0 25px 70px rgba(0,0,0,.2);
}

.pf-modal-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 67px;
    height: 67px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: #dcfce7;
    color: #166534;
    font-size: 31px;
}

.pf-modal-icon.error {
    background: #fee2e2;
    color: #dc2626;
}

.pf-modal h2 {
    margin: 0 0 10px;
    color: #102e52;
    font-size: 21px;
}

.pf-modal p {
    margin: 0 0 22px;
    font-size: 14px;
    line-height: 1.7;
    color: #64748b;
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
        grid-template-columns: repeat(2, minmax(0,1fr));
    }
}
</style>

</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

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
            <div class="pf-error">
                <?= pfh($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="productForm">

            <input
                type="hidden"
                name="csrf"
                value="<?= pfh($csrf) ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$id ?>"
            >

            <section class="pf-section">

                <h2>Thông tin sản phẩm</h2>

                <div class="pf-grid">

                    <div class="pf-field">

                        <label for="name">
                            Tên sản phẩm *
                        </label>

                        <input
                            class="pf-input"
                            type="text"
                            name="name"
                            id="name"
                            maxlength="255"
                            required
                            value="<?= pfh($product['name']) ?>"
                        >

                    </div>

                    <div class="pf-field">

                        <label for="category_id">
                            Danh mục *
                        </label>

                        <select
                            class="pf-input"
                            name="category_id"
                            id="category_id"
                            required
                        >
                            <option value="">
                                -- Chọn danh mục --
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int)$category['id'] ?>"
                                    <?= (int)$product['category_id'] ===
                                        (int)$category['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= pfh($category['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="pf-field">

                        <label for="brand">
                            Thương hiệu
                        </label>

                        <input
                            class="pf-input"
                            type="text"
                            name="brand"
                            id="brand"
                            value="<?= pfh($product['brand']) ?>"
                        >

                    </div>

                    <div class="pf-field">

                        <label for="price">
                            Giá bán (VNĐ) *
                        </label>

                        <input
                            class="pf-input"
                            type="number"
                            name="price"
                            id="price"
                            min="0"
                            step="1"
                            required
                            value="<?= pfh($product['price']) ?>"
                        >

                    </div>

                    <div class="pf-field">

                        <label for="stock">
                            Số lượng tồn kho *
                        </label>

                        <input
                            class="pf-input"
                            type="number"
                            name="stock"
                            id="stock"
                            min="0"
                            required
                            value="<?= pfh($product['stock']) ?>"
                        >

                    </div>

                    <div class="pf-field full">

                        <label for="specs">
                            Thông số kỹ thuật
                        </label>

                        <textarea
                            class="pf-input"
                            name="specs"
                            id="specs"
                            rows="4"
                        ><?= pfh($product['specs']) ?></textarea>

                    </div>

                    <div class="pf-field full">

                        <label for="description">
                            Mô tả sản phẩm
                        </label>

                        <textarea
                            class="pf-input"
                            name="description"
                            id="description"
                            rows="5"
                        ><?= pfh($product['description']) ?></textarea>

                    </div>

                </div>

            </section>

            <hr class="pf-divider">

            <section class="pf-section">
                <h2>Ảnh sản phẩm bằng URL</h2>

                <div class="pf-field">
                    <label for="productImageUrl">
                        Dán đường dẫn ảnh *
                    </label>

                    <input
                        class="pf-input"
                        type="url"
                        name="image"
                        id="productImageUrl"
                        maxlength="2000"
                        required
                        placeholder="https://example.com/san-pham.jpg"
                        value="<?= pfh($currentImage) ?>"
                    >

                    <p class="pf-note">
                        Chỉ dùng URL ảnh công khai (HTTP/HTTPS).
                        Khi sửa có thể thay URL hoặc giữ nguyên ảnh cũ.
                    </p>

                    <img
                        id="productPreview"
                        src="<?= pfh($currentImage) ?>"
                        alt="Xem trước ảnh sản phẩm"
                        style="display:<?= $currentImage ? 'block' : 'none' ?>;max-width:240px;max-height:175px;object-fit:cover;border-radius:10px;margin-top:12px"
                    >
                </div>
            </section>

            <hr class="pf-divider">

            <label class="pf-status">

                <input
                    type="checkbox"
                    name="status"
                    value="1"
                    <?= (int)$product['status'] === 1
                        ? 'checked'
                        : '' ?>
                >

                Hiển thị sản phẩm trên cửa hàng

            </label>

            <div class="pf-actions">

                <button
                    class="pf-btn"
                    type="submit"
                    id="saveProduct"
                >
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

<!-- =====================================
     POPUP KET QUA
     ===================================== -->

<div class="pf-overlay" id="productPopup" hidden>

    <div
        class="pf-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="popupTitle"
    >

        <div class="pf-modal-icon" id="popupIcon">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <h2 id="popupTitle">
            Thành công!
        </h2>

        <p id="popupMessage">
            Đã lưu thay đổi thành công.
        </p>

        <button
            type="button"
            class="pf-btn"
            id="popupOK"
        >
            Đồng ý
        </button>

    </div>

</div>

<script>
(function() {

    /* =====================================
       BIEN VA PHAN TU
       ===================================== */

    const form = document.getElementById('productForm');

    const saveButton = document.getElementById(
        'saveProduct'
    );

    const popup = document.getElementById(
        'productPopup'
    );

    const popupTitle = document.getElementById(
        'popupTitle'
    );

    const popupMessage = document.getElementById(
        'popupMessage'
    );

    const popupIcon = document.getElementById(
        'popupIcon'
    );

    const popupOK = document.getElementById(
        'popupOK'
    );

    let saveSucceeded = false;
    let submitting = false;

    /* =====================================
       POPUP THANH CONG / THAT BAI
       ===================================== */

    function showPopup(success, message) {

        saveSucceeded = success;

        popupTitle.textContent = success
            ? 'Cập nhật thành công!'
            : 'Không thành công';

        popupMessage.textContent = message;

        popupIcon.classList.toggle(
            'error',
            !success
        );

        popupIcon.innerHTML = success
            ? '<i class="fa-solid fa-circle-check"></i>'
            : '<i class="fa-solid fa-circle-xmark"></i>';

        popup.hidden = false;

        popupOK.focus();
    }

    popupOK.addEventListener('click', function() {

        if (saveSucceeded) {
            window.location.assign('index.php');
        } else {
            popup.hidden = true;
        }

    });

    const productUrlInput = document.getElementById(
        'productImageUrl'
    );

    const productPreview = document.getElementById(
        'productPreview'
    );

    productUrlInput?.addEventListener('input', () => {
        productPreview.src = productUrlInput.value.trim();

        productPreview.style.display =
            /^https?:\/\//i.test(productUrlInput.value.trim())
                ? 'block'
                : 'none';
    });

    productPreview?.addEventListener('error', () => {
        productPreview.style.display = 'none';
    });

    /* =====================================
       LUU BANG AJAX - KHONG CHUYEN TRANG
       ===================================== */

    const oldImage = <?= json_encode(
        $isEdit
            ? (string)($record['image'] ?? '')
            : '',
        JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

    form.addEventListener('submit', async function(event) {

        event.preventDefault();

        if (submitting) {
            return;
        }

        if (!form.reportValidity()) {
            return;
        }

        submitting = true;

        saveButton.disabled = true;

        const originalHTML = saveButton.innerHTML;

        saveButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

        try {

            const response = await fetch(
                form.action || window.location.href,
                {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            const raw = await response.text();

            let data;

            try {
                data = JSON.parse(raw);
            } catch (parseError) {
                throw new Error(
                    'Máy chủ trả về phản hồi không hợp lệ. ' +
                    'Vui lòng kiểm tra phiên đăng nhập hoặc lỗi PHP.'
                );
            }

            showPopup(
                !!data.success,
                data.message ||
                'Không có thông báo từ máy chủ.'
            );

        } catch (error) {

            showPopup(
                false,
                error.message ||
                'Không thể kết nối máy chủ.'
            );

        } finally {

            submitting = false;

            saveButton.disabled = false;

            saveButton.innerHTML = originalHTML;
        }

    });

})();
</script>

</body>
</html>
