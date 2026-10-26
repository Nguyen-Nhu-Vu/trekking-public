<?php
// VNN Travel - Header dong bo font va giao dien gon.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$cart_count = getCartCount();
$active_menu = $active_menu ?? '';
$page_title = $page_title ?? 'VNN Travel - Khám Phá & Chinh Phục Đỉnh Cao';
$baseUrl = rtrim(BASE_URL, '/') . '/';

if (!function_exists('vnnHeaderH')) {
    function vnnHeaderH($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$navItems = [
    ['home', 'index.php', 'TRANG CHỦ'],
    ['tour', 'tours/tour.php', 'TOUR TREKKING'],
    ['leaders', 'pages/leaders.php', 'HƯỚNG DẪN VIÊN'],
    ['location', 'pages/locations.php', 'ĐỊA ĐIỂM'],
    ['shop', 'shop/shop.php', 'CỬA HÀNG'],
    ['blog', 'pages/blog.php', 'BÀI VIẾT'],
    ['contact', 'pages/contact.php', 'LIÊN HỆ']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= vnnHeaderH($page_title) ?></title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= vnnHeaderH($baseUrl) ?>assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="<?= vnnHeaderH($baseUrl) ?>assets/css/responsive.css"
    >

    <style>
        /* ===================================
           HEADER VNN TRAVEL - DONG BO FONT
           =================================== */

        .site-header {
            position: relative;
            z-index: 1000;
            background: #fff;
            border-bottom: 1px solid #e9edf2;
        }

        .site-header,
        .site-header button,
        .site-header a {
            font-family: Arial, Helvetica, sans-serif !important;
            letter-spacing: 0 !important;
        }

        .site-header .vnn-header-container {
            width: calc(100% - 64px);
            max-width: 1600px;
            margin: 0 auto;
        }

        .site-header .navbar {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            min-height: 68px;
            padding: 5px 0;
            gap: 16px;
        }

        /* ===================================
           LOGO
           =================================== */

        .site-header .vnn-brand-container {
            display: inline-flex;
            align-items: center;
            flex: 0 0 auto;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
        }

        .vnn-mountain-left {
            width: 66px;
            height: 47px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vnn-mountain-left svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .vnn-text-right {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 3px;
        }

        .vnn-letters-row {
            display: flex;
            align-items: center;
            font-size: 26px;
            font-weight: 900;
            line-height: 1;
        }

        .vnn-travel-row {
            color: #1e293b;
            font-weight: 900;
            font-size: 10px;
            letter-spacing: 3px;
            line-height: 1.3;
        }

        /* ===================================
           MENU NGANG
           =================================== */

        .site-header .nav-menu {
            display: flex;
            flex: 1 1 auto;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
            min-width: 0;
            width: auto;
            margin: 0;
            padding: 0;
            gap: clamp(10px, 1.35vw, 23px);
            list-style: none;
        }

        .site-header .nav-menu > li {
            margin: 0;
            padding: 0;
            flex: 0 0 auto;
            list-style: none;
        }

        .site-header .nav-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 0;
            color: #16213b;
            text-decoration: none;
            white-space: nowrap;
            font-size: 13px !important;
            font-weight: 700 !important;
            line-height: 1.4;
            transition: color .2s ease;
        }

        .site-header .nav-link:hover,
        .site-header .nav-link.active {
            color: #ea580c;
        }

        .site-header .nav-link.active::after {
            content: "";
            position: absolute;
            bottom: 1px;
            left: 0;
            right: 0;
            height: 2px;
            border-radius: 3px;
            background: #ea580c;
        }

        /* ===================================
           NUT TAI KHOAN
           =================================== */

        .site-header .nav-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: nowrap;
            flex: 0 0 auto;
            gap: 8px;
        }

        .vnn-account {
            position: relative;
            flex-shrink: 0;
        }

        .vnn-account-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 39px;
            min-height: 39px;
            padding: 0 16px;
            border: 0;
            border-radius: 25px;
            background: #166534;
            color: #fff;
            cursor: pointer;
            font-size: 13px !important;
            font-weight: 700 !important;
            white-space: nowrap;
            line-height: 1;
            transition: background .2s;
        }

        .vnn-account-button:hover,
        .vnn-account-button:focus-visible {
            background: #14532d;
            color: #fff;
        }

        .vnn-account-button i {
            font-size: 14px;
        }

        .vnn-account-chevron {
            font-size: 11px !important;
            transition: transform .2s;
        }

        .vnn-account.open .vnn-account-chevron {
            transform: rotate(180deg);
        }

        /* ===================================
           DROPDOWN GON GANG
           =================================== */

        .vnn-account-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            z-index: 9999;
            width: 242px;
            max-width: calc(100vw - 25px);
            padding: 5px 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .15);
        }

        .vnn-account.open .vnn-account-dropdown,
        .vnn-account:focus-within .vnn-account-dropdown {
            display: block;
        }

        .vnn-account-dropdown::before {
            content: "";
            position: absolute;
            top: -8px;
            left: 0;
            right: 0;
            height: 8px;
        }

        .vnn-account-dropdown a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-height: 40px;
            padding: 10px 14px;
            color: #243047;
            text-decoration: none;
            font-size: 13px !important;
            font-weight: 700 !important;
            line-height: 1.4;
            transition: background .2s, color .2s;
        }

        .vnn-account-dropdown a:hover {
            background: #f1f5f9;
            color: #166534;
        }

        .vnn-dropdown-label {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
        }

        .vnn-dropdown-label i {
            width: 18px;
            flex-shrink: 0;
            color: #166534;
            text-align: center;
            font-size: 14px;
        }

        .vnn-dropdown-divider {
            height: 1px;
            margin: 4px 0;
            background: #e2e8f0;
        }

        .vnn-cart-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 20px;
            color: white;
            background: #f97316;
            font-size: 11px;
            font-weight: 800;
        }

        .vnn-account-dropdown a.vnn-logout,
        .vnn-account-dropdown a.vnn-logout i {
            color: #dc2626;
        }

        /* ===================================
           DANG NHAP / DANG KY
           =================================== */

        .vnn-login-actions {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            gap: 7px;
        }

        .vnn-login-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 37px;
            padding: 8px 13px;
            border-radius: 22px;
            text-decoration: none;
            white-space: nowrap;
            font-size: 12px !important;
            font-weight: 700 !important;
        }

        .vnn-login {
            background: #166534;
            color: #fff;
        }

        .vnn-register {
            background: #f97316;
            color: #fff;
        }

        /* ===================================
           RESPONSIVE
           =================================== */

        @media(max-width: 1370px) {
            .site-header .vnn-header-container {
                width: calc(100% - 32px);
            }

            .site-header .navbar {
                gap: 10px;
            }

            .site-header .nav-menu {
                gap: 10px;
            }

            .site-header .nav-link {
                font-size: 12px !important;
            }

            .vnn-mountain-left {
                width: 59px;
                height: 43px;
            }

            .vnn-letters-row {
                font-size: 24px;
            }
        }

        @media(max-width: 1100px) {
            .site-header .navbar {
                flex-wrap: wrap;
                justify-content: space-between;
                row-gap: 5px;
            }

            .site-header .nav-menu {
                order: 3;
                flex: 0 0 100%;
                width: 100%;
                justify-content: center;
                padding-bottom: 5px;
            }
        }

        @media(max-width: 680px) {
            .site-header .vnn-header-container {
                width: calc(100% - 22px);
            }

            .site-header .navbar {
                padding: 7px 0;
            }

            .vnn-mountain-left {
                width: 52px;
                height: 38px;
            }

            .vnn-letters-row {
                font-size: 21px;
            }

            .vnn-travel-row {
                font-size: 9px;
                letter-spacing: 2px;
            }

            .site-header .nav-menu {
                justify-content: flex-start;
                flex-wrap: nowrap;
                overflow-x: auto;
                gap: 16px;
                padding: 6px 0 9px;
                scrollbar-width: thin;
            }

            .site-header .nav-link {
                font-size: 12px !important;
            }

            .vnn-account-button {
                height: 37px;
                min-height: 37px;
                padding: 0 11px;
                font-size: 12px !important;
            }

            .vnn-account-dropdown {
                width: 230px;
            }

            .vnn-login-actions a {
                padding: 8px 10px;
                font-size: 11px !important;
            }
        }
    </style>
</head>

<body>

<header class="site-header">

    <div class="vnn-header-container">

        <nav class="navbar" aria-label="Điều hướng chính">

            <!-- LOGO VNN TRAVEL -->

            <a
                href="<?= vnnHeaderH($baseUrl) ?>index.php"
                class="vnn-brand-container"
                aria-label="VNN Travel - Trang chủ"
            >

                <div class="vnn-mountain-left">

                    <svg viewBox="0 0 180 100"
                         xmlns="http://www.w3.org/2000/svg"
                         role="img"
                         aria-label="VNN Travel">

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

                    </svg>

                </div>

                <div class="vnn-text-right">

                    <div class="vnn-letters-row">
                        <span style="color:#026aa7">V</span>
                        <span style="color:#f97316">N</span>
                        <span style="color:#78350f">N</span>
                    </div>

                    <div class="vnn-travel-row">
                        TRAVEL
                    </div>

                </div>

            </a>

            <!-- MENU CHINH -->

            <ul class="nav-menu">

                <?php foreach ($navItems as $item): ?>

                    <li>
                        <a
                            href="<?= vnnHeaderH($baseUrl . $item[1]) ?>"
                            class="nav-link <?= $active_menu === $item[0] ? 'active' : '' ?>"
                        >
                            <?= vnnHeaderH($item[2]) ?>
                        </a>
                    </li>

                <?php endforeach; ?>

            </ul>

            <!-- TAI KHOAN -->

            <div class="nav-actions">

                <?php if (isLoggedIn()): ?>

                    <?php if (in_array(
                        getRole(),
                        ['admin', 'manager', 'staff'],
                        true
                    )): ?>

                        <a
                            href="<?= vnnHeaderH($baseUrl) ?>admin/index.php"
                            class="btn btn-accent btn-sm"
                            style="border-radius:20px;white-space:nowrap;font-size:12px"
                        >
                            <i class="fa-solid fa-gauge"></i>
                            Quản trị
                        </a>

                    <?php endif; ?>

                    <div class="vnn-account" id="vnnAccount">

                        <button
                            type="button"
                            class="vnn-account-button"
                            id="vnnAccountButton"
                            aria-expanded="false"
                            aria-controls="vnnAccountDropdown"
                            aria-haspopup="true"
                        >
                            <i class="fa-regular fa-user"></i>
                            <span>Tài Khoản</span>
                            <i class="fa-solid fa-chevron-down vnn-account-chevron"></i>
                        </button>

                        <div
                            class="vnn-account-dropdown"
                            id="vnnAccountDropdown"
                        >

                            <a href="<?= vnnHeaderH($baseUrl) ?>shop/cart.php">
                                <span class="vnn-dropdown-label">
                                    <i class="fa-solid fa-cart-shopping"></i>
                                    Giỏ Hàng
                                </span>

                                <?php if ($cart_count > 0): ?>
                                    <span class="vnn-cart-count">
                                        <?= (int)$cart_count ?>
                                    </span>
                                <?php endif; ?>
                            </a>

                            <a href="<?= vnnHeaderH($baseUrl) ?>tours/my-tours.php">
                                <span class="vnn-dropdown-label">
                                    <i class="fa-solid fa-ticket"></i>
                                    Tour Tôi Đã Đăng Ký
                                </span>
                            </a>

                            <a href="<?= vnnHeaderH($baseUrl) ?>shop/my-orders.php">
                                <span class="vnn-dropdown-label">
                                    <i class="fa-solid fa-box-open"></i>
                                    Đơn Mua Thiết Bị
                                </span>
                            </a>

                            <div class="vnn-dropdown-divider"></div>

                            <a href="<?= vnnHeaderH($baseUrl) ?>auth/profile.php">
                                <span class="vnn-dropdown-label">
                                    <i class="fa-regular fa-id-card"></i>
                                    Thông Tin Cá Nhân
                                </span>
                            </a>

                            <div class="vnn-dropdown-divider"></div>

                            <a
                                class="vnn-logout"
                                href="<?= vnnHeaderH($baseUrl) ?>auth/logout.php"
                            >
                                <span class="vnn-dropdown-label">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                    Đăng Xuất
                                </span>
                            </a>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="vnn-login-actions">

                        <a
                            href="<?= vnnHeaderH($baseUrl) ?>shop/cart.php"
                            class="vnn-login"
                            aria-label="Giỏ hàng"
                        >
                            <i class="fa-solid fa-cart-shopping"></i>
                        </a>

                        <a
                            href="<?= vnnHeaderH($baseUrl) ?>auth/login.php"
                            class="vnn-login"
                        >
                            Đăng Nhập
                        </a>

                        <a
                            href="<?= vnnHeaderH($baseUrl) ?>auth/register.php"
                            class="vnn-register"
                        >
                            Đăng Ký
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </nav>

    </div>

</header>

<script>
(function() {
    const account = document.getElementById('vnnAccount');
    const button = document.getElementById('vnnAccountButton');

    if (!account || !button) {
        return;
    }

    function setOpen(value) {
        account.classList.toggle('open', value);
        button.setAttribute('aria-expanded', String(value));
    }

    button.addEventListener('click', function(event) {
        event.stopPropagation();
        setOpen(!account.classList.contains('open'));
    });

    account.addEventListener('mouseenter', function() {
        if (window.matchMedia('(hover: hover)').matches) {
            setOpen(true);
        }
    });

    account.addEventListener('mouseleave', function() {
        if (window.matchMedia('(hover: hover)').matches) {
            setOpen(false);
        }
    });

    document.addEventListener('click', function(event) {
        if (!account.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
})();
</script>
