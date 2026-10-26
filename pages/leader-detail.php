<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function leaderDetailH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT *
    FROM trekking_leaders
    WHERE id = ? AND status = 1
    LIMIT 1
");

$stmt->execute([$id]);
$leader = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$leader) {
    http_response_code(404);
    exit('Không tìm thấy hướng dẫn viên.');
}

$stmt = $pdo->prepare("
    SELECT id, title, image, duration, price
    FROM tours
    WHERE leader_id = ?
      AND status = 1
    ORDER BY id DESC
");

$stmt->execute([$id]);
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gallery = array_values(array_filter(array_map(
    'trim',
    preg_split('/\r\n|\n|\r/', (string)($leader['gallery'] ?? ''))
)));

$sections = [
    'experience_detail' => 'Hành Trình Và Kinh Nghiệm',
    'mountains' => 'Những Cung Đường Đã Chinh Phục',
    'skills' => 'Kỹ Năng Dẫn Đoàn',
    'achievements' => 'Thành Tích Nổi Bật',
    'safety_commitment' => 'Cam Kết An Toàn'
];

$page_title = $leader['fullname'] . ' - VNN Travel';
$active_menu = 'leaders';
$base = rtrim(BASE_URL, '/') . '/';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.ld-hero {
    background: linear-gradient(110deg,#0f172a,#166534);
    color: white;
    padding: 55px 20px;
}

.ld-wrap {
    max-width: 1150px;
    margin: auto;
}

.ld-hero-grid {
    display: grid;
    grid-template-columns: 320px minmax(0,1fr);
    gap: 35px;
    align-items: center;
    margin-top: 30px;
}

.ld-photo {
    width: 100%;
    height: 380px;
    object-fit: cover;
    border-radius: 15px;
    background: #334155;
}

.ld-placeholder {
    height: 380px;
    background: #334155;
    border-radius: 15px;
    display: grid;
    place-items: center;
    font-size: 70px;
}

.ld-hero h1 {
    font-size: clamp(28px,4vw,42px);
    margin: 17px 0;
}

.ld-hero p {
    line-height: 1.9;
}

.ld-body {
    background: #f8fafc;
    padding: 35px 20px 80px;
}

.ld-grid {
    display: grid;
    grid-template-columns: minmax(0,2fr) minmax(230px,1fr);
    gap: 25px;
    align-items: start;
}

.ld-box {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 28px;
    margin-bottom: 23px;
}

.ld-box h2 {
    color: #0f172a;
    font-size: 22px;
    margin-bottom: 20px;
}

.ld-text {
    white-space: pre-line;
    line-height: 2.1;
    color: #475569;
    overflow-wrap: anywhere;
}

.ld-quote {
    background: #fff7ed;
    border-left: 5px solid #ea580c;
    border-radius: 10px;
    padding: 25px;
    line-height: 2;
    color: #9a3412;
}

.ld-gallery {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 15px;
}

.ld-gallery img {
    width: 100%;
    height: 230px;
    object-fit: cover;
    border-radius: 10px;
}

.ld-tour {
    display: flex;
    gap: 15px;
    padding: 16px 0;
    border-bottom: 1px solid #e2e8f0;
    text-decoration: none;
    color: #166534;
}

.ld-tour img {
    width: 115px;
    height: 90px;
    object-fit: cover;
    border-radius: 9px;
}

.ld-side {
    position: sticky;
    top: 20px;
}

@media(max-width:850px) {
    .ld-hero-grid,
    .ld-grid {
        grid-template-columns: 1fr;
    }

    .ld-side {
        position: static;
    }

    .ld-photo {
        max-width: 370px;
    }
}

@media(max-width:500px) {
    .ld-gallery {
        grid-template-columns: 1fr;
    }

    .ld-box {
        padding: 20px;
    }
}
</style>

<main>

    <section class="ld-hero">
        <div class="ld-wrap">

            <a href="<?= leaderDetailH($base) ?>pages/leaders.php"
               style="color:#bbf7d0;text-decoration:none">
                ← Quay lại danh sách hướng dẫn viên
            </a>

            <div class="ld-hero-grid">

                <div>
                    <?php if ($leader['image']): ?>

                        <img
                            class="ld-photo"
                            src="<?= leaderDetailH($leader['image']) ?>"
                            alt="<?= leaderDetailH($leader['fullname']) ?>"
                        >

                    <?php else: ?>

                        <div class="ld-placeholder">
                            <i class="fa-solid fa-person-hiking"></i>
                        </div>

                    <?php endif; ?>
                </div>

                <div>
                    <span style="
                        background:#ea580c;
                        padding:8px 15px;
                        border-radius:25px;
                        font-weight:800;
                    ">
                        VNN TRAVEL LEADER
                    </span>

                    <h1>
                        <?= leaderDetailH($leader['fullname']) ?>
                    </h1>

                    <h3 style="color:#bbf7d0">
                        <?= leaderDetailH(
                            $leader['nickname']
                            ?: 'Hướng dẫn viên Trekking'
                        ) ?>
                    </h3>

                    <p>
                        <?= nl2br(leaderDetailH($leader['biography'])) ?>
                    </p>

                    <p>
                        <strong>
                            <?= (int)$leader['experience_years'] ?>
                            năm kinh nghiệm
                        </strong>

                        · <?= leaderDetailH($leader['hometown']) ?>
                    </p>
                </div>

            </div>
        </div>
    </section>

    <div class="ld-body">
        <div class="ld-wrap ld-grid">

            <div>

                <?php foreach ($sections as $key => $heading): ?>

                    <?php if (!empty($leader[$key])): ?>

                        <section class="ld-box">

                            <h2>
                                <?= leaderDetailH($heading) ?>
                            </h2>

                            <div class="ld-text"><?= leaderDetailH($leader[$key]) ?></div>

                        </section>

                    <?php endif; ?>

                <?php endforeach; ?>

                <?php if ($leader['philosophy']): ?>

                    <section class="ld-box">

                        <h2>Phương Châm Sống & Leo Núi</h2>

                        <blockquote class="ld-quote">
                            <?= nl2br(leaderDetailH($leader['philosophy'])) ?>
                        </blockquote>

                    </section>

                <?php endif; ?>

                <?php if ($gallery): ?>

                    <section class="ld-box">

                        <h2>Hình Ảnh Hoạt Động</h2>

                        <div class="ld-gallery">
                            <?php foreach ($gallery as $photo): ?>
                                <img
                                    src="<?= leaderDetailH($photo) ?>"
                                    alt="Hoạt động trekking"
                                    loading="lazy"
                                >
                            <?php endforeach; ?>
                        </div>

                    </section>

                <?php endif; ?>

                <section class="ld-box">

                    <h2>Các Tour Đang Phụ Trách</h2>

                    <?php foreach ($tours as $tour): ?>

                        <a class="ld-tour"
                           href="<?= leaderDetailH($base) ?>tours/tour-detail.php?id=<?= (int)$tour['id'] ?>">

                            <?php if ($tour['image']): ?>
                                <img src="<?= leaderDetailH($tour['image']) ?>"
                                     alt="">
                            <?php endif; ?>

                            <div>
                                <strong>
                                    <?= leaderDetailH($tour['title']) ?>
                                </strong>

                                <p>
                                    <?= leaderDetailH($tour['duration']) ?>
                                    · Xem chi tiết →
                                </p>
                            </div>

                        </a>

                    <?php endforeach; ?>

                    <?php if (!$tours): ?>
                        <p>Chưa có tour đang phụ trách.</p>
                    <?php endif; ?>

                </section>

            </div>

            <aside class="ld-side">

                <section class="ld-box">
                    <h2>Thông Tin Leader</h2>

                    <p>
                        <strong>Họ tên:</strong>
                        <?= leaderDetailH($leader['fullname']) ?>
                    </p>

                    <p>
                        <strong>Quê quán:</strong>
                        <?= leaderDetailH($leader['hometown']) ?>
                    </p>

                    <p>
                        <strong>Kinh nghiệm:</strong>
                        <?= (int)$leader['experience_years'] ?> năm
                    </p>

                    <p>
                        <strong>Tour phụ trách:</strong>
                        <?= count($tours) ?>
                    </p>
                </section>

            </aside>

        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
