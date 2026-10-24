
<?php
// VNN Travel - Dang ky va thanh toan Tour

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = rtrim(BASE_URL, '/') . '/';
$qrImage = $baseUrl . 'assets/images/momo-qr-vu.jpg';

function tr_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tr_phone($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function tr_name($value) {
    $value = trim((string)$value);

    if (class_exists('Transliterator')) {
        $t = Transliterator::create('Any-Latin; Latin-ASCII');
        if ($t) {
            $value = $t->transliterate($value);
        }
    } else {
        $value = strtr($value, [
            'đ'=>'d', 'Đ'=>'D',
            'á'=>'a', 'à'=>'a', 'ả'=>'a', 'ã'=>'a',
            'ạ'=>'a', 'ă'=>'a', 'ắ'=>'a', 'ằ'=>'a',
            'ẵ'=>'a', 'ặ'=>'a', 'ẳ'=>'a',
            'â'=>'a', 'ấ'=>'a', 'ầ'=>'a',
            'ậ'=>'a', 'ẩ'=>'a', 'ẫ'=>'a',
            'é'=>'e', 'è'=>'e', 'ẻ'=>'e',
            'ẽ'=>'e', 'ẹ'=>'e', 'ê'=>'e',
            'ế'=>'e', 'ề'=>'e', 'ể'=>'e',
            'ễ'=>'e', 'ệ'=>'e',
            'í'=>'i', 'ì'=>'i', 'ỉ'=>'i',
            'ĩ'=>'i', 'ị'=>'i',
            'ó'=>'o', 'ò'=>'o', 'ỏ'=>'o',
            'õ'=>'o', 'ọ'=>'o', 'ô'=>'o',
            'ố'=>'o', 'ồ'=>'o', 'ổ'=>'o',
            'ỗ'=>'o', 'ộ'=>'o', 'ơ'=>'o',
            'ớ'=>'o', 'ờ'=>'o', 'ở'=>'o',
            'ỡ'=>'o', 'ợ'=>'o',
            'ú'=>'u', 'ù'=>'u', 'ủ'=>'u',
            'ũ'=>'u', 'ụ'=>'u', 'ư'=>'u',
            'ứ'=>'u', 'ừ'=>'u', 'ử'=>'u',
            'ữ'=>'u', 'ự'=>'u',
            'ý'=>'y', 'ỳ'=>'y', 'ỷ'=>'y',
            'ỹ'=>'y', 'ỵ'=>'y'
        ]);
    }

    $value = strtoupper($value);
    return trim(preg_replace('/[^A-Z0-9]+/', ' ', $value));
}

function tr_token($value) {
    return str_replace(' ', '', tr_name($value));
}

function tr_transfer($tourId, $tourTitle, $name, $phone) {
    return 'TOUR T' . (int)$tourId . ' ' .
        substr(tr_token($tourTitle), 0, 24) . ' ' .
        tr_token($name) . ' ' .
        tr_phone($phone);
}

function tr_deadlock($e) {
    if (!($e instanceof PDOException)) return false;

    $info = $e->errorInfo ?? [];
    $mysqlCode = (int)($info[1] ?? 0);

    return in_array($mysqlCode, [1205, 1213], true)
        || in_array((string)$e->getCode(), ['40001'], true);
}

$tour_id = (int)($_GET['id'] ?? 0);
$selected_departure = trim((string)($_GET['departure'] ?? ''));

if ($tour_id <= 0) {
    header('Location: ' . $baseUrl . 'tours/tour.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT * FROM tours WHERE id = ? AND status = 1"
);
$stmt->execute([$tour_id]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: ' . $baseUrl . 'tours/tour.php');
    exit;
}

$page_title = 'Đăng Ký Tour: ' . $tour['title'];
$error = '';
$success = null;
$user = null;

if (isLoggedIn()) {
    $uStmt = $pdo->prepare(
        "SELECT * FROM users WHERE id = ?"
    );
    $uStmt->execute([$_SESSION['user_id']]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);
}

$depStmt = $pdo->prepare(
    "SELECT * FROM tour_departures
     WHERE tour_id = ?
       AND departure_date >= CURDATE()
       AND status = 1
     ORDER BY departure_date ASC"
);
$depStmt->execute([$tour_id]);
$departures = $depStmt->fetchAll(PDO::FETCH_ASSOC);

// Thong bao thanh cong sau khi POST.
$successKey = 'tour_payment_' . $tour_id;

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['payment']) &&
    isset($_SESSION[$successKey])
) {
    $success = $_SESSION[$successKey];
    unset($_SESSION[$successKey]);
}

$old = [
    'fullname' => $_POST['fullname'] ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'emergency_contact' => $_POST['emergency_contact'] ?? '',
    'departure_date' => $_POST['departure_date']
        ?? $selected_departure
        ?: ($departures[0]['departure_date'] ?? date('Y-m-d', strtotime('+3 days'))),
    'number_people' => $_POST['number_people'] ?? 1,
    'notes' => $_POST['notes'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim((string)$old['fullname']);
    $phone = tr_phone($old['phone']);
    $email = trim((string)$old['email']);
    $emergency = trim((string)$old['emergency_contact']);
    $departureDate = trim((string)$old['departure_date']);
    $numberPeople = (int)$old['number_people'];
    $notes = trim((string)$old['notes']);

    if (
        $fullname === '' ||
        strlen($phone) < 9 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $emergency === ''
    ) {
        $error = 'Vui lòng nhập đầy đủ và chính xác thông tin bắt buộc.';
    } elseif ($numberPeople < 1) {
        $error = 'Số người tham gia phải lớn hơn 0.';
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $departureDate) ||
        $departureDate < date('Y-m-d')
    ) {
        $error = 'Ngày khởi hành không hợp lệ.';
    } else {

        // Retry khi MySQL gap deadlock.
        $maxAttempts = 3;
        $bookingCreated = false;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

            try {
                $pdo->beginTransaction();

                // Khoa tour truoc khi kiem tra so cho.
                $lockTour = $pdo->prepare(
                    "SELECT id, title, price, available_slots
                     FROM tours
                     WHERE id = ? AND status = 1
                     FOR UPDATE"
                );
                $lockTour->execute([$tour_id]);
                $lockedTour = $lockTour->fetch(PDO::FETCH_ASSOC);

                if (!$lockedTour) {
                    throw new RuntimeException(
                        'Tour không còn hoạt động.'
                    );
                }

                if (
                    (int)$lockedTour['available_slots'] <
                    $numberPeople
                ) {
                    throw new RuntimeException(
                        'Tour không còn đủ chỗ trống.'
                    );
                }

                // Kiem tra dot khoi hanh trong giao dich.
                if (count($departures) > 0) {
                    $lockDeparture = $pdo->prepare(
                        "SELECT id, available_slots
                         FROM tour_departures
                         WHERE tour_id = ?
                           AND departure_date = ?
                           AND status = 1
                         FOR UPDATE"
                    );

                    $lockDeparture->execute([
                        $tour_id,
                        $departureDate
                    ]);

                    $dep = $lockDeparture->fetch(PDO::FETCH_ASSOC);

                    if (!$dep) {
                        throw new RuntimeException(
                            'Đợt khởi hành không còn khả dụng.'
                        );
                    }

                    if (
                        (int)$dep['available_slots'] <
                        $numberPeople
                    ) {
                        throw new RuntimeException(
                            'Đợt khởi hành không đủ số chỗ.'
                        );
                    }
                }

                // Tim tai khoan cho booking.
                $currentUserId = $_SESSION['user_id'] ?? null;

                if (!$currentUserId) {
                    $checkUser = $pdo->prepare(
                        "SELECT id FROM users
                         WHERE LOWER(email) = LOWER(?)
                         LIMIT 1"
                    );
                    $checkUser->execute([$email]);

                    $existing = $checkUser->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        $currentUserId = (int)$existing['id'];
                    } else {
                        // Tao tai khoan khach khi chua ton tai.
                        $randomPassword = password_hash(
                            bin2hex(random_bytes(16)),
                            PASSWORD_DEFAULT
                        );

                        $createUser = $pdo->prepare(
                            "INSERT INTO users
                             (fullname, email, password, phone, role, status)
                             VALUES (?, ?, ?, ?, 'customer', 1)"
                        );

                        $createUser->execute([
                            $fullname,
                            strtolower($email),
                            $randomPassword,
                            $phone
                        ]);

                        $currentUserId = (int)$pdo->lastInsertId();
                    }
                }

                $totalPrice =
                    (float)$lockedTour['price'] * $numberPeople;

                $bookingCode = 'TB-' .
                    date('YmdHis') . '-' .
                    random_int(1000, 9999);

                // Luu booking.
                $insertBooking = $pdo->prepare(
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

                $insertBooking->execute([
                    $bookingCode,
                    $currentUserId,
                    $tour_id,
                    $departureDate,
                    $fullname,
                    $phone,
                    $email,
                    $emergency,
                    $numberPeople,
                    $totalPrice,
                    $notes
                ]);

                // Tru so cho cua tour - co dieu kien bao ve.
                $updateTour = $pdo->prepare(
                    "UPDATE tours
                     SET available_slots = available_slots - ?
                     WHERE id = ?
                       AND available_slots >= ?"
                );

                $updateTour->execute([
                    $numberPeople,
                    $tour_id,
                    $numberPeople
                ]);

                if ($updateTour->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Số chỗ vừa thay đổi. Vui lòng đặt lại.'
                    );
                }

                $pdo->commit();
                $bookingCreated = true;

                $transferContent = tr_transfer(
                    $tour_id,
                    $lockedTour['title'],
                    $fullname,
                    $phone
                );

                $_SESSION[$successKey] = [
                    'booking_code' => $bookingCode,
                    'tour_name' => $lockedTour['title'],
                    'departure_date' => $departureDate,
                    'fullname' => $fullname,
                    'phone' => $phone,
                    'number_people' => $numberPeople,
                    'total_price' => $totalPrice,
                    'transfer_content' => $transferContent
                ];

                header(
                    'Location: ' . $baseUrl .
                    'tours/tour-register.php?id=' . $tour_id .
                    '&payment=1'
                );
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (
                    tr_deadlock($e) &&
                    $attempt < $maxAttempts
                ) {
                    usleep(random_int(100000, 300000));
                    continue;
                }

                error_log(
                    'Tour booking error: ' . $e->getMessage()
                );

                if ($e instanceof RuntimeException) {
                    $error = $e->getMessage();
                } else {
                    $error =
                        'Không thể hoàn tất đăng ký lúc này. ' .
                        'Vui lòng thử lại sau.';
                }

                break;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.tour-registration {
    padding:40px 15px 65px;
    background:#f8fafc;
    min-height:70vh;
}
.tour-registration-card {
    max-width:760px;
    margin:0 auto;
    background:#fff;
    padding:35px;
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,.07);
}
.tour-registration h2 {
    color:#14532d;
    text-align:center;
    font-size:27px;
    margin-bottom:10px;
}
.tour-subtitle {
    text-align:center;
    color:#64748b;
    line-height:1.7;
    margin-bottom:28px;
}
.tour-form-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:17px;
}
.tour-field {margin-bottom:18px}
.tour-field label {
    display:block;
    font-weight:700;
    margin-bottom:9px;
    color:#0f172a;
}
.tour-field input,
.tour-field select,
.tour-field textarea {
    width:100%;
    border:1px solid #dbe3ed;
    border-radius:9px;
    padding:12px 15px;
    font:inherit;
    box-sizing:border-box;
}
.tour-field textarea {min-height:95px}
.tour-departure-box {
    background:#fff7ed;
    border:1px solid #fed7aa;
    border-radius:10px;
    padding:17px;
    margin-bottom:24px;
}
.tour-departure-box label {
    color:#c2410c;
    font-weight:800;
}
.tour-summary {
    padding:20px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    margin:18px 0;
}
.tour-summary-row {
    display:flex;
    justify-content:space-between;
    gap:12px;
    margin:10px 0;
}
.tour-total {
    font-size:21px;
    font-weight:800;
    color:#ea580c;
}
.tour-submit {
    width:100%;
    border:0;
    border-radius:11px;
    background:#ea580c;
    color:white;
    font-weight:800;
    font-size:17px;
    padding:15px;
    cursor:pointer;
}
.tour-submit:disabled {
    opacity:.65;
    cursor:wait;
}
.tour-alert {
    padding:16px;
    border-radius:10px;
    margin-bottom:20px;
}
.tour-alert.error {
    background:#fee2e2;color:#b91c1c;
}
.tour-alert.success {
    background:#dcfce7;color:#166534;
}
.tour-payment-info {
    padding:20px;
    background:#f8fafc;
    border-radius:12px;
    margin-bottom:22px;
}
.tour-payment-row {
    display:flex;
    justify-content:space-between;
    gap:15px;
    margin:12px 0;
}
.tour-payment-row strong {
    text-align:right;
}
.tour-payment-grid {
    display:grid;
    grid-template-columns:250px 1fr;
    gap:22px;
    align-items:start;
}
.tour-payment-qr {
    width:100%;
    height:auto;
    border-radius:13px;
    border:1px solid #fbcfe8;
}
.tour-transfer-box {
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:19px;
}
.tour-transfer-code {
    padding:14px;
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#c2410c;
    font-size:16px;
    font-weight:800;
    border-radius:10px;
    overflow-wrap:anywhere;
    margin:15px 0;
}
.tour-copy {
    background:#14532d;
    color:#fff;
    border:0;
    border-radius:10px;
    padding:12px 16px;
    font-weight:700;
    cursor:pointer;
}
.tour-payment-note {
    color:#64748b;
    font-size:14px;
    line-height:1.8;
    margin-top:16px;
}
.tour-actions {
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top:25px;
}
.tour-actions a {
    display:inline-block;
    padding:12px 18px;
    border-radius:10px;
    text-decoration:none;
    font-weight:700;
    background:#e2e8f0;
    color:#0f172a;
}
@media(max-width:700px) {
    .tour-registration-card {padding:20px}
    .tour-form-grid,
    .tour-payment-grid {grid-template-columns:1fr}
    .tour-payment-row {flex-direction:column;gap:4px}
    .tour-payment-row strong {text-align:left}
}
</style>

<section class="tour-registration">
<div class="tour-registration-card">

<?php if ($success): ?>

    <h2>ĐĂNG KÝ TOUR THÀNH CÔNG!</h2>

    <div class="tour-alert success">
        Hệ thống đã ghi nhận đăng ký của bạn.
        Vui lòng xem thông tin thanh toán bên dưới.
    </div>

    <div class="tour-payment-info">

        <div class="tour-payment-row">
            <span>Mã đăng ký</span>
            <strong>
                <?= tr_h($success['booking_code']) ?>
            </strong>
        </div>

        <div class="tour-payment-row">
            <span>Tour</span>
            <strong>
                <?= tr_h($success['tour_name']) ?>
            </strong>
        </div>

        <div class="tour-payment-row">
            <span>Ngày khởi hành</span>
            <strong>
                <?= date(
                    'd/m/Y',
                    strtotime($success['departure_date'])
                ) ?>
            </strong>
        </div>

        <div class="tour-payment-row">
            <span>Khách hàng</span>
            <strong>
                <?= tr_h($success['fullname']) ?>
            </strong>
        </div>

        <div class="tour-payment-row">
            <span>Số người</span>
            <strong>
                <?= (int)$success['number_people'] ?>
            </strong>
        </div>

        <div class="tour-payment-row">
            <span>Tổng tiền</span>
            <strong style="color:#ea580c;font-size:22px">
                <?= format_price($success['total_price']) ?>
            </strong>
        </div>

    </div>

    <h3 style="color:#14532d;margin-bottom:20px">
        THANH TOÁN BẰNG HÌNH THỨC CHUYỂN KHOẢN
    </h3>

    <div class="tour-payment-grid">

        <div>
            <img
                src="<?= tr_h($qrImage) ?>"
                alt="Mã QR chuyển khoản MoMo"
                class="tour-payment-qr"
            >
        </div>

        <div class="tour-transfer-box">

            <h3>Thông tin chuyển khoản</h3>

            <p>Số tiền cần chuyển:</p>

            <h2 style="text-align:left;color:#ea580c">
                <?= format_price($success['total_price']) ?>
            </h2>

            <p>
                <strong>
                    Nội dung chuyển khoản bắt buộc:
                </strong>
            </p>

            <div
                class="tour-transfer-code"
                id="tour-transfer-content"
            ><?= tr_h($success['transfer_content']) ?></div>

            <button
                type="button"
                class="tour-copy"
                onclick="copyTourTransfer()"
            >
                <i class="fa-solid fa-copy"></i>
                Sao chép nội dung
            </button>

            <div class="tour-payment-note">
                <strong>Lưu ý:</strong>
                <p>
                    Vui lòng chuyển khoản đúng số tiền và
                    nội dung hiển thị phía trên.
                </p>
                <p>
                    Đây là mã QR cố định. Hệ thống chưa
                    tự động kiểm tra giao dịch. Quản lý
                    sẽ đối chiếu thanh toán thủ công.
                </p>
                <p>
                    Trạng thái đăng ký hiện tại:
                    <strong>Chờ xác nhận</strong>.
                </p>
            </div>

        </div>

    </div>

    <div class="tour-actions">
        <a href="<?= $baseUrl ?>tours/my-tours.php">
            Xem tour tôi đã đăng ký
        </a>

        <a href="<?= $baseUrl ?>tours/tour.php">
            Khám phá tour khác
        </a>
    </div>

<?php else: ?>

    <h2>ĐĂNG KÝ THAM GIA TOUR</h2>

    <p class="tour-subtitle">
        Hành trình:
        <strong><?= tr_h($tour['title']) ?></strong>
    </p>

    <?php if ($error): ?>
        <div class="tour-alert error">
            <?= tr_h($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="tour-registration-form">

        <div class="tour-departure-box">
            <div class="tour-field" style="margin:0">

                <label>CHỌN NGÀY KHỞI HÀNH (CÁC ĐỢT TOUR CÓ SẴN):</label>

                <?php if ($departures): ?>

                    <select name="departure_date" required>

                        <?php foreach ($departures as $dep): ?>

                            <option
                                value="<?= tr_h($dep['departure_date']) ?>"
                                <?= $old['departure_date'] ===
                                    $dep['departure_date']
                                    ? 'selected' : '' ?>
                            >
                                Đợt khởi hành:
                                <?= date(
                                    'd/m/Y',
                                    strtotime($dep['departure_date'])
                                ) ?>
                                (Còn <?= (int)$dep['available_slots'] ?> chỗ)
                            </option>

                        <?php endforeach; ?>

                    </select>

                <?php else: ?>

                    <input
                        type="date"
                        name="departure_date"
                        required
                        min="<?= date('Y-m-d') ?>"
                        value="<?= tr_h($old['departure_date']) ?>"
                    >

                <?php endif; ?>

            </div>
        </div>

        <div class="tour-form-grid">

            <div class="tour-field">
                <label>Họ và tên *</label>
                <input
                    name="fullname"
                    required
                    value="<?= tr_h($old['fullname']) ?>"
                    placeholder="Nguyễn Văn A"
                >
            </div>

            <div class="tour-field">
                <label>Số điện thoại *</label>
                <input
                    name="phone"
                    type="tel"
                    required
                    value="<?= tr_h($old['phone']) ?>"
                    placeholder="09xxxxxxxx"
                >
            </div>

            <div class="tour-field">
                <label>Địa chỉ Email *</label>
                <input
                    name="email"
                    type="email"
                    required
                    value="<?= tr_h($old['email']) ?>"
                    placeholder="email@gmail.com"
                >
            </div>

            <div class="tour-field">
                <label>SĐT liên hệ khẩn cấp *</label>
                <input
                    name="emergency_contact"
                    type="tel"
                    required
                    value="<?= tr_h($old['emergency_contact']) ?>"
                    placeholder="09xxxxxxxx"
                >
            </div>

        </div>

        <div class="tour-field">
            <label>
                Số người tham gia *
                (Tour còn <?= (int)$tour['available_slots'] ?> chỗ)
            </label>

            <input
                type="number"
                name="number_people"
                id="num_people"
                min="1"
                max="<?= (int)$tour['available_slots'] ?>"
                value="<?= (int)$old['number_people'] ?>"
                required
            >
        </div>

        <div class="tour-field">
            <label>Ghi chú</label>
            <textarea
                name="notes"
                placeholder="Chế độ ăn, lưu ý sức khỏe..."
            ><?= tr_h($old['notes']) ?></textarea>
        </div>

        <div class="tour-summary">

            <div class="tour-summary-row">
                <span>Đơn giá / người:</span>
                <strong>
                    <?= format_price($tour['price']) ?>
                </strong>
            </div>

            <div class="tour-summary-row tour-total">
                <span>TỔNG TIỀN DỰ KIẾN:</span>
                <span id="display_total">
                    <?= format_price(
                        $tour['price'] * max(1, (int)$old['number_people'])
                    ) ?>
                </span>
            </div>

        </div>

        <button
            type="submit"
            class="tour-submit"
            id="tour-submit"
            <?= (int)$tour['available_slots'] < 1 ? 'disabled' : '' ?>
        >
            XÁC NHẬN ĐĂNG KÝ TOUR
        </button>

    </form>

<?php endif; ?>

</div>
</section>

<script>
const tourUnitPrice = <?= json_encode((float)$tour['price']) ?>;

const numInput = document.getElementById('num_people');
const displayTotal = document.getElementById('display_total');

if (numInput && displayTotal) {
    numInput.addEventListener('input', function () {
        const count = Math.max(1, parseInt(this.value, 10) || 1);
        const total = count * tourUnitPrice;

        displayTotal.textContent =
            total.toLocaleString('vi-VN') + ' ₫';
    });
}

const bookingForm =
    document.getElementById('tour-registration-form');

if (bookingForm) {
    bookingForm.addEventListener('submit', function () {
        const btn = document.getElementById('tour-submit');

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'ĐANG XỬ LÝ ĐĂNG KÝ...';
        }
    });
}

async function copyTourTransfer() {
    const el = document.getElementById(
        'tour-transfer-content'
    );

    if (!el) return;

    const text = el.textContent.trim();

    try {
        await navigator.clipboard.writeText(text);
        alert('Đã sao chép nội dung chuyển khoản!');
    } catch (e) {
        window.prompt('Sao chép nội dung:', text);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
