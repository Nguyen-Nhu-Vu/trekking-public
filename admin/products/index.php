<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = 'products';
$page_title = 'Quản lý Dụng cụ & Tồn kho - VNN Travel';

function productH($value): string {
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

$popupMessage = $_SESSION['product_notice'] ?? '';
$popupType = 'success';
unset($_SESSION['product_notice']);

function productRedirect(string $message): void {
    $_SESSION['product_notice'] = $message;
    header('Location: index.php', true, 303);
    exit;
}

// =====================================
// XU LY CAC THAO TAC
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            (string)$csrf,
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
            );
        }

        $action = (string)($_POST['action'] ?? '');
        $id = filter_var(
            $_POST['product_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id <= 0) {
            throw new RuntimeException(
                'Sản phẩm không hợp lệ.'
            );
        }

        if ($action === 'update_stock') {
            $stock = filter_var(
                $_POST['stock'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($stock === false || $stock < 0) {
                throw new RuntimeException(
                    'Số lượng tồn kho phải từ 0 trở lên.'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE products
                SET stock = ?
                WHERE id = ?
            ");

            $stmt->execute([$stock, $id]);

            productRedirect(
                'Đã cập nhật số lượng tồn kho thành công.'
            );
        }

        if ($action === 'delete') {
            // Neu san pham da co trong don hang,
            // khoa ngoai co the khong cho xoa.
            $stmt = $pdo->prepare("
                DELETE FROM products
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Không tìm thấy sản phẩm cần xóa.'
                );
            }

            productRedirect(
                'Đã xóa sản phẩm thành công.'
            );
        }

        throw new RuntimeException(
            'Thao tác không hợp lệ.'
        );

    } catch (Throwable $e) {
        error_log('Product index: ' . $e->getMessage());

        $popupType = 'error';
        $popupMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể thực hiện thao tác. Sản phẩm có thể đã liên kết với đơn hàng.';
    }
}

// =====================================
// LAY DANH MUC VA DANH SACH SAN PHAM
// =====================================

$categories = [];
$products = [];

$search = trim((string)($_GET['q'] ?? ''));
$categoryId = (int)($_GET['category'] ?? 0);

try {
    $categories = $pdo->query("
        SELECT id, name
        FROM categories
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $sql = "
        SELECT
            p.*,
            c.name AS category_name
        FROM products p
        LEFT JOIN categories c
            ON c.id = p.category_id
        WHERE 1 = 1
    ";

    $params = [];

    if ($categoryId > 0) {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }

    if ($search !== '') {
        $sql .= "
            AND (
                p.name LIKE ?
                OR p.brand LIKE ?
            )
        ";

        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Load products: ' . $e->getMessage());
    $popupType = 'error';
    $popupMessage = 'Không thể tải danh sách sản phẩm.';
}

$totalStock = 0;

foreach ($products as $p) {
    $totalStock += (int)$p['stock'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= productH($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
* {
    box-sizing: border-box;
}

.pr-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    padding: 24px;
    background: #f1f5f9;
    color: #152f51;
    font-size: 14px;
}

.pr-header,
.pr-panel,
.pr-stat {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

.pr-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    padding: 22px;
    margin-bottom: 18px;
}

.pr-header h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #102e52;
}

.pr-header p {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 13px;
}

.pr-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 11px 15px;
    border: 0;
    border-radius: 9px;
    background: #166534;
    color: white !important;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
}

.pr-btn:hover {
    background: #14532d;
}

.pr-btn.blue {
    background: #1d4ed8;
}

.pr-btn.gray {
    background: #e2e8f0;
    color: #334155 !important;
}

.pr-btn.red {
    background: #dc2626;
}

.pr-btn.small {
    min-height: 36px;
    padding: 8px 11px;
    font-size: 12px;
}

.pr-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 18px;
}

.pr-stat {
    padding: 19px;
}

.pr-stat span {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}

.pr-stat strong {
    display: block;
    margin-top: 9px;
    font-size: 25px;
    color: #102e52;
}

.pr-panel {
    overflow: hidden;
}

.pr-panel-title {
    padding: 20px 22px;
    border-bottom: 1px solid #e2e8f0;
}

.pr-panel-title h2 {
    margin: 0;
    font-size: 18px;
    color: #102e52;
}

.pr-filter {
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
    padding: 20px 22px;
}

.pr-filter-field {
    flex: 1;
    min-width: 180px;
}

.pr-filter-field label {
    display: block;
    margin-bottom: 7px;
    font-weight: 700;
    font-size: 12px;
}

.pr-input {
    display: block;
    width: 100%;
    min-height: 41px;
    padding: 9px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
    background: white;
    color: #173454;
}

.pr-table-wrap {
    overflow-x: auto;
}

.pr-table {
    width: 100%;
    min-width: 920px;
    border-collapse: collapse;
}

.pr-table th,
.pr-table td {
    padding: 13px 14px;
    text-align: left;
    vertical-align: middle;
    border-top: 1px solid #e2e8f0;
    font-size: 13px;
}

.pr-table th {
    background: #f8fafc;
    color: #102e52;
    font-weight: 800;
    white-space: nowrap;
}

.pr-table tbody tr:hover {
    background: #f8fafc;
}

.pr-image {
    width: 63px;
    height: 67px;
    object-fit: cover;
    border-radius: 9px;
    background: #e2e8f0;
}

.pr-name {
    color: #102e52;
    font-size: 13px;
    font-weight: 800;
}

.pr-brand {
    color: #64748b;
    font-size: 12px;
    margin-top: 5px;
}

.pr-price {
    color: #ea580c;
    font-weight: 800;
    white-space: nowrap;
}

.pr-stock {
    display: flex;
    align-items: center;
    gap: 7px;
}

.pr-stock .pr-input {
    width: 85px;
}

.pr-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pr-actions form,
.pr-stock {
    margin: 0;
}

.pr-empty {
    text-align: center !important;
    padding: 30px !important;
    color: #64748b;
}

/* POPUP THONG BAO VA XAC NHAN */

.pr-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15,23,42,.56);
    padding: 20px;
}

.pr-overlay[hidden] {
    display: none !important;
}

.pr-modal {
    width: 100%;
    max-width: 410px;
    padding: 28px;
    border-radius: 17px;
    background: white;
    text-align: center;
    box-shadow: 0 22px 70px rgba(0,0,0,.2);
}

.pr-modal-icon {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: #dcfce7;
    color: #166534;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 28px;
}

.pr-modal-icon.error {
    background: #fee2e2;
    color: #dc2626;
}

.pr-modal-icon.warning {
    background: #fff7ed;
    color: #ea580c;
}

.pr-modal h2 {
    margin: 0 0 10px;
    font-size: 20px;
    color: #102e52;
}

.pr-modal p {
    margin: 0 0 22px;
    line-height: 1.7;
    color: #64748b;
}

.pr-modal-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

@media(max-width:650px) {
    .pr-page {
        padding: 12px;
    }

    .pr-header,
    .pr-panel-title,
    .pr-filter {
        padding: 15px;
    }

    .pr-stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="admin-body">

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main pr-page">

    <div class="pr-header">
        <div>
            <h1>
                <i class="fa-solid fa-boxes-stacked"></i>
                QUẢN LÝ DỤNG CỤ & TỒN KHO
            </h1>
            <p>
                Danh sách sản phẩm trekking và số lượng tồn kho.
            </p>
        </div>

        <a href="form.php" class="pr-btn">
            <i class="fa-solid fa-plus"></i>
            Thêm sản phẩm mới
        </a>
    </div>

    <div class="pr-stats">
        <div class="pr-stat">
            <span>Sản phẩm đang hiển thị</span>
            <strong><?= count($products) ?></strong>
        </div>

        <div class="pr-stat">
            <span>Tổng số lượng tồn của danh sách</span>
            <strong><?= number_format($totalStock, 0, ',', '.') ?></strong>
        </div>
    </div>

    <section class="pr-panel">

        <div class="pr-panel-title">
            <h2>Danh sách hàng hóa</h2>
        </div>

        <form method="GET" class="pr-filter">

            <div class="pr-filter-field">
                <label for="category">Danh mục</label>
                <select class="pr-input"
                        name="category"
                        id="category">
                    <option value="0">Tất cả danh mục</option>

                    <?php foreach ($categories as $c): ?>
                        <option
                            value="<?= (int)$c['id'] ?>"
                            <?= $categoryId === (int)$c['id']
                                ? 'selected' : '' ?>
                        >
                            <?= productH($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pr-filter-field">
                <label for="q">Tìm kiếm sản phẩm</label>
                <input class="pr-input"
                       type="search"
                       name="q"
                       id="q"
                       value="<?= productH($search) ?>"
                       placeholder="Tên hoặc thương hiệu...">
            </div>

            <button type="submit" class="pr-btn blue">
                <i class="fa-solid fa-filter"></i>
                Lọc
            </button>

            <a href="index.php" class="pr-btn gray">
                Xóa lọc
            </a>

        </form>

        <div class="pr-table-wrap">
            <table class="pr-table">

                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Giá bán</th>
                        <th>Tồn kho</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($products as $p): ?>

                    <tr>
                        <td>
                            <img class="pr-image"
                                 src="<?= productH($p['image']) ?>"
                                 alt="<?= productH($p['name']) ?>"
                                 loading="lazy">
                        </td>

                        <td>
                            <div class="pr-name">
                                <?= productH($p['name']) ?>
                            </div>
                            <div class="pr-brand">
                                <?= productH($p['brand'] ?? '') ?>
                            </div>
                        </td>

                        <td>
                            <?= productH($p['category_name'] ?? '—') ?>
                        </td>

                        <td class="pr-price">
                            <?= number_format(
                                (float)$p['price'],
                                0,
                                ',',
                                '.'
                            ) ?> đ
                        </td>

                        <td>
                            <form method="POST" class="pr-stock">
                                <input type="hidden"
                                       name="csrf"
                                       value="<?= productH($csrf) ?>">

                                <input type="hidden"
                                       name="product_id"
                                       value="<?= (int)$p['id'] ?>">

                                <input class="pr-input"
                                       type="number"
                                       name="stock"
                                       min="0"
                                       value="<?= (int)$p['stock'] ?>"
                                       required>

                                <button
                                    class="pr-btn small"
                                    type="submit"
                                    name="action"
                                    value="update_stock"
                                    title="Lưu tồn kho"
                                >
                                    <i class="fa-solid fa-floppy-disk"></i>
                                </button>
                            </form>
                        </td>

                        <td>
                            <div class="pr-actions">

                                <a href="form.php?id=<?= (int)$p['id'] ?>"
                                   class="pr-btn blue small">
                                    <i class="fa-solid fa-pen"></i>
                                    Sửa
                                </a>

                                <form method="POST"
                                      class="product-delete-form">

                                    <input type="hidden"
                                           name="csrf"
                                           value="<?= productH($csrf) ?>">

                                    <input type="hidden"
                                           name="product_id"
                                           value="<?= (int)$p['id'] ?>">

                                    <input type="hidden"
                                           name="action"
                                           value="delete">

                                    <button type="submit"
                                            class="pr-btn red small">
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
                        <td colspan="6" class="pr-empty">
                            Không có sản phẩm phù hợp.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>
        </div>

    </section>
</main>

<!-- POPUP KET QUA -->

<div class="pr-overlay"
     id="resultPopup"
     <?= $popupMessage === '' ? 'hidden' : '' ?>>

    <div class="pr-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="resultTitle">

        <div class="pr-modal-icon <?= $popupType === 'error' ? 'error' : '' ?>">
            <i class="fa-solid <?= $popupType === 'error'
                ? 'fa-circle-xmark'
                : 'fa-circle-check' ?>"></i>
        </div>

        <h2 id="resultTitle">
            <?= $popupType === 'error'
                ? 'Không thành công'
                : 'Thành công!' ?>
        </h2>

        <p><?= productH($popupMessage) ?></p>

        <button type="button"
                class="pr-btn"
                id="closeResult">
            Đồng ý
        </button>
    </div>
</div>

<!-- POPUP XAC NHAN XOA -->

<div class="pr-overlay" id="deletePopup" hidden>

    <div class="pr-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="deleteTitle">

        <div class="pr-modal-icon warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2 id="deleteTitle">
            Xác nhận xóa sản phẩm
        </h2>

        <p>
            Bạn có chắc muốn xóa sản phẩm này?
            Thao tác không thể hoàn tác.
        </p>

        <div class="pr-modal-actions">
            <button type="button"
                    class="pr-btn gray"
                    id="cancelDelete">
                Quay lại
            </button>

            <button type="button"
                    class="pr-btn red"
                    id="confirmDelete">
                Xác nhận xóa
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    const result = document.getElementById('resultPopup');
    const deletePopup = document.getElementById('deletePopup');

    document.getElementById('closeResult')
        ?.addEventListener('click', function() {
            result.hidden = true;
        });

    let pendingForm = null;

    document.querySelectorAll('.product-delete-form')
        .forEach(function(form) {

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                pendingForm = form;
                deletePopup.hidden = false;
            });

        });

    document.getElementById('cancelDelete')
        ?.addEventListener('click', function() {
            deletePopup.hidden = true;
            pendingForm = null;
        });

    document.getElementById('confirmDelete')
        ?.addEventListener('click', function() {

            if (!pendingForm) return;

            this.disabled = true;
            this.textContent = 'Đang xóa...';

            pendingForm.submit();
        });

    result?.addEventListener('click', function(event) {
        if (event.target === result) {
            result.hidden = true;
        }
    });

    deletePopup?.addEventListener('click', function(event) {
        if (event.target === deletePopup) {
            deletePopup.hidden = true;
            pendingForm = null;
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            if (result) result.hidden = true;
            if (deletePopup) deletePopup.hidden = true;
            pendingForm = null;
        }
    });
})();
</script>

</body>
</html>
