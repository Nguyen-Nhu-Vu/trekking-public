
<?php
// ==============================================
// VNN TRAVEL - ADMIN SIDEBAR
// Phan quyen menu Admin / Manager / Staff
// ==============================================

// File nay duoc include tu cac trang trong admin.
// Khong require database bang duong dan tuong doi sai.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Tai dependency chi khi chua co.
// Duong dan tu admin/includes ve thu muc goc la ../../

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/database.php';
}

if (!function_exists('getRole')) {
    require_once __DIR__ . '/../../includes/functions.php';
}

$current_page = $current_page ?? '';

$role = getRole();
$root = rtrim(BASE_URL, '/') . '/';

// ==============================================
// HAM HO TRO
// ==============================================

if (!function_exists('vnnSideEsc')) {
    function vnnSideEsc($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('vnnSideLink')) {
    function vnnSideLink(
        $href,
        $icon,
        $label,
        $active = false,
        $count = 0
    ) {
        $activeClass = $active ? ' active' : '';

        echo '<a class="vnn-side-link' .
            $activeClass .
            '" href="' .
            vnnSideEsc($href) .
            '">';

        echo '<span class="vnn-side-label">';

        echo '<i class="fa-solid ' .
            vnnSideEsc($icon) .
            '"></i>';

        echo '<span>' .
            vnnSideEsc($label) .
            '</span>';

        echo '</span>';

        if ($count > 0) {
            echo '<b class="vnn-side-count">';
            echo $count > 99 ? '99+' : (int)$count;
            echo '</b>';
        }

        echo '</a>';
    }
}

if (!function_exists('vnnSideCount')) {
    function vnnSideCount($pdo, $table) {
        try {
            $allowed = [
                'tour_bookings',
                'orders'
            ];

            if (!in_array($table, $allowed, true)) {
                return 0;
            }

            if (!isset($pdo) || !($pdo instanceof PDO)) {
                return 0;
            }

            $sql = "
                SELECT COUNT(*)
                FROM `$table`
                WHERE status = 'Chờ xác nhận'
            ";

            return (int)$pdo->query($sql)->fetchColumn();

        } catch (Throwable $e) {
            error_log(
                'Sidebar count: ' . $e->getMessage()
            );

            return 0;
        }
    }
}

// ==============================================
// TRANG THAI MENU LICH SU
// ==============================================

$category = (string)(
    $_GET['category'] ?? 'accounts'
);

$allowedCategories = [
    'accounts',
    'content',
    'transactions',
    'security'
];

if (!in_array($category, $allowedCategories, true)) {
    $category = 'accounts';
}

$historyOpen =
    $role === 'admin' &&
    $current_page === 'system-history';

$pendingTours = 0;
$pendingOrders = 0;

// Admin khong can truy van so don cho duyet.

if ($role !== 'admin') {
    $pendingTours = vnnSideCount(
        $pdo ?? null,
        'tour_bookings'
    );

    $pendingOrders = vnnSideCount(
        $pdo ?? null,
        'orders'
    );
}
?>

<style>
/* ==============================================
   SIDEBAR CHUNG
   ============================================== */

.admin-sidebar {
    width: 250px !important;
    min-width: 250px !important;
    flex: 0 0 250px !important;

    height: 100vh;
    position: sticky;
    top: 0;

    overflow-y: auto;
    overflow-x: hidden;

    background: #111b2d;
    color: #e2e8f0;

    z-index: 100;

    box-sizing: border-box;
}

.admin-main {
    flex: 1 1 auto;
    min-width: 0;
}

/* ==============================================
   LOGO
   ============================================== */

.vnn-side-brand {
    display: flex;
    align-items: center;
    gap: 12px;

    padding: 20px 16px;

    background: #0f172a;
}

.vnn-side-brand i {
    font-size: 34px;
    color: #65a30d;
}

.vnn-side-brand strong {
    display: block;

    color: #fb923c;

    font-size: 25px;
    font-weight: 900;
    letter-spacing: 3px;
    line-height: 1;
}

.vnn-side-brand small {
    display: block;

    margin-top: 5px;

    color: white;

    font-size: 11px;
    font-weight: 800;
    letter-spacing: 3px;
}

/* ==============================================
   NUT XEM WEBSITE
   ============================================== */

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

    transition: background 0.2s;
}

.vnn-side-site:hover {
    background: #1e293b;
    color: #7dd3fc;
}

.vnn-side-site i {
    margin-right: 6px;
}

/* ==============================================
   CAC MUC MENU
   ============================================== */

.vnn-side-link {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 8px;

    min-height: 47px;
    padding: 12px 15px;

    text-decoration: none;
    color: #cbd5e1;

    font-size: 13px;
    font-weight: 500;
    line-height: 1.5;

    box-sizing: border-box;

    transition:
        background 0.2s,
        color 0.2s;
}

.vnn-side-label {
    display: inline-flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.vnn-side-label > i {
    width: 19px;
    text-align: center;
    flex-shrink: 0;
}

.vnn-side-link:hover,
.vnn-side-link.active {
    background: #334155;
    color: #38bdf8;
}

.vnn-side-link.active {
    border-left: 3px solid #f97316;
    padding-left: 12px;
}

/* ==============================================
   TIEU DE NHOM
   ============================================== */

.vnn-side-title {
    display: flex;
    align-items: center;
    gap: 9px;

    margin-top: 6px;
    padding: 14px 15px;

    background: #253247;

    color: white;

    font-size: 12px;
    font-weight: 900;
    line-height: 1.5;
}

/* ==============================================
   SO LUONG CHO DUYET
   ============================================== */

.vnn-side-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 22px;
    height: 22px;
    padding: 0 6px;

    border-radius: 20px;

    background: #ef4444;
    color: white;

    font-size: 11px;
    font-weight: 900;

    flex-shrink: 0;
    box-sizing: border-box;
}

/* ==============================================
   MENU MO RONG - LICH SU HOAT DONG
   ============================================== */

.vnn-side-trigger {
    width: 100%;
    min-height: 48px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 8px;

    padding: 13px 15px;

    border: none;
    background: transparent;

    color: #cbd5e1;

    cursor: pointer;
    text-align: left;

    font: inherit;
    font-size: 13px;
    font-weight: 600;

    box-sizing: border-box;

    transition:
        background 0.2s,
        color 0.2s;
}

.vnn-side-trigger > span {
    display: inline-flex;
    align-items: center;
    gap: 11px;
}

.vnn-side-trigger > span > i {
    width: 19px;
    text-align: center;
}

.vnn-side-trigger:hover,
.vnn-side-trigger.active {
    background: #334155;
    color: #38bdf8;
}

.vnn-side-chevron {
    font-size: 11px;
    transition: transform 0.2s;
}

.vnn-side-trigger[aria-expanded="true"]
.vnn-side-chevron {
    transform: rotate(180deg);
}

/* ==============================================
   MENU CON
   ============================================== */

.vnn-side-submenu {
    background: #0c1627;
}

.vnn-side-submenu[hidden] {
    display: none !important;
}

.vnn-side-submenu .vnn-side-link {
    padding-left: 28px;
    min-height: 43px;
    font-size: 12px;
}

.vnn-side-submenu .vnn-side-link.active {
    padding-left: 25px;
}

/* ==============================================
   GHI CHU ADMIN
   ============================================== */

.vnn-side-note {
    margin: 18px 14px;
    padding: 12px;

    border: 1px solid #334155;
    border-radius: 10px;

    color: #94a3b8;

    font-size: 12px;
    line-height: 1.7;
}

/* ==============================================
   RESPONSIVE
   ============================================== */

@media (max-width: 700px) {
    .admin-sidebar {
        width: 200px !important;
        min-width: 200px !important;
        flex-basis: 200px !important;
    }

    .vnn-side-brand {
        padding: 16px 11px;
        gap: 9px;
    }

    .vnn-side-link {
        padding: 11px;
        font-size: 12px;
    }

    .vnn-side-trigger {
        padding: 11px;
        font-size: 12px;
    }

    .vnn-side-submenu .vnn-side-link {
        padding-left: 23px;
    }
}
</style>

<aside class="admin-sidebar">

    <!-- LOGO -->

    <div class="vnn-side-brand">

        <i class="fa-solid fa-mountain-sun"></i>

        <div>
            <strong>VNN</strong>
            <small>TRAVEL</small>
        </div>

    </div>

    <!-- XEM WEBSITE -->

    <a
        class="vnn-side-site"
        href="<?= vnnSideEsc($root) ?>index.php"
        target="_blank"
        rel="noopener"
    >
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        Xem Website Trực Tiếp
    </a>

    <!-- =====================================
         MENU ADMIN
         ===================================== -->

    <?php if ($role === 'admin'): ?>

        <!-- QUAN LY TAI KHOAN -->

        <?php
        vnnSideLink(
            $root . 'admin/system.php?tab=users',
            'fa-users-gear',
            'Quản lý & Cấp quyền tài khoản',
            $current_page === 'system-users'
        );
        ?>

        <!-- LICH SU HOAT DONG -->

        <button
            type="button"
            class="vnn-side-trigger <?= $historyOpen ? 'active' : '' ?>"
            id="vnnHistoryToggle"
            aria-expanded="<?= $historyOpen ? 'true' : 'false' ?>"
            aria-controls="vnnHistoryItems"
        >

            <span>
                <i class="fa-solid fa-clock-rotate-left"></i>
                Lịch sử hoạt động
            </span>

            <i class="fa-solid fa-chevron-down vnn-side-chevron"></i>

        </button>

        <div
            class="vnn-side-submenu"
            id="vnnHistoryItems"
            <?= $historyOpen ? '' : 'hidden' ?>
        >

            <?php
            $historyItems = [
                'accounts' => '1. Tài khoản',
                'content' => '2. Nội dung website',
                'transactions' => '3. Giao dịch',
                'security' => '4. Bảo mật'
            ];

            foreach ($historyItems as $id => $label) {

                vnnSideLink(
                    $root .
                    'admin/system.php?tab=history&category=' .
                    $id,
                    'fa-angle-right',
                    $label,
                    $historyOpen && $category === $id
                );
            }
            ?>

        </div>

        <div class="vnn-side-note">
            <i class="fa-solid fa-shield-halved"></i>
            Khu vực quản trị tài khoản và theo dõi lịch sử hệ thống.
        </div>

    <!-- =====================================
         MENU MANAGER / STAFF
         ===================================== -->

    <?php else: ?>

        <!-- DASHBOARD -->

        <?php
        vnnSideLink(
            $root . 'admin/index.php',
            'fa-gauge',
            'Dashboard Bàn Làm Việc',
            $current_page === 'dashboard'
        );
        ?>

        <!-- XU LY KHACH HANG -->

        <div class="vnn-side-title">

            <i class="fa-solid fa-headset"></i>
            XỬ LÝ KHÁCH HÀNG

        </div>

        <?php
        vnnSideLink(
            $root . 'admin/chat/',
            'fa-comments',
            'Chat Trực Tuyến',
            $current_page === 'chat'
        );

        vnnSideLink(
            $root . 'admin/bookings/',
            'fa-ticket',
            'Duyệt Tour',
            $current_page === 'bookings',
            $pendingTours
        );

        vnnSideLink(
            $root . 'admin/orders/',
            'fa-box',
            'Duyệt Đơn Hàng',
            $current_page === 'orders',
            $pendingOrders
        );

        vnnSideLink(
            $root . 'admin/reviews/',
            'fa-star',
            'Đánh Giá Từ Khách',
            $current_page === 'reviews'
        );

        vnnSideLink(
            $root . 'admin/contacts/',
            'fa-envelope',
            'Tin Nhắn Liên Hệ',
            $current_page === 'contacts'
        );
        ?>

        <!-- CHUC NANG RIENG MANAGER -->

        <?php if ($role === 'manager'): ?>

            <div class="vnn-side-title">

                <i class="fa-solid fa-boxes-stacked"></i>
                NỘI DUNG & KHO

            </div>

            <?php
            vnnSideLink(
                $root . 'admin/tours/',
                'fa-person-hiking',
                'Quản Lý Tour',
                $current_page === 'tours'
            );

            vnnSideLink(
                $root . 'admin/leaders/',
                'fa-person-hiking',
                'Đội Ngũ Leader',
                $current_page === 'leaders'
            );

            vnnSideLink(
                $root . 'admin/products/',
                'fa-warehouse',
                'Dụng Cụ, Kho & Danh Mục',
                $current_page === 'products'
            );

            vnnSideLink(
                $root . 'admin/locations/',
                'fa-map-location-dot',
                'Điểm Đến Trekking',
                $current_page === 'locations'
            );

            vnnSideLink(
                $root . 'admin/posts/',
                'fa-newspaper',
                'Bài Viết & Cẩm Nang',
                $current_page === 'posts'
            );

            vnnSideLink(
                $root . 'admin/revenue.php',
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

    const trigger = document.getElementById(
        'vnnHistoryToggle'
    );

    const submenu = document.getElementById(
        'vnnHistoryItems'
    );

    if (!trigger || !submenu) {
        return;
    }

    trigger.addEventListener('click', function() {

        submenu.hidden = !submenu.hidden;

        trigger.setAttribute(
            'aria-expanded',
            String(!submenu.hidden)
        );

    });

})();
</script>
