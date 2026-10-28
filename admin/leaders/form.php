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
    function leaderFormH($value) {
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
// QUET ANH CO SAN TRONG GITHUB
// ==========================================

// Thu muc nay nam trong source code da deploy.
// Vercel chi can deploy lai sau khi ban
// tai anh thu cong len GitHub.

$imageDirectory = dirname(__DIR__, 2)
    . '/assets/images/leaders';

$availableImages = [];

if (is_dir($imageDirectory)) {

    $files = scandir($imageDirectory);

    if (is_array($files)) {

        foreach ($files as $filename) {

            if ($filename === '.' || $filename === '..') {
                continue;
            }

            if (!preg_match(
                '/\.(jpg|jpeg|png|webp)$/i',
                $filename
            )) {
                continue;
            }

            $fullPath = $imageDirectory
                . DIRECTORY_SEPARATOR
                . $filename;

            if (!is_file($fullPath)) {
                continue;
            }

            $availableImages[] = [
                'name' => $filename,
                'path' => '/assets/images/leaders/' . $filename
            ];
        }

        usort(
            $availableImages,
            static function($a, $b) {
                return strnatcasecmp(
                    $a['name'],
                    $b['name']
                );
            }
        );

    }
}

// ==========================================
// LAY LEADER NEU DANG SUA
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

        $editing = $record;

    } catch (Throwable $e) {

        error_log(
            'Load Leader form: ' . $e->getMessage()
        );

        http_response_code(500);
        exit('Không thể tải hồ sơ Leader.');
    }
}

// ==========================================
// LUU THONG TIN
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $postedCsrf = (string)($_POST['csrf'] ?? '');

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

        // ==================================
        // KIEM TRA ANH DA CHON
        // ==================================

        $selectedImage = trim(
            (string)($_POST['image'] ?? '')
        );

        $allowedImages = array_column(
            $availableImages,
            'path'
        );

        // Chi chap nhan duong dan anh
        // thuc su ton tai trong thu muc.

        if (!in_array(
            $selectedImage,
            $allowedImages,
            true
        )) {

            throw new RuntimeException(
                'Vui lòng chọn một ảnh có sẵn trong thư viện.'
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

        header(
            'Location: index.php',
            true,
            303
        );

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
    font-weight: 850;
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
    font-weight: 850;
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
    box-shadow: 0 0 0 3px rgba(22,101,52,.09);
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

.lf-gallery {
    display: grid;
    grid-template-columns:
        repeat(5, minmax(0, 1fr));
    gap: 14px;
}

.lf-photo-option {
    display: block;
    position: relative;
    cursor: pointer;

    border: 2px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;

    transition:
        border-color .2s,
        background .2s;
}

.lf-photo-option:hover {
    border-color: #86efac;
}

.lf-photo-option:has(input:checked) {
    border-color: #166534;
    background: #f0fdf4;
    box-shadow: 0 0 0 2px rgba(22,101,52,.12);
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
    height: 165px;
    object-fit: cover;
    object-position: center;
    background: #f1f5f9;
}

.lf-photo-filename {
    padding: 10px 8px;
    font-size: 11px;
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
    box-shadow: 0 2px 9px rgba(0,0,0,.12);
}

.lf-photo-option:has(input:checked) .lf-photo-check {
    background: #166534;
    color: white;
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
}

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

    padding: 12px 18px;
    border: none;
    border-radius: 9px;

    background: #166534;
    color: white !important;
    text-decoration: none;

    font-size: 13px;
    font-weight: 850;
    cursor: pointer;
}

.lf-btn:hover {
    background: #14532d;
}

.lf-btn.gray {
    background: #e2e8f0;
    color: #334155 !important;
}

.lf-error {
    padding: 14px 16px;
    margin-bottom: 18px;

    background: #fee2e2;
    border-radius: 10px;
    color: #991b1b;
    font-size: 13px;
}

@media(max-width: 1150px) {
    .lf-gallery {
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
    }
}

@media(max-width: 850px) {
    .lf-gallery {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
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
}
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

            <div class="lf-error">
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

            <!-- THU VIEN ANH -->

            <h2 class="lf-section-title">
                <i class="fa-regular fa-images"></i>
                Chọn ảnh đại diện Leader
            </h2>

            <p class="lf-photo-note">
                Chọn một ảnh bên dưới. Các ảnh được lấy từ thư mục
                <strong>assets/images/leaders/</strong>
                mà bạn đã tải lên GitHub.
                Không cần nhập URL và không cần GitHub Token.
            </p>

            <?php if ($availableImages): ?>

                <div class="lf-gallery">

                    <?php foreach ($availableImages as $index => $photo): ?>

                        <?php
                        $checked = (string)(
                            $editing['image'] ?? ''
                        ) === $photo['path'];
                        ?>

                        <label class="lf-photo-option">

                            <input
                                type="radio"
                                name="image"
                                value="<?= leaderFormH($photo['path']) ?>"
                                <?= $checked ? 'checked' : '' ?>
                                <?= $index === 0 ? 'required' : '' ?>
                            >

                            <img
                                src="<?= leaderFormH($photo['path']) ?>"
                                alt="<?= leaderFormH($photo['name']) ?>"
                                loading="lazy"
                            >

                            <span class="lf-photo-check">
                                <i class="fa-solid fa-check"></i>
                            </span>

                            <div class="lf-photo-filename"
                                 title="<?= leaderFormH($photo['name']) ?>">
                                <?= leaderFormH($photo['name']) ?>
                            </div>

                        </label>

                    <?php endforeach; ?>

                </div>

                <div class="lf-selected">

                    <i class="fa-solid fa-image"></i>

                    <span>
                        Ảnh đã chọn:
                        <strong id="selectedImageName">
                            <?= $editing['image']
                                ? leaderFormH(
                                    basename($editing['image'])
                                )
                                : 'Chưa chọn ảnh' ?>
                        </strong>
                    </span>

                </div>

            <?php else: ?>

                <div class="lf-gallery-empty">

                    <strong>Chưa tìm thấy ảnh trong thư viện.</strong>

                    <p>
                        Bạn hãy tải các ảnh lên thư mục
                        <code>assets/images/leaders/</code>
                        trong GitHub.
                    </p>

                    <p>
                        Sau khi Vercel triển khai xong phiên bản mới,
                        tải lại trang này để chọn ảnh.
                    </p>

                </div>

            <?php endif; ?>

            <hr class="lf-divider">

            <!-- TRANG THAI -->

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

            <!-- NUT LUU -->

            <div class="lf-actions">

                <button
                    type="submit"
                    class="lf-btn"
                    <?= !$availableImages ? 'disabled' : '' ?>
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
(function() {

    const radios = document.querySelectorAll(
        'input[name="image"]'
    );

    const selectedName = document.getElementById(
        'selectedImageName'
    );

    radios.forEach(function(radio) {

        radio.addEventListener('change', function() {

            if (!radio.checked || !selectedName) {
                return;
            }

            const path = radio.value;

            const filename = path.split('/').pop();

            selectedName.textContent = filename;

        });

    });

})();
</script>

</body>
</html>
