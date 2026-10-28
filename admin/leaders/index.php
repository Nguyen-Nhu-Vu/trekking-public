<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

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

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (empty($_SESSION['leader_csrf'])) {
    $_SESSION['leader_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$notice = $_SESSION['leader_notice'] ?? '';
unset($_SESSION['leader_notice']);

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

        $action = (string)($_POST['action'] ?? '');

        if ($action !== 'delete') {
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

        // Khong cho xoa leader dang phu trach tour.
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
            'Đã xóa hướng dẫn viên thành công.';

        header('Location: index.php', true, 303);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Delete Leader: ' . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể xóa hướng dẫn viên.';
    }
}

// =====================================
// DANH SACH LEADER
// =====================================

$leaders = [];

try {
    $stmt = $pdo->query("
        SELECT
            l.id,
            l.fullname,
            l.nickname,
            l.image,
            l.hometown,
            l.experience_years,
            l.status,
            (
                SELECT COUNT(*)
                FROM tours t
                WHERE t.leader_id = l.id
            ) AS tour_count
        FROM trekking_leaders l
        ORDER BY l.id ASC
    ");

    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        'Load Leaders: ' . $e->getMessage()
    );

    $error = 'Không thể tải danh sách hướng dẫn viên.';
}

$totalLeaders = count($leaders);
$activeLeaders = 0;

foreach ($leaders as $leader) {
    if ((int)$leader['status'] === 1) {
        $activeLeaders++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= leaderListH($page_title) ?></title>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
* {
    box-sizing: border-box;
}

.ll-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    background: #f2f5fa;
    padding: 25px;
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
    font-weight: 850;
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
    background: white;
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
    background: white;
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
    font-weight: 850;
}

.ll-search {
    width: min(100%, 295px);
    height: 41px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 0 13px;
    background: white;
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
    font-weight: 850;
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
    object-position: center;
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
    font-weight: 850;
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

.ll-notice {
    padding: 15px 18px;
    margin-bottom: 18px;
    border-radius: 11px;
    background: #dcfce7;
    color: #166534;
    font-weight: 700;
}

.ll-error {
    padding: 15px 18px;
    margin-bottom: 18px;
    border-radius: 11px;
    background: #fee2e2;
    color: #991b1b;
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
                Quản lý hồ sơ hướng dẫn viên
                và Leader phụ trách tour trekking.
            </p>
        </div>

        <a href="form.php" class="ll-btn">
            <i class="fa-solid fa-plus"></i>
            Thêm Leader mới
        </a>

    </div>

    <?php if ($error !== ''): ?>
        <div class="ll-error">
            <?= leaderListH($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($notice !== ''): ?>
        <div class="ll-notice">
            <i class="fa-solid fa-circle-check"></i>
            <?= leaderListH($notice) ?>
        </div>
    <?php endif; ?>

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

            <input
                class="ll-search"
                id="leaderSearch"
                type="search"
                placeholder="Tìm tên, biệt danh, quê quán..."
            >
        </div>

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
                            <?= leaderListH($leader['hometown'] ?: '—') ?>
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
                                    onsubmit="return confirm('Bạn có chắc muốn xóa hồ sơ Leader này?');"
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
                            Chưa có Leader nào trong hệ thống.
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

<script>
(function() {

    const input = document.getElementById('leaderSearch');
    const rows = document.querySelectorAll('.leader-row');

    if (!input) return;

    input.addEventListener('input', function() {

        const keyword = input.value
            .trim()
            .toLocaleLowerCase('vi');

        rows.forEach(function(row) {

            const content = row.textContent
                .toLocaleLowerCase('vi');

            row.style.display =
                content.includes(keyword) ? '' : 'none';

        });

    });

})();
</script>

</body>
</html>
