
<?php
// VNN Travel - Header
// Fix dropdown Tai Khoan tu dong dong khi di chuot.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$cart_count = getCartCount();
$active_menu = $active_menu ?? '';
$page_title = $page_title ?? 'VNN Travel - Khám Phá & Chinh Phục Đỉnh Cao';

$baseUrl = rtrim(BASE_URL, '/') . '/';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>
    </title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet"
          href="<?= $baseUrl ?>assets/css/style.css">

    <link rel="stylesheet"
          href="<?= $baseUrl ?>assets/css/responsive.css">

    <style>
        /* ================= LOGO ================= */

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

        /* ================= NAVBAR ================= */

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
            white-space: nowrap !important;
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
            white-space: nowrap !important;
        }

        .nav-link:hover,
        .nav-link.active {
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

        /* ================= ACCOUNT DROPDOWN ================= */

        .user-dropdown-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            isolation: isolate;
        }

        .user-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin: 0 !important;
            min-width: 220px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            padding: 8px 0;
            z-index: 99999;
        }

        /*
         * Khong de khoang trong giua nut Tai Khoan
         * va menu dropdown.
         */
        .user-dropdown-wrapper:hover .user-dropdown-menu,
        .user-dropdown-wrapper:focus-within .user-dropdown-menu {
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
            white-space: nowrap;
            transition:
                background 0.2s ease,
                color 0.2s ease,
                padding-left 0.2s ease;
        }

        .user-dropdown-menu a:hover,
        .user-dropdown-menu a:focus-visible {
            background: #f8fafc;
            color: #ea580c;
            padding-left: 22px;
            outline: none;
        }

        .user-dropdown-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 6px 0;
        }

        /*
         * Dam bao dropdown khong bi phan tu
         * khac che khuat.
         */
        .main-header {
            position: relative;
            z-index: 1000;
        }

        .main-header .navbar,
        .main-header .nav-actions {
            overflow: visible;
        }

        /* Ho tro thiet bi cam ung */
        @media (hover: none) {
            .user-dropdown-wrapper:focus-within .user-dropdown-menu {
                display: block;
            }
        }
    </style>
</head>

<body>

<header class="main-header">

    <!-- THANH THONG TIN TREN CUNG -->
    <div class="header-top">
        <div class="container">

            <div>
                <i class="fa-solid fa-phone"></i>
                Hotline: 0988.888.888 |

                <i class="fa-solid fa-envelope"></i>
                contact@vnntravel.vn
            </div>

            <div>
                <?php if (isLoggedIn()): ?>

                    <span>
                        Xin chào,
                        <strong>
                            <?= htmlspecialchars(
                                $_SESSION['fullname'] ?? 'Thành viên',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>
                        (<?= htmlspecialchars(ucfirst(getRole()), ENT_QUOTES, 'UTF-8') ?>)
                    </span>

                <?php else: ?>

                    <span>
                        VNN Travel - Chuyên tổ chức tour leo núi
                        & dã ngoại chuyên nghiệp
                    </span>

                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- THANH DIEU HUONG -->
    <div class="container">
        <nav class="navbar">

            <!-- LOGO VNN TRAVEL -->
            <a href="<?= $baseUrl ?>index.php"
               class="vnn-brand-container">

                <div class="vnn-mountain-left">
                    <svg viewBox="0 0 180 115"
                         xmlns="http://www.w3.org/2000/svg">

                        <polygon points="50,42 22,86 82,86"
                                 fill="#58a82d"/>

                        <polygon points="50,42 58,56 44,56"
                                 fill="#ffffff"/>

                        <polygon points="95,8 42,94 150,94"
                                 fill="#4d9e26"/>

                        <polygon points="95,8 116,40 105,36 95,48 85,36 76,42"
                                 fill="#ffffff"/>

                        <path d="M95,48 L108,66 L99,62 L92,76 L82,64 Z"
                              fill="#ffffff" opacity="0.95"/>

                        <polygon points="135,38 106,86 170,86"
                                 fill="#3b7a1e"/>

                        <polygon points="135,38 144,52 128,52"
                                 fill="#ffffff"/>

                        <path d="M6,90 C48,58 132,58 174,90 C140,84 40,84 6,90 Z"
                              fill="#6db832"/>

                        <path d="M16,88 C52,74 128,74 164,88 C138,102 42,102 16,88 Z"
                              fill="#1b5e20"/>

                        <polygon points="36,76 31,86 41,86"
                                 fill="#0d3c13"/>

                        <polygon points="44,74 39,86 49,86"
                                 fill="#0d3c13"/>

                        <polygon points="54,74 49,86 59,86"
                                 fill="#0d3c13"/>

                        <polygon points="134,76 129,86 139,86"
                                 fill="#0d3c13"/>

                        <polygon points="144,74 139,86 149,86"
                                 fill="#0d3c13"/>

                        <path d="M88,80 C90,86 82,90 87,96 C90,98 104,98 107,92 C102,88 96,84 94,80 Z"
                              fill="#ffffff"/>
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

            <!-- MENU CHINH -->
            <ul class="nav-menu">

                <li>
                    <a href="<?= $baseUrl ?>index.php"
                       class="nav-link <?= $active_menu === 'home' ? 'active' : '' ?>">
                        TRANG CHỦ
                    </a>
                </li>

                <li>
                    <a href="<?= $baseUrl ?>tours/tour.php"
                       class="nav-link <?= $active_menu === 'tour' ? 'active' : '' ?>">
                        TOUR TREKKING
                    </a>
                </li>

                <li>
                    <a href="<?= $baseUrl ?>pages/locations.php"
                       class="nav-link <?= $active_menu === 'location' ? 'active' : '' ?>">
                        ĐỊA ĐIỂM
                    </a>
                </li>

                <li>
                    <a href="<?= $baseUrl ?>shop/shop.php"
                       class="nav-link <?= $active_menu === 'shop' ? 'active' : '' ?>">
                        CỬA HÀNG
                    </a>
                </li>

                <li>
                    <a href="<?= $baseUrl ?>pages/blog.php"
                       class="nav-link <?= $active_menu === 'blog' ? 'active' : '' ?>">
                        BÀI VIẾT
                    </a>
                </li>

                <li>
                    <a href="<?= $baseUrl ?>pages/contact.php"
                       class="nav-link <?= $active_menu === 'contact' ? 'active' : '' ?>">
                        LIÊN HỆ
                    </a>
                </li>

            </ul>

            <!-- CAC NUT BEN PHAI -->
            <div class="nav-actions">

                <!-- GIO HANG -->
                <a href="<?= $baseUrl ?>shop/cart.php"
                   class="btn btn-outline btn-sm"
                   style="position:relative;border-radius:20px;">

                    <i class="fa-solid fa-cart-shopping"></i>
                    Giỏ Hàng

                    <?php if ($cart_count > 0): ?>

                        <span class="badge"
                              style="position:absolute;top:-8px;right:-8px;">
                            <?= (int) $cart_count ?>
                        </span>

                    <?php endif; ?>

                </a>

                <?php if (isLoggedIn()): ?>

                    <!-- QUAN TRI -->
                    <?php if (in_array(getRole(), ['admin', 'manager', 'staff'], true)): ?>

                        <a href="<?= $baseUrl ?>admin/index.php"
                           class="btn btn-accent btn-sm"
                           style="border-radius:20px;">

                            <i class="fa-solid fa-gauge"></i>
                            Quản Trị

                        </a>

                    <?php endif; ?>

                    <!-- TAI KHOAN + DROPDOWN -->
                    <div class="user-dropdown-wrapper">

                        <a href="<?= $baseUrl ?>auth/profile.php"
                           class="btn btn-outline btn-sm"
                           aria-haspopup="true"
                           style="
                                border-radius:20px;
                                display:inline-flex;
                                align-items:center;
                                gap:6px;
                           ">

                            <i class="fa-regular fa-user"></i>

                            Tài Khoản

                            <i class="fa-solid fa-chevron-down"
                               style="font-size:0.75rem;"></i>

                        </a>

                        <!-- MENU TAI KHOAN -->
                        <div class="user-dropdown-menu">

                            <a href="<?= $baseUrl ?>tours/my-tours.php"
                               style="color:#ea580c;">

                                <i class="fa-solid fa-ticket"></i>
                                Tour Tôi Đã Đăng Ký

                            </a>

                            <a href="<?= $baseUrl ?>shop/my-orders.php">

                                <i class="fa-solid fa-box-open"></i>
                                Đơn Mua Thiết Bị

                            </a>

                            <div class="user-dropdown-divider"></div>

                            <a href="<?= $baseUrl ?>auth/profile.php">

                                <i class="fa-regular fa-id-card"></i>
                                Thông Tin Cá Nhân

                            </a>

                            <a href="<?= $baseUrl ?>auth/logout.php"
                               style="color:#dc2626;">

                                <i class="fa-solid fa-right-from-bracket"></i>
                                Đăng Xuất

                            </a>

                        </div>

                    </div>

                    <!-- NUT DANG XUAT NHANH -->
                    <a href="<?= $baseUrl ?>auth/logout.php"
                       class="btn btn-danger btn-sm"
                       style="border-radius:20px;"
                       title="Đăng xuất">

                        <i class="fa-solid fa-right-from-bracket"></i>

                    </a>

                <?php else: ?>

                    <!-- DANG NHAP -->
                    <a href="<?= $baseUrl ?>auth/login.php"
                       class="btn btn-primary btn-sm"
                       style="border-radius:20px;">

                        Đăng Nhập

                    </a>

                    <!-- DANG KY -->
                    <a href="<?= $baseUrl ?>auth/register.php"
                       class="btn btn-accent btn-sm"
                       style="border-radius:20px;">

                        Đăng Ký

                    </a>

                <?php endif; ?>

            </div>

        </nav>
    </div>

</header>
