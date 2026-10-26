<?php
// VNN Travel - Header
// Menu ngang gon, gio hang nam trong Tai Khoan.

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
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

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
        /* ===== HEADER CHUNG ===== */

        .site-header {
            position: relative;
            z-index: 1000;
            background: #fff;
        }

        .site-header .vnn-header-container {
            width: min(1600px, calc(100% - 48px));
            max-width: 1600px;
            margin: 0 auto;
        }

        .site-header .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap;
            gap: 22px;
            min-height: 91px;
            padding: 10px 0;
        }

        /* ===== LOGO ===== */

        .vnn-brand-container {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            text-decoration: none;
            white-space: nowrap;
        }

        .vnn-mountain-left {
            width: 86px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vnn-mountain-left svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .vnn-text-right {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 4px;
        }

        .vnn-letters-row {
            display: flex;
            align-items: center;
            font-family: system-ui, sans-serif;
            font-size: 32px;
            font-weight: 900;
            letter-spacing: .5px;
            line-height: 1;
        }

        .vnn-travel-row {
            color: #1e293b;
            font-weight: 900;
            font-size: 13px;
            letter-spacing: 4px;
            line-height: 1.3;
        }

        /* ===== MENU NGANG ===== */

        .site-header .nav-menu {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1 1 auto;
            min-width: 0;
            width: auto;
            gap: clamp(10px, 1.25vw, 25px);
            flex-wrap: nowrap;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .site-header .nav-menu > li {
            list-style: none;
            flex: 0 0 auto;
            margin: 0;
            padding: 0;
        }

        .site-header .nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 12px 0;
            text-decoration: none;
            white-space: nowrap;
            color: #14213d;
            font-size: clamp(11px, .85vw, 14px);
            font-weight: 900;
            line-height: 1.3;
            transition: color .2s;
        }

        .site-header .nav-link:hover,
        .site-header .nav-link.active {
            color: #ea580c;
        }

        .site-header .nav-link.active::after {
            position: absolute;
            content: "";
            bottom: 1px;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 3px;
            background: #ea580c;
        }

        /* ===== KHU VUC TAI KHOAN ===== */

        .site-header .nav-actions {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: nowrap;
            gap: 10px;
        }

        .vnn-account {
            position: relative;
            flex-shrink: 0;
        }

        .vnn-account-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 44px;
            padding: 10px 17px;
            border: none;
            border-radius: 28px;
            background: #166534;
            color: white;
            font-family: inherit;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            text-decoration: none;
            transition: background .2s;
        }

        .vnn-account-button:hover,
        .vnn-account-button:focus-visible {
            background: #14532d;
            color: white;
        }

        .vnn-account-button i {
            font-size: 15px;
        }

        .vnn-account-chevron {
            font-size: 11px !important;
            transition: transform .2s;
        }

        .vnn-account.open .vnn-account-chevron {
            transform: rotate(180deg);
        }

        /* ===== MENU XO XUONG ===== */

        .vnn-account-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            z-index: 9999;
            width: 285px;
            max-width: calc(100vw - 30px);
            padding: 9px 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(15,23,42,.16);
        }

        .vnn-account.open .vnn-account-dropdown,
        .vnn-account:focus-within .vnn-account-dropdown {
            display: block;
        }

        .vnn-account-dropdown::before {
            content: "";
            position: absolute;
            height: 10px;
            left: 0;
            right: 0;
            top: -10px;
        }

        .vnn-account-dropdown a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 19px;
            color: #1e293b;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            transition: background .2s;
        }

        .vnn-account-dropdown a:hover {
            background: #f1f5f9;
            color: #166534;
        }

        .vnn-dropdown-label {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .vnn-dropdown-label i {
            width: 20px;
            text-align: center;
            color: #166534;
            font-size: 15px;
        }

        .vnn-dropdown-divider {
            margin: 7px 0;
            height: 1px;
            background: #e2e8f0;
        }

        .vnn-cart-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 20px;
            background: #f97316;
            color: white;
            font-size: 11px;
            font-weight: 900;
        }

        .vnn-account-dropdown a.vnn-logout {
            color: #dc2626;
        }

        .vnn-account-dropdown a.vnn-logout i {
            color: #dc2626;
        }

        .vnn-login-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .vnn-login-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 14px;
            border-radius: 25px;
            font-weight: 800;
            font-size: 12px;
            white-space: nowrap;
            text-decoration: none;
        }

        .vnn-login {
            background: #166534;
            color: white;
        }

        .vnn-register {
            background: #f97316;
            color: white;
        }

        /* ===== RESPONSIVE ===== */

        @media (max-width: 1380px) {
            .site-header .navbar {
                gap: 14px;
            }

            .vnn-mountain-left {
                width: 67px;
                height: 51px;
            }

            .vnn-letters-row {
                font-size: 27px;
            }

            .vnn-travel-row {
                font-size: 11px;
                letter-spacing: 3px;
            }

            .site-header .nav-menu {
                gap: 11px;
            }

            .site-header .nav-link {
                font-size: 11px;
            }

            .vnn-account-button {
                padding: 10px 13px;
                font-size: 12px;
            }
        }

        @media (max-width: 1090px) {
            .site-header .navbar {
                flex-wrap: wrap;
                justify-content: space-between;
                row-gap: 12px;
            }

            .site-header .nav-menu {
                order: 3;
                flex: 0 0 100%;
                width: 100%;
                justify-content: center;
                gap: 15px;
                padding-bottom: 6px;
            }

            .site-header .nav-link {
                font-size: 12px;
            }
        }

        @media (max-width: 680px) {
            .site-header .vnn-header-container {
                width: calc(100% - 24px);
            }

            .site-header .navbar {
                padding: 12px 0;
            }

            .vnn-mountain-left {
                width: 57px;
                height: 43px;
            }

            .vnn-letters-row {
                font-size: 24px;
            }

            .vnn-travel-row {
                font-size: 10px;
                letter-spacing: 2px;
            }

            .site-header .nav-menu {
                justify-content: flex-start;
                overflow-x: auto;
                flex-wrap: nowrap;
                padding: 8px 0 11px;
                gap: 17px;
                scrollbar-width: thin;
            }

            .site-header .nav-menu > li {
                flex-shrink: 0;
            }

            .site-header .nav-link {
                font-size: 12px;
            }

            .vnn-account-button {
                font-size: 12px;
                min-height: 40px;
                padding: 9px 12px;
            }

            .vnn-account-dropdown {
                width: 265px;
            }

            .vnn-login-actions {
                gap: 5px;
            }

            .vnn-login-actions a {
                font-size: 11px;
                padding: 10px;
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

                    <svg
                        viewBox="0 0 180 100"
                        xmlns="http://www.w3.org/2000/svg"
                        role="img"
                        aria-label="VNN Travel"
                    >

                        <polygon
                            points="50,42 22,86 82,86"
                            fill="#58a82d"
                        />

                        <polygon
                            points="50,42 58,56 44,56"
                            fill="#ffffff"
                        />

                        <polygon
                            points="95,8 42,94 150,94"
                            fill="#4d9e26"
                        />

                        <polygon
                            points="95,8 116,40 105,36 95,48 85,36 76,42"
                            fill="#ffffff"
                        />

                        <path
                            d="M95,48 L108,66 L99,62 L92,76 L82,64 Z"
                            fill="#ffffff"
                            opacity="0.95"
                        />

                        <polygon
                            points="135,38 106,86 170,86"
                            fill="#3b7a1e"
                        />

                        <polygon
                            points="135,38 144,52 128,52"
                            fill="#ffffff"
                        />

                        <path
                            d="M6,90 C48,58 132,58 174,90 C140,84 40,84 6,90 Z"
                            fill="#6db832"
                        />

                        <path
                            d="M16,88 C52,74 128,74 164,88 C138,102 42,102 16,88 Z"
                            fill="#1b5e20"
                        />

                        <polygon
                            points="36,76 31,86 41,86"
                            fill="#0d3c13"
                        />

                        <polygon
                            points="44,74 39,86 49,86"
                            fill="#0d3c13"
                        />

                        <polygon
                            points="54,74 49,86 59,86"
                            fill="#0d3c13"
                        />

                        <polygon
                            points="134,76 129,86 139,86"
                            fill="#0d3c13"
                        />

                        <polygon
                            points="144,74 139,86 149,86"
                            fill="#0d3c13"
                        />

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

            <!-- TAI KHOAN VA CAC TAC VU -->

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
                            style="border-radius:22px;white-space:nowrap"
                        >
                            <i class="fa-solid fa-gauge"></i>
                            Quản trị
                        </a>

                    <?php endif; ?>

                    <div
                        class="vnn-account"
                        id="vnnAccount"
                    >

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

                            <!-- GIO HANG -->

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

                            <!-- TOUR CUA TOI -->

                            <a href="<?= vnnHeaderH($baseUrl) ?>tours/my-tours.php">

                                <span class="vnn-dropdown-label">
                                    <i class="fa-solid fa-ticket"></i>
                                    Tour Tôi Đã Đăng Ký
                                </span>

                            </a>

                            <!-- DON HANG -->

                            <a href="<?= vnnHeaderH($baseUrl) ?>shop/my-orders.php">

                                <span class="vnn-dropdown-label">
                                    <i class="fa-solid fa-box-open"></i>
                                    Đơn Mua Thiết Bị
                                </span>

                            </a>

                            <div class="vnn-dropdown-divider"></div>

                            <!-- THONG TIN CA NHAN -->

                            <a href="<?= vnnHeaderH($baseUrl) ?>auth/profile.php">

                                <span class="vnn-dropdown-label">
                                    <i class="fa-regular fa-id-card"></i>
                                    Thông Tin Cá Nhân
                                </span>

                            </a>

                            <div class="vnn-dropdown-divider"></div>

                            <!-- DANG XUAT -->

                            <a
                                href="<?= vnnHeaderH($baseUrl) ?>auth/logout.php"
                                class="vnn-logout"
                            >

                                <span class="vnn-dropdown-label">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                    Đăng Xuất
                                </span>

                            </a>

                        </div>

                    </div>

                <?php else: ?>

                    <!-- KHACH CHUA DANG NHAP -->

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
(function () {
    const account = document.getElementById('vnnAccount');
    const button = document.getElementById('vnnAccountButton');

    if (!account || !button) {
        return;
    }

    function setOpen(value) {
        account.classList.toggle('open', value);

        button.setAttribute(
            'aria-expanded',
            String(value)
        );
    }

    // Bam tai khoan de mo / dong menu.
    button.addEventListener('click', function (event) {
        event.stopPropagation();

        setOpen(
            !account.classList.contains('open')
        );
    });

    // Di chuot tren desktop van co the mo menu.
    account.addEventListener('mouseenter', function () {
        if (window.matchMedia('(hover: hover)').matches) {
            setOpen(true);
        }
    });

    account.addEventListener('mouseleave', function () {
        if (window.matchMedia('(hover: hover)').matches) {
            setOpen(false);
        }
    });

    // Bam ra ngoai thi dong.
    document.addEventListener('click', function (event) {
        if (!account.contains(event.target)) {
            setOpen(false);
        }
    });

    // Nhan Escape de dong.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
})();
</script>
