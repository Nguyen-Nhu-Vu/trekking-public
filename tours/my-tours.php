
<?php
// VNN Travel - Tour toi da dang ky
// Fix headers already sent
// Xem lai QR thanh toan khi chua duoc xac nhan

$page_title = 'Tour Tôi Đã Đăng Ký';
$active_menu = 'tour';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$base = rtrim(
    defined('BASE_URL') ? BASE_URL : '/',
    '/'
) . '/';

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

    return preg_replace(
        '/[^A-Z0-9]/',
        '',
        strtoupper($value)
    );
}

function mt_phone($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function mt_transfer($booking) {
    return 'TOUR ' .
        $booking['booking_code'] . ' ' .
        substr(mt_token($booking['tour_title']), 0, 22) . ' ' .
        mt_token($booking['fullname']) . ' ' .
        mt_phone($booking['phone']);
}

function mt_normalize_status($status) {
    $value = trim(mb_strtolower(
        (string)$status,
        'UTF-8'
    ));

    return $value;
}

function mt_is_paid($status) {
    return in_array(
        mt_normalize_status($status),
        [
            'đã thanh toán',
            'thanh toán thành công',
            'paid',
            'completed'
        ],
        true
    );
}

function mt_is_cancelled($status) {
    return in_array(
        mt_normalize_status($status),
        [
            'đã hủy',
            'đã huỷ',
            'cancelled',
            'canceled'
        ],
        true
    );
}

function mt_image($image, $base) {
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

// Khong goi requireLogin vi no redirect bang header().
// Trang nay tu xu ly khi session khong hop le.

$userId = isset($_SESSION['user_id'])
    ? (int)$_SESSION['user_id']
    : 0;

$loggedIn = $userId > 0;
$bookings = [];
$loadError = '';

if ($loggedIn) {
    try {
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

        $stmt->execute([$userId]);

        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        error_log(
            'My Tours Error: ' . $e->getMessage()
        );

        $loadError =
            'Không thể tải danh sách tour. ' .
            'Vui lòng thử lại sau.';
    }
}

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

.mt-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    margin-bottom: 23px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,.05);
}

.mt-main {
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

.mt-image {
    width: 95px;
    height: 85px;
    object-fit: cover;
    border-radius: 11px;
    background: #e2e8f0;
}

.mt-title {
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
    line-height: 1.4;
}

.mt-code-label {
    font-size: 14px;
    color: #64748b;
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

.mt-details {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
}

.mt-label {
    color: #64748b;
    font-size: 13px;
    margin-bottom: 8px;
}

.mt-value {
    color: #0f172a;
    font-size: 15px;
    font-weight: 800;
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

.mt-button {
    background: #176534;
    color: white;
    border: 0;
    border-radius: 10px;
    padding: 13px 20px;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
}

.mt-button:hover {
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
    grid-template-columns: 260px 1fr;
    gap: 25px;
}

.mt-qr {
    display: block;
    width: 100%;
    height: auto;
    border: 1px solid #fbcfe8;
    border-radius: 14px;
    background: white;
}

.mt-pay-info {
    padding: 21px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
}

.mt-pay-info h4 {
    font-size: 18px;
    margin: 0 0 17px;
}

.mt-payment-price {
    color: #ea580c;
    font-size: 25px;
    font-weight: 900;
    margin-bottom: 20px;
}

.mt-transfer {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 10px;
    padding: 16px;
    color: #c2410c;
    font-size: 16px;
    font-weight: 800;
    overflow-wrap: anywhere;
    margin: 12px 0;
    user-select: all;
}

.mt-copy {
    padding: 12px 18px;
    background: #14532d;
    border: 0;
    border-radius: 10px;
    color: white;
    font-weight: 700;
    cursor: pointer;
}

.mt-note {
    margin-top: 19px;
    padding: 15px;
    border-radius: 10px;
    background: #f0fdf4;
    color: #166534;
    line-height: 1.8;
    font-size: 14px;
}

.mt-message {
    max-width: 680px;
    margin: 35px auto;
    padding: 40px 25px;
    border-radius: 16px;
    text-align: center;
    background: white;
    box-shadow: 0 5px 20px rgba(0,0,0,.05);
}

.mt-message h3 {
    color: #14532d;
    margin-bottom: 12px;
}

.mt-message p {
    color: #64748b;
    line-height: 1.7;
}

.mt-message a {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 22px;
    background: #14532d;
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 700;
}

.mt-result {
    padding: 13px 17px;
    border-radius: 10px;
    font-weight: 700;
}

.mt-result.paid {
    background: #dcfce7;
    color: #166534;
}

.mt-result.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

@media(max-width:850px) {
    .mt-details {
        grid-template-columns: repeat(2, 1fr);
    }

    .mt-payment-grid {
        grid-template-columns: 1fr;
    }

    .mt-qr {
        max-width: 300px;
        margin: auto;
    }
}

@media(max-width:550px) {
    .mt-top {
        flex-direction: column;
    }

    .mt-details {
        grid-template-columns: 1fr 1fr;
    }

    .mt-main,
    .mt-payment {
        padding: 17px;
    }

    .mt-image {
        width: 70px;
        height: 70px;
    }

    .mt-title {
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
            Theo dõi lịch trình và thanh toán tour của bạn.
        </p>
    </div>

    <?php if (!$loggedIn): ?>

        <div class="mt-message">

            <i class="fa-solid fa-lock"
               style="font-size:40px;color:#14532d"></i>

            <h3>Vui lòng đăng nhập</h3>

            <p>
                Bạn cần đăng nhập để xem các tour
                đã đăng ký và thông tin thanh toán.
            </p>

            <a href="<?= mt_h($base) ?>auth/login.php">
                <i class="fa-solid fa-right-to-bracket"></i>
                Đăng nhập
            </a>

        </div>

    <?php elseif ($loadError !== ''): ?>

        <div class="mt-message">
            <h3>Không thể tải dữ liệu</h3>
            <p><?= mt_h($loadError) ?></p>
        </div>

    <?php elseif (empty($bookings)): ?>

        <div class="mt-message">
            <i class="fa-solid fa-mountain-sun"
               style="font-size:40px;color:#94a3b8"></i>

            <h3>Bạn chưa đăng ký tour nào</h3>

            <p>
                Hãy khám phá các hành trình trekking
                và lựa chọn chuyến đi phù hợp.
            </p>

            <a href="<?= mt_h($base) ?>tours/tour.php">
                Khám phá tour trekking
            </a>
        </div>

    <?php else: ?>

        <?php foreach ($bookings as $booking): ?>

            <?php
            $status = trim(
                (string)$booking['status']
            );

            $isPaid = mt_is_paid($status);
            $isCancelled = mt_is_cancelled($status);
            $canPay = !$isPaid && !$isCancelled;

            $paymentId = 'payment-' . (int)$booking['id'];
            $transferId = 'transfer-' . (int)$booking['id'];

            $transfer = mt_transfer($booking);

            $image = mt_image(
                $booking['tour_image'],
                $base
            );
            ?>

            <article class="mt-card">

                <div class="mt-main">

                    <div class="mt-top">

                        <div class="mt-tour-info">

                            <?php if ($image !== ''): ?>
                                <img
                                    class="mt-image"
                                    src="<?= mt_h($image) ?>"
                                    alt="Ảnh tour"
                                    loading="lazy"
                                >
                            <?php endif; ?>

                            <div>

                                <div class="mt-title">
                                    <?= mt_h($booking['tour_title']) ?>
                                </div>

                                <div class="mt-code-label">
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

                    <div class="mt-details">

                        <div>
                            <div class="mt-label">
                                Ngày khởi hành
                            </div>

                            <div class="mt-value">
                                <?php if (!empty($booking['departure_date'])): ?>
                                    <?= date(
                                        'd/m/Y',
                                        strtotime($booking['departure_date'])
                                    ) ?>
                                <?php else: ?>
                                    Đang cập nhật
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <div class="mt-label">
                                Số người tham gia
                            </div>

                            <div class="mt-value">
                                <?= (int)$booking['number_people'] ?> người
                            </div>
                        </div>

                        <div>
                            <div class="mt-label">
                                Số điện thoại
                            </div>

                            <div class="mt-value">
                                <?= mt_h($booking['phone']) ?>
                            </div>
                        </div>

                        <div>
                            <div class="mt-label">
                                Tổng tiền
                            </div>

                            <div class="mt-value mt-price">
                                <?= mt_money($booking['total_price']) ?>
                            </div>
                        </div>

                    </div>

                    <div class="mt-actions">

                        <?php if ($canPay): ?>

                            <button
                                type="button"
                                class="mt-button"
                                aria-controls="<?= mt_h($paymentId) ?>"
                                aria-expanded="false"
                                onclick="toggleTourPayment(this, '<?= mt_h($paymentId) ?>')"
                            >
                                <i class="fa-solid fa-qrcode"></i>
                                Xem thanh toán
                            </button>

                            <span style="font-size:14px;color:#c2410c">
                                <i class="fa-solid fa-clock"></i>
                                Chưa xác nhận thanh toán
                            </span>

                        <?php elseif ($isPaid): ?>

                            <div class="mt-result paid">
                                <i class="fa-solid fa-circle-check"></i>
                                Thanh toán đã được xác nhận
                            </div>

                        <?php else: ?>

                            <div class="mt-result cancelled">
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

                            <div>
                                <img
                                    class="mt-qr"
                                    src="<?= mt_h($qrImage) ?>"
                                    alt="Mã QR thanh toán MoMo"
                                    loading="lazy"
                                >
                            </div>

                            <div class="mt-pay-info">

                                <h4>Thông tin thanh toán</h4>

                                <div class="mt-label">
                                    Số tiền cần chuyển
                                </div>

                                <div class="mt-payment-price">
                                    <?= mt_money($booking['total_price']) ?>
                                </div>

                                <div class="mt-label">
                                    Nội dung chuyển khoản bắt buộc
                                </div>

                                <div
                                    id="<?= mt_h($transferId) ?>"
                                    class="mt-transfer"
                                ><?= mt_h($transfer) ?></div>

                                <button
                                    type="button"
                                    class="mt-copy"
                                    onclick="copyTourContent('<?= mt_h($transferId) ?>')"
                                >
                                    <i class="fa-solid fa-copy"></i>
                                    Sao chép nội dung
                                </button>

                                <div class="mt-note">

                                    <strong>
                                        <i class="fa-solid fa-circle-info"></i>
                                        Lưu ý thanh toán
                                    </strong>

                                    <p>
                                        Vui lòng chuyển đúng số tiền
                                        và nội dung hiển thị.
                                    </p>

                                    <p>
                                        Nếu đã chuyển khoản, bạn
                                        không cần chuyển lại.
                                        Hãy chờ quản lý xác nhận.
                                    </p>

                                    <p>
                                        Bạn có thể quay lại trang này
                                        để xem QR trước khi thanh toán
                                        được xác nhận.
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

</div>
</section>

<script>
function toggleTourPayment(button, elementId) {
    const panel = document.getElementById(elementId);

    if (!panel) return;

    const opening = !panel.classList.contains('open');

    panel.classList.toggle('open', opening);

    button.setAttribute(
        'aria-expanded',
        opening ? 'true' : 'false'
    );

    button.innerHTML = opening
        ? '<i class="fa-solid fa-chevron-up"></i> Ẩn thanh toán'
        : '<i class="fa-solid fa-qrcode"></i> Xem thanh toán';

    if (opening) {
        panel.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }
}

async function copyTourContent(elementId) {
    const element = document.getElementById(elementId);

    if (!element) return;

    const text = element.textContent.trim();

    try {
        await navigator.clipboard.writeText(text);
        alert('Đã sao chép nội dung chuyển khoản!');
    } catch (error) {
        window.prompt(
            'Sao chép nội dung chuyển khoản:',
            text
        );
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
