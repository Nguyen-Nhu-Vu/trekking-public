
<?php
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$current_page = 'system-dashboard';
$page_title = 'Dashboard Admin - VNN Travel';
$base = rtrim(BASE_URL, '/') . '/';

$links = [
    [
        'title' => 'Quản lý & Cấp quyền tài khoản',
        'detail' => 'Thêm, khóa, mở khóa và quản lý tài khoản theo quyền được cấp.',
        'icon' => 'fa-users-gear',
        'url' => $base.'admin/system.php?tab=users'
    ],
    [
        'title' => 'Lịch sử đăng nhập',
        'detail' => 'Theo dõi các phiên đăng nhập và thời điểm truy cập hệ thống.',
        'icon' => 'fa-clock-rotate-left',
        'url' => $base.'admin/system.php?tab=logins'
    ],
    [
        'title' => 'Lịch sử chỉnh sửa',
        'detail' => 'Kiểm tra những thay đổi đã được ghi lại trong hệ thống.',
        'icon' => 'fa-file-pen',
        'url' => $base.'admin/system.php?tab=edits'
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>assets/css/style.css">
<link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>assets/css/admin.css">
<style>
.vnn-admindash {
    background: #f2f6fb;
    min-height: 100vh;
    flex: 1;
    min-width: 0;
}
.vnn-admindash * {
    box-sizing: border-box;
}
.vnn-admindash-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 21px 30px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}
.vnn-admindash-head h1 {
    margin: 0;
    color: #0e2d52;
    font-size: 21px;
    font-weight: 800;
}
.vnn-admindash-logout {
    padding: 13px 18px;
    color: white;
    background: #ef4444;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    white-space: nowrap;
}
.vnn-admindash-logout:hover {
    background: #dc2626;
    color: #fff;
}
.vnn-admindash-content {
    padding: 27px 30px;
    max-width: 1700px;
    margin: auto;
}
.vnn-admindash-hero {
    border-radius: 20px;
    padding: 44px 38px;
    min-height: 235px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 16px;
    background:
        linear-gradient(
            90deg,
            rgba(8,25,53,.88),
            rgba(12,50,65,.68),
            rgba(16,93,57,.53)
        ),
        url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1400&q=80') center/cover;
    color: white;
}
.vnn-admindash-label {
    display: inline-block;
    padding: 9px 16px;
    background: #f97316;
    color: white;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 800;
}
.vnn-admindash-hero h2 {
    margin: 0;
    font-size: clamp(26px,3vw,38px);
    color: white;
    font-weight: 800;
}
.vnn-admindash-hero p {
    margin: 0;
    line-height: 1.7;
    font-size: 14px;
    color: #f8fafc;
    max-width: 830px;
}
.vnn-admindash-panel {
    background: white;
    border: 1px solid #dce6ef;
    border-radius: 18px;
    margin-top: 23px;
    padding: 25px;
}
.vnn-admindash-panel h3 {
    margin: 0 0 22px;
    font-size: 20px;
    font-weight: 800;
    color: #0e2d52;
}
.vnn-admindash-grid {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 15px;
}
.vnn-admindash-card {
    display: block;
    padding: 22px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 14px;
    text-decoration: none;
    transition: .2s;
    min-width: 0;
}
.vnn-admindash-card:hover {
    border-color: #93c5fd;
    transform: translateY(-2px);
    box-shadow: 0 7px 22px rgba(15,23,42,.06);
}
.vnn-admindash-icon {
    width: 39px;
    height: 39px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e0f2fe;
    color: #0369a1;
    margin-bottom: 14px;
}
.vnn-admindash-card strong {
    display: block;
    color: #0e2d52;
    margin-bottom: 9px;
    font-size: 16px;
}
.vnn-admindash-card span {
    display: block;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
}
@media(max-width:950px) {
    .vnn-admindash-grid {
        grid-template-columns: 1fr;
    }
    .vnn-admindash-head {
        padding: 17px;
    }
    .vnn-admindash-content {
        padding: 17px;
    }
    .vnn-admindash-hero {
        padding: 30px 24px;
    }
}
</style>
</head>

<body class="admin-body">

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main vnn-admindash">

<header class="vnn-admindash-head">
    <h1>HỆ THỐNG QUẢN LÝ VNN TRAVEL</h1>

    <a
        class="vnn-admindash-logout"
        href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>admin/logout.php"
    >
        <i class="fa-solid fa-right-from-bracket"></i>
        Đăng xuất
    </a>
</header>

<div class="vnn-admindash-content">

    <section class="vnn-admindash-hero">
        <span class="vnn-admindash-label">
            TRANG QUẢN TRỊ HỆ THỐNG
        </span>

        <h2>Chào mừng trở lại!</h2>

        <p>
            Quản lý tài khoản, phân quyền và theo dõi
            lịch sử hoạt động của VNN Travel.
        </p>
    </section>

    <section class="vnn-admindash-panel">
        <h3>
            <i class="fa-solid fa-table-cells-large"></i>
            Truy cập nhanh
        </h3>

        <div class="vnn-admindash-grid">

            <?php foreach ($links as $item): ?>

                <a
                    class="vnn-admindash-card"
                    href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                >
                    <div class="vnn-admindash-icon">
                        <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </div>

                    <strong>
                        <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($item['detail'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>

            <?php endforeach; ?>

        </div>
    </section>

</div>

</main>

</body>
</html>
