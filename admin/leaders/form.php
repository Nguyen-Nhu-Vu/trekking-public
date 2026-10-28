<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

$current_page = 'leaders';
$page_title = 'Thêm / Sửa Leader - VNN Travel';

function lfH($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

if (empty($_SESSION['leader_csrf'])) {
    $_SESSION['leader_csrf'] = bin2hex(random_bytes(32));
}

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
    'philosophy' => 'Phương châm sống và leo núi',
    'safety_commitment' => 'Cam kết an toàn',
    'achievements' => 'Thành tích nổi bật',
    'gallery' => 'Album ảnh bổ sung (mỗi URL HTTPS một dòng)'
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

if ($id > 0) {
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
        exit('Không tìm thấy Leader.');
    }

    $editing = $record;
}

// ==========================================
// UPLOAD ANH LEN GITHUB
// ==========================================

function lfUploadGitHub(array $file): string {

    if (
        ($file['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'Tải ảnh lên không thành công.'
        );
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException(
            'Tệp tải lên không hợp lệ.'
        );
    }

    if (
        $file['size'] <= 0 ||
        $file['size'] > 4 * 1024 * 1024
    ) {
        throw new RuntimeException(
            'Ảnh phải nhỏ hơn 4 MB.'
        );
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))
        ->file($file['tmp_name']);

    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null
    };

    if ($extension === null) {
        throw new RuntimeException(
            'Chỉ nhận ảnh JPG, PNG hoặc WebP.'
        );
    }

    $token = (string)(
        getenv('GITHUB_TOKEN') ?: ''
    );

    if ($token === '') {
        throw new RuntimeException(
            'Chưa cấu hình GITHUB_TOKEN trên Vercel.'
        );
    }

    $repo = (string)(
        getenv('GITHUB_REPO')
        ?: 'Nguyen-Nhu-Vu/trekking-public'
    );

    if (!preg_match(
        '~^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$~',
        $repo
    )) {
        throw new RuntimeException(
            'GITHUB_REPO không hợp lệ.'
        );
    }

    $branch = (string)(
        getenv('GITHUB_BRANCH') ?: 'main'
    );

    $filename = 'leader-' .
        bin2hex(random_bytes(12)) .
        '.' . $extension;

    $path = 'assets/images/leaders/' . $filename;

    $content = file_get_contents(
        $file['tmp_name']
    );

    $payload = json_encode([
        'message' => 'Upload Leader photo ' . $filename,
        'content' => base64_encode($content),
        'branch' => $branch
    ]);

    $url = 'https://api.github.com/repos/' .
        $repo . '/contents/' . $path;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $token,
            'X-GitHub-Api-Version: 2022-11-28',
            'Content-Type: application/json',
            'User-Agent: VNN-Travel-Leader-Admin'
        ]
    ]);

    $result = curl_exec($ch);
    $status = (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);

    curl_close($ch);

    if (
        $result === false ||
        !in_array($status, [200, 201], true)
    ) {
        error_log(
            'GitHub Leader upload HTTP ' .
            $status . ': ' . $curlError
        );

        throw new RuntimeException(
            'Không lưu được ảnh lên GitHub. ' .
            'Kiểm tra GITHUB_TOKEN và quyền Contents.'
        );
    }

    return '/' . $path;
}

// ==========================================
// LUU LEADER
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (!hash_equals(
            (string)$_SESSION['leader_csrf'],
            (string)($_POST['csrf'] ?? '')
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

        foreach (
            preg_split(
                '/\r\n|\n|\r/',
                $values['gallery']
            ) as $url
        ) {
            $url = trim($url);

            if ($url === '') {
                continue;
            }

            if (
                !filter_var($url, FILTER_VALIDATE_URL) ||
                parse_url($url, PHP_URL_SCHEME) !== 'https'
            ) {
                throw new RuntimeException(
                    'Album ảnh chỉ nhận URL HTTPS.'
                );
            }
        }

        $image = $id > 0
            ? (string)$record['image']
            : '';

        if (
            !empty($_FILES['avatar']) &&
            ($_FILES['avatar']['error'] ??
                UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ) {
            $image = lfUploadGitHub(
                $_FILES['avatar']
            );
        }

        if ($id === 0 && $image === '') {
            throw new RuntimeException(
                'Vui lòng chọn ảnh đại diện từ máy tính.'
            );
        }

        $status = (int)$editing['status'];

        if ($id > 0) {

            $set = implode(
                ', ',
                array_map(
                    static fn($field) =>
                        "`$field` = ?",
                    $fields
                )
            );

            $sql = "
                UPDATE trekking_leaders
                SET $set,
                    image = ?,
                    status = ?
                WHERE id = ?
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ...array_values($values),
                $image,
                $status,
                $id
            ]);

            $_SESSION['leader_notice'] =
                'Đã cập nhật Leader.';

        } else {

            $columns = '`' .
                implode('`,`', $fields) .
                '`, `image`, `status`';

            $marks = implode(
                ',',
                array_fill(
                    0,
                    count($fields) + 2,
                    '?'
                )
            );

            $sql = "
                INSERT INTO trekking_leaders
                ($columns)
                VALUES ($marks)
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ...array_values($values),
                $image,
                $status
            ]);

            $_SESSION['leader_notice'] =
                'Đã thêm Leader mới.';
        }

        header(
            'Location: index.php',
            true,
            303
        );

        exit;

    } catch (Throwable $e) {

        error_log(
            'Save Leader: ' .
            $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không lưu được Leader.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title><?= lfH($page_title) ?></title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet"
 href="../../assets/css/style.css">

<link rel="stylesheet"
 href="../../assets/css/admin.css">

<style>
.lf-page {
    flex: 1;
    min-width: 0;
    min-height: 100vh;
    background: #f1f5f9;
    padding: 24px;
    color: #132a4d;
}

.lf-panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 23px;
    max-width: 1200px;
    margin: 0 auto;
}

.lf-title {
    display: flex;
    gap: 12px;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.lf-title h1 {
    font-size: 20px;
    margin: 0;
    color: #102c50;
}

.lf-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: 0;
    border-radius: 8px;
    background: #166534;
    padding: 11px 17px;
    color: white !important;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
}

.lf-btn.gray {
    background: #e2e8f0;
    color: #102c50 !important;
}

.lf-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.lf-field.full {
    grid-column: 1 / -1;
}

.lf-field label {
    display: block;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 8px;
}

.lf-field input:not([type=checkbox]),
.lf-field textarea {
    box-sizing: border-box;
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font: inherit;
    font-size: 13px;
    background: white;
}

.lf-field textarea {
    min-height: 120px;
    resize: vertical;
}

.lf-note {
    font-size: 12px;
    color: #64748b;
    line-height: 1.7;
    margin: 8px 0;
}

.lf-error {
    padding: 12px 15px;
    border-radius: 9px;
    background: #fee2e2;
    color: #991b1b;
    margin-bottom: 17px;
}

.lf-preview {
    display: block;
    width: 180px;
    height: 210px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #dbe4ef;
    margin: 12px 0;
    background: #f8fafc;
}

.lf-preview[hidden] {
    display: none;
}

.lf-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 23px;
}

.lf-check {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 13px;
    font-weight: 700;
}

@media(max-width:700px) {
    .lf-page {
        padding: 10px;
    }

    .lf-panel {
        padding: 16px;
    }

    .lf-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="admin-body">

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main lf-page">

<section class="lf-panel">

    <div class="lf-title">

        <h1>
            <i class="fa-solid fa-person-hiking"></i>

            <?= $id > 0
                ? 'CHỈNH SỬA HỒ SƠ LEADER'
                : 'THÊM HƯỚNG DẪN VIÊN MỚI' ?>
        </h1>

        <a class="lf-btn gray" href="index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Về danh sách
        </a>

    </div>

    <?php if ($error): ?>

        <div class="lf-error" role="alert">
            <?= lfH($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST"
          enctype="multipart/form-data">

        <input
            type="hidden"
            name="csrf"
            value="<?= lfH($_SESSION['leader_csrf']) ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?= $id ?>"
        >

        <div class="lf-grid">

            <?php foreach ($fields as $field): ?>

                <div class="lf-field <?= in_array(
                    $field,
                    $longFields,
                    true
                ) ? 'full' : '' ?>">

                    <label for="lf-<?= lfH($field) ?>">
                        <?= lfH($labels[$field]) ?>
                    </label>

                    <?php if (in_array(
                        $field,
                        $longFields,
                        true
                    )): ?>

                        <textarea
                            name="<?= lfH($field) ?>"
                            id="lf-<?= lfH($field) ?>"
                            rows="5"
                        ><?= lfH(
                            $editing[$field] ?? ''
                        ) ?></textarea>

                    <?php else: ?>

                        <input
                            name="<?= lfH($field) ?>"
                            id="lf-<?= lfH($field) ?>"
                            type="<?= $field === 'experience_years'
                                ? 'number'
                                : 'text' ?>"
                            value="<?= lfH(
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

            <div class="lf-field full">

                <label for="lf-avatar">
                    Ảnh đại diện (chọn từ máy tính)
                    <?= $id === 0 ? '*' : '' ?>
                </label>

                <input
                    type="file"
                    name="avatar"
                    id="lf-avatar"
                    accept="image/jpeg,image/png,image/webp"
                    <?= $id === 0 ? 'required' : '' ?>
                >

                <p class="lf-note">
                    Chọn ảnh JPG, PNG hoặc WebP,
                    dung lượng tối đa 4 MB.
                    Ảnh được lưu vào GitHub.
                    <?= $id > 0
                        ? 'Bỏ trống để giữ ảnh hiện tại.'
                        : '' ?>
                </p>

                <img
                    id="lf-preview"
                    class="lf-preview"
                    src="<?= lfH(
                        $editing['image'] ?: ''
                    ) ?>"
                    alt="Ảnh Leader"
                    <?= empty($editing['image'])
                        ? 'hidden'
                        : '' ?>
                >

            </div>

            <div class="lf-field full">

                <label class="lf-check">

                    <input
                        type="checkbox"
                        name="status"
                        value="1"
                        <?= (int)$editing['status'] === 1
                            ? 'checked'
                            : '' ?>
                    >

                    Hiển thị hồ sơ Leader trên website

                </label>

            </div>

        </div>

        <div class="lf-actions">

            <button type="submit" class="lf-btn">
                <i class="fa-solid fa-floppy-disk"></i>
                Lưu hồ sơ
            </button>

            <a class="lf-btn gray" href="index.php">
                Hủy
            </a>

        </div>

    </form>

</section>

</main>

<script>
const leaderFile = document.getElementById('lf-avatar');
const leaderPreview = document.getElementById('lf-preview');

leaderFile?.addEventListener('change', function() {

    const file = leaderFile.files?.[0];

    if (!file) {
        return;
    }

    const allowed = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (
        !allowed.includes(file.type) ||
        file.size > 4194304
    ) {
        alert(
            'Chỉ chọn ảnh JPG, PNG, WebP dưới 4 MB.'
        );

        leaderFile.value = '';
        return;
    }

    leaderPreview.src = URL.createObjectURL(file);
    leaderPreview.hidden = false;

});
</script>

</body>
</html>
