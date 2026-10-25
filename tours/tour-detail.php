<?php
// VNN Travel - Chi tiet tour
// So cho tinh theo tung dot va booking thuc te

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function td_h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT t.*, l.name AS loc_name, l.region
    FROM tours t
    JOIN locations l ON t.location_id = l.id
    WHERE t.id = ? AND t.status = 1
    LIMIT 1
");
$stmt->execute([$id]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header("Location: " . BASE_URL . "tours/tour.php");
    exit;
}

$page_title = $tour['title'];
$active_menu = 'tour';

// Lay cho con lai dung voi tour-register.php
$depStmt = $pdo->prepare("
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
    ORDER BY d.departure_date
");
$depStmt->execute([$id]);
$departures = $depStmt->fetchAll(PDO::FETCH_ASSOC);

$reviewsStmt = $pdo->prepare("
    SELECT r.*, u.fullname
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.tour_id = ? AND r.status = 1
    ORDER BY r.id DESC
");
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

$firstAvailable = null;

foreach ($departures as $dep) {
    if ((int)$dep['remaining'] > 0) {
        $firstAvailable = $dep['departure_date'];
        break;
    }
}

$registerUrl = BASE_URL .
    'tours/tour-register.php?id=' . $id;

if ($firstAvailable) {
    $registerUrl .= '&departure=' .
        rawurlencode($firstAvailable);
}

$heroImage = (string)($tour['image'] ?? '');
$description = (string)($tour['description'] ?? '');
$schedule = trim((string)($tour['schedule'] ?? ''));

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.td-page {
    background: #f8fafc;
    padding-bottom: 60px;
}

.td-container {
    max-width: 1180px;
    margin: auto;
    padding: 0 20px;
}

.td-bar {
    background: white;
    padding: 15px 0;
    box-shadow: 0 3px 14px rgba(0,0,0,.08);
    position: sticky;
    top: 0;
    z-index: 100;
}

.td-bar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.td-tabs {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}

.td-tabs a {
    text-decoration: none;
    padding: 9px 14px;
    border-radius: 22px;
    background: #f1f5f9;
    color: #475569;
    font-size: 14px;
    font-weight: 700;
}

.td-tabs a:hover {
    background: #ffedd5;
    color: #c2410c;
}

.td-hero {
    background: linear-gradient(
        rgba(15,23,42,.5),
        rgba(15,23,42,.8)
    );
    background-position: center;
    background-size: cover;
    min-height: 400px;
    display: flex;
    align-items: end;
    color: white;
    padding: 50px 0;
}

.td-hero h1 {
    font-size: clamp(28px, 4vw, 44px);
    font-weight: 800;
    margin: 15px 0;
    line-height: 1.3;
}

.td-hero-info {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    font-size: 15px;
    line-height: 1.6;
}

.td-hero-info i {
    color: #fb923c;
    margin-right: 5px;
}

.td-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin: 35px 0;
    align-items: stretch;
}

.td-box {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    padding: 28px;
    box-shadow: 0 6px 18px rgba(0,0,0,.04);
}

.td-box h2 {
    color: #0f172a;
    font-weight: 800;
    margin-bottom: 18px;
}

.td-section {
    margin: 35px 0;
    scroll-margin-top: 100px;
}

.td-label {
    color: #ea580c;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .5px;
}

.td-description {
    line-height: 1.9;
    color: #475569;
}

.td-tip {
    margin-top: 30px;
    padding: 18px;
    border-radius: 10px;
    background: #fff7ed;
    border-left: 4px solid #f97316;
    color: #9a3412;
    line-height: 1.8;
}

.td-booking-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    border: 1px solid #fed7aa;
    box-shadow: 0 7px 20px rgba(0,0,0,.07);
}

.td-booking-head {
    background: linear-gradient(135deg,#f97316,#ea580c);
    padding: 24px;
    text-align: center;
    color: white;
}

.td-booking-price {
    font-size: 31px;
    font-weight: 900;
    margin: 7px 0;
}

.td-booking-body {
    padding: 22px;
}

.td-date-box {
    background: #f8fafc;
    border-radius: 11px;
    border: 1px solid #e2e8f0;
    padding: 15px;
    margin-bottom: 18px;
}

.td-date-box label {
    display: block;
    margin-bottom: 10px;
    font-weight: 800;
    color: #334155;
}

.td-date-box select {
    width: 100%;
}

.td-remaining {
    font-size: 14px;
    font-weight: 700;
    color: #166534;
    margin-top: 10px;
}

.td-booking-button {
    display: block;
    text-decoration: none;
    text-align: center;
    background: #f97316;
    border-radius: 28px;
    color: white;
    padding: 14px;
    font-weight: 800;
    margin-top: 20px;
}

.td-booking-button:hover {
    color: white;
    background: #ea580c;
}

.td-booking-button.disabled {
    pointer-events: none;
    background: #94a3b8;
    cursor: not-allowed;
}

.td-detail-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin: 14px 0;
    font-size: 14px;
}

.td-schedule-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.td-schedule-day {
    border-top: 4px solid #f97316;
    background: white;
    padding: 23px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    line-height: 1.8;
    color: #475569;
}

.td-schedule-day h3 {
    font-size: 16px;
    color: #c2410c;
    margin-bottom: 15px;
}

.td-accordion {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-top: 13px;
    overflow: hidden;
}

.td-accordion summary {
    font-weight: 800;
    padding: 18px 20px;
    background: #f8fafc;
    cursor: pointer;
    color: #334155;
}

.td-accordion > div {
    padding: 20px;
    background: #f0fdf4;
    line-height: 1.8;
    color: #334155;
}

.td-accordion p {
    margin-bottom: 11px;
}

.td-review {
    border-bottom: 1px solid #e2e8f0;
    padding: 17px 0;
}

.td-review strong {
    color: #0f172a;
}

.td-review-stars {
    color: #eab308;
}

.td-review p {
    color: #475569;
    margin-top: 8px;
}

@media(max-width:950px) {
    .td-grid {
        grid-template-columns: 1fr;
    }

    .td-schedule-grid {
        grid-template-columns: 1fr;
    }

    .td-bar {
        position: static;
    }
}

@media(max-width:600px) {
    .td-box {
        padding: 19px;
    }

    .td-booking-price {
        font-size: 27px;
    }

    .td-tabs {
        overflow-x: auto;
    }
}
</style>

<div class="td-page">

    <div class="td-bar">
        <div class="td-container td-bar-inner">

            <a
                href="<?= BASE_URL ?>tours/tour.php"
                class="btn btn-outline btn-sm"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Tour khác
            </a>

            <div class="td-tabs">
                <a href="#tongquan">Tổng quan</a>
                <a href="#thongtin">Thông tin & Đặt tour</a>
                <a href="#lichtrinh">Lịch trình</a>
                <a href="#luuy">Dịch vụ & Lưu ý</a>
                <a href="#danhgia">Đánh giá</a>
            </div>

            <?php if ($firstAvailable): ?>
                <a
                    href="<?= td_h($registerUrl) ?>"
                    class="btn btn-accent btn-sm"
                >
                    <i class="fa-solid fa-ticket"></i>
                    Đặt chỗ ngay
                </a>
            <?php endif; ?>

        </div>
    </div>

    <section
        id="tongquan"
        class="td-hero"
        style="background-image:
            linear-gradient(rgba(15,23,42,.5),rgba(15,23,42,.8)),
            url('<?= td_h($heroImage) ?>')"
    >

        <div class="td-container" style="width:100%">

            <span class="tour-badge">
                <?= td_h($tour['difficulty']) ?>
            </span>

            <h1><?= td_h($tour['title']) ?></h1>

            <div class="td-hero-info">

                <span>
                    <i class="fa-solid fa-location-dot"></i>
                    <?= td_h($tour['loc_name']) ?>
                    (<?= td_h($tour['region']) ?>)
                </span>

                <span>
                    <i class="fa-regular fa-clock"></i>
                    <?= td_h($tour['duration']) ?>
                </span>

                <span>
                    <i class="fa-solid fa-mountain"></i>
                    <?= td_h($tour['altitude']) ?>
                </span>

                <span>
                    <i class="fa-solid fa-person-hiking"></i>
                    <?= td_h($tour['distance'] ?? '') ?>
                </span>

            </div>

        </div>
    </section>

    <div class="td-container">

        <section id="thongtin" class="td-grid td-section">

            <div class="td-box">

                <span class="td-label">
                    <i class="fa-solid fa-circle-info"></i>
                    THÔNG TIN TOUR
                </span>

                <h2>
                    Hành Trình <?= td_h($tour['title']) ?>
                </h2>

                <div class="td-description">
                    <?= nl2br(td_h($description)) ?>
                </div>

                <div class="td-tip">

                    <strong>
                        <i class="fa-solid fa-user-group"></i>
                        LỜI KHUYÊN TỪ LEADER
                    </strong>

                    <p>
                        Thành viên cần chuẩn bị thể lực,
                        trang phục và các vật dụng cá nhân
                        phù hợp với điều kiện địa hình,
                        thời tiết của chuyến đi.
                    </p>

                </div>

            </div>

            <div class="td-booking-card">

                <div class="td-booking-head">

                    <small>GIÁ TOUR TRỌN GÓI</small>

                    <div class="td-booking-price">
                        <?= format_price($tour['price']) ?>
                    </div>

                    <small>/ mỗi khách</small>

                </div>

                <div class="td-booking-body">

                    <div class="td-date-box">

                        <label for="tdDeparture">
                            <i class="fa-solid fa-calendar-days"
                               style="color:#ea580c"></i>

                            Chọn Ngày Xuất Phát:
                        </label>

                        <?php if ($departures): ?>

                            <select
                                id="tdDeparture"
                                class="form-control"
                            >

                                <?php foreach ($departures as $dep): ?>

                                    <?php
                                    $remaining = (int)$dep['remaining'];
                                    ?>

                                    <option
                                        value="<?= td_h($dep['departure_date']) ?>"
                                        data-remaining="<?= $remaining ?>"
                                        <?= $remaining <= 0 ? 'disabled' : '' ?>
                                    >
                                        <?= date(
                                            'd/m/Y',
                                            strtotime($dep['departure_date'])
                                        ) ?>

                                        —

                                        <?= $remaining > 0
                                            ? 'Còn ' . $remaining . ' chỗ'
                                            : 'Đã hết chỗ' ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div
                                class="td-remaining"
                                id="tdRemaining"
                                aria-live="polite"
                            ></div>

                        <?php else: ?>

                            <p style="color:#b91c1c">
                                Chưa có lịch khởi hành mở đăng ký.
                            </p>

                        <?php endif; ?>

                    </div>

                    <div class="td-detail-row">
                        <span>Thời lượng:</span>
                        <strong><?= td_h($tour['duration']) ?></strong>
                    </div>

                    <div class="td-detail-row">
                        <span>Điểm đón:</span>
                        <strong><?= td_h($tour['meeting_point'] ?? '') ?></strong>
                    </div>

                    <?php if ($firstAvailable): ?>

                        <a
                            id="tdBookingButton"
                            href="<?= td_h($registerUrl) ?>"
                            class="td-booking-button"
                        >
                            <i class="fa-solid fa-ticket"></i>
                            ĐĂNG KÝ THEO NGÀY ĐÃ CHỌN
                        </a>

                    <?php else: ?>

                        <div class="td-booking-button disabled">
                            CHƯA CÓ ĐỢT CÒN CHỖ
                        </div>

                    <?php endif; ?>

                    <p style="text-align:center;
                              margin-top:17px;
                              font-size:13px;
                              color:#64748b">
                        <i class="fa-solid fa-phone"></i>
                        Hotline tư vấn: 0988.888.888
                    </p>

                </div>

            </div>

        </section>

        <section id="lichtrinh" class="td-section">

            <span class="td-label">
                <i class="fa-solid fa-route"></i>
                HÀNH TRÌNH KHÁM PHÁ
            </span>

            <h2 class="section-title">
                Lịch Trình Chi Tiết Từng Ngày
            </h2>

            <p style="color:#64748b;margin-bottom:20px">
                Xe đưa đón:
                <?= td_h($tour['transport'] ?? '') ?>

                |

                Điểm tập trung:
                <?= td_h($tour['meeting_point'] ?? '') ?>
            </p>

            <div class="td-schedule-grid">

                <?php if ($schedule !== ''): ?>

                    <?php
                    $days = preg_split(
                        '/(?=NGÀY\s+\d+)/u',
                        $schedule,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    );

                    foreach ($days as $day):

                        $lines = array_values(array_filter(
                            array_map('trim', explode("\n", $day)),
                            fn($line) => $line !== ''
                        ));

                        if (!$lines) continue;

                        $heading = array_shift($lines);
                    ?>

                        <div class="td-schedule-day">

                            <h3>
                                <i class="fa-regular fa-calendar-check"></i>
                                <?= td_h($heading) ?>
                            </h3>

                            <?php foreach ($lines as $line): ?>

                                <p><?= td_h($line) ?></p>

                            <?php endforeach; ?>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="td-box">
                        Lịch trình chi tiết đang được cập nhật.
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <section id="luuy" class="td-section">

            <div class="td-box">

                <span class="td-label">
                    <i class="fa-solid fa-shield-halved"></i>
                    QUY ĐỊNH & AN TOÀN
                </span>

                <h2>Dịch Vụ Bao Gồm & Những Lưu Ý Quan Trọng</h2>

                <details class="td-accordion" open>

                    <summary>Dịch Vụ Tour Bao Gồm</summary>

                    <div>
                        <p>Phương tiện đưa đón theo chương trình tour.</p>
                        <p>Hướng dẫn viên và đội hỗ trợ trong hành trình.</p>
                        <p>Ăn uống, lưu trú và vật dụng chung theo chương trình.</p>
                        <p>Hỗ trợ y tế cơ bản trong quá trình trekking.</p>
                        <p>Các quyền lợi bảo hiểm áp dụng theo chương trình tour đã công bố.</p>
                    </div>

                </details>

                <details class="td-accordion">

                    <summary>Dịch Vụ Tour Không Bao Gồm</summary>

                    <div>
                        <p>Chi tiêu cá nhân phát sinh ngoài chương trình.</p>
                        <p>Đồ uống và dịch vụ mua thêm ngoài tiêu chuẩn.</p>
                        <p>Dịch vụ porter cá nhân ngoài gói nếu có phát sinh.</p>
                        <p>Tiền bồi dưỡng tự nguyện cho đội phục vụ.</p>
                    </div>

                </details>

                <details class="td-accordion">

                    <summary>Bạn Cần Chuẩn Bị Những Gì?</summary>

                    <div>
                        <p>Balo trekking, giày leo núi và quần áo phù hợp thời tiết.</p>
                        <p>Áo ấm, áo mưa, đèn pin, bình nước và sạc dự phòng.</p>
                        <p>Các loại thuốc cá nhân nếu cần thiết.</p>
                        <p>Chủ động rèn luyện thể lực trước ngày khởi hành.</p>
                    </div>

                </details>

                <details class="td-accordion">

                    <summary>Quy Định Hoàn & Hủy Tour</summary>

                    <div>
                        <p>
                            Chính sách hủy hoặc đổi ngày thực hiện theo
                            điều kiện của chương trình đã đăng ký.
                        </p>
                        <p>
                            Khách hàng vui lòng liên hệ VNN Travel
                            để được kiểm tra và xác nhận yêu cầu.
                        </p>
                    </div>

                </details>

            </div>

        </section>

        <section id="danhgia" class="td-section">

            <div class="td-box">

                <h2>
                    <i class="fa-solid fa-comments"
                       style="color:#f97316"></i>

                    Đánh Giá Của Các Trekker
                    (<?= count($reviews) ?>)
                </h2>

                <?php if (isLoggedIn()): ?>

                    <form
                        action="<?= BASE_URL ?>tours/tour-review.php"
                        method="POST"
                        style="background:#f8fafc;
                               padding:20px;
                               border-radius:10px;
                               margin-bottom:20px"
                    >

                        <input
                            type="hidden"
                            name="tour_id"
                            value="<?= $id ?>"
                        >

                        <div class="form-group">

                            <label>Mức độ hài lòng</label>

                            <select name="rating"
                                    class="form-control">
                                <option value="5">5 sao</option>
                                <option value="4">4 sao</option>
                                <option value="3">3 sao</option>
                            </select>

                        </div>

                        <div class="form-group">

                            <label>Chia sẻ cảm nhận của bạn</label>

                            <textarea
                                name="comment"
                                class="form-control"
                                rows="3"
                                required
                            ></textarea>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary btn-sm"
                        >
                            Gửi đánh giá
                        </button>

                    </form>

                <?php else: ?>

                    <p style="margin:20px 0">
                        Vui lòng
                        <a href="<?= BASE_URL ?>auth/login.php">
                            đăng nhập
                        </a>
                        để đánh giá chuyến đi.
                    </p>

                <?php endif; ?>

                <?php if ($reviews): ?>

                    <?php foreach ($reviews as $review): ?>

                        <div class="td-review">

                            <strong>
                                <?= td_h($review['fullname']) ?>
                            </strong>

                            <span class="td-review-stars">
                                <?= str_repeat(
                                    '★',
                                    max(0, min(5, (int)$review['rating']))
                                ) ?>
                            </span>

                            <p>
                                <?= nl2br(td_h($review['comment'])) ?>
                            </p>

                            <small style="color:#94a3b8">
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($review['created_at'])
                                ) ?>
                            </small>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p style="color:#64748b">
                        Chưa có đánh giá nào cho chuyến đi này.
                    </p>

                <?php endif; ?>

            </div>

        </section>

    </div>

</div>

<script>
(function() {
    const select = document.getElementById('tdDeparture');
    const button = document.getElementById('tdBookingButton');
    const notice = document.getElementById('tdRemaining');

    if (!select || !button) return;

    const baseUrl = <?= json_encode(
        BASE_URL . 'tours/tour-register.php?id=' . $id . '&departure=',
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    function updateDeparture() {
        const option = select.options[select.selectedIndex];
        if (!option) return;

        const remaining = Number(option.dataset.remaining || 0);

        notice.textContent = remaining > 0
            ? 'Ngày này còn ' + remaining + ' chỗ.'
            : 'Ngày này đã hết chỗ.';

        button.href = baseUrl + encodeURIComponent(select.value);

        button.classList.toggle('disabled', remaining <= 0);
        button.setAttribute('aria-disabled', String(remaining <= 0));
    }

    const firstAvailable = Array.from(select.options)
        .find(opt => Number(opt.dataset.remaining) > 0);

    if (firstAvailable) {
        select.value = firstAvailable.value;
    }

    select.addEventListener('change', updateDeparture);
    updateDeparture();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
