
<?php
// VNN Travel - Dang ky Tour va thanh toan chuyen khoan
// Fix booking_code 1406 va MySQL deadlock 1213

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$baseUrl = rtrim(BASE_URL, '/') . '/';
$qrImage = $baseUrl . 'assets/images/momo-qr-vu.jpg';

function tr_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tr_money($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

function tr_phone($value) {
    return preg_replace('/[^0-9]/', '', (string)$value);
}

function tr_token($value) {
    $value = trim((string)$value);

    if (class_exists('Transliterator')) {
        $trans = Transliterator::create('Any-Latin; Latin-ASCII');
        if ($trans) {
            $value = $trans->transliterate($value);
        }
    } else {
        $map = [
            'đ'=>'d','Đ'=>'D',
            'á'=>'a','à'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
            'ă'=>'a','ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
            'é'=>'e','è'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
            'ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
            'í'=>'i','ì'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
            'ó'=>'o','ò'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
            'ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
            'ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
            'ú'=>'u','ù'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
            'ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
            'ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y'
        ];
        $value = strtr($value, $map);
    }

    $value = strtoupper($value);
    return preg_replace('/[^A-Z0-9]/', '', $value);
}

function tr_transfer_content($bookingCode, $tourTitle, $fullname, $phone) {
    return 'TOUR ' .
        $bookingCode . ' ' .
        substr(tr_token($tourTitle), 0, 25) . ' ' .
        tr_token($fullname) . ' ' .
        tr_phone($phone);
}

function tr_booking_code_length(PDO $pdo) {
    $stmt = $pdo->query(
        "SHOW COLUMNS FROM tour_bookings LIKE 'booking_code'"
    );

    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        throw new RuntimeException(
            'Không tìm thấy cột mã đặt tour trong database.'
        );
    }

    $type = strtolower($column['Type']);

    if (preg_match('/^(?:var)?char\((\d+)\)/', $type, $match)) {
        return (int)$match[1];
    }

    throw new RuntimeException(
        'Kiểu dữ liệu mã đặt tour chưa được hỗ trợ.'
    );
}

function tr_create_booking_code(PDO $pdo) {
    $maxLength = tr_booking_code_length($pdo);

    if ($maxLength < 8) {
        throw new RuntimeException(
            'Cột booking_code quá ngắn để tạo mã an toàn.'
        );
    }

    // Doc do dai that tu MySQL, khong tao ma qua dai.
    $randomLength = min($maxLength - 2, 24);

    for ($attempt = 0; $attempt < 15; $attempt++) {
        $random = strtoupper(bin2hex(random_bytes(16)));

        $code = 'TB' . substr($random, 0, $randomLength);

        $check = $pdo->prepare(
            "SELECT id FROM tour_bookings
             WHERE booking_code = ?
             LIMIT 1"
        );
        $check->execute([$code]);

        if (!$check->fetch()) {
            return $code;
        }
    }

    throw new RuntimeException(
        'Không thể tạo mã đăng ký mới. Vui lòng thử lại.'
    );
}

function tr_is_retryable($e) {
    if (!($e instanceof PDOException)) {
        return false;
    }

    $mysqlCode = (int)($e->errorInfo[1] ?? 0);

    return in_array($mysqlCode, [1205, 1213], true)
        || (string)$e->getCode() === '40001';
}

$tourId = (int)($_GET['id'] ?? 0);

if ($tourId <= 0) {
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

$error = '';
$success = null;
$user = null;

if (!empty($_SESSION['user_id'])) {
    $userStmt = $pdo->prepare(
        "SELECT * FROM users WHERE id = ?"
    );
    $userStmt->execute([$_SESSION['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
}

$depStmt = $pdo->prepare(
    "SELECT *
     FROM tour_departures
     WHERE tour_id = ?
       AND departure_date >= CURDATE()
       AND status = 1
     ORDER BY departure_date ASC"
);
$depStmt->execute([$tourId]);
$departures = $depStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedDeparture = (string)(
    $_POST['departure_date']
    ?? $_GET['departure']
    ?? ($departures[0]['departure_date'] ?? '')
);

$paymentKey = 'tour_payment_' . $tourId;

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    && isset($_GET['payment'])
    && !empty($_SESSION[$paymentKey])
) {
    $success = $_SESSION[$paymentKey];
}

$old = [
    'fullname' => $_POST['fullname'] ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'emergency_contact' => $_POST['emergency_contact'] ?? '',
    'departure_date' => $selectedDeparture,
    'number_people' => $_POST['number_people'] ?? 1,
    'notes' => $_POST['notes'] ?? ''
];

// Tao token ngan double-submit trong cung session
$nonceKey = 'tour_nonce_' . $tourId;

if (empty($_SESSION[$nonceKey])) {
    $_SESSION[$nonceKey] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nonce = (string)($_POST['booking_nonce'] ?? '');

    $fullname = trim((string)$old['fullname']);
    $phone = tr_phone($old['phone']);
    $email = trim((string)$old['email']);
    $emergency = tr_phone($old['emergency_contact']);
    $departureDate = trim((string)$old['departure_date']);
    $numberPeople = (int)$old['number_people'];
    $notes = trim((string)$old['notes']);

    if (
        $nonce === ''
        || !hash_equals($_SESSION[$nonceKey], $nonce)
    ) {
        $error = 'Phiên đăng ký không hợp lệ. Vui lòng tải lại trang.';

    } elseif (
        $fullname === ''
        || strlen($phone) < 9
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($emergency) < 9
    ) {
        $error = 'Vui lòng nhập đầy đủ và chính xác thông tin bắt buộc.';

    } elseif ($numberPeople < 1) {
        $error = 'Số người tham gia phải lớn hơn 0.';

    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $departureDate)
        || $departureDate < date('Y-m-d')
    ) {
        $error = 'Ngày khởi hành không hợp lệ.';

    } else {

        for ($attempt = 1; $attempt <= 3; $attempt++) {

            try {
                $pdo->beginTransaction();

                // Khoa tour de tranh tru cho vuot so luong
                $lockTour = $pdo->prepare(
                    "SELECT *
                     FROM tours
                     WHERE id = ? AND status = 1
                     FOR UPDATE"
                );
                $lockTour->execute([$tourId]);
                $lockedTour = $lockTour->fetch(PDO::FETCH_ASSOC);

                if (!$lockedTour) {
                    throw new RuntimeException(
                        'Tour hiện không còn hoạt động.'
                    );
                }

                if (
                    (int)$lockedTour['available_slots']
                    < $numberPeople
                ) {
                    throw new RuntimeException(
                        'Tour không còn đủ chỗ.'
                    );
                }

                // Kiem tra dot khoi hanh
                if ($departures) {
                    $depLock = $pdo->prepare(
                        "SELECT *
                         FROM tour_departures
                         WHERE tour_id = ?
                           AND departure_date = ?
                           AND status = 1
                         FOR UPDATE"
                    );

                    $depLock->execute([$tourId, $departureDate]);
                    $departure = $depLock->fetch(PDO::FETCH_ASSOC);

                    if (!$departure) {
                        throw new RuntimeException(
                            'Đợt khởi hành không còn khả dụng.'
                        );
                    }

                    if (
                        (int)$departure['available_slots']
                        < $numberPeople
                    ) {
                        throw new RuntimeException(
                            'Đợt khởi hành không đủ chỗ.'
                        );
                    }
                }

                // Lien ket khach hang theo logic cu
                $userId = $_SESSION['user_id'] ?? null;

                if (!$userId) {
                    $check = $pdo->prepare(
                        "SELECT id FROM users
                         WHERE LOWER(email) = LOWER(?)
                         LIMIT 1"
                    );
                    $check->execute([$email]);
                    $existing = $check->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        $userId = (int)$existing['id'];
                    } else {
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
                }

                // Ma dat tour ngan dung voi kich thuoc cot
                $bookingCode = tr_create_booking_code($pdo);

                $totalPrice =
                    (float)$lockedTour['price'] * $numberPeople;

                $insert = $pdo->prepare(
                    "INSERT INTO tour_bookings
                     (
                        booking_code,
                        user_id,
                        tour_id,
                        departure_date,
                        fullname,
                        phone,
                        email,
                        emergency_contact,
                        number_people,
                        total_price,
                        notes,
                        status
                     )
                     VALUES
                     (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xác nhận')"
                );

                $insert->execute([
                    $bookingCode,
                    $userId,
                    $tourId,
                    $departureDate,
                    $fullname,
                    $phone,
                    $email,
                    $emergency,
                    $numberPeople,
                    $totalPrice,
                    $notes
                ]);

                // Tru cho tour duy nhat sau khi luu booking
                $updateTour = $pdo->prepare(
                    "UPDATE tours
                     SET available_slots = available_slots - ?
                     WHERE id = ?
                       AND available_slots >= ?"
                );

                $updateTour->execute([
                    $numberPeople,
                    $tourId,
                    $numberPeople
                ]);

                if ($updateTour->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Số chỗ vừa thay đổi. Vui lòng đăng ký lại.'
                    );
                }

                $pdo->commit();

                $transferContent = tr_transfer_content(
                    $bookingCode,
                    $lockedTour['title'],
                    $fullname,
                    $phone
                );

                $_SESSION[$paymentKey] = [
                    'booking_code' => $bookingCode,
                    'tour_name' => $lockedTour['title'],
                    'departure_date' => $departureDate,
                    'fullname' => $fullname,
                    'phone' => $phone,
                    'number_people' => $numberPeople,
                    'total_price' => $totalPrice,
                    'transfer_content' => $transferContent
                ];

                // Token moi de chong gui lai form cu
                $_SESSION[$nonceKey] = bin2hex(random_bytes(16));

                header(
                    'Location: ' . $baseUrl .
                    'tours/tour-register.php?id=' .
                    $tourId . '&payment=1'
                );
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (
                    tr_is_retryable($e)
                    && $attempt < 3
                ) {
                    usleep(random_int(100000, 300000));
                    continue;
                }

                error_log(
                    'Tour booking: ' . $e->getMessage()
                );

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Không thể hoàn tất đăng ký lúc này. Vui lòng thử lại.';

                break;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.tr-page {
    padding:40px 15px 65px;
    background:#f8fafc;
    min-height:70vh;
}

.tr-card {
    max-width:760px;
    margin:auto;
    background:#fff;
    padding:35px;
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,.07);
}

.tr-card h2 {
    color:#14532d;
    text-align:center;
    font-size:27px;
    margin:0 0 12px;
}

.tr-subtitle {
    color:#64748b;
    text-align:center;
    line-height:1.7;
    margin-bottom:28px;
}

.tr-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:17px;
}

.tr-field {
    margin-bottom:18px;
}

.tr-field label {
    display:block;
    font-weight:700;
    color:#0f172a;
    margin-bottom:9px;
}

.tr-field input,
.tr-field select,
.tr-field textarea {
    display:block;
    width:100%;
    padding:12px 15px;
    border:1px solid #dbe3ed;
    border-radius:9px;
    font:inherit;
    box-sizing:border-box;
}

.tr-field textarea {
    min-height:95px;
    resize:vertical;
}

.tr-departure {
    padding:17px;
    background:#fff7ed;
    border:1px solid #fed7aa;
    border-radius:10px;
    margin-bottom:24px;
}

.tr-departure label {
    color:#c2410c;
}

.tr-summary {
    padding:20px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    margin:18px 0;
}

.tr-row {
    display:flex;
    justify-content:space-between;
    gap:15px;
    margin:12px 0;
}

.tr-total {
    color:#ea580c;
    font-size:21px;
    font-weight:800;
}

.tr-button {
    width:100%;
    padding:15px;
    border:0;
    border-radius:11px;
    background:#ea580c;
    color:white;
    font-size:17px;
    font-weight:800;
    cursor:pointer;
}

.tr-button:disabled {
    opacity:.65;
    cursor:wait;
}

.tr-alert {
    padding:16px;
    border-radius:10px;
    margin-bottom:20px;
    line-height:1.6;
}

.tr-error {
    color:#b91c1c;
    background:#fee2e2;
}

.tr-success {
    color:#166534;
    background:#dcfce7;
}

.tr-payment {
    background:#f8fafc;
    padding:20px;
    border-radius:12px;
    margin:20px 0;
}

.tr-qr-grid {
    display:grid;
    grid-template-columns:250px 1fr;
    gap:22px;
    align-items:start;
}

.tr-qr {
    width:100%;
    border-radius:13px;
    border:1px solid #fbcfe8;
}

.tr-payment-detail {
    border:1px solid #e2e8f0;
    padding:19px;
    border-radius:12px;
}

.tr-code {
    padding:14px;
    background:#fff7ed;
    border:1px solid #fed7aa;
    border-radius:10px;
    color:#c2410c;
    font-size:16px;
    font-weight:800;
    overflow-wrap:anywhere;
    margin:15px 0;
}

.tr-copy {
    padding:12px 16px;
    border:0;
    border-radius:10px;
    background:#14532d;
    color:white;
    font-weight:700;
    cursor:pointer;
}

.tr-note {
    color:#64748b;
    line-height:1.8;
    font-size:14px;
    margin-top:16px;
}

.tr-actions {
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top:24px;
}

.tr-actions a {
    background:#e2e8f0;
    color:#0f172a;
    text-decoration:none;
    padding:12px 18px;
    border-radius:10px;
    font-weight:700;
}

@media(max-width:700px) {
    .tr-card {padding:20px}
    .tr-grid,.tr-qr-grid {grid-template-columns:1fr}
    .tr-row {flex-direction:column;gap:5px}
}
</style>

<section class="tr-page">
<div class="tr-card">

<?php if ($success): ?>

<h2>ĐĂNG KÝ TOUR THÀNH CÔNG!</h2>

<div class="tr-alert tr-success">
    Đăng ký của bạn đã được lưu thành công.
    Vui lòng thực hiện thanh toán theo hướng dẫn bên dưới.
</div>

<div class="tr-payment">

    <div class="tr-row">
        <span>Mã đăng ký</span>
        <strong><?= tr_h($success['booking_code']) ?></strong>
    </div>

    <div class="tr-row">
        <span>Tour</span>
        <strong><?= tr_h($success['tour_name']) ?></strong>
    </div>

    <div class="tr-row">
        <span>Ngày khởi hành</span>
        <strong>
            <?= date('d/m/Y', strtotime($success['departure_date'])) ?>
        </strong>
    </div>

    <div class="tr-row">
        <span>Khách hàng</span>
        <strong><?= tr_h($success['fullname']) ?></strong>
    </div>

    <div class="tr-row">
        <span>Số điện thoại</span>
        <strong><?= tr_h($success['phone']) ?></strong>
    </div>

    <div class="tr-row">
        <span>Số người</span>
        <strong><?= (int)$success['number_people'] ?></strong>
    </div>

    <div class="tr-row tr-total">
        <span>Tổng tiền</span>
        <strong><?= tr_money($success['total_price']) ?></strong>
    </div>

</div>

<h3 style="color:#14532d;margin-bottom:20px">
    THANH TOÁN BẰNG HÌNH THỨC CHUYỂN KHOẢN
</h3>

<div class="tr-qr-grid">

    <div>
        <img
            src="<?= tr_h($qrImage) ?>"
            alt="Mã QR thanh toán MoMo"
            class="tr-qr"
        >
    </div>

    <div class="tr-payment-detail">

        <h3>Thông tin thanh toán</h3>

        <p>Số tiền cần chuyển:</p>

        <h2 style="text-align:left;color:#ea580c">
            <?= tr_money($success['total_price']) ?>
        </h2>

        <p><strong>Nội dung chuyển khoản bắt buộc:</strong></p>

        <div id="tr-transfer" class="tr-code"><?= tr_h($success['transfer_content']) ?></div>

        <button
            type="button"
            class="tr-copy"
            onclick="copyTourTransfer()"
        >
            <i class="fa-solid fa-copy"></i>
            Sao chép nội dung
        </button>

        <div class="tr-note">
            Vui lòng chuyển đúng số tiền và nội dung trên.
            Mã QR sử dụng cố định nên hệ thống chưa tự động
            xác nhận giao dịch. Quản lý sẽ kiểm tra thanh toán
            thủ công.
        </div>

    </div>

</div>

<div class="tr-actions">
    <a href="<?= $baseUrl ?>tours/my-tours.php">
        Tour tôi đã đăng ký
    </a>
    <a href="<?= $baseUrl ?>tours/tour.php">
        Xem tour khác
    </a>
</div>

<?php else: ?>

<h2>ĐĂNG KÝ THAM GIA TOUR</h2>

<p class="tr-subtitle">
    Hành trình:
    <strong><?= tr_h($tour['title']) ?></strong>
</p>

<?php if ($error): ?>
<div class="tr-alert tr-error"><?= tr_h($error) ?></div>
<?php endif; ?>

<form method="POST" id="tour-form">

<input
    type="hidden"
    name="booking_nonce"
    value="<?= tr_h($_SESSION[$nonceKey]) ?>"
>

<div class="tr-departure">
    <div class="tr-field" style="margin:0">

        <label>
            CHỌN NGÀY KHỞI HÀNH (CÁC ĐỢT TOUR CÓ SẴN):
        </label>

        <?php if ($departures): ?>

        <select name="departure_date" required>

            <?php foreach ($departures as $dep): ?>

            <option
                value="<?= tr_h($dep['departure_date']) ?>"
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
            value="<?= tr_h(
                $selectedDeparture ?: date('Y-m-d', strtotime('+3 days'))
            ) ?>"
            required
        >

        <small>
            Quý khách có thể chọn ngày dự kiến.
            VNN Travel sẽ liên hệ để xác nhận.
        </small>

        <?php endif; ?>

    </div>
</div>

<div class="tr-grid">

    <div class="tr-field">
        <label>Họ và tên *</label>
        <input
            name="fullname"
            required
            value="<?= tr_h($old['fullname']) ?>"
            placeholder="Nguyễn Văn A"
        >
    </div>

    <div class="tr-field">
        <label>Số điện thoại *</label>
        <input
            type="tel"
            name="phone"
            required
            value="<?= tr_h($old['phone']) ?>"
            placeholder="09xxxxxxxx"
        >
    </div>

    <div class="tr-field">
        <label>Địa chỉ Email *</label>
        <input
            type="email"
            name="email"
            required
            value="<?= tr_h($old['email']) ?>"
            placeholder="email@gmail.com"
        >
    </div>

    <div class="tr-field">
        <label>SĐT liên hệ khẩn cấp *</label>
        <input
            type="tel"
            name="emergency_contact"
            required
            value="<?= tr_h($old['emergency_contact']) ?>"
            placeholder="09xxxxxxxx"
        >
    </div>

</div>

<div class="tr-field">
    <label>
        Số người tham gia *
        (Tour còn <?= (int)$tour['available_slots'] ?> chỗ)
    </label>

    <input
        type="number"
        id="tr-people"
        name="number_people"
        min="1"
        max="<?= (int)$tour['available_slots'] ?>"
        value="<?= (int)$old['number_people'] ?>"
        required
    >
</div>

<div class="tr-field">
    <label>Ghi chú</label>
    <textarea
        name="notes"
        placeholder="Chế độ ăn, lưu ý sức khỏe..."
    ><?= tr_h($old['notes']) ?></textarea>
</div>

<div class="tr-summary">

    <div class="tr-row">
        <span>Đơn giá / người:</span>
        <strong><?= tr_money($tour['price']) ?></strong>
    </div>

    <div class="tr-row tr-total">
        <span>TỔNG TIỀN DỰ KIẾN:</span>
        <span id="tr-total">
            <?= tr_money(
                (float)$tour['price'] *
                max(1, (int)$old['number_people'])
            ) ?>
        </span>
    </div>

</div>

<button
    type="submit"
    id="tr-submit"
    class="tr-button"
    <?= (int)$tour['available_slots'] < 1 ? 'disabled' : '' ?>
>
    XÁC NHẬN ĐĂNG KÝ TOUR
</button>

<?php endif; ?>

</form>

</div>
</section>

<script>
const tourPrice = <?= json_encode((float)$tour['price']) ?>;

const peopleInput = document.getElementById('tr-people');
const totalDisplay = document.getElementById('tr-total');

if (peopleInput && totalDisplay) {
    peopleInput.addEventListener('input', function () {
        const number = Math.max(1, parseInt(this.value, 10) || 1);
        totalDisplay.textContent =
            (number * tourPrice).toLocaleString('vi-VN') + ' đ';
    });
}

const tourForm = document.getElementById('tour-form');

if (tourForm) {
    tourForm.addEventListener('submit', function () {
        if (!tourForm.checkValidity()) return;

        const button = document.getElementById('tr-submit');

        if (button) {
            button.disabled = true;
            button.textContent = 'ĐANG XỬ LÝ ĐĂNG KÝ...';
        }
    });
}

async function copyTourTransfer() {
    const element = document.getElementById('tr-transfer');
    if (!element) return;

    const content = element.textContent.trim();

    try {
        await navigator.clipboard.writeText(content);
        alert('Đã sao chép nội dung chuyển khoản!');
    } catch (error) {
        window.prompt('Sao chép nội dung:', content);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
