<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$current_page = 'leaders';
$page_title = 'Thêm / Sửa Leader - VNN Travel';

if (!function_exists('leaderFormH')) {
    function leaderFormH($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (empty($_SESSION['leader_csrf'])) {
    $_SESSION['leader_csrf'] = bin2hex(random_bytes(32));
}

// ==========================================
// CAC TRUONG THONG TIN LEADER
// ==========================================

$fields = [
    'fullname',
    'nickname',
    'hometown',
    'experience_years',
    'mountains',
    'skills',
    'biography',
    'experience_detail',
    'philosophy',
    'safety_commitment',
    'achievements',
    'gallery'
];

$labels = [
    'fullname' => 'Họ và tên *',
    'nickname' => 'Biệt danh',
    'hometown' => 'Quê quán',
    'experience_years' => 'Số năm kinh nghiệm',
    'mountains' => 'Các cung đường từng chinh phục',
    'skills' => 'Kỹ năng chuyên môn',
    'biography' => 'Giới thiệu bản thân',
    'experience_detail' => 'Hành trình và kinh nghiệm chi tiết',
    'philosophy' => 'Phương châm trekking',
    'safety_commitment' => 'Cam kết an toàn',
    'achievements' => 'Thành tích nổi bật',
    'gallery' => 'Album ảnh bổ sung'
];

$longFields = [
    'mountains',
    'skills',
    'biography',
    'experience_detail',
    'philosophy',
    'safety_commitment',
    'achievements',
    'gallery'
];

$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? 0,
    FILTER_VALIDATE_INT
) ?: 0;

$editing = array_fill_keys($fields, '');
$editing['image'] = '';
$editing['status'] = 1;
$editing['experience_years'] = 0;

$error = '';
$record = null;

// ==========================================
// LAY THONG TIN KHI SUA LEADER
// ==========================================

if ($id > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM trekking_leaders
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            http_response_code(404);
            exit('Không tìm thấy hồ sơ Leader.');
        }

        $editing = array_merge(
            $editing,
            $record
        );

    } catch (Throwable $e) {

        error_log(
            'Load Leader form: ' . $e->getMessage()
        );

        http_response_code(500);
        exit('Không thể tải hồ sơ Leader.');
    }
}

// ==========================================
// LUU THONG TIN LEADER
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $postedCsrf = (string)(
            $_POST['csrf'] ?? ''
        );

        if (!hash_equals(
            (string)$_SESSION['leader_csrf'],
            $postedCsrf
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ.'
            );
        }

        $values = [];

        foreach ($fields as $field) {

            $values[$field] = trim(
                (string)($_POST[$field] ?? '')
            );

        }

        $editing = array_merge(
            $editing,
            $values
        );

        $editing['status'] =
            isset($_POST['status']) ? 1 : 0;

        if ($values['fullname'] === '') {
            throw new RuntimeException(
                'Vui lòng nhập họ và tên Leader.'
            );
        }

        if (mb_strlen($values['fullname']) > 255) {
            throw new RuntimeException(
                'Họ tên quá dài.'
            );
        }

        $years = filter_var(
            $values['experience_years'],
            FILTER_VALIDATE_INT
        );

        if (
            $years === false ||
            $years < 0 ||
            $years > 80
        ) {
            throw new RuntimeException(
                'Số năm kinh nghiệm phải từ 0 đến 80.'
            );
        }

        $values['experience_years'] = $years;

        $galleryUrls = array_values(array_filter(array_map(
            'trim',
            preg_split(
                '/\r\n|\n|\r/',
                (string)$values['gallery']
            )
        )));

        if (count($galleryUrls) > 30) {
            throw new RuntimeException(
                'Album Leader tối đa 30 ảnh.'
            );
        }

        foreach ($galleryUrls as $url) {
            $parts = parse_url($url);

            if (
                strlen($url) > 2048 ||
                !filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array(
                    strtolower((string)($parts['scheme'] ?? '')),
                    ['https', 'http'],
                    true
                ) ||
                isset($parts['user']) ||
                isset($parts['pass'])
            ) {
                throw new RuntimeException(
                    'Album chứa URL ảnh không hợp lệ.'
                );
            }
        }

        $values['gallery'] = implode(
            "\n",
            $galleryUrls
        );

        // ==================================
        // KIEM TRA ANH
        // ==================================

        $selectedImage = trim(
            (string)($_POST['image'] ?? '')
        );

        // Khi them moi bat buoc chon anh.
        // Khi sua, neu khong chon anh khac
        // thi giu nguyen anh hien tai.

        $currentImage = $id > 0
            ? (string)($record['image'] ?? '')
            : '';

        if ($selectedImage === '' && $id > 0) {
            $selectedImage = $currentImage;
        }

        if ($selectedImage === '') {
            throw new RuntimeException(
                'Vui lòng chọn ảnh đại diện Leader.'
            );
        }

        if ($selectedImage === '') {
            throw new RuntimeException(
                'Vui lòng nhập URL ảnh.'
            );
        }

        if (
            !filter_var(
                $selectedImage,
                FILTER_VALIDATE_URL
            ) ||
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

        $editing['image'] = $selectedImage;

        // ==================================
        // LUU DATABASE
        // ==================================

        $status = (int)$editing['status'];

        if ($id > 0) {

            $set = implode(
                ', ',
                array_map(
                    static fn($field) => "`$field` = ?",
                    $fields
                )
            );

            $sql = "
                UPDATE trekking_leaders
                SET
                    $set,
                    image = ?,
                    status = ?
                WHERE id = ?
            ";

            $params = array_values($values);
            $params[] = $selectedImage;
            $params[] = $status;
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['leader_notice'] =
                'Đã cập nhật hồ sơ Leader thành công.';

        } else {

            $columns = '`'
                . implode('`,`', $fields)
                . '`, `image`, `status`';

            $placeholders = implode(
                ', ',
                array_fill(
                    0,
                    count($fields) + 2,
                    '?'
                )
            );

            $sql = "
                INSERT INTO trekking_leaders
                ($columns)
                VALUES ($placeholders)
            ";

            $params = array_values($values);
            $params[] = $selectedImage;
            $params[] = $status;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['leader_notice'] =
                'Đã thêm Leader mới thành công.';
        }

        require_once __DIR__ . '/../includes/audit.php';

        vnnAuditChange(
            $pdo,
            'content',
            $id > 0 ? 'Sửa Leader' : 'Thêm Leader',
            'trekking_leaders',
            $id > 0
                ? $id
                : (int)$pdo->lastInsertId(),
            '',
            (string)($editing['fullname'] ?? '')
        );

        if (!headers_sent()) {
            header('Location: index.php', true, 303);
        } else {
            echo '<script>window.location.replace("index.php");</script>';
        }

        exit;

    } catch (Throwable $e) {

        error_log(
            'Save Leader: ' . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không lưu được hồ sơ Leader.';
    }
}

// Xac dinh anh da chon de hien thi.
$selectedImage = (string)(
    $editing['image'] ?? ''
);

$selectedFilename = $selectedImage !== ''
    ? basename(rawurldecode($selectedImage))
    : 'Chưa chọn ảnh';

?>
<!DOCTYPE html>
<html lang="vi">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title><?= leaderFormH($page_title) ?></title>

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

.lf-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    padding: 24px;
    background: #f2f5fa;
    color: #163455;
}

.lf-header {
    max-width: 1230px;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    padding: 21px 23px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

.lf-header h1 {
    margin: 0;
    color: #102e52;
    font-size: 20px;
    font-weight: 800;
}

.lf-panel {
    max-width: 1230px;
    margin: 0 auto;
    padding: 24px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

.lf-section-title {
    font-size: 17px;
    font-weight: 800;
    color: #102e52;
    margin: 0 0 19px;
}

.lf-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.lf-field {
    min-width: 0;
}

.lf-field.full {
    grid-column: 1 / -1;
}

.lf-field label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 800;
    color: #142e50;
}

.lf-field input:not([type=checkbox]),
.lf-field textarea {
    display: block;
    width: 100%;
    padding: 12px 13px;
    background: white;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    color: #183657;
    font: inherit;
    font-size: 13px;
}

.lf-field input:focus,
.lf-field textarea:focus {
    outline: none;
    border-color: #166534;
    box-shadow:
        0 0 0 3px rgba(22,101,52,.09);
}

.lf-field textarea {
    min-height: 116px;
    resize: vertical;
    line-height: 1.7;
}

.lf-divider {
    border: 0;
    border-top: 1px solid #e2e8f0;
    margin: 27px 0;
}

.lf-photo-note {
    color: #64748b;
    font-size: 12px;
    line-height: 1.7;
    margin: 0 0 17px;
}

/* ================================
   TIM KIEM VA LOC ANH
   ================================ */

.lf-photo-tools {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.lf-photo-search-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
}

.lf-photo-search-wrap i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
}

.lf-photo-search {
    width: 100%;
    height: 44px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 0 13px 0 40px;
    background: white;
    color: #163455;
    font: inherit;
    font-size: 13px;
}

.lf-photo-search:focus {
    outline: none;
    border-color: #166534;
    box-shadow:
        0 0 0 3px rgba(22,101,52,.09);
}

.lf-gallery {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.lf-photo-option {
    display: block;
    position: relative;
    cursor: pointer;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: white;
    transition:
        border-color .2s,
        background .2s;
}

.lf-photo-option[hidden] {
    display: none !important;
}

.lf-photo-option:hover {
    border-color: #86efac;
}

.lf-photo-option:has(input:checked) {
    border-color: #166534;
    background: #f0fdf4;
    box-shadow:
        0 0 0 2px rgba(22,101,52,.12);
}

.lf-photo-option input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.lf-photo-option img {
    display: block;
    width: 100%;
    height: 175px;
    object-fit: cover;
    object-position: center;
    background: #f1f5f9;
}

.lf-photo-filename {
    padding: 11px 9px;
    font-size: 12px;
    font-weight: 750;
    color: #334155;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.lf-photo-check {
    position: absolute;
    top: 9px;
    right: 9px;
    width: 29px;
    height: 29px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: white;
    color: #94a3b8;
    box-shadow:
        0 2px 9px rgba(0,0,0,.12);
}

.lf-photo-option:has(input:checked)
.lf-photo-check {
    background: #166534;
    color: white;
}

.lf-photo-count {
    margin: 14px 0 0;
    color: #64748b;
    font-size: 12px;
}

.lf-no-results {
    padding: 22px;
    border-radius: 10px;
    background: #f8fafc;
    color: #64748b;
    text-align: center;
    font-size: 13px;
}

.lf-no-results[hidden] {
    display: none !important;
}

.lf-gallery-empty {
    padding: 25px;
    background: #fff7ed;
    color: #9a3412;
    border: 1px solid #fed7aa;
    border-radius: 11px;
    line-height: 1.8;
    font-size: 13px;
}

.lf-selected {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-top: 17px;
    padding: 12px 15px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    color: #166534;
    font-size: 13px;
    overflow-wrap: anywhere;
}

/* ================================
   TRANG THAI VA NUT
   ================================ */

.lf-status {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 750;
    font-size: 13px;
    color: #163455;
}

.lf-status input {
    width: 17px;
    height: 17px;
    accent-color: #166534;
}

.lf-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 27px;
}

.lf-btn {
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

.lf-btn:hover {
    background: #14532d;
}

.lf-btn.blue {
    background: #1d4ed8;
}

.lf-btn.blue:hover {
    background: #1e40af;
}

.lf-btn.gray {
    background: #e2e8f0;
    color: #334155 !important;
}

.lf-btn:disabled {
    opacity: .55;
    cursor: not-allowed;
}

.lf-error {
    padding: 14px 16px;
    margin-bottom: 18px;
    background: #fee2e2;
    border-radius: 10px;
    color: #991b1b;
    font-size: 13px;
}

/* ================================
   RESPONSIVE
   ================================ */

@media(max-width: 850px) {
    .lf-gallery {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 650px) {
    .lf-page {
        padding: 12px;
    }

    .lf-header,
    .lf-panel {
        padding: 15px;
    }

    .lf-header h1 {
        font-size: 17px;
    }

    .lf-grid {
        grid-template-columns: 1fr;
    }

    .lf-gallery {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .lf-photo-option img {
        height: 145px;
    }

    .lf-photo-search-wrap {
        width: 100%;
    }
}

.lf-gallery-preview { display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-top:12px; }
.lf-gallery-preview-item { padding:7px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;min-width:0; }
.lf-gallery-preview-item img { width:100%;height:125px;object-fit:cover;border-radius:7px;display:block; }
.lf-gallery-preview-caption { font-size:12px;color:#475569;padding-top:6px;overflow-wrap:anywhere; }
.lf-gallery-preview-error { font-size:12px;color:#b91c1c;padding:10px;overflow-wrap:anywhere; }
</style>

</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main lf-page">

    <div class="lf-header">

        <h1>
            <i class="fa-solid fa-person-hiking"></i>

            <?= $id > 0
                ? 'CHỈNH SỬA HỒ SƠ LEADER'
                : 'THÊM HƯỚNG DẪN VIÊN MỚI' ?>
        </h1>

        <a href="index.php" class="lf-btn gray">
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại danh sách
        </a>

    </div>

    <section class="lf-panel">

        <?php if ($error !== ''): ?>
            <div class="lf-error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= leaderFormH($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="leaderForm">

            <input
                type="hidden"
                name="csrf"
                value="<?= leaderFormH($_SESSION['leader_csrf']) ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$id ?>"
            >

            <h2 class="lf-section-title">
                Thông tin hướng dẫn viên
            </h2>

            <div class="lf-grid">

                <?php foreach ($fields as $field): ?>

                    <div class="lf-field <?= in_array(
                        $field,
                        $longFields,
                        true
                    ) ? 'full' : '' ?>">

                        <label for="lf-<?= leaderFormH($field) ?>">
                            <?= leaderFormH($labels[$field]) ?>
                        </label>

                        <?php if (in_array(
                            $field,
                            $longFields,
                            true
                        )): ?>

                            <textarea
                                name="<?= leaderFormH($field) ?>"
                                id="lf-<?= leaderFormH($field) ?>"
                                rows="5"
                            ><?= leaderFormH(
                                $editing[$field] ?? ''
                            ) ?></textarea>

                            <?php if ($field === 'gallery'): ?>
                                <p class="lf-photo-note">Mỗi dòng một URL ảnh HTTPS. Ảnh sẽ được xem trước ngay bên dưới.</p>
                                <div id="leaderGalleryPreview" class="lf-gallery-preview" aria-live="polite"></div>
                            <?php endif; ?>

                        <?php else: ?>

                            <input
                                name="<?= leaderFormH($field) ?>"
                                id="lf-<?= leaderFormH($field) ?>"
                                type="<?= $field === 'experience_years'
                                    ? 'number'
                                    : 'text' ?>"
                                value="<?= leaderFormH(
                                    $editing[$field] ?? ''
                                ) ?>"
                                <?= $field === 'fullname'
                                    ? 'required'
                                    : '' ?>
                                <?= $field === 'experience_years'
                                    ? 'min="0" max="80"'
                                    : '' ?>
                            >

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

            <hr class="lf-divider">

            <h2 class="lf-section-title">
                Ảnh đại diện Leader bằng URL
            </h2>

            <div class="lf-field">
                <label for="leaderImageUrl">
                    Dán URL ảnh Leader *
                </label>

                <input
                    id="leaderImageUrl"
                    name="image"
                    type="url"
                    required
                    maxlength="2000"
                    placeholder="https://example.com/leader.jpg"
                    value="<?= leaderFormH($selectedImage) ?>"
                    style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:9px"
                >

                <p class="lf-photo-note">
                    Sử dụng liên kết ảnh công khai.
                    Có thể giữ URL cũ khi sửa.
                </p>

                <img
                    id="leaderPreview"
                    src="<?= leaderFormH($selectedImage) ?>"
                    alt="Xem trước ảnh Leader"
                    style="display:<?= $selectedImage ? 'block' : 'none' ?>;width:155px;height:175px;object-fit:cover;border-radius:10px;margin:12px 0"
                >
            </div>

            <hr class="lf-divider">

            <!-- ====================================
                 TRANG THAI HIEN THI
                 ==================================== -->

            <label class="lf-status">

                <input
                    type="checkbox"
                    name="status"
                    value="1"
                    <?= (int)($editing['status'] ?? 1) === 1
                        ? 'checked'
                        : '' ?>
                >

                Hiển thị hồ sơ Leader trên website

            </label>

            <!-- ====================================
                 NUT LUU
                 ==================================== -->

            <div class="lf-actions">

                <button
                    type="submit"
                    class="lf-btn"
                    id="saveLeaderBtn"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    <?= $id > 0
                        ? 'Lưu thay đổi'
                        : 'Thêm Leader' ?>

                </button>

                <a href="index.php" class="lf-btn gray">
                    Hủy
                </a>

            </div>

        </form>

    </section>

</main>

<script>
(() => {
    const input = document.getElementById('leaderImageUrl');
    const preview = document.getElementById('leaderPreview');
    const form = document.getElementById('leaderForm');
    const save = document.getElementById('saveLeaderBtn');

    input?.addEventListener('input', () => {
        const value = input.value.trim();

        preview.src = value;

        preview.style.display =
            /^https?:\/\//i.test(value)
                ? 'block'
                : 'none';
    });

    preview?.addEventListener('error', () => {
        preview.style.display = 'none';
    });

    const galleryInput = document.getElementById('lf-gallery');
    const galleryPreview = document.getElementById('leaderGalleryPreview');
    let galleryRenderVersion = 0;

    function renderGalleryPreview() {
        if (!galleryInput || !galleryPreview) return;
        const version = ++galleryRenderVersion;
        const urls = [...new Set(galleryInput.value.split(/\r?\n/).map(v => v.trim()).filter(Boolean))];
        galleryPreview.replaceChildren();
        urls.forEach((url, index) => {
            const item = document.createElement('div');
            item.className = 'lf-gallery-preview-item';
            if (!/^https?:\/\//i.test(url)) {
                const error = document.createElement('div');
                error.className = 'lf-gallery-preview-error';
                error.textContent = 'URL ' + (index + 1) + ' không hợp lệ.';
                item.appendChild(error);
            } else {
                const img = document.createElement('img');
                img.alt = 'Ảnh album ' + (index + 1);
                img.loading = 'lazy';
                img.addEventListener('error', () => {
                    if (version !== galleryRenderVersion) return;
                    item.replaceChildren();
                    const error = document.createElement('div');
                    error.className = 'lf-gallery-preview-error';
                    error.textContent = 'Ảnh ' + (index + 1) + ' không tải được. Hãy kiểm tra URL hoặc quyền truy cập ảnh.';
                    item.appendChild(error);
                });
                img.src = url;
                item.appendChild(img);
            }
            galleryPreview.appendChild(item);
        });
    }
    galleryInput?.addEventListener('input', renderGalleryPreview);
    renderGalleryPreview();

    form?.addEventListener('submit', () => {
        if (form.checkValidity() && save) {
            save.disabled = true;
            save.textContent = 'ĐANG LƯU...';
        }
    });
})();
</script>

</body>
</html>
