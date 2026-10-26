<?php
$current_page = $current_page ?? '';

$adminBase = rtrim(BASE_URL, '/') . '/admin/';
$siteBase = rtrim(BASE_URL, '/') . '/';

if (!function_exists('vnnAdminEscape')) {
    function vnnAdminEscape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vnnAdminCount')) {
    function vnnAdminCount(PDO $pdo, string $sql): int {
        try {
            return (int)$pdo->query($sql)->fetchColumn();
        } catch (Throwable $e) {
            error_log('Sidebar count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('vnnAdminLink')) {
    function vnnAdminLink($id, $url, $icon, $label, $count = 0) {
        global $current_page;

        $active = $current_page === $id ? 'active' : '';

        echo '<li class="' . $active . '">';
        echo '<a href="' . vnnAdminEscape($url) . '">';
        echo '<span class="vnn-item-left">';
        echo '<i class="fa-solid ' . vnnAdminEscape($icon) . '"></i>';
        echo '<span>' . vnnAdminEscape($label) . '</span>';
        echo '</span>';

        if ($count > 0) {
            echo '<span class="vnn-menu-count">';
            echo $count > 99 ? '99+' : (int)$count;
            echo '</span>';
        }

        echo '</a></li>';
    }
}

$role = getRole();

$pendingTours = 0;
$pendingOrders = 0;

if ($role !== 'admin') {
    $pendingTours = vnnAdminCount(
        $pdo,
        "SELECT COUNT(*) FROM tour_bookings
         WHERE status = 'Chờ xác nhận'"
    );

    $pendingOrders = vnnAdminCount(
        $pdo,
        "SELECT COUNT(*) FROM orders
         WHERE status = 'Chờ xác nhận'"
    );
}
?>

<style>
.admin-sidebar {
    width: 250px !important;
    min-width: 250px !important;
    flex: 0 0 250px !important;
    height: 100vh;
    position: sticky;
    top: 0;
    overflow-y: auto;
    overflow-x: hidden;
    background: #111b2c;
    color: #e2e8f0;
    z-index: 100;
}

.admin-main {
    flex: 1 1 auto;
    min-width: 0;
}

.vnn-admin-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 22px 16px;
    background: #0f172a;
}

.vnn-admin-brand i {
    font-size: 34px;
    color: #65a30d;
}

.vnn-brand-name {
    color: #f97316;
    font-size: 25px;
    font-weight: 900;
    letter-spacing: 3px;
}

.vnn-brand-name::first-letter {
    color: #38bdf8;
}

.vnn-brand-caption {
    margin-top: 2px;
    color: white;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 3px;
}

.vnn-admin-website {
    display: block;
    margin: 15px;
    padding: 12px;
    text-align: center;
    color: #38bdf8;
    border: 1px solid #334155;
    border-radius: 9px;
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
}

.vnn-admin-menu,
.vnn-admin-menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.vnn-admin-menu li a {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 14px 15px;
    font-size: 13px;
    color: #cbd5e1;
    text-decoration: none;
    transition: background .2s;
}

.vnn-admin-menu li a:hover,
.vnn-admin-menu li.active a {
    background: #334155;
    color: #38bdf8;
}

.vnn-admin-menu li.active a {
    border-left: 4px solid #f97316;
}

.vnn-item-left {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.vnn-item-left i {
    width: 19px;
    text-align: center;
    flex-shrink: 0;
}

.vnn-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    color: white;
    font-size: 12px;
    font-weight: 900;
    background: #253247;
    margin-top: 7px;
}

.vnn-menu-count {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-width: 23px;
    height: 23px;
    padding: 0 6px;
    border-radius: 20px;
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 900;
}

.vnn-sidebar-note {
    margin: 22px 15px;
    padding: 13px;
    border: 1px solid #334155;
    border-radius: 9px;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.7;
}

@media (max-width:700px) {
    .admin-sidebar {
        width: 200px !important;
        min-width: 200px !important;
        flex-basis: 200px !important;
    }
}
</style>

<aside class="admin-sidebar">

    <div class="vnn-admin-brand">
        <i class="fa-solid fa-mountain-sun"></i>

        <div>
            <div class="vnn-brand-name">VNN</div>
            <div class="vnn-brand-caption">TRAVEL</div>
        </div>
    </div>

    <a
        href="<?= vnnAdminEscape($siteBase) ?>index.php"
        class="vnn-admin-website"
        target="_blank"
        rel="noopener"
    >
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        Xem Website Trực Tiếp
    </a>

    <ul class="vnn-admin-menu">

        <?php if ($role === 'admin'): ?>

            <?php
            vnnAdminLink(
                'system-overview',
                $adminBase . 'system.php?tab=overview',
                'fa-chart-pie',
                'Tổng Quan Hệ Thống'
            );

            vnnAdminLink(
                'system-users',
                $adminBase . 'system.php?tab=users',
                'fa-users-gear',
                'Quản Lý Tài Khoản'
            );

            vnnAdminLink(
                'system-content',
                $adminBase . 'system.php?tab=content',
                'fa-panels-top-left',
                'Nội Dung Website'
            );

            vnnAdminLink(
                'system-history',
                $adminBase . 'system.php?tab=history',
                'fa-clock-rotate-left',
                'Lịch Sử Hoạt Động'
            );
            ?>

        <?php else: ?>

            <?php
            vnnAdminLink(
                'dashboard',
                $adminBase . 'index.php',
                'fa-gauge',
                'Dashboard Bàn Làm Việc'
            );
            ?>

            <div class="vnn-section-title">
                <i class="fa-solid fa-headset"></i>
                XỬ LÝ KHÁCH HÀNG
            </div>

            <?php
            vnnAdminLink(
                'chat',
                $adminBase . 'chat/',
                'fa-comments',
                'Chat Trực Tuyến'
            );

            vnnAdminLink(
                'bookings',
                $adminBase . 'bookings/',
                'fa-ticket',
                'Duyệt Tour',
                $pendingTours
            );

            vnnAdminLink(
                'orders',
                $adminBase . 'orders/',
                'fa-box',
                'Duyệt Đơn Hàng',
                $pendingOrders
            );

            vnnAdminLink(
                'reviews',
                $adminBase . 'reviews/',
                'fa-star',
                'Đánh Giá Từ Khách'
            );

            vnnAdminLink(
                'contacts',
                $adminBase . 'contacts/',
                'fa-envelope',
                'Tin Nhắn Liên Hệ'
            );
            ?>

            <?php if ($role === 'manager'): ?>

                <div class="vnn-section-title">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    NỘI DUNG & KHO
                </div>

                <?php
                vnnAdminLink(
                    'tours',
                    $adminBase . 'tours/',
                    'fa-person-hiking',
                    'Quản Lý Tour'
                );

                vnnAdminLink(
                    'leaders',
                    $adminBase . 'leaders/',
                    'fa-person-hiking',
                    'Đội Ngũ Leader'
                );

                vnnAdminLink(
                    'products',
                    $adminBase . 'products/',
                    'fa-warehouse',
                    'Dụng Cụ, Kho & Danh Mục'
                );

                vnnAdminLink(
                    'locations',
                    $adminBase . 'locations/',
                    'fa-map-location-dot',
                    'Điểm Đến Trekking'
                );

                vnnAdminLink(
                    'posts',
                    $adminBase . 'posts/',
                    'fa-newspaper',
                    'Bài Viết & Cẩm Nang'
                );

                vnnAdminLink(
                    'revenue',
                    $adminBase . 'revenue.php',
                    'fa-chart-line',
                    'Doanh Thu & Báo Cáo'
                );
                ?>

            <?php endif; ?>

        <?php endif; ?>

    </ul>

    <?php if ($role === 'admin'): ?>

        <div class="vnn-sidebar-note">
            <i class="fa-solid fa-shield-halved"></i>
            Khu vực quản trị hệ thống và tài khoản.
        </div>

    <?php endif; ?>

</aside>
