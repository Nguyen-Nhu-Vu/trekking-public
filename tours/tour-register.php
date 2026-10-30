
<?php
// VNN Travel - Dang ky tour va thanh toan QR
// So cho duoc tinh theo tung ngay khoi hanh.
// Chi customer va khach vang lai duoc dang ky.

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function vt_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function vt_money($v) {
    return number_format((float)$v, 0, ',', '.') . ' đ';
}

function vt_phone($v) {
    return preg_replace('/[^0-9]/', '', (string)$v);
}

function vt_token($v) {
    $v = strtr((string)$v, ['đ' => 'd', 'Đ' => 'D']);

    if (class_exists('Transliterator')) {
        $tr = Transliterator::create('Any-Latin; Latin-ASCII');
        if ($tr) $v = $tr->transliterate($v);
    } elseif (function_exists('iconv')) {
        $result = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if ($result !== false) $v = $result;
    }

    return preg_replace('/[^A-Z0-9]/', '', strtoupper($v));
}

function vt_valid_date($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
        return false;
    }

    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function vt_code(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tour_bookings'
          AND COLUMN_NAME = 'booking_code'
        LIMIT 1
    ");

    $length = (int)$stmt->fetchColumn();

    if ($length < 8) {
        throw new RuntimeException(
            'Cấu hình mã đặt tour không hợp lệ.'
        );
    }

    for ($i = 0; $i < 10; $i++) {
        $code = 'TB' . substr(
            strtoupper(bin2hex(random_bytes(12))),
            0,
            min(22, $length - 2)
        );

        $stmt = $pdo->prepare("
            SELECT id FROM tour_bookings
            WHERE booking_code = ? LIMIT 1
        ");
        $stmt->execute([$code]);

        if (!$stmt->fetchColumn()) return $code;
    }

    throw new RuntimeException('Không thể tạo mã đặt tour.');
}

function vt_departures(PDO $pdo, int $tourId) {
    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.departure_date,
            d.available_slots AS capacity,
            GREATEST(
                0,
                d.available_slots - COALESCE((
                    SELECT SUM(b.number_people)
                    FROM tour_bookings b
                    WHERE b.tour_id = d.tour_id
                      AND b.departure_date = d.departure_date
                      AND LOWER(TRIM(b.status)) IN (
                          'đã xác nhận',
                          'đã thanh toán',
                          'đã hoàn thành',
                          'confirmed',
                          'paid',
                          'completed'
                      )
                ), 0)
            ) AS remaining
        FROM tour_departures d
        WHERE d.tour_id = ?
          AND d.departure_date >= CURDATE()
          AND d.status = 1
        ORDER BY d.departure_date ASC
    ");

    $stmt->execute([$tourId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function vt_status(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tour_bookings'
          AND COLUMN_NAME = 'status'
        LIMIT 1
    ");

    $type = (string)$stmt->fetchColumn();

    if (stripos($type, 'enum(') !== 0) {
        return 'Chờ xác nhận';
    }

    preg_match_all("/'([^']*)'/", $type, $matches);

    foreach ([
        'Chờ xác nhận',
        'Chờ duyệt',
        'pending',
        'Pending'
    ] as $candidate) {
        if (in_array($candidate, $matches[1] ?? [], true)) {
            return $candidate;
        }
    }

    throw new RuntimeException(
        'Trạng thái đặt tour chưa tương thích.'
    );
}

function vt_retryable(Throwable $e) {
    return $e instanceof PDOException
        && in_array(
            (int)($e->errorInfo[1] ?? 0),
            [1205, 1213],
            true
        );
}

// ==========================================
// CAU HINH
// ==========================================

$base = rtrim(
    defined('BASE_URL') ? BASE_URL : '/',
    '/'
) . '/';

$qrImage = $base . 'assets/images/momo-qr-vu.jpg';

$tourId = (int)($_GET['id'] ?? 0);
$error = '';
$payment = null;
$success = false;

$stmt = $pdo->prepare("
    SELECT * FROM tours
    WHERE id = ? AND status = 1
    LIMIT 1
");
$stmt->execute([$tourId]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = $tour
    ? 'Đăng ký tour: ' . $tour['title']
    : 'Không tìm thấy tour';

$active_menu = 'tour';

$departures = $tour
    ? vt_departures($pdo, $tourId)
    : [];

// ==========================================
// KIEM TRA PHAN QUYEN DANG KY TOUR
// ==========================================

$user = null;
$blockedAccount = false;
$roleCheckError = '';

if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $blockedAccount = !$user ||
            strtolower(trim((string)($user['role'] ?? '')))
            !== 'customer';

    } catch (Throwable $e) {
        error_log('Tour role check: ' . $e->getMessage());

        $blockedAccount = true;
        $roleCheckError =
            'Không thể kiểm tra quyền tài khoản. Vui lòng thử lại sau.';
    }
}

$tokenKey = 'vnn_booking_token_' . $tourId;
$doneKey = 'vnn_last_booking_' . $tourId;

if (empty($_SESSION[$tokenKey])) {
    $_SESSION[$tokenKey] = bin2hex(random_bytes(16));
}

$defaultDate = '';

foreach ($departures as $dep) {
    if ((int)$dep['remaining'] > 0) {
        $defaultDate = $dep['departure_date'];
        break;
    }
}

$selectedDate = (string)(
    $_POST['departure_date']
    ?? $_GET['departure']
    ?? $defaultDate
);

$old = [
    'fullname' => $_POST['fullname']
        ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone']
        ?? ($user['phone'] ?? ''),
    'email' => $_POST['email']
        ?? ($user['email'] ?? ''),
    'emergency_contact' => $_POST['emergency_contact'] ?? '',
    'number_people' => $_POST['number_people'] ?? 1,
    'notes' => $_POST['notes'] ?? ''
];

// ==========================================
// XU LY DANG KY TOUR
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $tour &&
    !$blockedAccount
) {
    $token = (string)($_POST['booking_token'] ?? '');
    $name = trim((string)$old['fullname']);
    $phone = vt_phone($old['phone']);
    $email = trim((string)$old['email']);
    $emergency = vt_phone($old['emergency_contact']);
    $notes = trim((string)$old['notes']);

    $people = filter_var(
        $old['number_people'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (
        $token !== ''
        && !empty($_SESSION[$doneKey])
        && hash_equals(
            (string)$_SESSION[$doneKey]['token'],
            $token
        )
    ) {
        $payment = $_SESSION[$doneKey]['payment'];
        $success = true;

    } elseif (
        !$token
        || !hash_equals((string)$_SESSION[$tokenKey], $token)
    ) {
        $error = 'Phiên đăng ký đã hết hạn. Vui lòng tải lại trang.';

    } elseif (
        $name === ''
        || strlen($phone) < 9
        || strlen($emergency) < 9
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Vui lòng nhập đầy đủ thông tin hợp lệ.';

    } elseif (!vt_valid_date($selectedDate)) {
        $error = 'Ngày khởi hành không hợp lệ.';

    } elseif ($people === false) {
        $error = 'Số người tham gia phải là số nguyên lớn hơn 0.';

    } else {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    SELECT id, available_slots
                    FROM tour_departures
                    WHERE tour_id = ?
                      AND departure_date = ?
                      AND departure_date >= CURDATE()
                      AND status = 1
                    FOR UPDATE
                ");

                $stmt->execute([$tourId, $selectedDate]);
                $departure = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$departure) {
                    throw new RuntimeException(
                        'Ngày khởi hành đã chọn không còn khả dụng.'
                    );
                }

                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(number_people), 0)
                    FROM tour_bookings
                    WHERE tour_id = ?
                      AND departure_date = ?
                      AND LOWER(TRIM(status)) IN (
                          'đã xác nhận',
                          'đã thanh toán',
                          'đã hoàn thành',
                          'confirmed',
                          'paid',
                          'completed'
                      )
                ");

                $stmt->execute([$tourId, $selectedDate]);
                $booked = (int)$stmt->fetchColumn();

                $remaining = max(
                    0,
                    (int)$departure['available_slots'] - $booked
                );

                if ($remaining <= 0) {
                    throw new RuntimeException(
                        'Ngày khởi hành này đã hết chỗ. Vui lòng chọn ngày khác.'
                    );
                }

                if ($people > $remaining) {
                    throw new RuntimeException(
                        'Ngày ' .
                        date('d/m/Y', strtotime($selectedDate)) .
                        ' chỉ còn ' . $remaining .
                        ' chỗ. Vui lòng giảm số người tham gia.'
                    );
                }

                $bookingCode = vt_code($pdo);
                $totalPrice = (float)$tour['price'] * $people;
                $status = vt_status($pdo);

                $stmt = $pdo->prepare("
                    INSERT INTO tour_bookings
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
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $bookingCode,
                    $user ? (int)$user['id'] : null,
                    $tourId,
                    $selectedDate,
                    $name,
                    $phone,
                    $email,
                    $emergency,
                    $people,
                    $totalPrice,
                    $notes,
                    $status
                ]);

                $pdo->commit();

                $payment = [
                    'code' => $bookingCode,
                    'tour' => $tour['title'],
                    'date' => $selectedDate,
                    'name' => $name,
                    'phone' => $phone,
                    'people' => $people,
                    'total' => $totalPrice,
                    'transfer' =>
                        'TOUR ' .
                        $bookingCode . ' ' .
                        substr(vt_token($tour['title']), 0, 22) . ' ' .
                        vt_token($name) . ' ' .
                        $phone
                ];

                $success = true;

                $_SESSION[$doneKey] = [
                    'token' => $token,
                    'payment' => $payment
                ];

                $_SESSION[$tokenKey] = bin2hex(random_bytes(16));
                break;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (vt_retryable($e) && $attempt < 3) {
                    usleep(random_int(100000, 300000));
                    continue;
                }

                error_log('VNN Tour: ' . $e->getMessage());

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Không thể đăng ký tour. Vui lòng thử lại.';

                break;
            }
        }

        if (!$success) {
            $departures = vt_departures($pdo, $tourId);
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.vt-page {
    min-height: 75vh;
    padding: 36px 16px 70px;
    background: #f8fafc;
}
.vt-card {
    max-width: 760px;
    margin: auto;
    padding: 34px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 26px rgba(0,0,0,.07);
}
.vt-title {
    text-align: center;
    font-size: 27px;
    font-weight: 800;
    color: #14532d;
    margin: 0 0 12px;
}
.vt-subtitle {
    text-align: center;
    line-height: 1.7;
    color: #64748b;
    margin-bottom: 28px;
}
.vt-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 16px;
    margin: 18px 0;
    border-radius: 10px;
    line-height: 1.7;
}
.vt-ok {
    padding: 16px;
    border-radius: 10px;
    background: #dcfce7;
    color: #166534;
    line-height: 1.7;
    margin: 20px 0;
}
.vt-departure {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 11px;
    padding: 17px;
    margin-bottom: 23px;
}
.vt-departure label {
    display: block;
    font-weight: 800;
    color: #c2410c;
    margin-bottom: 11px;
}
.vt-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 17px;
}
.vt-field {
    margin-bottom: 18px;
}
.vt-field label {
    display: block;
    color: #0f172a;
    margin-bottom: 8px;
    font-weight: 700;
}
.vt-field input,
.vt-field textarea,
.vt-field select {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #dbe3ed;
    border-radius: 9px;
    padding: 12px;
    font: inherit;
    background: #fff;
}
.vt-field textarea {
    min-height: 95px;
    resize: vertical;
}
.vt-inline-error {
    color: #b91c1c;
    font-size: 14px;
    margin-top: 8px;
    display: none;
}
.vt-summary {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 11px;
    padding: 20px;
    margin: 24px 0;
}
.vt-row {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 15px;
    margin: 13px 0;
}
.vt-total {
    color: #ea580c;
    font-size: 21px;
    font-weight: 800;
}
.vt-button {
    width: 100%;
    padding: 16px;
    border: 0;
    border-radius: 11px;
    background: #ea580c;
    color: white;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
}
.vt-button:disabled {
    opacity: .55;
    cursor: not-allowed;
}
.vt-back {
    display: inline-block;
    text-decoration: none;
    font-weight: 700;
    color: #14532d;
    margin-top: 24px;
}
.vt-pay-button {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 9px;
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 11px;
    background: #166534;
    color: #fff;
    font-size: 17px;
    font-weight: 800;
    cursor: pointer;
    transition: background .2s, transform .2s;
}
.vt-pay-button:hover {
    background: #15803d;
    transform: translateY(-1px);
}
.vt-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: rgba(15,23,42,.68);
    backdrop-filter: blur(5px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}
.vt-modal-overlay.open {
    display: flex;
}
.vt-modal {
    position: relative;
    width: 100%;
    max-width: 740px;
    max-height: 90vh;
    overflow-y: auto;
    background: #fff;
    padding: 30px;
    border-radius: 18px;
    box-sizing: border-box;
    box-shadow: 0 25px 80px rgba(0,0,0,.3);
    animation: vtModalAppear .2s ease;
}
@keyframes vtModalAppear {
    from { opacity: 0; transform: translateY(12px) scale(.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.vt-modal-close {
    position: absolute;
    top: 13px;
    right: 13px;
    width: 38px;
    height: 38px;
    background: #f1f5f9;
    color: #334155;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 23px;
    line-height: 1;
}
.vt-modal-close:hover {
    background: #e2e8f0;
}
.vt-modal-title {
    text-align: center;
    color: #14532d;
    font-size: 22px;
    font-weight: 800;
    padding: 0 35px;
    margin: 0 0 24px;
}
.vt-payment {
    display: grid;
    grid-template-columns: minmax(0,250px) minmax(0,1fr);
    gap: 20px;
    align-items: start;
}
.vt-qr {
    display: block;
    width: 100%;
    height: auto;
    border: 1px solid #fbcfe8;
    border-radius: 12px;
}
.vt-paybox {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}
.vt-paybox h3 {
    font-size: 17px;
    margin: 0 0 13px;
    color: #0f172a;
}
.vt-transfer {
    margin: 14px 0;
    padding: 14px;
    border-radius: 9px;
    border: 1px solid #fed7aa;
    background: #fff7ed;
    color: #c2410c;
    font-size: 14px;
    font-weight: 800;
    overflow-wrap: anywhere;
    user-select: all;
}
.vt-copy {
    background: #14532d;
    color: white;
    border: 0;
    border-radius: 8px;
    padding: 12px 16px;
    cursor: pointer;
    font-weight: 700;
}
.vt-note {
    margin-top: 17px;
    font-size: 13px;
    color: #475569;
    line-height: 1.8;
}
.vt-modal-footer {
    margin-top: 21px;
    display: flex;
    justify-content: center;
}
.vt-modal-done {
    border: 0;
    border-radius: 9px;
    padding: 12px 28px;
    background: #e2e8f0;
    color: #334155;
    font-weight: 700;
    cursor: pointer;
}
body.vt-modal-active {
    overflow: hidden;
}
@media(max-width:700px) {
    .vt-card { padding: 20px; }
    .vt-grid,
    .vt-payment { grid-template-columns: 1fr; }
    .vt-row { gap: 8px; }
    .vt-title { font-size: 23px; }
    .vt-modal { padding: 24px 16px; }
    .vt-payment { max-width: 100%; }
    .vt-qr { max-width: 270px; margin: auto; }
}
</style>

<section class="vt-page">
<div class="vt-card">

<?php if ($blockedAccount): ?>

    <h2 class="vt-title" style="color:#b91c1c">
        Tài khoản không có quyền đăng ký tour
    </h2>

    <div style="text-align:center">
        <a class="vt-back"
           href="<?= vt_h($base) ?>tours/tour.php">
            Quay về danh sách tour
        </a>
    </div>

<?php elseif (!$tour): ?>

    <h2 class="vt-title">Không tìm thấy tour</h2>
    <p>Tour không tồn tại hoặc không còn hoạt động.</p>

    <a class="vt-back"
       href="<?= vt_h($base) ?>tours/tour.php">
        Quay lại danh sách tour
    </a>

<?php elseif ($success && $payment): ?>

    <h2 class="vt-title">
        ĐĂNG KÝ TOUR THÀNH CÔNG!
    </h2>

    <div class="vt-ok">
        <i class="fa-solid fa-circle-check"></i>
        Đăng ký của bạn đã được ghi nhận thành công.
        Vui lòng chọn Thanh toán để xem thông tin chuyển khoản.
    </div>

    <div class="vt-summary">
        <div class="vt-row">
            <span>Mã đặt tour</span>
            <strong><?= vt_h($payment['code']) ?></strong>
        </div>

        <div class="vt-row">
            <span>Tên tour</span>
            <strong><?= vt_h($payment['tour']) ?></strong>
        </div>

        <div class="vt-row">
            <span>Ngày khởi hành</span>
            <strong><?= date('d/m/Y', strtotime($payment['date'])) ?></strong>
        </div>

        <div class="vt-row">
            <span>Khách hàng</span>
            <strong><?= vt_h($payment['name']) ?></strong>
        </div>

        <div class="vt-row">
            <span>Số điện thoại</span>
            <strong><?= vt_h($payment['phone']) ?></strong>
        </div>

        <div class="vt-row">
            <span>Số người tham gia</span>
            <strong><?= (int)$payment['people'] ?></strong>
        </div>

        <div class="vt-row vt-total">
            <span>Tổng tiền</span>
            <strong><?= vt_money($payment['total']) ?></strong>
        </div>
    </div>

    <button
        type="button"
        class="vt-pay-button"
        onclick="openPaymentModal()"
    >
        <i class="fa-solid fa-credit-card"></i>
        THANH TOÁN
    </button>

    <div style="text-align:center">
        <a class="vt-back"
           href="<?= vt_h($base) ?>tours/my-tours.php">
            <i class="fa-solid fa-list"></i>
            Xem tour tôi đã đăng ký
        </a>
    </div>

<?php else: ?>

    <h2 class="vt-title">
        ĐĂNG KÝ THAM GIA TOUR
    </h2>

    <p class="vt-subtitle">
        Hành trình:
        <strong><?= vt_h($tour['title']) ?></strong>
    </p>

    <?php if ($error !== ''): ?>
        <div class="vt-error" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= vt_h($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$departures || !$defaultDate): ?>

        <div class="vt-error">
            Hiện không có ngày khởi hành còn chỗ.
            Vui lòng chọn tour khác hoặc quay lại sau.
        </div>

    <?php else: ?>

    <form method="POST" id="vtForm">
        <input
            type="hidden"
            name="booking_token"
            value="<?= vt_h($_SESSION[$tokenKey]) ?>"
        >

        <div class="vt-departure vt-field">
            <label for="vtDeparture">
                <i class="fa-solid fa-calendar-check"></i>
                CHỌN NGÀY KHỞI HÀNH (CÁC ĐỢT TOUR CÓ SẴN):
            </label>

            <select
                name="departure_date"
                id="vtDeparture"
                required
            >
                <?php foreach ($departures as $dep): ?>
                    <?php
                    $date = (string)$dep['departure_date'];
                    $remaining = (int)$dep['remaining'];
                    ?>

                    <option
                        value="<?= vt_h($date) ?>"
                        data-remaining="<?= $remaining ?>"
                        <?= $date === $selectedDate ? 'selected' : '' ?>
                        <?= $remaining <= 0 ? 'disabled' : '' ?>
                    >
                        Đợt khởi hành:
                        <?= date('d/m/Y', strtotime($date)) ?>
                        —
                        <?= $remaining > 0
                            ? 'Còn ' . $remaining . ' chỗ'
                            : 'Đã hết chỗ' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="vt-grid">
            <div class="vt-field">
                <label>Họ và tên *</label>
                <input
                    type="text"
                    name="fullname"
                    required
                    value="<?= vt_h($old['fullname']) ?>"
                    placeholder="Nguyễn Văn A"
                >
            </div>

            <div class="vt-field">
                <label>Số điện thoại *</label>
                <input
                    type="tel"
                    name="phone"
                    required
                    value="<?= vt_h($old['phone']) ?>"
                    placeholder="09xxxxxxxx"
                >
            </div>

            <div class="vt-field">
                <label>Địa chỉ Email *</label>
                <input
                    type="email"
                    name="email"
                    required
                    value="<?= vt_h($old['email']) ?>"
                    placeholder="email@gmail.com"
                >
            </div>

            <div class="vt-field">
                <label>SĐT liên hệ khẩn cấp *</label>
                <input
                    type="tel"
                    name="emergency_contact"
                    required
                    value="<?= vt_h($old['emergency_contact']) ?>"
                    placeholder="Số điện thoại người thân"
                >
            </div>
        </div>

        <div class="vt-field">
            <label for="vtPeople" id="vtPeopleLabel">
                Số người tham gia *
            </label>

            <input
                type="number"
                name="number_people"
                id="vtPeople"
                min="1"
                step="1"
                required
                value="<?= max(1, (int)$old['number_people']) ?>"
            >

            <div
                class="vt-inline-error"
                id="vtPeopleError"
                role="alert"
            ></div>
        </div>

        <div class="vt-field">
            <label>
                Ghi chú (chế độ ăn, kích thước áo, lưu ý sức khỏe...)
            </label>

            <textarea name="notes" rows="3"><?= vt_h($old['notes']) ?></textarea>
        </div>

        <div class="vt-summary">
            <div class="vt-row">
                <span>Đơn giá / người:</span>
                <strong><?= vt_money($tour['price']) ?></strong>
            </div>

            <div class="vt-row vt-total">
                <span>TỔNG TIỀN DỰ KIẾN:</span>
                <strong id="vtTotal">
                    <?= vt_money(
                        (float)$tour['price'] *
                        max(1, (int)$old['number_people'])
                    ) ?>
                </strong>
            </div>
        </div>

        <button
            type="submit"
            id="vtSubmit"
            class="vt-button"
        >
            <i class="fa-solid fa-check"></i>
            XÁC NHẬN ĐĂNG KÝ TOUR
        </button>
    </form>

    <?php endif; ?>

<?php endif; ?>

</div>
</section>

<?php if ($success && $payment && !$blockedAccount): ?>

<div
    class="vt-modal-overlay"
    id="vtPaymentModal"
    role="presentation"
    onclick="if(event.target===this)closePaymentModal()"
>
    <div
        class="vt-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="vtPaymentTitle"
    >
        <button
            type="button"
            class="vt-modal-close"
            onclick="closePaymentModal()"
            aria-label="Đóng cửa sổ thanh toán"
        >
            &times;
        </button>

        <h2 class="vt-modal-title" id="vtPaymentTitle">
            <i class="fa-solid fa-credit-card"></i>
            THÔNG TIN THANH TOÁN
        </h2>

        <div class="vt-payment">
            <div>
                <img
                    src="<?= vt_h($qrImage) ?>"
                    class="vt-qr"
                    alt="Mã QR MoMo thanh toán tour"
                >
            </div>

            <div class="vt-paybox">
                <h3>Thanh toán bằng hình thức chuyển khoản</h3>
                <p>Số tiền cần chuyển:</p>

                <div class="vt-total">
                    <?= vt_money($payment['total']) ?>
                </div>

                <p style="margin-top:18px">
                    <strong>
                        Nội dung chuyển khoản bắt buộc:
                    </strong>
                </p>

                <div
                    class="vt-transfer"
                    id="vtTransfer"
                ><?= vt_h($payment['transfer']) ?></div>

                <button
                    type="button"
                    class="vt-copy"
                    onclick="copyTransfer()"
                >
                    <i class="fa-solid fa-copy"></i>
                    Sao chép nội dung
                </button>

                <p class="vt-note">
                    Vui lòng chuyển đúng số tiền và
                    nội dung chuyển khoản. Nếu đã thanh toán,
                    bạn không cần chuyển lại.
                    Hãy chờ bộ phận quản lý kiểm tra
                    và xác nhận giao dịch.
                </p>
            </div>
        </div>

        <div class="vt-modal-footer">
            <button
                type="button"
                class="vt-modal-done"
                onclick="closePaymentModal()"
            >
                Đóng
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
(function() {
    const form = document.getElementById('vtForm');
    const departure = document.getElementById('vtDeparture');
    const people = document.getElementById('vtPeople');
    const label = document.getElementById('vtPeopleLabel');
    const error = document.getElementById('vtPeopleError');
    const total = document.getElementById('vtTotal');
    const submit = document.getElementById('vtSubmit');

    if (!form || !departure || !people) return;

    const price = <?= json_encode((float)($tour['price'] ?? 0)) ?>;

    function getRemaining() {
        const option =
            departure.options[departure.selectedIndex];

        return option
            ? Number(option.dataset.remaining || 0)
            : 0;
    }

    function update() {
        const slots = getRemaining();
        const count = Number(people.value);

        people.max = String(slots);

        label.textContent =
            'Số người tham gia * (Còn trống ' +
            slots + ' chỗ)';

        const valid =
            Number.isInteger(count)
            && count >= 1
            && count <= slots;

        if (!valid) {
            error.style.display = 'block';

            if (slots <= 0) {
                error.textContent =
                    'Ngày khởi hành này đã hết chỗ.';
            } else if (count > slots) {
                error.textContent =
                    'Ngày đã chọn chỉ còn ' + slots +
                    ' chỗ. Vui lòng giảm số người tham gia.';
            } else {
                error.textContent =
                    'Vui lòng nhập số người từ 1 đến ' +
                    slots + '.';
            }
        } else {
            error.style.display = 'none';
            error.textContent = '';
        }

        total.textContent =
            (Math.max(0, count || 0) * price)
                .toLocaleString('vi-VN') + ' đ';

        submit.disabled = !valid;
        return valid;
    }

    departure.addEventListener('change', function() {
        people.value = getRemaining() > 0 ? '1' : '';
        update();
    });

    people.addEventListener('input', update);

    form.addEventListener('submit', function(event) {
        if (!update()) {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) return;

        submit.disabled = true;
        submit.textContent = 'ĐANG XỬ LÝ ĐĂNG KÝ...';
    });

    if (getRemaining() <= 0) {
        const available = Array.from(departure.options)
            .find(opt => Number(opt.dataset.remaining) > 0);

        if (available) {
            departure.value = available.value;
            people.value = '1';
        }
    }

    update();
})();

const vtModal = document.getElementById('vtPaymentModal');
let vtPreviousFocus = null;

function openPaymentModal() {
    if (!vtModal) return;

    vtPreviousFocus = document.activeElement;

    vtModal.classList.add('open');
    document.body.classList.add('vt-modal-active');

    const closeBtn = vtModal.querySelector('.vt-modal-close');

    if (closeBtn) closeBtn.focus();
}

function closePaymentModal() {
    if (!vtModal) return;

    vtModal.classList.remove('open');
    document.body.classList.remove('vt-modal-active');

    if (
        vtPreviousFocus &&
        typeof vtPreviousFocus.focus === 'function'
    ) {
        vtPreviousFocus.focus();
    }
}

document.addEventListener('keydown', function(e) {
    if (!vtModal || !vtModal.classList.contains('open')) {
        return;
    }

    if (e.key === 'Escape') {
        e.preventDefault();
        closePaymentModal();
    }

    if (e.key === 'Tab') {
        const buttons = Array.from(
            vtModal.querySelectorAll('button:not([disabled])')
        );

        if (!buttons.length) return;

        const first = buttons[0];
        const last = buttons[buttons.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
});

async function copyTransfer() {
    const element = document.getElementById('vtTransfer');

    if (!element) return;

    const content = element.textContent.trim();

    try {
        await navigator.clipboard.writeText(content);
        alert('Đã sao chép nội dung chuyển khoản!');
    } catch (e) {
        window.prompt(
            'Sao chép nội dung chuyển khoản:',
            content
        );
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
