<?php
/*
 * VNN Travel - Form them / sua Tour
 * Dung chung cho add.php va edit.php
 *
 * Anh: assets/images/tours/
 * Lich: tour_departures
 */

if (!isset($pdo, $tourFormMode)) {
    http_response_code(500);
    exit('Cấu hình biểu mẫu không hợp lệ.');
}

if (!function_exists('tfH')) {
    function tfH($value): string {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('tfAjaxResponse')) {
    function tfAjaxResponse(bool $ok, string $message): void {
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($ok ? 200 : 422);
        }
        echo json_encode(['success' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (empty($_SESSION['tour_form_csrf'])) {
    $_SESSION['tour_form_csrf'] = bin2hex(random_bytes(32));
}

$isEdit = $tourFormMode === 'edit';
$tourId = $isEdit ? (int)$tourFormId : 0;

date_default_timezone_set('Asia/Ho_Chi_Minh');
$today = date('Y-m-d');

$error = '';
$success = '';

/* ======================================
   THU VIEN ANH CO SAN TREN GITHUB
   ====================================== */

$imageDirectory = dirname(__DIR__, 2) . '/assets/images/tours';
$availableImages = [];

if (is_dir($imageDirectory)) {
    $files = scandir($imageDirectory);

    foreach ($files ?: [] as $file) {
        if (!preg_match('/\.(jpg|jpeg|png|webp)$/i', $file)) {
            continue;
        }

        if (!is_file($imageDirectory . '/' . $file)) {
            continue;
        }

        $availableImages[] = [
            'name' => $file,
            'path' => '/assets/images/tours/' . rawurlencode($file)
        ];
    }

    usort(
        $availableImages,
        static fn($a, $b) =>
            strnatcasecmp($a['name'], $b['name'])
    );
}

$allowedImages = array_column($availableImages, 'path');

/* ======================================
   DU LIEU DANH MUC
   ====================================== */

$locations = [];
$leaders = [];

try {
    $locations = $pdo->query("
        SELECT id, name
        FROM locations
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $leaders = $pdo->query("
        SELECT id, fullname, status
        FROM trekking_leaders
        ORDER BY fullname
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Tour options: ' . $e->getMessage());
    $error = 'Không thể tải danh sách địa điểm hoặc Leader.';
}

/* ======================================
   DU LIEU TOUR
   ====================================== */

$defaults = [
    'title' => '',
    'location_id' => '',
    'leader_id' => '',
    'price' => '',
    'duration' => '2N1Đ',
    'difficulty' => 'Vừa phải',
    'altitude' => '',
    'distance' => '',
    'max_slots' => '15',
    'available_slots' => '15',
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

$tour = $defaults;
$departures = [];

if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM tours
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$tourId]);

    $existingTour = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingTour) {
        http_response_code(404);
        exit('Không tìm thấy tour.');
    }

    $tour = array_merge($tour, $existingTour);

    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.departure_date,
            d.available_slots,
            d.status,
            COALESCE((
                SELECT SUM(b.number_people)
                FROM tour_bookings b
                WHERE b.tour_id = d.tour_id
                  AND b.departure_date = d.departure_date
                  AND LOWER(TRIM(b.status)) IN ('đã xác nhận', 'đã thanh toán', 'đã hoàn thành', 'confirmed', 'paid', 'completed')
            ), 0) AS booked
        FROM tour_departures d
        WHERE d.tour_id = ?
        ORDER BY d.departure_date ASC, d.id ASC
    ");

    $stmt->execute([$tourId]);
    $departures = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ======================================
   CAC TRUONG BIEU MAU
   ====================================== */

$fields = [
    'title',
    'location_id',
    'leader_id',
    'price',
    'duration',
    'difficulty',
    'altitude',
    'distance',
    'max_slots',
    'meeting_point',
    'transport',
    'description',
    'schedule',
    'includes',
    'excludes',
    'requirements',
    'safety_notes'
];

$labels = [
    'title' => 'Tên tour *',
    'location_id' => 'Địa điểm *',
    'leader_id' => 'Leader cố định *',
    'price' => 'Giá tour (VNĐ) *',
    'duration' => 'Thời lượng',
    'difficulty' => 'Độ khó',
    'altitude' => 'Độ cao',
    'distance' => 'Quãng đường',
    'max_slots' => 'Tổng số chỗ',
    'meeting_point' => 'Điểm đón',
    'transport' => 'Phương tiện đưa đón',
    'description' => 'Mô tả giới thiệu tour',
    'schedule' => 'Lịch trình chi tiết từng ngày',
    'includes' => 'Dịch vụ bao gồm',
    'excludes' => 'Không bao gồm',
    'requirements' => 'Đồ khách cần chuẩn bị',
    'safety_notes' => 'Lưu ý và chính sách hoàn/hủy'
];

$textareas = [
    'description',
    'schedule',
    'includes',
    'excludes',
    'requirements',
    'safety_notes'
];

$difficulties = [
    'Dễ',
    'Vừa phải',
    'Thử thách',
    'Khắc nghiệt'
];

/* ======================================
   LUU TOUR VA LICH KHOI HANH
   ====================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            (string)$_SESSION['tour_form_csrf'],
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ. Hãy tải lại trang.'
            );
        }

        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';
            $tour[$field] = is_string($value)
                ? trim($value)
                : '';
        }

        $selectedImage = trim((string)($_POST['image'] ?? ''));

        if ($selectedImage !== '') {
            $tour['image'] = $selectedImage;
        }

        $leaderId = filter_var(
            $tour['leader_id'],
            FILTER_VALIDATE_INT
        );

        $locationId = filter_var(
            $tour['location_id'],
            FILTER_VALIDATE_INT
        );

        $maxSlots = filter_var(
            $tour['max_slots'],
            FILTER_VALIDATE_INT
        );

        $availableSlots = $maxSlots; // Khong nhap tay: suc chua tu tong so cho

        $price = filter_var(
            $tour['price'],
            FILTER_VALIDATE_FLOAT
        );

        if ($tour['title'] === '' || mb_strlen($tour['title']) > 255) {
            throw new RuntimeException('Tên tour không hợp lệ.');
        }

        if (!$locationId || $locationId <= 0) {
            throw new RuntimeException('Hãy chọn địa điểm.');
        }

        if (!$leaderId || $leaderId <= 0) {
            throw new RuntimeException('Hãy chọn Leader.');
        }

        if ($price === false || $price <= 0) {
            throw new RuntimeException('Giá tour phải lớn hơn 0.');
        }

        if ($maxSlots === false || $maxSlots < 1) {
            throw new RuntimeException('Tổng số chỗ phải lớn hơn 0.');
        }


        if (!in_array($tour['difficulty'], $difficulties, true)) {
            throw new RuntimeException('Độ khó không hợp lệ.');
        }

        // Khong cho luu anh khong co trong thu vien.
        // Khi sua tour, van chap nhan giu anh cu.
        if ($selectedImage !== '' &&
            !in_array($selectedImage, $allowedImages, true)) {
            throw new RuntimeException(
                'Ảnh được chọn không có trong thư viện.'
            );
        }

        if (!$isEdit && $selectedImage === '') {
            throw new RuntimeException(
                'Vui lòng chọn ảnh đại diện tour.'
            );
        }

        $checkLeader = $pdo->prepare("
            SELECT fullname
            FROM trekking_leaders
            WHERE id = ?
            LIMIT 1
        ");
        $checkLeader->execute([$leaderId]);
        $leaderName = $checkLeader->fetchColumn();

        if (!$leaderName) {
            throw new RuntimeException('Leader không tồn tại.');
        }

        // Chi cho phep Leader dang an neu do
        // chinh la Leader da duoc phan cong tu truoc.
        $leaderRecord = null;

        foreach ($leaders as $leader) {
            if ((int)$leader['id'] === (int)$leaderId) {
                $leaderRecord = $leader;
                break;
            }
        }

        if (
            !$leaderRecord ||
            (
                (int)$leaderRecord['status'] !== 1 &&
                (!$isEdit ||
                 (int)$existingTour['leader_id'] !== (int)$leaderId)
            )
        ) {
            throw new RuntimeException(
                'Leader đã chọn hiện không hoạt động.'
            );
        }

        $checkLocation = $pdo->prepare("
            SELECT id FROM locations
            WHERE id = ?
            LIMIT 1
        ");
        $checkLocation->execute([$locationId]);

        if (!$checkLocation->fetchColumn()) {
            throw new RuntimeException('Địa điểm không tồn tại.');
        }

        /* ==================================
           DOC NGAY KHOI HANH MOI
           ================================== */

        $newDates = $_POST['new_departure_date'] ?? [];

        if (!is_array($newDates)) {
            throw new RuntimeException('Dữ liệu lịch khởi hành không hợp lệ.');
        }

        if (count($newDates) > 100) {
            throw new RuntimeException(
                'Chỉ được thêm tối đa 100 ngày trong một lần.'
            );
        }

        $newDepartures = [];
        $seenDates = [];

        foreach ($newDates as $index => $date) {
            $date = trim((string)$date);

            if ($date === '') {
                continue;
            }

            $parsed = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date
            );

            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new RuntimeException(
                    'Ngày khởi hành không hợp lệ.'
                );
            }

            if ($date < $today) {
                throw new RuntimeException(
                    'Không thể tạo đợt khởi hành trong quá khứ.'
                );
            }

            if (isset($seenDates[$date])) {
                throw new RuntimeException(
                    'Bạn đang nhập trùng ngày ' .
                    date('d/m/Y', strtotime($date))
                );
            }

            $seenDates[$date] = true;

            $capacity = $maxSlots;

            $newDepartures[] = [
                'date' => $date,
                'capacity' => $capacity
            ];
        }

        if (!$isEdit && !$newDepartures) {
            throw new RuntimeException(
                'Vui lòng thêm ít nhất một ngày khởi hành.'
            );
        }

        /* ==================================
           TRANSACTION
           ================================== */

        $pdo->beginTransaction();

        if ($isEdit) {
            $update = $pdo->prepare("
                UPDATE tours SET
                    title = ?,
                    location_id = ?,
                    leader_id = ?,
                    tour_guide = ?,
                    price = ?,
                    duration = ?,
                    difficulty = ?,
                    altitude = ?,
                    distance = ?,
                    max_slots = ?,
                    available_slots = ?,
                    meeting_point = ?,
                    transport = ?,
                    image = ?,
                    description = ?,
                    schedule = ?,
                    includes = ?,
                    excludes = ?,
                    requirements = ?,
                    safety_notes = ?
                WHERE id = ?
            ");

            $update->execute([
                $tour['title'],
                $locationId,
                $leaderId,
                $leaderName,
                $price,
                $tour['duration'],
                $tour['difficulty'],
                $tour['altitude'],
                $tour['distance'],
                $maxSlots,
                $availableSlots,
                $tour['meeting_point'],
                $tour['transport'],
                $tour['image'],
                $tour['description'],
                $tour['schedule'],
                $tour['includes'],
                $tour['excludes'],
                $tour['requirements'],
                $tour['safety_notes'],
                $tourId
            ]);

        } else {
            $insert = $pdo->prepare("
                INSERT INTO tours (
                    title, location_id, leader_id,
                    tour_guide, price, duration,
                    difficulty, altitude, distance,
                    max_slots, available_slots,
                    meeting_point, transport, image,
                    description, schedule, includes,
                    excludes, requirements, safety_notes,
                    status
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
                )
            ");

            $insert->execute([
                $tour['title'],
                $locationId,
                $leaderId,
                $leaderName,
                $price,
                $tour['duration'],
                $tour['difficulty'],
                $tour['altitude'],
                $tour['distance'],
                $maxSlots,
                $availableSlots,
                $tour['meeting_point'],
                $tour['transport'],
                $tour['image'],
                $tour['description'],
                $tour['schedule'],
                $tour['includes'],
                $tour['excludes'],
                $tour['requirements'],
                $tour['safety_notes']
            ]);

            $tourId = (int)$pdo->lastInsertId();

            if ($tourId <= 0) {
                throw new RuntimeException('Không thể tạo tour.');
            }
        }

        /* ==================================
           CAP NHAT DOT KHOI HANH CU
           ================================== */

        if ($isEdit) {
            $postedStatuses = $_POST['departure_status'] ?? [];

            if (!is_array($postedStatuses)) {
                throw new RuntimeException(
                    'Dữ liệu đợt khởi hành không hợp lệ.'
                );
            }

            $getExisting = $pdo->prepare("
                SELECT id, departure_date, available_slots, status
                FROM tour_departures
                WHERE tour_id = ?
                FOR UPDATE
            ");

            $getExisting->execute([$tourId]);
            $currentDepartures = $getExisting->fetchAll(
                PDO::FETCH_ASSOC
            );

            $bookedQuery = $pdo->prepare("
                SELECT COALESCE(SUM(number_people), 0)
                FROM tour_bookings
                WHERE tour_id = ?
                  AND departure_date = ?
                  AND LOWER(TRIM(status)) IN ('đã xác nhận', 'đã thanh toán', 'đã hoàn thành', 'confirmed', 'paid', 'completed')
            ");

            $updateDeparture = $pdo->prepare("
                UPDATE tour_departures
                SET available_slots = ?, status = ?
                WHERE id = ? AND tour_id = ?
            ");

            foreach ($currentDepartures as $departure) {
                $depId = (int)$departure['id'];

                if (
                    !array_key_exists($depId, $postedStatuses)
                ) {
                    throw new RuntimeException(
                        'Thiếu thông tin của một đợt khởi hành.'
                    );
                }

                $capacity = $maxSlots;

                $status = (string)$postedStatuses[$depId];


                if (!in_array($status, ['0', '1'], true)) {
                    throw new RuntimeException(
                        'Trạng thái đợt khởi hành không hợp lệ.'
                    );
                }

                $bookedQuery->execute([
                    $tourId,
                    $departure['departure_date']
                ]);

                $booked = (int)$bookedQuery->fetchColumn();

                if ($capacity < $booked) {
                    throw new RuntimeException(
                        'Ngày ' .
                        date(
                            'd/m/Y',
                            strtotime($departure['departure_date'])
                        ) .
                        ' đã có ' . $booked .
                        ' khách đăng ký. Không thể giảm xuống ' .
                        $capacity . ' chỗ.'
                    );
                }

                $updateDeparture->execute([
                    $capacity,
                    (int)$status,
                    $depId,
                    $tourId
                ]);
            }
        }

        /* ==================================
           THEM DOT KHOI HANH MOI
           ================================== */

        $checkDate = $pdo->prepare("
            SELECT COUNT(*)
            FROM tour_departures
            WHERE tour_id = ?
              AND departure_date = ?
        ");

        $insertDeparture = $pdo->prepare("
            INSERT INTO tour_departures (
                tour_id,
                departure_date,
                available_slots,
                status
            )
            VALUES (?, ?, ?, 1)
        ");

        foreach ($newDepartures as $departure) {
            $checkDate->execute([
                $tourId,
                $departure['date']
            ]);

            if ((int)$checkDate->fetchColumn() > 0) {
                throw new RuntimeException(
                    'Ngày khởi hành ' .
                    date('d/m/Y', strtotime($departure['date'])) .
                    ' đã tồn tại trong tour.'
                );
            }

            $insertDeparture->execute([
                $tourId,
                $departure['date'],
                $departure['capacity']
            ]);
        }

        $pdo->commit();

        tfAjaxResponse(true, $isEdit
            ? 'Đã lưu thay đổi tour và lịch khởi hành.'
            : 'Đã tạo tour và lịch khởi hành thành công.');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Tour form: ' . $e->getMessage());

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể lưu tour. Vui lòng thử lại.';
        tfAjaxResponse(false, $error);
    }
}

/* ======================================
   GIAO DIEN
   ====================================== */
?>
<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= tfH($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
* {
    box-sizing: border-box;
}

.tf-page {
    min-height: 100vh;
    background: #f2f5fa;
    padding: 25px 18px 60px;
    color: #152f51;
    font-size: 14px;
}

.tf-panel {
    max-width: 1120px;
    margin: 0 auto;
    padding: 25px;
    background: white;
    border: 1px solid #e0e7ef;
    border-radius: 15px;
}

.tf-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    padding-bottom: 18px;
    margin-bottom: 24px;
    border-bottom: 1px solid #e2e8f0;
}

.tf-head h1 {
    font-size: 21px;
    margin: 0;
    color: #102d51;
}

.tf-section {
    margin: 25px 0;
}

.tf-section h2 {
    font-size: 18px;
    color: #102d51;
    margin: 0 0 18px;
}

.tf-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.tf-grid.three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.tf-field {
    min-width: 0;
    margin-bottom: 15px;
}

.tf-field.full {
    grid-column: 1 / -1;
}

.tf-field label {
    display: block;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 7px;
    color: #183556;
}

.tf-input {
    display: block;
    width: 100%;
    min-height: 45px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 11px 13px;
    background: white;
    color: #153451;
    font: inherit;
    font-size: 13px;
}

.tf-input:focus {
    outline: none;
    border-color: #166534;
    box-shadow: 0 0 0 3px rgba(22,101,52,.09);
}

textarea.tf-input {
    min-height: 125px;
    resize: vertical;
    line-height: 1.7;
}

.tf-leader {
    padding: 17px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
}

.tf-note {
    color: #64748b;
    font-size: 12px;
    line-height: 1.7;
    margin: 9px 0 0;
}

.tf-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 43px;
    border: none;
    border-radius: 9px;
    padding: 11px 17px;
    background: #166534;
    color: white !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.tf-btn:hover {
    background: #14532d;
}

.tf-btn.gray {
    color: #334155 !important;
    background: #e2e8f0;
}

.tf-btn.blue {
    background: #1d4ed8;
}

.tf-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.tf-error {
    padding: 15px;
    border-radius: 10px;
    color: #991b1b;
    background: #fee2e2;
    margin-bottom: 17px;
}

.tf-photo-tools {
    display: flex;
    align-items: center;
    gap: 11px;
    flex-wrap: wrap;
    margin-bottom: 17px;
}

.tf-photo-tools input {
    flex: 1;
    min-width: 200px;
}

.tf-gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 13px;
}

.tf-photo {
    display: block;
    position: relative;
    border: 2px solid #e2e8f0;
    border-radius: 11px;
    overflow: hidden;
    cursor: pointer;
    background: white;
}

.tf-photo:has(input:checked) {
    border-color: #166534;
    background: #f0fdf4;
}

.tf-photo input {
    position: absolute;
    opacity: 0;
    width: 1px;
    height: 1px;
}

.tf-photo img {
    display: block;
    width: 100%;
    height: 135px;
    object-fit: cover;
    background: #f1f5f9;
}

.tf-photo-name {
    display: block;
    padding: 10px;
    font-size: 12px;
    color: #334155;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tf-photo-check {
    position: absolute;
    right: 8px;
    top: 8px;
    padding: 7px;
    border-radius: 50%;
    background: #166534;
    color: #fff;
    display: none;
}

.tf-photo:has(input:checked) .tf-photo-check {
    display: block;
}

.tf-selected {
    margin-top: 15px;
    padding: 13px;
    border-radius: 9px;
    background: #f0fdf4;
    color: #166534;
    font-size: 13px;
    overflow-wrap: anywhere;
}

.tf-current-image {
    display: block;
    max-width: 220px;
    max-height: 140px;
    object-fit: cover;
    border-radius: 10px;
    margin: 10px 0;
}

.tf-departure {
    display: grid;
    grid-template-columns: minmax(150px, 1.3fr)
                           minmax(120px, .8fr)
                           minmax(135px, .8fr)
                           minmax(90px, .8fr);
    gap: 12px;
    align-items: end;
    margin-bottom: 12px;
    padding: 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.tf-departure label {
    display: block;
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 6px;
}

.tf-departure .tf-info {
    padding: 12px 0;
    font-weight: 750;
    color: #102d51;
}

.tf-remove {
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #dc2626;
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
}

.tf-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 25px;
}

.tf-empty {
    color: #64748b;
    font-size: 13px;
    padding: 17px;
    border: 1px dashed #cbd5e1;
    border-radius: 9px;
    margin-bottom: 13px;
}

.tf-prompt {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 999999;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15,23,42,.56);
}

.tf-prompt.open {
    display: flex;
}

.tf-prompt-box {
    width: min(100%, 420px);
    background: white;
    border-radius: 17px;
    padding: 27px;
    text-align: center;
    box-shadow: 0 20px 60px #0002;
}

.tf-prompt-box h2 {
    color: #102d51;
    font-size: 20px;
}

.tf-prompt-box p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

@media(max-width:850px) {
    .tf-grid.three {
        grid-template-columns: 1fr 1fr;
    }

    .tf-gallery {
        grid-template-columns: 1fr 1fr;
    }

    .tf-departure {
        grid-template-columns: 1fr 1fr;
    }
}

@media(max-width:600px) {
    .tf-page {
        padding: 12px;
    }

    .tf-panel {
        padding: 15px;
    }

    .tf-grid,
    .tf-grid.three {
        grid-template-columns: 1fr;
    }

    .tf-gallery {
        grid-template-columns: 1fr 1fr;
    }

    .tf-departure {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="admin-body">

<main class="admin-main tf-page">

<section class="tf-panel">

    <div class="tf-head">
        <h1>
            <i class="fa-solid fa-person-hiking"></i>
            <?= $isEdit ? 'CHỈNH SỬA TOUR TREKKING' : 'THÊM TOUR TREKKING MỚI' ?>
        </h1>

        <a href="index.php" class="tf-btn gray">
            <i class="fa-solid fa-arrow-left"></i>
            Quay lại
        </a>
    </div>

    <?php if ($error): ?>
        <div class="tf-error" role="alert">
            <?= tfH($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="tourForm">

        <input type="hidden"
               name="csrf"
               value="<?= tfH($_SESSION['tour_form_csrf']) ?>">

        <input type="hidden"
               name="tour_id"
               value="<?= (int)$tourId ?>">

        <section class="tf-section">

            <h2>Thông tin tour</h2>

            <div class="tf-field tf-leader">
                <label for="leader_id">
                    Leader cố định phụ trách tour *
                </label>

                <select class="tf-input"
                        name="leader_id"
                        id="leader_id"
                        required>

                    <option value="">-- Chọn Leader --</option>

                    <?php foreach ($leaders as $leader): ?>
                        <?php
                        $selected = (int)$tour['leader_id'] ===
                                    (int)$leader['id'];

                        if ((int)$leader['status'] !== 1 && !$selected) {
                            continue;
                        }
                        ?>

                        <option
                            value="<?= (int)$leader['id'] ?>"
                            <?= $selected ? 'selected' : '' ?>
                        >
                            <?= tfH($leader['fullname']) ?>
                            <?= (int)$leader['status'] !== 1 ? ' (Đang ẩn)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="tf-note">
                    Một tour có một Leader cố định cho tất cả các ngày khởi hành.
                </p>
            </div>

            <div class="tf-field">
                <label>Tên tour *</label>

                <input class="tf-input"
                       type="text"
                       name="title"
                       maxlength="255"
                       required
                       value="<?= tfH($tour['title']) ?>">
            </div>

            <div class="tf-grid">

                <div class="tf-field">
                    <label>Địa điểm *</label>

                    <select class="tf-input" name="location_id" required>
                        <option value="">-- Chọn địa điểm --</option>

                        <?php foreach ($locations as $location): ?>
                            <option
                                value="<?= (int)$location['id'] ?>"
                                <?= (int)$tour['location_id'] === (int)$location['id']
                                    ? 'selected' : '' ?>
                            >
                                <?= tfH($location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tf-field">
                    <label>Giá tour (VNĐ) *</label>

                    <input class="tf-input"
                           type="number"
                           name="price"
                           min="1"
                           step="1"
                           required
                           value="<?= tfH($tour['price']) ?>">
                </div>

            </div>

            <div class="tf-grid three">

                <div class="tf-field">
                    <label>Thời lượng</label>
                    <input class="tf-input"
                           name="duration"
                           value="<?= tfH($tour['duration']) ?>">
                </div>

                <div class="tf-field">
                    <label>Độ khó</label>
                    <select class="tf-input" name="difficulty">
                        <?php foreach ($difficulties as $difficulty): ?>
                            <option value="<?= tfH($difficulty) ?>"
                                <?= $tour['difficulty'] === $difficulty
                                    ? 'selected' : '' ?>>
                                <?= tfH($difficulty) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tf-field">
                    <label>Độ cao</label>
                    <input class="tf-input"
                           name="altitude"
                           value="<?= tfH($tour['altitude']) ?>">
                </div>

                <div class="tf-field">
                    <label>Quãng đường</label>
                    <input class="tf-input"
                           name="distance"
                           value="<?= tfH($tour['distance']) ?>">
                </div>

                <div class="tf-field">
                    <label>Tổng số chỗ</label>
                    <input class="tf-input"
                           type="number"
                           name="max_slots"
                           min="1"
                           required
                           value="<?= tfH($tour['max_slots']) ?>">
                </div>

                <div class="tf-field">
                    <label>Điểm đón</label>
                    <input class="tf-input"
                           name="meeting_point"
                           value="<?= tfH($tour['meeting_point']) ?>">
                </div>

                <div class="tf-field">
                    <label>Phương tiện</label>
                    <input class="tf-input"
                           name="transport"
                           value="<?= tfH($tour['transport']) ?>">
                </div>

            </div>
        </section>

        <!-- =================================
             THU VIEN ANH
             ================================= -->

        <section class="tf-section">

            <h2>
                <i class="fa-regular fa-images"></i>
                Ảnh đại diện tour
            </h2>

            <p class="tf-note">
                Ảnh được lấy từ thư mục
                <strong>assets/images/tours/</strong>
                trên GitHub. Mặc định chỉ hiện 6 ảnh để trang gọn hơn.
            </p>

            <?php if ($isEdit && !empty($tour['image'])): ?>
                <div style="margin:15px 0">
                    <strong>Ảnh hiện tại:</strong>

                    <img class="tf-current-image"
                         src="<?= tfH($tour['image']) ?>"
                         alt="Ảnh tour hiện tại"
                         onerror="this.style.display='none'">
                </div>
            <?php endif; ?>

            <div class="tf-photo-tools">

                <input
                    class="tf-input"
                    type="search"
                    id="photoSearch"
                    placeholder="Tìm kiếm tên ảnh... Ví dụ: fansipan"
                    autocomplete="off"
                >

                <button type="button"
                        class="tf-btn blue"
                        id="toggleImages">
                    Xem tất cả ảnh
                </button>

            </div>

            <?php if ($availableImages): ?>

                <div class="tf-gallery" id="photoGallery">

                    <?php foreach ($availableImages as $image): ?>

                        <label class="tf-photo"
                               data-filename="<?= tfH(mb_strtolower($image['name'])) ?>">

                            <input
                                type="radio"
                                name="image"
                                value="<?= tfH($image['path']) ?>"
                                <?= $tour['image'] === $image['path']
                                    ? 'checked' : '' ?>
                            >

                            <img
                                src="<?= tfH($image['path']) ?>"
                                alt="<?= tfH($image['name']) ?>"
                                loading="lazy"
                            >

                            <span class="tf-photo-check">
                                <i class="fa-solid fa-check"></i>
                            </span>

                            <span class="tf-photo-name"
                                  title="<?= tfH($image['name']) ?>">
                                <?= tfH($image['name']) ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>

                <p class="tf-note" id="photoCount"></p>

                <div class="tf-selected">
                    Ảnh được chọn:
                    <strong id="selectedPhoto">
                        <?= !empty($tour['image'])
                            ? tfH(basename(rawurldecode($tour['image'])))
                            : 'Chưa chọn ảnh' ?>
                    </strong>
                </div>

            <?php else: ?>

                <div class="tf-empty">
                    Chưa có ảnh trong
                    <strong>assets/images/tours/</strong>.
                    Hãy tải ảnh lên GitHub rồi triển khai lại Vercel.
                    <?php if ($isEdit): ?>
                        Bạn vẫn có thể giữ ảnh tour hiện tại.
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </section>

        <!-- =================================
             NGAY KHOI HANH
             ================================= -->

        <section class="tf-section">

            <h2>
                <i class="fa-solid fa-calendar-days"></i>
                Các ngày khởi hành
            </h2>

            <p class="tf-note" style="margin-bottom:16px">
                Mỗi ngày sử dụng tổng số chỗ của tour. Khách chỉ đăng ký được
                các đợt đang mở, chưa qua ngày khởi hành và còn chỗ.
            </p>

            <?php if ($isEdit): ?>

                <h3 style="font-size:15px">
                    Các đợt khởi hành hiện tại
                </h3>

                <?php if (!$departures): ?>
                    <div class="tf-empty">
                        Tour này chưa có ngày khởi hành.
                    </div>
                <?php endif; ?>

                <?php foreach ($departures as $dep): ?>

                    <div class="tf-departure">

                        <div>
                            <label>Ngày khởi hành</label>
                            <div class="tf-info">
                                <?= tfH(date(
                                    'd/m/Y',
                                    strtotime($dep['departure_date'])
                                )) ?>
                            </div>
                        </div>

                        <div>
                            <label>
                                Tổng số chỗ
                                (đã đặt: <?= (int)$dep['booked'] ?>)
                            </label>

                            <div class="tf-info">Theo tổng số chỗ: <?= (int)$tour['max_slots'] ?></div>
                        </div>

                        <div>
                            <label>Trạng thái</label>

                            <select
                                class="tf-input"
                                name="departure_status[<?= (int)$dep['id'] ?>]"
                            >
                                <option value="1"
                                    <?= (int)$dep['status'] === 1
                                        ? 'selected' : '' ?>>
                                    Đang mở
                                </option>

                                <option value="0"
                                    <?= (int)$dep['status'] === 0
                                        ? 'selected' : '' ?>>
                                    Đóng đăng ký
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>Còn nhận được</label>
                            <div class="tf-info">
                                <?= max(
                                    0,
                                    (int)$tour['max_slots'] -
                                    (int)$dep['booked']
                                ) ?> chỗ
                            </div>
                        </div>

                    </div>

                <?php endforeach; ?>

                <p class="tf-note">
                    Nếu không muốn nhận thêm khách vào một ngày,
                    hãy chọn <strong>Đóng đăng ký</strong>.
                    Ngày đã có khách đăng ký sẽ không bị xóa khỏi lịch sử.
                </p>

            <?php endif; ?>

            <h3 style="font-size:15px;margin-top:24px">
                <?= $isEdit
                    ? 'Thêm ngày khởi hành mới'
                    : 'Thiết lập ngày khởi hành đầu tiên' ?>
            </h3>

            <div id="newDepartures">

                <div class="tf-departure tf-new-departure">

                    <div>
                        <label>Ngày khởi hành</label>

                        <input
                            class="tf-input"
                            type="date"
                            name="new_departure_date[]"
                            min="<?= tfH($today) ?>"
                            <?= !$isEdit ? 'required' : '' ?>
                        >
                    </div>

                    <div>
                        <label>Số chỗ</label>

                        <div class="tf-info">Tự động dùng tổng số chỗ của tour</div>
                    </div>

                    <div>
                        <label>Trạng thái</label>
                        <div class="tf-info" style="color:#166534">
                            Tự động mở đăng ký
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            class="tf-remove"
                            onclick="removeDeparture(this)"
                        >
                            <i class="fa-solid fa-xmark"></i>
                            Bỏ dòng
                        </button>
                    </div>

                </div>

            </div>

            <button
                type="button"
                class="tf-btn blue"
                id="addDeparture"
            >
                <i class="fa-solid fa-plus"></i>
                Thêm ngày khởi hành
            </button>

        </section>

        <!-- =================================
             NOI DUNG CHI TIET TOUR
             ================================= -->

        <section class="tf-section">

            <h2>Nội dung và lịch trình</h2>

            <?php foreach ($textareas as $field): ?>

                <div class="tf-field">

                    <label for="field-<?= tfH($field) ?>">
                        <?= tfH($labels[$field]) ?>
                    </label>

                    <textarea
                        class="tf-input"
                        id="field-<?= tfH($field) ?>"
                        name="<?= tfH($field) ?>"
                        rows="<?= $field === 'schedule' ? '7' : '4' ?>"
                    ><?= tfH($tour[$field] ?? '') ?></textarea>

                </div>

            <?php endforeach; ?>

        </section>

        <div class="tf-actions">

            <button
                type="submit"
                class="tf-btn"
                id="saveTour"
            >
                <i class="fa-solid fa-floppy-disk"></i>
                <?= $isEdit ? 'LƯU THAY ĐỔI' : 'TẠO TOUR MỚI' ?>
            </button>

            <a href="index.php" class="tf-btn gray">
                Hủy
            </a>

        </div>

    </form>

</section>
</main>

<!-- POPUP LOI KIEM TRA -->
<div class="tf-prompt" id="errorPopup">
    <div class="tf-prompt-box">
        <h2>Vui lòng kiểm tra lại</h2>
        <p id="errorPopupMessage"></p>
        <button type="button"
                class="tf-btn"
                id="closeErrorPopup">
            Đồng ý
        </button>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('tourForm');
    const search = document.getElementById('photoSearch');
    const gallery = document.getElementById('photoGallery');
    const toggle = document.getElementById('toggleImages');
    const count = document.getElementById('photoCount');
    const selected = document.getElementById('selectedPhoto');

    const cards = gallery
        ? Array.from(gallery.querySelectorAll('.tf-photo'))
        : [];

    let showAll = false;

    function normalize(text) {
        return String(text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('vi');
    }

    function updateGallery() {
        const keyword = normalize(search?.value.trim() || '');
        const matching = cards.filter(function(card) {
            return normalize(card.dataset.filename).includes(keyword);
        });

        // Mac dinh chi 6 anh.
        // Khi tim kiem, hien thi ket qua phu hop.
        const limit = keyword || showAll
            ? matching.length
            : 6;

        cards.forEach(function(card) {
            const index = matching.indexOf(card);
            card.hidden = index < 0 || index >= limit;
            card.style.display = card.hidden ? 'none' : '';
        });

        if (count) {
            count.textContent =
                'Hiển thị ' +
                Math.min(limit, matching.length) +
                '/' + matching.length +
                ' ảnh phù hợp.';
        }

        if (toggle) {
            toggle.textContent = showAll
                ? 'Thu gọn ảnh'
                : 'Xem tất cả ảnh';

            toggle.style.display =
                keyword ? 'none' : '';
        }
    }

    search?.addEventListener('input', updateGallery);

    toggle?.addEventListener('click', function() {
        showAll = !showAll;
        updateGallery();
    });

    gallery?.querySelectorAll(
        'input[name="image"]'
    ).forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (selected && radio.checked) {
                selected.textContent = decodeURIComponent(
                    radio.value.split('/').pop()
                );
            }
        });
    });

    updateGallery();

    // ======================================
    // THEM DONG NGAY KHOI HANH
    // ======================================

    const departureContainer =
        document.getElementById('newDepartures');

    const addButton = document.getElementById('addDeparture');

    addButton?.addEventListener('click', function() {
        const existing = departureContainer.querySelector(
            '.tf-new-departure'
        );

        if (!existing) return;

        const clone = existing.cloneNode(true);

        clone.querySelectorAll('input').forEach(function(input) {
            if (input.type === 'date') {
                input.value = '';
            }
        });

        departureContainer.appendChild(clone);
    });

    window.removeDeparture = function(button) {
        const row = button.closest('.tf-new-departure');

        if (!row) return;

        const rows = departureContainer.querySelectorAll(
            '.tf-new-departure'
        );

        if (rows.length <= 1) {
            const date = row.querySelector('input[type="date"]');

            if (date) {
                date.value = '';
            }

            return;
        }

        row.remove();
    };

    // ======================================
    // KIEM TRA TRUOC KHI LUU
    // ======================================

    function popup(message) {
        document.getElementById(
            'errorPopupMessage'
        ).textContent = message;

        document.getElementById(
            'errorPopup'
        ).classList.add('open');
    }

    document.getElementById(
        'closeErrorPopup'
    )?.addEventListener('click', function() {
        document.getElementById(
            'errorPopup'
        ).classList.remove('open');
    });

    const save = document.getElementById('saveTour');
    let saving = false;
    const modal = document.createElement('div');
    modal.id = 'tfResultModal';
    modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:999999;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:20px';
    modal.innerHTML = '<div role="dialog" aria-modal="true" style="background:#fff;padding:28px;border-radius:15px;text-align:center;max-width:410px;width:100%;box-shadow:0 20px 60px #0003"><h2 id="tfResultTitle" style="color:#123357;margin:0 0 12px">Thông báo</h2><p id="tfResultText" style="line-height:1.7;color:#475569"></p><button id="tfResultOK" type="button" style="border:0;border-radius:9px;background:#166534;color:white;padding:12px 30px;cursor:pointer;font-weight:700">Đồng ý</button></div>';
    document.body.appendChild(modal);
    let success = false;
    function notice(ok, msg) {
        success = !!ok;
        document.getElementById('tfResultTitle').textContent = ok ? 'Thành công!' : 'Không thành công';
        document.getElementById('tfResultText').textContent = msg;
        modal.style.display = 'flex';
    }
    document.getElementById('tfResultOK').addEventListener('click', function() {
        if (success) window.location.assign('index.php');
        else modal.style.display = 'none';
    });
    form?.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (saving || !form.reportValidity()) return;
        const dates = Array.from(form.querySelectorAll('input[name="new_departure_date[]"]'))
            .map(input => input.value).filter(Boolean);
        if (new Set(dates).size !== dates.length) {
            notice(false, 'Bạn đang chọn trùng ngày khởi hành.');
            return;
        }
        saving = true;
        const original = save?.innerHTML || '';
        if (save) { save.disabled = true; save.textContent = 'ĐANG LƯU...'; }
        try {
            const response = await fetch(location.href, {
                method:'POST', body:new FormData(form), credentials:'same-origin',
                headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
            });
            const raw = await response.text();
            let result;
            try { result = JSON.parse(raw); }
            catch (_) { throw new Error('Máy chủ trả về lỗi PHP hoặc phiên đăng nhập đã hết hạn.'); }
            notice(!!result.success, result.message || 'Không có thông báo.');
        } catch (err) { notice(false, err.message || 'Không thể kết nối máy chủ.'); }
        finally { saving = false; if (save) {save.disabled = false; save.innerHTML = original;} }
    });
})();
</script>

</body>
</html>
