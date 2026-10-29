<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function td_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
    header('Location: ' . BASE_URL . 'tours/tour.php');
    exit;
}

$assignedLeader = null;

if (!empty($tour['leader_id'])) {
    $stmt = $pdo->prepare("
        SELECT id, fullname, nickname, image,
               experience_years, biography
        FROM trekking_leaders
        WHERE id = ? AND status = 1
        LIMIT 1
    ");
    $stmt->execute([(int)$tour['leader_id']]);
    $assignedLeader = $stmt->fetch(PDO::FETCH_ASSOC);
}

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

$registerUrl = BASE_URL . 'tours/tour-register.php?id=' . $id;

if ($firstAvailable) {
    $registerUrl .= '&departure=' . rawurlencode($firstAvailable);
}

$page_title = $tour['title'];
$active_menu = 'tour';
$heroImage = (string)($tour['image'] ?? '');
$description = (string)($tour['description'] ?? '');
$schedule = trim((string)($tour['schedule'] ?? ''));

function tdLines($value) {
    $lines = preg_split('/\r\n|\n|\r/', (string)$value);
    return array_values(array_filter(
        array_map('trim', $lines),
        fn($line) => $line !== ''
    ));
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.td-page {
    background: #f8fafc;
    padding-bottom: 70px;
}

.td-container {
    max-width: 1180px;
    margin: auto;
    padding: 0 20px;
}

.td-bar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: #fff;
    padding: 13px 0;
    box-shadow: 0 3px 15px rgba(0,0,0,.08);
}

.td-bar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 13px;
    flex-wrap: wrap;
}

.td-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.td-tabs a {
    background: #f1f5f9;
    border-radius: 22px;
    padding: 9px 14px;
    text-decoration: none;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
}

.td-tabs a:hover {
    color: #ea580c;
}

.td-hero {
    min-height: 390px;
    padding: 65px 0 50px;
    display: flex;
    align-items: end;
    background-size: cover;
    background-position: center;
    color: white;
}

.td-hero h1 {
    font-size: clamp(27px,4vw,44px);
    line-height: 1.3;
    margin: 15px 0;
    color: white;
}

.td-hero-info {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
}

.td-hero-info i {
    color: #fb923c;
}

.td-grid {
    display: grid;
    grid-template-columns: minmax(0,2fr) minmax(280px,1fr);
    gap: 25px;
    margin: 35px 0;
    align-items: start;
}

.td-content-col {
    min-width: 0;
}

.td-box,
.td-booking-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    box-shadow: 0 5px 18px rgba(0,0,0,.04);
    overflow: hidden;
}

.td-box {
    padding: 27px;
    margin-bottom: 22px;
}

.td-box h2 {
    color: #0f172a;
    font-size: 24px;
    margin: 16px 0 20px;
}

.td-label {
    color: #ea580c;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 1px;
}

.td-description {
    color: #475569;
    line-height: 2;
    overflow-wrap: anywhere;
}

.td-tip {
    background: #f0fdf4;
    border-left: 4px solid #16a34a;
    padding: 20px;
    border-radius: 9px;
    margin-top: 25px;
    color: #166534;
    line-height: 1.9;
}

.td-leader {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}

.td-leader-photo {
    width: 105px;
    height: 115px;
    border-radius: 12px;
    object-fit: cover;
    background: #e2e8f0;
}

.td-leader-name {
    font-size: 21px;
    font-weight: 800;
    color: #0f172a;
}

.td-leader-link {
    color: #166534;
    font-weight: 800;
    text-decoration: none;
}

.td-booking-card {
    position: sticky;
    top: 95px;
}

.td-booking-head {
    background: #166534;
    color: #fff;
    text-align: center;
    padding: 27px 20px;
}

.td-booking-head small {
    color: #dcfce7;
}

.td-booking-price {
    font-size: 31px;
    font-weight: 900;
    margin: 10px 0;
}

.td-booking-body {
    padding: 23px;
}

.td-date-box {
    background: #fff7ed;
    padding: 17px;
    border-radius: 10px;
    margin-bottom: 18px;
}

.td-date-box label {
    display: block;
    font-weight: 800;
    color: #9a3412;
    margin-bottom: 11px;
}

.td-date-box select {
    width: 100%;
}

.td-remaining {
    color: #166534;
    font-size: 13px;
    font-weight: 800;
    margin-top: 10px;
}

.td-booking-button {
    display: block;
    text-decoration: none;
    text-align: center;
    background: #f97316;
    border-radius: 25px;
    color: white;
    padding: 15px;
    font-weight: 800;
    margin-top: 20px;
}

.td-booking-button:hover {
    background: #ea580c;
    color: white;
}

.td-booking-button.disabled {
    background: #94a3b8;
    pointer-events: none;
}

.td-detail-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin: 15px 0;
    font-size: 14px;
}

.td-section {
    margin-top: 35px;
    scroll-margin-top: 100px;
}

.td-section-title {
    font-size: 26px;
    color: #0f172a;
    margin: 12px 0 22px;
}

.td-schedule-grid {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 18px;
}

.td-schedule-day {
    background: white;
    border-top: 4px solid #f97316;
    padding: 22px;
    border-radius: 12px;
    color: #475569;
    line-height: 1.8;
}

.td-schedule-day h3 {
    font-size: 17px;
    color: #c2410c;
    margin-bottom: 13px;
}

.td-accordion {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-top: 12px;
    overflow: hidden;
}

.td-accordion summary {
    padding: 17px 19px;
    background: #f8fafc;
    font-weight: 800;
    cursor: pointer;
}

.td-accordion > div {
    padding: 19px;
    color: #475569;
    line-height: 1.9;
    white-space: pre-line;
}

.td-review {
    padding: 18px 0;
    border-bottom: 1px solid #e2e8f0;
}

.td-review-stars {
    color: #eab308;
    margin-left: 10px;
}

@media(max-width:950px) {
    .td-grid,
    .td-schedule-grid {
        grid-template-columns: 1fr;
    }

    .td-booking-card {
        position: static;
    }

    .td-bar {
        position: static;
    }
}

@media(max-width:600px) {
    .td-box {
        padding: 18px;
    }

    .td-tabs {
        overflow-x: auto;
    }
}
</style>

<div class="td-page">

    <div class="td-bar">
        <div class="td-container td-bar-inner">

            <a href="<?= BASE_URL ?>tours/tour.php"
               class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrow-left"></i>
                Tour khác
            </a>

            <nav class="td-tabs">
                <a href="#tongquan">Tổng quan</a>
                <a href="#thongtin">Thông tin</a>
                <a href="#leader">Leader</a>
                <a href="#lichtrinh">Lịch trình</a>
                <a href="#luuy">Dịch vụ & Lưu ý</a>
                <a href="#danhgia">Đánh giá</a>
            </nav>

            <?php if ($firstAvailable): ?>
                <a href="<?= td_h($registerUrl) ?>"
                   class="btn btn-accent btn-sm">
                    <i class="fa-solid fa-ticket"></i>
                    Đặt chỗ ngay
                </a>
            <?php endif; ?>

        </div>
    </div>

    <section id="tongquan"
             class="td-hero"
             style="background-image:
             linear-gradient(rgba(15,23,42,.5),rgba(15,23,42,.8)),
             url('<?= td_h($heroImage) ?>')">

        <div class="td-container" style="width:100%">

            <span class="tour-badge">
                <?= td_h($tour['difficulty']) ?>
            </span>

            <h1>
                <?= td_h($tour['title']) ?>
            </h1>

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

        <section id="thongtin" class="td-grid">

            <div class="td-content-col">

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
                            Nên chuẩn bị thể lực, trang phục
                            và trang bị cá nhân phù hợp
                            với địa hình, thời tiết chuyến đi.
                        </p>
                    </div>
                </div>

                <div class="td-box" id="leader">
                    <span class="td-label">
                        <i class="fa-solid fa-person-hiking"></i>
                        LEADER PHỤ TRÁCH
                    </span>

                    <?php if ($assignedLeader): ?>

                        <div class="td-leader" style="margin-top:18px">

                            <?php if (!empty($assignedLeader['image'])): ?>
                                <img
                                    class="td-leader-photo"
                                    src="<?= td_h($assignedLeader['image']) ?>"
                                    alt="<?= td_h($assignedLeader['fullname']) ?>"
                                >
                            <?php else: ?>
                                <div class="td-leader-photo"
                                     style="display:grid;place-items:center;font-size:35px">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <div class="td-leader-name">
                                    <?= td_h($assignedLeader['fullname']) ?>
                                </div>

                                <p>
                                    <?= td_h(
                                        $assignedLeader['nickname']
                                        ?: 'Hướng dẫn viên Trekking'
                                    ) ?>
                                </p>

                                <p>
                                    <strong>
                                        <?= (int)$assignedLeader['experience_years'] ?>
                                        năm kinh nghiệm
                                    </strong>
                                </p>

                                <p style="color:#64748b;font-size:13px">
                                    Phụ trách tất cả ngày khởi hành của tour này.
                                </p>

                                <a class="td-leader-link"
                                   href="<?= rtrim(BASE_URL, '/') ?>/pages/leader-detail.php?id=<?= (int)$assignedLeader['id'] ?>">
                                    Xem hồ sơ chi tiết
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>

                        </div>

                    <?php else: ?>

                        <p style="margin-top:15px;color:#64748b">
                            Hướng dẫn viên đang được cập nhật.
                        </p>

                    <?php endif; ?>
                </div>

            </div>

            <aside class="td-booking-card">

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
                            <i class="fa-solid fa-calendar-days"></i>
                            Chọn Ngày Xuất Phát
                        </label>

                        <?php if ($departures): ?>

                            <select id="tdDeparture" class="form-control">

                                <?php foreach ($departures as $dep): ?>
                                    <?php $remaining = (int)$dep['remaining']; ?>

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

                            <div class="td-remaining"
                                 id="tdRemaining"
                                 aria-live="polite"></div>

                        <?php else: ?>

                            <p style="color:#b91c1c">
                                Chưa có lịch khởi hành mở đăng ký.
                            </p>

                        <?php endif; ?>
                    </div>

                    <div class="td-detail-row">
                        <span>Thời lượng</span>
                        <strong><?= td_h($tour['duration']) ?></strong>
                    </div>

                    <div class="td-detail-row">
                        <span>Điểm đón</span>
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

                    <p style="text-align:center;font-size:13px;color:#64748b;margin-top:17px">
                        <i class="fa-solid fa-phone"></i>
                        Hotline tư vấn: 0988.888.888
                    </p>

                </div>
            </aside>

        </section>

        <section id="lichtrinh" class="td-section">

            <span class="td-label">
                <i class="fa-solid fa-route"></i>
                HÀNH TRÌNH KHÁM PHÁ
            </span>

            <h2 class="td-section-title">
                Lịch Trình Chi Tiết Từng Ngày
            </h2>

            <p style="color:#64748b;margin-bottom:22px">
                Phương tiện: <?= td_h($tour['transport'] ?? '') ?>
                ·
                Điểm tập trung: <?= td_h($tour['meeting_point'] ?? '') ?>
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
                    ?>

                    <?php foreach ($days as $day): ?>

                        <?php
                        $lines = tdLines($day);

                        if (!$lines) {
                            continue;
                        }

                        $heading = array_shift($lines);
                        ?>

                        <article class="td-schedule-day">

                            <h3>
                                <i class="fa-regular fa-calendar-check"></i>
                                <?= td_h($heading) ?>
                            </h3>

                            <?php foreach ($lines as $line): ?>
                                <p><?= td_h($line) ?></p>
                            <?php endforeach; ?>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="td-box">
                        Lịch trình đang được cập nhật.
                    </div>

                <?php endif; ?>

            </div>
        </section>

        <section id="luuy" class="td-section">

            <div class="td-box">

                <span class="td-label">
                    <i class="fa-solid fa-shield-halved"></i>
                    DỊCH VỤ & AN TOÀN
                </span>

                <h2>Dịch Vụ Và Những Lưu Ý Quan Trọng</h2>

                <?php
                $accordions = [
                    ['Dịch Vụ Tour Bao Gồm', $tour['includes'] ?? ''],
                    ['Dịch Vụ Tour Không Bao Gồm', $tour['excludes'] ?? ''],
                    ['Bạn Cần Chuẩn Bị Những Gì?', $tour['requirements'] ?? ''],
                    ['Quy Định & Lưu Ý An Toàn', $tour['safety_notes'] ?? '']
                ];
                ?>

                <?php foreach ($accordions as $index => $item): ?>

                    <details class="td-accordion"
                             <?= $index === 0 ? 'open' : '' ?>>

                        <summary>
                            <?= td_h($item[0]) ?>
                        </summary>

                        <div>
                            <?= $item[1] !== ''
                                ? nl2br(td_h($item[1]))
                                : 'Đang cập nhật thông tin.' ?>
                        </div>

                    </details>

                <?php endforeach; ?>

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
                        style="background:#f8fafc;padding:20px;border-radius:10px;margin-bottom:25px"
                    >
                        <input type="hidden"
                               name="tour_id"
                               value="<?= $id ?>">

                        <div class="form-group">
                            <label>Mức độ hài lòng</label>

                            <select name="rating" class="form-control">
                                <option value="5">5 sao</option>
                                <option value="4">4 sao</option>
                                <option value="3">3 sao</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Chia sẻ cảm nhận của bạn</label>

                            <textarea name="comment"
                                      class="form-control"
                                      rows="3"
                                      required></textarea>
                        </div>

                        <button type="submit"
                                class="btn btn-primary btn-sm">
                            Gửi đánh giá
                        </button>

                    </form>

                <?php else: ?>

                    <p>
                        Vui lòng
                        <a href="<?= BASE_URL ?>auth/login.php">
                            đăng nhập
                        </a>
                        để đánh giá chuyến đi.
                    </p>

                <?php endif; ?>

                <?php if ($reviews): ?>

                    <?php foreach ($reviews as $review): ?>

                        <article class="td-review">

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

                        </article>

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

        if (notice) {
            notice.textContent = remaining > 0
                ? 'Ngày này còn ' + remaining + ' chỗ.'
                : 'Ngày này đã hết chỗ.';
        }

        button.href = baseUrl + encodeURIComponent(select.value);
        button.classList.toggle('disabled', remaining <= 0);
        button.setAttribute('aria-disabled', String(remaining <= 0));
    }

    const firstAvailable = Array.from(select.options)
        .find(option => Number(option.dataset.remaining) > 0);

    if (firstAvailable) {
        select.value = firstAvailable.value;
    }

    select.addEventListener('change', updateDeparture);
    updateDeparture();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
