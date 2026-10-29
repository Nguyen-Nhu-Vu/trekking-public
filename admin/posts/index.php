<?php
ob_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireManager();

if (session_status() === PHP_SESSION_NONE) session_start();

$current_page = 'posts';
$page_title = 'Quản lý bài viết - VNN Travel';

function postListH($x): string {
    return htmlspecialchars((string)($x ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['post_csrf'])) {
    $_SESSION['post_csrf'] = bin2hex(random_bytes(32));
}

$message = $_SESSION['post_notice'] ?? '';
unset($_SESSION['post_notice']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            $_SESSION['post_csrf'],
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException('Phiên làm việc hết hạn.');
        }

        if (($_POST['action'] ?? '') !== 'delete') {
            throw new RuntimeException('Thao tác không hợp lệ.');
        }

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            throw new RuntimeException('Mã bài viết không hợp lệ.');
        }

        $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute([$id]);

        $message = $stmt->rowCount()
            ? 'Đã xóa bài viết thành công.'
            : 'Bài viết không tồn tại.';

    } catch (Throwable $e) {
        error_log('Post delete: ' . $e->getMessage());
        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể xóa bài viết.';
    }
}

$posts = [];

try {
    $posts = $pdo->query(
        'SELECT id,title,image,views,status,created_at
         FROM posts ORDER BY id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Post listing: ' . $e->getMessage());
    $error = 'Không tải được danh sách bài viết.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= postListH($page_title) ?></title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
* { box-sizing: border-box; }
.pl-page { padding:24px; background:#f1f5f9; min-height:100vh; flex:1; min-width:0; color:#102e52; font-size:14px; }
.pl-head,.pl-panel { background:#fff; border:1px solid #dfe6f0; border-radius:14px; margin:0 auto 18px; max-width:1400px; }
.pl-head { padding:23px; display:flex; align-items:center; justify-content:space-between; gap:15px; flex-wrap:wrap; }
.pl-head h1 { margin:0; font-size:20px; }
.pl-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:10px 14px; border:0; border-radius:8px; background:#166534; color:#fff!important; font-weight:750; font-size:13px; text-decoration:none; cursor:pointer; }
.pl-btn.blue { background:#1d4ed8; }
.pl-btn.red { background:#dc2626; }
.pl-btn.gray { background:#e2e8f0; color:#334155!important; }
.pl-tablewrap { overflow-x:auto; }
.pl-table { border-collapse:collapse; width:100%; min-width:800px; }
.pl-table th,.pl-table td { padding:15px; text-align:left; border-bottom:1px solid #e2e8f0; font-size:13px; vertical-align:middle; }
.pl-table th { background:#f8fafc; }
.pl-table img { width:75px; height:55px; object-fit:cover; border-radius:8px; }
.pl-actions { display:flex; align-items:center; gap:8px; }
.pl-actions form { margin:0; }
.pl-badge { display:inline-block; padding:6px 10px; border-radius:30px; background:#dcfce7; color:#166534; font-weight:700; }
.pl-badge.draft { background:#e2e8f0; color:#475569; }
.pl-overlay { position:fixed; inset:0; z-index:999999; background:#0f172a99; display:flex; align-items:center; justify-content:center; padding:18px; }
.pl-overlay[hidden] { display:none!important; }
.pl-modal { background:#fff; padding:28px; border-radius:16px; text-align:center; max-width:410px; width:100%; box-shadow:0 20px 60px #0002; }
.pl-modal h2 { font-size:21px; }
.pl-modal p { line-height:1.6; color:#64748b; }
.pl-modal .pl-btn { margin:5px; }
@media(max-width:600px) { .pl-page {padding:12px;} .pl-head {padding:15px;} }
</style>
</head>

<body class="admin-body">
<?php require_once __DIR__.'/../includes/sidebar.php'; ?>

<main class="admin-main pl-page">
    <div class="pl-head">
        <h1>
            <i class="fa-regular fa-newspaper"></i>
            QUẢN LÝ BÀI VIẾT & CẨM NANG
        </h1>
        <a class="pl-btn" href="form.php">
            <i class="fa-solid fa-plus"></i> Thêm bài viết
        </a>
    </div>

    <section class="pl-panel">
        <div class="pl-tablewrap">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Tiêu đề bài viết</th>
                        <th>Lượt xem</th>
                        <th>Ngày đăng</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $p): ?>
                    <tr>
                        <td>
                            <img src="<?= postListH($p['image']) ?>"
                                 alt="Ảnh bài viết">
                        </td>
                        <td>
                            <strong><?= postListH($p['title']) ?></strong>
                        </td>
                        <td><?= number_format((int)$p['views']) ?></td>
                        <td>
                            <?= postListH(
                                date('d/m/Y', strtotime($p['created_at']))
                            ) ?>
                        </td>
                        <td>
                            <span class="pl-badge <?= ((int)$p['status'] === 1 ? '' : 'draft') ?>">
                                <?= ((int)$p['status'] === 1 ? 'Xuất bản' : 'Bản nháp') ?>
                            </span>
                        </td>
                        <td>
                            <div class="pl-actions">
                                <a class="pl-btn blue"
                                   href="form.php?id=<?= (int)$p['id'] ?>">
                                    <i class="fa-solid fa-pen"></i> Sửa
                                </a>

                                <form method="POST" class="post-delete">
                                    <input type="hidden" name="csrf"
                                           value="<?= postListH($_SESSION['post_csrf']) ?>">
                                    <input type="hidden" name="id"
                                           value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="action"
                                           value="delete">
                                    <button class="pl-btn red" type="submit">
                                        <i class="fa-solid fa-trash"></i> Xóa
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$posts): ?>
                    <tr>
                        <td colspan="6"
                            style="text-align:center;padding:30px">
                            Chưa có bài viết nào.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="pl-overlay" id="notice"
     <?= (!$message && !$error ? 'hidden' : '') ?>>
    <div class="pl-modal" role="dialog" aria-modal="true">
        <h2 id="noticeTitle">
            <?= $error ? 'Không thành công' : 'Thành công!' ?>
        </h2>
        <p id="noticeText">
            <?= postListH($error ?: $message) ?>
        </p>
        <button type="button" class="pl-btn" id="noticeOK">
            Đồng ý
        </button>
    </div>
</div>

<div class="pl-overlay" id="confirmDelete" hidden>
    <div class="pl-modal" role="dialog" aria-modal="true">
        <h2>Xóa bài viết?</h2>
        <p>Bài viết sẽ bị xóa khỏi website.</p>
        <button class="pl-btn gray" type="button"
                id="cancelDelete">Quay lại</button>
        <button class="pl-btn red" type="button"
                id="yesDelete">Xác nhận xóa</button>
    </div>
</div>

<script>
(() => {
    const notice = document.getElementById('notice');
    const confirm = document.getElementById('confirmDelete');

    document.getElementById('noticeOK').onclick =
        () => notice.hidden = true;

    let chosen = null;

    document.querySelectorAll('.post-delete').forEach(form =>
        form.addEventListener('submit', e => {
            e.preventDefault();
            chosen = form;
            confirm.hidden = false;
        })
    );

    document.getElementById('cancelDelete').onclick = () => {
        chosen = null;
        confirm.hidden = true;
    };

    document.getElementById('yesDelete').onclick = () => {
        if (chosen) chosen.submit();
    };
})();
</script>
</body>
</html>
