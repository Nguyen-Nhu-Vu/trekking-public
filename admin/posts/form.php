
<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = 'posts';
$page_title = 'Thêm / Sửa bài viết - VNN Travel';

if (!function_exists('postFormH')) {
    function postFormH($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('postFormJson')) {
    function postFormJson(bool $success, string $message): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            http_response_code($success ? 200 : 422);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        echo json_encode([
            'success' => $success,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}

if (!function_exists('postValidImageUrl')) {
    function postValidImageUrl(string $url): bool {
        if (
            $url === '' ||
            strlen($url) > 2048 ||
            preg_match('/[\x00-\x20\x7f]/', $url)
        ) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (
            !in_array($scheme, ['http', 'https'], true) ||
            $host === '' ||
            isset($parts['user']) ||
            isset($parts['pass'])
        ) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

/* ==========================================
   CSRF
   ========================================== */

if (empty($_SESSION['post_csrf'])) {
    $_SESSION['post_csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string)$_SESSION['post_csrf'];

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

/* ==========================================
   TAI BAI VIET KHI CHINH SUA
   ========================================== */

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM posts
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                postFormJson(false, 'Bài viết không tồn tại.');
            }

            http_response_code(404);
            exit('Không tìm thấy bài viết.');
        }

        $post = array_merge($post, $record);

    } catch (Throwable $e) {
        error_log('Post load: ' . $e->getMessage());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            postFormJson(false, 'Không thể tải bài viết.');
        }

        $error = 'Không thể tải thông tin bài viết.';
    }
}

/* ==========================================
   LUU THEM / SUA BAI VIET
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            $csrf,
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
            );
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $image = trim((string)($_POST['image'] ?? ''));
        $status = isset($_POST['status']) ? 1 : 0;

        if ($title === '' || mb_strlen($title) > 255) {
            throw new RuntimeException(
                'Tiêu đề bài viết không hợp lệ.'
            );
        }

        if ($content === '') {
            throw new RuntimeException(
                'Vui lòng nhập nội dung bài viết.'
            );
        }

        /* KIEM TRA URL ANH */

        if ($image === '') {
            throw new RuntimeException(
                'Vui lòng nhập URL ảnh đại diện.'
            );
        }

        if (!postValidImageUrl($image)) {
            throw new RuntimeException(
                'URL ảnh không hợp lệ. Hãy sử dụng liên kết http:// hoặc https://.'
            );
        }

        /* TU DONG TAO SLUG NEU DE TRONG */

        if ($slug === '') {
            $slug = mb_strtolower($title, 'UTF-8');

            $slug = preg_replace(
                '/[\s_]+/u',
                '-',
                $slug
            );

            $slug = preg_replace(
                '/[^\pL\pN-]/u',
                '',
                $slug
            );

            $slug = trim($slug, '-');
            $slug = mb_substr($slug, 0, 200);

            if ($slug === '') {
                $slug = 'bai-viet-' . bin2hex(random_bytes(5));
            }
        }

        if (mb_strlen($slug) > 255) {
            throw new RuntimeException(
                'Đường dẫn bài viết quá dài.'
            );
        }

        /* KIEM TRA SLUG TRUNG */

        $checkSlug = $pdo->prepare("
            SELECT id
            FROM posts
            WHERE slug = ?
              AND id <> ?
            LIMIT 1
        ");

        $checkSlug->execute([$slug, $id]);

        if ($checkSlug->fetchColumn()) {
            throw new RuntimeException(
                'Đường dẫn bài viết đã tồn tại. Hãy nhập slug khác.'
            );
        }

        /* CAP NHAT BAI VIET */

        if ($isEdit) {
            $stmt = $pdo->prepare("
                UPDATE posts
                SET
                    title = ?,
                    slug = ?,
                    excerpt = ?,
                    content = ?,
                    image = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $title,
                $slug,
                $excerpt,
                $content,
                $image,
                $status,
                $id
            ]);

            postFormJson(
                true,
                'Đã lưu thay đổi bài viết thành công.'
            );
        }

        /* THEM BAI VIET MOI */

        $stmt = $pdo->prepare("
            INSERT INTO posts (
                title,
                slug,
                excerpt,
                content,
                image,
                status,
                views
            )
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");

        $stmt->execute([
            $title,
            $slug,
            $excerpt,
            $content,
            $image,
            $status
        ]);

        postFormJson(
            true,
            'Đã thêm bài viết mới thành công.'
        );

    } catch (Throwable $e) {
        error_log('Post save: ' . $e->getMessage());

        postFormJson(
            false,
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể lưu bài viết. Vui lòng thử lại.'
        );
    }
}

$currentImage = (string)($post['image'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= postFormH($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
* {
    box-sizing: border-box;
}

.po-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    background: #f1f5f9;
    padding: 24px;
    color: #102e52;
    font-size: 14px;
}

.po-header,
.po-panel {
    max-width: 1100px;
    margin: 0 auto 18px;
    padding: 24px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

.po-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.po-header h1 {
    margin: 0;
    color: #102e52;
    font-size: 20px;
    font-weight: 800;
}

.po-section {
    margin-bottom: 28px;
}

.po-section h2 {
    margin: 0 0 18px;
    color: #102e52;
    font-size: 18px;
}

.po-field {
    margin-bottom: 20px;
}

.po-field label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 800;
    color: #173454;
}

.po-input {
    display: block;
    width: 100%;
    min-height: 44px;
    padding: 12px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font: inherit;
    font-size: 13px;
    color: #173454;
    background: #fff;
}

.po-input:focus {
    outline: none;
    border-color: #166534;
    box-shadow: 0 0 0 3px rgba(22,101,52,.1);
}

textarea.po-input {
    min-height: 135px;
    resize: vertical;
    line-height: 1.7;
}

.po-note {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.8;
}

.po-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 43px;
    padding: 11px 17px;
    border: none;
    border-radius: 9px;
    background: #166534;
    color: white !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.po-btn:hover {
    background: #14532d;
}

.po-btn.gray {
    background: #e2e8f0;
    color: #334155 !important;
}

.po-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.po-divider {
    height: 1px;
    margin: 25px 0;
    border: 0;
    background: #e2e8f0;
}

.po-image-box {
    padding: 20px;
    border: 1px solid #dbe5ee;
    border-radius: 12px;
    background: #f8fafc;
}

.po-url-row {
    display: flex;
    gap: 10px;
    align-items: center;
}

.po-url-row .po-input {
    flex: 1;
    min-width: 0;
}

.po-preview {
    margin-top: 17px;
    padding: 17px;
    border: 1px dashed #cbd5e1;
    border-radius: 11px;
    background: white;
    text-align: center;
}

.po-preview img {
    display: block;
    width: 100%;
    max-width: 450px;
    max-height: 280px;
    margin: 0 auto;
    border-radius: 10px;
    object-fit: contain;
    background: #f1f5f9;
}

.po-preview-empty {
    padding: 40px 12px;
    color: #94a3b8;
}

.po-preview-empty i {
    display: block;
    font-size: 35px;
    margin-bottom: 12px;
}

.po-preview-message {
    margin-top: 12px;
    color: #64748b;
    font-size: 12px;
}

.po-status {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 700;
}

.po-status input {
    width: 18px;
    height: 18px;
    accent-color: #166534;
}

.po-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 24px;
}

.po-error {
    padding: 14px;
    margin-bottom: 18px;
    border-radius: 9px;
    background: #fee2e2;
    color: #991b1b;
}

.po-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15,23,42,.6);
}

.po-overlay[hidden] {
    display: none !important;
}

.po-modal {
    width: 100%;
    max-width: 420px;
    padding: 30px 25px;
    border-radius: 17px;
    background: #fff;
    text-align: center;
    box-shadow: 0 25px 70px rgba(0,0,0,.2);
}

.po-modal-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 68px;
    height: 68px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: #dcfce7;
    color: #166534;
    font-size: 30px;
}

.po-modal-icon.error {
    color: #dc2626;
    background: #fee2e2;
}

.po-modal h2 {
    margin: 0 0 10px;
    font-size: 21px;
    color: #102e52;
}

.po-modal p {
    margin: 0 0 22px;
    color: #64748b;
    line-height: 1.7;
}

@media(max-width:700px) {
    .po-page {
        padding: 12px;
    }

    .po-header,
    .po-panel {
        padding: 15px;
    }

    .po-url-row {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main po-page">

    <div class="po-header">
        <h1>
            <i class="fa-solid fa-pen-to-square"></i>
            <?= $isEdit
                ? 'CHỈNH SỬA BÀI VIẾT'
                : 'THÊM BÀI VIẾT MỚI' ?>
        </h1>

        <a href="index.php" class="po-btn gray">
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại danh sách
        </a>
    </div>

    <section class="po-panel">

        <?php if ($error): ?>
            <div class="po-error">
                <?= postFormH($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="postForm">

            <input
                type="hidden"
                name="csrf"
                value="<?= postFormH($csrf) ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$id ?>"
            >

            <section class="po-section">

                <h2>Thông tin bài viết</h2>

                <div class="po-field">
                    <label for="postTitle">
                        Tiêu đề bài viết *
                    </label>

                    <input
                        class="po-input"
                        type="text"
                        name="title"
                        id="postTitle"
                        maxlength="255"
                        required
                        value="<?= postFormH($post['title']) ?>"
                        placeholder="Nhập tiêu đề bài viết..."
                    >
                </div>

                <div class="po-field">
                    <label for="postSlug">
                        Đường dẫn bài viết (Slug)
                    </label>

                    <input
                        class="po-input"
                        type="text"
                        name="slug"
                        id="postSlug"
                        maxlength="255"
                        value="<?= postFormH($post['slug']) ?>"
                        placeholder="Để trống để tự tạo từ tiêu đề"
                    >

                    <p class="po-note">
                        Slug được sử dụng trong đường dẫn bài viết.
                        Nếu để trống, hệ thống sẽ tự tạo.
                    </p>
                </div>

                <div class="po-field">
                    <label for="postExcerpt">
                        Mô tả ngắn
                    </label>

                    <textarea
                        class="po-input"
                        name="excerpt"
                        id="postExcerpt"
                        rows="3"
                        placeholder="Giới thiệu ngắn về bài viết..."
                    ><?= postFormH($post['excerpt']) ?></textarea>
                </div>

                <div class="po-field">
                    <label for="postContent">
                        Nội dung bài viết *
                    </label>

                    <textarea
                        class="po-input"
                        name="content"
                        id="postContent"
                        rows="15"
                        required
                        placeholder="Nhập nội dung bài viết..."
                    ><?= postFormH($post['content']) ?></textarea>

                    <p class="po-note">
                        Có thể nhập nội dung HTML nếu cần định dạng
                        bài viết. Chỉ sử dụng HTML đáng tin cậy,
                        không chèn mã JavaScript.
                    </p>
                </div>

            </section>

            <hr class="po-divider">

            <!-- =================================
                 ANH DAI DIEN BANG URL
                 ================================= -->

            <section class="po-section">

                <h2>
                    <i class="fa-regular fa-image"></i>
                    Ảnh đại diện bài viết
                </h2>

                <div class="po-image-box">

                    <div class="po-field">

                        <label for="postImage">
                            URL ảnh đại diện *
                        </label>

                        <div class="po-url-row">

                            <input
                                class="po-input"
                                type="url"
                                name="image"
                                id="postImage"
                                required
                                maxlength="2048"
                                value="<?= postFormH($currentImage) ?>"
                                placeholder="https://example.com/hinh-anh.jpg"
                                autocomplete="off"
                            >

                            <button
                                type="button"
                                class="po-btn"
                                id="previewButton"
                            >
                                <i class="fa-regular fa-eye"></i>
                                Xem trước
                            </button>

                        </div>

                        <p class="po-note">
                            Dán đường dẫn trực tiếp đến ảnh.
                            Nên dùng URL HTTPS của ảnh JPG,
                            PNG hoặc WebP từ nguồn cho phép
                            hiển thị trên website khác.
                        </p>

                    </div>

                    <div class="po-preview" id="imagePreviewBox">

                        <img
                            id="imagePreview"
                            src=""
                            alt="Xem trước ảnh đại diện"
                            hidden
                        >

                        <div
                            class="po-preview-empty"
                            id="previewEmpty"
                        >
                            <i class="fa-regular fa-image"></i>
                            Dán URL để xem trước ảnh bài viết.
                        </div>

                        <div
                            class="po-preview-message"
                            id="previewMessage"
                            aria-live="polite"
                        ></div>

                    </div>

                </div>

            </section>

            <hr class="po-divider">

            <section class="po-section">

                <label class="po-status">

                    <input
                        type="checkbox"
                        name="status"
                        value="1"
                        <?= (int)$post['status'] === 1
                            ? 'checked'
                            : '' ?>
                    >

                    Xuất bản bài viết trên website

                </label>

            </section>

            <div class="po-actions">

                <button
                    class="po-btn"
                    type="submit"
                    id="savePost"
                >
                    <i class="fa-solid fa-floppy-disk"></i>

                    <?= $isEdit
                        ? 'Lưu thay đổi'
                        : 'Thêm bài viết' ?>

                </button>

                <a href="index.php" class="po-btn gray">
                    Hủy
                </a>

            </div>

        </form>

    </section>

</main>

<!-- =================================
     POPUP THONG BAO
     ================================= -->

<div class="po-overlay" id="postPopup" hidden>

    <div
        class="po-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="popupTitle"
    >

        <div class="po-modal-icon" id="popupIcon">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <h2 id="popupTitle">
            Thành công!
        </h2>

        <p id="popupMessage">
            Đã lưu bài viết thành công.
        </p>

        <button
            class="po-btn"
            type="button"
            id="popupOK"
        >
            Đồng ý
        </button>

    </div>

</div>

<script>
(function() {

    /* =====================================
       XEM TRUOC ANH TU URL
       ===================================== */

    const imageInput = document.getElementById(
        'postImage'
    );

    const previewImage = document.getElementById(
        'imagePreview'
    );

    const previewEmpty = document.getElementById(
        'previewEmpty'
    );

    const previewMessage = document.getElementById(
        'previewMessage'
    );

    const previewButton = document.getElementById(
        'previewButton'
    );

    function validImageUrl(value) {
        try {
            const url = new URL(value);

            return ['http:', 'https:'].includes(url.protocol) &&
                !url.username &&
                !url.password;
        } catch (_) {
            return false;
        }
    }

    function showImagePreview() {
        const url = imageInput.value.trim();

        previewImage.hidden = true;
        previewImage.removeAttribute('src');

        if (!url) {
            previewEmpty.hidden = false;
            previewMessage.textContent = '';
            return;
        }

        if (!validImageUrl(url)) {
            previewEmpty.hidden = false;
            previewMessage.textContent =
                'URL không hợp lệ. Vui lòng nhập http:// hoặc https://.';
            return;
        }

        previewEmpty.hidden = true;
        previewMessage.textContent = 'Đang kiểm tra ảnh...';

        const testImage = new Image();

        testImage.onload = function() {
            if (imageInput.value.trim() !== url) return;

            previewImage.src = url;
            previewImage.hidden = false;
            previewEmpty.hidden = true;
            previewMessage.textContent =
                'Ảnh xem trước đã tải thành công.';
        };

        testImage.onerror = function() {
            if (imageInput.value.trim() !== url) return;

            previewImage.hidden = true;
            previewEmpty.hidden = false;
            previewMessage.textContent =
                'Không tải được ảnh. Hãy kiểm tra URL hoặc quyền truy cập ảnh.';
        };

        testImage.src = url;
    }

    previewButton.addEventListener(
        'click',
        showImagePreview
    );

    imageInput.addEventListener('change', showImagePreview);

    imageInput.addEventListener('paste', function() {
        setTimeout(showImagePreview, 0);
    });

    if (imageInput.value.trim()) {
        showImagePreview();
    }

    /* =====================================
       LUU BAI VIET BANG AJAX
       ===================================== */

    const form = document.getElementById('postForm');
    const saveButton = document.getElementById('savePost');

    const popup = document.getElementById('postPopup');
    const popupTitle = document.getElementById('popupTitle');
    const popupMessage = document.getElementById('popupMessage');
    const popupIcon = document.getElementById('popupIcon');
    const popupOK = document.getElementById('popupOK');

    let saving = false;
    let saveSucceeded = false;

    function showPopup(success, message) {
        saveSucceeded = !!success;

        popupTitle.textContent = success
            ? 'Thành công!'
            : 'Không thành công';

        popupMessage.textContent = message;

        popupIcon.classList.toggle(
            'error',
            !success
        );

        popupIcon.innerHTML = success
            ? '<i class="fa-solid fa-circle-check"></i>'
            : '<i class="fa-solid fa-circle-xmark"></i>';

        popup.hidden = false;
        popupOK.focus();
    }

    popupOK.addEventListener('click', function() {
        if (saveSucceeded) {
            window.location.assign('index.php');
        } else {
            popup.hidden = true;
        }
    });

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (saving || !form.reportValidity()) {
            return;
        }

        if (!validImageUrl(imageInput.value.trim())) {
            showPopup(
                false,
                'Vui lòng nhập URL ảnh hợp lệ.'
            );
            return;
        }

        saving = true;

        const originalHTML = saveButton.innerHTML;

        saveButton.disabled = true;
        saveButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

        try {
            const response = await fetch(
                window.location.href,
                {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            const raw = await response.text();

            let result;

            try {
                result = JSON.parse(raw);
            } catch (_) {
                throw new Error(
                    'Máy chủ không trả về dữ liệu hợp lệ. ' +
                    'Vui lòng kiểm tra phiên đăng nhập hoặc lỗi PHP.'
                );
            }

            showPopup(
                !!result.success,
                result.message ||
                    'Không có thông báo từ máy chủ.'
            );

        } catch (error) {
            showPopup(
                false,
                error.message ||
                    'Không thể kết nối máy chủ.'
            );

        } finally {
            saving = false;
            saveButton.disabled = false;
            saveButton.innerHTML = originalHTML;
        }
    });

})();
</script>

</body>
</html>
