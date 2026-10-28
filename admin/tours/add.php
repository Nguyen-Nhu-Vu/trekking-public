<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

$current_page = 'tours';
$page_title = 'Thêm Tour Mới - VNN Travel';

if (!function_exists('tourAddH')) {
    function tourAddH($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (empty($_SESSION['tour_add_csrf'])) {
    $_SESSION['tour_add_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$leaderOptions = [];
$locations = [];

// ========================================
// THU VIEN ANH TOUR
// ========================================

$imageDirectory = dirname(__DIR__, 2)
    . '/assets/images/tours';

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
                'path' => '/assets/images/tours/' . rawurlencode($filename)
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

// ========================================
// LAY LEADER VA DIA DIEM
// ========================================

try {
    $stmt = $pdo->query("
        SELECT id, fullname
        FROM trekking_leaders
        WHERE status = 1
        ORDER BY fullname ASC
    ");

    $leaderOptions = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    $stmt = $pdo->query("
        SELECT id, name
        FROM locations
        ORDER BY name ASC
    ");

    $locations = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {
    error_log('Add tour options: ' . $e->getMessage());
    $error = 'Không thể tải danh sách Leader hoặc địa điểm.';
}

// ========================================
// GIA TRI MAC DINH
// ========================================

$defaults = [
    'title' => '',
    'leader_id' => '',
    'location_id' => '',
    'price' => '',
    'duration' => '2N1Đ',
    'difficulty' => 'Vừa phải',
    'altitude' => '2800m',
    'distance' => '18 km',
    'max_slots' => '15',
    'meeting_point' => 'Hà Nội',
    'transport' => 'Xe giường nằm',
    'image' => '',
    'description' => '',
    'schedule' => '',
    'includes' => '',
    'excludes' => '',
    'requirements' => '',
    'safety_notes' => ''
];

$form = $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($defaults as $field => $default) {
        $value = $_POST[$field] ?? $default;
        $form[$field] = is_string($value)
            ? trim($value)
            : '';
    }

    try {
        // ====================================
        // KIEM TRA PHIEN LAM VIEC
        // ====================================

        if (!hash_equals(
            (string)$_SESSION['tour_add_csrf'],
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
            );
        }

        // ====================================
        // DOC VA KIEM TRA DU LIEU
        // ====================================

        $title = $form['title'];
        $leaderId = filter_var(
            $form['leader_id'],
            FILTER_VALIDATE_INT
        );

        $locationId = filter_var(
            $form['location_id'],
            FILTER_VALIDATE_INT
        );

        $price = filter_var(
            $form['price'],
            FILTER_VALIDATE_FLOAT
        );

        $maxSlots = filter_var(
            $form['max_slots'],
            FILTER_VALIDATE_INT
        );

        $duration = $form['duration'];
        $difficulty = $form['difficulty'];
        $altitude = $form['altitude'];
        $distance = $form['distance'];
        $meetingPoint = $form['meeting_point'];
        $transport = $form['transport'];

        $description = $form['description'];
        $schedule = $form['schedule'];
        $includes = $form['includes'];
        $excludes = $form['excludes'];
        $requirements = $form['requirements'];
        $safetyNotes = $form['safety_notes'];

        $imagePath = $form['image'];

        if ($title === '') {
            throw new RuntimeException(
                'Vui lòng nhập tên tour.'
            );
        }

        if (mb_strlen($title) > 255) {
            throw new RuntimeException(
                'Tên tour quá dài.'
            );
        }

        if (!$leaderId || $leaderId <= 0) {
            throw new RuntimeException(
                'Vui lòng chọn Leader phụ trách tour.'
            );
        }

        if (!$locationId || $locationId <= 0) {
            throw new RuntimeException(
                'Vui lòng chọn địa điểm.'
            );
        }

        if ($price === false || $price <= 0) {
            throw new RuntimeException(
                'Giá tour phải lớn hơn 0.'
            );
        }

        if (
            $maxSlots === false ||
            $maxSlots < 1 ||
            $maxSlots > 100000
        ) {
            throw new RuntimeException(
                'Số chỗ tối đa không hợp lệ.'
            );
        }

        $validDifficulties = [
            'Dễ',
            'Vừa phải',
            'Thử thách',
            'Khắc nghiệt'
        ];

        if (!in_array(
            $difficulty,
            $validDifficulties,
            true
        )) {
            throw new RuntimeException(
                'Độ khó không hợp lệ.'
            );
        }

        // ====================================
        // KIEM TRA ANH DUOC CHON
        // ====================================

        $allowedImages = array_column(
            $availableImages,
            'path'
        );

        if (!in_array(
            $imagePath,
            $allowedImages,
            true
        )) {
            throw new RuntimeException(
                'Vui lòng chọn ảnh tour có sẵn trong thư viện.'
            );
        }

        // Khong upload file vao Vercel.
        // Chi luu duong dan anh co san
        // trong GitHub vao database.

        // ====================================
        // KIEM TRA LEADER
        // ====================================

        $guideStmt = $pdo->prepare("
            SELECT fullname
            FROM trekking_leaders
            WHERE id = ?
              AND status = 1
            LIMIT 1
        ");

        $guideStmt->execute([$leaderId]);

        $guideName = $guideStmt->fetchColumn();

        if (!$guideName) {
            throw new RuntimeException(
                'Leader không hợp lệ hoặc đang bị ẩn.'
            );
        }

        // ====================================
        // KIEM TRA DIA DIEM
        // ====================================

        $locationStmt = $pdo->prepare("
            SELECT id
            FROM locations
            WHERE id = ?
            LIMIT 1
        ");

        $locationStmt->execute([$locationId]);

        if (!$locationStmt->fetchColumn()) {
            throw new RuntimeException(
                'Địa điểm không hợp lệ.'
            );
        }

        // ====================================
        // TAO TOUR
        // ====================================

        $stmt = $pdo->prepare("
            INSERT INTO tours (
                title,
                location_id,
                leader_id,
                tour_guide,
                price,
                duration,
                difficulty,
                altitude,
                distance,
                max_slots,
                available_slots,
                meeting_point,
                transport,
                image,
                description,
                schedule,
                includes,
                excludes,
                requirements,
                safety_notes,
                status
            )
            VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                1
            )
        ");

        $stmt->execute([
            $title,
            $locationId,
            $leaderId,
            $guideName,
            $price,
            $duration,
            $difficulty,
            $altitude,
            $distance,
            $maxSlots,
            $maxSlots,
            $meetingPoint,
            $transport,
            $imagePath,
            $description,
            $schedule,
            $includes,
            $excludes,
            $requirements,
            $safetyNotes
        ]);

        // ====================================
        // CHUYEN VE DANH SACH
        // ====================================

        header(
            'Location: index.php?msg=added',
            true,
            303
        );

        exit;

    } catch (Throwable $e) {
        error_log('Add tour: ' . $e->getMessage());

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể tạo tour. Vui lòng thử lại.';
    }
}

$difficulties = [
    'Dễ',
    'Vừa phải',
    'Thử thách',
    'Khắc nghiệt'
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Thêm Tour Mới - Admin</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/style.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/admin.css"
>

<style>
* {
    box-sizing: border-box;
}

.tour-add-page {
    min-height: 100vh;
    padding: 30px 18px 60px;
    background: #f2f5fa;
}

.tour-add-panel {
    max-width: 1060px;
    margin: 0 auto;
    padding: 30px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(15,23,42,.04);
}

.tour-add-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    padding-bottom: 19px;
    margin-bottom: 22px;
    border-bottom: 1px solid #e2e8f0;
}

.tour-add-header h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: #102e52;
}

.tour-add-section {
    margin-bottom: 27px;
}

.tour-add-section h2 {
    margin: 0 0 17px;
    color: #102e52;
    font-size: 18px;
    font-weight: 800;
}

.tour-add-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.tour-add-grid.three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.tour-add-field {
    min-width: 0;
    margin-bottom: 16px;
}

.tour-add-field label {
    display: block;
    margin-bottom: 8px;
    color: #183453;
    font-weight: 750;
    font-size: 13px;
}

.tour-add-input {
    display: block;
    width: 100%;
    padding: 12px 13px;
    background: #fff;
    color: #19314e;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font: inherit;
    font-size: 13px;
}

.tour-add-input:focus {
    outline: none;
    border-color: #166534;
    box-shadow: 0 0 0 3px rgba(22,101,52,.09);
}

textarea.tour-add-input {
    min-height: 105px;
    resize: vertical;
    line-height: 1.7;
}

.tour-add-leader {
    padding: 17px;
    margin-bottom: 23px;
    border: 1px solid #bbf7d0;
    border-radius: 11px;
    background: #f0fdf4;
}

.tour-add-leader label {
    font-weight: 800;
    color: #166534;
}

.tour-add-note {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.7;
}

.tour-add-error {
    padding: 15px 17px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
    border-radius: 10px;
    background: #fef2f2;
    color: #991b1b;
    font-size: 13px;
}

.tour-add-gallery {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.tour-photo {
    position: relative;
    display: block;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    background: white;
}

.tour-photo:hover {
    border-color: #86efac;
}

.tour-photo:has(input:checked) {
    border-color: #166534;
    background: #f0fdf4;
    box-shadow: 0 0 0 2px rgba(22,101,52,.12);
}

.tour-photo input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.tour-photo img {
    display: block;
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    background: #f1f5f9;
}

.tour-photo-name {
    display: block;
    padding: 10px;
    color: #334155;
    font-size: 12px;
    font-weight: 750;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    text-align: center;
}

.tour-photo-check {
    position: absolute;
    right: 9px;
    top: 9px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: white;
    color: #94a3b8;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 2px 8px #0002;
}

.tour-photo:has(input:checked) .tour-photo-check {
    background: #166534;
    color: white;
}

.tour-add-no-images {
    padding: 23px;
    border-radius: 11px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 13px;
    line-height: 1.9;
}

.tour-add-selected {
    margin-top: 15px;
    padding: 12px 15px;
    border-radius: 10px;
    background: #f0fdf4;
    color: #166534;
    font-size: 13px;
    font-weight: 750;
}

.tour-add-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 28px;
}

.tour-add-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 13px 21px;
    border: 0;
    border-radius: 9px;
    background: #166534;
    color: white !important;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
}

.tour-add-btn:hover {
    background: #14532d;
}

.tour-add-btn.gray {
    background: #e2e8f0;
    color: #334155 !important;
}

.tour-add-btn:disabled {
    opacity: .55;
    cursor: not-allowed;
}

@media(max-width:850px) {
    .tour-add-gallery {
        grid-template-columns: repeat(3, minmax(0,1fr));
    }

    .tour-add-grid.three {
        grid-template-columns: repeat(2, minmax(0,1fr));
    }
}

@media(max-width:650px) {
    .tour-add-page {
        padding: 12px;
    }

    .tour-add-panel {
        padding: 17px;
    }

    .tour-add-grid,
    .tour-add-grid.three {
        grid-template-columns: 1fr;
    }

    .tour-add-gallery {
        grid-template-columns: repeat(2, minmax(0,1fr));
    }

    .tour-add-header h1 {
        font-size: 19px;
    }
}
</style>
</head>

<body class="admin-body">

<main class="admin-main tour-add-page">

    <section class="tour-add-panel">

        <div class="tour-add-header">

            <h1>
                <i class="fa-solid fa-person-hiking"></i>
                THÊM TOUR TREKKING MỚI
            </h1>

            <a
                href="index.php"
                class="tour-add-btn gray"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Quay lại danh sách
            </a>

        </div>

        <?php if ($error !== ''): ?>

            <div class="tour-add-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= tourAddH($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" id="tourAddForm">

            <input
                type="hidden"
                name="csrf"
                value="<?= tourAddH($_SESSION['tour_add_csrf']) ?>"
            >

            <!-- LEADER -->

            <div class="tour-add-leader tour-add-field">

                <label for="leader_id">
                    <i class="fa-solid fa-person-hiking"></i>
                    Leader cố định cho tour *
                </label>

                <select
                    name="leader_id"
                    id="leader_id"
                    class="tour-add-input"
                    required
                >
                    <option value="">
                        -- Chọn hướng dẫn viên --
                    </option>

                    <?php foreach ($leaderOptions as $leader): ?>

                        <option
                            value="<?= (int)$leader['id'] ?>"
                            <?= (string)$form['leader_id'] ===
                                (string)$leader['id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= tourAddH($leader['fullname']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <p class="tour-add-note">
                    Leader được chọn sẽ phụ trách cố định
                    cho tất cả các đợt khởi hành của tour.
                </p>

            </div>

            <!-- THONG TIN CO BAN -->

            <div class="tour-add-section">

                <h2>Thông tin cơ bản</h2>

                <div class="tour-add-field">

                    <label for="title">
                        Tên Tour *
                    </label>

                    <input
                        class="tour-add-input"
                        type="text"
                        name="title"
                        id="title"
                        maxlength="255"
                        required
                        placeholder="Chinh phục đỉnh núi..."
                        value="<?= tourAddH($form['title']) ?>"
                    >

                </div>

                <div class="tour-add-grid">

                    <div class="tour-add-field">

                        <label for="location_id">
                            Địa điểm *
                        </label>

                        <select
                            class="tour-add-input"
                            name="location_id"
                            id="location_id"
                            required
                        >
                            <option value="">
                                -- Chọn địa điểm --
                            </option>

                            <?php foreach ($locations as $location): ?>

                                <option
                                    value="<?= (int)$location['id'] ?>"
                                    <?= (string)$form['location_id'] ===
                                        (string)$location['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= tourAddH($location['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="tour-add-field">

                        <label for="price">
                            Giá tour (VNĐ) *
                        </label>

                        <input
                            type="number"
                            class="tour-add-input"
                            name="price"
                            id="price"
                            required
                            min="1"
                            step="1"
                            placeholder="3500000"
                            value="<?= tourAddH($form['price']) ?>"
                        >

                    </div>

                </div>

                <div class="tour-add-grid three">

                    <div class="tour-add-field">

                        <label for="duration">Thời lượng</label>

                        <input
                            type="text"
                            name="duration"
                            id="duration"
                            class="tour-add-input"
                            value="<?= tourAddH($form['duration']) ?>"
                        >

                    </div>

                    <div class="tour-add-field">

                        <label for="difficulty">Độ khó</label>

                        <select
                            name="difficulty"
                            id="difficulty"
                            class="tour-add-input"
                        >

                            <?php foreach ($difficulties as $difficulty): ?>

                                <option
                                    value="<?= tourAddH($difficulty) ?>"
                                    <?= $form['difficulty'] === $difficulty
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= tourAddH($difficulty) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="tour-add-field">

                        <label for="max_slots">
                            Số chỗ tối đa
                        </label>

                        <input
                            type="number"
                            name="max_slots"
                            id="max_slots"
                            class="tour-add-input"
                            min="1"
                            required
                            value="<?= tourAddH($form['max_slots']) ?>"
                        >

                    </div>

                </div>

                <div class="tour-add-grid three">

                    <div class="tour-add-field">

                        <label for="altitude">Độ cao</label>

                        <input
                            class="tour-add-input"
                            name="altitude"
                            id="altitude"
                            value="<?= tourAddH($form['altitude']) ?>"
                        >

                    </div>

                    <div class="tour-add-field">

                        <label for="distance">Quãng đường</label>

                        <input
                            class="tour-add-input"
                            name="distance"
                            id="distance"
                            value="<?= tourAddH($form['distance']) ?>"
                        >

                    </div>

                    <div class="tour-add-field">

                        <label for="transport">
                            Phương tiện di chuyển
                        </label>

                        <input
                            class="tour-add-input"
                            name="transport"
                            id="transport"
                            value="<?= tourAddH($form['transport']) ?>"
                        >

                    </div>

                </div>

                <div class="tour-add-field">

                    <label for="meeting_point">
                        Điểm tập trung
                    </label>

                    <input
                        class="tour-add-input"
                        name="meeting_point"
                        id="meeting_point"
                        value="<?= tourAddH($form['meeting_point']) ?>"
                    >

                </div>

            </div>

            <!-- CHON ANH TOUR -->

            <div class="tour-add-section">

                <h2>
                    <i class="fa-regular fa-images"></i>
                    Chọn ảnh đại diện tour
                </h2>

                <p class="tour-add-note" style="margin-bottom:16px">
                    Ảnh được lấy trực tiếp từ thư mục
                    <strong>assets/images/tours/</strong>
                    trong mã nguồn GitHub.
                    Bạn chỉ cần tải ảnh lên GitHub trước,
                    sau đó chọn ảnh muốn sử dụng bên dưới.
                </p>

                <?php if ($availableImages): ?>

                    <div class="tour-add-gallery">

                        <?php foreach ($availableImages as $photo): ?>

                            <label class="tour-photo">

                                <input
                                    type="radio"
                                    name="image"
                                    value="<?= tourAddH($photo['path']) ?>"
                                    <?= $form['image'] === $photo['path']
                                        ? 'checked'
                                        : '' ?>
                                    required
                                >

                                <img
                                    src="<?= tourAddH($photo['path']) ?>"
                                    alt="<?= tourAddH($photo['name']) ?>"
                                    loading="lazy"
                                >

                                <span class="tour-photo-check">
                                    <i class="fa-solid fa-check"></i>
                                </span>

                                <span
                                    class="tour-photo-name"
                                    title="<?= tourAddH($photo['name']) ?>"
                                >
                                    <?= tourAddH($photo['name']) ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                    <div class="tour-add-selected">

                        <i class="fa-solid fa-circle-check"></i>

                        Ảnh đã chọn:

                        <strong id="selectedTourImage">
                            <?= $form['image'] !== ''
                                ? tourAddH(
                                    basename(
                                        rawurldecode($form['image'])
                                    )
                                )
                                : 'Chưa chọn ảnh' ?>
                        </strong>

                    </div>

                <?php else: ?>

                    <div class="tour-add-no-images">

                        <strong>
                            Chưa có ảnh tour trong thư viện.
                        </strong>

                        <p>
                            Vào GitHub, mở thư mục
                            <code>assets/images/tours/</code>,
                            tải ảnh JPG, PNG hoặc WebP vào đó.
                        </p>

                        <p>
                            Sau khi Vercel triển khai xong,
                            tải lại trang Thêm tour để chọn ảnh.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

            <!-- NOI DUNG TOUR -->

            <div class="tour-add-section">

                <h2>Nội dung và lịch trình</h2>

                <div class="tour-add-field">

                    <label for="description">
                        Mô tả giới thiệu tour
                    </label>

                    <textarea
                        class="tour-add-input"
                        name="description"
                        id="description"
                        rows="4"
                    ><?= tourAddH($form['description']) ?></textarea>

                </div>

                <div class="tour-add-field">

                    <label for="schedule">
                        Lịch trình chi tiết
                    </label>

                    <textarea
                        class="tour-add-input"
                        name="schedule"
                        id="schedule"
                        rows="6"
                    ><?= tourAddH($form['schedule']) ?></textarea>

                </div>

                <div class="tour-add-grid">

                    <div class="tour-add-field">

                        <label
                            for="includes"
                            style="color:#16a34a"
                        >
                            Dịch vụ bao gồm (mỗi ý 1 dòng)
                        </label>

                        <textarea
                            class="tour-add-input"
                            name="includes"
                            id="includes"
                            rows="5"
                            placeholder="Xe đưa đón khứ hồi&#10;Porter gùi đồ&#10;Bảo hiểm..."
                        ><?= tourAddH($form['includes']) ?></textarea>

                    </div>

                    <div class="tour-add-field">

                        <label
                            for="excludes"
                            style="color:#dc2626"
                        >
                            Không bao gồm (mỗi ý 1 dòng)
                        </label>

                        <textarea
                            class="tour-add-input"
                            name="excludes"
                            id="excludes"
                            rows="5"
                            placeholder="Chi phí cá nhân&#10;Đồ uống ngoài thực đơn..."
                        ><?= tourAddH($form['excludes']) ?></textarea>

                    </div>

                </div>

                <div class="tour-add-grid">

                    <div class="tour-add-field">

                        <label
                            for="requirements"
                            style="color:#ea580c"
                        >
                            Đồ khách cần chuẩn bị
                        </label>

                        <textarea
                            class="tour-add-input"
                            name="requirements"
                            id="requirements"
                            rows="5"
                            placeholder="Giày trekking&#10;Balo trợ lực..."
                        ><?= tourAddH($form['requirements']) ?></textarea>

                    </div>

                    <div class="tour-add-field">

                        <label
                            for="safety_notes"
                            style="color:#a16207"
                        >
                            Lưu ý hoàn / hủy tour
                        </label>

                        <textarea
                            class="tour-add-input"
                            name="safety_notes"
                            id="safety_notes"
                            rows="5"
                            placeholder="Hủy trước 7 ngày..."
                        ><?= tourAddH($form['safety_notes']) ?></textarea>

                    </div>

                </div>

            </div>

            <div class="tour-add-actions">

                <button
                    type="submit"
                    class="tour-add-btn"
                    id="createTourBtn"
                    <?= !$availableImages ? 'disabled' : '' ?>
                >

                    <i class="fa-solid fa-plus"></i>
                    TẠO TOUR MỚI

                </button>

                <a
                    href="index.php"
                    class="tour-add-btn gray"
                >
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
        'selectedTourImage'
    );

    radios.forEach(function(radio) {

        radio.addEventListener('change', function() {

            if (!radio.checked || !selectedName) {
                return;
            }

            const path = radio.value;

            selectedName.textContent = decodeURIComponent(
                path.split('/').pop()
            );

        });

    });

    const form = document.getElementById('tourAddForm');

    form?.addEventListener('submit', function(event) {

        if (!form.checkValidity()) {
            return;
        }

        const button = document.getElementById(
            'createTourBtn'
        );

        if (button) {
            button.disabled = true;
            button.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> ĐANG TẠO TOUR...';
        }

    });

})();
</script>

</body>
</html>
