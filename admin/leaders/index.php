<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

$current_page = 'leaders';
$page_title = 'Quản lý hướng dẫn viên';

function leaderH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['leader_csrf'])) {
    $_SESSION['leader_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['leader_csrf'];

$fields = [
    'fullname', 'nickname', 'image', 'hometown',
    'experience_years', 'mountains', 'skills',
    'biography', 'experience_detail', 'philosophy',
    'safety_commitment', 'achievements', 'gallery'
];

$labels = [
    'fullname' => 'Họ và tên *',
    'nickname' => 'Biệt danh',
    'image' => 'URL ảnh đại diện',
    'hometown' => 'Quê quán',
    'experience_years' => 'Số năm kinh nghiệm',
    'mountains' => 'Các cung đường từng chinh phục',
    'skills' => 'Kỹ năng chuyên môn',
    'biography' => 'Giới thiệu bản thân',
    'experience_detail' => 'Hành trình và kinh nghiệm chi tiết',
    'philosophy' => 'Phương châm sống và leo núi',
    'safety_commitment' => 'Cam kết an toàn',
    'achievements' => 'Thành tích nổi bật',
    'gallery' => 'Album ảnh (mỗi URL một dòng)'
];

$longFields = [
    'mountains', 'skills', 'biography',
    'experience_detail', 'philosophy',
    'safety_commitment', 'achievements', 'gallery'
];

$error = '';
$notice = $_SESSION['leader_notice'] ?? '';
unset($_SESSION['leader_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Phiên làm việc không hợp lệ.');
        }

        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'save') {
            $values = [];

            foreach ($fields as $field) {
                $values[$field] = trim((string)($_POST[$field] ?? ''));
            }

            if ($values['fullname'] === '') {
                throw new RuntimeException('Vui lòng nhập họ tên Leader.');
            }

            $years = filter_var(
                $values['experience_years'],
                FILTER_VALIDATE_INT
            );

            if ($years === false || $years < 0 || $years > 80) {
                throw new RuntimeException('Số năm kinh nghiệm không hợp lệ.');
            }

            $values['experience_years'] = $years;

            $urls = array_merge(
                [$values['image']],
                preg_split('/\r\n|\n|\r/', $values['gallery'])
            );

            foreach ($urls as $url) {
                $url = trim($url);

                if ($url === '') {
                    continue;
                }

                $https = filter_var($url, FILTER_VALIDATE_URL)
                    && parse_url($url, PHP_URL_SCHEME) === 'https';

                $local = preg_match(
                    '~^/(?!/)(?!.*\.\.)[a-zA-Z0-9_./-]+$~',
                    $url
                );

                if (!$https && !$local) {
                    throw new RuntimeException(
                        'Đường dẫn ảnh phải là HTTPS hoặc đường dẫn nội bộ.'
                    );
                }
            }

            $status = isset($_POST['status']) ? 1 : 0;

            if ($id > 0) {
                $sets = implode(
                    ', ',
                    array_map(fn($f) => "`$f` = ?", $fields)
                );

                $sql = "UPDATE trekking_leaders
                        SET $sets, status = ?
                        WHERE id = ?";

                $params = array_values($values);
                $params[] = $status;
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $message = 'Đã cập nhật hồ sơ Leader.';
            } else {
                $columns = '`' . implode('`,`', $fields) . '`';

                $marks = implode(
                    ',',
                    array_fill(0, count($fields) + 1, '?')
                );

                $stmt = $pdo->prepare(
                    "INSERT INTO trekking_leaders
                     ($columns, status) VALUES ($marks)"
                );

                $params = array_values($values);
                $params[] = $status;

                $stmt->execute($params);

                $message = 'Đã thêm Leader mới.';
            }

        } elseif ($action === 'delete') {
            if ($id <= 0) {
                throw new RuntimeException('Leader không hợp lệ.');
            }

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM tours WHERE leader_id = ?"
            );

            $stmt->execute([$id]);

            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException(
                    'Leader đang phụ trách tour. Hãy đổi Leader của tour trước.'
                );
            }

            $stmt = $pdo->prepare(
                "DELETE FROM trekking_leaders WHERE id = ?"
            );

            $stmt->execute([$id]);
            $message = 'Đã xóa hồ sơ Leader.';
        } else {
            throw new RuntimeException('Thao tác không hợp lệ.');
        }

        $_SESSION['leader_notice'] = $message;

        header('Location: index.php', true, 303);
        exit;

    } catch (Throwable $e) {
        error_log('Leader: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}

$leaders = $pdo->query("
    SELECT l.*,
        (
            SELECT COUNT(*)
            FROM tours t
            WHERE t.leader_id = l.id
        ) AS tour_count
    FROM trekking_leaders l
    ORDER BY l.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$editing = array_fill_keys($fields, '');
$editing['id'] = 0;
$editing['status'] = 1;
$editing['experience_years'] = 0;

$editId = (int)($_GET['edit'] ?? 0);

foreach ($leaders as $leader) {
    if ((int)$leader['id'] === $editId) {
        $editing = $leader;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Quản lý Leader - VNN Travel</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet"
          href="../../assets/css/style.css">

    <link rel="stylesheet"
          href="../../assets/css/admin.css">

    <style>
        .leader-page {
            padding: 25px;
            background: #f1f5f9;
            flex: 1;
            min-width: 0;
        }

        .leader-panel {
            background: #fff;
            padding: 24px;
            border-radius: 14px;
            margin-bottom: 22px;
            border: 1px solid #e2e8f0;
        }

        .leader-panel h2 {
            font-size: 21px;
            color: #0f172a;
            margin-bottom: 22px;
        }

        .leader-grid {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap: 16px;
        }

        .leader-field.full {
            grid-column: 1 / -1;
        }

        .leader-field label {
            display: block;
            font-weight: 700;
            margin-bottom: 7px;
            color: #334155;
        }

        .leader-field input,
        .leader-field textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font: inherit;
        }

        .leader-field textarea {
            min-height: 115px;
            resize: vertical;
        }

        .leader-btn {
            padding: 11px 16px;
            border: none;
            border-radius: 8px;
            background: #166534;
            color: white;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .leader-btn.red {
            background: #dc2626;
        }

        .leader-btn.secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .leader-table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        .leader-table th,
        .leader-table td {
            padding: 13px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .leader-table th {
            background: #f8fafc;
        }

        .leader-table img {
            width: 60px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
        }

        .leader-error,
        .leader-ok {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .leader-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .leader-ok {
            background: #dcfce7;
            color: #166534;
        }

        @media(max-width:700px) {
            .leader-grid {
                grid-template-columns: 1fr;
            }

            .leader-page {
                padding: 12px;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main leader-page">

    <div class="admin-topbar">
        <strong>
            <i class="fa-solid fa-person-hiking"></i>
            QUẢN LÝ ĐỘI NGŨ LEADER
        </strong>
    </div>

    <?php if ($error): ?>
        <div class="leader-error" role="alert">
            <?= leaderH($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($notice): ?>
        <div class="leader-ok" role="status">
            <?= leaderH($notice) ?>
        </div>
    <?php endif; ?>

    <section class="leader-panel">

        <h2>
            <?= (int)$editing['id'] > 0
                ? 'Chỉnh sửa hồ sơ Leader'
                : 'Thêm hướng dẫn viên mới' ?>
        </h2>

        <form method="POST">
            <input type="hidden"
                   name="csrf"
                   value="<?= leaderH($csrf) ?>">

            <input type="hidden"
                   name="action"
                   value="save">

            <input type="hidden"
                   name="id"
                   value="<?= (int)$editing['id'] ?>">

            <div class="leader-grid">

                <?php foreach ($fields as $field): ?>

                    <?php
                    $isLong = in_array(
                        $field,
                        $longFields,
                        true
                    );
                    ?>

                    <div class="leader-field <?= $isLong ? 'full' : '' ?>">

                        <label for="field-<?= leaderH($field) ?>">
                            <?= leaderH($labels[$field]) ?>
                        </label>

                        <?php if ($isLong): ?>

                            <textarea
                                id="field-<?= leaderH($field) ?>"
                                name="<?= leaderH($field) ?>"
                                rows="5"
                            ><?= leaderH($editing[$field] ?? '') ?></textarea>

                        <?php else: ?>

                            <input
                                id="field-<?= leaderH($field) ?>"
                                name="<?= leaderH($field) ?>"
                                type="<?= $field === 'experience_years'
                                    ? 'number' : 'text' ?>"
                                value="<?= leaderH($editing[$field] ?? '') ?>"
                                <?= $field === 'fullname' ? 'required' : '' ?>
                                <?= $field === 'experience_years'
                                    ? 'min="0" max="80"' : '' ?>
                            >

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

                <div class="leader-field full">
                    <label>
                        <input
                            type="checkbox"
                            name="status"
                            value="1"
                            style="width:auto"
                            <?= (int)$editing['status'] === 1
                                ? 'checked' : '' ?>
                        >
                        Hiển thị hồ sơ trên website
                    </label>
                </div>

            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:24px">

                <button class="leader-btn" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Lưu hồ sơ
                </button>

                <a class="leader-btn secondary" href="index.php">
                    Thêm mới
                </a>

            </div>
        </form>
    </section>

    <section class="leader-panel">

        <h2>Danh sách hướng dẫn viên</h2>

        <div style="overflow-x:auto">
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
                                <?php if ($leader['image']): ?>
                                    <img
                                        src="<?= leaderH($leader['image']) ?>"
                                        alt="<?= leaderH($leader['fullname']) ?>"
                                    >
                                <?php endif; ?>
                            </td>

                            <td>
                                <strong>
                                    <?= leaderH($leader['fullname']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= (int)$leader['experience_years'] ?> năm
                            </td>

                            <td>
                                <?= (int)$leader['tour_count'] ?> tour
                            </td>

                            <td>
                                <?= (int)$leader['status'] === 1
                                    ? 'Đang hiển thị'
                                    : 'Đang ẩn' ?>
                            </td>

                            <td>
                                <a class="leader-btn"
                                   href="?edit=<?= (int)$leader['id'] ?>">
                                    Sửa
                                </a>

                                <form method="POST"
                                      style="display:inline-block"
                                      onsubmit="return confirm('Xóa hồ sơ Leader này?')">

                                    <input type="hidden"
                                           name="csrf"
                                           value="<?= leaderH($csrf) ?>">

                                    <input type="hidden"
                                           name="id"
                                           value="<?= (int)$leader['id'] ?>">

                                    <button class="leader-btn red"
                                            type="submit"
                                            name="action"
                                            value="delete">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$leaders): ?>
                        <tr>
                            <td colspan="6">
                                Chưa có hướng dẫn viên nào.
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
