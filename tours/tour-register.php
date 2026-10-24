
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    }
}

$baseUrl = rtrim(BASE_URL, '/') . '/';
$qrImage = $baseUrl . 'assets/images/momo-qr-vu.jpg';

function bt_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function bt_money($v) {
    return number_format((float)$v, 0, ',', '.') . ' đ';
}

function bt_phone($v) {
    return preg_replace('/[^0-9]/', '', (string)$v);
}

function bt_token($v) {
    $v = trim((string)$v);

    if (class_exists('Transliterator')) {
        $trans = Transliterator::create('Any-Latin; Latin-ASCII');
        if ($trans) $v = $trans->transliterate($v);
    }

    $v = strtr($v, ['đ'=>'d', 'Đ'=>'D']);
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($v));
}

function bt_booking_code(PDO $pdo) {
    $stmt = $pdo->query(
        "SHOW COLUMNS FROM tour_bookings LIKE 'booking_code'"
    );
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$column ||
        !preg_match('/^(?:var)?char\((\d+)\)/i', $column['Type'], $m)
    ) {
        throw new RuntimeException(
            'Không xác định được độ dài mã đặt tour.'
        );
    }

    $length = (int)$m[1];

    if ($length < 8) {
        throw new RuntimeException(
            'Cột booking_code quá ngắn.'
        );
    }

    // Tạo mã chỉ gồm chữ và số, không vượt giới hạn MySQL.
    for ($i = 0; $i < 20; $i++) {
        $code = 'TB' . substr(
            strtoupper(bin2hex(random_bytes(16))),
            0,
            min($length - 2, 24)
        );

        $check = $pdo->prepare(
            "SELECT id FROM tour_bookings
             WHERE booking_code = ? LIMIT 1"
        );
        $check->execute([$code]);

        if (!$check->fetch()) return $code;
    }

    throw new RuntimeException(
        'Không tạo được mã đặt tour duy nhất.'
    );
}

function bt_deadlock(Throwable $e) {
    if (!$e instanceof PDOException) return false;

    $driverCode = (int)($e->errorInfo[1] ?? 0);

    return in_array($driverCode, [1205, 1213], true)
        || (string)$e->getCode() === '40001';
}

$tourId = (int)($_GET['id'] ?? 0);

if ($tourId < 1) {
    header('Location: ' . $baseUrl . 'tours/tour.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT * FROM tours WHERE id = ? AND status = 1"
);
$stmt->execute([$tourId]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: ' . $baseUrl . 'tours/tour.php');
    exit;
}

$page_title = 'Đăng Ký Tour: ' . $tour['title'];
$active_menu = 'tour';

$user = null;
$error = '';
$success = null;

if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE id = ?"
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $pdo->prepare(
    "SELECT * FROM tour_departures
     WHERE tour_id = ?
       AND departure_date >= CURDATE()
       AND status = 1
     ORDER BY departure_date"
);
$stmt->execute([$tourId]);
$departures = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paymentKey = 'vnn_tour_payment_' . $tourId;
$tokenKey = 'vnn_tour_token_' . $tourId;

if (empty($_SESSION[$tokenKey])) {
    $_SESSION[$tokenKey] = bin2hex(random_bytes(16));
}

// Hiển thị trang thanh toán sau redirect.
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['payment']) &&
    !empty($_SESSION[$paymentKey])
) {
    $success = $_SESSION[$paymentKey];
}

$selectedDeparture = (string)(
    $_POST['departure_date']
    ?? $_GET['departure']
    ?? ($departures[0]['departure_date'] ?? '')
);

$old = [
    'fullname' => $_POST['fullname'] ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'emergency_contact' => $_POST['emergency_contact'] ?? '',
    'departure_date' => $selectedDeparture,
    'number_people' => $_POST['number_people'] ?? 1,
    'notes' => $_POST['notes'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = (string)($_POST['booking_token'] ?? '');
    $fullname = trim((string)$old['fullname']);
    $phone = bt_phone($old['phone']);
    $email = trim((string)$old['email']);
    $emergency = bt_phone($old['emergency_contact']);
    $date = trim((string)$old['departure_date']);
    $people = (int)$old['number_people'];
    $notes = trim((string)$old['notes']);

    if (
        !$token ||
        !hash_equals($_SESSION[$tokenKey], $token)
    ) {
        $error = 'Phiên đăng ký không hợp lệ. Vui lòng tải lại trang.';
    } elseif (
        !$fullname ||
        strlen($phone) < 9 ||
        strlen($emergency) < 9 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Vui lòng nhập đầy đủ thông tin hợp lệ.';
    } elseif (
        $people < 1 ||
        $people > 100
    ) {
        $error = 'Số người tham gia không hợp lệ.';
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
        $date < date('Y-m-d')
    ) {
        $error = 'Ngày khởi hành không hợp lệ.';
    } else {

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $pdo->beginTransaction();

                // Khóa tour để kiểm tra số chỗ.
                $lock = $pdo->prepare(
                    "SELECT * FROM tours
                     WHERE id = ? AND status = 1
                     FOR UPDATE"
                );
                $lock->execute([$tourId]);
                $lockedTour = $lock->fetch(PDO::FETCH_ASSOC);

                if (!$lockedTour) {
                    throw new RuntimeException(
                        'Tour không còn hoạt động.'
                    );
                }

                if ((int)$lockedTour['available_slots'] < $people) {
                    throw new RuntimeException(
                        'Tour không còn đủ chỗ trống.'
                    );
                }

                if ($departures) {
                    $depStmt = $pdo->prepare(
                        "SELECT id, available_slots
                         FROM tour_departures
                         WHERE tour_id = ?
                           AND departure_date = ?
                           AND status = 1
                         FOR UPDATE"
                    );

                    $depStmt->execute([$tourId, $date]);
                    $dep = $depStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$dep || (int)$dep['available_slots'] < $people) {
                        throw new RuntimeException(
                            'Đợt khởi hành không đủ chỗ hoặc đã đóng.'
                        );
                    }
                }

                // Sử dụng tài khoản đang đăng nhập.
                $userId = !empty($_SESSION['user_id'])
                    ? (int)$_SESSION['user_id']
                    : null;

                if (!$userId) {
                    // Giữ khả năng đặt tour không cần đăng nhập.
                    // Không tự liên kết vào tài khoản đã đăng ký
                    // chỉ dựa trên email do khách nhập.
                    $check = $pdo->prepare(
                        "SELECT id FROM users
                         WHERE LOWER(email) = LOWER(?)
                         LIMIT 1"
                    );
                    $check->execute([$email]);

                    if ($check->fetch()) {
                        throw new RuntimeException(
                            'Email đã có tài khoản. Vui lòng đăng nhập để đặt tour.'
                        );
                    }

                    $password = password_hash(
                        bin2hex(random_bytes(16)),
                        PASSWORD_DEFAULT
                    );

                    $create = $pdo->prepare(
                        "INSERT INTO users
                         (fullname,email,password,phone,role,status)
                         VALUES (?, ?, ?, ?, 'customer', 1)"
                    );
                    $create->execute([
                        $fullname,
                        strtolower($email),
                        $password,
                        $phone
                    ]);

                    $userId = (int)$pdo->lastInsertId();
                }

                $bookingCode = bt_booking_code($pdo);
                $total = (float)$lockedTour['price'] * $people;

                $insert = $pdo->prepare(
                    "INSERT INTO tour_bookings
                    (
                        booking_code,user_id,tour_id,
                        departure_date,fullname,phone,email,
                        emergency_contact,number_people,
                        total_price,notes,status
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xác nhận')"
                );

                $insert->execute([
                    $bookingCode, $userId, $tourId,
                    $date, $fullname, $phone, $email,
                    $emergency, $people, $total, $notes
                ]);

                $update = $pdo->prepare(
                    "UPDATE tours
                     SET available_slots = available_slots - ?
                     WHERE id = ? AND available_slots >= ?"
                );
                $update->execute([$people, $tourId, $people]);

                if ($update->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Không thể giữ chỗ. Vui lòng thử lại.'
                    );
                }

                $pdo->commit();

                $transfer =
                    'TOUR ' .
                    $bookingCode . ' ' .
                    substr(bt_token($lockedTour['title']), 0, 24) . ' ' .
                    bt_token($fullname) . ' ' .
                    $phone;

                $_SESSION[$paymentKey] = [
                    'booking_code' => $bookingCode,
                    'tour_name' => $lockedTour['title'],
                    'departure_date' => $date,
                    'fullname' => $fullname,
                    'phone' => $phone,
                    'number_people' => $people,
                    'total_price' => $total,
                    'transfer' => $transfer
                ];

                // Token mới để tránh gửi lại biểu mẫu cũ.
                $_SESSION[$tokenKey] = bin2hex(random_bytes(16));

                header(
                    'Location: ' . $baseUrl .
                    'tours/tour-register.php?id=' .
                    $tourId . '&payment=1',
                    true,
                    303
                );
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (bt_deadlock($e) && $attempt < 3) {
                    usleep(random_int(100000, 300000));
                    continue;
                }

                error_log('Tour registration: ' . $e->getMessage());

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Không thể đăng ký tour lúc này. Vui lòng thử lại.';

                break;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.vnn-tour-page {
    background:#f8fafc;
    padding:35px 16px 70px;
    min-height:70vh;
}
.vnn-tour-card {
    max-width:760px;
    margin:auto;
    background:#fff;
    padding:34px;
    border-radius:16px;
    box-shadow:0 8px 24px rgba(0,0,0,.07);
}
.vnn-tour-card h2 {
    text-align:center;
    color:#14532d;
    margin:0 0 12px;
}
.vnn-subtitle {
    color:#64748b;
    text-align:center;
    margin-bottom:27px;
}
.vnn-form-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}
.vnn-field {
    margin-bottom:18px;
}
.vnn-field label {
    display:block;
    font-weight:700;
    margin-bottom:8px;
}
.vnn-field input,
.vnn-field select,
.vnn-field textarea {
    display:block;
    width:100%;
    border:1px solid #dbe3ed;
    border-radius:9px;
    padding:12px 15px;
    font:inherit;
    box-sizing:border-box;
}
.vnn-field textarea {
    resize:vertical;
    min-height:90px;
}
.vnn-departure {
    padding:17px;
    background:#fff7ed;
    border:1px solid #fed7aa;
    border-radius:10px;
    margin-bottom:25px;
}
.vnn-departure label {
    color:#c2410c;
}
.vnn-summary {
    background:#f8fafc;
    padding:20px;
    border:1px solid #e2e8f0;
    border-radius:11px;
    margin:22px 0;
}
.vnn-row {
    display:flex;
    justify-content:space-between;
    gap:15px;
    padding:9px 0;
}
.vnn-total {
    color:#ea580c;
    font-size:21px;
    font-weight:800;
}
.vnn-submit {
    background:#ea580c;
    color:#fff;
    border:0;
    border-radius:10px;
    padding:15px;
    width:100%;
    font-weight:800;
    font-size:17px;
    cursor:pointer;
}
.vnn-submit:disabled {
    opacity:.6;
    cursor:wait;
}
.vnn-message {
    border-radius:10px;
    padding:16px;
    margin-bottom:20px;
}
.vnn-error {
    color:#b91c1c;
    background:#fee2e2;
}
.vnn-success {
    color:#166534;
    background:#dcfce7;
}
.vnn-payment-grid {
    display:grid;
    grid-template-columns:250px 1fr;
    gap:22px;
    margin-top:22px;
}
.vnn-qr {
    width:100%;
    height:auto;
    border-radius:12px;
    border:1px solid #fbcfe8;
}
.vnn-payment-info {
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:20px;
}
.vnn-code {
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#c2410c;
    font-weight:800;
    padding:14px;
    border-radius:10px;
    margin:14px 0;
    overflow-wrap:anywhere;
}
.vnn-copy {
    background:#14532d;
    color:white;
    border:0;
    padding:12px 16px;
    border-radius:9px;
    font-weight:700;
    cursor:pointer;
}
.vnn-help {
    color:#64748b;
    font-size:14px;
    line-height:1.7;
}
.vnn-links {
    display:flex;
    gap:14px;
    flex-wrap:wrap;
    margin-top:24px;
}
.vnn-links a {
    text-decoration:none;
    background:#e2e8f0;
    color:#0f172a;
    padding:12px 17px;
    border-radius:10px;
    font-weight:700;
}
@media(max-width:700px) {
    .vnn-tour-card {padding:20px}
    .vnn-form-grid,
    .vnn-payment-grid {grid-template-columns:1fr}
    .vnn-row {flex-direction:column;gap:3px}
}
</style>

<section class="vnn-tour-page">
<div class="vnn-tour-card">

<?php if ($success): ?>

<h2>ĐĂNG KÝ TOUR THÀNH CÔNG!</h2>

<div class="vnn-message vnn-success">
    Đăng ký tour đã được lưu. Vui lòng thanh toán
    theo hướng dẫn bên dưới.
</div>

<div class="vnn-summary">
    <div class="vnn-row">
        <span>Mã đăng ký</span>
        <strong><?= bt_h($success['booking_code']) ?></strong>
    </div>
    <div class="vnn-row">
        <span>Tour</span>
        <strong><?= bt_h($success['tour_name']) ?></strong>
    </div>
    <div class="vnn-row">
        <span>Ngày khởi hành</span>
        <strong>
            <?= date('d/m/Y', strtotime($success['departure_date'])) ?>
        </strong>
    </div>
    <div class="vnn-row">
        <span>Khách hàng</span>
        <strong><?= bt_h($success['fullname']) ?></strong>
    </div>
    <div class="vnn-row">
        <span>Số điện thoại</span>
        <strong><?= bt_h($success['phone']) ?></strong>
    </div>
    <div class="vnn-row">
        <span>Số người</span>
        <strong><?= (int)$success['number_people'] ?></strong>
    </div>
    <div class="vnn-row vnn-total">
        <span>Tổng tiền</span>
        <strong><?= bt_money($success['total_price']) ?></strong>
    </div>
</div>

<h3 style="color:#14532d">
    THANH TOÁN BẰNG HÌNH THỨC CHUYỂN KHOẢN
</h3>

<div class="vnn-payment-grid">

    <div>
        <img src="<?= bt_h($qrImage) ?>"
             alt="QR thanh toán MoMo"
             class="vnn-qr">
    </div>

    <div class="vnn-payment-info">

        <h3>Thông tin chuyển khoản</h3>

        <p>Số tiền:</p>

        <h2 style="text-align:left;color:#ea580c">
            <?= bt_money($success['total_price']) ?>
        </h2>

        <p><strong>Nội dung chuyển khoản bắt buộc:</strong></p>

        <div class="vnn-code" id="vnn-transfer">
            <?= bt_h($success['transfer']) ?>
        </div>

        <button type="button" class="vnn-copy"
                onclick="copyVnnTransfer()">
            Sao chép nội dung chuyển khoản
        </button>

        <p class="vnn-help" style="margin-top:18px">
            Vui lòng chuyển khoản đúng số tiền và nội dung.
            Hệ thống chưa tự xác nhận giao dịch.
            Quản lý sẽ đối chiếu và xác nhận thủ công.
        </p>

    </div>
</div>

<div class="vnn-links">
    <a href="<?= $baseUrl ?>tours/my-tours.php">
        Xem tour tôi đã đăng ký
    </a>
    <a href="<?= $baseUrl ?>tours/tour.php">
        Xem các tour khác
    </a>
</div>

<?php else: ?>

<h2>ĐĂNG KÝ THAM GIA TOUR</h2>

<p class="vnn-subtitle">
    Hành trình:
    <strong><?= bt_h($tour['title']) ?></strong>
</p>

<?php if ($error): ?>
<div class="vnn-message vnn-error">
    <?= bt_h($error) ?>
</div>
<?php endif; ?>

<form method="POST" id="vnn-tour-form">

<input
    type="hidden"
    name="booking_token"
    value="<?= bt_h($_SESSION[$tokenKey]) ?>"
>

<div class="vnn-departure">
    <div class="vnn-field" style="margin-bottom:0">

        <label>CHỌN NGÀY KHỞI HÀNH (CÁC ĐỢT TOUR CÓ SẴN):</label>

        <?php if ($departures): ?>

        <select name="departure_date" required>
            <?php foreach ($departures as $dep): ?>
            <option
                value="<?= bt_h($dep['departure_date']) ?>"
                <?= $selectedDeparture === $dep['departure_date']
                    ? 'selected' : '' ?>
            >
                Đợt khởi hành:
                <?= date('d/m/Y', strtotime($dep['departure_date'])) ?>
                (Còn <?= (int)$dep['available_slots'] ?> chỗ)
            </option>
            <?php endforeach; ?>
        </select>

        <?php else: ?>

        <input
            type="date"
            name="departure_date"
            min="<?= date('Y-m-d') ?>"
            value="<?= bt_h(
                $selectedDeparture ?: date('Y-m-d', strtotime('+3 days'))
            ) ?>"
            required
        >

        <?php endif; ?>
    </div>
</div>

<div class="vnn-form-grid">

    <div class="vnn-field">
        <label>Họ và tên *</label>
        <input type="text" name="fullname" required
               value="<?= bt_h($old['fullname']) ?>"
               placeholder="Nguyễn Văn A">
    </div>

    <div class="vnn-field">
        <label>Số điện thoại *</label>
        <input type="tel" name="phone" required
               value="<?= bt_h($old['phone']) ?>"
               placeholder="09xxxxxxxx">
    </div>

    <div class="vnn-field">
        <label>Địa chỉ Email *</label>
        <input type="email" name="email" required
               value="<?= bt_h($old['email']) ?>"
               placeholder="email@gmail.com">
    </div>

    <div class="vnn-field">
        <label>SĐT liên hệ khẩn cấp *</label>
        <input type="tel" name="emergency_contact" required
               value="<?= bt_h($old['emergency_contact']) ?>"
               placeholder="09xxxxxxxx">
    </div>

</div>

<div class="vnn-field">
    <label>
        Số người tham gia *
        (Tour còn <?= (int)$tour['available_slots'] ?> chỗ)
    </label>

    <input
        type="number"
        name="number_people"
        id="vnn-people"
        min="1"
        max="<?= (int)$tour['available_slots'] ?>"
        value="<?= (int)$old['number_people'] ?>"
        required
    >
</div>

<div class="vnn-field">
    <label>Ghi chú</label>
    <textarea name="notes" rows="3"
              placeholder="Chế độ ăn, lưu ý sức khỏe..."><?= bt_h($old['notes']) ?></textarea>
</div>

<div class="vnn-summary">
    <div class="vnn-row">
        <span>Đơn giá / người</span>
        <strong><?= bt_money($tour['price']) ?></strong>
    </div>
    <div class="vnn-row vnn-total">
        <span>TỔNG TIỀN DỰ KIẾN</span>
        <span id="vnn-total">
            <?= bt_money(
                (float)$tour['price'] *
                max(1, (int)$old['number_people'])
            ) ?>
        </span>
    </div>
</div>

<button
    type="submit"
    class="vnn-submit"
    id="vnn-submit"
    <?= (int)$tour['available_slots'] < 1 ? 'disabled' : '' ?>
>
    XÁC NHẬN ĐĂNG KÝ TOUR
</button>

</form>

<?php endif; ?>

</div>
</section>

<script>
const vnnPrice = <?= json_encode((float)$tour['price']) ?>;

const peopleInput = document.getElementById('vnn-people');
const totalElement = document.getElementById('vnn-total');

if (peopleInput && totalElement) {
    peopleInput.addEventListener('input', function() {
        const number = Math.max(1, parseInt(this.value, 10) || 1);
        totalElement.textContent =
            (number * vnnPrice).toLocaleString('vi-VN') + ' đ';
    });
}

const tourForm = document.getElementById('vnn-tour-form');

if (tourForm) {
    tourForm.addEventListener('submit', function(event) {
        if (!tourForm.checkValidity()) return;

        const button = document.getElementById('vnn-submit');
        if (button) {
            button.disabled = true;
            button.textContent = 'ĐANG XỬ LÝ ĐĂNG KÝ...';
        }
    });
}

async function copyVnnTransfer() {
    const text = document.getElementById('vnn-transfer')
        ?.textContent.trim();

    if (!text) return;

    try {
        await navigator.clipboard.writeText(text);
        alert('Đã sao chép nội dung chuyển khoản!');
    } catch (error) {
        window.prompt('Sao chép nội dung:', text);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
