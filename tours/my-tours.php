<?php
// VNN Travel - Tour toi da dang ky
// Hien thi thanh toan trong modal popup.
// Khong redirect bang header().

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
        (float)$value, 0, ',', '.'
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
    return preg_replace(
        '/\D+/',
        '',
        (string)$value
    );
}

function mt_transfer($booking) {
    return 'TOUR ' .
        $booking['booking_code'] . ' ' .
        substr(mt_token($booking['tour_title']), 0, 22) . ' ' .
        mt_token($booking['fullname']) . ' ' .
        mt_phone($booking['phone']);
}

function mt_status($value) {
    return trim(mb_strtolower(
        (string)$value,
        'UTF-8'
    ));
}

function mt_is_paid($status) {
    return in_array(mt_status($status), [
        'đã thanh toán',
        'thanh toán thành công',
        'paid',
        'completed'
    ], true);
}

function mt_is_cancelled($status) {
    return in_array(mt_status($status), [
        'đã hủy',
        'đã huỷ',
        'cancelled',
        'canceled'
    ], true);
}

function mt_image($image, $base) {
    $image = trim((string)$image);

    if ($image === '') return '';

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

// Khong dung requireLogin() de tranh loi header().
$userId = (int)($_SESSION['user_id'] ?? 0);

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
            'My Tours: ' . $e->getMessage()
        );

        $loadError =
            'Không thể tải danh sách tour lúc này.';
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
    min-width: 0;
}

.mt-image {
    width: 95px;
    height: 85px;
    object-fit: cover;
    border-radius: 11px;
    background: #e2e8f0;
    flex-shrink: 0;
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
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
}

.mt-label {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 8px;
}

.mt-value {
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
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.mt-button {
    background: #176534;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 13px 20px;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    transition: background .2s;
}

.mt-button:hover {
    background: #14532d;
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

.mt-message {
    background: white;
    border-radius: 16px;
    padding: 45px 25px;
    text-align: center;
    max-width: 700px;
    margin: 30px auto;
    box-shadow: 0 5px 20px rgba(0,0,0,.05);
}

.mt-message h3 {
    color: #14532d;
    margin: 15px 0;
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
}

/* ==================================
   CUA SO THANH TOAN POPUP
   ================================== */

.mt-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: rgba(15,23,42,.68);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);

    display: none;
    align-items: center;
    justify-content: center;

    padding: 20px;
    box-sizing: border-box;
}

.mt-modal-overlay.active {
    display: flex;
}

.mt-modal {
    position: relative;
    background: #ffffff;
    width: 100%;
    max-width: 850px;
    max-height: 90vh;
    border-radius: 18px;
    box-shadow: 0 24px 70px rgba(0,0,0,.3);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: mtAppear .2s ease-out;
}

@keyframes mtAppear {
    from {
        opacity: 0;
        transform: translateY(15px) scale(.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.mt-modal-header {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;

    background: #14532d;
    color: white;
    padding: 20px 25px;
    flex-shrink: 0;
}

.mt-modal-header h3 {
    color: white;
    margin: 0;
    font-size: 20px;
    line-height: 1.4;
}

.mt-modal-header p {
    margin: 6px 0 0;
    color: #dcfce7;
    font-size: 13px;
}

.mt-modal-close {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;

    width: 40px;
    height: 40px;
    border: 0;
    border-radius: 50%;

    background: rgba(255,255,255,.17);
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    transition: background .2s;
}

.mt-modal-close:hover,
.mt-modal-close:focus-visible {
    background: rgba(255,255,255,.35);
}

.mt-modal-body {
    padding: 25px;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.mt-modal-tour {
    background: #f8fafc;
    border-radius: 12px;
    padding: 15px 18px;
    margin-bottom: 22px;
}

.mt-modal-tour strong {
    display: block;
    color: #0f172a;
    line-height: 1.5;
}

.mt-modal-tour small {
    display: block;
    color: #64748b;
    margin-top: 5px;
}

.mt-modal-grid {
    display: grid;
    grid-template-columns: 250px minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}

.mt-modal-qr {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 13px;
    border: 1px solid #fbcfe8;
    background: white;
}

.mt-modal-info {
    min-width: 0;
}

.mt-modal-info h4 {
    font-size: 18px;
    color: #0f172a;
    margin: 0 0 18px;
}

.mt-modal-price {
    font-size: 26px;
    color: #ea580c;
    font-weight: 900;
    margin: 5px 0 20px;
}

.mt-transfer {
    border: 1px solid #fed7aa;
    background: #fff7ed;
    border-radius: 11px;
    padding: 15px;
    font-size: 15px;
    font-weight: 800;
    color: #c2410c;
    overflow-wrap: anywhere;
    user-select: all;
    margin: 12px 0;
}

.mt-copy {
    background: #14532d;
    border: none;
    border-radius: 9px;
    color: #fff;
    padding: 12px 16px;
    font-weight: 700;
    cursor: pointer;
}

.mt-copy:hover {
    background: #166534;
}

.mt-note {
    background: #f0fdf4;
    color: #166534;
    border-radius: 11px;
    padding: 15px;
    margin-top: 20px;
    font-size: 14px;
    line-height: 1.75;
}

.mt-note p {
    margin: 8px 0;
}

.mt-modal-footer {
    padding: 15px 25px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    background: white;
    flex-shrink: 0;
}

.mt-done-button {
    background: #e2e8f0;
    border: 0;
    color: #0f172a;
    padding: 11px 24px;
    border-radius: 9px;
    font-weight: 700;
    cursor: pointer;
}

.mt-done-button:hover {
    background: #cbd5e1;
}

body.mt-modal-open {
    overflow: hidden;
}

@media (max-width: 850px) {
    .mt-details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .mt-modal-grid {
        grid-template-columns: 1fr;
    }

    .mt-modal-qr {
        max-width: 230px;
        margin: auto;
    }
}

@media (max-width: 550px) {
    .mt-top {
        flex-direction: column;
    }

    .mt-details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .mt-main {
        padding: 17px;
    }

    .mt-image {
        width: 70px;
        height: 70px;
    }

    .mt-title {
        font-size: 16px;
    }

    .mt-modal-overlay {
        padding: 10px;
    }

    .mt-modal {
        max-height: 94vh;
        border-radius: 13px;
    }

    .mt-modal-header {
        padding: 16px;
    }

    .mt-modal-header h3 {
        font-size: 17px;
    }

    .mt-modal-body {
        padding: 16px;
    }

    .mt-modal-footer {
        padding: 12px 16px;
    }

    .mt-modal-price {
        font-size: 23px;
    }
}
</style>

<section class="mt-page">
<div class="mt-container">

    <div class="mt-heading">
        <span class="mt-subtitle">
            TÀI KHOẢN KHÁCH HÀNG
        </span>

        <h2>Các Chuyến Đi Tôi Đã Đăng Ký</h2>

        <p style="color:#64748b">
            Theo dõi lịch trình, trạng thái đăng ký
            và thông tin thanh toán tour.
        </p>
    </div>

    <?php if (!$loggedIn): ?>

        <div class="mt-message">
            <i class="fa-solid fa-lock"
               style="font-size:40px;color:#14532d"></i>

            <h3>Vui lòng đăng nhập</h3>

            <p>
                Bạn cần đăng nhập để xem tour
                và thông tin thanh toán của mình.
            </p>

            <a href="<?= mt_h($base) ?>auth/login.php">
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
                Hãy khám phá những hành trình trekking
                phù hợp với bạn.
            </p>

            <a href="<?= mt_h($base) ?>tours/tour.php">
                Khám phá tour
            </a>
        </div>

    <?php else: ?>

        <?php foreach ($bookings as $booking): ?>

            <?php
            $status = trim((string)$booking['status']);

            $paid = mt_is_paid($status);
            $cancelled = mt_is_cancelled($status);
            $canPay = !$paid && !$cancelled;

            $paymentId = 'mt-modal-' . (int)$booking['id'];
            $transferId = 'mt-transfer-' . (int)$booking['id'];

            $image = mt_image(
                $booking['tour_image'],
                $base
            );

            $transfer = mt_transfer($booking);
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

                        <span class="mt-status <?= $paid ? 'paid' : ($cancelled ? 'cancelled' : 'pending') ?>">
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
                                data-payment-modal="<?= mt_h($paymentId) ?>"
                                onclick="openTourPayment(this.dataset.paymentModal)"
                            >
                                <i class="fa-solid fa-qrcode"></i>
                                Xem thanh toán
                            </button>

                            <span style="color:#c2410c;font-size:14px">
                                <i class="fa-solid fa-clock"></i>
                                Chưa xác nhận thanh toán
                            </span>

                        <?php elseif ($paid): ?>

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
            </article>

        <?php endforeach; ?>

    <?php endif; ?>

</div>
</section>

<!-- ==================================
     MODAL THANH TOAN
     Dat ngoai danh sach tour de khong
     bi anh huong boi CSS cua card.
     ================================== -->

<?php if ($loggedIn && !$loadError): ?>

    <?php foreach ($bookings as $booking): ?>

        <?php
        $status = trim((string)$booking['status']);

        if (
            mt_is_paid($status)
            || mt_is_cancelled($status)
        ) {
            continue;
        }

        $paymentId = 'mt-modal-' . (int)$booking['id'];
        $transferId = 'mt-transfer-' . (int)$booking['id'];
        $transfer = mt_transfer($booking);
        ?>

        <div
            class="mt-modal-overlay"
            id="<?= mt_h($paymentId) ?>"
            aria-hidden="true"
            onclick="if(event.target === this) closeTourPayment()"
        >

            <div
                class="mt-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="mt-heading-<?= (int)$booking['id'] ?>"
            >

                <!-- THANH TIEU DE -->
                <div class="mt-modal-header">

                    <div>
                        <h3 id="mt-heading-<?= (int)$booking['id'] ?>">
                            <i class="fa-solid fa-credit-card"></i>
                            Thanh toán chuyển khoản
                        </h3>

                        <p>
                            VNN Travel - Thông tin thanh toán tour
                        </p>
                    </div>

                    <!-- NUT X GOC PHAI -->
                    <button
                        type="button"
                        class="mt-modal-close"
                        aria-label="Đóng cửa sổ thanh toán"
                        onclick="closeTourPayment()"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                </div>

                <!-- NOI DUNG -->
                <div class="mt-modal-body">

                    <div class="mt-modal-tour">

                        <strong>
                            <?= mt_h($booking['tour_title']) ?>
                        </strong>

                        <small>
                            Mã đăng ký:
                            <?= mt_h($booking['booking_code']) ?>
                        </small>

                    </div>

                    <div class="mt-modal-grid">

                        <!-- ANH QR -->
                        <div>
                            <img
                                class="mt-modal-qr"
                                src="<?= mt_h($qrImage) ?>"
                                alt="QR thanh toán MoMo"
                                loading="lazy"
                            >
                        </div>

                        <!-- THONG TIN CHUYEN KHOAN -->
                        <div class="mt-modal-info">

                            <h4>Thông tin thanh toán</h4>

                            <div class="mt-label">
                                Số tiền cần chuyển
                            </div>

                            <div class="mt-modal-price">
                                <?= mt_money($booking['total_price']) ?>
                            </div>

                            <div class="mt-label">
                                Nội dung chuyển khoản bắt buộc
                            </div>

                            <div
                                class="mt-transfer"
                                id="<?= mt_h($transferId) ?>"
                            ><?= mt_h($transfer) ?></div>

                            <button
                                type="button"
                                class="mt-copy"
                                data-transfer-id="<?= mt_h($transferId) ?>"
                                onclick="copyTourContent(this.dataset.transferId)"
                            >
                                <i class="fa-solid fa-copy"></i>
                                Sao chép nội dung
                            </button>

                        </div>

                    </div>

                    <div class="mt-note">

                        <strong>
                            <i class="fa-solid fa-circle-info"></i>
                            Lưu ý thanh toán
                        </strong>

                        <p>
                            Vui lòng chuyển khoản đúng số tiền
                            và nội dung chuyển khoản.
                        </p>

                        <p>
                            Nếu đã chuyển tiền, bạn không cần
                            chuyển lại. Hãy chờ quản lý kiểm tra
                            và xác nhận.
                        </p>

                        <p>
                            Bạn có thể đóng cửa sổ này và mở lại
                            bất cứ lúc nào trước khi thanh toán
                            được xác nhận.
                        </p>

                    </div>

                </div>

                <!-- NUT DONG O DUOI -->
                <div class="mt-modal-footer">

                    <button
                        type="button"
                        class="mt-done-button"
                        onclick="closeTourPayment()"
                    >
                        Đóng
                    </button>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>

<script>
let mtCurrentModal = null;
let mtPreviousFocus = null;

function openTourPayment(modalId) {

    const modal = document.getElementById(modalId);

    if (!modal) return;

    mtPreviousFocus = document.activeElement;

    // Chi cho mo mot popup tai mot thoi diem.
    document.querySelectorAll('.mt-modal-overlay.active')
        .forEach(item => {
            item.classList.remove('active');
            item.setAttribute('aria-hidden', 'true');
        });

    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');

    document.body.classList.add('mt-modal-open');

    mtCurrentModal = modal;

    const closeButton =
        modal.querySelector('.mt-modal-close');

    if (closeButton) closeButton.focus();
}

function closeTourPayment() {

    if (!mtCurrentModal) return;

    mtCurrentModal.classList.remove('active');
    mtCurrentModal.setAttribute('aria-hidden', 'true');

    document.body.classList.remove('mt-modal-open');

    mtCurrentModal = null;

    if (
        mtPreviousFocus &&
        typeof mtPreviousFocus.focus === 'function'
    ) {
        mtPreviousFocus.focus();
    }
}

document.addEventListener('keydown', function(event) {

    if (!mtCurrentModal) return;

    // Bam Esc de dong.
    if (event.key === 'Escape') {
        event.preventDefault();
        closeTourPayment();
        return;
    }

    // Giu focus ben trong popup.
    if (event.key === 'Tab') {
        const buttons = Array.from(
            mtCurrentModal.querySelectorAll(
                'button:not([disabled]), a[href], input:not([disabled])'
            )
        ).filter(el => el.getClientRects().length > 0);

        if (!buttons.length) return;

        const first = buttons[0];
        const last = buttons[buttons.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (
            !event.shiftKey &&
            document.activeElement === last
        ) {
            event.preventDefault();
            first.focus();
        }
    }
});

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
