<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Khoi tao session neu file config chua khoi tao.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$page_title = 'Đăng nhập - VNN Travel';
$error = '';
$email = '';
$redirectUrl = '';

if (!function_exists('vnnLoginH')) {
    function vnnLoginH($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

// Ghi lich su dang nhap.
// Neu bang audit chua san sang, khong lam hong dang nhap.
if (!function_exists('vnnLoginAudit')) {
    function vnnLoginAudit(
        PDO $pdo,
        ?array $user,
        string $email,
        string $result,
        string $note = ''
    ): void {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO login_audit_logs (
                    user_id,
                    account_email,
                    account_name,
                    account_role,
                    ip_address,
                    user_agent,
                    result,
                    note
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $user['id'] ?? null,
                $email,
                $user['fullname'] ?? null,
                $user['role'] ?? null,
                substr(
                    (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    0,
                    45
                ),
                substr(
                    (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    0,
                    1000
                ),
                $result,
                $note
            ]);
        } catch (Throwable $e) {
            error_log(
                'Login audit: ' . $e->getMessage()
            );
        }
    }
}

// Chuyen trang sau dang nhap.
if (!function_exists('vnnLoginRedirect')) {
    function vnnLoginRedirect(string $url): void {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
            exit;
        }

        // Du phong khi header da duoc gui.
        echo '<!DOCTYPE html>';
        echo '<html lang="vi"><head>';
        echo '<meta charset="UTF-8">';
        echo '<meta http-equiv="refresh" content="0;url=' .
            htmlspecialchars(
                $url,
                ENT_QUOTES,
                'UTF-8'
            ) . '">';
        echo '<title>Đang chuyển trang</title>';
        echo '</head><body>';

        echo '<script>';
        echo 'window.location.replace(' .
            json_encode(
                $url,
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            ) .
            ');';
        echo '</script>';

        echo '<p>Đăng nhập thành công. ';
        echo '<a href="' .
            htmlspecialchars(
                $url,
                ENT_QUOTES,
                'UTF-8'
            ) .
            '">Tiếp tục</a></p>';

        echo '</body></html>';
        exit;
    }
}

// =========================================
// XU LY DANG NHAP
// =========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(
        trim((string)($_POST['email'] ?? ''))
    );

    $password = (string)(
        $_POST['password'] ?? ''
    );

    if (
        $email === '' ||
        $password === ''
    ) {
        $error = 'Vui lòng nhập email và mật khẩu.';

    } elseif (!filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )) {
        $error = 'Email không hợp lệ.';

    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE LOWER(email) = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $user = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            // Kiem tra email va mat khau
            if (
                !$user ||
                !password_verify(
                    $password,
                    (string)$user['password']
                )
            ) {
                vnnLoginAudit(
                    $pdo,
                    $user ?: null,
                    $email,
                    'failed',
                    'Thông tin đăng nhập không chính xác'
                );

                $error = 'Email hoặc mật khẩu không chính xác.';

            } elseif (
                (int)$user['status'] !== 1
            ) {
                vnnLoginAudit(
                    $pdo,
                    $user,
                    $email,
                    'locked',
                    'Tài khoản bị khóa'
                );

                $error = 'Tài khoản đang bị khóa. Vui lòng liên hệ quản trị viên.';

            } else {
                // ---------------------------------
                // DANG NHAP THANH CONG
                // ---------------------------------

                if (
                    session_status() === PHP_SESSION_ACTIVE &&
                    !headers_sent()
                ) {
                    session_regenerate_id(true);
                }

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                $role = strtolower(
                    trim((string)$user['role'])
                );

                // Ghi lich su, khong anh huong xac thuc.
                vnnLoginAudit(
                    $pdo,
                    $user,
                    $email,
                    'success',
                    'Đăng nhập thành công'
                );

                $baseUrl = rtrim(
                    BASE_URL,
                    '/'
                );

                // Dieu huong theo vai tro.
                switch ($role) {
                    case 'admin':
                        $redirectUrl =
                            $baseUrl .
                            '/admin/system.php?tab=users';
                        break;

                    case 'manager':
                    case 'staff':
                        $redirectUrl =
                            $baseUrl .
                            '/admin/index.php';
                        break;

                    default:
                        $redirectUrl =
                            $baseUrl .
                            '/index.php';
                        break;
                }

                // Bao dam session duoc ghi truoc khi chuyen.
                if (
                    session_status() === PHP_SESSION_ACTIVE
                ) {
                    session_write_close();
                }

                vnnLoginRedirect($redirectUrl);
            }

        } catch (Throwable $e) {
            error_log(
                'Login error: ' . $e->getMessage()
            );

            $error = 'Không thể đăng nhập lúc này. Vui lòng thử lại.';
        }
    }
}

// =========================================
// GIAO DIEN DANG NHAP
// =========================================

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.vnn-login-page {
    min-height: 72vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 45px 16px;
    background: #f3f6fa;
}

.vnn-login-card {
    width: 100%;
    max-width: 440px;
    padding: 34px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 18px 50px rgba(15,23,42,.08);
    box-sizing: border-box;
}

.vnn-login-card h1 {
    margin: 0 0 9px;
    color: #0f172a;
    text-align: center;
    font-size: 25px;
    font-weight: 900;
}

.vnn-login-subtitle {
    margin: 0 0 26px;
    color: #64748b;
    text-align: center;
    font-size: 13px;
}

.vnn-login-label {
    display: block;
    margin-bottom: 8px;
    color: #334155;
    font-size: 13px;
    font-weight: 800;
}

.vnn-login-field {
    margin-bottom: 18px;
}

.vnn-login-input {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: white;
    color: #0f172a;
    font: inherit;
    font-size: 14px;
    box-sizing: border-box;
}

.vnn-login-input:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22,163,74,.1);
}

.vnn-login-password {
    position: relative;
}

.vnn-login-password .vnn-login-input {
    padding-right: 50px;
}

.vnn-login-eye {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: none;
    color: #64748b;
    cursor: pointer;
    font-size: 16px;
}

.vnn-login-button {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 9px;
    width: 100%;
    padding: 14px 18px;
    border: 0;
    border-radius: 10px;
    background: #166534;
    color: white;
    font-size: 14px;
    font-weight: 900;
    cursor: pointer;
}

.vnn-login-button:hover {
    background: #14532d;
}

.vnn-login-error {
    padding: 13px 15px;
    margin-bottom: 18px;
    border-radius: 9px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 13px;
    line-height: 1.6;
}

.vnn-login-footer {
    margin-top: 22px;
    text-align: center;
    color: #64748b;
    font-size: 13px;
}

.vnn-login-footer a {
    color: #166534;
    font-weight: 700;
    text-decoration: none;
}

.vnn-login-footer a:hover {
    text-decoration: underline;
}

@media(max-width:550px) {
    .vnn-login-card {
        padding: 25px 20px;
    }
}
</style>

<main class="vnn-login-page">

    <section class="vnn-login-card">

        <h1>ĐĂNG NHẬP</h1>

        <p class="vnn-login-subtitle">
            Chào mừng đến với VNN Travel
        </p>

        <?php if ($error !== ''): ?>

            <div class="vnn-login-error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= vnnLoginH($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="vnn-login-field">

                <label
                    class="vnn-login-label"
                    for="vnnEmail"
                >
                    Email
                </label>

                <input
                    id="vnnEmail"
                    class="vnn-login-input"
                    type="email"
                    name="email"
                    placeholder="Nhập địa chỉ email"
                    value="<?= vnnLoginH($email) ?>"
                    autocomplete="username"
                    required
                >

            </div>

            <div class="vnn-login-field">

                <label
                    class="vnn-login-label"
                    for="vnnPassword"
                >
                    Mật khẩu
                </label>

                <div class="vnn-login-password">

                    <input
                        id="vnnPassword"
                        class="vnn-login-input"
                        type="password"
                        name="password"
                        placeholder="Nhập mật khẩu"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        class="vnn-login-eye"
                        type="button"
                        id="vnnShowPassword"
                        aria-label="Hiện hoặc ẩn mật khẩu"
                    >
                        <i class="fa-regular fa-eye"></i>
                    </button>

                </div>

            </div>

            <button
                class="vnn-login-button"
                type="submit"
            >
                <i class="fa-solid fa-right-to-bracket"></i>
                ĐĂNG NHẬP
            </button>

        </form>

        <div class="vnn-login-footer">

            Chưa có tài khoản?

            <a
                href="<?= vnnLoginH(
                    rtrim(BASE_URL, '/') .
                    '/auth/register.php'
                ) ?>"
            >
                Đăng ký
            </a>

        </div>

    </section>

</main>

<script>
(function() {
    const button = document.getElementById(
        'vnnShowPassword'
    );

    const input = document.getElementById(
        'vnnPassword'
    );

    if (!button || !input) {
        return;
    }

    button.addEventListener('click', function() {
        const show = input.type === 'password';

        input.type = show ? 'text' : 'password';

        const icon = button.querySelector('i');

        if (icon) {
            icon.className = show
                ? 'fa-regular fa-eye-slash'
                : 'fa-regular fa-eye';
        }
    });
})();
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
