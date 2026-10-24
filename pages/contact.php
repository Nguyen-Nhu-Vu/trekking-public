<?php
// VNN Travel - Lien he va ho tro
// AJAX contact form - JSON response - No page reload

ob_start();

$page_title = "Liên Hệ & Hỗ Trợ";
$active_menu = 'contact';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$contactResult = null;

function contactJson($success, $message, $status = 200)
{
    // Loai bo output khong mong muon truoc JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo json_encode([
        'success' => $success,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Xu ly gui form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isAjax = (
        strtolower(
            $_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''
        ) === 'xmlhttprequest'
    );

    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    $success = false;
    $responseMessage = '';
    $statusCode = 200;

    if (
        $fullname === '' ||
        $email === '' ||
        $message === ''
    ) {
        $responseMessage =
            'Vui lòng nhập đầy đủ họ tên, email và nội dung hỗ trợ.';
        $statusCode = 422;

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $responseMessage = 'Địa chỉ email không hợp lệ.';
        $statusCode = 422;

    } elseif (
        mb_strlen($fullname, 'UTF-8') > 150 ||
        mb_strlen($email, 'UTF-8') > 254 ||
        mb_strlen($phone, 'UTF-8') > 30 ||
        mb_strlen($message, 'UTF-8') > 5000
    ) {
        $responseMessage = 'Nội dung nhập quá dài.';
        $statusCode = 422;

    } else {

        try {
            // Su dung bang contacts hien co
            $stmt = $pdo->prepare("
                INSERT INTO contacts
                    (fullname, email, phone, message)
                VALUES
                    (:fullname, :email, :phone, :message)
            ");

            $stmt->execute([
                ':fullname' => $fullname,
                ':email' => $email,
                ':phone' => $phone,
                ':message' => $message
            ]);

            $success = true;
            $responseMessage =
                'Tin nhắn đã được gửi thành công! '
                . 'VNN Travel sẽ liên hệ lại với bạn sớm nhất.';

        } catch (Throwable $e) {
            error_log(
                'VNN Contact Error: ' . $e->getMessage()
            );

            $success = false;
            $statusCode = 500;
            $responseMessage =
                'Không thể lưu tin nhắn lúc này. '
                . 'Vui lòng thử lại sau.';
        }
    }

    // Tra ve JSON sach cho AJAX
    if ($isAjax) {
        contactJson(
            $success,
            $responseMessage,
            $statusCode
        );
    }

    // Truong hop trinh duyet khong co JavaScript
    $contactResult = [
        'success' => $success,
        'message' => $responseMessage
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.contact-page {
    padding-top: 45px;
    padding-bottom: 65px;
    min-height: 65vh;
}

.contact-main-box {
    background: #ffffff;
    padding: 40px;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.contact-title {
    color: #185b30;
    font-size: 25px;
    font-weight: 800;
    margin-bottom: 15px;
}

.contact-description {
    color: #64748b;
    line-height: 1.7;
    margin-bottom: 25px;
}

.contact-detail {
    margin-bottom: 15px;
    font-size: 15px;
    color: #334155;
}

.contact-detail i {
    color: #f97316;
    width: 25px;
}

.contact-form-group {
    margin-bottom: 18px;
}

.contact-form-group label {
    display: block;
    color: #0f172a;
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 9px;
}

.contact-form-group .form-control {
    width: 100%;
    border: 1px solid #dce3ed;
    border-radius: 9px;
    padding: 13px 15px;
    font-size: 15px;
    outline: none;
}

.contact-form-group .form-control:focus {
    border-color: #f97316;
    box-shadow: 0 0 0 2px rgba(249,115,22,0.08);
}

.contact-form-group textarea {
    min-height: 115px;
    resize: vertical;
}

.contact-submit-btn {
    width: 100%;
    padding: 14px 16px;
    border: none;
    border-radius: 9px;
    background: #176534;
    color: #ffffff;
    cursor: pointer;
    font-weight: 800;
    font-size: 15px;
}

.contact-submit-btn:hover {
    background: #14532d;
}

.contact-submit-btn:disabled {
    opacity: 0.7;
    cursor: wait;
}

.contact-feedback {
    display: none;
    padding: 15px 18px;
    border-radius: 11px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
    font-weight: 600;
    align-items: center;
    gap: 12px;
}

.contact-feedback.success {
    display: flex;
    color: #166534;
    background: #dcfce7;
    border: 1px solid #86efac;
}

.contact-feedback.error {
    display: flex;
    color: #b91c1c;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.contact-feedback i {
    font-size: 18px;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .contact-main-box {
        padding: 22px;
    }

    .contact-page {
        padding-top: 20px;
    }
}
</style>

<section class="contact-page container section">

    <div class="grid grid-2 contact-main-box">

        <!-- THONG TIN LIEN HE -->
        <div>

            <h2 class="contact-title">
                LIÊN HỆ VỚI CHÚNG TÔI
            </h2>

            <p class="contact-description">
                Bạn có câu hỏi về lịch khởi hành, yêu cầu thiết kế
                tour riêng cho cơ quan / nhóm bạn hoặc cần tư vấn
                chọn giày, balo? Đừng ngần ngại để lại lời nhắn!
            </p>

            <div class="contact-detail">
                <i class="fa-solid fa-location-dot"></i>
                Số 18 Hoàng Quốc Việt, Cầu Giấy, Hà Nội
            </div>

            <div class="contact-detail">
                <i class="fa-solid fa-phone"></i>
                Hotline: 0988.888.888
            </div>

            <div class="contact-detail">
                <i class="fa-solid fa-envelope"></i>
                contact@vnntravel.vn
            </div>

            <div class="contact-detail">
                <i class="fa-solid fa-clock"></i>
                08:00 - 21:00 (Từ Thứ Hai đến Chủ Nhật)
            </div>

        </div>

        <!-- BIEU MAU LIEN HE -->
        <div>

            <div
                id="contact-feedback"
                class="contact-feedback"
                role="status"
                aria-live="polite"
            ></div>

            <?php if ($contactResult !== null): ?>
                <div class="contact-feedback <?= $contactResult['success'] ? 'success' : 'error' ?>"
                     style="display:flex;">
                    <?= htmlspecialchars(
                        $contactResult['message'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <form
                id="contact-form"
                method="POST"
                action="<?= htmlspecialchars(
                    $_SERVER['REQUEST_URI'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

                <div class="contact-form-group">
                    <label for="contact-fullname">
                        Họ và tên *
                    </label>

                    <input
                        type="text"
                        id="contact-fullname"
                        name="fullname"
                        class="form-control"
                        placeholder="Nguyễn Văn A"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="grid grid-2">

                    <div class="contact-form-group">
                        <label for="contact-email">
                            Địa chỉ Email *
                        </label>

                        <input
                            type="email"
                            id="contact-email"
                            name="email"
                            class="form-control"
                            placeholder="email@gmail.com"
                            maxlength="254"
                            required
                        >
                    </div>

                    <div class="contact-form-group">
                        <label for="contact-phone">
                            Số điện thoại
                        </label>

                        <input
                            type="tel"
                            id="contact-phone"
                            name="phone"
                            class="form-control"
                            placeholder="09xxxxxxxx"
                            maxlength="30"
                        >
                    </div>

                </div>

                <div class="contact-form-group">
                    <label for="contact-message">
                        Nội dung cần hỗ trợ *
                    </label>

                    <textarea
                        id="contact-message"
                        name="message"
                        class="form-control"
                        rows="4"
                        maxlength="5000"
                        placeholder="Nhập câu hỏi của bạn..."
                        required
                    ></textarea>
                </div>

                <button
                    type="submit"
                    id="contact-submit"
                    class="contact-submit-btn"
                >
                    <i class="fa-solid fa-paper-plane"></i>
                    GỬI TIN NHẮN LIÊN HỆ
                </button>

            </form>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';

    const form = document.getElementById('contact-form');
    const button = document.getElementById('contact-submit');
    const feedback = document.getElementById('contact-feedback');

    if (!form || !button || !feedback) return;

    let isSending = false;

    function showMessage(message, success) {
        feedback.className =
            'contact-feedback ' + (success ? 'success' : 'error');

        feedback.replaceChildren();

        const icon = document.createElement('i');

        icon.className = success
            ? 'fa-solid fa-circle-check'
            : 'fa-solid fa-circle-exclamation';

        const text = document.createElement('span');
        text.textContent = message;

        feedback.append(icon, text);
    }

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (isSending || !form.reportValidity()) return;

        isSending = true;
        button.disabled = true;

        const originalButton = button.innerHTML;

        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> ĐANG GỬI...';

        feedback.className = 'contact-feedback';
        feedback.replaceChildren();

        try {
            const response = await fetch(
                form.action || window.location.href,
                {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }
            );

            const raw = await response.text();
            let result;

            try {
                result = JSON.parse(raw);
            } catch (parseError) {
                console.error(
                    'Contact API returned non-JSON:',
                    response.status,
                    raw.slice(0, 500)
                );

                throw new Error(
                    'Máy chủ chưa phản hồi đúng định dạng. ' +
                    'Vui lòng kiểm tra kết nối hoặc nhật ký Vercel.'
                );
            }

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message ||
                    'Không thể gửi tin nhắn lúc này.'
                );
            }

            showMessage(
                result.message ||
                'Tin nhắn đã được gửi thành công!',
                true
            );

            // Chi reset khi server xac nhan thanh cong
            form.reset();

        } catch (error) {
            console.error('Contact error:', error);

            showMessage(
                error.message || 'Có lỗi xảy ra. Vui lòng thử lại.',
                false
            );

            // Giu nguyen thong tin da nhap
        } finally {
            isSending = false;
            button.disabled = false;
            button.innerHTML = originalButton;
        }
    });
})();
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
