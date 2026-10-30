
<?php
$page_title = "Trang Chủ - VNN Travel";
$active_menu = 'home';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$featured_tours = $pdo->query("
    SELECT t.*,
           IFNULL(l.name, 'Tây Bắc') AS loc_name
    FROM tours t
    LEFT JOIN locations l ON t.location_id = l.id
    ORDER BY t.id ASC
    LIMIT 9
")->fetchAll(PDO::FETCH_ASSOC);

$featured_products = $pdo->query("
    SELECT *
    FROM products
    WHERE status = 1
    ORDER BY id DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

function home_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.about-gl-section {
    padding:70px 0;
    background:#fff;
}
.about-gl-grid {
    display:grid;
    grid-template-columns:1.1fr 1fr;
    gap:50px;
    align-items:center;
}
.about-gl-badge {
    display:inline-block;
    background:#ffedd5;
    color:#ea580c;
    font-size:.85rem;
    font-weight:800;
    padding:6px 16px;
    border-radius:20px;
    text-transform:uppercase;
    margin-bottom:15px;
}
.about-gl-title {
    font-size:2.8rem;
    font-weight:800;
    color:#0f172a;
    line-height:1.25;
    margin-bottom:20px;
}
.about-gl-title span {color:#f97316}
.about-gl-desc {
    color:#475569;
    font-size:1.05rem;
    line-height:1.8;
    margin-bottom:25px;
}
.about-feature-cards {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
}
.about-f-card {
    background:#fff;
    border:1px solid #f1f5f9;
    padding:24px;
    border-radius:16px;
    box-shadow:0 4px 20px rgba(0,0,0,.04);
    transition:transform .25s ease,border-color .25s ease;
}
.about-f-card:hover {
    transform:translateY(-4px);
    border-color:#fed7aa;
}
.about-f-icon {
    width:48px;
    height:48px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.3rem;
    margin-bottom:16px;
}
.about-f-card h4 {
    font-size:1.1rem;
    color:#0f172a;
    margin-bottom:8px;
}
.about-f-card p {
    font-size:.88rem;
    color:#64748b;
    line-height:1.6;
    margin:0;
}
.tour-card {
    background:#fff;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 4px 18px rgba(0,0,0,.06);
    border:1px solid #e2e8f0;
    display:flex;
    flex-direction:column;
    transition:transform .25s ease;
}
.tour-card:hover {transform:translateY(-5px)}
.card-img-wrap {
    position:relative;
    height:220px;
    background:#f1f5f9;
    overflow:hidden;
}
.card-img-wrap img {
    width:100%;
    height:100%;
    object-fit:cover;
}
.tour-badge {
    position:absolute;
    top:14px;
    left:14px;
    background:#ea580c;
    color:#fff;
    font-size:.78rem;
    font-weight:700;
    padding:4px 12px;
    border-radius:20px;
}
/* Khong hien so cho tren the tour trang chu */
.tour-card .tour-slots {display:none!important}
.card-body {
    padding:20px;
    flex:1;
    display:flex;
    flex-direction:column;
}
.home-prod-card {
    background:#fff;
    border-radius:12px;
    padding:16px;
    border:1px solid #e2e8f0;
    box-shadow:0 2px 8px rgba(0,0,0,.04);
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    transition:transform .25s ease,box-shadow .25s ease;
}
.home-prod-card:hover {
    transform:translateY(-4px);
    box-shadow:0 8px 20px rgba(0,0,0,.08);
}
.home-prod-thumb {
    height:195px;
    border-radius:8px;
    overflow:hidden;
    background:#f8fafc;
    margin-bottom:12px;
}
.home-prod-thumb img {
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}
.home-prod-brand {
    font-size:.72rem;
    font-weight:800;
    text-transform:uppercase;
    color:#94a3b8;
    letter-spacing:.8px;
    margin-bottom:6px;
    display:block;
}
.home-prod-title {
    font-size:.96rem!important;
    font-weight:700!important;
    color:#0f172a!important;
    line-height:1.5!important;
    margin:0 0 14px!important;
    padding:0!important;
    height:auto!important;
    max-height:none!important;
    min-height:56px;
    display:block!important;
    word-break:break-word;
}
.home-prod-title a {
    color:#0f172a;
    text-decoration:none;
}
.home-prod-title a:hover {color:#ea580c}
.home-prod-bottom {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding-top:10px;
    border-top:1px solid #f1f5f9;
    margin-top:auto;
}
.home-prod-price {
    color:#ea580c;
    font-size:1.15rem;
    font-weight:800;
}
@media(max-width:992px) {
    .about-gl-grid {grid-template-columns:1fr}
}
@media(max-width:600px) {
    .about-gl-title {font-size:2rem}
    .about-feature-cards {grid-template-columns:1fr}
}
</style>

<!-- BANNER TRANG CHU -->
<section class="hero">
    <div class="hero-content">
        <h1>Khám phá - Chinh phục - Trải nghiệm</h1>
        <p>
            Hành trình đặt chân lên những đỉnh núi huyền thoại cùng VNN Travel.
        </p>
        <a
            href="<?= BASE_URL ?>tours/tour.php"
            class="btn btn-accent"
            style="padding:14px 34px;font-size:1.1rem;border-radius:30px"
        >
            <i class="fa-solid fa-compass"></i>
            KHÁM PHÁ TẤT CẢ TOUR
        </a>
    </div>
</section>

<!-- VE CHUNG TOI -->
<section class="about-gl-section">
    <div class="container">
        <div class="about-gl-grid">

            <div>
                <span class="about-gl-badge">VỀ CHÚNG TÔI</span>

                <h2 class="about-gl-title">
                    Khám Phá <span>& Chinh Phục</span>
                    Cùng VNN Travel
                </h2>

                <p class="about-gl-desc">
                    <strong>VNN Travel</strong> không chỉ mang đến
                    các hành trình trekking chinh phục thiên nhiên
                    hùng vĩ với độ an toàn cao nhất, mà còn trao
                    gửi cho bạn những chuyến đi
                    <strong>chạm đến trái tim</strong>
                    và bứt phá mọi giới hạn của bản thân.
                </p>

                <div style="
                    background:#fff7ed;
                    border-left:4px solid #f97316;
                    padding:18px 22px;
                    border-radius:8px;
                    margin-bottom:25px;
                ">
                    <p style="
                        margin:0;
                        font-size:.95rem;
                        color:#7c2d12;
                        line-height:1.7;
                    ">
                        "Mỗi bước chân trên cung đường đại ngàn
                        cùng VNN Travel là một trải nghiệm rèn
                        luyện ý chí, gắn kết đồng đội và tận hưởng
                        vẻ đẹp hoang sơ đích thực của đất trời Việt Nam."
                    </p>
                </div>

                <a
                    href="<?= BASE_URL ?>tours/tour.php"
                    style="
                        color:#ea580c;
                        font-weight:700;
                        text-decoration:none;
                        font-size:1rem;
                    "
                >
                    Khám phá các hành trình trekking &rarr;
                </a>
            </div>

            <div class="about-feature-cards">

                <div class="about-f-card">
                    <div class="about-f-icon"
                         style="background:#ffedd5;color:#ea580c">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h4>Hướng dẫn viên tận tâm</h4>
                    <p>
                        Năng động, nhiệt tình, am hiểu địa hình
                        rừng núi, mang đến trải nghiệm thân thiện
                        và an toàn tuyệt đối suốt hành trình.
                    </p>
                </div>

                <div class="about-f-card">
                    <div class="about-f-icon"
                         style="background:#fee2e2;color:#dc2626">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                    <h4>Ẩm thực phong phú</h4>
                    <p>
                        Thực đơn nóng sốt đa dạng với tiệc BBQ,
                        lẩu gà đồi thơm lừng giữa mây trời,
                        đầy đủ dinh dưỡng và hỗ trợ ăn chay theo yêu cầu.
                    </p>
                </div>

                <div class="about-f-card">
                    <div class="about-f-icon"
                         style="background:#dbeafe;color:#0284c7">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h4>An toàn & Chuẩn quốc tế</h4>
                    <p>
                        Túi cứu thương y tế dã ngoại, gậy leo núi
                        trợ lực, túi ngủ ấm áp chịu âm độ và bảo hiểm
                        du lịch lên đến 100.000.000đ/vụ.
                    </p>
                </div>

                <div class="about-f-card">
                    <div class="about-f-icon"
                         style="background:#fef3c7;color:#d97706">
                        <i class="fa-solid fa-fire"></i>
                    </div>
                    <h4>Năng lượng tích cực</h4>
                    <p>
                        Cộng đồng trẻ, yêu khám phá và đam mê leo núi,
                        luôn san sẻ từng ngụm nước, tạo nên không khí
                        sôi nổi và tràn đầy niềm vui.
                    </p>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- TOUR TREKKING NOI BAT -->
<section class="container section" style="padding-top:20px">

    <div class="section-header">
        <span class="section-subtitle">
            Chinh Phục Đỉnh Cao
        </span>
        <h2 class="section-title">
            Các Cung Đường Trekking Nổi Bật
        </h2>
    </div>

    <div class="grid grid-3">

        <?php if (!empty($featured_tours)): ?>

            <?php foreach ($featured_tours as $t): ?>

                <div class="tour-card">

                    <div class="card-img-wrap">

                        <img
                            src="<?= home_h($t['image']) ?>"
                            alt="<?= home_h($t['title']) ?>"
                            loading="lazy"
                        >

                        <span class="tour-badge">
                            <?= home_h($t['difficulty']) ?>
                        </span>

                        <!-- DA BO NHAN CON BAO NHIEU CHO -->

                    </div>

                    <div class="card-body">

                        <span style="
                            font-size:.82rem;
                            color:var(--primary);
                            font-weight:bold;
                            margin-bottom:4px;
                            display:inline-block;
                        ">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= home_h($t['loc_name']) ?>
                        </span>

                        <h3 style="margin:4px 0 10px;font-size:1.15rem">

                            <a
                                href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= (int)$t['id'] ?>"
                                style="color:#0f172a;text-decoration:none"
                            >
                                <?= home_h($t['title']) ?>
                            </a>

                        </h3>

                        <div class="tour-meta" style="
                            display:flex;
                            gap:15px;
                            font-size:.85rem;
                            color:#64748b;
                            margin-bottom:12px;
                        ">

                            <span>
                                <i class="fa-regular fa-clock"></i>
                                <?= home_h($t['duration']) ?>
                            </span>

                            <span>
                                <i class="fa-solid fa-mountain"></i>
                                <?= home_h($t['altitude']) ?>
                            </span>

                        </div>

                        <div class="tour-footer" style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            margin-top:auto;
                            padding-top:10px;
                            border-top:1px solid #f1f5f9;
                        ">

                            <span class="price-tag" style="
                                color:#ea580c;
                                font-weight:800;
                                font-size:1.1rem;
                            ">
                                <?= format_price($t['price']) ?>
                            </span>

                            <a
                                href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= (int)$t['id'] ?>"
                                class="btn btn-primary btn-sm"
                            >
                                Xem chi tiết
                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div style="
                grid-column:span 3;
                text-align:center;
                padding:50px 0;
                color:#64748b;
            ">
                Chưa có dữ liệu tour nào trong hệ thống.
            </div>

        <?php endif; ?>

    </div>

    <div style="text-align:center;margin-top:35px">

        <a
            href="<?= BASE_URL ?>tours/tour.php"
            class="btn btn-outline"
            style="
                padding:12px 35px;
                border-radius:30px;
                font-weight:bold;
            "
        >
            Xem tất cả các chuyến tour trekking &rarr;
        </a>

    </div>

</section>

<!-- SAN PHAM BAN CHAY -->
<section class="container section" style="padding-top:0">

    <div class="section-header">

        <span class="section-subtitle">
            Trang Bị Chuyên Nghiệp
        </span>

        <h2 class="section-title">
            Dụng Cụ Leo Núi Bán Chạy
        </h2>

    </div>

    <div class="grid grid-4">

        <?php foreach ($featured_products as $p): ?>

            <div class="home-prod-card">

                <div>

                    <div class="home-prod-thumb">

                        <a href="<?= BASE_URL ?>shop/product-detail.php?id=<?= (int)$p['id'] ?>">

                            <img
                                src="<?= home_h($p['image']) ?>"
                                alt="<?= home_h($p['name']) ?>"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1510312305653-8ed496efae75?w=500';"
                            >

                        </a>

                    </div>

                    <span class="home-prod-brand">
                        <?= home_h($p['brand'] ?? '') ?>
                    </span>

                    <h4 class="home-prod-title">

                        <a href="<?= BASE_URL ?>shop/product-detail.php?id=<?= (int)$p['id'] ?>">
                            <?= home_h($p['name']) ?>
                        </a>

                    </h4>

                </div>

                <div class="home-prod-bottom">

                    <span class="home-prod-price">
                        <?= format_price($p['price']) ?>
                    </span>

                    <a
                        href="<?= BASE_URL ?>shop/product-detail.php?id=<?= (int)$p['id'] ?>"
                        class="btn btn-primary btn-sm"
                        style="border-radius:8px"
                    >
                        <i class="fa-solid fa-cart-plus"></i>
                    </a>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <div style="text-align:center;margin-top:35px">
        <a
            href="<?= BASE_URL ?>shop/shop.php"
            class="btn btn-outline"
            style="padding:12px 35px;border-radius:30px;font-weight:bold;"
        >
            Xem tất cả các sản phẩm &rarr;
        </a>
    </div>

</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
