<?php
// ============================================
// VNN TRAVEL - DANH SACH HUONG DAN VIEN
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Đội Ngũ Hướng Dẫn Viên - VNN Travel';
$active_menu = 'leaders';

function guideH($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Lay danh sach Leader tu database
$leaders = $pdo->query("
    SELECT
        id,
        fullname,
        nickname,
        image,
        hometown,
        experience_years,
        biography,
        mountains,
        skills
    FROM trekking_leaders
    WHERE status = 1
    ORDER BY experience_years DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = rtrim(BASE_URL, '/') . '/';

// Tao avatar minh hoa rieng cho tung Leader
function leaderPhoto($leader) {

    // Uu tien anh da nhap trong trang quan ly
    if (!empty($leader['image'])) {
        return $leader['image'];
    }

    // Moi Leader co mot avatar khac nhau
    $seed = 'VNN-Trekking-Leader-' . (int)$leader['id'];

    return 'https://api.dicebear.com/9.x/adventurer/svg?seed='
        . rawurlencode($seed)
        . '&backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc';
}

// Rut gon tieu su
function leaderSummary($text, $length = 145) {
    $text = trim((string)$text);

    if ($text === '') {
        return 'Hướng dẫn viên trekking đồng hành cùng bạn trong những hành trình khám phá và chinh phục núi rừng.';
    }

    return mb_strimwidth(
        $text,
        0,
        $length,
        '...',
        'UTF-8'
    );
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>

/* ==========================================
   TRANG DANH SACH LEADER
   ========================================== */

.leaders-page {
    background: #f8fafc;
    min-height: 80vh;
    padding: 65px 20px 95px;
}

.leaders-container {
    max-width: 1250px;
    margin: 0 auto;
}

/* ==========================================
   TIEU DE
   ========================================== */

.leaders-heading {
    text-align: center;
    margin: 0 auto 48px;
    max-width: 800px;
}

.leaders-heading .subtitle {
    display: inline-block;
    color: #ea580c;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 13px;
}

.leaders-heading h1 {
    font-family: Arial, Helvetica, sans-serif;
    font-size: clamp(28px, 4vw, 40px);
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
    margin: 0 0 16px;
}

.leaders-heading h1 span {
    color: #166534;
}

.leaders-heading p {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 15px;
    line-height: 1.9;
    color: #64748b;
    margin: 0 auto;
}

/* ==========================================
   LUOI THE HUONG DAN VIEN
   ========================================== */

.leaders-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 25px;
}

/* ==========================================
   THE LEADER
   ========================================== */

.leader-card {
    display: flex;
    flex-direction: column;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    color: inherit;
    text-decoration: none;
    box-shadow: 0 5px 20px rgba(15, 23, 42, 0.05);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.leader-card:hover {
    transform: translateY(-7px);
    box-shadow: 0 15px 30px rgba(15, 23, 42, 0.13);
}

/* ==========================================
   HINH ANH
   ========================================== */

.leader-image-box {
    position: relative;
    width: 100%;
    height: 305px;
    overflow: hidden;
    background: linear-gradient(
        145deg,
        #dbeafe,
        #dcfce7
    );
}

.leader-image-box img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s ease;
}

.leader-card:hover .leader-image-box img {
    transform: scale(1.06);
}

.leader-experience {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 2;
    padding: 9px 13px;
    border-radius: 25px;
    background: rgba(22, 101, 52, 0.95);
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
}

.leader-experience i {
    margin-right: 4px;
}

/* ==========================================
   NOI DUNG THE
   ========================================== */

.leader-card-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 22px;
}

.leader-card-name {
    font-family: Arial, Helvetica, sans-serif;
    color: #0f172a;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.4;
    margin: 0 0 8px;
}

.leader-card-role {
    color: #ea580c;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 15px;
}

.leader-card-description {
    color: #64748b;
    font-size: 13px;
    line-height: 1.85;
    margin: 0 0 18px;
    flex: 1;
}

.leader-card-location {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #475569;
    margin-bottom: 17px;
}

.leader-card-location i {
    color: #ea580c;
}

.leader-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    color: #166534;
    font-size: 12px;
    font-weight: 800;
}

.leader-card-footer i {
    transition: transform 0.2s;
}

.leader-card:hover .leader-card-footer .arrow {
    transform: translateX(4px);
}

/* ==========================================
   KHI CHUA CO LEADER
   ========================================== */

.leaders-empty {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 16px;
    color: #64748b;
}

.leaders-empty i {
    display: block;
    color: #166534;
    font-size: 50px;
    margin-bottom: 20px;
}

.leaders-empty h2 {
    color: #0f172a;
    margin-bottom: 10px;
}

/* ==========================================
   RESPONSIVE
   ========================================== */

@media (max-width: 1100px) {
    .leaders-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 800px) {
    .leaders-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .leader-image-box {
        height: 270px;
    }
}

@media (max-width: 550px) {
    .leaders-page {
        padding: 40px 15px 65px;
    }

    .leaders-grid {
        grid-template-columns: 1fr;
    }

    .leader-image-box {
        height: 340px;
    }

    .leader-card-body {
        padding: 20px;
    }
}

</style>

<main class="leaders-page">

    <div class="leaders-container">

        <!-- TIEU DE TRANG -->

        <section class="leaders-heading">

            <span class="subtitle">
                VNN TRAVEL TEAM
            </span>

            <h1>
                Đội Ngũ <span>Hướng Dẫn Viên</span>
                Trekking
            </h1>

            <p>
                Đằng sau mỗi hành trình chinh phục là những
                người dẫn đường tận tâm và giàu kinh nghiệm.

                Cùng gặp gỡ đội ngũ Leader Trekking,
                khám phá câu chuyện, những đỉnh núi từng
                chinh phục và các hành trình họ đang phụ trách.
            </p>

        </section>

        <!-- DANH SACH THE LEADER -->

        <?php if (!empty($leaders)): ?>

            <section class="leaders-grid">

                <?php foreach ($leaders as $leader): ?>

                    <?php
                    $leaderId = (int)$leader['id'];

                    $leaderName = $leader['fullname'];

                    $leaderNickname =
                        !empty($leader['nickname'])
                        ? $leader['nickname']
                        : 'Leader Trekking';

                    $leaderImage = leaderPhoto($leader);

                    $leaderDescription = leaderSummary(
                        $leader['biography']
                    );

                    $leaderHometown =
                        !empty($leader['hometown'])
                        ? $leader['hometown']
                        : 'Việt Nam';

                    $leaderExperience =
                        (int)$leader['experience_years'];

                    $detailUrl =
                        $baseUrl
                        . 'pages/leader-detail.php?id='
                        . $leaderId;
                    ?>

                    <a
                        href="<?= guideH($detailUrl) ?>"
                        class="leader-card"
                    >

                        <!-- HINH ANH -->

                        <div class="leader-image-box">

                            <img
                                src="<?= guideH($leaderImage) ?>"
                                alt="Hình minh họa <?= guideH($leaderName) ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                onerror="this.onerror=null;this.src='https://api.dicebear.com/9.x/shapes/svg?seed=VNNTravel';"
                            >

                            <span class="leader-experience">

                                <i class="fa-solid fa-mountain"></i>

                                <?= $leaderExperience ?> năm

                            </span>

                        </div>

                        <!-- NOI DUNG -->

                        <div class="leader-card-body">

                            <h2 class="leader-card-name">
                                <?= guideH($leaderName) ?>
                            </h2>

                            <div class="leader-card-role">

                                <i class="fa-solid fa-person-hiking"></i>

                                <?= guideH($leaderNickname) ?>

                            </div>

                            <p class="leader-card-description">
                                <?= guideH($leaderDescription) ?>
                            </p>

                            <div class="leader-card-location">

                                <i class="fa-solid fa-location-dot"></i>

                                <?= guideH($leaderHometown) ?>

                            </div>

                            <div class="leader-card-footer">

                                <span>
                                    <i class="fa-solid fa-compass"></i>
                                    Hướng dẫn viên
                                </span>

                                <span>
                                    Xem hồ sơ
                                    <i class="fa-solid fa-arrow-right arrow"></i>
                                </span>

                            </div>

                        </div>

                    </a>

                <?php endforeach; ?>

            </section>

        <?php else: ?>

            <div class="leaders-empty">

                <i class="fa-solid fa-person-hiking"></i>

                <h2>
                    Đội ngũ đang được cập nhật
                </h2>

                <p>
                    Thông tin hướng dẫn viên Trekking
                    sẽ sớm được bổ sung.
                </p>

            </div>

        <?php endif; ?>

    </div>

</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
