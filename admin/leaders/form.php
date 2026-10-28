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
// DOC THU VIEN ANH TU SOURCE GITHUB
// ==========================================

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
                'path' => '/assets/images/leaders/'
                    . rawurlencode($filename)
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

$allowedImages = array_column(
    $availableImages,
    'path'
);

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

        // Anh moi phai co trong thu vien.
        // Anh cu co the nam o duong dan cu
        // nen cho phep giu nguyen khi sua.

        $isAvailableImage = in_array(
            $selectedImage,
            $allowedImages,
            true
        );

        $isUnchangedImage = (
            $id > 0 &&
            $currentImage !== '' &&
            hash_equals(
                $currentImage,
                $selectedImage
            )
        );

        if (!$isAvailableImage && !$isUnchangedImage) {
            throw new RuntimeException(
                'Ảnh được chọn không có trong thư viện.'
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

            <!-- ====================================
                 TIM KIEM VA CHON ANH LEADER
                 ==================================== -->

            <h2 class="lf-section-title">
                <i class="fa-regular fa-images"></i>
                Chọn ảnh đại diện Leader
            </h2>

            <p class="lf-photo-note">
                Các ảnh được lấy từ thư mục
                <strong>assets/images/leaders/</strong>
                trên GitHub. Mặc định chỉ hiện tối đa
                <strong>6 ảnh</strong>.
                Bạn có thể tìm kiếm theo tên ảnh
                hoặc chọn Xem tất cả ảnh.
            </p>

            <?php if ($id > 0 && $selectedImage !== ''): ?>

                <div style="margin-bottom:18px">

                    <div style="
                        font-size:13px;
                        font-weight:800;
                        margin-bottom:9px;
                    ">
                        Ảnh đại diện hiện tại
                    </div>

                    <img
                        src="<?= leaderFormH($selectedImage) ?>"
                        alt="Ảnh Leader hiện tại"
                        style="
                            display:block;
                            width:155px;
                            height:175px;
                            object-fit:cover;
                            border-radius:10px;
                            border:1px solid #e2e8f0;
                        "
                        onerror="this.style.display='none'"
                    >

                </div>

            <?php endif; ?>

            <?php if ($availableImages): ?>

                <!-- BO TIM KIEM -->

                <div class="lf-photo-tools">

                    <div class="lf-photo-search-wrap">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="search"
                            class="lf-photo-search"
                            id="leaderPhotoSearch"
                            placeholder="Tìm tên ảnh... Ví dụ: nguyen-van-hung"
                            autocomplete="off"
                        >

                    </div>

                    <button
                        type="button"
                        class="lf-btn blue"
                        id="toggleLeaderImages"
                    >
                        <i class="fa-solid fa-images"></i>
                        <span id="toggleLeaderText">
                            Xem tất cả ảnh
                        </span>
                    </button>

                </div>

                <!-- DANH SACH ANH -->

                <div class="lf-gallery" id="leaderPhotoGallery">

                    <?php foreach ($availableImages as $photo): ?>

                        <?php
                        $checked =
                            $selectedImage === $photo['path'];
                        ?>

                        <label
                            class="lf-photo-option"
                            data-filename="<?= leaderFormH($photo['name']) ?>"
                        >

                            <input
                                type="radio"
                                name="image"
                                value="<?= leaderFormH($photo['path']) ?>"
                                <?= $checked ? 'checked' : '' ?>
                            >

                            <img
                                src="<?= leaderFormH($photo['path']) ?>"
                                alt="<?= leaderFormH($photo['name']) ?>"
                                loading="lazy"
                            >

                            <span class="lf-photo-check">
                                <i class="fa-solid fa-check"></i>
                            </span>

                            <div
                                class="lf-photo-filename"
                                title="<?= leaderFormH($photo['name']) ?>"
                            >
                                <?= leaderFormH($photo['name']) ?>
                            </div>

                        </label>

                    <?php endforeach; ?>

                </div>

                <div
                    class="lf-no-results"
                    id="leaderPhotoNoResults"
                    hidden
                >
                    <i class="fa-regular fa-image"></i>
                    Không tìm thấy ảnh phù hợp.
                    Hãy thử tên khác.
                </div>

                <p
                    class="lf-photo-count"
                    id="leaderPhotoCount"
                ></p>

                <div class="lf-selected">

                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        Ảnh đã chọn:
                        <strong id="selectedImageName">
                            <?= leaderFormH($selectedFilename) ?>
                        </strong>
                    </span>

                </div>

            <?php else: ?>

                <div class="lf-gallery-empty">

                    <strong>
                        Chưa tìm thấy ảnh trong thư viện.
                    </strong>

                    <p>
                        Bạn hãy tải ảnh JPG, PNG hoặc WebP
                        lên thư mục
                        <code>assets/images/leaders/</code>
                        trên GitHub.
                    </p>

                    <p>
                        Sau khi Vercel triển khai xong,
                        tải lại trang để chọn ảnh.
                    </p>

                    <?php if ($id > 0 && $selectedImage !== ''): ?>
                        <p>
                            Bạn vẫn có thể lưu các thông tin khác
                            và giữ nguyên ảnh hiện tại.
                        </p>
                    <?php endif; ?>

                </div>

            <?php endif; ?>

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
                    <?= !$availableImages && $selectedImage === ''
                        ? 'disabled'
                        : '' ?>
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

    // ======================================
    // TIM KIEM ANH CO DAU / KHONG DAU
    // ======================================

    const searchInput = document.getElementById(
        'leaderPhotoSearch'
    );

    const gallery = document.getElementById(
        'leaderPhotoGallery'
    );

    const toggleButton = document.getElementById(
        'toggleLeaderImages'
    );

    const toggleText = document.getElementById(
        'toggleLeaderText'
    );

    const photoCount = document.getElementById(
        'leaderPhotoCount'
    );

    const noResults = document.getElementById(
        'leaderPhotoNoResults'
    );

    const selectedName = document.getElementById(
        'selectedImageName'
    );

    const cards = gallery
        ? Array.from(
            gallery.querySelectorAll(
                '.lf-photo-option'
            )
        )
        : [];

    const defaultLimit = 6;

    let showAll = false;

    function normalize(text) {
        return String(text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'd')
            .toLocaleLowerCase('vi');
    }

    // ======================================
    // CAP NHAT DANH SACH ANH
    // ======================================

    function updateGallery() {

        const keyword = normalize(
            searchInput?.value.trim() || ''
        );

        const matching = cards.filter(function(card) {

            const filename = normalize(
                card.dataset.filename || ''
            );

            return filename.includes(keyword);

        });

        const limit = keyword !== '' || showAll
            ? matching.length
            : defaultLimit;

        let visible = 0;

        cards.forEach(function(card) {

            const index = matching.indexOf(card);

            const shouldShow =
                index >= 0 &&
                index < limit;

            card.hidden = !shouldShow;

            if (shouldShow) {
                visible++;
            }

        });

        if (photoCount) {

            if (keyword !== '') {
                photoCount.textContent =
                    'Tìm thấy ' +
                    matching.length +
                    ' ảnh phù hợp.';
            } else {
                photoCount.textContent =
                    'Đang hiển thị ' +
                    visible +
                    '/' +
                    cards.length +
                    ' ảnh.';
            }

        }

        if (noResults) {
            noResults.hidden =
                matching.length !== 0;
        }

        if (toggleButton) {

            toggleButton.style.display =
                keyword !== ''
                    ? 'none'
                    : '';

        }

        if (toggleText) {

            toggleText.textContent =
                showAll
                    ? 'Thu gọn ảnh'
                    : 'Xem tất cả ảnh';

        }

    }

    // ======================================
    // SU KIEN TIM KIEM
    // ======================================

    searchInput?.addEventListener(
        'input',
        updateGallery
    );

    // ======================================
    // XEM TAT CA / THU GON
    // ======================================

    toggleButton?.addEventListener(
        'click',
        function() {

            showAll = !showAll;

            updateGallery();

        }
    );

    // ======================================
    // CHON ANH
    // ======================================

    const imageRadios = document.querySelectorAll(
        'input[name="image"]'
    );

    imageRadios.forEach(function(radio) {

        radio.addEventListener(
            'change',
            function() {

                if (!radio.checked) {
                    return;
                }

                const path = radio.value;

                const filename = decodeURIComponent(
                    path.split('/').pop() || ''
                );

                if (selectedName) {
                    selectedName.textContent = filename;
                }

            }
        );

    });

    // ======================================
    // CHAN BAM LUU LIEN TUC
    // ======================================

    const form = document.getElementById(
        'leaderForm'
    );

    const saveButton = document.getElementById(
        'saveLeaderBtn'
    );

    form?.addEventListener('submit', function(event) {

        if (!form.checkValidity()) {
            return;
        }

        const checkedImage = form.querySelector(
            'input[name="image"]:checked'
        );

        const existingImage = <?=
            json_encode(
                $id > 0 ? $selectedImage : '',
                JSON_UNESCAPED_UNICODE |
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            )
        ?>;

        if (!checkedImage && !existingImage) {

            event.preventDefault();

            alert(
                'Vui lòng chọn ảnh đại diện Leader.'
            );

            return;
        }

        if (saveButton) {

            saveButton.disabled = true;

            saveButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> ĐANG LƯU...';

        }

    });

    // KHOI TAO THU VIEN
    updateGallery();

})();
</script>

</body>
</html>
