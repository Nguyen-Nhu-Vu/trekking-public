<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$cart_count = getCartCount();
$active_menu = $active_menu ?? '';
$page_title = $page_title ?? 'VNN Travel - Khám Phá & Chinh Phục Đỉnh Cao';
$baseUrl = rtrim(BASE_URL, '/') . '/';

function vnnHeaderH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">

    <title><?= vnnHeaderH($page_title) ?></title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet"
          href="<?= vnnHeaderH($baseUrl) ?>assets/css/style.css">

    <link rel="stylesheet"
          href="<?= vnnHeaderH($baseUrl) ?>assets/css/responsive.css">

    <style>
        .vnn-brand-container {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .vnn-mountain-left {
            width: 72px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vnn-mountain-left svg {
            width: 100%;
            height: 100%;
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
            font-size: 28px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: .5px;
        }

        .vnn-travel-row {
            color: #1e293b;
            font-weight: 900;
            font-size: 13px;
            letter-spacing: 4px;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 82px;
            gap: 18px;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-menu li {
            list-style: none;
        }

        .nav-link {
            color: #1e293b;
            text-decoration: none;
            font-weight: 800;
            font-size: 13px;
            white-space: nowrap;
            display: inline-block;
            position: relative;
            padding: 9px 0;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #ea580c;
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            height: 3px;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 3px;
            background: #ea580c;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
        }

        .user-dropdown-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .user-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            min-width: 220px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 0;
            box-shadow: 0 12px 30px rgba(0,0,0,.16);
            z-index: 99999;
        }

        .user-dropdown-wrapper:hover .user-dropdown-menu,
        .user-dropdown-wrapper:focus-within .user-dropdown-menu {
            display: block;
        }

        .user-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #334155;
            text-decoration: none;
            padding: 12px 17px;
            font-size: 13px;
            font-weight: 700;
        }

        .user-dropdown-menu a:hover {
            background: #f8fafc;
            color: #ea580c;
        }

        .user-dropdown-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 6px 0;
        }

        @media(max-width:1250px) {
            .navbar {
                flex-wrap: wrap;
                justify-content: center;
                padding: 15px 0;
            }

            .nav-menu {
                order: 3;
                width: 100%;
                gap: 18px;
            }
        }

        @media(max-width:650px) {
            .navbar {
                gap: 13px;
            }

            .nav-menu {
                gap: 10px 15px;
            }

            .nav-link {
                font-size: 12px;
            }

            .nav-actions {
                justify-content: center;
            }
        }
    </style>
</head>

<body>

<header class="site-header">

    <div class="container">

        <nav class="navbar" aria-label="Điều hướng chính">

            <a href="<?= vnnHeaderH($baseUrl) ?>index.php"
               class="vnn-brand-container"
               aria-label="VNN Travel - Trang chủ">

                <div class="vnn-mountain-left">
                    <svg viewBox="0 0 180 100"
                         xmlns="http://www.w3.org/2000/svg"
                         role="img"
                         aria-label="Biểu tượng núi VNN">
                        <polygon points="50,42 22,86 82,86"
                                 fill="#58a82d"/>
                        <polygon points="50,42 58,56 44,56"
                                 fill="#ffffff"/>

                        <polygon points="95,8 42,94 150,94"
                                 fill="#4d9e26"/>
                        <polygon points="95,8 116,40 105,36 95,48 85,36 76,42"
                                 fill="#ffffff"/>
                        <path d="M95,48 L108,66 L99,62 L92,76 L82,64 Z"
                              fill="#ffffff"
                              opacity="0.95"/>

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

            <ul class="nav-menu">

                <?php
                $menus = [
                    ['home', 'index.php', 'TRANG CHỦ'],
                    ['tour', 'tours/tour.php', 'TOUR TREKKING'],
                    ['leaders', 'pages/leaders.php', 'HƯỚNG DẪN VIÊN'],
                    ['location', 'pages/locations.php', 'ĐỊA ĐIỂM'],
                    ['shop', 'shop/shop.php', 'CỬA HÀNG'],
                    ['blog', 'pages/blog.php', 'BÀI VIẾT'],
                    ['contact', 'pages/contact.php', 'LIÊN HỆ']
                ];
                ?>

                <?php foreach ($menus as $menu): ?>

                    <li>
                        <a
                            href="<?= vnnHeaderH($baseUrl . $menu[1]) ?>"
                            class="nav-link <?= $active_menu === $menu[0]
                                ? 'active' : '' ?>"
                        >
                            <?= vnnHeaderH($menu[2]) ?>
                        </a>
                    </li>

                <?php endforeach; ?>

            </ul>

            <div class="nav-actions">

                <a href="<?= vnnHeaderH($baseUrl) ?>shop/cart.php"
                   class="btn btn-outline btn-sm"
                   style="position:relative;border-radius:20px">

                    <i class="fa-solid fa-cart-shopping"></i>
                    Giỏ Hàng

                    <?php if ($cart_count > 0): ?>
                        <span class="badge"
                              style="position:absolute;top:-8px;right:-8px">
                            <?= (int)$cart_count ?>
                        </span>
                    <?php endif; ?>

                </a>

                <?php if (isLoggedIn()): ?>

                    <?php if (in_array(
                        getRole(),
                        ['admin', 'manager', 'staff'],
                        true
                    )): ?>

                        <a href="<?= vnnHeaderH($baseUrl) ?>admin/index.php"
                           class="btn btn-accent btn-sm"
                           style="border-radius:20px">
                            <i class="fa-solid fa-gauge"></i>
                            Quản Trị
                        </a>

                    <?php endif; ?>

                    <div class="user-dropdown-wrapper">

                        <a href="<?= vnnHeaderH($baseUrl) ?>auth/profile.php"
                           class="btn btn-outline btn-sm"
                           style="border-radius:20px"
                           aria-haspopup="true">
                            <i class="fa-regular fa-user"></i>
                            Tài Khoản
                            <i class="fa-solid fa-chevron-down"></i>
                        </a>

                        <div class="user-dropdown-menu">

                            <a href="<?= vnnHeaderH($baseUrl) ?>tours/my-tours.php"
                               style="color:#ea580c">
                                <i class="fa-solid fa-ticket"></i>
                                Tour Tôi Đã Đăng Ký
                            </a>

                            <a href="<?= vnnHeaderH($baseUrl) ?>shop/my-orders.php">
                                <i class="fa-solid fa-box-open"></i>
                                Đơn Mua Thiết Bị
                            </a>

                            <div class="user-dropdown-divider"></div>

                            <a href="<?= vnnHeaderH($baseUrl) ?>auth/profile.php">
                                <i class="fa-regular fa-id-card"></i>
                                Thông Tin Cá Nhân
                            </a>

                            <a href="<?= vnnHeaderH($baseUrl) ?>auth/logout.php"
                               style="color:#dc2626">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                Đăng Xuất
                            </a>

                        </div>
                    </div>

                    <a href="<?= vnnHeaderH($baseUrl) ?>auth/logout.php"
                       class="btn btn-danger btn-sm"
                       title="Đăng xuất"
                       style="border-radius:20px">

                        <i class="fa-solid fa-right-from-bracket"></i>

                    </a>

                <?php else: ?>

                    <a href="<?= vnnHeaderH($baseUrl) ?>auth/login.php"
                       class="btn btn-primary btn-sm"
                       style="border-radius:20px">
                        Đăng Nhập
                    </a>

                    <a href="<?= vnnHeaderH($baseUrl) ?>auth/register.php"
                       class="btn btn-accent btn-sm"
                       style="border-radius:20px">
                        Đăng Ký
                    </a>

                <?php endif; ?>

            </div>

        </nav>

    </div>

</header>
