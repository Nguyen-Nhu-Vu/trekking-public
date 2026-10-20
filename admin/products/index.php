<?php
// trekking/admin/products/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireManager();

$msg = '';
$error = '';

// 1. Cập nhật tồn kho nhanh trực tiếp từ bảng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock_fast'])) {
    $pid = intval($_POST['product_id']);
    $new_stock = intval($_POST['stock']);
    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$new_stock, $pid]);
    $msg = "Cập nhật tồn kho thành công!";
}

// 2. Xử lý thêm sản phẩm mới (Tải ảnh trực tiếp từ máy tính)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = sanitize($_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $brand = sanitize($_POST['brand'] ?? 'TrekGear');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $specs = sanitize($_POST['specs'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    // Ảnh mặc định nếu người dùng không chọn file
    $image_path = 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800';

    // Xử lý upload file từ máy tính
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image_file']['tmp_name'];
        $file_name = $_FILES['image_file']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($ext, $allowed)) {
            $new_name = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_dir = __DIR__ . '/../../uploads/products/';
            
            // Tự động tạo thư mục uploads/products nếu chưa có
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $image_path = BASE_URL . 'uploads/products/' . $new_name;
            } else {
                $error = "Không thể lưu file ảnh vào thư mục uploads/products!";
            }
        } else {
            $error = "Định dạng file không hợp lệ (chỉ chấp nhận JPG, PNG, WEBP, GIF)!";
        }
    }

    if (empty($name) || $category_id <= 0 || $price <= 0) {
        $error = "Vui lòng nhập đầy đủ tên sản phẩm, danh mục và giá!";
    } elseif (empty($error)) {
        $stmt = $pdo->prepare("INSERT INTO products (name, category_id, brand, price, stock, image, specs, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $category_id, $brand, $price, $stock, $image_path, $specs, $description]);
        $msg = "Đã thêm sản phẩm mới và tải ảnh lên thành công!";
    }
}

// 3. Xóa sản phẩm
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$del_id]);
    $msg = "Đã xóa sản phẩm thành công!";
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();

$current_page = 'products';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Dụng Cụ & Tồn Kho - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">

    <!-- Thanh sidebar Accordion dùng chung -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div><strong>QUẢN LÝ DỤNG CỤ VÀ THỐNG KÊ TỒN KHO</strong></div>
        </div>

        <div class="admin-content">
            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <!-- Form thêm sản phẩm kèm tải file từ máy tính -->
            <div style="background:white; padding:25px; border-radius:10px; box-shadow:var(--shadow); margin-bottom:30px;">
                <h4 style="margin-bottom:15px; color:#1e293b;"><i class="fa-solid fa-plus-circle"></i> Thêm Dụng Cụ Mới Vào Kho</h4>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_product" value="1">
                    <div class="grid grid-3">
                        <div class="form-group">
                            <label>Tên sản phẩm *</label>
                            <input type="text" name="name" class="form-control" required placeholder="Balo, Giày leo núi...">
                        </div>
                        <div class="form-group">
                            <label>Danh mục *</label>
                            <select name="category_id" class="form-control" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Thương hiệu</label>
                            <input type="text" name="brand" class="form-control" value="TrekGear">
                        </div>
                    </div>
                    <div class="grid grid-3">
                        <div class="form-group">
                            <label>Giá bán (VNĐ) *</label>
                            <input type="number" name="price" class="form-control" required placeholder="1500000">
                        </div>
                        <div class="form-group">
                            <label>Số lượng tồn kho ban đầu *</label>
                            <input type="number" name="stock" class="form-control" value="20" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-upload"></i> Chọn ảnh từ máy tính</label>
                            <input type="file" name="image_file" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label>Thông số kỹ thuật</label>
                            <textarea name="specs" class="form-control" rows="2" placeholder="Chất liệu chống nước, dung tích..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Mô tả chi tiết</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Đặc điểm nổi bật..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Thêm Vào Kho</button>
                </form>
            </div>

            <!-- Bảng quản lý tồn kho có thể chỉnh sửa trực tiếp -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>Tên Sản Phẩm</th>
                            <th>Danh Mục</th>
                            <th>Giá Bán</th>
                            <th style="width:170px;">Chỉnh Sửa Tồn Kho</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><img src="<?= htmlspecialchars($p['image']) ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px;"></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                                        <small style="color:var(--gray);"><?= htmlspecialchars($p['brand']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($p['category_name']) ?></td>
                                    <td style="color:var(--accent); font-weight:bold;"><?= format_price($p['price']) ?></td>
                                    <td>
                                        <form action="" method="POST" style="display:flex; gap:6px; align-items:center;">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="number" name="stock" value="<?= $p['stock'] ?>" class="form-control" style="width:80px; padding:4px 8px; text-align:center;">
                                            <button type="submit" name="update_stock_fast" class="btn btn-primary btn-sm" title="Lưu số lượng tồn kho"><i class="fa-solid fa-check"></i></button>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="../../product-detail.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i></a>
                                        <a href="index.php?delete=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?');"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:35px; color:#64748b;">Chưa có sản phẩm nào trong kho.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>