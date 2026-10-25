<?php
// VNN Travel - Dang ky tour theo ngay khoi hanh
// So cho = suc chua dot - tong nguoi da dat chua huy

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function tr_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function tr_valid_date($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function tr_departures(PDO $pdo, int $tourId) {
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
                      AND LOWER(TRIM(b.status)) NOT IN (
                          'đã hủy',
                          'đã huỷ',
                          'hủy',
                          'huỷ',
                          'cancelled',
                          'canceled',
                          'rejected',
                          'từ chối'
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
$selectedDate = trim((string)(
    $_POST['departure_date']
    ?? $_GET['departure']
    ?? ''
));

$error = '';
$user = null;
$departures = [];

$stmt = $pdo->prepare("
    SELECT *
    FROM tours
    WHERE id = ? AND status = 1
    LIMIT 1
");
$stmt->execute([$tourId]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header("Location: " . BASE_URL . "tours/tour.php");
    exit;
}

$page_title = 'Đăng Ký Tour: ' . $tour['title'];

$departures = tr_departures($pdo, $tourId);

if ($selectedDate === '' && !empty($departures)) {
    $selectedDate = $departures[0]['departure_date'];
}

if (!empty($_SESSION['user_id'])) {
    $u = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $u->execute([(int)$_SESSION['user_id']]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
}

if (empty($_SESSION['tour_booking_token'])) {
    $_SESSION['tour_booking_token'] =
        bin2hex(random_bytes(16));
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
    $phone = trim((string)$old['phone']);
    $email = trim((string)$old['email']);
    $emergency = trim((string)$old['emergency_contact']);
    $notes = trim((string)$old['notes']);

    $numberPeople = filter_var(
        $old['number_people'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $submittedToken = (string)(
        $_POST['tour_booking_token'] ?? ''
    );

    if (
        !$submittedToken ||
        !hash_equals(
            $_SESSION['tour_booking_token'],
            $submittedToken
        )
    ) {
        $error = 'Phiên đăng ký không hợp lệ. Vui lòng tải lại trang.';

    } elseif (
        $fullname === '' ||
        $phone === '' ||
        $email === '' ||
        $emergency === ''
    ) {
        $error = 'Vui lòng nhập đầy đủ các thông tin bắt buộc.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';

    } elseif (!tr_valid_date($selectedDate)) {
        $error = 'Ngày khởi hành không hợp lệ.';

    } elseif ($numberPeople === false) {
        $error = 'Số người tham gia phải là số nguyên lớn hơn 0.';

    } else {

        try {
            $pdo->beginTransaction();

            // Khoa dung dong ngay khoi hanh.
            // Nhieu nguoi dat cung luc se khong vuot suc chua.

            $lockStmt = $pdo->prepare("
                SELECT id, available_slots
                FROM tour_departures
                WHERE tour_id = ?
                  AND departure_date = ?
                  AND departure_date >= CURDATE()
                  AND status = 1
                FOR UPDATE
            ");

            $lockStmt->execute([
                $tourId,
                $selectedDate
            ]);

            $departure = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$departure) {
                throw new RuntimeException(
                    'Đợt khởi hành đã chọn không còn khả dụng.'
                );
            }

            // Tong so nguoi da dat trong dot nay.
            // Don huy khong chiem cho.

            $bookedStmt = $pdo->prepare("
                SELECT COALESCE(SUM(number_people), 0)
                FROM tour_bookings
                WHERE tour_id = ?
                  AND departure_date = ?
                  AND LOWER(TRIM(status)) NOT IN (
                      'đã hủy',
                      'đã huỷ',
                      'hủy',
                      'huỷ',
                      'cancelled',
                      'canceled',
                      'rejected',
                      'từ chối'
                  )
            ");

            $bookedStmt->execute([
                $tourId,
                $selectedDate
            ]);

            $booked = (int)$bookedStmt->fetchColumn();

            $remaining = max(
                0,
                (int)$departure['available_slots'] - $booked
            );

            if ($remaining <= 0) {
                throw new RuntimeException(
                    'Đợt khởi hành này đã hết chỗ. ' .
                    'Vui lòng chọn ngày khác.'
                );
            }

            if ($numberPeople > $remaining) {
                throw new RuntimeException(
                    'Ngày ' .
                    date('d/m/Y', strtotime($selectedDate)) .
                    ' chỉ còn ' . $remaining .
                    ' chỗ. Vui lòng giảm số người tham gia.'
                );
            }

            // Lien ket tai khoan theo session hoac email.

            $currentUserId = (int)(
                $_SESSION['user_id'] ?? 0
            );

            if ($currentUserId > 0) {
                $check = $pdo->prepare(
                    "SELECT id FROM users WHERE id = ?"
                );
                $check->execute([$currentUserId]);
                if (!$check->fetchColumn()) {
                    $currentUserId = 0;
                }
            }

            if ($currentUserId <= 0) {

                $check = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE LOWER(email) = LOWER(?)
                    LIMIT 1
                ");

                $check->execute([$email]);
                $existingUser = $check->fetch(PDO::FETCH_ASSOC);

                if ($existingUser) {
                    $currentUserId = (int)$existingUser['id'];
                } else {
                    $password = password_hash(
                        bin2hex(random_bytes(12)),
                        PASSWORD_DEFAULT
                    );

                    $createUser = $pdo->prepare("
                        INSERT INTO users
                        (fullname, email, password, phone, role, status)
                        VALUES (?, ?, ?, ?, 'customer', 1)
                    ");

                    $createUser->execute([
                        $fullname,
                        strtolower($email),
                        $password,
                        $phone
                    ]);

                    $currentUserId = (int)$pdo->lastInsertId();
                }
            }

            $bookingCode = 'TB-' .
                strtoupper(bin2hex(random_bytes(8)));

            $totalPrice =
                (float)$tour['price'] * $numberPeople;

            $insert = $pdo->prepare("
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
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xác nhận')
            ");

            $insert->execute([
                $bookingCode,
                $currentUserId,
                $tourId,
                $selectedDate,
                $fullname,
                $phone,
                $email,
                $emergency,
                $numberPeople,
                $totalPrice,
                $notes
            ]);

            // Khong tru tours.available_slots.
            // Khong tru tour_departures.available_slots.
            // Vi cho con lai duoc tinh bang tong booking
            // de bao gom ca cac booking cu.

            $pdo->commit();

            unset($_SESSION['tour_booking_token']);

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

            if ($e instanceof RuntimeException) {
                $error = $e->getMessage();
            } else {
                error_log(
                    'Tour booking: ' . $e->getMessage()
                );

                $error =
                    'Không thể đăng ký tour lúc này. ' .
                    'Vui lòng thử lại.';
            }
        }
    }

    // Lay lai so cho hien tai neu dat that bai.
    $departures = tr_departures($pdo, $tourId);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.tr-page {
    padding: 34px 16px 65px;
    background: #f8fafc;
    min-height: 70vh;
}

.tr-card {
    max-width: 700px;
    margin: auto;
    background: white;
    padding: 35px;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,.07);
}

.tr-title {
    color: var(--primary, #14532d);
    text-align: center;
    margin: 0 0 9px;
}

.tr-subtitle {
    color: #64748b;
    text-align: center;
    line-height: 1.6;
    margin-bottom: 25px;
}

.tr-departure-box {
    background: #fff7ed;
    padding: 16px;
    border-radius: 10px;
    border: 1px solid #fed7aa;
    margin-bottom: 23px;
}

.tr-departure-box label {
    display: block;
    font-weight: 800;
    color: #c2410c;
    margin-bottom: 9px;
}

.tr-departure-box select {
    width: 100%;
}

.tr-notice {
    margin: 12px 0;
    font-size: 14px;
    font-weight: 700;
    color: #166534;
}

.tr-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 14px 17px;
    border-radius: 10px;
    margin: 18px 0;
    line-height: 1.7;
}

.tr-total {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px;
    margin: 25px 0;
}

.tr-total-line {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.tr-total-final {
    color: #ea580c;
    font-weight: 800;
    font-size: 20px;
}

.tr-submit {
    width: 100%;
    padding: 14px;
    font-size: 17px;
}

.tr-submit:disabled {
    opacity: .55;
    cursor: not-allowed;
}

@media (max-width: 650px) {
    .tr-card {
        padding: 20px;
    }

    .tr-total-final {
        font-size: 17px;
    }
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
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= tr_h($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($departures)): ?>

            <div class="tr-error">
                Hiện chưa có lịch khởi hành mở đăng ký.
                Vui lòng quay lại sau.
            </div>

            <a class="btn btn-primary"
               href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= $tourId ?>">
                Quay lại chi tiết tour
            </a>

        <?php else: ?>

        <form method="POST" id="tourForm">

            <input type="hidden"
                   name="tour_booking_token"
                   value="<?= tr_h($_SESSION['tour_booking_token']) ?>">

            <div class="tr-departure-box">

                <label for="departure_date">
                    <i class="fa-solid fa-calendar-check"></i>
                    CHỌN NGÀY KHỞI HÀNH:
                </label>

                <select
                    id="departure_date"
                    name="departure_date"
                    class="form-control"
                    required
                >

                    <?php foreach ($departures as $dep): ?>

                        <?php
                        $remaining = (int)$dep['remaining'];
                        $date = (string)$dep['departure_date'];
                        ?>

                        <option
                            value="<?= tr_h($date) ?>"
                            data-remaining="<?= $remaining ?>"
                            <?= $selectedDate === $date ? 'selected' : '' ?>
                            <?= $remaining <= 0 ? 'disabled' : '' ?>
                        >
                            <?= date('d/m/Y', strtotime($date)) ?>
                            —
                            <?= $remaining > 0
                                ? 'Còn ' . $remaining . ' chỗ'
                                : 'Đã hết chỗ' ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <div class="tr-notice" id="remainingNotice"
                     aria-live="polite"></div>

            </div>

            <div class="grid grid-2">

                <div class="form-group">

                    <label>Họ và tên *</label>

                    <input
                        type="text"
                        name="fullname"
                        class="form-control"
                        required
                        value="<?= tr_h($old['fullname']) ?>"
                        placeholder="Nguyễn Văn A"
                    >

                </div>

                <div class="form-group">

                    <label>Số điện thoại *</label>

                    <input
                        type="tel"
                        name="phone"
                        class="form-control"
                        required
                        value="<?= tr_h($old['phone']) ?>"
                        placeholder="09xxxxxxxx"
                    >

                </div>

            </div>

            <div class="grid grid-2">

                <div class="form-group">

                    <label>Địa chỉ Email *</label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        required
                        value="<?= tr_h($old['email']) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>SĐT liên hệ khẩn cấp *</label>

                    <input
                        type="tel"
                        name="emergency_contact"
                        class="form-control"
                        required
                        value="<?= tr_h($old['emergency_contact']) ?>"
                        placeholder="Số người thân"
                    >

                </div>

            </div>

            <div class="form-group">

                <label for="number_people" id="peopleLabel">
                    Số người tham gia *
                </label>

                <input
                    type="number"
                    id="number_people"
                    name="number_people"
                    class="form-control"
                    min="1"
                    step="1"
                    value="<?= max(1, (int)$old['number_people']) ?>"
                    required
                >

                <div
                    id="peopleError"
                    style="display:none;
                           color:#b91c1c;
                           font-size:14px;
                           margin-top:8px"
                    role="alert"
                ></div>

            </div>

            <div class="form-group">

                <label>Ghi chú (ăn chay, kích thước áo, lưu ý sức khỏe...)</label>

                <textarea
                    name="notes"
                    class="form-control"
                    rows="3"
                ><?= tr_h($old['notes']) ?></textarea>

            </div>

            <div class="tr-total">

                <div class="tr-total-line">
                    <span>Đơn giá / người:</span>
                    <strong><?= format_price($tour['price']) ?></strong>
                </div>

                <div class="tr-total-line tr-total-final">
                    <span>TỔNG TIỀN DỰ KIẾN:</span>
                    <span id="display_total">
                        <?= format_price($tour['price']) ?>
                    </span>
                </div>

            </div>

            <button
                type="submit"
                id="submitTour"
                class="btn btn-accent tr-submit"
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
    const notice = document.getElementById('remainingNotice');
    const error = document.getElementById('peopleError');
    const total = document.getElementById('display_total');
    const button = document.getElementById('submitTour');
    const form = document.getElementById('tourForm');

    if (!select || !people || !form) return;

    const unitPrice = <?= json_encode((float)$tour['price']) ?>;

    function getRemaining() {
        const selected = select.options[select.selectedIndex];

        return selected
            ? Number(selected.dataset.remaining || 0)
            : 0;
    }

    function updateForm() {
        const remaining = getRemaining();
        const count = Number(people.value);

        people.max = String(remaining);

        label.textContent =
            'Số người tham gia * (Còn trống ' +
            remaining + ' chỗ)';

        notice.textContent =
            remaining > 0
            ? 'Ngày đã chọn còn ' + remaining + ' chỗ.'
            : 'Đợt khởi hành này đã hết chỗ.';

        const valid =
            Number.isInteger(count) &&
            count >= 1 &&
            count <= remaining;

        if (!valid) {
            error.style.display = 'block';

            if (remaining <= 0) {
                error.textContent =
                    'Ngày đã chọn đã hết chỗ. ' +
                    'Vui lòng chọn ngày khác.';
            } else if (count > remaining) {
                error.textContent =
                    'Ngày này chỉ còn ' + remaining +
                    ' chỗ. Vui lòng giảm số người tham gia.';
            } else {
                error.textContent =
                    'Vui lòng nhập số người từ 1 đến ' +
                    remaining + '.';
            }
        } else {
            error.style.display = 'none';
            error.textContent = '';
        }

        total.textContent =
            (Math.max(0, count || 0) * unitPrice)
                .toLocaleString('vi-VN') + ' đ';

        button.disabled = !valid;

        return valid;
    }

    select.addEventListener('change', function() {
        const remaining = getRemaining();

        people.value = remaining > 0 ? '1' : '';
        updateForm();
    });

    people.addEventListener('input', updateForm);

    form.addEventListener('submit', function(event) {
        if (!updateForm()) {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) {
            return;
        }

        button.disabled = true;
        button.textContent = 'ĐANG XỬ LÝ ĐĂNG KÝ...';
    });

    // Tu dong chon dot con cho neu dot hien tai da het.
    if (getRemaining() <= 0) {
        const available = Array.from(select.options)
            .find(opt => Number(opt.dataset.remaining) > 0);

        if (available) {
            select.value = available.value;
        }
    }

    if (getRemaining() <= 0) {
        people.value = '';
    }

    updateForm();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
