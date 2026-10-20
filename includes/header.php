<?php
// trekking/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$cart_count = getCartCount();
$active_menu = $active_menu ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . " - VNN Travel" : "VNN Travel - Khám Phá & Chinh Phục Đỉnh Cao" ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/responsive.css">
    <style>
        /* BỐ CỤC LOGO: NÚI BÊN TRÁI - 2 DÒNG BÊN PHẢI */
        .vnn-brand-container {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            user-select: none;
            height: 54px;
            flex-shrink: 0;
        }
        .vnn-mountain-left {
            width: 72px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .vnn-mountain-left svg {
            width: 100%;
            height: 100%;
            display: block;
        }
        .vnn-text-right {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 52px;
        }
        .vnn-letters-row {
            display: flex;
            align-items: center;
            line-height: 1;
            font-size: 1.75rem;
            font-weight: 900;
            letter-spacing: 0.5px;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .vnn-travel-row {
            font-size: 0.88rem;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: 3.5px;
            text-transform: uppercase;
            line-height: 1;
            font-family: system-ui, -apple-system, sans-serif;
        }

        /* KHẮC PHỤC TRIỆT ĐỂ MENU BỊ RỚT DÒNG CHỮ */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 82px;
            gap: 20px;
        }
        .nav-menu {
            display: flex !important;
            align-items: center;
            list-style: none;
            gap: 24px;
            margin: 0;
            padding: 0;
            white-space: nowrap !important; /* Tuyệt đối không cho ngắt dòng */
            flex-shrink: 0;
        }
        .nav-menu li {
            display: inline-block;
            white-space: nowrap !important;
        }
        .nav-link {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
            padding: 8px 0;
            position: relative;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap !important; /* Mỗi link nằm trên đúng 1 dòng */
        }
        .nav-link:hover, .nav-link.active {
            color: #ea580c;
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #ea580c;
            border-radius: 2px;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Menu thả xuống cho Tài Khoản */
        .user-dropdown-wrapper {
            position: relative;
            display: inline-block;
        }
        .user-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: #ffffff;
            min-width: 220px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 8px 0;
            z-index: 99999;
            margin-top: 8px;
        }
        .user-dropdown-wrapper:hover .user-dropdown-menu {
            display: block;
        }
        .user-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            color: #334155;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .user-dropdown-menu a:hover {
            background: #f8fafc;
            color: #ea580c;
            padding-left: 22px;
        }
        .user-dropdown-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 6px 0;
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="header-top">
        <div class="container">
            <div><i class="fa-solid fa-phone"></i> Hotline: 0988.888.888 | <i class="fa-solid fa-envelope"></i> contact@vnntravel.vn</div>
            <div>
                <?php if (isLoggedIn()): ?>
                    <span>Xin chào, <strong><?= htmlspecialchars($_SESSION['fullname']) ?></strong> (<?= ucfirst(getRole()) ?>)</span>
                <?php else: ?>
                    <span>VNN Travel - Chuyên tổ chức tour leo núi & dã ngoại chuyên nghiệp</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <nav class="navbar">
            <!-- LOGO: NÚI BÊN TRÁI - VNN & TRAVEL BÊN PHẢI -->
            <a href="<?= BASE_URL ?>index.php" class="vnn-brand-container">
                <div class="vnn-mountain-left">
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
                        <polygon points="54,74 49,86 59,86" fill="#0d3c13" />
                        <polygon points="134,76 129,86 139,86" fill="#0d3c13" />
                        <polygon points="144,74 139,86 149,86" fill="#0d3c13" />
                        <path d="M88,80 C90,86 82,90 87,96 C90,98 104,98 107,92 C102,88 96,84 94,80 Z" fill="#ffffff" />
                    </svg>
                </div>
                <div class="vnn-text-right">
                    <div class="vnn-letters-row">
                        <span style="color:#026aa7;">V</span>
                        <span style="color:#f97316;">N</span>
                        <span style="color:#78350f;">N</span>
                    </div>
                    <div class="vnn-travel-row">
                        TRAVEL
                    </div>
                </div>
            </a>

            <!-- THANH MENU DUY NHẤT 1 HÀNG NGANG -->
            <ul class="nav-menu">
                <li><a href="<?= BASE_URL ?>index.php" class="nav-link <?= $active_menu === 'home' ? 'active' : '' ?>">TRANG CHỦ</a></li>
                <li><a href="<?= BASE_URL ?>tours/tour.php" class="nav-link <?= $active_menu === 'tour' ? 'active' : '' ?>">TOUR TREKKING</a></li>
                <li><a href="<?= BASE_URL ?>pages/locations.php" class="nav-link <?= $active_menu === 'location' ? 'active' : '' ?>">ĐỊA ĐIỂM</a></li>
                <li><a href="<?= BASE_URL ?>shop/shop.php" class="nav-link <?= $active_menu === 'shop' ? 'active' : '' ?>">CỬA HÀNG</a></li>
                <li><a href="<?= BASE_URL ?>pages/blog.php" class="nav-link <?= $active_menu === 'blog' ? 'active' : '' ?>">BÀI VIẾT</a></li>
                <li><a href="<?= BASE_URL ?>pages/contact.php" class="nav-link <?= $active_menu === 'contact' ? 'active' : '' ?>">LIÊN HỆ</a></li>
            </ul>

            <div class="nav-actions">
                <a href="<?= BASE_URL ?>shop/cart.php" class="btn btn-outline btn-sm" style="position:relative; border-radius:20px;">
                    <i class="fa-solid fa-cart-shopping"></i> Giỏ Hàng
                    <?php if ($cart_count > 0): ?>
                        <span class="badge" style="position:absolute; top:-8px; right:-8px;"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isLoggedIn()): ?>
                    <?php if (in_array(getRole(), ['admin', 'manager', 'staff'])): ?>
                        <a href="<?= BASE_URL ?>admin/index.php" class="btn btn-accent btn-sm" style="border-radius:20px;">
                            <i class="fa-solid fa-gauge"></i> Quản Trị
                        </a>
                    <?php endif; ?>

                    <div class="user-dropdown-wrapper">
                        <a href="<?= BASE_URL ?>auth/profile.php" class="btn btn-outline btn-sm" style="border-radius:20px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-regular fa-user"></i> Tài Khoản <i class="fa-solid fa-chevron-down" style="font-size:0.75rem;"></i>
                        </a>
                        <div class="user-dropdown-menu">
                            <a href="<?= BASE_URL ?>tours/my-tours.php" style="color:#ea580c;">
                                <i class="fa-solid fa-ticket"></i> Tour Tôi Đã Đăng Ký
                            </a>
                            <a href="<?= BASE_URL ?>shop/my-orders.php">
                                <i class="fa-solid fa-box-open"></i> Đơn Mua Thiết Bị
                            </a>
                            <div class="user-dropdown-divider"></div>
                            <a href="<?= BASE_URL ?>auth/profile.php">
                                <i class="fa-regular fa-id-card"></i> Thông Tin Cá Nhân
                            </a>
                            <a href="<?= BASE_URL ?>auth/logout.php" style="color:#dc2626;">
                                <i class="fa-solid fa-right-from-bracket"></i> Đăng Xuất
                            </a>
                        </div>
                    </div>

                    <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-danger btn-sm" style="border-radius:20px;" title="Đăng xuất">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-primary btn-sm" style="border-radius:20px;">Đăng Nhập</a>
                    <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-accent btn-sm" style="border-radius:20px;">Đăng Ký</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>