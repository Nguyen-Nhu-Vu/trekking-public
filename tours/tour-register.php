
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function tr_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function tr_date_valid($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function tr_get_departures(PDO $pdo, int $tourId) {
    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.departure_date,
            d.available_slots,
            GREATEST(
                0,
                d.available_slots - COALESCE((
                    SELECT SUM(b.number_people)
                    FROM tour_bookings b
                    WHERE b.tour_id = d.tour_id
                      AND b.departure_date = d.departure_date
                      AND LOWER(TRIM(b.status)) NOT IN (
                          'đã hủy','đã huỷ','hủy','huỷ',
                          'cancelled','canceled',
                          'rejected','từ chối'
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

$tourId = (int)($_GET['id'] ?? 0);
$error = '';
$success = false;

$stmt = $pdo->prepare("
    SELECT *
    FROM tours
    WHERE id = ? AND status = 1
    LIMIT 1
");
$stmt->execute([$tourId]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: ' . BASE_URL . 'tours/tour.php');
    exit;
}

$page_title = 'Đăng Ký Tour: ' . $tour['title'];
$active_menu = 'tour';

$departures = tr_get_departures($pdo, $tourId);

$selectedDate = (string)(
    $_POST['departure_date']
    ?? $_GET['departure']
    ?? ''
);

if ($selectedDate === '' && $departures) {
    foreach ($departures as $d) {
        if ((int)$d['remaining'] > 0) {
            $selectedDate = $d['departure_date'];
            break;
        }
    }
}

$user = null;

if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT * FROM users WHERE id = ?
    ");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$tokenKey = 'tour_booking_token_' . $tourId;

if (empty($_SESSION[$tokenKey])) {
    $_SESSION[$tokenKey] = bin2hex(random_bytes(16));
}

$old = [
    'fullname' => $_POST['fullname'] ?? ($user['fullname'] ?? ''),
    'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
    'email' => $_POST['email'] ?? ($user['email'] ?? ''),
    'emergency_contact' => $_POST['emergency_contact'] ?? '',
    'number_people' => $_POST['number_people'] ?? 1,
    'notes' => $_POST['notes'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim((string)$old['fullname']);
    $phone = preg_replace('/\D+/', '', (string)$old['phone']);
    $email = trim((string)$old['email']);
    $emergency = preg_replace(
        '/\D+/', '', (string)$old['emergency_contact']
    );
    $notes = trim((string)$old['notes']);

    $people = filter_var(
        $old['number_people'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $token = (string)($_POST['booking_token'] ?? '');

    if (
        !$token ||
        !hash_equals($_SESSION[$tokenKey], $token)
    ) {
        $error = 'Phiên đăng ký không hợp lệ. Vui lòng tải lại trang.';
    } elseif (!$user) {
        $error = 'Vui lòng đăng nhập để đăng ký tour.';
    } elseif (
        $fullname === '' ||
        strlen($phone) < 9 ||
        strlen($emergency) < 9 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Vui lòng nhập đầy đủ thông tin hợp lệ.';
    } elseif (!tr_date_valid($selectedDate)) {
        $error = 'Vui lòng chọn ngày khởi hành hợp lệ.';
    } elseif ($people === false) {
        $error = 'Số người tham gia phải là số nguyên lớn hơn 0.';
    } else {
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
                    'Đợt khởi hành này không còn khả dụng.'
                );
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(number_people), 0)
                FROM tour_bookings
                WHERE tour_id = ?
                  AND departure_date = ?
                  AND LOWER(TRIM(status)) NOT IN (
                      'đã hủy','đã huỷ','hủy','huỷ',
                      'cancelled','canceled',
                      'rejected','từ chối'
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
                    'Đợt khởi hành này đã hết chỗ. Vui lòng chọn ngày khác.'
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

            $bookingCode = 'TB' .
                strtoupper(bin2hex(random_bytes(6)));

            $totalPrice = (float)$tour['price'] * $people;

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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xác nhận')
            ");

            $stmt->execute([
                $bookingCode,
                (int)$user['id'],
                $tourId,
                $selectedDate,
                $fullname,
                $phone,
                $email,
                $emergency,
                $people,
                $totalPrice,
                $notes
            ]);

            $pdo->commit();

            $_SESSION[$tokenKey] = bin2hex(random_bytes(16));
            $success = true;

            header(
                'Location: ' . BASE_URL .
                'tours/my-tours.php?registered=1&code=' .
                rawurlencode($bookingCode),
                true,
                303
            );
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Tour booking: ' . $e->getMessage());

            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể đăng ký tour. Vui lòng thử lại.';
        }
    }

    $departures = tr_get_departures($pdo, $tourId);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.tr-page {
    padding:34px 16px 65px;
    background:#f8fafc;
    min-height:70vh;
}
.tr-card {
    max-width:700px;
    margin:auto;
    padding:35px;
    background:#fff;
    border-radius:14px;
    box-shadow:0 8px 30px rgba(0,0,0,.07);
}
.tr-title {
    text-align:center;
    color:#14532d;
    margin:0 0 10px;
}
.tr-subtitle {
    text-align:center;
    color:#64748b;
    line-height:1.6;
    margin-bottom:25px;
}
.tr-departure-box {
    background:#fff7ed;
    border:1px solid #fed7aa;
    padding:16px;
    border-radius:10px;
    margin-bottom:23px;
}
.tr-departure-box label {
    display:block;
    font-weight:800;
    color:#c2410c;
    margin-bottom:9px;
}
.tr-departure-box select {
    width:100%;
}
.tr-error {
    background:#fee2e2;
    border:1px solid #fecaca;
    color:#991b1b;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    line-height:1.7;
}
.tr-total {
    padding:20px;
    border:1px solid #e2e8f0;
    background:#f8fafc;
    border-radius:10px;
    margin:25px 0;
}
.tr-row {
    display:flex;
    justify-content:space-between;
    gap:12px;
    margin-bottom:12px;
}
.tr-grand-total {
    color:#ea580c;
    font-size:20px;
    font-weight:800;
}
.tr-submit {
    width:100%;
    padding:15px;
    font-size:16px;
}
.tr-submit:disabled {
    opacity:.5;
    cursor:not-allowed;
}
.tr-inline-error {
    color:#b91c1c;
    font-size:14px;
    margin-top:8px;
    display:none;
}
@media(max-width:650px) {
    .tr-card {padding:20px}
}
</style>

<section class="tr-page">
<div class="tr-card">

    <h2 class="tr-title">
        ĐĂNG KÝ THAM GIA TOUR
    </h2>

    <p class="tr-subtitle">
        Hành trình:
        <strong><?= tr_h($tour['title']) ?></strong>
    </p>

    <?php if ($error !== ''): ?>
        <div class="tr-error" role="alert">
            <?= tr_h($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$departures): ?>

        <div class="tr-error">
            Hiện chưa có lịch khởi hành mở đăng ký.
        </div>

    <?php else: ?>

    <form method="POST" id="tourForm">

        <input
            type="hidden"
            name="booking_token"
            value="<?= tr_h($_SESSION[$tokenKey]) ?>"
        >

        <div class="tr-departure-box">

            <label for="departure_date">
                <i class="fa-solid fa-calendar-check"></i>
                CHỌN NGÀY KHỞI HÀNH (CÁC ĐỢT TOUR CÓ SẴN):
            </label>

            <select
                name="departure_date"
                id="departure_date"
                class="form-control"
                required
            >

                <?php foreach ($departures as $dep): ?>

                    <?php
                    $date = (string)$dep['departure_date'];
                    $remaining = (int)$dep['remaining'];
                    ?>

                    <option
                        value="<?= tr_h($date) ?>"
                        data-remaining="<?= $remaining ?>"
                        <?= $selectedDate === $date ? 'selected' : '' ?>
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

            <!-- KHONG CON DONG "NGAY DA CHON CON ... CHO" -->

        </div>

        <div class="grid grid-2">

            <div class="form-group">
                <label>Họ và tên *</label>
                <input
                    name="fullname"
                    type="text"
                    class="form-control"
                    required
                    value="<?= tr_h($old['fullname']) ?>"
                >
            </div>

            <div class="form-group">
                <label>Số điện thoại *</label>
                <input
                    name="phone"
                    type="tel"
                    class="form-control"
                    required
                    value="<?= tr_h($old['phone']) ?>"
                >
            </div>

        </div>

        <div class="grid grid-2">

            <div class="form-group">
                <label>Địa chỉ Email *</label>
                <input
                    name="email"
                    type="email"
                    class="form-control"
                    required
                    value="<?= tr_h($old['email']) ?>"
                >
            </div>

            <div class="form-group">
                <label>SĐT liên hệ khẩn cấp *</label>
                <input
                    name="emergency_contact"
                    type="tel"
                    class="form-control"
                    required
                    value="<?= tr_h($old['emergency_contact']) ?>"
                >
            </div>

        </div>

        <div class="form-group">

            <label id="peopleLabel" for="number_people">
                Số người tham gia *
            </label>

            <input
                type="number"
                name="number_people"
                id="number_people"
                class="form-control"
                min="1"
                step="1"
                required
                value="<?= max(1, (int)$old['number_people']) ?>"
            >

            <div
                class="tr-inline-error"
                id="peopleError"
                role="alert"
            ></div>

        </div>

        <div class="form-group">

            <label>
                Ghi chú (Chế độ ăn chay, kích thước áo, lưu ý sức khỏe...)
            </label>

            <textarea
                name="notes"
                class="form-control"
                rows="3"
            ><?= tr_h($old['notes']) ?></textarea>

        </div>

        <div class="tr-total">

            <div class="tr-row">
                <span>Đơn giá / người:</span>
                <strong><?= format_price($tour['price']) ?></strong>
            </div>

            <div class="tr-row tr-grand-total">
                <span>TỔNG TIỀN DỰ KIẾN:</span>
                <span id="display_total">
                    <?= format_price($tour['price']) ?>
                </span>
            </div>

        </div>

        <button
            type="submit"
            class="btn btn-accent tr-submit"
            id="submitTour"
        >
            XÁC NHẬN ĐĂNG KÝ TOUR
        </button>

    </form>

    <?php endif; ?>

</div>
</section>

<script>
(function() {

    const select = document.getElementById('departure_date');
    const people = document.getElementById('number_people');
    const label = document.getElementById('peopleLabel');
    const error = document.getElementById('peopleError');
    const total = document.getElementById('display_total');
    const button = document.getElementById('submitTour');
    const form = document.getElementById('tourForm');

    if (!select || !people || !form) return;

    const price = <?= json_encode((float)$tour['price']) ?>;

    function remaining() {
        const option = select.options[select.selectedIndex];
        return option
            ? Number(option.dataset.remaining || 0)
            : 0;
    }

    function update() {
        const slots = remaining();
        const count = Number(people.value);

        people.max = String(slots);

        // Chi hien cho o dropdown va gioi han tren label.
        // Khong hien thong bao ngay da chon rieng biet.
        label.textContent =
            'Số người tham gia * (Còn trống ' + slots + ' chỗ)';

        const valid =
            Number.isInteger(count) &&
            count >= 1 &&
            count <= slots;

        if (!valid) {
            error.style.display = 'block';

            if (slots <= 0) {
                error.textContent =
                    'Đợt khởi hành này đã hết chỗ. Vui lòng chọn ngày khác.';
            } else if (count > slots) {
                error.textContent =
                    'Đợt đã chọn chỉ còn ' + slots +
                    ' chỗ. Vui lòng giảm số người tham gia.';
            } else {
                error.textContent =
                    'Vui lòng nhập số người từ 1 đến ' + slots + '.';
            }
        } else {
            error.style.display = 'none';
            error.textContent = '';
        }

        total.textContent =
            (Math.max(0, count || 0) * price)
                .toLocaleString('vi-VN') + ' đ';

        button.disabled = !valid;
        return valid;
    }

    select.addEventListener('change', function() {
        people.value = remaining() > 0 ? '1' : '';
        update();
    });

    people.addEventListener('input', update);

    form.addEventListener('submit', function(event) {
        if (!update()) {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) return;

        button.disabled = true;
        button.textContent = 'ĐANG XỬ LÝ ĐĂNG KÝ...';
    });

    if (remaining() <= 0) {
        const available = Array.from(select.options)
            .find(o => Number(o.dataset.remaining) > 0);

        if (available) {
            select.value = available.value;
            people.value = '1';
        }
    }

    update();

})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
