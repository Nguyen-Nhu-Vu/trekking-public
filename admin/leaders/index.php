<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

$current_page = 'leaders';
$page_title = 'Quản lý đội ngũ Leader - VNN Travel';

function leaderH($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

if (empty($_SESSION['leader_csrf'])) {
    $_SESSION['leader_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            $_SESSION['leader_csrf'],
            (string)($_POST['csrf'] ?? '')
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

        if (!$id || $id < 1) {
            throw new RuntimeException(
                'Leader không hợp lệ.'
            );
        }

        $pdo->beginTransaction();

        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM tours
            WHERE leader_id = ?
        ");

        $check->execute([$id]);

        if ((int)$check->fetchColumn() > 0) {
            throw new RuntimeException(
                'Leader đang phụ trách tour. ' .
                'Hãy đổi Leader của tour trước khi xóa.'
            );
        }

        $delete = $pdo->prepare("
            DELETE FROM trekking_leaders
            WHERE id = ?
        ");

        $delete->execute([$id]);

        if ($delete->rowCount() !== 1) {
            throw new RuntimeException(
                'Không tìm thấy Leader.'
            );
        }

        $pdo->commit();

        $_SESSION['leader_notice'] =
            'Đã xóa hồ sơ Leader.';

        header('Location: index.php', true, 303);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Leader delete: ' . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể xóa hồ sơ.';
    }
}

try {
    $leaders = $pdo->query("
        SELECT
            l.*,
            (
                SELECT COUNT(*)
                FROM tours t
                WHERE t.leader_id = l.id
            ) AS tour_count
        FROM trekking_leaders l
        ORDER BY l.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $leaders = [];
    $error = 'Không thể tải danh sách Leader.';
    error_log($e->getMessage());
}

$notice = $_SESSION['leader_notice'] ?? '';

unset($_SESSION['leader_notice']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title><?= leaderH($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet"
 href="../../assets/css/style.css">

<link rel="stylesheet"
 href="../../assets/css/admin.css">

<style>
.leader-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    background: #f1f5f9;
    padding: 25px;
    color: #132a4d;
}

.leader-bar,
.leader-panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 21px;
    margin-bottom: 18px;
}

.leader-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.leader-bar h1 {
    font-size: 19px;
    margin: 0;
    color: #102c50;
}

.leader-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    background: #166534;
    color: white !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.leader-btn.secondary {
    background: #e8f1fb;
    color: #174a79 !important;
}

.leader-btn.red {
    background: #dc2626;
}

.leader-panel h2 {
    margin: 0 0 17px;
    font-size: 18px;
}

.leader-tablewrap {
    overflow-x: auto;
}

.leader-table {
    width: 100%;
    min-width: 780px;
    border-collapse: collapse;
}

.leader-table th,
.leader-table td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e5ebf3;
    font-size: 13px;
    vertical-align: middle;
}

.leader-table th {
    background: #f8fafc;
    font-weight: 800;
    color: #102c50;
}

.leader-table img {
    width: 65px;
    height: 77px;
    border-radius: 9px;
    object-fit: cover;
    background: #e2e8f0;
}

.leader-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.leader-actions form {
    margin: 0;
}

.leader-badge {
    display: inline-block;
    background: #dcfce7;
    color: #166534;
    border-radius: 20px;
    padding: 7px 11px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.leader-badge.off {
    background: #f1f5f9;
    color: #64748b;
}

.leader-msg {
    padding: 13px 17px;
    margin-bottom: 15px;
    border-radius: 10px;
    background: #fee2e2;
    color: #991b1b;
}

.leader-msg.ok {
    background: #dcfce7;
    color: #166534;
}

@media(max-width:700px) {
    .leader-page {
        padding: 12px;
    }

    .leader-bar,
    .leader-panel {
        padding: 15px;
    }
}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main leader-page">

    <div class="leader-bar">

        <h1>
            <i class="fa-solid fa-person-hiking"></i>
            QUẢN LÝ ĐỘI NGŨ LEADER
        </h1>

        <a href="form.php" class="leader-btn">
            <i class="fa-solid fa-plus"></i>
            Thêm Leader mới
        </a>

    </div>

    <?php if ($error): ?>
        <div class="leader-msg" role="alert">
            <?= leaderH($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($notice): ?>
        <div class="leader-msg ok" role="status">
            <?= leaderH($notice) ?>
        </div>
    <?php endif; ?>

    <section class="leader-panel">

        <h2>
            Danh sách hướng dẫn viên
            (<?= count($leaders) ?>)
        </h2>

        <div class="leader-tablewrap">

            <table class="leader-table">

                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Họ tên</th>
                        <th>Kinh nghiệm</th>
                        <th>Tour phụ trách</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($leaders as $leader): ?>

                    <tr>

                        <td>
                            <?php if (!empty($leader['image'])): ?>

                                <img
                                    src="<?= leaderH($leader['image']) ?>"
                                    alt="<?= leaderH($leader['fullname']) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <strong>
                                <?= leaderH($leader['fullname']) ?>
                            </strong>

                            <?php if (!empty($leader['nickname'])): ?>
                                <div style="color:#64748b;font-size:12px">
                                    <?= leaderH($leader['nickname']) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= (int)$leader['experience_years'] ?> năm
                        </td>

                        <td>
                            <?= (int)$leader['tour_count'] ?> tour
                        </td>

                        <td>
                            <span class="leader-badge <?= (int)$leader['status'] === 1 ? '' : 'off' ?>">
                                <?= (int)$leader['status'] === 1
                                    ? 'Đang hiển thị'
                                    : 'Đang ẩn' ?>
                            </span>
                        </td>

                        <td>
                            <div class="leader-actions">

                                <a
                                    class="leader-btn secondary"
                                    href="form.php?id=<?= (int)$leader['id'] ?>"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                    Sửa
                                </a>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Xóa Leader này?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf"
                                        value="<?= leaderH($_SESSION['leader_csrf']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$leader['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="action"
                                        value="delete"
                                        class="leader-btn red"
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
                        <td colspan="6">
                            Chưa có hướng dẫn viên.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>
