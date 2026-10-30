
<?php
$current_page = $current_page ?? '';
$role = getRole();
$base = rtrim(BASE_URL, '/') . '/';

if (!function_exists('vnnSideH')) {
    function vnnSideH($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vnnSideMenuLink')) {
    function vnnSideMenuLink($url, $icon, $label, $active = false, $count = 0) {
        echo '<a class="vnn-nav-link'.($active?' active':'').'" href="'.vnnSideH($url).'">';
        echo '<span class="vnn-nav-label"><i class="fa-solid '.vnnSideH($icon).'"></i>';
        echo '<span>'.vnnSideH($label).'</span></span>';
        if ($count > 0) {
            echo '<b class="vnn-nav-count">'.($count>99?'99+':(int)$count).'</b>';
        }
        echo '</a>';
    }
}

if (!function_exists('vnnSidePending')) {
    function vnnSidePending($pdo, $table) {
        if (!in_array($table, ['orders','tour_bookings'], true)) return 0;
        try {
            return (int)$pdo->query(
                "SELECT COUNT(*) FROM `$table` WHERE status='Chờ xác nhận'"
            )->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

$isAdminHistory = in_array(
    $current_page,
    ['system-login-history', 'system-edit-history'],
    true
);

if (!function_exists('vnnSideUnanswered')) {
    function vnnSideUnanswered(PDO $pdo): int {
        try {
            // Count using the same latest support-message definition as chat-api.php.
            // A customer needs a reply only when their latest staff/customer
            // conversation message was sent by that customer.
            return (int) $pdo->query("SELECT COUNT(*) FROM users u
                WHERE u.role = 'customer'
                  AND (
                    SELECT m.sender_id
                    FROM messages m
                    JOIN users sender ON sender.id = m.sender_id
                    JOIN users receiver ON receiver.id = m.receiver_id
                    WHERE (m.sender_id = u.id AND receiver.role IN ('manager','staff','admin'))
                       OR (m.receiver_id = u.id AND sender.role IN ('manager','staff','admin'))
                    ORDER BY m.id DESC LIMIT 1
                  ) = u.id")->fetchColumn();
        } catch (Throwable $e) {
            error_log('Chat unanswered badge: ' . $e->getMessage());
            return 0;
        }
    }
}

$pendingChat = 0;
$pendingTours = 0;
$pendingOrders = 0;

if (in_array($role, ['admin', 'manager', 'staff'], true)) {
    $pendingChat = vnnSideUnanswered($pdo);
    $pendingTours = vnnSidePending($pdo, 'tour_bookings');
    $pendingOrders = vnnSidePending($pdo, 'orders');
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
    background: #101a2d;
    color: #e2e8f0;
    z-index: 100;
    box-sizing: border-box;
}
.admin-main { flex: 1 1 auto; min-width: 0; }
.vnn-nav-brand {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 23px 16px;
    background: #0f172a;
}
.vnn-nav-brand > i { font-size: 35px; color: #65a30d; }
.vnn-nav-brand b {
    display: block;
    font-size: 26px;
    letter-spacing: 3px;
    line-height: 1;
    color: #f97316;
}
.vnn-nav-brand small {
    display: block;
    margin-top: 6px;
    font-size: 11px;
    letter-spacing: 3px;
    color: white;
    font-weight: 800;
}
.vnn-nav-website {
    display: block;
    margin: 14px;
    padding: 13px 10px;
    border: 1px solid #334155;
    border-radius: 10px;
    text-align: center;
    color: #38bdf8;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
}
.vnn-nav-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 49px;
    padding: 12px 16px;
    gap: 8px;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 13px;
    box-sizing: border-box;
}
.vnn-nav-link:hover,
.vnn-nav-link.active {
    background: #334155;
    color: #38bdf8;
}
.vnn-nav-link.active {
    border-left: 3px solid #f97316;
    padding-left: 13px;
}
.vnn-nav-label {
    display: inline-flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}
.vnn-nav-label > i {
    flex-shrink: 0;
    width: 19px;
    text-align: center;
}
.vnn-nav-count {
    padding: 4px 8px;
    border-radius: 20px;
    background: #ef4444;
    color: white;
    font-size: 11px;
}
.vnn-nav-heading {
    padding: 15px;
    margin-top: 8px;
    background: #26354d;
    color: white;
    font-size: 12px;
    font-weight: 900;
}
.vnn-nav-toggle {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 16px;
    border: none;
    background: transparent;
    color: #cbd5e1;
    font: inherit;
    font-size: 13px;
    cursor: pointer;
    text-align: left;
}
.vnn-nav-toggle span {
    display: inline-flex;
    align-items: center;
    gap: 11px;
}
.vnn-nav-toggle:hover,
.vnn-nav-toggle.active {
    background: #334155;
    color: #38bdf8;
}
.vnn-nav-toggle[aria-expanded="true"] .vnn-chevron {
    transform: rotate(180deg);
}
.vnn-nav-sub { background: #0b1628; }
.vnn-nav-sub[hidden] { display: none !important; }
.vnn-nav-sub .vnn-nav-link { padding-left: 32px; }
.vnn-nav-sub .vnn-nav-link.active { padding-left: 29px; }
.vnn-nav-note {
    margin: 22px 14px;
    padding: 13px;
    border: 1px solid #334155;
    border-radius: 10px;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.8;
}
@media (max-width: 700px) {
    .admin-sidebar {
        width: 205px !important;
        min-width: 205px !important;
        flex-basis: 205px !important;
    }
}
</style>

<aside class="admin-sidebar">

    <div class="vnn-nav-brand">
        <i class="fa-solid fa-mountain-sun"></i>
        <div>
            <b>VNN</b>
            <small>TRAVEL</small>
        </div>
    </div>

    <a class="vnn-nav-website"
       href="<?= vnnSideH($base) ?>index.php"
       target="_blank" rel="noopener">
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        Xem Website Trực Tiếp
    </a>

    <?php if ($role === 'admin'): ?>

        <?php
        vnnSideMenuLink(
            $base.'admin/system-dashboard.php',
            'fa-gauge',
            'Dashboard Bàn Làm Việc',
            $current_page === 'system-dashboard'
        );
        ?>

        <?php
        vnnSideMenuLink(
            $base.'admin/system.php?tab=users',
            'fa-users-gear',
            'Quản lý & Cấp quyền tài khoản',
            $current_page === 'system-users'
        );
        ?>

        <button
            id="vnnHistoryMenuButton"
            class="vnn-nav-toggle <?= $isAdminHistory?'active':'' ?>"
            type="button"
            aria-expanded="<?= $isAdminHistory?'true':'false' ?>"
            aria-controls="vnnHistorySubmenu"
        >
            <span>
                <i class="fa-solid fa-clock-rotate-left"></i>
                Lịch sử hoạt động
            </span>
            <i class="fa-solid fa-chevron-down vnn-chevron"></i>
        </button>

        <div
            id="vnnHistorySubmenu"
            class="vnn-nav-sub"
            <?= $isAdminHistory?'':'hidden' ?>
        >
            <?php
            vnnSideMenuLink(
                $base.'admin/system.php?tab=logins',
                'fa-angle-right',
                'Lịch sử đăng nhập',
                $current_page === 'system-login-history'
            );

            vnnSideMenuLink(
                $base.'admin/system.php?tab=edits',
                'fa-angle-right',
                'Lịch sử chỉnh sửa',
                $current_page === 'system-edit-history'
            );
            ?>
        </div>

        <a class="vnn-nav-link" href="<?= vnnSideH($base) ?>admin/logout.php" style="color:#fca5a5">
            <span class="vnn-nav-label"><i class="fa-solid fa-right-from-bracket"></i><span>Đăng xuất</span></span>
        </a>

        <div class="vnn-nav-note">
            <i class="fa-solid fa-shield-halved"></i>
            Khu vực quản lý tài khoản và theo dõi lịch sử hệ thống.
        </div>

    <?php else: ?>

        <?php
        vnnSideMenuLink(
            $base.'admin/index.php',
            'fa-gauge',
            'Dashboard Bàn Làm Việc',
            $current_page === 'dashboard'
        );
        ?>

        <div class="vnn-nav-heading">XỬ LÝ KHÁCH HÀNG</div>

        <?php
        vnnSideMenuLink(
            $base.'admin/chat/',
            'fa-comments',
            'Chat Trực Tuyến',
            $current_page === 'chat',
            $pendingChat
        );

        vnnSideMenuLink(
            $base.'admin/bookings/',
            'fa-ticket',
            'Duyệt Tour',
            $current_page === 'bookings',
            $pendingTours
        );

        vnnSideMenuLink(
            $base.'admin/orders/',
            'fa-box',
            'Duyệt Đơn Hàng',
            $current_page === 'orders',
            $pendingOrders
        );

        vnnSideMenuLink(
            $base.'admin/reviews/',
            'fa-star',
            'Đánh Giá Từ Khách',
            $current_page === 'reviews'
        );

        vnnSideMenuLink(
            $base.'admin/contacts/',
            'fa-envelope',
            'Tin Nhắn Liên Hệ',
            $current_page === 'contacts'
        );
        ?>

        <?php if ($role === 'manager'): ?>

            <div class="vnn-nav-heading">NỘI DUNG & KHO</div>

            <?php
            vnnSideMenuLink(
                $base.'admin/tours/',
                'fa-person-hiking',
                'Quản Lý Tour',
                $current_page === 'tours'
            );

            vnnSideMenuLink(
                $base.'admin/leaders/',
                'fa-person-hiking',
                'Đội Ngũ Leader',
                $current_page === 'leaders'
            );

            vnnSideMenuLink(
                $base.'admin/products/',
                'fa-warehouse',
                'Dụng Cụ, Kho & Danh Mục',
                $current_page === 'products'
            );

            vnnSideMenuLink(
                $base.'admin/locations/',
                'fa-map-location-dot',
                'Điểm Đến Trekking',
                $current_page === 'locations'
            );

            vnnSideMenuLink(
                $base.'admin/posts/',
                'fa-newspaper',
                'Bài Viết & Cẩm Nang',
                $current_page === 'posts'
            );

            vnnSideMenuLink(
                $base.'admin/revenue.php',
                'fa-chart-line',
                'Doanh Thu & Báo Cáo',
                $current_page === 'revenue'
            );
            ?>

        <?php endif; ?>

    <?php endif; ?>

</aside>

<script>
(function() {
    const button = document.getElementById('vnnHistoryMenuButton');
    const submenu = document.getElementById('vnnHistorySubmenu');

    if (!button || !submenu) return;

    button.addEventListener('click', function() {
        submenu.hidden = !submenu.hidden;
        button.setAttribute(
            'aria-expanded',
            String(!submenu.hidden)
        );
    });
})();
</script>
