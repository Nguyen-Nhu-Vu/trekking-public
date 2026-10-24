
<?php
// VNN Travel - Tour toi da dang ky
// Xem lai QR chuyen khoan khi chua thanh toan

$page_title = 'Tour Tôi Đã Đăng Ký';
$active_menu = 'tour';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user_id = (int)$_SESSION['user_id'];
$base = rtrim(BASE_URL, '/') . '/';
$qrImage = $base . 'assets/images/momo-qr-vu.jpg';

function mt_h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function mt_money($value) {
    return number_format(
        (float)$value,
        0,
        ',',
        '.'
    ) . ' đ';
}

function mt_token($value) {
    $value = trim((string)$value);

    $value = strtr($value, [
        'đ' => 'd',
        'Đ' => 'D'
    ]);

    if (class_exists('Transliterator')) {
        $trans = Transliterator::create(
            'Any-Latin; Latin-ASCII'
        );

        if ($trans) {
            $value = $trans->transliterate($value);
        }
    } else {
        $converted = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        if ($converted !== false) {
            $value = $converted;
        }
    }

    $value = strtoupper($value);

    return preg_replace(
        '/[^A-Z0-9]/',
        '',
        $value
    );
}

function mt_phone($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

// Tao lai noi dung CK tu booking luu trong MySQL
function mt_transfer($booking) {
    $code = (string)$booking['booking_code'];

    $tourName = substr(
        mt_token($booking['tour_title']),
        0,
        22
    );

    $customerName = mt_token(
        $booking['fullname']
    );

    $phone = mt_phone(
        $booking['phone']
    );

    return 'TOUR ' .
        $code . ' ' .
        $tourName . ' ' .
        $customerName . ' ' .
        $phone;
}

function mt_status($status) {
    return trim((string)$status);
}

function mt_is_paid($status) {
    $status = mt_status($status);

    return in_array($status, [
        'Đã thanh toán',
        'Thanh toán thành công',
        'paid',
        'completed'
    ], true);
}

function mt_is_cancelled($status) {
    return in_array(mt_status($status), [
        'Đã hủy',
        'Đã huỷ',
        'cancelled',
        'canceled'
    ], true);
}

function mt_image_url($image, $base) {
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $image)) {
        return $image;
    }

    if (str_starts_with($image, '//')) {
        return 'https:' . $image;
    }

    if (str_starts_with($image, '/')) {
        return $image;
    }

    return $base . ltrim($image, '/');
}

// Lay tat ca tour thuoc tai khoan dang dang nhap
$stmt = $pdo->prepare("
    SELECT
        b.*,
        t.title AS tour_title,
        t.image AS tour_image
    FROM tour_bookings b
    INNER JOIN tours t ON b.tour_id = t.id
    WHERE b.user_id = ?
    ORDER BY b.id DESC
");

$stmt->execute([$user_id]);

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.mt-page {
    padding: 38px 16px 75px;
    background: #f8fafc;
    min-height: 70vh;
}

.mt-container {
    max-width: 1150px;
    margin: 0 auto;
}

.mt-heading {
    margin-bottom: 28px;
}

.mt-heading h2 {
    color: #14532d;
    font-size: 29px;
    font-weight: 800;
    margin: 8px 0;
}

.mt-subtitle {
    color: #ea580c;
    font-size: 14px;
    font-weight: 700;
}

.mt-booking-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    margin-bottom: 23px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,.05);
}

.mt-booking-main {
    padding: 24px;
}

.mt-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 20px;
}

.mt-tour-info {
    display: flex;
    align-items: center;
    gap: 17px;
}

.mt-tour-image {
    width: 95px;
    height: 85px;
    object-fit: cover;
    border-radius: 11px;
    background: #e2e8f0;
    flex-shrink: 0;
}

.mt-tour-title {
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
    line-height: 1.4;
}

.mt-booking-code {
    color: #64748b;
    font-size: 14px;
}

.mt-status {
    padding: 8px 13px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}

.mt-status.pending {
    background: #fff7ed;
    color: #c2410c;
}

.mt-status.paid {
    background: #dcfce7;
    color: #166534;
}

.mt-status.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.mt-booking-details {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
}

.mt-detail-label {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 8px;
}

.mt-detail-value {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    overflow-wrap: anywhere;
}

.mt-price {
    color: #ea580c;
    font-size: 19px;
}

.mt-actions {
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.mt-pay-toggle {
    background: #176534;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    padding: 13px 20px;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
}

.mt-pay-toggle:hover {
    background: #14532d;
}

.mt-payment {
    display: none;
    padding: 27px 24px;
    border-top: 1px solid #e2e8f0;
    background: #fffafc;
}

.mt-payment.open {
    display: block;
}

.mt-payment h3 {
    color: #14532d;
    font-size: 21px;
    margin-bottom: 18px;
}

.mt-payment-grid {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    gap: 25px;
    align-items: start;
}

.mt-qr-image {
    width: 100%;
    height: auto;
    display: block;
    border: 1px solid #fbcfe8;
    border-radius: 14px;
    background: #fff;
}

.mt-payment-information {
    padding: 21px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
}

.mt-payment-information h4 {
    font-size: 18px;
    color: #0f172a;
    margin: 0 0 17px;
}

.mt-payment-label {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 7px;
}

.mt-payment-price {
    color: #ea580c;
    font-size: 25px;
    font-weight: 900;
    margin-bottom: 20px;
}

.mt-transfer-content {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 10px;
    padding: 16px;
    font-size: 16px;
    font-weight: 800;
    color: #c2410c;
    overflow-wrap: anywhere;
    margin: 12px 0;
    user-select: all;
}

.mt-copy-button {
    padding: 12px 18px;
    background: #14532d;
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 700;
    cursor: pointer;
}

.mt-payment-note {
    margin-top: 19px;
    background: #f0fdf4;
    color: #166534;
    padding: 15px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.8;
}

.mt-completed {
    color: #166534;
    background: #dcfce7;
    border-radius: 10px;
    padding: 13px 17px;
    font-weight: 700;
}

.mt-cancelled {
    color: #991b1b;
    background: #fee2e2;
    border-radius: 10px;
    padding: 13px 17px;
    font-weight: 700;
}

.mt-empty {
    background: white;
    text-align: center;
    padding: 55px 20px;
    border-radius: 15px;
}

.mt-empty i {
    font-size: 42px;
    color: #94a3b8;
    margin-bottom: 15px;
}

.mt-empty h3 {
    color: #0f172a;
    margin-bottom: 12px;
}

.mt-empty a {
    display: inline-block;
    margin-top: 15px;
    text-decoration: none;
    background: #14532d;
    color: white;
    padding: 12px 20px;
    border-radius: 9px;
}

@media(max-width: 850px) {
    .mt-booking-details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .mt-payment-grid {
        grid-template-columns: 1fr;
    }

    .mt-qr-image {
        max-width: 300px;
        margin: auto;
    }
}

@media(max-width: 550px) {
    .mt-top {
        flex-direction: column;
    }

    .mt-booking-details {
        grid-template-columns: 1fr 1fr;
    }

    .mt-booking-main,
    .mt-payment {
        padding: 17px;
    }

    .mt-tour-image {
        width: 70px;
        height: 70px;
    }

    .mt-tour-title {
        font-size: 16px;
    }
}
</style>

<section class="mt-page">
<div class="mt-container">

    <div class="mt-heading">
        <span class="mt-subtitle">
            TÀI KHOẢN KHÁCH HÀNG
        </span>

        <h2>
            Các Chuyến Đi Tôi Đã Đăng Ký
        </h2>

        <p style="color:#64748b">
            Theo dõi lịch trình, trạng thái đăng ký
            và thanh toán tour của bạn.
        </p>
    </div>

    <?php if (!empty($bookings)): ?>

        <?php foreach ($bookings as $booking): ?>

            <?php
            $status = mt_status($booking['status']);

            $isPaid = mt_is_paid($status);
            $isCancelled = mt_is_cancelled($status);

            $canPay = !$isPaid && !$isCancelled;

            $paymentId = 'payment-' . (int)$booking['id'];

            $transferId = 'transfer-' . (int)$booking['id'];

            $transfer = mt_transfer($booking);

            $image = mt_image_url(
                $booking['tour_image'],
                $base
            );
            ?>

            <article class="mt-booking-card">

                <div class="mt-booking-main">

                    <div class="mt-top">

                        <div class="mt-tour-info">

                            <?php if ($image !== ''): ?>
                                <img
                                    class="mt-tour-image"
                                    src="<?= mt_h($image) ?>"
                                    alt="Ảnh tour"
                                    loading="lazy"
                                >
                            <?php endif; ?>

                            <div>
                                <div class="mt-tour-title">
                                    <?= mt_h($booking['tour_title']) ?>
                                </div>

                                <div class="mt-booking-code">
                                    Mã đặt tour:
                                    <strong>
                                        <?= mt_h($booking['booking_code']) ?>
                                    </strong>
                                </div>
                            </div>

                        </div>

                        <span class="mt-status <?= $isPaid ? 'paid' : ($isCancelled ? 'cancelled' : 'pending') ?>">
                            <?= mt_h($status) ?>
                        </span>

                    </div>

                    <div class="mt-booking-details">

                        <div>
                            <div class="mt-detail-label">
                                Ngày khởi hành
                            </div>

                            <div class="mt-detail-value">
                                <?= !empty($booking['departure_date'])
                                    ? date(
                                        'd/m/Y',
                                        strtotime($booking['departure_date'])
                                    )
                                    : 'Đang cập nhật' ?>
                            </div>
                        </div>

                        <div>
                            <div class="mt-detail-label">
                                Số người tham gia
                            </div>

                            <div class="mt-detail-value">
                                <?= (int)$booking['number_people'] ?> người
                            </div>
                        </div>

                        <div>
                            <div class="mt-detail-label">
                                Số điện thoại
                            </div>

                            <div class="mt-detail-value">
                                <?= mt_h($booking['phone']) ?>
                            </div>
                        </div>

                        <div>
                            <div class="mt-detail-label">
                                Tổng tiền
                            </div>

                            <div class="mt-detail-value mt-price">
                                <?= mt_money($booking['total_price']) ?>
                            </div>
                        </div>

                    </div>

                    <div class="mt-actions">

                        <?php if ($canPay): ?>

                            <button
                                type="button"
                                class="mt-pay-toggle"
                                aria-controls="<?= mt_h($paymentId) ?>"
                                aria-expanded="false"
                                onclick="toggleTourPayment(this, '<?= mt_h($paymentId) ?>')"
                            >
                                <i class="fa-solid fa-qrcode"></i>
                                Xem thanh toán
                            </button>

                            <span style="color:#c2410c;font-size:14px">
                                <i class="fa-solid fa-clock"></i>
                                Chưa xác nhận thanh toán
                            </span>

                        <?php elseif ($isPaid): ?>

                            <div class="mt-completed">
                                <i class="fa-solid fa-circle-check"></i>
                                Thanh toán đã được xác nhận
                            </div>

                        <?php else: ?>

                            <div class="mt-cancelled">
                                <i class="fa-solid fa-circle-xmark"></i>
                                Đăng ký đã hủy
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

                <?php if ($canPay): ?>

                    <div
                        class="mt-payment"
                        id="<?= mt_h($paymentId) ?>"
                    >

                        <h3>
                            <i class="fa-solid fa-credit-card"></i>
                            Thanh toán bằng hình thức chuyển khoản
                        </h3>

                        <div class="mt-payment-grid">

                            <!-- ANH QR MOMO -->
                            <div>
                                <img
                                    src="<?= mt_h($qrImage) ?>"
                                    class="mt-qr-image"
                                    alt="Mã QR thanh toán"
                                    loading="lazy"
                                >
                            </div>

                            <!-- THONG TIN CHUYEN KHOAN -->
                            <div class="mt-payment-information">

                                <h4>
                                    Thông tin thanh toán
                                </h4>

                                <div class="mt-payment-label">
                                    Số tiền cần chuyển
                                </div>

                                <div class="mt-payment-price">
                                    <?= mt_money($booking['total_price']) ?>
                                </div>

                                <div class="mt-payment-label">
                                    Nội dung chuyển khoản bắt buộc
                                </div>

                                <div
                                    class="mt-transfer-content"
                                    id="<?= mt_h($transferId) ?>"
                                ><?= mt_h($transfer) ?></div>

                                <button
                                    type="button"
                                    class="mt-copy-button"
                                    onclick="copyTourContent('<?= mt_h($transferId) ?>')"
                                >
                                    <i class="fa-solid fa-copy"></i>
                                    Sao chép nội dung
                                </button>

                                <div class="mt-payment-note">

                                    <strong>
                                        <i class="fa-solid fa-circle-info"></i>
                                        Lưu ý thanh toán
                                    </strong>

                                    <p>
                                        Vui lòng chuyển đúng số tiền
                                        và nội dung chuyển khoản.
                                    </p>

                                    <p>
                                        Nếu đã chuyển khoản, bạn
                                        không cần chuyển lại.
                                        Hãy chờ quản lý kiểm tra
                                        và xác nhận giao dịch.
                                    </p>

                                    <p>
                                        Bạn có thể quay lại trang này
                                        để xem QR bất cứ lúc nào
                                        trước khi thanh toán được xác nhận.
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="mt-empty">

            <i class="fa-solid fa-mountain-sun"></i>

            <h3>
                Bạn chưa đăng ký tour nào
            </h3>

            <p>
                Hãy khám phá và lựa chọn hành trình
                trekking phù hợp với bạn.
            </p>

            <a href="<?= mt_h($base) ?>tours/tour.php">
                Khám phá tour trekking
            </a>

        </div>

    <?php endif; ?>

</div>
</section>

<script>
function toggleTourPayment(button, elementId) {

    const panel = document.getElementById(elementId);

    if (!panel) return;

    const isOpening = !panel.classList.contains('open');

    panel.classList.toggle('open', isOpening);

    button.setAttribute(
        'aria-expanded',
        isOpening ? 'true' : 'false'
    );

    button.innerHTML = isOpening
        ? '<i class="fa-solid fa-chevron-up"></i> Ẩn thanh toán'
        : '<i class="fa-solid fa-qrcode"></i> Xem thanh toán';

    if (isOpening) {
        panel.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }
}

async function copyTourContent(elementId) {

    const element = document.getElementById(elementId);

    if (!element) return;

    const content = element.textContent.trim();

    try {
        await navigator.clipboard.writeText(content);

        alert('Đã sao chép nội dung chuyển khoản!');

    } catch (error) {

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
