<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$current_page = 'leaders';
$page_title = 'Quản lý đội ngũ Leader - VNN Travel';

if (!function_exists('leaderListH')) {
    function leaderListH($value) {
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

$popupMessage = '';
$popupType = 'success';

// Nhan thong bao tu trang Them/Sua.
if (!empty($_SESSION['leader_notice'])) {
    $popupMessage = (string)$_SESSION['leader_notice'];
    unset($_SESSION['leader_notice']);
}

// =====================================
// XOA LEADER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf = (string)($_POST['csrf'] ?? '');

        if (!hash_equals(
            (string)$_SESSION['leader_csrf'],
            $csrf
        )) {
            throw new RuntimeException(
                'Phiên làm việc không hợp lệ.'
            );
        }

        if (($_POST['action'] ?? '') !== 'delete') {
            throw new RuntimeException(
                'Thao tác không hợp lệ.'
            );
        }

        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id <= 0) {
            throw new RuntimeException(
                'Hướng dẫn viên không hợp lệ.'
            );
        }

        $pdo->beginTransaction();

        // Khong xoa Leader dang phu trach tour.
        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM tours
            WHERE leader_id = ?
        ");

        $check->execute([$id]);

        if ((int)$check->fetchColumn() > 0) {
            throw new RuntimeException(
                'Leader đang phụ trách tour. ' .
                'Vui lòng đổi Leader của tour trước khi xóa.'
            );
        }

        $delete = $pdo->prepare("
            DELETE FROM trekking_leaders
            WHERE id = ?
        ");

        $delete->execute([$id]);

        if ($delete->rowCount() !== 1) {
            throw new RuntimeException(
                'Không tìm thấy hướng dẫn viên.'
            );
        }

        $pdo->commit();

        $_SESSION['leader_notice'] =
            'Đã xóa hồ sơ Leader thành công.';

        header('Location: index.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 303);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Delete Leader: ' . $e->getMessage());

        $popupType = 'error';
        $popupMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể xóa hướng dẫn viên.';
    }
}

// =====================================
// DANH SACH LEADER VA BO LOC SQL
// =====================================

$leaders = [];
$tourOptions = [];
$hometownOptions = [];
$filterSearch = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$filterHometown = trim((string)($_GET['hometown'] ?? ''));
$filterExperience = (string)($_GET['experience'] ?? '');
$filterTour = filter_var($_GET['tour_id'] ?? 0, FILTER_VALIDATE_INT);
$filterTour = ($filterTour !== false && $filterTour > 0) ? $filterTour : 0;
$filterStatus = (string)($_GET['status'] ?? '');
$filterSort = (string)($_GET['sort'] ?? 'newest');

$experienceOptions = [
    '' => 'Tất cả kinh nghiệm',
    'under3' => 'Dưới 3 năm',
    '3to5' => '3–5 năm',
    '6to10' => '6–10 năm',
    'over10' => 'Trên 10 năm'
];
$sortOptions = [
    'newest' => 'Mới nhất',
    'name_asc' => 'Tên A–Z',
    'name_desc' => 'Tên Z–A',
    'exp_desc' => 'Kinh nghiệm nhiều nhất',
    'exp_asc' => 'Kinh nghiệm ít nhất',
    'tour_desc' => 'Số tour nhiều nhất',
    'tour_asc' => 'Số tour ít nhất'
];
if (!array_key_exists($filterExperience, $experienceOptions)) $filterExperience = '';
if (!array_key_exists($filterSort, $sortOptions)) $filterSort = 'newest';
if (!in_array($filterStatus, ['', '1', '0'], true)) $filterStatus = '';

$totalLeaders = 0;
$activeLeaders = 0;
$filteredCount = 0;

try {
    // Toan bo so lieu tong quan khong bi anh huong bo loc.
    $summary = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status=1 THEN 1 ELSE 0 END),0) AS active FROM trekking_leaders")
        ->fetch(PDO::FETCH_ASSOC);
    $totalLeaders = (int)($summary['total'] ?? 0);
    $activeLeaders = (int)($summary['active'] ?? 0);

    // Danh sach tour thuc te va que quan thuc te lay tu MySQL.
    $tourOptions = $pdo->query("SELECT id, title FROM tours ORDER BY title ASC, id ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
    $hometownOptions = $pdo->query("SELECT DISTINCT hometown FROM trekking_leaders WHERE hometown IS NOT NULL AND TRIM(hometown) <> '' ORDER BY hometown ASC")
        ->fetchAll(PDO::FETCH_COLUMN);

    $tourIds = array_map('intval', array_column($tourOptions, 'id'));
    if (!in_array($filterTour, $tourIds, true)) $filterTour = 0;
    if (!in_array($filterHometown, $hometownOptions, true)) $filterHometown = '';

    $sql = "SELECT l.id, l.fullname, l.nickname, l.image,
                l.hometown, l.experience_years, l.status,
                (SELECT COUNT(*) FROM tours t WHERE t.leader_id = l.id) AS tour_count
            FROM trekking_leaders l WHERE 1=1";
    $params = [];
    if ($filterSearch !== '') {
        $sql .= " AND (l.fullname LIKE ? OR l.nickname LIKE ? OR l.hometown LIKE ?)";
        $like = '%' . $filterSearch . '%';
        array_push($params, $like, $like, $like);
    }
    if ($filterHometown !== '') {
        $sql .= " AND l.hometown = ?";
        $params[] = $filterHometown;
    }
    if ($filterExperience === 'under3') $sql .= ' AND l.experience_years < 3';
    if ($filterExperience === '3to5') $sql .= ' AND l.experience_years BETWEEN 3 AND 5';
    if ($filterExperience === '6to10') $sql .= ' AND l.experience_years BETWEEN 6 AND 10';
    if ($filterExperience === 'over10') $sql .= ' AND l.experience_years > 10';
    if ($filterTour > 0) {
        // EXISTS dam bao moi Leader chi xuat hien mot lan duoc phan cong tour da chon.
        $sql .= " AND EXISTS (SELECT 1 FROM tours ft WHERE ft.leader_id = l.id AND ft.id = ?)";
        $params[] = $filterTour;
    }
    if ($filterStatus !== '') {
        $sql .= ' AND l.status = ?';
        $params[] = (int)$filterStatus;
    }
    $sortSql = [
        'newest' => 'l.id DESC',
        'name_asc' => 'l.fullname ASC, l.id ASC',
        'name_desc' => 'l.fullname DESC, l.id DESC',
        'exp_desc' => 'l.experience_years DESC, l.id ASC',
        'exp_asc' => 'l.experience_years ASC, l.id ASC',
        'tour_desc' => 'tour_count DESC, l.id ASC',
        'tour_asc' => 'tour_count ASC, l.id ASC'
    ];
    $sql .= ' ORDER BY ' . $sortSql[$filterSort];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $filteredCount = count($leaders);
} catch (Throwable $e) {
    error_log('Load/filter Leaders: ' . $e->getMessage());
    $popupMessage = 'Không thể tải danh sách hướng dẫn viên.';
    $popupType = 'error';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title><?= leaderListH($page_title) ?></title>

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

.ll-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    padding: 25px;
    background: #f2f5fa;
    color: #18314f;
    font-size: 14px;
}

.ll-header {
    background: #fff;
    border: 1px solid #e0e8f0;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 18px;

    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}

.ll-header h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #102e52;
}

.ll-header p {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 13px;
}

.ll-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    background: #166534;
    color: white !important;
    border: none;
    border-radius: 9px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}

.ll-btn:hover {
    background: #14532d;
}

.ll-btn.edit {
    background: #eff6ff;
    color: #1d4ed8 !important;
}

.ll-btn.edit:hover {
    background: #dbeafe;
}

.ll-btn.delete {
    background: #fef2f2;
    color: #dc2626 !important;
}

.ll-btn.delete:hover {
    background: #fee2e2;
}

.ll-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 19px;
}

.ll-stat {
    background: #fff;
    border: 1px solid #e0e8f0;
    border-radius: 13px;
    padding: 19px;
}

.ll-stat-label {
    font-size: 12px;
    font-weight: 750;
    color: #64748b;
}

.ll-stat-value {
    margin-top: 10px;
    color: #102e52;
    font-size: 26px;
    font-weight: 900;
}

.ll-panel {
    background: #fff;
    border: 1px solid #e0e8f0;
    border-radius: 14px;
    overflow: hidden;
}

.ll-panel-title {
    padding: 20px 23px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.ll-panel-title h2 {
    margin: 0;
    color: #102e52;
    font-size: 18px;
    font-weight: 800;
}

.ll-search {
    width: min(100%, 295px);
    height: 41px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 0 13px;
    background: #fff;
    color: #102e52;
    font-size: 13px;
}

.ll-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.ll-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.ll-table th {
    padding: 14px 16px;
    background: #f8fafc;
    color: #102e52;
    font-size: 13px;
    font-weight: 800;
    text-align: left;
    white-space: nowrap;
}

.ll-table td {
    padding: 13px 16px;
    border-top: 1px solid #e2e8f0;
    vertical-align: middle;
    font-size: 13px;
}

.ll-table tbody tr:hover {
    background: #f8fafc;
}

.ll-avatar {
    width: 68px;
    height: 76px;
    object-fit: cover;
    border-radius: 10px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.ll-avatar-empty {
    width: 68px;
    height: 76px;
    border-radius: 10px;
    background: #f1f5f9;
    color: #94a3b8;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 22px;
}

.ll-name {
    color: #0f2d52;
    font-size: 14px;
    font-weight: 800;
}

.ll-sub {
    margin-top: 5px;
    font-size: 12px;
    color: #64748b;
}

.ll-status {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.ll-status.off {
    background: #f1f5f9;
    color: #64748b;
}

.ll-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.ll-actions form {
    margin: 0;
}

.ll-empty {
    padding: 30px;
    color: #64748b;
    text-align: center;
}

.ll-footer {
    padding: 14px 23px;
    border-top: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 12px;
}

/* =======================================
   POPUP DUNG CHUNG
   ======================================= */

.vnn-popup-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .56);
    backdrop-filter: blur(3px);
}

.vnn-popup-overlay[hidden] {
    display: none !important;
}

.vnn-popup-box {
    width: 100%;
    max-width: 420px;
    padding: 30px 26px;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 25px 80px rgba(0,0,0,.2);
    text-align: center;
    animation: vnnPopupIn .2s ease-out;
}

@keyframes vnnPopupIn {
    from {
        opacity: 0;
        transform: translateY(12px) scale(.97);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.vnn-popup-icon {
    width: 67px;
    height: 67px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #dcfce7;
    color: #166534;
    font-size: 32px;
}

.vnn-popup-icon.error {
    background: #fee2e2;
    color: #dc2626;
}

.vnn-popup-icon.warning {
    background: #fff7ed;
    color: #ea580c;
}

.vnn-popup-title {
    margin: 0 0 10px;
    color: #102e52;
    font-size: 21px;
    font-weight: 850;
}

.vnn-popup-message {
    color: #64748b;
    font-size: 14px;
    line-height: 1.8;
    margin: 0 0 23px;
    overflow-wrap: anywhere;
}

.vnn-popup-buttons {
    display: flex;
    justify-content: center;
    gap: 11px;
}

.vnn-popup-button {
    min-width: 105px;
    padding: 12px 19px;
    border: none;
    border-radius: 9px;
    background: #166534;
    color: white;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.vnn-popup-button.cancel {
    background: #e2e8f0;
    color: #334155;
}

.vnn-popup-button.danger {
    background: #dc2626;
    color: white;
}

@media (max-width: 900px) {
    .ll-stats {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 650px) {
    .ll-page {
        padding: 12px;
    }

    .ll-header,
    .ll-panel-title {
        padding: 15px;
    }

    .ll-header h1 {
        font-size: 17px;
    }

    .ll-stats {
        grid-template-columns: 1fr;
    }
}

/* Loc Leader tren toan bo CSDL, khong loc lai bang JavaScript */
.ll-filter-panel { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:15px; padding:20px 23px; border-bottom:1px solid #e2e8f0; background:#fff; }
.ll-filter-field { min-width:0; }
.ll-filter-field label { display:block; margin-bottom:7px; font-weight:750; font-size:12px; color:#334155; }
.ll-filter-input { display:block; width:100%; height:42px; border:1px solid #cbd5e1; border-radius:9px; background:white; color:#18314f; font:inherit; font-size:13px; padding:0 12px; }
.ll-filter-input:focus {outline:2px solid #86efac;outline-offset:1px;}
.ll-filter-actions {display:flex; align-items:end; gap:10px;flex-wrap:wrap;}
.ll-filter-reset {background:#e2e8f0 !important;color:#334155 !important;}
.ll-filter-counter {font-size:12px;color:#64748b;font-weight:700;}
@media(max-width:1050px){.ll-filter-panel{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:650px){.ll-filter-panel{grid-template-columns:1fr;padding:15px;}}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main ll-page">

    <div class="ll-header">
        <div>
            <h1>
                <i class="fa-solid fa-person-hiking"></i>
                QUẢN LÝ ĐỘI NGŨ LEADER
            </h1>

            <p>
                Quản lý hồ sơ hướng dẫn viên và
                Leader phụ trách tour trekking.
            </p>
        </div>

        <a href="form.php" class="ll-btn">
            <i class="fa-solid fa-plus"></i>
            Thêm Leader mới
        </a>
    </div>

    <div class="ll-stats">
        <div class="ll-stat">
            <div class="ll-stat-label">
                Tổng số Leader
            </div>
            <div class="ll-stat-value">
                <?= $totalLeaders ?>
            </div>
        </div>

        <div class="ll-stat">
            <div class="ll-stat-label">
                Leader đang hiển thị
            </div>
            <div class="ll-stat-value">
                <?= $activeLeaders ?>
            </div>
        </div>

        <div class="ll-stat">
            <div class="ll-stat-label">
                Leader đang ẩn
            </div>
            <div class="ll-stat-value">
                <?= $totalLeaders - $activeLeaders ?>
            </div>
        </div>
    </div>

    <section class="ll-panel">
        <div class="ll-panel-title">
            <h2>Danh sách Leader</h2>

            <span class="ll-filter-counter">Hiển thị <?= (int)$filteredCount ?> / <?= (int)$totalLeaders ?> Leader</span>
        </div>

        <form class="ll-filter-panel" method="GET" action="index.php">
            <div class="ll-filter-field ll-filter-search">
                <label for="leaderSearch">Tìm kiếm Leader</label>
                <input class="ll-filter-input" id="leaderSearch" name="q" type="search"
                       value="<?= leaderListH($filterSearch) ?>" placeholder="Tên, biệt danh, quê quán...">
            </div>
            <div class="ll-filter-field">
                <label for="filterHometown">Quê quán</label>
                <select class="ll-filter-input" id="filterHometown" name="hometown">
                    <option value="">Tất cả quê quán</option>
                    <?php foreach ($hometownOptions as $home): ?>
                        <option value="<?= leaderListH($home) ?>" <?= $filterHometown === $home ? 'selected' : '' ?>><?= leaderListH($home) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ll-filter-field">
                <label for="filterExperience">Kinh nghiệm</label>
                <select class="ll-filter-input" id="filterExperience" name="experience">
                    <?php foreach ($experienceOptions as $value => $label): ?>
                        <option value="<?= leaderListH($value) ?>" <?= $filterExperience === $value ? 'selected' : '' ?>><?= leaderListH($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ll-filter-field">
                <label for="filterTour">Tour phụ trách</label>
                <select class="ll-filter-input" id="filterTour" name="tour_id">
                    <option value="0">Tất cả tour</option>
                    <?php foreach ($tourOptions as $tour): ?>
                        <option value="<?= (int)$tour['id'] ?>" <?= $filterTour === (int)$tour['id'] ? 'selected' : '' ?>><?= leaderListH($tour['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ll-filter-field">
                <label for="filterStatus">Trạng thái hiển thị</label>
                <select class="ll-filter-input" id="filterStatus" name="status">
                    <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>Tất cả trạng thái</option>
                    <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Đang hiển thị</option>
                    <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Đang ẩn</option>
                </select>
            </div>
            <div class="ll-filter-field">
                <label for="filterSort">Sắp xếp</label>
                <select class="ll-filter-input" id="filterSort" name="sort">
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= leaderListH($value) ?>" <?= $filterSort === $value ? 'selected' : '' ?>><?= leaderListH($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ll-filter-actions">
                <button class="ll-btn" type="submit"><i class="fa-solid fa-filter"></i> Lọc</button>
                <a class="ll-btn ll-filter-reset" href="index.php"><i class="fa-solid fa-rotate-left"></i> Xóa lọc</a>
            </div>
        </form>

        <div class="ll-table-wrap">

            <table class="ll-table" id="leadersTable">
                <thead>
                    <tr>
                        <th>Ảnh đại diện</th>
                        <th>Họ và tên</th>
                        <th>Quê quán</th>
                        <th>Kinh nghiệm</th>
                        <th>Tour phụ trách</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($leaders as $leader): ?>

                    <tr class="leader-row">

                        <td>
                            <?php if (!empty($leader['image'])): ?>

                                <img
                                    class="ll-avatar"
                                    src="<?= leaderListH($leader['image']) ?>"
                                    alt="<?= leaderListH($leader['fullname']) ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                                >

                                <div class="ll-avatar-empty" style="display:none">
                                    <i class="fa-solid fa-user"></i>
                                </div>

                            <?php else: ?>

                                <div class="ll-avatar-empty">
                                    <i class="fa-solid fa-user"></i>
                                </div>

                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="ll-name">
                                <?= leaderListH($leader['fullname']) ?>
                            </div>

                            <?php if (!empty($leader['nickname'])): ?>
                                <div class="ll-sub">
                                    <?= leaderListH($leader['nickname']) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= leaderListH(
                                $leader['hometown'] ?: '—'
                            ) ?>
                        </td>

                        <td>
                            <?= (int)$leader['experience_years'] ?> năm
                        </td>

                        <td>
                            <?= (int)$leader['tour_count'] ?> tour
                        </td>

                        <td>
                            <span class="ll-status <?= (int)$leader['status'] === 1 ? '' : 'off' ?>">
                                <?= (int)$leader['status'] === 1
                                    ? 'Đang hiển thị'
                                    : 'Đang ẩn' ?>
                            </span>
                        </td>

                        <td>
                            <div class="ll-actions">

                                <a
                                    href="form.php?id=<?= (int)$leader['id'] ?>"
                                    class="ll-btn edit"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                    Sửa
                                </a>

                                <form
                                    method="POST"
                                    class="leader-delete-form"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf"
                                        value="<?= leaderListH($_SESSION['leader_csrf']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$leader['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="ll-btn delete"
                                        name="action"
                                        value="delete"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                        Xóa
                                    </button>

                                </form>

                            </div>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$leaders): ?>
                    <tr>
                        <td colspan="7" class="ll-empty">
                            Không có Leader phù hợp với bộ lọc.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>

        </div>

        <div class="ll-footer">
            Danh sách hướng dẫn viên của VNN Travel.
        </div>
    </section>

</main>

<!-- =======================================
     POPUP THONG BAO KET QUA
     ======================================= -->

<div
    class="vnn-popup-overlay"
    id="resultPopup"
    <?= $popupMessage === '' ? 'hidden' : '' ?>
>
    <div
        class="vnn-popup-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="resultPopupTitle"
    >

        <div class="vnn-popup-icon <?= $popupType === 'error' ? 'error' : '' ?>">
            <i class="fa-solid <?= $popupType === 'error'
                ? 'fa-circle-xmark'
                : 'fa-circle-check' ?>"></i>
        </div>

        <h2 class="vnn-popup-title" id="resultPopupTitle">
            <?= $popupType === 'error'
                ? 'Không thành công'
                : 'Thành công!' ?>
        </h2>

        <p class="vnn-popup-message">
            <?= leaderListH($popupMessage) ?>
        </p>

        <div class="vnn-popup-buttons">
            <button
                class="vnn-popup-button"
                type="button"
                id="closeResultPopup"
            >
                Đồng ý
            </button>
        </div>

    </div>
</div>

<!-- =======================================
     POPUP XAC NHAN XOA
     ======================================= -->

<div
    class="vnn-popup-overlay"
    id="deletePopup"
    hidden
>
    <div
        class="vnn-popup-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deletePopupTitle"
    >

        <div class="vnn-popup-icon warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2 class="vnn-popup-title" id="deletePopupTitle">
            Xác nhận xóa Leader
        </h2>

        <p class="vnn-popup-message">
            Bạn có chắc chắn muốn xóa hồ sơ Leader này?
            Hành động này không thể hoàn tác.
        </p>

        <div class="vnn-popup-buttons">

            <button
                class="vnn-popup-button cancel"
                type="button"
                id="cancelDelete"
            >
                Quay lại
            </button>

            <button
                class="vnn-popup-button danger"
                type="button"
                id="confirmDelete"
            >
                Xác nhận xóa
            </button>

        </div>
    </div>
</div>

<script>
(function() {

    // ==================================
    // POPUP THONG BAO
    // ==================================

    const resultPopup = document.getElementById(
        'resultPopup'
    );

    const closeResultPopup = document.getElementById(
        'closeResultPopup'
    );

    function hideResultPopup() {
        if (resultPopup) {
            resultPopup.hidden = true;
        }
    }

    closeResultPopup?.addEventListener(
        'click',
        hideResultPopup
    );

    // ==================================
    // POPUP XAC NHAN XOA
    // ==================================

    const deletePopup = document.getElementById(
        'deletePopup'
    );

    const cancelDelete = document.getElementById(
        'cancelDelete'
    );

    const confirmDelete = document.getElementById(
        'confirmDelete'
    );

    let pendingDeleteForm = null;

    document.querySelectorAll(
        '.leader-delete-form'
    ).forEach(function(form) {

        form.addEventListener('submit', function(event) {

            event.preventDefault();

            pendingDeleteForm = form;

            deletePopup.hidden = false;

        });

    });

    cancelDelete?.addEventListener('click', function() {

        deletePopup.hidden = true;
        pendingDeleteForm = null;

    });

    confirmDelete?.addEventListener('click', function() {

        if (!pendingDeleteForm) {
            return;
        }

        confirmDelete.disabled = true;
        confirmDelete.textContent = 'Đang xóa...';

        // requestSubmit() gui lai su kien submit.
        // De tranh mo popup lan 2, dung form.submit().
        //
        // Nut action=delete la submit button,
        // form.submit() khong gui gia tri cua nut do.
        // Bo sung hidden input action truoc khi gui.

        let actionInput = pendingDeleteForm.querySelector(
            'input[name="action"]'
        );

        if (!actionInput) {

            actionInput = document.createElement('input');

            actionInput.type = 'hidden';
            actionInput.name = 'action';

            pendingDeleteForm.appendChild(actionInput);
        }

        actionInput.value = 'delete';

        pendingDeleteForm.submit();

    });

    // ==================================
    // DONG POPUP
    // ==================================

    resultPopup?.addEventListener('click', function(event) {

        if (event.target === resultPopup) {
            hideResultPopup();
        }

    });

    deletePopup?.addEventListener('click', function(event) {

        if (event.target === deletePopup) {
            deletePopup.hidden = true;
            pendingDeleteForm = null;
        }

    });

    document.addEventListener('keydown', function(event) {

        if (event.key !== 'Escape') {
            return;
        }

        hideResultPopup();

        if (deletePopup) {
            deletePopup.hidden = true;
            pendingDeleteForm = null;
        }

    });

})();
</script>

</body>
</html>
