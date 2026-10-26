<?php
$current_page = $current_page ?? '';
$role = getRole();
$root = rtrim(BASE_URL, '/') . '/';

if (!function_exists('vnnSideEsc')) {
    function vnnSideEsc($x) {
        return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vnnSideLink')) {
    function vnnSideLink($href, $icon, $label, $active = false, $count = 0) {
        echo '<a class="vnn-side-link'.($active?' active':'').'" href="'.vnnSideEsc($href).'">';
        echo '<span><i class="fa-solid '.vnnSideEsc($icon).'"></i> '.vnnSideEsc($label).'</span>';
        if ($count > 0) {
            echo '<b class="vnn-side-count">'.(int)$count.'</b>';
        }
        echo '</a>';
    }
}

if (!function_exists('vnnSideCount')) {
    function vnnSideCount($pdo, $table) {
        try {
            $allowed = ['tour_bookings', 'orders'];
            if (!in_array($table, $allowed, true)) return 0;
            return (int)$pdo->query(
                "SELECT COUNT(*) FROM `$table` WHERE status='Chờ xác nhận'"
            )->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

$category = (string)($_GET['category'] ?? 'accounts');

if (!in_array(
    $category,
    ['accounts','content','transactions','security'],
    true
)) {
    $category = 'accounts';
}

$historyOpen = $role === 'admin'
    && $current_page === 'system-history';
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
    background: #111b2d;
    color: #e2e8f0;
    z-index: 100;
}

.admin-main {
    flex: 1 1 auto;
    min-width: 0;
}

.vnn-side-brand {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 20px 16px;
    background: #0f172a;
}

.vnn-side-brand i {
    font-size: 34px;
    color: #65a30d;
}

.vnn-side-brand strong {
    font-size: 25px;
    letter-spacing: 3px;
    color: #fb923c;
}

.vnn-side-brand small {
    display: block;
    letter-spacing: 3px;
    color: #fff;
    font-weight: 800;
}

.vnn-side-site {
    display: block;
    margin: 14px;
    padding: 12px;
    border: 1px solid #334155;
    border-radius: 10px;
    color: #38bdf8;
    text-align: center;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
}

.vnn-side-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 14px 15px;
    text-decoration: none;
    color: #cbd5e1;
    font-size: 13px;
}

.vnn-side-link > span {
    display: flex;
    align-items: center;
    gap: 10px;
}

.vnn-side-link i {
    width: 19px;
    text-align: center;
}

.vnn-side-link:hover,
.vnn-side-link.active {
    background: #334155;
    color: #38bdf8;
}

.vnn-side-link.active {
    border-left: 3px solid #f97316;
}

.vnn-side-title {
    padding: 15px;
    background: #253247;
    color: white;
    font-size: 12px;
    font-weight: 900;
}

.vnn-side-count {
    background: #ef4444;
    color: white;
    border-radius: 20px;
    padding: 4px 8px;
    font-size: 11px;
}

.vnn-side-trigger {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 0;
    background: none;
    color: #cbd5e1;
    padding: 14px 15px;
    cursor: pointer;
    font: inherit;
    font-size: 13px;
    text-align: left;
}

.vnn-side-trigger span {
    display: flex;
    align-items: center;
    gap: 10px;
}

.vnn-side-trigger:hover,
.vnn-side-trigger.active {
    background: #334155;
    color: #38bdf8;
}

.vnn-side-submenu {
    background: #0c1627;
}

.vnn-side-submenu[hidden] {
    display: none !important;
}

.vnn-side-submenu .vnn-side-link {
    padding-left: 28px;
    font-size: 12px;
}

@media(max-width:700px) {
    .admin-sidebar {
        width: 200px !important;
        min-width: 200px !important;
        flex-basis: 200px !important;
    }
}
</style>

<aside class="admin-sidebar">

    <div class="vnn-side-brand">
        <i class="fa-solid fa-mountain-sun"></i>
        <div>
            <strong>VNN</strong>
            <small>TRAVEL</small>
        </div>
    </div>

    <a
        class="vnn-side-site"
        href="<?= vnnSideEsc($root) ?>index.php"
        target="_blank"
        rel="noopener"
    >
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        Xem Website Trực Tiếp
    </a>

    <?php if ($role === 'admin'): ?>

        <?php
        vnnSideLink(
            $root.'admin/system.php?tab=users',
            'fa-users-gear',
            'Quản lý & Cấp quyền tài khoản',
            $current_page === 'system-users'
        );
        ?>

        <button
            type="button"
            class="vnn-side-trigger <?= $historyOpen?'active':'' ?>"
            id="vnnHistoryToggle"
            aria-expanded="<?= $historyOpen?'true':'false' ?>"
        >
            <span>
                <i class="fa-solid fa-clock-rotate-left"></i>
                Lịch sử hoạt động
            </span>

            <i class="fa-solid fa-chevron-down"></i>
        </button>

        <div
            class="vnn-side-submenu"
            id="vnnHistoryItems"
            <?= $historyOpen?'':'hidden' ?>
        >

            <?php
            foreach ([
                'accounts' => '1. Tài khoản',
                'content' => '2. Nội dung',
                'transactions' => '3. Giao dịch',
                'security' => '4. Bảo mật'
            ] as $id => $label) {

                vnnSideLink(
                    $root.'admin/system.php?tab=history&category='.$id,
                    'fa-angle-right',
                    $label,
                    $historyOpen && $category === $id
                );
            }
            ?>

        </div>

    <?php else: ?>

        <?php
        vnnSideLink(
            $root.'admin/index.php',
            'fa-gauge',
            'Dashboard Bàn Làm Việc',
            $current_page === 'dashboard'
        );
        ?>

        <div class="vnn-side-title">
            XỬ LÝ KHÁCH HÀNG
        </div>

        <?php
        vnnSideLink(
            $root.'admin/chat/',
            'fa-comments',
            'Chat Trực Tuyến',
            $current_page === 'chat'
        );

        vnnSideLink(
            $root.'admin/bookings/',
            'fa-ticket',
            'Duyệt Tour',
            $current_page === 'bookings',
            vnnSideCount($pdo, 'tour_bookings')
        );

        vnnSideLink(
            $root.'admin/orders/',
            'fa-box',
            'Duyệt Đơn Hàng',
            $current_page === 'orders',
            vnnSideCount($pdo, 'orders')
        );

        vnnSideLink(
            $root.'admin/reviews/',
            'fa-star',
            'Đánh Giá Từ Khách',
            $current_page === 'reviews'
        );

        vnnSideLink(
            $root.'admin/contacts/',
            'fa-envelope',
            'Tin Nhắn Liên Hệ',
            $current_page === 'contacts'
        );
        ?>

        <?php if ($role === 'manager'): ?>

            <div class="vnn-side-title">
                NỘI DUNG & KHO
            </div>

            <?php
            vnnSideLink(
                $root.'admin/tours/',
                'fa-person-hiking',
                'Quản Lý Tour',
                $current_page === 'tours'
            );

            vnnSideLink(
                $root.'admin/leaders/',
                'fa-person-hiking',
                'Đội Ngũ Leader',
                $current_page === 'leaders'
            );

            vnnSideLink(
                $root.'admin/products/',
                'fa-warehouse',
                'Dụng Cụ, Kho & Danh Mục',
                $current_page === 'products'
            );

            vnnSideLink(
                $root.'admin/locations/',
                'fa-map-location-dot',
                'Điểm Đến Trekking',
                $current_page === 'locations'
            );

            vnnSideLink(
                $root.'admin/posts/',
                'fa-newspaper',
                'Bài Viết & Cẩm Nang',
                $current_page === 'posts'
            );

            vnnSideLink(
                $root.'admin/revenue.php',
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
    const trigger = document.getElementById('vnnHistoryToggle');
    const menu = document.getElementById('vnnHistoryItems');

    if (!trigger || !menu) return;

    trigger.addEventListener('click', function() {
        menu.hidden = !menu.hidden;

        trigger.setAttribute(
            'aria-expanded',
            String(!menu.hidden)
        );
    });
})();
</script>
