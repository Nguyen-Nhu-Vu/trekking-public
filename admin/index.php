<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$current_page = 'products';
$page_title = 'Quản lý kho và danh mục - VNN Travel';

function wh_h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function wh_redirect($message)
{
    $_SESSION['warehouse_notice'] = $message;

    header('Location: index.php', true, 303);
    exit;
}

function wh_category_exists(PDO $pdo, int $id)
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM categories WHERE id = ?"
    );

    $stmt->execute([$id]);

    return (int) $stmt->fetchColumn() > 0;
}

if (empty($_SESSION['warehouse_csrf'])) {
    $_SESSION['warehouse_csrf'] = bin2hex(random_bytes(16));
}

$csrf = $_SESSION['warehouse_csrf'];
$error = '';

/*
|--------------------------------------------------------------------------
| XỬ LÝ THÊM, SỬA, XÓA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $postedCsrf = (string) ($_POST['csrf'] ?? '');

        if (!hash_equals($csrf, $postedCsrf)) {
            throw new RuntimeException(
                'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'
            );
        }

        $action = (string) ($_POST['action'] ?? '');

        // THÊM DANH MỤC
        if ($action === 'add_category') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            if ($name === '') {
                throw new RuntimeException(
                    'Vui lòng nhập tên danh mục.'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO categories (name, description)
                VALUES (?, ?)
            ");

            $stmt->execute([$name, $description]);

            wh_redirect('Đã thêm danh mục thành công.');
        }

        // SỬA DANH MỤC
        if ($action === 'update_category') {
            $id = (int) ($_POST['category_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            if ($id <= 0 || $name === '') {
                throw new RuntimeException(
                    'Thông tin danh mục không hợp lệ.'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE categories
                SET name = ?, description = ?
                WHERE id = ?
            ");

            $stmt->execute([$name, $description, $id]);

            wh_redirect('Đã cập nhật danh mục.');
        }

        // XÓA DANH MỤC TRỐNG
        if ($action === 'delete_category') {
            $id = (int) ($_POST['category_id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException(
                    'Danh mục không hợp lệ.'
                );
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM products
                WHERE category_id = ?
            ");

            $stmt->execute([$id]);
            $count = (int) $stmt->fetchColumn();

            if ($count > 0) {
                throw new RuntimeException(
                    'Danh mục còn ' . $count .
                    ' sản phẩm. Hãy chuyển sản phẩm ' .
                    'sang danh mục khác trước khi xóa.'
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM categories
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            wh_redirect('Đã xóa danh mục trống.');
        }

        // CẬP NHẬT TỒN KHO VÀ DANH MỤC
        if ($action === 'update_product') {
            $id = (int) ($_POST['product_id'] ?? 0);
            $categoryId = (int) ($_POST['category_id'] ?? 0);

            $stock = filter_var(
                $_POST['stock'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                $id <= 0 ||
                $categoryId <= 0 ||
                $stock === false ||
                $stock < 0
            ) {
                throw new RuntimeException(
                    'Số lượng hoặc danh mục không hợp lệ.'
                );
            }

            if (!wh_category_exists($pdo, $categoryId)) {
                throw new RuntimeException(
                    'Danh mục được chọn không tồn tại.'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE products
                SET stock = ?, category_id = ?
                WHERE id = ?
            ");

            $stmt->execute([$stock, $categoryId, $id]);

            wh_redirect('Đã cập nhật tồn kho thành công.');
        }

        // THÊM SẢN PHẨM
        if ($action === 'add_product') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $brand = trim((string) ($_POST['brand'] ?? ''));
            $description = trim(
                (string) ($_POST['description'] ?? '')
            );
            $specs = trim((string) ($_POST['specs'] ?? ''));

            $categoryId = (int) ($_POST['category_id'] ?? 0);

            $price = filter_var(
                $_POST['price'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            $stock = filter_var(
                $_POST['stock'] ?? null,
                FILTER_VALIDATE_INT
            );

            $image = trim((string) ($_POST['image_url'] ?? ''));

            if (
                $name === '' ||
                $categoryId <= 0 ||
                $price === false ||
                $price < 0 ||
                $stock === false ||
                $stock < 0
            ) {
                throw new RuntimeException(
                    'Vui lòng nhập thông tin sản phẩm hợp lệ.'
                );
            }

            if (!wh_category_exists($pdo, $categoryId)) {
                throw new RuntimeException(
                    'Danh mục không tồn tại.'
                );
            }

            if ($image === '') {
                $image = '/assets/images/no-image.jpg';
            }

            $isHttps = filter_var(
                $image,
                FILTER_VALIDATE_URL
            ) && parse_url($image, PHP_URL_SCHEME) === 'https';

            $isLocal = preg_match(
                '#^/(?!/)[a-zA-Z0-9_./-]+$#',
                $image
            );

            if (!$isHttps && !$isLocal) {
                throw new RuntimeException(
                    'Đường dẫn ảnh phải là HTTPS hoặc đường dẫn nội bộ.'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO products
                (
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $name,
                $categoryId,
                $brand,
                $price,
                $stock,
                $image,
                $specs,
                $description
            ]);

            wh_redirect('Đã thêm sản phẩm thành công.');
        }

        // XÓA SẢN PHẨM
        if ($action === 'delete_product') {
            $id = (int) ($_POST['product_id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException(
                    'Sản phẩm không hợp lệ.'
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM products
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            wh_redirect('Đã xóa sản phẩm thành công.');
        }

        throw new RuntimeException(
            'Thao tác không được hỗ trợ.'
        );

    } catch (Throwable $e) {
        error_log('Warehouse: ' . $e->getMessage());

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể cập nhật dữ liệu. ' .
              'Sản phẩm có thể đang liên kết với đơn hàng.';
    }
}

/*
|--------------------------------------------------------------------------
| THÔNG BÁO
|--------------------------------------------------------------------------
*/

$notice = $_SESSION['warehouse_notice'] ?? '';

unset($_SESSION['warehouse_notice']);

/*
|--------------------------------------------------------------------------
| LẤY DANH MỤC
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        c.id,
        c.name,
        c.description,
        COUNT(p.id) AS item_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.description
    ORDER BY c.name ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| LỌC SẢN PHẨM
|--------------------------------------------------------------------------
*/

$categoryFilter = max(0, (int) ($_GET['category'] ?? 0));
$search = trim((string) ($_GET['q'] ?? ''));

$sql = "
    SELECT
        p.*,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE 1 = 1
";

$params = [];

if ($categoryFilter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.brand LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= wh_h($page_title) ?></title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/admin.css"
    >

    <style>
        .wh-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 3px 14px rgba(15, 23, 42, .05);
        }

        .wh-card h2 {
            font-size: 20px;
            color: #0f172a;
            margin: 0 0 20px;
        }

        .wh-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .wh-field {
            min-width: 0;
        }

        .wh-field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
            color: #334155;
        }

        .wh-field input,
        .wh-field select,
        .wh-field textarea,
        .wh-table input,
        .wh-table select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
            font: inherit;
            background: white;
            color: #0f172a;
        }

        .wh-field textarea {
            min-height: 85px;
            resize: vertical;
        }

        .wh-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 15px;
            border: none;
            border-radius: 9px;
            background: #166534;
            color: white;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }

        .wh-btn:hover {
            background: #15803d;
            color: white;
        }

        .wh-btn.danger {
            background: #dc2626;
        }

        .wh-btn.light {
            background: #e2e8f0;
            color: #0f172a;
        }

        .wh-alert {
            padding: 15px;
            margin-bottom: 18px;
            border-radius: 10px;
            background: #dcfce7;
            color: #166534;
        }

        .wh-alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .wh-toolbar {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 13px;
            margin-bottom: 20px;
        }

        .wh-toolbar .wh-field {
            flex: 1;
            min-width: 170px;
        }

        .wh-category-row {
            display: grid;
            grid-template-columns:
                minmax(150px, 1fr)
                minmax(180px, 2fr)
                auto auto;
            gap: 10px;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding: 12px 0;
        }

        .wh-category-row input {
            min-width: 0;
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .wh-scroll {
            max-width: 100%;
            overflow-x: auto;
        }

        .wh-table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        .wh-table th,
        .wh-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        .wh-table th {
            background: #f8fafc;
            font-weight: 800;
            color: #0f172a;
            font-size: 13px;
        }

        .wh-table tr:hover td {
            background: #f8fafc;
        }

        .wh-image {
            width: 58px;
            height: 58px;
            object-fit: cover;
            border-radius: 8px;
            background: #f1f5f9;
        }

        .wh-product-name {
            font-weight: 800;
            color: #0f172a;
            line-height: 1.5;
        }

        .wh-muted {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        .wh-pill {
            display: inline-block;
            background: #eff6ff;
            color: #0369a1;
            font-size: 12px;
            font-weight: 700;
            border-radius: 20px;
            padding: 5px 9px;
        }

        .wh-price {
            color: #ea580c;
            font-weight: 800;
            white-space: nowrap;
        }

        .wh-inline {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wh-inline input {
            max-width: 90px;
        }

        .wh-inline select {
            min-width: 150px;
        }

        .wh-note {
            font-size: 13px;
            line-height: 1.7;
            color: #64748b;
            margin-bottom: 20px;
        }

        @media (max-width: 1000px) {
            .wh-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .wh-category-row {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 650px) {
            .wh-grid,
            .wh-category-row {
                grid-template-columns: 1fr;
            }

            .wh-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body class="admin-body">

    <?php
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>

    <main class="admin-main">

        <div class="admin-topbar">
            <strong>
                <i class="fa-solid fa-warehouse"></i>
                QUẢN LÝ DỤNG CỤ, TỒN KHO & DANH MỤC
            </strong>
        </div>

        <div class="admin-content">

            <?php if ($notice): ?>
                <div class="wh-alert" role="status">
                    <?= wh_h($notice) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="wh-alert error" role="alert">
                    <?= wh_h($error) ?>
                </div>
            <?php endif; ?>

            <!-- THÊM SẢN PHẨM -->

            <section class="wh-card">

                <h2>
                    <i class="fa-solid fa-plus-circle"></i>
                    Thêm sản phẩm mới
                </h2>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= wh_h($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="add_product"
                    >

                    <div class="wh-grid">

                        <div class="wh-field">
                            <label>Tên sản phẩm *</label>

                            <input
                                name="name"
                                type="text"
                                required
                            >
                        </div>

                        <div class="wh-field">
                            <label>Danh mục *</label>

                            <select name="category_id" required>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>">
                                        <?= wh_h($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="wh-field">
                            <label>Thương hiệu</label>
                            <input name="brand" type="text">
                        </div>

                        <div class="wh-field">
                            <label>Giá bán (VNĐ) *</label>

                            <input
                                name="price"
                                type="number"
                                min="0"
                                step="1000"
                                required
                            >
                        </div>

                        <div class="wh-field">
                            <label>Số lượng tồn kho *</label>

                            <input
                                name="stock"
                                type="number"
                                min="0"
                                value="0"
                                required
                            >
                        </div>

                        <div class="wh-field">
                            <label>URL ảnh sản phẩm</label>

                            <input
                                name="image_url"
                                type="text"
                                placeholder="https://... hoặc /uploads/..."
                            >
                        </div>

                        <div class="wh-field">
                            <label>Thông số kỹ thuật</label>
                            <textarea name="specs"></textarea>
                        </div>

                        <div class="wh-field">
                            <label>Mô tả sản phẩm</label>
                            <textarea name="description"></textarea>
                        </div>

                    </div>

                    <button
                        type="submit"
                        class="wh-btn"
                        style="margin-top:20px"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Thêm vào kho
                    </button>

                </form>

            </section>

            <!-- DANH MỤC HÀNG -->

            <section class="wh-card">

                <h2>
                    <i class="fa-solid fa-layer-group"></i>
                    Quản lý danh mục hàng
                </h2>

                <p class="wh-note">
                    Quản lý toàn bộ danh mục tại đây.
                    Danh mục có sản phẩm sẽ không thể xóa.
                </p>

                <form method="POST" class="wh-toolbar">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= wh_h($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="add_category"
                    >

                    <div class="wh-field">
                        <label>Tên danh mục mới</label>

                        <input
                            name="name"
                            placeholder="Nhập tên danh mục"
                            required
                        >
                    </div>

                    <div class="wh-field">
                        <label>Mô tả</label>
                        <input name="description">
                    </div>

                    <button class="wh-btn" type="submit">
                        <i class="fa-solid fa-plus"></i>
                        Thêm danh mục
                    </button>

                </form>

                <?php foreach ($categories as $c): ?>

                    <?php
                    $categoryForm = 'cat-' . (int) $c['id'];
                    ?>

                    <div class="wh-category-row">

                        <input
                            form="<?= wh_h($categoryForm) ?>"
                            name="name"
                            value="<?= wh_h($c['name']) ?>"
                            aria-label="Tên danh mục"
                            required
                        >

                        <input
                            form="<?= wh_h($categoryForm) ?>"
                            name="description"
                            value="<?= wh_h($c['description'] ?? '') ?>"
                            aria-label="Mô tả danh mục"
                        >

                        <form
                            id="<?= wh_h($categoryForm) ?>"
                            method="POST"
                        >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= wh_h($csrf) ?>"
                            >

                            <input
                                type="hidden"
                                name="category_id"
                                value="<?= (int) $c['id'] ?>"
                            >

                            <button
                                class="wh-btn"
                                name="action"
                                value="update_category"
                            >
                                <i class="fa-solid fa-save"></i>
                                Lưu (<?= (int) $c['item_count'] ?> SP)
                            </button>

                        </form>

                        <form
                            method="POST"
                            onsubmit="return confirm('Bạn muốn xóa danh mục này?');"
                        >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= wh_h($csrf) ?>"
                            >

                            <input
                                type="hidden"
                                name="category_id"
                                value="<?= (int) $c['id'] ?>"
                            >

                            <button
                                class="wh-btn danger"
                                name="action"
                                value="delete_category"
                            >
                                <i class="fa-solid fa-trash"></i>
                                Xóa
                            </button>

                        </form>

                    </div>

                <?php endforeach; ?>

            </section>

            <!-- BẢNG KHO HÀNG -->

            <section class="wh-card">

                <h2>
                    <i class="fa-solid fa-boxes-stacked"></i>
                    Danh sách sản phẩm và tồn kho
                </h2>

                <form method="GET" class="wh-toolbar">

                    <div class="wh-field">
                        <label>Lọc theo danh mục</label>

                        <select name="category">

                            <option value="0">
                                Tất cả danh mục
                            </option>

                            <?php foreach ($categories as $c): ?>

                                <option
                                    value="<?= (int) $c['id'] ?>"
                                    <?= $categoryFilter === (int) $c['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= wh_h($c['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="wh-field">
                        <label>Tìm kiếm sản phẩm</label>

                        <input
                            name="q"
                            type="search"
                            value="<?= wh_h($search) ?>"
                            placeholder="Tên hoặc thương hiệu"
                        >
                    </div>

                    <button class="wh-btn" type="submit">
                        <i class="fa-solid fa-filter"></i>
                        Lọc
                    </button>

                    <a href="index.php" class="wh-btn light">
                        Xóa lọc
                    </a>

                </form>

                <p class="wh-note">
                    Đang hiển thị
                    <strong><?= count($products) ?></strong>
                    sản phẩm. Có thể thay đổi số lượng và danh mục trực tiếp.
                </p>

                <div class="wh-scroll">

                    <table class="wh-table">

                        <thead>
                            <tr>
                                <th>Ảnh</th>
                                <th>Tên sản phẩm</th>
                                <th>Danh mục</th>
                                <th>Giá bán</th>
                                <th>Số lượng / Chuyển danh mục</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($products as $p): ?>

                                <?php
                                $productId = (int) $p['id'];
                                $formId = 'stock-' . $productId;
                                ?>

                                <tr>

                                    <td>
                                        <img
                                            class="wh-image"
                                            src="<?= wh_h($p['image']) ?>"
                                            alt="<?= wh_h($p['name']) ?>"
                                            loading="lazy"
                                        >
                                    </td>

                                    <td>
                                        <div class="wh-product-name">
                                            <?= wh_h($p['name']) ?>
                                        </div>

                                        <div class="wh-muted">
                                            <?= wh_h($p['brand'] ?? '') ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="wh-pill">
                                            <?= wh_h(
                                                $p['category_name']
                                                ?? 'Chưa phân loại'
                                            ) ?>
                                        </span>
                                    </td>

                                    <td class="wh-price">
                                        <?= format_price($p['price']) ?>
                                    </td>

                                    <td>

                                        <form
                                            id="<?= wh_h($formId) ?>"
                                            method="POST"
                                            class="wh-inline"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= wh_h($csrf) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?= $productId ?>"
                                            >

                                            <input
                                                type="number"
                                                name="stock"
                                                min="0"
                                                required
                                                value="<?= (int) $p['stock'] ?>"
                                                aria-label="Số lượng tồn kho"
                                            >

                                            <select
                                                name="category_id"
                                                aria-label="Danh mục sản phẩm"
                                            >

                                                <?php foreach ($categories as $c): ?>

                                                    <option
                                                        value="<?= (int) $c['id'] ?>"
                                                        <?= (int) $p['category_id'] === (int) $c['id']
                                                            ? 'selected'
                                                            : '' ?>
                                                    >
                                                        <?= wh_h($c['name']) ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </form>

                                    </td>

                                    <td>
                                        <div class="wh-inline">

                                            <button
                                                form="<?= wh_h($formId) ?>"
                                                type="submit"
                                                class="wh-btn"
                                                name="action"
                                                value="update_product"
                                            >
                                                <i class="fa-solid fa-save"></i>
                                                Lưu
                                            </button>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Bạn chắc chắn muốn xóa sản phẩm này?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf"
                                                    value="<?= wh_h($csrf) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="product_id"
                                                    value="<?= $productId ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="wh-btn danger"
                                                    name="action"
                                                    value="delete_product"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                    Xóa
                                                </button>

                                            </form>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <?php if (!$products): ?>

                                <tr>
                                    <td
                                        colspan="6"
                                        style="text-align:center;padding:35px"
                                    >
                                        Không có sản phẩm phù hợp.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </div>

    </main>

</body>
</html>
