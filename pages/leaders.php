<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Đội Ngũ Hướng Dẫn Viên - VNN Travel';
$active_menu = 'leaders';

function guideH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$base = rtrim(BASE_URL, '/') . '/';

$leaders = $pdo->query("
    SELECT
        id,
        fullname,
        nickname,
        image,
        hometown,
        experience_years,
        biography
    FROM trekking_leaders
    WHERE status = 1
    ORDER BY experience_years DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.guide-page {
    padding: 60px 20px 90px;
    background: #f8fafc;
    min-height: 70vh;
}

.guide-container {
    max-width: 1200px;
    margin: auto;
}

.guide-intro {
    text-align: center;
    max-width: 720px;
    margin: 0 auto 45px;
}

.guide-intro small {
    color: #ea580c;
    font-size: 12px;
    letter-spacing: 2px;
    font-weight: 900;
}

.guide-intro h1 {
    font-size: clamp(27px,4vw,42px);
    color: #0f172a;
    margin: 14px 0;
}

.guide-intro p {
    color: #64748b;
    line-height: 1.9;
}

.guide-grid {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 24px;
}

.guide-card {
    display: block;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 6px 20px rgba(15,23,42,.06);
    transition: transform .2s, box-shadow .2s;
}

.guide-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 14px 30px rgba(15,23,42,.13);
}

.guide-photo {
    width: 100%;
    height: 300px;
    background: #e2e8f0;
    display: grid;
    place-items: center;
    color: #64748b;
    font-size: 50px;
}

.guide-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.guide-content {
    padding: 20px;
}

.guide-content h2 {
    font-size: 19px;
    color: #0f172a;
    margin: 0 0 8px;
}

.guide-role {
    color: #ea580c;
    font-size: 13px;
    font-weight: 800;
}

.guide-description {
    font-size: 13px;
    color: #64748b;
    line-height: 1.8;
    margin-top: 12px;
    min-height: 90px;
}

.guide-meta {
    font-size: 13px;
    color: #166534;
    font-weight: 800;
    border-top: 1px solid #e2e8f0;
    padding-top: 15px;
    margin-top: 15px;
}

@media(max-width:1000px) {
    .guide-grid {
        grid-template-columns: repeat(3,1fr);
    }
}

@media(max-width:750px) {
    .guide-grid {
        grid-template-columns: repeat(2,1fr);
    }
}

@media(max-width:500px) {
    .guide-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="guide-page">

    <div class="guide-container">

        <section class="guide-intro">
            <small>VNN TRAVEL TEAM</small>

            <h1>
                Đội Ngũ Hướng Dẫn Viên Trekking
            </h1>

            <p>
                Những người dẫn đường đồng hành cùng bạn
                trên từng hành trình. Khám phá kinh nghiệm,
                các cung đường và câu chuyện của đội ngũ
                Leader tại VNN Travel.
            </p>
        </section>

        <section class="guide-grid">

            <?php foreach ($leaders as $leader): ?>

                <?php
                $description = mb_strimwidth(
                    (string)($leader['biography'] ?? ''),
                    0,
                    145,
                    '...',
                    'UTF-8'
                );
                ?>

                <a
                    class="guide-card"
                    href="<?= guideH($base) ?>pages/leader-detail.php?id=<?= (int)$leader['id'] ?>"
                >
                    <div class="guide-photo">

                        <?php if ($leader['image']): ?>
                            <img
                                src="<?= guideH($leader['image']) ?>"
                                alt="<?= guideH($leader['fullname']) ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <i class="fa-solid fa-person-hiking"></i>
                        <?php endif; ?>

                    </div>

                    <div class="guide-content">

                        <h2>
                            <?= guideH($leader['fullname']) ?>
                        </h2>

                        <div class="guide-role">
                            <?= guideH(
                                $leader['nickname']
                                ?: 'Leader Trekking'
                            ) ?>
                        </div>

                        <p class="guide-description">
                            <?= guideH($description) ?>
                        </p>

                        <div class="guide-meta">
                            <i class="fa-solid fa-mountain"></i>
                            <?= (int)$leader['experience_years'] ?>
                            năm kinh nghiệm

                            <div style="margin-top:8px">
                                Xem hồ sơ chi tiết →
                            </div>
                        </div>

                    </div>
                </a>

            <?php endforeach; ?>

        </section>

        <?php if (!$leaders): ?>
            <p style="text-align:center;padding:40px;color:#64748b">
                Đội ngũ hướng dẫn viên đang được cập nhật.
            </p>
        <?php endif; ?>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
