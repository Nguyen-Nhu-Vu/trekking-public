<?php
ob_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireManager();

if (session_status() === PHP_SESSION_NONE) session_start();

$current_page = 'posts';
$page_title = 'Thêm / Sửa bài viết - VNN Travel';

function postFormH($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function postFormJson(bool $ok, string $message): void {
    while (ob_get_level()) ob_end_clean();

    if (!headers_sent()) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

    echo json_encode(
        ['success' => $ok, 'message' => $message],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (empty($_SESSION['post_csrf'])) {
    $_SESSION['post_csrf'] = bin2hex(random_bytes(32));
}

$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? 0,
    FILTER_VALIDATE_INT
) ?: 0;

$isEdit = $id > 0;

$post = [
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'content' => '',
    'image' => '',
    'status' => 1
];

$record = null;
$error = '';

if ($isEdit) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM posts WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                postFormJson(false, 'Bài viết không tồn tại.');
            }
            http_response_code(404);
            exit('Bài viết không tồn tại.');
        }

        $post = array_merge($post, $record);

    } catch (Throwable $e) {
        error_log('Post load: ' . $e->getMessage());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            postFormJson(false, 'Không tải được bài viết.');
        }

        $error = 'Không tải được bài viết.';
    }
}

$imageDirectory = dirname(__DIR__, 2) . '/assets/images/posts';
$photos = [];

if (is_dir($imageDirectory)) {
    foreach (scandir($imageDirectory) ?: [] as $file) {
        if (
            preg_match('/\.(jpe?g|png|webp)$/i', $file) &&
            is_file($imageDirectory . '/' . $file)
        ) {
            $photos[] = [
                'name' => $file,
                'url' => '/assets/images/posts/' . rawurlencode($file)
            ];
        }
    }
}

usort(
    $photos,
    static fn($a, $b) => strnatcasecmp($a['name'], $b['name'])
);

$allowed = array_column($photos, 'url');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            (string)$_SESSION['post_csrf'],
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException('Phiên làm việc không hợp lệ.');
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $status = isset($_POST['status']) ? 1 : 0;
        $image = trim((string)($_POST['image'] ?? ''));

        if ($title === '' || mb_strlen($title) > 255) {
            throw new RuntimeException('Tiêu đề không hợp lệ.');
        }

        if ($content === '') {
            throw new RuntimeException('Vui lòng nhập nội dung bài viết.');
        }

        if ($slug === '') {
            $slug = mb_strtolower($title, 'UTF-8');
            $slug = preg_replace('/[\s_]+/u', '-', $slug);
            $slug = trim(
                preg_replace('/[^\pL\pN-]/u', '', $slug),
                '-'
            );
            $slug = mb_substr($slug, 0, 200);

            if ($slug === '') {
                $slug = 'bai-viet-' . date('YmdHis');
            }
        }

        if (mb_strlen($slug) > 255) {
            throw new RuntimeException('Đường dẫn bài viết quá dài.');
        }

        $oldImage = $isEdit
            ? (string)($record['image'] ?? '')
            : '';

        if ($image === '') $image = $oldImage;

        if (
            $image !== '' &&
            !in_array($image, $allowed, true) &&
            !($isEdit && $image === $oldImage)
        ) {
            throw new RuntimeException('Ảnh không nằm trong thư viện.');
        }

        if ($image === '') {
            throw new RuntimeException(
                'Vui lòng chọn ảnh bài viết từ thư viện.'
            );
        }

        $dupe = $pdo->prepare(
            'SELECT id FROM posts WHERE slug=? AND id<>? LIMIT 1'
        );
        $dupe->execute([$slug, $id]);

        if ($dupe->fetchColumn()) {
            throw new RuntimeException(
                'Đường dẫn bài viết đã có, hãy đổi slug.'
            );
        }

        if ($isEdit) {
            $stmt = $pdo->prepare(
                'UPDATE posts SET
                 title=?,slug=?,excerpt=?,content=?,image=?,status=?
                 WHERE id=?'
            );
            $stmt->execute([
                $title, $slug, $excerpt, $content,
                $image, $status, $id
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO posts
                 (title,slug,excerpt,content,image,status,views)
                 VALUES (?,?,?,?,?,?,0)'
            );
            $stmt->execute([
                $title, $slug, $excerpt, $content,
                $image, $status
            ]);
        }

        postFormJson(
            true,
            $isEdit
                ? 'Đã lưu thay đổi bài viết thành công.'
                : 'Đã thêm bài viết mới thành công.'
        );

    } catch (Throwable $e) {
        error_log('Post save: ' . $e->getMessage());

        postFormJson(
            false,
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể lưu bài viết.'
        );
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= postFormH($page_title) ?></title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
* { box-sizing:border-box; }
.po-page { min-height:100vh; flex:1; min-width:0; background:#f1f5f9; padding:24px; color:#102e52; font-size:14px; }
.po-panel,.po-header { max-width:1100px; margin:0 auto 18px; background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; }
.po-header { display:flex; align-items:center; justify-content:space-between; gap:16px; }
.po-header h1 { margin:0; font-size:20px; }
.po-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.po-field { margin-bottom:18px; }
.po-field.full { grid-column:1/-1; }
.po-field label { display:block; font-weight:750; margin-bottom:8px; }
.po-input { display:block; width:100%; border:1px solid #cbd5e1; border-radius:9px; background:#fff; color:#163455; padding:12px; font:inherit; font-size:13px; }
.po-input:focus { outline:2px solid #bbf7d0; }
textarea.po-input { min-height:140px; line-height:1.65; resize:vertical; }
.po-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:11px 16px; border:0; border-radius:9px; background:#166534; color:#fff!important; text-decoration:none; font-size:13px; font-weight:750; cursor:pointer; }
.po-btn.gray { background:#e2e8f0; color:#334155!important; }
.po-btn.blue { background:#1d4ed8; }
.po-btn:disabled { opacity:.6; }
.po-gallery { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
.po-photo { display:block; border:2px solid #e2e8f0; border-radius:10px; overflow:hidden; cursor:pointer; position:relative; }
.po-photo[hidden] { display:none!important; }
.po-photo:has(input:checked) { border-color:#166534; }
.po-photo input { position:absolute; opacity:0; width:1px; }
.po-photo img { display:block; width:100%; height:145px; object-fit:cover; }
.po-photo span { display:block; padding:10px; font-size:12px; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.po-tools { display:flex; gap:10px; align-items:center; margin-bottom:15px; }
.po-tools input { flex:1; }
.po-muted { color:#64748b; font-size:12px; }
.po-actions { display:flex; gap:12px; align-items:center; margin-top:23px; }
.po-overlay { position:fixed; inset:0; z-index:999999; background:#0f172a99; display:flex; align-items:center; justify-content:center; padding:15px; }
.po-overlay[hidden] { display:none!important; }
.po-modal { width:100%; max-width:420px; background:#fff; border-radius:16px; text-align:center; padding:28px; box-shadow:0 20px 65px #0003; }
.po-modal h2 { font-size:21px; }
.po-modal p { color:#64748b; line-height:1.7; }
.po-current { width:180px; max-height:130px; object-fit:cover; border-radius:8px; }
@media(max-width:700px) {
    .po-page { padding:12px; }
    .po-panel,.po-header { padding:15px; }
    .po-grid { grid-template-columns:1fr; }
    .po-gallery { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
</style>
</head>

<body class="admin-body">
<?php require_once __DIR__.'/../includes/sidebar.php'; ?>

<main class="admin-main po-page">
    <div class="po-header">
        <h1>
            <i class="fa-solid fa-pen-to-square"></i>
            <?= $isEdit ? 'CHỈNH SỬA BÀI VIẾT' : 'THÊM BÀI VIẾT MỚI' ?>
        </h1>
        <a href="index.php" class="po-btn gray">Quay lại</a>
    </div>

    <section class="po-panel">
        <form id="postForm" method="POST">
            <input type="hidden" name="csrf"
                   value="<?= postFormH($_SESSION['post_csrf']) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="po-grid">
                <div class="po-field full">
                    <label>Tiêu đề bài viết *</label>
                    <input class="po-input" name="title" required
                           maxlength="255"
                           value="<?= postFormH($post['title']) ?>">
                </div>

                <div class="po-field full">
                    <label>Đường dẫn bài viết (slug, để trống sẽ tự tạo)</label>
                    <input class="po-input" name="slug" maxlength="255"
                           value="<?= postFormH($post['slug']) ?>">
                </div>

                <div class="po-field full">
                    <label>Mô tả ngắn</label>
                    <textarea class="po-input" name="excerpt"
                              rows="3"><?= postFormH($post['excerpt']) ?></textarea>
                </div>

                <div class="po-field full">
                    <label>Nội dung bài viết (hỗ trợ HTML cơ bản) *</label>
                    <textarea class="po-input" name="content"
                              rows="14" required><?= postFormH($post['content']) ?></textarea>
                    <p class="po-muted">
                        Nội dung của bài được trang khách hiển thị dưới
                        dạng HTML. Chỉ nhập HTML từ nguồn tin cậy,
                        không chèn script.
                    </p>
                </div>
            </div>

            <hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0">

            <h2 style="font-size:18px">Ảnh đại diện bài viết</h2>
            <p class="po-muted">
                Đưa ảnh vào assets/images/posts/ trên GitHub trước.
                Mặc định chỉ hiện 6 ảnh, có tìm theo tên tệp.
            </p>

            <?php if ($isEdit && $post['image']): ?>
                <p>Ảnh hiện tại</p>
                <img class="po-current"
                     src="<?= postFormH($post['image']) ?>"
                     alt="Ảnh hiện tại">
            <?php endif; ?>

            <?php if ($photos): ?>
                <div class="po-tools">
                    <input class="po-input" type="search"
                           id="photoSearch" placeholder="Tìm tên ảnh...">
                    <button type="button" class="po-btn blue"
                            id="showAllPhotos">Xem tất cả</button>
                </div>

                <div class="po-gallery" id="photos">
                    <?php foreach ($photos as $image): ?>
                        <label class="po-photo"
                               data-name="<?= postFormH($image['name']) ?>">
                            <input type="radio" name="image"
                                   value="<?= postFormH($image['url']) ?>"
                                   <?= $post['image'] === $image['url']
                                       ? 'checked' : '' ?>>
                            <img loading="lazy"
                                 src="<?= postFormH($image['url']) ?>"
                                 alt="<?= postFormH($image['name']) ?>">
                            <span><?= postFormH($image['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p class="po-muted" id="photoCount"></p>
            <?php else: ?>
                <p class="po-muted">
                    Chưa có ảnh trong thư viện. Nếu đang sửa,
                    bạn có thể giữ ảnh hiện tại.
                </p>
            <?php endif; ?>

            <p style="background:#f0fdf4;padding:12px;border-radius:8px;color:#166534">
                Ảnh đã chọn:
                <strong id="chosenImage">
                    <?= postFormH(
                        $post['image']
                            ? basename(rawurldecode($post['image']))
                            : 'Chưa chọn ảnh'
                    ) ?>
                </strong>
            </p>

            <label style="display:flex;gap:10px;align-items:center;margin-top:20px">
                <input type="checkbox" name="status" value="1"
                       <?= (int)$post['status'] === 1 ? 'checked' : '' ?>>
                Xuất bản trên website
            </label>

            <div class="po-actions">
                <button class="po-btn" type="submit" id="saveBtn">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $isEdit ? 'Lưu thay đổi' : 'Thêm bài viết' ?>
                </button>
                <a class="po-btn gray" href="index.php">Hủy</a>
            </div>
        </form>
    </section>
</main>

<div class="po-overlay" id="popup" hidden>
    <div class="po-modal" role="dialog" aria-modal="true">
        <h2 id="popupTitle">Thành công!</h2>
        <p id="popupText"></p>
        <button class="po-btn" type="button" id="popupOK">
            Đồng ý
        </button>
    </div>
</div>

<script>
(() => {
    const search = document.getElementById('photoSearch');
    const toggle = document.getElementById('showAllPhotos');
    const list = document.getElementById('photos');
    const cards = list
        ? [...list.querySelectorAll('.po-photo')]
        : [];
    const count = document.getElementById('photoCount');
    const chosen = document.getElementById('chosenImage');

    let all = false;

    function draw() {
        const key = (search?.value || '')
            .toLowerCase().normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        const matches = cards.filter(c =>
            c.dataset.name.toLowerCase().normalize('NFD')
             .replace(/[\u0300-\u036f]/g, '')
             .includes(key)
        );

        const visible = key || all ? matches : matches.slice(0, 6);

        cards.forEach(c => c.hidden = !visible.includes(c));

        if (count) {
            count.textContent =
                `Hiển thị ${visible.length}/${matches.length} ảnh`;
        }

        if (toggle) {
            toggle.textContent = all ? 'Thu gọn' : 'Xem tất cả';
            toggle.hidden = !!key;
        }
    }

    search?.addEventListener('input', draw);
    toggle?.addEventListener('click', () => {
        all = !all;
        draw();
    });

    list?.querySelectorAll('input[name="image"]').forEach(r =>
        r.addEventListener('change', () => {
            chosen.textContent =
                decodeURIComponent(r.value.split('/').pop());
        })
    );
    draw();

    const form = document.getElementById('postForm');
    const btn = document.getElementById('saveBtn');
    const popup = document.getElementById('popup');

    let successful = false;
    let busy = false;

    function message(ok, text) {
        successful = ok;
        document.getElementById('popupTitle').textContent =
            ok ? 'Thành công!' : 'Không thành công';
        document.getElementById('popupText').textContent = text;
        popup.hidden = false;
    }

    document.getElementById('popupOK').onclick = () => {
        if (successful) {
            location.href = 'index.php';
        } else {
            popup.hidden = true;
        }
    };

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (busy || !form.reportValidity()) return;

        busy = true;
        btn.disabled = true;

        try {
            const response = await fetch(location.href, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const txt = await response.text();
            let data;

            try {
                data = JSON.parse(txt);
            } catch (_) {
                throw new Error(
                    'Máy chủ không trả về JSON hợp lệ. ' +
                    'Kiểm tra PHP và đăng nhập.'
                );
            }

            message(
                !!data.success,
                data.message || 'Không có thông báo.'
            );
        } catch (err) {
            message(
                false,
                err.message || 'Không kết nối được máy chủ.'
            );
        } finally {
            btn.disabled = false;
            busy = false;
        }
    });
})();
</script>
</body>
</html>
