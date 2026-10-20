<?php
// trekking/admin/includes/sidebar.php
if (!isset($pending_bookings)) {
    $pending_bookings = $pdo->query("SELECT COUNT(*) FROM tour_bookings WHERE status = 'Chờ xác nhận'")->fetchColumn();
}
if (!isset($pending_orders)) {
    $pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Chờ xác nhận'")->fetchColumn();
}

$current_page = $current_page ?? '';
?>
<style>
/* Sidebar Accordion */
.sidebar-group-title {
    padding: 13px 20px;
    color: #cbd5e1;
    font-size: 0.92rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
    border-left: 4px solid transparent;
    transition: 0.2s ease;
}
.sidebar-group-title:hover {
    background: #243248;
    color: white;
}
.sidebar-group-title i.chevron {
    transition: transform 0.25s ease;
    font-size: 0.8rem;
}
.sidebar-sub-menu {
    display: none;
    list-style: none;
    padding-left: 0;
    background: #141d2b;
}
.sidebar-group.open .sidebar-sub-menu {
    display: block;
}
.sidebar-group.open .sidebar-group-title i.chevron {
    transform: rotate(180deg);
}
.sidebar-sub-menu li a {
    padding: 10px 20px 10px 28px !important;
    font-size: 0.88rem !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #94a3b8;
    border-left: 3px solid transparent;
    transition: 0.2s;
    text-decoration: none;
}
.sidebar-sub-menu li a:hover, .sidebar-sub-menu li.active a {
    color: #38bdf8 !important;
    background: #1e293b !important;
    border-left-color: #38bdf8 !important;
}

/* Huy hiệu số đỏ thông báo */
.badge-notify-pill {
    background-color: #ef4444;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
    min-width: 19px;
    height: 19px;
    line-height: 19px;
    border-radius: 10px;
    text-align: center;
    padding: 0 6px;
    box-shadow: 0 2px 5px rgba(239, 68, 68, 0.4);
}

/* Nút Xem Website nổi bật ở Sidebar */
.btn-sidebar-viewsite {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #1e293b;
    color: #38bdf8;
    border: 1px solid #334155;
    padding: 10px 15px;
    margin: 12px 16px;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}
.btn-sidebar-viewsite:hover {
    background: #0284c7;
    color: #ffffff;
    border-color: #0284c7;
}
</style>

<aside class="admin-sidebar">
    <!-- KHỐI LOGO VNN TRAVEL CHUẨN ĐỒNG BỘ -->
    <div class="admin-brand" style="display:flex; align-items:center; gap:12px; padding:18px 20px; height:74px; background:#0f172a; border-bottom:1px solid #1e293b;">
        <div style="width:54px; height:42px; flex-shrink:0;">
            <svg viewBox="0 0 180 115" xmlns="http://www.w3.org/2000/svg">
                <polygon points="50,42 22,86 82,86" fill="#58a82d" />
                <polygon points="50,42 58,56 44,56" fill="#ffffff" />
                <polygon points="95,8 42,94 150,94" fill="#4d9e26" />
                <polygon points="95,8 116,40 105,36 95,48 85,36 76,42" fill="#ffffff" />
                <path d="M95,48 L108,66 L99,62 L92,76 L82,64 Z" fill="#ffffff" opacity="0.95" />
                <polygon points="135,38 106,86 170,86" fill="#3b7a1e" />
                <polygon points="135,38 144,52 128,52" fill="#ffffff" />
                <path d="M6,90 C48,58 132,58 174,90 C140,84 40,84 6,90 Z" fill="#6db832" />
                <path d="M16,88 C52,74 128,74 164,88 C138,102 42,102 16,88 Z" fill="#1b5e20" />
                <polygon points="36,76 31,86 41,86" fill="#0d3c13" />
                <polygon points="44,74 39,86 49,86" fill="#0d3c13" />
                <polygon points="134,76 129,86 139,86" fill="#0d3c13" />
                <polygon points="144,74 139,86 149,86" fill="#0d3c13" />
                <path d="M88,80 C90,86 82,90 87,96 C90,98 104,98 107,92 C102,88 96,84 94,80 Z" fill="#ffffff" />
            </svg>
        </div>
        <div style="display:flex; flex-direction:column; justify-content:space-between; height:42px;">
            <div style="font-size:1.45rem; font-weight:900; line-height:1; letter-spacing:1px; font-family:system-ui, -apple-system, sans-serif;">
                <span style="color:#38bdf8;">V</span>
                <span style="color:#f97316;">N</span>
                <span style="color:#d97706;">N</span>
            </div>
            <div style="font-size:0.75rem; font-weight:800; color:#cbd5e1; letter-spacing:2.5px; line-height:1;">
                TRAVEL
            </div>
        </div>
    </div>

    <!-- NÚT XEM WEBSITE ĐẶT NGAY ĐẦU THANH MENU CHO TẤT CẢ VAI TRÒ -->
    <a href="<?= BASE_URL ?>index.php" target="_blank" class="btn-sidebar-viewsite">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem Website Trực Tiếp
    </a>

    <ul class="admin-menu" style="padding:0;">
        <li class="<?= $current_page === 'dashboard' ? 'active' : '' ?>" style="border-bottom: 1px solid #1e293b;">
            <a href="<?= BASE_URL ?>admin/index.php" style="padding: 12px 20px; color:#e2e8f0; font-weight:bold;">
                <i class="fa-solid fa-gauge"></i> Dashboard Bàn Làm Việc
            </a>
        </li>

        <!-- NHÓM 1: XỬ LÝ KHÁCH HÀNG (Nhân viên & Quản lý) -->
        <?php if (isStaff() || isManager()): ?>
        <li class="sidebar-group open">
            <div class="sidebar-group-title" onclick="toggleSidebarGroup(this)">
                <span><i class="fa-solid fa-bell-concierge" style="color:#38bdf8; margin-right:8px;"></i> XỬ LÝ KHÁCH HÀNG</span>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <ul class="sidebar-sub-menu">
                <li class="<?= $current_page === 'chat' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/chat/">
                        <span><i class="fa-solid fa-headset"></i> Chat Trực Tuyến</span>
                        <span class="badge-notify-pill" style="background:#16a34a;">Live</span>
                    </a>
                </li>
                <li class="<?= $current_page === 'bookings' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/bookings/">
                        <span><i class="fa-solid fa-ticket"></i> Duyệt Tour</span>
                        <?php if ($pending_bookings > 0): ?>
                            <span class="badge-notify-pill"><?= $pending_bookings ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="<?= $current_page === 'orders' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/orders/">
                        <span><i class="fa-solid fa-box-open"></i> Duyệt Đơn Hàng</span>
                        <?php if ($pending_orders > 0): ?>
                            <span class="badge-notify-pill"><?= $pending_orders ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="<?= $current_page === 'reviews' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/reviews/">
                        <span><i class="fa-solid fa-star"></i> Đánh Giá Từ Khách</span>
                    </a>
                </li>
                <li class="<?= $current_page === 'contacts' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/contacts/">
                        <span><i class="fa-solid fa-envelope"></i> Tin Nhắn Liên Hệ</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- NHÓM 2: QUẢN LÝ NỘI DUNG & KHO (Quản lý) -->
        <?php if (isManager()): ?>
        <li class="sidebar-group open">
            <div class="sidebar-group-title" onclick="toggleSidebarGroup(this)">
                <span><i class="fa-solid fa-boxes-stacked" style="color:#ea580c; margin-right:8px;"></i> NỘI DUNG & KHO</span>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <ul class="sidebar-sub-menu">
                <li class="<?= $current_page === 'tours' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/tours/"><span><i class="fa-solid fa-person-hiking"></i> Quản Lý Tour</span></a>
                </li>
                <li class="<?= $current_page === 'products' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/products/"><span><i class="fa-solid fa-campground"></i> Dụng Cụ & Tồn Kho</span></a>
                </li>
                <li class="<?= $current_page === 'categories' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/categories/"><span><i class="fa-solid fa-layer-group"></i> Danh Mục Hàng</span></a>
                </li>
                <li class="<?= $current_page === 'locations' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/locations/"><span><i class="fa-solid fa-map-location-dot"></i> Điểm Đến Trekking</span></a>
                </li>
                <li class="<?= $current_page === 'posts' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/posts/"><span><i class="fa-solid fa-newspaper"></i> Bài Viết & Cẩm Nang</span></a>
                </li>
            </ul>
        </li>

        <!-- NHÓM 3: DOANH THU & BÁO CÁO -->
        <li class="sidebar-group open">
            <div class="sidebar-group-title" onclick="toggleSidebarGroup(this)">
                <span><i class="fa-solid fa-chart-line" style="color:#10b981; margin-right:8px;"></i> DOANH THU BÁO CÁO</span>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <ul class="sidebar-sub-menu">
                <li class="<?= $current_page === 'revenue_tours' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/revenue-tours.php"><span><i class="fa-solid fa-money-bill-trend-up"></i> Doanh Thu Tour</span></a>
                </li>
                <li class="<?= $current_page === 'revenue_shop' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/revenue-shop.php"><span><i class="fa-solid fa-bag-shopping"></i> Doanh Thu Bán Hàng</span></a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- NHÓM 4: HỆ THỐNG ADMIN (Admin) -->
        <?php if (isAdmin()): ?>
        <li class="sidebar-group open">
            <div class="sidebar-group-title" onclick="toggleSidebarGroup(this)">
                <span><i class="fa-solid fa-shield-halved" style="color:#dc2626; margin-right:8px;"></i> QUẢN TRỊ HỆ THỐNG</span>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <ul class="sidebar-sub-menu">
                <li class="<?= $current_page === 'users' ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>admin/users/"><span><i class="fa-solid fa-user-shield"></i> Cấp Quyền & Tài Khoản</span></a>
                </li>
            </ul>
        </li>
        <?php endif; ?>
    </ul>
</aside>

<script>
function toggleSidebarGroup(headerElement) {
    const parentGroup = headerElement.parentElement;
    parentGroup.classList.toggle('open');
}
</script>