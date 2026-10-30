<?php
ob_start();

// VNN Travel - Dang ky tai khoan
// auth/register.php

$page_title = "Đăng Ký Tài Khoản Khách Hàng";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = sanitize($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $repassword = (string)($_POST['repassword'] ?? '');

    if (empty($fullname) || empty($email) || empty($password)) {

        $error = "Vui lòng nhập đầy đủ các trường bắt buộc!";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Địa chỉ email không hợp lệ!";

    } elseif (strlen($password) < 6) {

        $error = "Mật khẩu phải có ít nhất 6 ký tự!";

    } elseif ($password !== $repassword) {

        $error = "Mật khẩu xác nhận không khớp!";

    } else {

        try {

            $chk = $pdo->prepare("
                SELECT id
                FROM users
                WHERE LOWER(email) = LOWER(?)
                LIMIT 1
            ");

            $chk->execute([$email]);

            if ($chk->fetch()) {

                $error = "Email này đã được đăng ký tài khoản!";

            } else {

                $hashed = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare("
                    INSERT INTO users
                        (fullname, email, password, phone, role, status)
                    VALUES
                        (?, ?, ?, ?, 'customer', 1)
                ");

                $stmt->execute([
                    $fullname,
                    $email,
                    $hashed,
                    $phone
                ]);

                // Dang ky thanh cong.
                // Chuyen ngay sang trang dang nhap.
                $loginUrl = rtrim(BASE_URL, '/') .
                    '/auth/login.php';

                if (!headers_sent()) {

                    header(
                        'Location: ' . $loginUrl,
                        true,
                        303
                    );

                } else {

                    echo '<script>window.location.replace(' .
                        json_encode(
                            $loginUrl,
                            JSON_HEX_TAG |
                            JSON_HEX_AMP |
                            JSON_HEX_APOS |
                            JSON_HEX_QUOT
                        ) .
                        ');</script>';
                }

                exit;
            }

        } catch (PDOException $e) {

            error_log(
                'Register error: ' . $e->getMessage()
            );

            $error = "Không thể đăng ký tài khoản. Vui lòng thử lại.";
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section">

    <div style="
        max-width:480px;
        margin:0 auto;
        background:white;
        padding:35px;
        border-radius:12px;
        box-shadow:var(--shadow-lg);
    ">

        <h2 style="
            text-align:center;
            color:var(--primary);
            margin-bottom:20px;
        ">
            ĐĂNG KÝ THÀNH VIÊN
        </h2>

        <?php if ($msg): ?>

            <div class="alert alert-success">
                <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form action="" method="POST">

            <div class="form-group">

                <label>
                    Họ và tên *
                </label>

                <input
                    type="text"
                    name="fullname"
                    class="form-control"
                    required
                    placeholder="Nguyễn Văn A"
                    value="<?= htmlspecialchars($_POST['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >

            </div>

            <div class="form-group">

                <label>
                    Địa chỉ Email Google (Gmail) *
                </label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    required
                    placeholder="taikhoan@gmail.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >

            </div>

            <div class="form-group">

                <label>
                    Số điện thoại
                </label>

                <input
                    type="tel"
                    name="phone"
                    class="form-control"
                    placeholder="09xxxxxxxx"
                    value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >

            </div>

            <div class="form-group">

                <label>
                    Mật khẩu *
                </label>

                <input
                    type="password"
                    name="password"
                    class="form-control"
                    required
                    minlength="6"
                    placeholder="Tối thiểu 6 ký tự"
                >

            </div>

            <div class="form-group">

                <label>
                    Nhập lại mật khẩu *
                </label>

                <input
                    type="password"
                    name="repassword"
                    class="form-control"
                    required
                    minlength="6"
                    placeholder="Nhập lại mật khẩu"
                >

            </div>

            <button
                type="submit"
                class="btn btn-accent"
                style="
                    width:100%;
                    padding:12px;
                    font-size:1.05rem;
                    margin-top:10px;
                "
            >
                ĐĂNG KÝ NGAY
            </button>

        </form>

        <div style="
            text-align:center;
            margin-top:20px;
            font-size:0.9rem;
        ">

            Đã có tài khoản?

            <a
                href="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/auth/login.php', ENT_QUOTES, 'UTF-8') ?>"
                style="
                    color:var(--primary);
                    font-weight:bold;
                "
            >
                Đăng nhập tại đây
            </a>

        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
