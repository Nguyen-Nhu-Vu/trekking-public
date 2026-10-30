
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

/* ============================================
   CSRF
   ============================================ */

if (empty($_SESSION['posts_csrf'])) {
    $_SESSION['posts_csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string)$_SESSION['posts_csrf'];

$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? 0,
    FILTER_VALIDATE_INT
) ?: 0;

$isEdit = $id > 0;
$error = '';

$post = [
    'title' => '',
    'slug' => '',
    'category' => 'Tin tức',
    'excerpt' => '',
    'content' => '',
    'image' => '',
    'status' => 1
];

$record = null;

/* ============================================
   LAY BAI VIET KHI CHINH SUA
   ============================================ */

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
            http_response_code(404);
            exit('Không tìm thấy bài viết.');
        }

        $post = array_merge($post, $record);

    } catch (Throwable $e) {
        error_log('Load post: ' . $e->getMessage());
        $error = 'Không thể tải bài viết cần chỉnh sửa.';
    }
}

/* ============================================
   HAM TAO SLUG
   ============================================ */

if (!function_exists('postFormSlug')) {
    function postFormSlug(string $text): string {
        $text = trim($text);

        if (function_exists('iconv')) {
            $ascii = @iconv(
                'UTF-8',
                'ASCII//TRANSLIT//IGNORE',
                $text
            );

            if ($ascii !== false) {
                $text = $ascii;
            }
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text !== '' ? $text : 'bai-viet';
    }
}

/* ============================================
   XU LY LUU BAI VIET
   ============================================ */

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
        $category = trim((string)($_POST['category'] ?? ''));
        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));

        $status = isset($_POST['status']) ? 1 : 0;

        $selectedImage = trim(
            (string)($_POST['image'] ?? '')
        );

        if ($title === '') {
            throw new RuntimeException(
                'Vui lòng nhập tiêu đề bài viết.'
            );
        }

        if (mb_strlen($title) > 255) {
            throw new RuntimeException(
                'Tiêu đề bài viết quá dài.'
            );
        }

        if ($category === '') {
            throw new RuntimeException(
                'Vui lòng chọn danh mục bài viết.'
            );
        }

        if ($content === '') {
            throw new RuntimeException(
                'Vui lòng nhập nội dung bài viết.'
            );
        }

        if ($slug === '') {
            $slug = postFormSlug($title);
        } else {
            $slug = postFormSlug($slug);
        }

        $checkSlug = $pdo->prepare("
            SELECT id
            FROM posts
            WHERE slug = ?
              AND id <> ?
            LIMIT 1
        ");

        $baseSlug = $slug;
        $suffix = 1;

        do {
            $checkSlug->execute([$slug, $id]);
            $exists = $checkSlug->fetchColumn();

            if (!$exists) {
                break;
            }

            $suffix++;
            $slug = $baseSlug . '-' . $suffix;

        } while ($suffix < 10000);

        if ($exists) {
            throw new RuntimeException(
                'Không thể tạo đường dẫn bài viết duy nhất.'
            );
        }

        /* ====================================
           ANH DAI DIEN
           ==================================== */

        $oldImage = $isEdit
            ? (string)($record['image'] ?? '')
            : '';

        if ($selectedImage === '' && $isEdit) {
            $selectedImage = $oldImage;
        }

        if ($selectedImage === '') {
            throw new RuntimeException(
                'Vui lòng nhập URL ảnh đại diện bài viết.'
            );
        }

        if (
            !filter_var($selectedImage, FILTER_VALIDATE_URL) ||
            !in_array(
                strtolower(
                    (string)parse_url(
                        $selectedImage,
                        PHP_URL_SCHEME
                    )
                ),
                ['http', 'https'],
                true
            )
        ) {
            throw new RuntimeException(
                'URL ảnh phải bắt đầu bằng http:// hoặc https:// và hợp lệ.'
            );
        }

        if (strlen($selectedImage) > 2000) {
            throw new RuntimeException(
                'URL ảnh quá dài.'
            );
        }

        /* ====================================
           GHI DATABASE
           ==================================== */

        if ($isEdit) {

            $stmt = $pdo->prepare("
                UPDATE posts
                SET
                    title = ?,
                    slug = ?,
                    category = ?,
                    excerpt = ?,
                    content = ?,
                    image = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $title,
                $slug,
                $category,
                $excerpt,
                $content,
                $selectedImage,
                $status,
                $id
            ]);

            require_once __DIR__ . '/../includes/audit.php';

            vnnAuditChange(
                $pdo,
                'content',
                'Sửa bài viết',
                'posts',
                $id,
                (string)($record['title'] ?? ''),
                $title
            );

            postFormJson(
                true,
                'Đã lưu thay đổi bài viết thành công.'
            );

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO posts (
                    title,
                    slug,
                    category,
                    excerpt,
                    content,
                    image,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $title,
                $slug,
                $category,
                $excerpt,
                $content,
                $selectedImage,
                $status
            ]);

            require_once __DIR__ . '/../includes/audit.php';

            vnnAuditChange(
                $pdo,
                'content',
                'Thêm bài viết',
                'posts',
                (int)$pdo->lastInsertId(),
                '',
                $title
            );

            postFormJson(
                true,
                'Đã thêm bài viết mới thành công.'
            );
        }

    } catch (Throwable $e) {

        error_log(
            'Save post: ' . $e->getMessage()
        );

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
<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title><?= postFormH($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet"
 href="../../assets/css/style.css">

<link rel="stylesheet"
 href="../../assets/css/admin.css">

<style>
* {
    box-sizing: border-box;
}

.ps-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    padding: 24px;
    background: #f1f5f9;
    color: #173454;
    font-size: 14px;
}

.ps-header,
.ps-panel {
    max-width: 1200px;
    margin: 0 auto 18px;
    padding: 24px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

.ps-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.ps-header h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #102e52;
}

.ps-section {
    margin-bottom: 26px;
}

.ps-section h2 {
    font-size: 18px;
    color: #102e52;
    margin: 0 0 18px;
}

.ps-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.ps-field {
    min-width: 0;
    margin-bottom: 13px;
}

.ps-field.full {
    grid-column: 1 / -1;
}

.ps-field label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 800;
    color: #163455;
}

.ps-input {
    display: block;
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    color: #173454;
    font: inherit;
    font-size: 13px;
}

.ps-input:focus {
    outline: none;
    border-color: #166534;
    box-shadow:
        0 0 0 3px rgba(22,101,52,.09);
}

textarea.ps-input {
    min-height: 118px;
    resize: vertical;
    line-height: 1.7;
}

.ps-content-editor {
    min-height: 360px !important;
    font-family:
        'Consolas',
        'Courier New',
        monospace;
    line-height: 1.75;
    tab-size: 4;
}

.ps-editor-note {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    border-radius: 9px;
    padding: 13px 15px;
    font-size: 12px;
    line-height: 1.8;
    margin-bottom: 14px;
}

.ps-editor-note code {
    font-weight: 800;
}

.ps-photo-preview {
    width: 250px;
    max-width: 100%;
    height: 170px;
    display: block;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
    margin: 14px 0 0;
}

.ps-note {
    color: #64748b;
    font-size: 12px;
    line-height: 1.7;
    margin: 8px 0 0;
}

.ps-divider {
    border: 0;
    border-top: 1px solid #e2e8f0;
    margin: 27px 0;
}

.ps-status {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 800;
    color: #173454;
}

.ps-status input {
    width: 18px;
    height: 18px;
    accent-color: #166534;
}

.ps-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 26px;
}

.ps-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 44px;
    padding: 11px 17px;
    border: none;
    border-radius: 9px;
    background: #166534;
    color: white !important;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
}

.ps-btn:hover {
    background: #14532d;
}

.ps-btn.gray {
    background: #e2e8f0;
    color: #334155 !important;
}

.ps-btn:disabled {
    opacity: .55;
    cursor: not-allowed;
}

.ps-error {
    border-radius: 9px;
    padding: 14px 16px;
    background: #fee2e2;
    color: #991b1b;
    margin-bottom: 18px;
}

.ps-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 17px;
    background: rgba(15,23,42,.65);
}

.ps-overlay[hidden] {
    display: none !important;
}

.ps-modal {
    width: 100%;
    max-width: 430px;
    border-radius: 17px;
    padding: 27px;
    background: white;
    text-align: center;
    box-shadow:
        0 22px 70px rgba(0,0,0,.2);
}

.ps-modal-icon {
    width: 68px;
    height: 68px;
    margin: 0 auto 17px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #dcfce7;
    color: #166534;
    border-radius: 50%;
    font-size: 30px;
}

.ps-modal-icon.error {
    background: #fee2e2;
    color: #dc2626;
}

.ps-modal h2 {
    margin: 0 0 11px;
    font-size: 21px;
    color: #102e52;
}

.ps-modal p {
    margin: 0 0 23px;
    font-size: 14px;
    color: #64748b;
    line-height: 1.7;
}

@media(max-width:760px) {
    .ps-page {
        padding: 12px;
    }

    .ps-header,
    .ps-panel {
        padding: 16px;
    }

    .ps-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main ps-page">

    <div class="ps-header">

        <h1>
            <i class="fa-solid fa-newspaper"></i>

            <?= $isEdit
                ? 'CHỈNH SỬA BÀI VIẾT'
                : 'THÊM BÀI VIẾT MỚI' ?>
        </h1>

        <a
            href="index.php"
            class="ps-btn gray"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại danh sách
        </a>

    </div>

    <section class="ps-panel">

        <?php if ($error): ?>

            <div class="ps-error">
                <i class="fa-solid fa-circle-exclamation"></i>
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

            <section class="ps-section">

                <h2>Thông tin bài viết</h2>

                <div class="ps-grid">

                    <div class="ps-field full">

                        <label for="title">
                            Tiêu đề bài viết *
                        </label>

                        <input
                            type="text"
                            class="ps-input"
                            name="title"
                            id="title"
                            required
                            maxlength="255"
                            value="<?= postFormH(
                                $post['title']
                            ) ?>"
                        >

                    </div>

                    <div class="ps-field">

                        <label for="slug">
                            Đường dẫn bài viết (Slug)
                        </label>

                        <input
                            type="text"
                            class="ps-input"
                            name="slug"
                            id="slug"
                            placeholder="Tu dong tao neu bo trong"
                            value="<?= postFormH(
                                $post['slug']
                            ) ?>"
                        >

                        <p class="ps-note">
                            Để trống để tự tạo từ tiêu đề.
                        </p>

                    </div>

                    <div class="ps-field">

                        <label for="category">
                            Danh mục *
                        </label>

                        <select
                            class="ps-input"
                            name="category"
                            id="category"
                            required
                        >

                            <?php
                            $postCategories = [
                                'Tin tức',
                                'Kinh nghiệm',
                                'Hướng dẫn',
                                'Cẩm nang',
                                'Khuyến mãi',
                                'Thông báo'
                            ];
                            ?>

                            <?php foreach ($postCategories as $category): ?>

                                <option
                                    value="<?= postFormH($category) ?>"
                                    <?= $post['category'] === $category
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= postFormH($category) ?>
                                </option>

                            <?php endforeach; ?>

                            <?php if (
                                $post['category'] !== '' &&
                                !in_array(
                                    $post['category'],
                                    $postCategories,
                                    true
                                )
                            ): ?>

                                <option
                                    value="<?= postFormH(
                                        $post['category']
                                    ) ?>"
                                    selected
                                >
                                    <?= postFormH(
                                        $post['category']
                                    ) ?>
                                </option>

                            <?php endif; ?>

                        </select>

                    </div>

                    <div class="ps-field full">

                        <label for="excerpt">
                            Mô tả ngắn
                        </label>

                        <textarea
                            name="excerpt"
                            id="excerpt"
                            class="ps-input"
                            rows="3"
                            placeholder="Tóm tắt ngắn nội dung bài viết..."
                        ><?= postFormH(
                            $post['excerpt']
                        ) ?></textarea>

                    </div>

                </div>

            </section>

            <hr class="ps-divider">

            <section class="ps-section">

                <h2>Ảnh đại diện bài viết bằng URL</h2>

                <div class="ps-field">

                    <label for="postImageUrl">
                        URL ảnh đại diện *
                    </label>

                    <input
                        type="url"
                        class="ps-input"
                        name="image"
                        id="postImageUrl"
                        required
                        maxlength="2000"
                        placeholder="https://example.com/anh-bai-viet.jpg"
                        value="<?= postFormH(
                            $currentImage
                        ) ?>"
                    >

                    <p class="ps-note">
                        Dán URL ảnh công khai (http hoặc https).
                        Khi sửa bài viết, bạn có thể giữ hoặc thay URL cũ.
                    </p>

                    <img
                        class="ps-photo-preview"
                        id="postPreview"
                        alt="Ảnh đại diện bài viết"
                        src="<?= postFormH($currentImage) ?>"
                        style="display:<?= $currentImage !== ''
                            ? 'block'
                            : 'none' ?>"
                    >

                </div>

            </section>

            <hr class="ps-divider">

            <section class="ps-section">

                <h2>Nội dung bài viết</h2>

                <div class="ps-editor-note">

                    Bạn có thể nhập nội dung có định dạng HTML.

                    <strong>Ví dụ:</strong>

                    <code>&lt;h2&gt;Tiêu đề phụ&lt;/h2&gt;</code>,

                    <code>&lt;p&gt;Đoạn văn&lt;/p&gt;</code>,

                    <code>&lt;br&gt;</code>,

                    <code>&lt;strong&gt;Chữ đậm&lt;/strong&gt;</code>,

                    <code>&lt;ul&gt;&lt;li&gt;Danh sách&lt;/li&gt;&lt;/ul&gt;</code>.

                    Hãy sử dụng HTML hợp lệ, tránh đưa mã JavaScript
                    hoặc thẻ script vào nội dung.

                </div>

                <div class="ps-field full">

                    <label for="content">
                        Nội dung chi tiết *
                    </label>

                    <textarea
                        name="content"
                        id="content"
                        class="ps-input ps-content-editor"
                        rows="16"
                        required
                        placeholder="Nhập nội dung bài viết..."
                    ><?= postFormH(
                        $post['content']
                    ) ?></textarea>

                </div>

            </section>

            <hr class="ps-divider">

            <label class="ps-status">

                <input
                    type="checkbox"
                    name="status"
                    value="1"
                    <?= (int)$post['status'] === 1
                        ? 'checked'
                        : '' ?>
                >

                Hiển thị bài viết trên website

            </label>

            <div class="ps-actions">

                <button
                    type="submit"
                    class="ps-btn"
                    id="savePost"
                >
                    <i class="fa-solid fa-floppy-disk"></i>

                    <?= $isEdit
                        ? 'Lưu thay đổi'
                        : 'Thêm bài viết' ?>
                </button>

                <a
                    href="index.php"
                    class="ps-btn gray"
                >
                    Hủy
                </a>

            </div>

        </form>

    </section>

</main>

<!-- ===================================
     POPUP THONG BAO
     =================================== -->

<div
    class="ps-overlay"
    id="postPopup"
    hidden
>
    <div
        class="ps-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="postPopupTitle"
    >

        <div
            class="ps-modal-icon"
            id="postPopupIcon"
        >
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <h2 id="postPopupTitle">
            Thông báo
        </h2>

        <p id="postPopupMessage"></p>

        <button
            type="button"
            class="ps-btn"
            id="postPopupOK"
        >
            Đồng ý
        </button>

    </div>
</div>

<script>
(function() {

    const form = document.getElementById('postForm');

    const saveButton = document.getElementById(
        'savePost'
    );

    const popup = document.getElementById(
        'postPopup'
    );

    const popupIcon = document.getElementById(
        'postPopupIcon'
    );

    const popupTitle = document.getElementById(
        'postPopupTitle'
    );

    const popupMessage = document.getElementById(
        'postPopupMessage'
    );

    const popupOK = document.getElementById(
        'postPopupOK'
    );

    let saveSucceeded = false;
    let submitting = false;

    /* ===================================
       XEM TRUOC ANH
       =================================== */

    const imageInput = document.getElementById(
        'postImageUrl'
    );

    const preview = document.getElementById(
        'postPreview'
    );

    imageInput?.addEventListener('input', function() {
        const url = imageInput.value.trim();

        preview.src = url;

        preview.style.display = /^https?:\/\//i.test(url)
            ? 'block'
            : 'none';
    });

    preview?.addEventListener('error', function() {
        preview.style.display = 'none';
    });

    /* ===================================
       HIEN POPUP
       =================================== */

    function showPopup(success, message) {

        saveSucceeded = success;

        popupIcon.classList.toggle(
            'error',
            !success
        );

        popupIcon.innerHTML = success
            ? '<i class="fa-solid fa-circle-check"></i>'
            : '<i class="fa-solid fa-circle-xmark"></i>';

        popupTitle.textContent = success
            ? 'Thành công!'
            : 'Không thành công';

        popupMessage.textContent = message;

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

    /* ===================================
       LUU BAI VIET AJAX
       =================================== */

    form.addEventListener('submit', async function(event) {

        event.preventDefault();

        if (submitting) {
            return;
        }

        if (!form.reportValidity()) {
            return;
        }

        submitting = true;
        saveButton.disabled = true;

        const originalHTML = saveButton.innerHTML;

        saveButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

        try {

            const response = await fetch(
                form.action || window.location.href,
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

            let data;

            try {
                data = JSON.parse(raw);
            } catch (parseError) {
                throw new Error(
                    'Máy chủ trả về dữ liệu không hợp lệ. ' +
                    'Vui lòng kiểm tra phiên đăng nhập hoặc lỗi PHP.'
                );
            }

            showPopup(
                !!data.success,
                data.message || 'Đã xử lý yêu cầu.'
            );

        } catch (error) {

            showPopup(
                false,
                error.message ||
                'Không thể kết nối máy chủ.'
            );

        } finally {

            submitting = false;
            saveButton.disabled = false;
            saveButton.innerHTML = originalHTML;

        }

    });

})();
</script>

</body>
</html>
