
<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Đăng Nhập - VNN Travel';
$error = '';
$email = '';

if (!function_exists('vnnLoginH')) {
    function vnnLoginH($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vnnRecordLogin')) {
    function vnnRecordLogin(
        PDO $pdo,
        ?array $user,
        string $email,
        string $result,
        string $note = ''
    ): void {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO login_audit_logs
                (
                    user_id,account_email,account_name,account_role,
                    ip_address,user_agent,result,note
                )
                VALUES (?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $user ? (int)$user['id'] : null,
                $email,
                $user['fullname'] ?? null,
                $user['role'] ?? null,
                substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
                $result,
                $note
            ]);
        } catch (Throwable $e) {
            error_log('Login audit: '.$e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM users
            WHERE LOWER(email) = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            vnnRecordLogin(
                $pdo, $user ?: null, $email,
                'failed', 'Email hoặc mật khẩu không chính xác'
            );

            $error = 'Email hoặc mật khẩu không chính xác.';
        } elseif ((int)$user['status'] === 0) {
            vnnRecordLogin(
                $pdo, $user, $email,
                'locked', 'Tài khoản bị khóa'
            );

            $error = 'Tài khoản của bạn đang bị khóa.';
        } else {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            vnnRecordLogin(
                $pdo, $user, $email,
                'success', 'Đăng nhập thành công'
            );

            $base = rtrim(BASE_URL, '/') . '/';

            if ($user['role'] === 'admin') {
                $url = $base.'admin/system.php?tab=users';
            } elseif (in_array(
                $user['role'],
                ['manager','staff'],
                true
            )) {
                $url = $base.'admin/index.php';
            } else {
                $url = $base.'index.php';
            }

            header('Location: '.$url, true, 303);
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.vnn-login-wrap {
    min-height: 65vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 45px 15px;
    background: #f4f7fb;
}
.vnn-login-card {
    width: 100%;
    max-width: 445px;
    padding: 32px;
    border-radius: 16px;
    background: white;
    border: 1px solid #e2e8f0;
    box-shadow: 0 15px 45px rgba(15,23,42,.08);
}
.vnn-login-card h1 {
    color: #0f172a;
    text-align: center;
    font-size: 26px;
    margin: 0 0 9px;
}
.vnn-login-card p {
    text-align: center;
    color: #64748b;
    font-size: 13px;
    margin-bottom: 24px;
}
.vnn-login-field { margin-bottom: 16px; }
.vnn-login-field label {
    display: block;
    font-weight: 800;
    color: #334155;
    margin-bottom: 8px;
    font-size: 13px;
}
.vnn-login-field input {
    width: 100%;
    box-sizing: border-box;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font: inherit;
}
.vnn-login-error {
    background: #fee2e2;
    color: #991b1b;
    border-radius: 9px;
    padding: 12px;
    margin-bottom: 18px;
    font-size: 13px;
}
.vnn-login-submit {
    width: 100%;
    background: #166534;
    color: white;
    border: none;
    border-radius: 9px;
    padding: 13px;
    font-weight: 800;
    cursor: pointer;
}
.vnn-password-box { position: relative; }
.vnn-password-box input { padding-right: 48px; }
.vnn-eye {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    cursor: pointer;
    color: #64748b;
}
.vnn-login-bottom {
    margin-top: 20px;
    text-align: center;
    font-size: 13px;
}
.vnn-login-bottom a { color: #166534; }
</style>

<main class="vnn-login-wrap">
    <div class="vnn-login-card">
        <h1>ĐĂNG NHẬP</h1>
        <p>Chào mừng đến với VNN Travel</p>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="vnn-login-error">
                <?= vnnLoginH($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="vnn-login-error"><?= vnnLoginH($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="vnn-login-field">
                <label for="loginEmail">Email</label>
                <input id="loginEmail" name="email" type="email"
                       required autocomplete="username"
                       value="<?= vnnLoginH($email) ?>"
                       placeholder="Nhập địa chỉ email">
            </div>

            <div class="vnn-login-field">
                <label for="loginPassword">Mật khẩu</label>
                <div class="vnn-password-box">
                    <input id="loginPassword" name="password"
                           type="password" required
                           autocomplete="current-password"
                           placeholder="Nhập mật khẩu">

                    <button type="button" class="vnn-eye"
                            id="vnnTogglePassword"
                            aria-label="Hiện hoặc ẩn mật khẩu">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button class="vnn-login-submit" type="submit">
                ĐĂNG NHẬP
            </button>
        </form>

        <div class="vnn-login-bottom">
            Chưa có tài khoản?
            <a href="<?= vnnLoginH(rtrim(BASE_URL,'/').'/auth/register.php') ?>">
                Đăng ký
            </a>
        </div>
    </div>
</main>

<script>
(function() {
    const btn = document.getElementById('vnnTogglePassword');
    const input = document.getElementById('loginPassword');

    btn.addEventListener('click', function() {
        input.type = input.type === 'password' ? 'text' : 'password';
        btn.querySelector('i').className = input.type === 'password'
            ? 'fa-regular fa-eye'
            : 'fa-regular fa-eye-slash';
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
