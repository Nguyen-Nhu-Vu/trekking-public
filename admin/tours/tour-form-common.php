
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
   DU LIEU DANH MUC
   ====================================== */

$leaders = [];
try {
    $leaders = $pdo->query("SELECT id, fullname, status FROM trekking_leaders ORDER BY fullname")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Tour leaders: ' . $e->getMessage());
    $error = 'Không thể tải danh sách Leader.';
}

/* ======================================
   DU LIEU TOUR
   ====================================== */

$defaults = [
    'title' => '',
    'location_id' => '',
    'location_name' => '',
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
    'safety_notes' => '',
    'gallery' => ''
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
    $locStmt = $pdo->prepare('SELECT name FROM locations WHERE id = ? LIMIT 1');
    $locStmt->execute([(int)$tour['location_id']]);
    $tour['location_name'] = (string)($locStmt->fetchColumn() ?: '');

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
                  AND LOWER(TRIM(b.status)) NOT IN (
                      'đã hủy',
                      'đã huỷ',
                      'hủy',
                      'huỷ',
                      'cancelled',
                      'canceled',
                      'rejected',
                      'từ chối'
                  )
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
    'location_name',
    'leader_id',
    'price',
    'duration',
    'difficulty',
    'altitude',
    'distance',
    'max_slots',
    'available_slots',
    'meeting_point',
    'transport',
    'description',
    'schedule',
    'includes',
    'excludes',
    'requirements',
    'safety_notes',
    'gallery'
];

$labels = [
    'title' => 'Tên tour *',
    'location_name' => 'Địa điểm *',
    'leader_id' => 'Leader cố định *',
    'price' => 'Giá tour (VNĐ) *',
    'duration' => 'Thời lượng',
    'difficulty' => 'Độ khó',
    'altitude' => 'Độ cao',
    'distance' => 'Quãng đường',
    'max_slots' => 'Tổng số chỗ',
    'available_slots' => 'Số chỗ trống hiển thị',
    'meeting_point' => 'Điểm đón',
    'transport' => 'Phương tiện đưa đón',
    'description' => 'Mô tả giới thiệu tour',
    'schedule' => 'Lịch trình chi tiết từng ngày',
    'includes' => 'Dịch vụ bao gồm',
    'excludes' => 'Không bao gồm',
    'requirements' => 'Đồ khách cần chuẩn bị',
    'safety_notes' => 'Lưu ý và chính sách hoàn/hủy',
    'gallery' => 'Album ảnh Tour (mỗi dòng một URL HTTPS)'
];

$textareas = [
    'description',
    'schedule',
    'includes',
    'excludes',
    'requirements',
    'safety_notes',
    'gallery'
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

        $locationName = trim((string)$tour['location_name']);
        $locationId = 0;

        $maxSlots = filter_var(
            $tour['max_slots'],
            FILTER_VALIDATE_INT
        );

        $availableSlots = filter_var(
            $tour['available_slots'],
            FILTER_VALIDATE_INT
        );

        $price = filter_var(
            $tour['price'],
            FILTER_VALIDATE_FLOAT
        );

        if ($tour['title'] === '' || mb_strlen($tour['title']) > 255) {
            throw new RuntimeException('Tên tour không hợp lệ.');
        }

        if ($locationName === '' || mb_strlen($locationName) > 255) {
            throw new RuntimeException('Vui lòng nhập tên địa điểm (tối đa 255 ký tự).');
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

        if (
            $availableSlots === false ||
            $availableSlots < 0 ||
            $availableSlots > $maxSlots
        ) {
            throw new RuntimeException(
                'Số chỗ trống phải từ 0 đến tổng số chỗ.'
            );
        }

        if (!in_array($tour['difficulty'], $difficulties, true)) {
            throw new RuntimeException('Độ khó không hợp lệ.');
        }

        $selectedImage = trim((string)($tour['image'] ?? ''));
        if ($selectedImage === '') {
            throw new RuntimeException('Vui lòng nhập URL ảnh.');
        }
        if (!filter_var($selectedImage, FILTER_VALIDATE_URL) ||
            !in_array(strtolower((string)parse_url($selectedImage, PHP_URL_SCHEME)), ['http','https'], true)) {
            throw new RuntimeException('URL ảnh phải bắt đầu bằng http:// hoặc https:// và hợp lệ.');
        }
        if (strlen($selectedImage) > 2000) {
            throw new RuntimeException('URL ảnh quá dài.');
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

        /* ==================================
           DOC NGAY KHOI HANH MOI
           ================================== */

        $newDates = $_POST['new_departure_date'] ?? [];
        $newCapacities = $_POST['new_departure_capacity'] ?? [];

        if (!is_array($newDates) || !is_array($newCapacities)) {
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

            $capacity = filter_var(
                $newCapacities[$index] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($capacity === false || $capacity < 1) {
                throw new RuntimeException(
                    'Số chỗ của mỗi ngày khởi hành phải lớn hơn 0.'
                );
            }

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

        // Album ảnh tour, mỗi dòng một URL ảnh công khai.
        $galleryUrls = preg_split('/\r\n|\n|\r/', (string)($tour['gallery'] ?? ''));
        $galleryUrls = array_values(array_filter(array_map('trim', $galleryUrls)));
        if (count($galleryUrls) > 30) {
            throw new RuntimeException('Album tối đa 30 ảnh.');
        }
        foreach ($galleryUrls as $url) {
            $parts = parse_url($url);
            if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['https','http'], true)
                || isset($parts['user']) || isset($parts['pass'])) {
                throw new RuntimeException('URL ảnh trong album không hợp lệ: ' . $url);
            }
        }
        $tour['gallery'] = implode("\n", $galleryUrls);

        /* ==================================
           TRANSACTION
           ================================== */

        $pdo->beginTransaction();

        // Giu bang locations nhu khoa ngoai noi bo, khong con danh muc chon san.
        // Khi sua tour, giu ID cu neu ten dia diem khong thay doi.
        if ($isEdit && (int)$existingTour['location_id'] > 0) {
            $currentLocation = $pdo->prepare('SELECT name FROM locations WHERE id = ? LIMIT 1');
            $currentLocation->execute([(int)$existingTour['location_id']]);
            if (trim((string)$currentLocation->fetchColumn()) === $locationName) {
                $locationId = (int)$existingTour['location_id'];
            }
        }
        if (!$locationId) {
            $findLocation = $pdo->prepare('SELECT id FROM locations WHERE name = ? ORDER BY id ASC LIMIT 1');
            $findLocation->execute([$locationName]);
            $locationId = (int)$findLocation->fetchColumn();
        }
        if (!$locationId) {
            $newLocation = $pdo->prepare("INSERT INTO locations
                (name, region, altitude, difficulty, distance, description, image)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $newLocation->execute([
                $locationName, 'Khác',
                (string)($tour['altitude'] ?: 'Đang cập nhật'),
                (string)($tour['difficulty'] ?: 'Vừa phải'),
                (string)($tour['distance'] ?: 'Đang cập nhật'),
                'Địa điểm của tour ' . $tour['title'],
                'default_location.jpg'
            ]);
            $locationId = (int)$pdo->lastInsertId();
            if (!$locationId) {
                throw new RuntimeException('Không thể tạo địa điểm của tour.');
            }
        }


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
                    safety_notes = ?,
                    gallery = ?
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
                $tour['gallery'],
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
                    gallery, status
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
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
                $tour['safety_notes'],
                $tour['gallery']
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
            $postedCaps = $_POST['departure_capacity'] ?? [];
            $postedStatuses = $_POST['departure_status'] ?? [];

            if (!is_array($postedCaps) || !is_array($postedStatuses)) {
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
                  AND LOWER(TRIM(status)) NOT IN (
                    'đã hủy', 'đã huỷ',
                    'hủy', 'huỷ',
                    'cancelled', 'canceled',
                    'rejected', 'từ chối'
                  )
            ");

            $updateDeparture = $pdo->prepare("
                UPDATE tour_departures
                SET available_slots = ?, status = ?
                WHERE id = ? AND tour_id = ?
            ");

            foreach ($currentDepartures as $departure) {
                $depId = (int)$departure['id'];

                if (
                    !array_key_exists($depId, $postedCaps) ||
                    !array_key_exists($depId, $postedStatuses)
                ) {
                    throw new RuntimeException(
                        'Thiếu thông tin của một đợt khởi hành.'
                    );
                }

                $capacity = filter_var(
                    $postedCaps[$depId],
                    FILTER_VALIDATE_INT
                );

                $status = (string)$postedStatuses[$depId];

                if ($capacity === false || $capacity < 0) {
                    throw new RuntimeException(
                        'Số chỗ của đợt khởi hành không hợp lệ.'
                    );
                }

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

        require_once __DIR__ . '/../includes/audit.php';
        vnnAuditChange(
            $pdo,
            'content',
            $isEdit ? 'Sửa tour' : 'Thêm tour',
            'tours',
            $tourId,
            $isEdit ? 'Thông tin tour cũ' : '',
            'Đã cập nhật thông tin tour và lịch khởi hành'
        );

        $successMessage = $isEdit
            ? 'Cập nhật tour thành công!'
            : 'Thêm tour thành công!';

        if (
            ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')
            === 'XMLHttpRequest'
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            if (!headers_sent()) {
                header(
                    'Content-Type: application/json; charset=utf-8'
                );
            }

            echo json_encode([
                'success' => true,
                'message' => $successMessage
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $_SESSION['tour_form_notice'] = $successMessage;

        if (!headers_sent()) {
            header('Location: index.php', true, 303);
            exit;
        }

        echo '<script>location.replace("index.php")</script>';
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Tour form: ' . $e->getMessage());

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể lưu tour. Vui lòng thử lại.';

        if (
            ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')
            === 'XMLHttpRequest'
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            if (!headers_sent()) {
                header(
                    'Content-Type: application/json; charset=utf-8'
                );
                http_response_code(422);
            }

            echo json_encode([
                'success' => false,
                'message' => $error
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
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

<link rel="stylesheet"
 href="../../assets/css/style.css">

<link rel="stylesheet"
 href="../../assets/css/admin.css">

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

/* Xem truoc album anh; khong thay doi cach luu URL. */
.tf-album-preview-head { margin: 10px 0; font-size: 13px; color: #475569; }
.tf-album-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(145px, 1fr)); gap: 12px; }
.tf-album-item { min-width: 0; background: #f8fafc; border: 1px solid #dbe4ee; border-radius: 10px; overflow: hidden; }
.tf-album-item img { display: block; width: 100%; height: 130px; object-fit: cover; }
.tf-album-caption { padding: 8px; font-size: 12px; color: #475569; overflow-wrap: anywhere; }
.tf-album-item.is-error { border-color: #fca5a5; background: #fff7f7; }
.tf-album-item.is-error .tf-album-caption { color: #b91c1c; }
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

                    <input class="tf-input" type="text"
                           name="location_name" maxlength="255" required
                           placeholder="Nhập địa điểm tour, ví dụ: Sa Pa, Lào Cai"
                           value="<?= tfH($tour['location_name']) ?>">
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
                    <label>Số chỗ trống hiển thị</label>
                    <input class="tf-input"
                           type="number"
                           name="available_slots"
                           min="0"
                           required
                           value="<?= tfH($tour['available_slots']) ?>">
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
            <h2>Ảnh đại diện tour bằng URL</h2>
            <div class="tf-field">
                <label for="tourImageUrl">Dán đường dẫn URL ảnh *</label>
                <input class="tf-input" id="tourImageUrl" name="image" type="url"
                       required maxlength="2000" placeholder="https://example.com/anh-tour.jpg"
                       value="<?= tfH($tour['image'] ?? '') ?>">
                <p class="tf-note">Dùng liên kết ảnh công khai dạng HTTPS; URL ảnh cũ được giữ khi sửa tour.</p>
                <img id="tourPreview" src="<?= tfH($tour['image'] ?? '') ?>"
                     alt="Xem trước ảnh tour" style="display:<?= !empty($tour['image']) ? 'block' : 'none' ?>;max-width:260px;max-height:170px;object-fit:cover;border-radius:10px;margin-top:12px">
            </div>
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
                Mỗi ngày có số chỗ riêng. Khách chỉ đăng ký được
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

                            <input
                                class="tf-input"
                                type="number"
                                min="<?= (int)$dep['booked'] ?>"
                                name="departure_capacity[<?= (int)$dep['id'] ?>]"
                                value="<?= (int)$dep['available_slots'] ?>"
                                required
                            >
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
                                    (int)$dep['available_slots'] -
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

                        <input
                            class="tf-input"
                            type="number"
                            name="new_departure_capacity[]"
                            min="1"
                            value="<?= tfH($tour['max_slots']) ?>"
                            required
                        >
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

                    <?php if ($field === 'gallery'): ?>
                        <div class="tf-album-preview-head" aria-live="polite" id="tfAlbumSummary">
                            Dán URL ảnh, hình xem trước sẽ xuất hiện ngay bên dưới.
                        </div>
                        <div class="tf-album-preview" id="tfAlbumPreview" aria-live="polite"></div>
                    <?php endif; ?>

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

<div class="tf-prompt" id="tourSavePopup"><div class="tf-prompt-box">
<h2>Thông báo</h2><p id="tourSaveMessage"></p>
<button type="button" class="tf-btn" id="tourSaveOK">Đồng ý</button>
</div></div>

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
    const urlInput = document.getElementById('tourImageUrl');
    const imagePreview = document.getElementById('tourPreview');

    urlInput?.addEventListener('input', () => {
        const value = urlInput.value.trim();
        imagePreview.src = value;
        imagePreview.style.display =
            /^https?:\/\//i.test(value) ? 'block' : 'none';
    });

    imagePreview?.addEventListener('error', () => {
        imagePreview.style.display = 'none';
    });


    // Xem truoc album ngay khi nhap/dan URL, va khi mo form chinh sua.
    const albumInput = document.getElementById('field-gallery');
    const albumPreview = document.getElementById('tfAlbumPreview');
    const albumSummary = document.getElementById('tfAlbumSummary');
    let albumPreviewTimer;
    function renderAlbumPreview() {
        if (!albumInput || !albumPreview || !albumSummary) return;
        const urls = albumInput.value.split(/\r?\n/).map(v => v.trim()).filter(Boolean);
        albumPreview.replaceChildren();
        if (!urls.length) {
            albumSummary.textContent = 'Dán URL ảnh, hình xem trước sẽ xuất hiện ngay bên dưới.';
            return;
        }
        albumSummary.textContent = 'Đang xem trước ' + urls.length + ' ảnh (tối đa 30 ảnh được lưu).';
        urls.forEach((url, index) => {
            const card = document.createElement('div');
            card.className = 'tf-album-item';
            const caption = document.createElement('div');
            caption.className = 'tf-album-caption';
            caption.textContent = 'Ảnh ' + (index + 1);
            if (!/^https:\/\//i.test(url) && !/^http:\/\//i.test(url)) {
                card.classList.add('is-error');
                caption.textContent = 'URL ' + (index + 1) + ' không hợp lệ (cần HTTPS).';
            } else {
                const img = document.createElement('img');
                img.alt = 'Xem trước ảnh album ' + (index + 1);
                img.loading = 'lazy';
                img.referrerPolicy = 'no-referrer';
                img.addEventListener('error', () => {
                    card.classList.add('is-error');
                    img.remove();
                    caption.textContent = 'Không tải được ảnh ' + (index + 1) + '. Kiểm tra URL hoặc quyền truy cập ảnh.';
                });
                img.src = url;
                card.appendChild(img);
            }
            card.appendChild(caption);
            albumPreview.appendChild(card);
        });
    }
    albumInput?.addEventListener('input', () => {
        clearTimeout(albumPreviewTimer);
        albumPreviewTimer = setTimeout(renderAlbumPreview, 250);
    });
    renderAlbumPreview();

    // THEM DONG NGAY KHOI HANH

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

    // KIEM TRA TRUOC KHI LUU

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

    form?.addEventListener('submit', async function(event) {
        event.preventDefault();

        const dates = Array.from(
            form.querySelectorAll(
                'input[name="new_departure_date[]"]'
            )
        ).map(input => input.value).filter(Boolean);

        if (new Set(dates).size !== dates.length) {
            event.preventDefault();
            popup('Bạn đang chọn trùng ngày khởi hành.');
            return;
        }

        const save = document.getElementById('saveTour');

        if (!form.reportValidity()) return;

        const original = save?.innerHTML || '';

        if (save) {
            save.disabled = true;
            save.textContent = 'ĐANG LƯU...';
        }

        try {
            const response = await fetch(location.href, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const raw = await response.text();
            const data = JSON.parse(
                raw.replace(/^\uFEFF/, "").trim()
            );

            document.getElementById(
                'tourSaveMessage'
            ).textContent =
                data.message || 'Đã xử lý yêu cầu.';

            document.getElementById(
                'tourSavePopup'
            ).classList.add('open');

            document.getElementById(
                'tourSaveOK'
            ).onclick = () => {
                document.getElementById(
                    'tourSavePopup'
                ).classList.remove('open');

                if (data.success) {
                    window.location.assign('index.php');
                }
            };

        } catch (error) {
            popup('Không thể lưu tour. Vui lòng thử lại.');
        } finally {
            if (save) {
                save.disabled = false;
                save.innerHTML = original;
            }
        }
    });
})();
</script>

</body>
</html>
