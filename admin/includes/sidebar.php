<?php
$current_page = $current_page ?? '';

if (!function_exists('vnnCount')) {

    function vnnCount(PDO $pdo, string $sql): int {
        try {
            return max(0, (int)$pdo->query($sql)->fetchColumn());
        } catch (Throwable $e) {
            error_log('Sidebar: ' . $e->getMessage());
            return 0;
        }
    }

    function vnnMenu(
        string $page,
        string $url,
        string $icon,
        string $title,
        int $count = 0
    ): void {

        global $current_page;

        $active = $current_page === $page ? 'active' : '';

        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeIcon = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');

        echo '<li class="' . $active . '">';
        echo '<a href="' . $safeUrl . '">';

        echo '<span class="menu-label">';
        echo '<i class="fa-solid ' . $safeIcon . '"></i>';
        echo '<span>' . $safeTitle . '</span>';
        echo '</span>';

        if ($count > 0) {
            echo '<span class="vnn-notification">';
            echo '<span class="vnn-notification-dot"></span>';
            echo $count > 99 ? '99+' : $count;
            echo '</span>';
        }

        echo '</a></li>';
    }
}

$adminBase = rtrim(BASE_URL, '/') . '/admin/';
$siteBase = rtrim(BASE_URL, '/') . '/';

$pendingTours = vnnCount(
    $pdo,
    "SELECT COUNT(*) FROM tour_bookings
     WHERE status = 'Chờ xác nhận'"
);

$pendingOrders = vnnCount(
    $pdo,
    "SELECT COUNT(*) FROM orders
     WHERE status = 'Chờ xác nhận'"
);

$pendingReviews = vnnCount(
    $pdo,
    "SELECT COUNT(*) FROM reviews WHERE status = 0"
);
?>

<style>
.admin-sidebar {
    width: 250px !important;
    min-width: 250px !important;
    max-width: 250px !important;
    flex: 0 0 250px !important;
    height: 100vh;
    position: sticky;
    top: 0;
    overflow-x: hidden;
    overflow-y: auto;
    background: #1e293b;
    color: #e2e8f0;
    z-index: 100;
}

.admin-main {
    flex: 1 1 auto;
    min-width: 0;
    max-width: none;
    overflow-x: auto;
}

.vnn-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 76px;
    padding: 15px;
    background: #0f172a;
}

.vnn-brand i {
    font-size: 34px;
    color: #65a30d;
}

.vnn-brand-title {
    font-size: 23px;
    font-weight: 900;
    letter-spacing: 3px;
    line-height: 1.1;
}

.vnn-brand-title span:first-child {
    color: #38bdf8;
}

.vnn-brand-title span:last-child {
    color: #f97316;
}

.vnn-brand-sub {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 3px;
    color: white;
    margin-top: 4px;
}

.vnn-view-website {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 12px;
    margin: 13px;
    border: 1px solid #334155;
    border-radius: 9px;
    color: #38bdf8;
    font-size: 13px;
    font-weight: 800;
    text-align: center;
    text-decoration: none;
}

.admin-menu,
.admin-menu ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.admin-menu li > a {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 9px;
    padding: 12px 15px;
    color: #cbd5e1;
    font-size: 13px;
    text-decoration: none;
}

.admin-menu li > a:hover,
.admin-menu li.active > a {
    background: #334155;
    color: #38bdf8;
}

.admin-menu li.active > a {
    border-left: 4px solid #f97316;
}

.admin-menu .menu-label {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.admin-menu .menu-label i {
    width: 19px;
    flex-shrink: 0;
    text-align: center;
}

.admin-menu .menu-label span {
    overflow-wrap: anywhere;
}

.vnn-group-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 14px 15px;
    border: 0;
    background: #253247;
    color: white;
    font-size: 13px;
    font-weight: 800;
    text-align: left;
    cursor: pointer;
}

.vnn-group-heading:hover {
    background: #334155;
}

.vnn-submenu {
    display: none;
    background: #141d2b;
}

.vnn-group.open > .vnn-submenu {
    display: block;
}

.vnn-submenu li > a {
    padding-left: 19px;
}

.vnn-chevron {
    transition: transform .2s;
}

.vnn-group.open .vnn-chevron {
    transform: rotate(180deg);
}

.vnn-notification {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 4px;
    background: #ef4444;
    color: white;
    border-radius: 20px;
    min-width: 23px;
    height: 23px;
    padding: 0 7px;
    font-size: 11px;
    font-weight: 900;
}

.vnn-notification-dot {
    width: 5px;
    height: 5px;
    background: white;
    border-radius: 50%;
}

@media(max-width:700px) {
    .admin-sidebar {
        width: 200px !important;
        min-width: 200px !important;
        max-width: 200px !important;
        flex-basis: 200px !important;
    }

    .admin-menu li > a,
    .vnn-group-heading {
        font-size: 12px;
        padding-left: 9px;
        padding-right: 9px;
    }
}
</style>

<aside class="admin-sidebar">

    <div class="vnn-brand">
        <i class="fa-solid fa-mountain-sun"></i>

        <div>
            <div class="vnn-brand-title">
                <span>V</span><span>NN</span>
            </div>

            <div class="vnn-brand-sub">
                TRAVEL
            </div>
        </div>
    </div>

    <a
        href="<?= htmlspecialchars($siteBase, ENT_QUOTES, 'UTF-8') ?>index.php"
        class="vnn-view-website"
        target="_blank"
        rel="noopener"
    >
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        Xem Website Trực Tiếp
    </a>

    <ul class="admin-menu">

        <?php
        vnnMenu(
            'dashboard',
            $adminBase . 'index.php',
            'fa-gauge',
            'Dashboard Bàn Làm Việc'
        );
        ?>

        <?php if (isStaff() || isManager() || isAdmin()): ?>

            <li class="vnn-group open">

                <button
                    class="vnn-group-heading"
                    type="button"
                    onclick="vnnToggleMenu(this)"
                    aria-expanded="true"
                >
                    <span>
                        <i class="fa-solid fa-headset"></i>
                        XỬ LÝ KHÁCH HÀNG
                    </span>

                    <i class="fa-solid fa-chevron-down vnn-chevron"></i>
                </button>

                <ul class="vnn-submenu">

                    <?php
                    vnnMenu(
                        'chat',
                        $adminBase . 'chat/',
                        'fa-comments',
                        'Chat Trực Tuyến'
                    );

                    vnnMenu(
                        'bookings',
                        $adminBase . 'bookings/',
                        'fa-ticket',
                        'Duyệt Tour',
                        $pendingTours
                    );

                    vnnMenu(
                        'orders',
                        $adminBase . 'orders/',
                        'fa-box',
                        'Duyệt Đơn Hàng',
                        $pendingOrders
                    );

                    vnnMenu(
                        'reviews',
                        $adminBase . 'reviews/',
                        'fa-star',
                        'Đánh Giá Từ Khách',
                        $pendingReviews
                    );

                    vnnMenu(
                        'contacts',
                        $adminBase . 'contacts/',
                        'fa-envelope',
                        'Tin Nhắn Liên Hệ'
                    );
                    ?>

                </ul>
            </li>

        <?php endif; ?>

        <?php if (isManager()): ?>

            <li class="vnn-group open">

                <button
                    class="vnn-group-heading"
                    type="button"
                    onclick="vnnToggleMenu(this)"
                    aria-expanded="true"
                >
                    <span>
                        <i class="fa-solid fa-boxes-stacked"></i>
                        NỘI DUNG & KHO
                    </span>

                    <i class="fa-solid fa-chevron-down vnn-chevron"></i>
                </button>

                <ul class="vnn-submenu">

                    <?php
                    vnnMenu(
                        'tours',
                        $adminBase . 'tours/',
                        'fa-person-hiking',
                        'Quản Lý Tour'
                    );

                    vnnMenu(
                        'leaders',
                        $adminBase . 'leaders/',
                        'fa-person-hiking',
                        'Đội Ngũ Leader'
                    );

                    vnnMenu(
                        'products',
                        $adminBase . 'products/',
                        'fa-warehouse',
                        'Dụng Cụ, Kho & Danh Mục'
                    );

                    vnnMenu(
                        'locations',
                        $adminBase . 'locations/',
                        'fa-map-location-dot',
                        'Điểm Đến Trekking'
                    );

                    vnnMenu(
                        'posts',
                        $adminBase . 'posts/',
                        'fa-newspaper',
                        'Bài Viết & Cẩm Nang'
                    );
                    ?>

                </ul>
            </li>

            <?php
            vnnMenu(
                'revenue',
                $adminBase . 'revenue.php',
                'fa-chart-line',
                'Doanh Thu & Báo Cáo'
            );
            ?>

        <?php endif; ?>

        <?php if (isAdmin()): ?>

            <li class="vnn-group open">

                <button
                    class="vnn-group-heading"
                    type="button"
                    onclick="vnnToggleMenu(this)"
                    aria-expanded="true"
                >
                    <span>
                        <i class="fa-solid fa-shield-halved"></i>
                        QUẢN TRỊ HỆ THỐNG
                    </span>

                    <i class="fa-solid fa-chevron-down vnn-chevron"></i>
                </button>

                <ul class="vnn-submenu">

                    <?php
                    vnnMenu(
                        'users',
                        $adminBase . 'users/',
                        'fa-user-shield',
                        'Cấp Quyền & Tài Khoản'
                    );
                    ?>

                </ul>
            </li>

        <?php endif; ?>

    </ul>

</aside>

<script>
function vnnToggleMenu(button) {
    const group = button.closest('.vnn-group');

    if (!group) return;

    const open = group.classList.toggle('open');

    button.setAttribute(
        'aria-expanded',
        open ? 'true' : 'false'
    );
}
</script>
