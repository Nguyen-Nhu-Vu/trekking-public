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

    // Khong de HTML/khoang trang da buffer can tro HTTP redirect.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (!headers_sent()) {
        header('Location: index.php', true, 303);
        exit;
    }

    // Du phong khi file da in ky tu truoc the mo PHP.
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=index.php">';
    echo '<title>Đang chuyển trang</title></head><body>';
    echo '<script>window.location.replace("index.php");</script>';
    echo '<a href="index.php">Quay lại danh sách sản phẩm</a>';
    echo '</body></html>';
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
            $added = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
            if ($added === false || $added < 1 || $added > 1000000) {
                throw new RuntimeException('Số lượng nhập thêm phải từ 1 đến 1.000.000.');
            }
            $pdo->beginTransaction();
            $beforeQuery = $pdo->prepare('SELECT name, stock FROM products WHERE id = ? FOR UPDATE');
            $beforeQuery->execute([$id]);
            $before = $beforeQuery->fetch(PDO::FETCH_ASSOC);
            $beforeQuery->closeCursor();
            if (!$before) {
                throw new RuntimeException('Không tìm thấy sản phẩm.');
            }
            // Cong truc tiep trong DB duoi khoa giao dich, khong doc-so-roi-ghi-de.
            $upd = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $upd->execute([$added, $id]);
            $after = (int)$before['stock'] + $added;
            $actorId = (int)($_SESSION['user_id'] ?? 0);
            $actorName = (string)($_SESSION['fullname'] ?? 'Quản lý');
            $log = $pdo->prepare('INSERT INTO admin_audit_logs
                (actor_id,actor_name,category,action,target_type,target_id,old_value,new_value)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $log->execute([
                $actorId ?: null, $actorName, 'inventory', 'Nhập thêm hàng',
                'products', (string)$id,
                (string)$before['name'].' | Tồn trước: '.(int)$before['stock'],
                'Nhập thêm: '.$added.' | Tồn sau: '.$after
            ]);
            $pdo->commit();
            productRedirect('Đã nhập thêm '.$added.' sản phẩm. Tồn kho mới: '.$after.'.');
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
        if ($pdo->inTransaction()) $pdo->rollBack();
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

$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 150, 'UTF-8');
$categoryId = max(0, (int)($_GET['category'] ?? 0));
$stockState = (string)($_GET['stock_state'] ?? 'all');
$brand = trim((string)($_GET['brand'] ?? ''));
$saleStatus = (string)($_GET['sale_status'] ?? 'all');
$priceRange = (string)($_GET['price_range'] ?? 'all');
$sort = (string)($_GET['sort'] ?? 'new');

$allowedStockStates = ['all', 'out', 'low', 'available'];
$allowedSaleStatuses = ['all', '1', '0'];
$allowedPriceRanges = ['all', 'under100', '100to500', '500to1000', '1000to3000', 'over3000'];
$sortOptions = [
    'new' => ['p.id DESC', 'Sản phẩm mới nhất'],
    'old' => ['p.id ASC', 'Sản phẩm cũ nhất'],
    'name' => ['p.name ASC', 'Tên A–Z'],
    'name_desc' => ['p.name DESC', 'Tên Z–A'],
    'price_asc' => ['p.price ASC, p.id DESC', 'Giá tăng dần'],
    'price_desc' => ['p.price DESC, p.id DESC', 'Giá giảm dần'],
    'stock_asc' => ['p.stock ASC, p.id DESC', 'Tồn kho tăng dần'],
    'stock_desc' => ['p.stock DESC, p.id DESC', 'Tồn kho giảm dần'],
];
if (!in_array($stockState, $allowedStockStates, true)) $stockState = 'all';
if (!in_array($saleStatus, $allowedSaleStatuses, true)) $saleStatus = 'all';
if (!in_array($priceRange, $allowedPriceRanges, true)) $priceRange = 'all';
if (!isset($sortOptions[$sort])) $sort = 'new';

$categories = [];
$brands = [];
$products = [];

try {
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    $brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND TRIM(brand) <> '' ORDER BY brand ASC")
        ->fetchAll(PDO::FETCH_COLUMN);
    // Chi cho phep brand ton tai trong CSDL; gia tri khac quay ve Tat ca.
    if ($brand !== '' && !in_array($brand, $brands, true)) $brand = '';

    $sql = 'SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE 1 = 1';
    $params = [];

    if ($categoryId > 0) {
        $sql .= ' AND p.category_id = ?';
        $params[] = $categoryId;
    }
    if ($search !== '') {
        $sql .= ' AND (p.name LIKE ? OR p.brand LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($brand !== '') {
        $sql .= ' AND p.brand = ?';
        $params[] = $brand;
    }
    if ($saleStatus !== 'all') {
        $sql .= ' AND p.status = ?';
        $params[] = (int)$saleStatus;
    }
    // Ba muc trang thai kho khong chong lan:
    // 0 het; 1-10 sap het; >= 11 con hang.
    switch ($stockState) {
        case 'out': $sql .= ' AND p.stock = 0'; break;
        case 'low': $sql .= ' AND p.stock BETWEEN 1 AND 10'; break;
        case 'available': $sql .= ' AND p.stock >= 11'; break;
    }
    // Khoang gia khong chong lan (VND), khong can hai o nhap so.
    switch ($priceRange) {
        case 'under100': $sql .= ' AND p.price < 100000'; break;
        case '100to500': $sql .= ' AND p.price >= 100000 AND p.price < 500000'; break;
        case '500to1000': $sql .= ' AND p.price >= 500000 AND p.price < 1000000'; break;
        case '1000to3000': $sql .= ' AND p.price >= 1000000 AND p.price < 3000000'; break;
        case 'over3000': $sql .= ' AND p.price >= 3000000'; break;
    }
    // ORDER BY chi lay tu danh sach trang thai co dinh, khong noi du lieu GET truc tiep.
    $sql .= ' ORDER BY ' . $sortOptions[$sort][0];
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
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: end;
    gap: 15px;
    padding: 20px 22px;
}
.pr-filter-field { min-width: 0; }
.pr-filter-field label {
    display: block;
    margin-bottom: 7px;
    font-weight: 700;
    font-size: 12px;
    color: #334155;
}
.pr-filter-field.search { grid-column: span 2; }
.pr-filter-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    align-items: center;
}
.pr-input {
    display: block;
    width: 100%;
    height: 42px;
    min-height: 42px;
    padding: 9px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
    background: white;
    color: #173454;
}
.pr-input:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,.08); }
@media(max-width:1150px) {
    .pr-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media(max-width:650px) {
    .pr-filter { grid-template-columns: 1fr; }
    .pr-filter-field.search { grid-column: auto; }
    .pr-filter-actions { justify-content: stretch; }
    .pr-filter-actions .pr-btn { flex:1; }
}

.pr-table-wrap {
    overflow-x: auto;
}

.pr-table {
    width: 100%;
    min-width: 1120px;
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

.pr-stock-current {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 112px;
    gap: 3px;
    padding: 8px 10px;
    border-radius: 9px;
    background: #dcfce7;
    color: #166534;
    font-size: 15px;
    font-weight: 800;
    line-height: 1.25;
    text-align: center;
}
.pr-stock-current small { font-size: 10px; font-weight: 700; white-space: nowrap; }
.pr-stock-current.low { background: #ffedd5; color: #9a3412; }
.pr-stock-current.empty { background: #fee2e2; color: #b91c1c; }
.pr-stock-form { display:flex;align-items:center;gap:9px;margin:0;white-space:nowrap; }
.pr-stock-input { width:100px;min-width:0;height:40px;padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#173454;text-align:center;font:inherit;font-size:13px; }
.pr-stock-input:focus { outline:none;border-color:#166534; }
.pr-stock-form .pr-stock-save { display:inline-flex !important;align-items:center !important;justify-content:center !important;flex:0 0 40px !important;box-sizing:border-box !important;width:40px !important;min-width:40px !important;max-width:40px !important;height:40px !important;min-height:40px !important;max-height:40px !important;margin:0 !important;padding:0 !important;border:0 !important;border-radius:8px;background:#166534;color:#fff;font-size:16px !important;line-height:1 !important;cursor:pointer;vertical-align:middle; }
.pr-stock-form .pr-stock-input { box-sizing:border-box !important;height:40px !important;min-height:40px !important;max-height:40px !important;margin:0 !important; }
.pr-stock-form .pr-stock-save i { font-size:16px !important;line-height:1 !important; }
.pr-stock-save:hover { background:#14532d; }
.pr-stock-save:disabled { opacity:.6;cursor:wait; }
.pr-actions { display:flex;gap:8px;align-items:center; }
.pr-actions form { margin:0; }

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
                Danh sách sản phẩm và chức năng nhập thêm hàng (tự cộng vào tồn kho).
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
                <select class="pr-input" name="category" id="category">
                    <option value="0">Tất cả danh mục</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= productH($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pr-filter-field">
                <label for="stock_state">Tình trạng tồn kho</label>
                <select class="pr-input" name="stock_state" id="stock_state">
                    <option value="all" <?= $stockState === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="out" <?= $stockState === 'out' ? 'selected' : '' ?>>Hết hàng (0)</option>
                    <option value="low" <?= $stockState === 'low' ? 'selected' : '' ?>>Sắp hết (1–10)</option>
                    <option value="available" <?= $stockState === 'available' ? 'selected' : '' ?>>Còn hàng (từ 11)</option>
                </select>
            </div>

            <div class="pr-filter-field search">
                <label for="q">Tìm tên hoặc thương hiệu</label>
                <input class="pr-input" type="search" name="q" id="q"
                       value="<?= productH($search) ?>" placeholder="Nhập tên sản phẩm hoặc thương hiệu...">
            </div>

            <div class="pr-filter-field">
                <label for="brand">Thương hiệu</label>
                <select class="pr-input" name="brand" id="brand">
                    <option value="">Tất cả thương hiệu</option>
                    <?php foreach ($brands as $brandOption): ?>
                        <option value="<?= productH($brandOption) ?>" <?= $brand === $brandOption ? 'selected' : '' ?>>
                            <?= productH($brandOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pr-filter-field">
                <label for="sale_status">Trạng thái bán</label>
                <select class="pr-input" name="sale_status" id="sale_status">
                    <option value="all" <?= $saleStatus === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="1" <?= $saleStatus === '1' ? 'selected' : '' ?>>Đang hiển thị</option>
                    <option value="0" <?= $saleStatus === '0' ? 'selected' : '' ?>>Đang ẩn</option>
                </select>
            </div>

            <div class="pr-filter-field">
                <label for="price_range">Khoảng giá bán (VNĐ)</label>
                <select class="pr-input" name="price_range" id="price_range">
                    <?php foreach ([
                        'all' => 'Tất cả mức giá',
                        'under100' => 'Dưới 100.000 đ',
                        '100to500' => '100.000 – dưới 500.000 đ',
                        '500to1000' => '500.000 – dưới 1.000.000 đ',
                        '1000to3000' => '1.000.000 – dưới 3.000.000 đ',
                        'over3000' => 'Từ 3.000.000 đ trở lên'
                    ] as $value => $label): ?>
                        <option value="<?= productH($value) ?>" <?= $priceRange === $value ? 'selected' : '' ?>><?= productH($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pr-filter-field">
                <label for="sort">Sắp xếp theo</label>
                <select class="pr-input" name="sort" id="sort">
                    <?php foreach ($sortOptions as $value => $data): ?>
                        <option value="<?= productH($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= productH($data[1]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pr-filter-actions">
                <button type="submit" class="pr-btn blue"><i class="fa-solid fa-filter"></i> Lọc</button>
                <a href="index.php" class="pr-btn gray">Xóa lọc</a>
            </div>
        </form>

        <div class="pr-table-wrap">
            <table class="pr-table">

                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Giá bán</th>
                        <th>Tồn hàng</th>
                        <th>Nhập hàng</th>
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
                            <?php $currentStock = (int)$p['stock']; ?>
                            <span class="pr-stock-current <?= $currentStock <= 0 ? 'empty' : ($currentStock <= 10 ? 'low' : '') ?>">
                                <?= number_format($currentStock, 0, ',', '.') ?>
                                <small><?= $currentStock <= 0 ? 'Hết hàng' : ($currentStock <= 10 ? 'Sắp hết hàng' : 'Còn hàng') ?></small>
                            </span>
                        </td>

                        <td>
                            <form method="POST" class="pr-stock-form">
                                <input type="hidden" name="csrf" value="<?= productH($csrf) ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="action" value="update_stock">
                                <input class="pr-stock-input" type="number" name="stock"
                                       min="1" max="1000000" step="1" required
                                       placeholder="Số lượng"
                                       aria-label="Số lượng nhập thêm cho <?= productH($p['name']) ?>">
                                <button class="pr-stock-save" type="submit"
                                        title="Lưu nhập kho" aria-label="Lưu nhập kho cho <?= productH($p['name']) ?>">
                                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
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
                        <td colspan="7" class="pr-empty">
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
