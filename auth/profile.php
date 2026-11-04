
<?php
ob_start();

$page_title = 'Hồ Sơ Cá Nhân - VNN Travel';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user_id = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$error = '';

function profileH($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Lấy hồ sơ hiện tại của tài khoản.
$stmt = $pdo->prepare(
    'SELECT * FROM users WHERE id = ? LIMIT 1'
);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(403);
    exit('Tài khoản không tồn tại hoặc phiên đăng nhập đã hết hạn.');
}

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim((string)($_POST['fullname'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $emergency = trim((string)($_POST['emergency_contact'] ?? ''));
    $csrf = (string)($_POST['profile_csrf'] ?? '');

    $user['fullname'] = $fullname;
    $user['phone'] = $phone;
    $user['address'] = $address;
    $user['emergency_contact'] = $emergency;

    if (!hash_equals((string)$_SESSION['profile_csrf'], $csrf)) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';

    } elseif ($fullname === '') {
        $error = 'Họ và tên không được để trống.';

    } elseif (
        mb_strlen($fullname, 'UTF-8') > 255 ||
        mb_strlen($address, 'UTF-8') > 500
    ) {
        $error = 'Họ tên hoặc địa chỉ quá dài.';

    } elseif (
        $phone !== '' &&
        (
            !preg_match('/^[0-9+().\s-]+$/', $phone) ||
            strlen($phone) > 30
        )
    ) {
        $error = 'Số điện thoại không hợp lệ.';

    } elseif (
        $emergency !== '' &&
        (
            !preg_match('/^[0-9+().\s-]+$/', $emergency) ||
            strlen($emergency) > 30
        )
    ) {
        $error = 'Số điện thoại khẩn cấp không hợp lệ.';

    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET fullname = ?,
                     phone = ?,
                     address = ?,
                     emergency_contact = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $fullname,
                $phone,
                $address,
                $emergency,
                $user_id
            ]);

            $_SESSION['fullname'] = $fullname;

            $msg = 'Cập nhật thông tin cá nhân thành công!';

        } catch (Throwable $e) {
            error_log('Profile update: ' . $e->getMessage());

            $error = 'Không thể lưu hồ sơ. Hãy kiểm tra hai cột address và emergency_contact trong bảng users.';
        }
    }
}

// Thống kê Tour đã đăng ký.
$my_tour_count = $pdo->prepare(
    'SELECT COUNT(*) FROM tour_bookings WHERE user_id = ?'
);
$my_tour_count->execute([$user_id]);
$total_tours = (int)$my_tour_count->fetchColumn();

// Thống kê đơn mua thiết bị.
$my_order_count = $pdo->prepare(
    'SELECT COUNT(*) FROM orders WHERE user_id = ?'
);
$my_order_count->execute([$user_id]);
$total_orders = (int)$my_order_count->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">
    <div style="max-width:810px;margin:0 auto;">

        <div class="grid grid-2"
             style="margin-bottom:25px;gap:18px;">

            <a href="<?= profileH(BASE_URL) ?>tours/my-tours.php"
               style="background:#fff7ed;
                      border:1px solid #fed7aa;
                      padding:20px;
                      border-radius:12px;
                      display:flex;
                      align-items:center;
                      gap:16px;
                      text-decoration:none;
                      box-shadow:0 2px 8px rgba(0,0,0,.04);">

                <div style="width:50px;
                            height:50px;
                            border-radius:50%;
                            background:#ea580c;
                            color:white;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            font-size:1.4rem;">
                    <i class="fa-solid fa-ticket"></i>
                </div>

                <div>
                    <h4 style="margin:0;
                               color:#9a3412;
                               font-size:1.05rem;">
                        Tour Tôi Đã Đăng Ký
                    </h4>

                    <span style="font-size:.85rem;
                                 color:#c2410c;
                                 font-weight:700;">
                        Đang có <?= $total_tours ?> chuyến đi
                    </span>
                </div>
            </a>

            <a href="<?= profileH(BASE_URL) ?>shop/my-orders.php"
               style="background:#f0fdf4;
                      border:1px solid #bbf7d0;
                      padding:20px;
                      border-radius:12px;
                      display:flex;
                      align-items:center;
                      gap:16px;
                      text-decoration:none;
                      box-shadow:0 2px 8px rgba(0,0,0,.04);">

                <div style="width:50px;
                            height:50px;
                            border-radius:50%;
                            background:#16a34a;
                            color:white;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            font-size:1.4rem;">
                    <i class="fa-solid fa-box-open"></i>
                </div>

                <div>
                    <h4 style="margin:0;
                               color:#166534;
                               font-size:1.05rem;">
                        Đơn Mua Thiết Bị
                    </h4>

                    <span style="font-size:.85rem;
                                 color:#15803d;
                                 font-weight:700;">
                        Đang có <?= $total_orders ?> đơn hàng
                    </span>
                </div>
            </a>

        </div>

        <div style="background:white;
                    padding:35px;
                    border-radius:12px;
                    box-shadow:var(--shadow);
                    border:1px solid #e2e8f0;">

            <h2 style="color:var(--primary);
                       margin-bottom:20px;
                       text-align:center;">
                THÔNG TIN CÁ NHÂN
            </h2>

            <p style="color:#64748b;
                      text-align:center;
                      margin:0 0 24px;">
                Thông tin này giúp điền nhanh phiếu đặt tour
                và đơn mua hàng.
            </p>

            <?php if ($msg): ?>
                <div class="alert alert-success" role="status">
                    <?= profileH($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error" role="alert">
                    <?= profileH($error) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">

                <input type="hidden"
                       name="profile_csrf"
                       value="<?= profileH($_SESSION['profile_csrf']) ?>">

                <div class="form-group">
                    <label>Địa chỉ Email (Cố định)</label>

                    <input type="email"
                           class="form-control"
                           value="<?= profileH($user['email'] ?? '') ?>"
                           disabled
                           style="background:#f1f5f9;">
                </div>

                <div class="form-group">
                    <label for="p-fullname">Họ và tên *</label>

                    <input id="p-fullname"
                           type="text"
                           name="fullname"
                           class="form-control"
                           maxlength="255"
                           value="<?= profileH($user['fullname'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="p-phone">
                        Số điện thoại liên lạc
                    </label>

                    <input id="p-phone"
                           type="tel"
                           name="phone"
                           class="form-control"
                           maxlength="30"
                           value="<?= profileH($user['phone'] ?? '') ?>"
                           placeholder="09xxxxxxxx">
                </div>

                <div class="form-group">
                    <label for="p-address">
                        Địa chỉ giao hàng
                    </label>

                    <textarea id="p-address"
                              name="address"
                              class="form-control"
                              rows="3"
                              maxlength="500"
                              placeholder="Số nhà, đường, phường/xã, tỉnh/thành phố"><?= profileH($user['address'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="p-emergency">
                        SĐT liên hệ khẩn cấp (dùng khi đăng ký tour)
                    </label>

                    <input id="p-emergency"
                           type="tel"
                           name="emergency_contact"
                           class="form-control"
                           maxlength="30"
                           value="<?= profileH($user['emergency_contact'] ?? '') ?>"
                           placeholder="Số điện thoại người thân">
                </div>

                <div class="form-group">
                    <label>Vai trò tài khoản</label>

                    <input type="text"
                           class="form-control"
                           value="<?= profileH(strtoupper((string)($user['role'] ?? ''))) ?>"
                           disabled
                           style="background:#f1f5f9;font-weight:bold;">
                </div>

                <button type="submit"
                        class="btn btn-primary"
                        style="width:100%;
                               padding:12px;
                               margin-top:10px;">
                    LƯU THAY ĐỔI
                </button>

            </form>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
