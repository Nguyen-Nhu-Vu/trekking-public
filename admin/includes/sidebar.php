<?php
// VNN TRAVEL - SIDEBAR QUAN LY DUNG CHUNG

$current_page = $current_page ?? '';

if (!function_exists('vnnSidebarCount')) {

    function vnnSidebarCount(PDO $pdo, string $sql): int
    {
        try {
            return max(
                0,
                (int) $pdo->query($sql)->fetchColumn()
            );
        } catch (Throwable $e) {
            error_log(
                'VNN Sidebar: ' . $e->getMessage()
            );

            return 0;
        }
    }

    function vnnSidebarItem(
        string $key,
        string $url,
        string $icon,
        string $title,
        int $count = 0
    ): void {
        global $current_page;

        $active = $current_page === $key
            ? 'active'
            : '';

        $url = htmlspecialchars(
            $url,
            ENT_QUOTES,
            'UTF-8'
        );

        $title = htmlspecialchars(
            $title,
            ENT_QUOTES,
            'UTF-8'
        );

        $icon = htmlspecialchars(
            $icon,
            ENT_QUOTES,
            'UTF-8'
        );

        echo '<li class="' . $active . '">';

        echo '<a href="' . $url . '">';

        echo '<span class="vnn-menu-label">';

        echo '<i class="fa-solid ' . $icon . '"></i>';

        echo '<span>' . $title . '</span>';

        echo '</span>';

        if ($count > 0) {
            echo '<span class="vnn-notification" ' .
                 'aria-label="' . $count . ' thông báo">';

            echo '<span class="vnn-notification-dot"></span>';

            echo $count > 99 ? '99+' : $count;

            echo '</span>';
        }

        echo '</a>';

        echo '</li>';
    }
}

$adminBase = rtrim(BASE_URL, '/') . '/admin/';
$siteBase = rtrim(BASE_URL, '/') . '/';

// SO LUONG CHO XU LY

$pendingTours = vnnSidebarCount(
    $pdo,
    "SELECT COUNT(*)
     FROM tour_bookings
     WHERE status IN ('Chờ xác nhận', 'pending')"
);

$pendingOrders = vnnSidebarCount(
    $pdo,
    "SELECT COUNT(*)
     FROM orders
     WHERE status IN ('Chờ xác nhận', 'pending')"
);

// Status = 0 duoc xem la danh gia cho duyet
// neu database dang su dung quy uoc nay.
$pendingReviews = vnnSidebarCount(
    $pdo,
    "SELECT COUNT(*)
     FROM reviews
     WHERE status = 0"
);

// Khong dem contacts neu chua co trang thai da doc.
// Tranh tao thong bao do gia cho tin nhan cu.

$customerPages = [
    'chat',
    'bookings',
    'orders',
    'reviews',
    'contacts'
];

$contentPages = [
    'tours',
    'products',
    'categories',
    'locations',
    'posts'
];

$revenuePages = [
    'revenue_tours',
    'revenue_shop'
];

$isCustomerPage = in_array(
    $current_page,
    $customerPages,
    true
);

$isContentPage = in_array(
    $current_page,
    $contentPages,
    true
);

$isRevenuePage = in_array(
    $current_page,
    $revenuePages,
    true
);
?>

<style>
    .admin-sidebar {
        width: 250px !important;
        min-width: 250px !important;
        max-width: 250px !important;
        flex: 0 0 250px !important;
        flex-shrink: 0 !important;

        height: 100vh;
        position: sticky;
        top: 0;

        background: #1e293b;
        color: #e2e8f0;

        overflow-y: auto;
        overflow-x: hidden;

        z-index: 100;
    }

    .admin-main {
        flex: 1 1 auto;
        min-width: 0;
        max-width: none;
        overflow-x: auto;
    }

    .vnn-admin-brand {
        min-height: 76px;
        display: flex;
        align-items: center;
        gap: 12px;

        padding: 15px;
        background: #0f172a;
    }

    .vnn-brand-mountain {
        font-size: 35px;
        color: #65a30d;
        flex-shrink: 0;
    }

    .vnn-brand-title {
        font-size: 23px;
        font-weight: 900;
        letter-spacing: 3px;
        line-height: 1.1;
    }

    .vnn-brand-title .blue {
        color: #38bdf8;
    }

    .vnn-brand-title .orange {
        color: #f97316;
    }

    .vnn-brand-subtitle {
        color: white;
        font-size: 11px;
        letter-spacing: 3px;
        font-weight: 800;
        margin-top: 4px;
    }

    .vnn-view-website {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 9px;

        margin: 13px;
        padding: 13px 9px;

        border-radius: 9px;
        border: 1px solid #334155;

        color: #38bdf8;
        font-weight: 800;
        font-size: 13px;
        text-align: center;
        text-decoration: none;
    }

    .vnn-view-website:hover {
        background: #334155;
        color: white;
    }

    .admin-menu {
        margin: 0;
        padding: 0;
        list-style: none;
        width: 100%;
    }

    .admin-menu li {
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

    .vnn-menu-label {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .vnn-menu-label > i {
        width: 19px;
        flex-shrink: 0;
        text-align: center;
    }

    .vnn-menu-label > span {
        overflow-wrap: anywhere;
    }

    .vnn-group {
        width: 100%;
    }

    .vnn-group-heading {
        width: 100%;
        padding: 14px 15px;

        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;

        background: #253247;
        border: none;

        color: white;
        font-size: 13px;
        font-weight: 800;
        text-align: left;
        cursor: pointer;
    }

    .vnn-group-heading:hover {
        background: #334155;
    }

    .vnn-group-heading > span {
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .vnn-group-heading .vnn-chevron {
        transition: transform .2s ease;
    }

    .vnn-group.open .vnn-chevron {
        transform: rotate(180deg);
    }

    .vnn-group .sidebar-sub-menu {
        display: none;
        margin: 0;
        padding: 0;
        list-style: none;
        background: #141d2b;
    }

    .vnn-group.open > .sidebar-sub-menu {
        display: block;
    }

    .vnn-group .sidebar-sub-menu li a {
        padding-left: 19px;
    }

    .vnn-notification {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;

        min-width: 25px;
        height: 23px;
        padding: 0 7px;

        border-radius: 20px;
        background: #ef4444;

        color: white;
        font-size: 11px;
        font-weight: 900;

        white-space: nowrap;
        flex-shrink: 0;
    }

    .vnn-notification-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: white;
    }

    @media (max-width: 700px) {
        .admin-sidebar {
            width: 200px !important;
            min-width: 200px !important;
            max-width: 200px !important;
            flex-basis: 200px !important;
        }

        .vnn-brand-title {
            font-size: 19px;
        }

        .admin-menu li > a,
        .vnn-group-heading {
            font-size: 12px;
            padding-left: 9px;
            padding-right: 9px;
        }
    }
</style>

<aside class="admin-sidebar" id="vnnAdminSidebar">

    <!-- LOGO -->

    <div class="vnn-admin-brand">

        <div class="vnn-brand-mountain">
            <i class="fa-solid fa-mountain-sun"></i>
        </div>

        <div>
            <div class="vnn-brand-title">
                <span class="blue">V</span><span class="orange">NN</span>
            </div>

            <div class="vnn-brand-subtitle">
                TRAVEL
            </div>
        </div>

    </div>

    <!-- TRUY CAP WEBSITE -->

    <a
        class="vnn-view-website"
        href="<?= htmlspecialchars($siteBase, ENT_QUOTES, 'UTF-8') ?>index.php"
        target="_blank"
        rel="noopener"
    >
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        Xem Website Trực Tiếp
    </a>

    <ul class="admin-menu">

        <?php
        vnnSidebarItem(
            'dashboard',
            $adminBase . 'index.php',
            'fa-gauge',
            'Dashboard Bàn Làm Việc'
        );
        ?>

        <!-- XỬ LÝ KHÁCH HÀNG -->

        <?php if (isStaff() || isManager() || isAdmin()): ?>

            <li class="vnn-group <?= $isCustomerPage ? 'open' : 'open' ?>">

                <button
                    type="button"
                    class="vnn-group-heading"
                    aria-expanded="true"
                    onclick="vnnToggleGroup(this)"
                >
                    <span>
                        <i
                            class="fa-solid fa-headset"
                            style="color:#38bdf8"
                        ></i>
                        XỬ LÝ KHÁCH HÀNG
                    </span>

                    <i class="fa-solid fa-chevron-down vnn-chevron"></i>
                </button>

                <ul class="sidebar-sub-menu">

                    <?php
                    vnnSidebarItem(
                        'chat',
                        $adminBase . 'chat/',
                        'fa-comments',
                        'Chat Trực Tuyến'
                    );

                    vnnSidebarItem(
                        'bookings',
                        $adminBase . 'bookings/',
                        'fa-ticket',
                        'Duyệt Tour',
                        $pendingTours
                    );

                    vnnSidebarItem(
                        'orders',
                        $adminBase . 'orders/',
                        'fa-box',
                        'Duyệt Đơn Hàng',
                        $pendingOrders
                    );

                    vnnSidebarItem(
                        'reviews',
                        $adminBase . 'reviews/',
                        'fa-star',
                        'Đánh Giá Từ Khách',
                        $pendingReviews
                    );

                    vnnSidebarItem(
                        'contacts',
                        $adminBase . 'contacts/',
                        'fa-envelope',
                        'Tin Nhắn Liên Hệ'
                    );
                    ?>

                </ul>

            </li>

        <?php endif; ?>

        <!-- NỘI DUNG VÀ KHO -->

        <?php if (isManager()): ?>

            <li class="vnn-group <?= $isContentPage ? 'open' : 'open' ?>">

                <button
                    type="button"
                    class="vnn-group-heading"
                    aria-expanded="true"
                    onclick="vnnToggleGroup(this)"
                >
                    <span>
                        <i
                            class="fa-solid fa-boxes-stacked"
                            style="color:#f97316"
                        ></i>
                        NỘI DUNG & KHO
                    </span>

                    <i class="fa-solid fa-chevron-down vnn-chevron"></i>
                </button>

                <ul class="sidebar-sub-menu">

                    <?php
                    vnnSidebarItem(
                        'tours',
                        $adminBase . 'tours/',
                        'fa-person-hiking',
                        'Quản Lý Tour'
                    );

                    vnnSidebarItem(
                        'products',
                        $adminBase . 'products/',
                        'fa-warehouse',
                        'Dụng Cụ, Kho & Danh Mục'
                    );

                    vnnSidebarItem(
                        'locations',
                        $adminBase . 'locations/',
                        'fa-map-location-dot',
                        'Điểm Đến Trekking'
                    );

                    vnnSidebarItem(
                        'posts',
                        $adminBase . 'posts/',
                        'fa-newspaper',
                        'Bài Viết & Cẩm Nang'
                    );
                    ?>

                </ul>

            </li>

            <!-- DOANH THU -->

            <li class="vnn-group <?= $isRevenuePage ? 'open' : 'open' ?>">

                <button
                    type="button"
                    class="vnn-group-heading"
                    aria-expanded="true"
                    onclick="vnnToggleGroup(this)"
                >
                    <span>
                        <i
                            class="fa-solid fa-chart-line"
                            style="color:#22c55e"
                        ></i>
                        DOANH THU & BÁO CÁO
                    </span>

                    <i class="fa-solid fa-chevron-down vnn-chevron"></i>
                </button>

                <ul class="sidebar-sub-menu">

                    <?php
                    vnnSidebarItem(
                        'revenue_tours',
                        $adminBase . 'revenue-tours.php',
                        'fa-chart-column',
                        'Doanh Thu Tour'
                    );

                    vnnSidebarItem(
                        'revenue_shop',
                        $adminBase . 'revenue-shop.php',
                        'fa-bag-shopping',
                        'Doanh Thu Bán Hàng'
                    );
                    ?>

                </ul>

            </li>

        <?php endif; ?>

        <!-- QUẢN TRỊ -->

        <?php if (isAdmin()): ?>

            <li class="vnn-group open">

                <button
                    type="button"
                    class="vnn-group-heading"
                    aria-expanded="true"
                    onclick="vnnToggleGroup(this)"
                >
                    <span>
                        <i class="fa-solid fa-shield-halved"></i>
                        QUẢN TRỊ HỆ THỐNG
                    </span>

                    <i class="fa-solid fa-chevron-down vnn-chevron"></i>
                </button>

                <ul class="sidebar-sub-menu">

                    <?php
                    vnnSidebarItem(
                        'users',
                        $adminBase . 'users/',
                        'fa-users-gear',
                        'Cấp Quyền & Tài Khoản'
                    );
                    ?>

                </ul>

            </li>

        <?php endif; ?>

    </ul>

</aside>

<script>
function vnnToggleGroup(button) {
    const group = button.closest('.vnn-group');

    if (!group) {
        return;
    }

    const isOpen = group.classList.toggle('open');

    button.setAttribute(
        'aria-expanded',
        isOpen ? 'true' : 'false'
    );
}
</script>
