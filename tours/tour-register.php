
<?php
// VNN Travel - Dang ky Tour va thanh toan
// Khong redirect sau POST, tranh loi headers already sent.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function vtr_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function vtr_money($v) {
    return number_format((float)$v, 0, ',', '.') . ' đ';
}

function vtr_phone($v) {
    return preg_replace('/\D+/', '', (string)$v);
}

function vtr_token($v) {
    $v = strtr((string)$v, ['đ' => 'd', 'Đ' => 'D']);
    if (class_exists('Transliterator')) {
        $t = Transliterator::create('Any-Latin; Latin-ASCII');
        if ($t) $v = $t->transliterate($v);
    } else {
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
    }
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($v));
}

function vtr_code(PDO $pdo) {
    $s = $pdo->query(
        "SHOW COLUMNS FROM tour_bookings LIKE 'booking_code'"
    );
    $column = $s->fetch(PDO::FETCH_ASSOC);
    if (!$column || !preg_match(
        '/^(?:var)?char\((\d+)\)/i',
        $column['Type'],
        $m
    )) {
        throw new RuntimeException('Không xác định được cột booking_code.');
    }
    $length = (int)$m[1];
    if ($length < 8) {
        throw new RuntimeException('Cột booking_code quá ngắn.');
    }
    for ($i = 0; $i < 12; $i++) {
        $code = 'TB' . substr(
            strtoupper(bin2hex(random_bytes(12))),
            0,
            min($length - 2, 24)
        );
        $check = $pdo->prepare(
            "SELECT id FROM tour_bookings WHERE booking_code=? LIMIT 1"
        );
        $check->execute([$code]);
        if (!$check->fetch()) return $code;
    }
    throw new RuntimeException('Không tạo được mã đặt tour.');
}

function vtr_retryable(Throwable $e) {
    if (!($e instanceof PDOException)) return false;
    return in_array((int)($e->errorInfo[1] ?? 0), [1205,1213], true)
        || (string)$e->getCode() === '40001';
}

$base = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') . '/';
$qrImage = $base . 'assets/images/momo-qr-vu.jpg';
$tourId = (int)($_GET['id'] ?? 0);
$error = '';
$payment = null;
$success = false;

$tour = null;
if ($tourId > 0) {
    $s = $pdo->prepare("SELECT * FROM tours WHERE id=?");
    $s->execute([$tourId]);
    $tour = $s->fetch(PDO::FETCH_ASSOC);
}

$page_title = $tour
    ? 'Đăng ký tour: ' . $tour['title']
    : 'Không tìm thấy tour';
$active_menu = 'tour';

$departures = [];
$user = null;

if ($tour) {
    $s = $pdo->prepare(
        "SELECT * FROM tour_departures
         WHERE tour_id=? AND departure_date >= CURDATE()
           AND status=1
         ORDER BY departure_date"
    );
    $s->execute([$tourId]);
    $departures = $s->fetchAll(PDO::FETCH_ASSOC);
}

if (!empty($_SESSION['user_id'])) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $s->execute([(int)$_SESSION['user_id']]);
    $user = $s->fetch(PDO::FETCH_ASSOC);
}

$tokenKey = 'vnn_booking_token_' . $tourId;
$doneKey = 'vnn_last_booking_' . $tourId;

if (empty($_SESSION[$tokenKey])) {
    $_SESSION[$tokenKey] = bin2hex(random_bytes(16));
}

$old = [
    'fullname' => $_POST['fullname'] ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'emergency_contact' => $_POST['emergency_contact'] ?? '',
    'departure_date' => $_POST['departure_date']
        ?? $_GET['departure']
        ?? ($departures[0]['departure_date'] ?? ''),
    'number_people' => $_POST['number_people'] ?? 1,
    'notes' => $_POST['notes'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tour) {
    $submittedToken = (string)($_POST['booking_token'] ?? '');
    $name = trim((string)$old['fullname']);
    $phone = vtr_phone($old['phone']);
    $email = trim((string)$old['email']);
    $emergency = vtr_phone($old['emergency_contact']);
    $date = trim((string)$old['departure_date']);
    $people = (int)$old['number_people'];
    $notes = trim((string)$old['notes']);

    if (
        $submittedToken &&
        !empty($_SESSION[$doneKey]) &&
        hash_equals(
            (string)($_SESSION[$doneKey]['token'] ?? ''),
            $submittedToken
        )
    ) {
        $payment = $_SESSION[$doneKey]['payment'];
        $success = true;
    } elseif (
        !$submittedToken ||
        !hash_equals($_SESSION[$tokenKey], $submittedToken)
    ) {
        $error = 'Phiên đặt tour đã hết hạn. Vui lòng tải lại trang.';
    } elseif (
        !$name || strlen($phone) < 9 ||
        strlen($emergency) < 9 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Vui lòng nhập đầy đủ thông tin hợp lệ.';
    } elseif ($people < 1 || $people > 100) {
        $error = 'Số người tham gia không hợp lệ.';
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
        !checkdate(
            (int)substr($date,5,2),
            (int)substr($date,8,2),
            (int)substr($date,0,4)
        ) ||
        $date < date('Y-m-d')
    ) {
        $error = 'Ngày khởi hành không hợp lệ.';
    } elseif (!$user) {
        $error = 'Vui lòng đăng nhập tài khoản để đăng ký tour.';
    } else {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $pdo->beginTransaction();

                $s = $pdo->prepare(
                    "SELECT * FROM tours
                     WHERE id=? AND status=1 FOR UPDATE"
                );
                $s->execute([$tourId]);
                $locked = $s->fetch(PDO::FETCH_ASSOC);

                if (!$locked) {
                    throw new RuntimeException('Tour không còn hoạt động.');
                }

                if ((int)$locked['available_slots'] < $people) {
                    throw new RuntimeException(
                        'Tour không đủ chỗ trống.'
                    );
                }

                if ($departures) {
                    $s = $pdo->prepare(
                        "SELECT * FROM tour_departures
                         WHERE tour_id=? AND departure_date=?
                           AND status=1 FOR UPDATE"
                    );
                    $s->execute([$tourId, $date]);
                    $dep = $s->fetch(PDO::FETCH_ASSOC);

                    if (!$dep || (int)$dep['available_slots'] < $people) {
                        throw new RuntimeException(
                            'Đợt khởi hành không đủ chỗ.'
                        );
                    }
                }

                $code = vtr_code($pdo);
                $total = (float)$locked['price'] * $people;

                $s = $pdo->prepare(
                    "INSERT INTO tour_bookings
                    (booking_code,user_id,tour_id,departure_date,
                     fullname,phone,email,emergency_contact,
                     number_people,total_price,notes,status)
                    VALUES
                    (?,?,?,?,?,?,?,?,?,?,?,'Chờ xác nhận')"
                );

                $s->execute([
                    $code, (int)$user['id'], $tourId, $date,
                    $name, $phone, $email, $emergency,
                    $people, $total, $notes
                ]);

                $s = $pdo->prepare(
                    "UPDATE tours
                     SET available_slots=available_slots-?
                     WHERE id=? AND available_slots>=?"
                );
                $s->execute([$people, $tourId, $people]);

                if ($s->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Không giữ được chỗ. Vui lòng thử lại.'
                    );
                }

                $pdo->commit();

                $payment = [
                    'code' => $code,
                    'tour' => $locked['title'],
                    'date' => $date,
                    'name' => $name,
                    'phone' => $phone,
                    'people' => $people,
                    'total' => $total,
                    'transfer' => 'TOUR ' . $code . ' ' .
                        substr(vtr_token($locked['title']),0,22) . ' ' .
                        vtr_token($name) . ' ' . $phone
                ];

                $success = true;

                $_SESSION[$doneKey] = [
                    'token' => $submittedToken,
                    'payment' => $payment
                ];
                $_SESSION[$tokenKey] = bin2hex(random_bytes(16));
                break;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (vtr_retryable($e) && $attempt < 3) {
                    usleep(random_int(100000,300000));
                    continue;
                }

                error_log('VNN booking: ' . $e->getMessage());
                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Đặt tour chưa thành công. Vui lòng thử lại.';
                break;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.vtr-page {
    background:#f8fafc;
    padding:36px 16px 70px;
}
.vtr-card {
    max-width:760px;
    margin:auto;
    padding:34px;
    background:#fff;
    border-radius:15px;
    box-shadow:0 8px 24px #00000012;
}
.vtr-card h2 {
    text-align:center;
    color:#14532d;
    font-size:27px;
    margin-bottom:12px;
}
.vtr-subtitle {
    text-align:center;
    color:#64748b;
    line-height:1.6;
    margin-bottom:28px;
}
.vtr-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:17px;
}
.vtr-field {margin-bottom:18px}
.vtr-field label {
    display:block;
    font-weight:700;
    margin-bottom:8px;
}
.vtr-field input,
.vtr-field select,
.vtr-field textarea {
    width:100%;
    padding:12px;
    border:1px solid #dbe3ed;
    border-radius:9px;
    box-sizing:border-box;
    font:inherit;
}
.vtr-field textarea {min-height:90px}
.vtr-departure {
    padding:17px;
    border:1px solid #fed7aa;
    background:#fff7ed;
    border-radius:11px;
    margin-bottom:24px;
}
.vtr-summary {
    padding:18px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:11px;
    margin:20px 0;
}
.vtr-row {
    display:flex;
    justify-content:space-between;
    gap:12px;
    margin:12px 0;
}
.vtr-total {
    color:#ea580c;
    font-weight:800;
    font-size:21px;
}
.vtr-button {
    display:block;
    width:100%;
    padding:15px;
    background:#ea580c;
    border:0;
    border-radius:11px;
    color:white;
    font-weight:800;
    cursor:pointer;
}
.vtr-button:disabled {opacity:.65}
.vtr-alert {
    padding:16px;
    border-radius:10px;
    margin-bottom:20px;
}
.vtr-error {
    background:#fee2e2;
    color:#b91c1c;
}
.vtr-ok {
    background:#dcfce7;
    color:#166534;
}
.vtr-payment {
    display:grid;
    grid-template-columns:245px 1fr;
    gap:22px;
    align-items:start;
    margin-top:24px;
}
.vtr-qr {
    width:100%;
    height:auto;
    border:1px solid #fbcfe8;
    border-radius:13px;
}
.vtr-paybox {
    padding:18px;
    border:1px solid #e2e8f0;
    border-radius:12px;
}
.vtr-code {
    padding:14px;
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#c2410c;
    border-radius:10px;
    font-weight:800;
    overflow-wrap:anywhere;
    margin:14px 0;
}
.vtr-copy {
    padding:12px 16px;
    background:#14532d;
    color:white;
    border:0;
    border-radius:9px;
    cursor:pointer;
}
.vtr-note {
    color:#64748b;
    font-size:14px;
    line-height:1.7;
    margin-top:15px;
}
@media(max-width:700px) {
    .vtr-card {padding:20px}
    .vtr-grid,.vtr-payment {grid-template-columns:1fr}
    .vtr-row {flex-direction:column;gap:4px}
}
</style>

<section class="vtr-page">
<div class="vtr-card">

<?php if (!$tour): ?>

<h2>Không tìm thấy tour</h2>
<p>Tour không tồn tại hoặc đã được gỡ khỏi hệ thống.</p>
<a href="<?= vtr_h($base) ?>tours/tour.php">Quay lại danh sách tour</a>

<?php elseif ($success && $payment): ?>

<h2>ĐĂNG KÝ TOUR THÀNH CÔNG!</h2>

<div class="vtr-alert vtr-ok">
    Đã lưu đăng ký tour vào hệ thống.
    Vui lòng xem hướng dẫn chuyển khoản bên dưới.
</div>

<div class="vtr-summary">
    <div class="vtr-row">
        <span>Mã đặt tour</span>
        <strong><?= vtr_h($payment['code']) ?></strong>
    </div>
    <div class="vtr-row">
        <span>Tên tour</span>
        <strong><?= vtr_h($payment['tour']) ?></strong>
    </div>
    <div class="vtr-row">
        <span>Ngày khởi hành</span>
        <strong><?= date('d/m/Y',strtotime($payment['date'])) ?></strong>
    </div>
    <div class="vtr-row">
        <span>Khách hàng</span>
        <strong><?= vtr_h($payment['name']) ?></strong>
    </div>
    <div class="vtr-row">
        <span>Số điện thoại</span>
        <strong><?= vtr_h($payment['phone']) ?></strong>
    </div>
    <div class="vtr-row">
        <span>Số người</span>
        <strong><?= (int)$payment['people'] ?></strong>
    </div>
    <div class="vtr-row vtr-total">
        <span>Tổng tiền</span>
        <strong><?= vtr_money($payment['total']) ?></strong>
    </div>
</div>

<h3>THANH TOÁN BẰNG HÌNH THỨC CHUYỂN KHOẢN</h3>

<div class="vtr-payment">
    <img class="vtr-qr"
         src="<?= vtr_h($qrImage) ?>"
         alt="Mã QR thanh toán MoMo">

    <div class="vtr-paybox">
        <h3>Thông tin thanh toán</h3>

        <p>Số tiền cần chuyển:</p>
        <h2 style="text-align:left;color:#ea580c">
            <?= vtr_money($payment['total']) ?>
        </h2>

        <p><strong>Nội dung chuyển khoản:</strong></p>

        <div id="vtr-transfer" class="vtr-code">
            <?= vtr_h($payment['transfer']) ?>
        </div>

        <button class="vtr-copy" type="button"
                onclick="copyVtrTransfer()">
            Sao chép nội dung
        </button>

        <p class="vtr-note">
            Hãy chuyển khoản đúng số tiền và nội dung trên.
            Việc đăng ký thành công không có nghĩa đã thanh toán.
            Quản lý sẽ kiểm tra và xác nhận giao dịch thủ công.
        </p>
    </div>
</div>

<p style="margin-top:24px">
    <a href="<?= vtr_h($base) ?>tours/my-tours.php">
        Xem tour tôi đã đăng ký
    </a>
</p>

<?php else: ?>

<h2>ĐĂNG KÝ THAM GIA TOUR</h2>

<p class="vtr-subtitle">
    Hành trình: <strong><?= vtr_h($tour['title']) ?></strong>
</p>

<?php if ($error): ?>
<div class="vtr-alert vtr-error"><?= vtr_h($error) ?></div>
<?php endif; ?>

<form method="POST" id="vtr-form">

<input type="hidden" name="booking_token"
       value="<?= vtr_h($_SESSION[$tokenKey]) ?>">

<div class="vtr-departure vtr-field">
    <label>CHỌN NGÀY KHỞI HÀNH (CÁC ĐỢT TOUR CÓ SẴN):</label>

    <?php if ($departures): ?>
        <select name="departure_date" required>
        <?php foreach ($departures as $dep): ?>
            <option value="<?= vtr_h($dep['departure_date']) ?>"
                <?= $old['departure_date'] === $dep['departure_date']
                    ? 'selected' : '' ?>>
                Đợt khởi hành:
                <?= date('d/m/Y',strtotime($dep['departure_date'])) ?>
                (Còn <?= (int)$dep['available_slots'] ?> chỗ)
            </option>
        <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="date" name="departure_date"
               min="<?= date('Y-m-d') ?>"
               value="<?= vtr_h(
                   $old['departure_date'] ?: date('Y-m-d',strtotime('+3 days'))
               ) ?>" required>
    <?php endif; ?>
</div>

<div class="vtr-grid">
    <div class="vtr-field">
        <label>Họ và tên *</label>
        <input name="fullname" required
               value="<?= vtr_h($old['fullname']) ?>">
    </div>
    <div class="vtr-field">
        <label>Số điện thoại *</label>
        <input name="phone" type="tel" required
               value="<?= vtr_h($old['phone']) ?>">
    </div>
    <div class="vtr-field">
        <label>Địa chỉ Email *</label>
        <input name="email" type="email" required
               value="<?= vtr_h($old['email']) ?>">
    </div>
    <div class="vtr-field">
        <label>SĐT liên hệ khẩn cấp *</label>
        <input name="emergency_contact" type="tel" required
               value="<?= vtr_h($old['emergency_contact']) ?>">
    </div>
</div>

<div class="vtr-field">
    <label>
        Số người tham gia *
        (Tour còn <?= (int)$tour['available_slots'] ?> chỗ)
    </label>
    <input type="number" name="number_people"
           id="vtr-people"
           min="1"
           max="<?= (int)$tour['available_slots'] ?>"
           value="<?= (int)$old['number_people'] ?>"
           required>
</div>

<div class="vtr-field">
    <label>Ghi chú</label>
    <textarea name="notes"><?= vtr_h($old['notes']) ?></textarea>
</div>

<div class="vtr-summary">
    <div class="vtr-row">
        <span>Đơn giá / người</span>
        <strong><?= vtr_money($tour['price']) ?></strong>
    </div>
    <div class="vtr-row vtr-total">
        <span>TỔNG TIỀN DỰ KIẾN</span>
        <span id="vtr-total">
            <?= vtr_money($tour['price'] * max(1,(int)$old['number_people'])) ?>
        </span>
    </div>
</div>

<button class="vtr-button" id="vtr-submit" type="submit"
    <?= (int)$tour['available_slots'] < 1 ? 'disabled' : '' ?>>
    XÁC NHẬN ĐĂNG KÝ TOUR
</button>

</form>
<?php endif; ?>

</div>
</section>

<script>
const vtrPrice = <?= json_encode((float)($tour['price'] ?? 0)) ?>;
const vtrPeople = document.getElementById('vtr-people');
const vtrTotal = document.getElementById('vtr-total');

if (vtrPeople && vtrTotal) {
    vtrPeople.addEventListener('input', () => {
        const n = Math.max(1,parseInt(vtrPeople.value,10) || 1);
        vtrTotal.textContent =
            (n*vtrPrice).toLocaleString('vi-VN') + ' đ';
    });
}

const vtrForm = document.getElementById('vtr-form');
if (vtrForm) {
    vtrForm.addEventListener('submit', () => {
        if (!vtrForm.checkValidity()) return;
        const btn = document.getElementById('vtr-submit');
        btn.disabled = true;
        btn.textContent = 'ĐANG XỬ LÝ...';
    });
}

async function copyVtrTransfer() {
    const text = document.getElementById('vtr-transfer')
        ?.textContent.trim();
    if (!text) return;

    try {
        await navigator.clipboard.writeText(text);
        alert('Đã sao chép nội dung chuyển khoản!');
    } catch(e) {
        window.prompt('Sao chép nội dung:',text);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
