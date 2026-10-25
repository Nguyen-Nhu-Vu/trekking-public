<?php
// VNN Travel - Sidebar chung cho trang quan ly

$current_page = $current_page ?? '';

function vnn_sidebar_count($pdo, $sql) {
    try {
        return max(0, (int)$pdo->query($sql)->fetchColumn());
    } catch (Throwable $e) {
        error_log('Sidebar count: ' . $e->getMessage());
        return 0;
    }
}

$pending_bookings = vnn_sidebar_count(
    $pdo,
    "SELECT COUNT(*) FROM tour_bookings
     WHERE status = 'Chờ xác nhận'"
);

$pending_orders = vnn_sidebar_count(
    $pdo,
    "SELECT COUNT(*) FROM orders
     WHERE status = 'Chờ xác nhận'"
);

// Danh gia dang an: can kiem tra truoc khi hien.
$pending_reviews = vnn_sidebar_count(
    $pdo,
    "SELECT COUNT(*) FROM reviews WHERE status = 0"
);

// Chua co cot da doc: dem cac tin con trong hop thu.
$contact_count = vnn_sidebar_count(
    $pdo,
    "SELECT COUNT(*) FROM contacts"
);

function vnn_sidebar_badge($count) {
    $count = (int)$count;

    if ($count <= 0) return;

    echo '<span class="badge-notify-pill" ' .
         'aria-label="' . $count . ' thông báo">' .
         $count .
         '</span>';
}

function vnn_sidebar_link($page, $url, $icon, $title, $count=0) {
    global $current_page;

    $active = $current_page === $page ? 'active' : '';
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeIcon = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');

    echo '<li class="' . $active . '">';
    echo '<a href="' . $safeUrl . '">';
    echo '<span class="menu-label">';
    echo '<i class="fa-solid ' . $safeIcon . '"></i>';
    echo $safeTitle;
    echo '</span>';

    vnn_sidebar_badge($count);

    echo '</a></li>';
}

function vnn_sidebar_group_title($icon, $title, $color) {
    echo '<div class="sidebar-group-title" ' .
         'onclick="toggleSidebarGroup(this)">';
    echo '<span><i class="fa-solid ' . $icon . '" ' .
         'style="color:' . $color . ';margin-right:7px"></i>';
    echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '</span>';
    echo '<i class="fa-solid fa-chevron-down chevron"></i>';
    echo '</div>';
}

$adminBase = rtrim(BASE_URL, '/') . '/admin/';
$siteBase = rtrim(BASE_URL, '/') . '/';

$customerPages = [
    'chat','bookings','orders','reviews','contacts'
];

$contentPages = [
    'tours','products','categories','locations','posts'
];

$reportPages = [
    'revenue_tours','revenue_shop'
];

$customerOpen = in_array($current_page, $customerPages, true);
$contentOpen = in_array($current_page, $contentPages, true);
$reportOpen = in_array($current_page, $reportPages, true);
?>

<aside class="admin-sidebar" id="vnnAdminSidebar">

    <div class="admin-brand"
         style="display:flex;align-items:center;
                gap:12px;height:74px;min-height:74px;
                background:#0f172a;padding:15px">

        <svg viewBox="0 0 180 115"
             xmlns="http://www.w3.org/2000/svg"
             style="width:58px;flex-shrink:0">
            <polygon points="50,42 22,86 82,86"
                     fill="#58a82d"/>
            <polygon points="50,42 58,56 44,56"
                     fill="#ffffff"/>
            <polygon points="95,8 42,94 150,94"
                     fill="#4d9e26"/>
            <polygon points="95,8 116,40 105,36 95,48 85,36 76,42"
                     fill="#ffffff"/>
            <polygon points="135,38 106,86 170,86"
                     fill="#3b7a1e"/>
            <polygon points="135,38 144,52 128,52"
                     fill="#ffffff"/>
            <path d="M6 90 C48 58 132 58 174 90
                     C140 84 40 84 6 90Z"
                  fill="#6db832"/>
            <path d="M16 88 C52 74 128 74 164 88
                     C138 102 42 102 16 88Z"
                  fill="#1b5e20"/>
        </svg>

        <div>
            <div style="font-weight:900;
                        font-size:22px;
                        letter-spacing:1px;
                        line-height:1.1">
                <span style="color:#38bdf8">V</span>
                <span style="color:#f97316">N</span>
                <span style="color:#f59e0b">N</span>
            </div>
            <div style="font-size:11px;
                        color:white;
                        font-weight:800;
                        letter-spacing:3px">
                TRAVEL
            </div>
        </div>

    </div>

    <a href="<?= htmlspecialchars($siteBase) ?>index.php"
       target="_blank"
       rel="noopener"
       class="btn-sidebar-viewsite">
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        Xem Website Trực Tiếp
    </a>

    <ul class="admin-menu">

        <?php vnn_sidebar_link(
            'dashboard',
            $adminBase . 'index.php',
            'fa-gauge',
            'Dashboard Bàn Làm Việc'
        ); ?>

        <?php if (isStaff() || isManager() || isAdmin()): ?>

        <li class="sidebar-group <?= $customerOpen ? 'open' : 'open' ?>">

            <?php vnn_sidebar_group_title(
                'fa-bell-concierge',
                'XỬ LÝ KHÁCH HÀNG',
                '#38bdf8'
            ); ?>

            <ul class="sidebar-sub-menu">

                <?php vnn_sidebar_link(
                    'chat',
                    $adminBase . 'chat/',
                    'fa-headset',
                    'Chat Trực Tuyến'
                ); ?>

                <?php vnn_sidebar_link(
                    'bookings',
                    $adminBase . 'bookings/',
                    'fa-ticket',
                    'Duyệt Tour',
                    $pending_bookings
                ); ?>

                <?php vnn_sidebar_link(
                    'orders',
                    $adminBase . 'orders/',
                    'fa-box-open',
                    'Duyệt Đơn Hàng',
                    $pending_orders
                ); ?>

                <?php vnn_sidebar_link(
                    'reviews',
                    $adminBase . 'reviews/',
                    'fa-star',
                    'Đánh Giá Từ Khách',
                    $pending_reviews
                ); ?>

                <?php vnn_sidebar_link(
                    'contacts',
                    $adminBase . 'contacts/',
                    'fa-envelope',
                    'Tin Nhắn Liên Hệ',
                    $contact_count
                ); ?>

            </ul>
        </li>

        <?php endif; ?>

        <?php if (isManager()): ?>

        <li class="sidebar-group <?= $contentOpen ? 'open' : 'open' ?>">

            <?php vnn_sidebar_group_title(
                'fa-boxes-stacked',
                'NỘI DUNG & KHO',
                '#f97316'
            ); ?>

            <ul class="sidebar-sub-menu">

                <?php vnn_sidebar_link(
                    'tours',
                    $adminBase . 'tours/',
                    'fa-person-hiking',
                    'Quản Lý Tour'
                ); ?>

                <?php vnn_sidebar_link(
                    'products',
                    $adminBase . 'products/',
                    'fa-campground',
                    'Dụng Cụ & Tồn Kho'
                ); ?>

                <?php vnn_sidebar_link(
                    'categories',
                    $adminBase . 'categories/',
                    'fa-layer-group',
                    'Danh Mục Hàng'
                ); ?>

                <?php vnn_sidebar_link(
                    'locations',
                    $adminBase . 'locations/',
                    'fa-map-location-dot',
                    'Điểm Đến Trekking'
                ); ?>

                <?php vnn_sidebar_link(
                    'posts',
                    $adminBase . 'posts/',
                    'fa-newspaper',
                    'Bài Viết & Cẩm Nang'
                ); ?>

            </ul>
        </li>

        <li class="sidebar-group <?= $reportOpen ? 'open' : 'open' ?>">

            <?php vnn_sidebar_group_title(
                'fa-chart-line',
                'DOANH THU & BÁO CÁO',
                '#10b981'
            ); ?>

            <ul class="sidebar-sub-menu">

                <?php vnn_sidebar_link(
                    'revenue_tours',
                    $adminBase . 'revenue-tours.php',
                    'fa-chart-column',
                    'Doanh Thu Tour'
                ); ?>

                <?php vnn_sidebar_link(
                    'revenue_shop',
                    $adminBase . 'revenue-shop.php',
                    'fa-bag-shopping',
                    'Doanh Thu Bán Hàng'
                ); ?>

            </ul>
        </li>

        <?php endif; ?>

        <?php if (isAdmin()): ?>

        <li class="sidebar-group open">

            <?php vnn_sidebar_group_title(
                'fa-shield-halved',
                'QUẢN TRỊ HỆ THỐNG',
                '#ef4444'
            ); ?>

            <ul class="sidebar-sub-menu">

                <?php vnn_sidebar_link(
                    'users',
                    $adminBase . 'users/',
                    'fa-user-shield',
                    'Cấp Quyền & Tài Khoản'
                ); ?>

            </ul>

        </li>

        <?php endif; ?>

    </ul>

</aside>

<script>
function toggleSidebarGroup(element) {
    const group = element.closest('.sidebar-group');
    if (!group) return;

    group.classList.toggle('open');
}
</script>
