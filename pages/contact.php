
<?php
// VNN Travel - Trang lien he
// Gui AJAX khong tai lai trang, hien thong bao thanh cong.

$page_title = "Liên Hệ & Hỗ Trợ";
$active_menu = 'contact';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Xu ly yeu cau gui tin nhan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isAjax = (
        ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
    );

    $fullname = trim((string)($_POST['fullname'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    $success = false;
    $responseMessage = '';

    if ($fullname === '' || $email === '' || $message === '') {
        $responseMessage = 'Vui lòng nhập đầy đủ họ tên, email và nội dung cần hỗ trợ!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $responseMessage = 'Địa chỉ email không hợp lệ!';
    } elseif (
        mb_strlen($fullname, 'UTF-8') > 150 ||
        mb_strlen($email, 'UTF-8') > 254 ||
        mb_strlen($phone, 'UTF-8') > 30 ||
        mb_strlen($message, 'UTF-8') > 5000
    ) {
        $responseMessage = 'Nội dung quá dài. Vui lòng kiểm tra lại!';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO contacts
                    (fullname, email, phone, message)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $fullname,
                $email,
                $phone,
                $message
            ]);

            $success = true;
            $responseMessage =
                'Tin nhắn của bạn đã được gửi thành công! '
                . 'VNN Travel sẽ liên hệ lại sớm nhất.';

        } catch (Throwable $e) {
            error_log('Contact form error: ' . $e->getMessage());
            $responseMessage =
                'Không thể gửi tin nhắn lúc này. Vui lòng thử lại.';
        }
    }

    // Phan hoi JSON cho JavaScript
    if ($isAjax) {
        http_response_code($success ? 200 : 400);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode([
            'success' => $success,
            'message' => $responseMessage
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Ho tro truong hop JavaScript bi tat
    $contactResult = [
        'success' => $success,
        'message' => $responseMessage
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.contact-main-box {
    background: #ffffff;
    padding: 40px;
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.contact-feedback {
    display: none;
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.6;
}

.contact-feedback.success {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #166534;
    background: #dcfce7;
    border: 1px solid #86efac;
}

.contact-feedback.error {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #b91c1c;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.contact-submit-btn {
    width: 100%;
    padding: 12px;
    background: #185b30;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
    transition: background 0.2s;
}

.contact-submit-btn:hover {
    background: #166534;
}

.contact-submit-btn:disabled {
    opacity: 0.7;
    cursor: wait;
}

.contact-details-row {
    margin-bottom: 15px;
    font-size: 0.95rem;
}

.contact-details-row i {
    color: var(--accent);
    width: 24px;
}

@media(max-width: 768px) {
    .contact-main-box {
        padding: 22px;
    }
}
</style>

<div class="container section" id="contact-section">

    <div class="grid grid-2 contact-main-box">

        <!-- THONG TIN LIEN HE -->
        <div>

            <h2 style="color:var(--primary);margin-bottom:15px;">
                LIÊN HỆ VỚI CHÚNG TÔI
            </h2>

            <p style="color:#64748b;line-height:1.7;margin-bottom:25px;">
                Bạn có câu hỏi về lịch khởi hành, yêu cầu thiết kế
                tour riêng cho cơ quan / nhóm bạn hoặc cần tư vấn
                chọn giày, balo? Đừng ngần ngại để lại lời nhắn!
            </p>

            <div class="contact-details-row">
                <i class="fa-solid fa-location-dot"></i>
                Số 18 Hoàng Quốc Việt, Cầu Giấy, Hà Nội
            </div>

            <div class="contact-details-row">
                <i class="fa-solid fa-phone"></i>
                Hotline: 0988.888.888
            </div>

            <div class="contact-details-row">
                <i class="fa-solid fa-envelope"></i>
                contact@vnntravel.vn
            </div>

            <div class="contact-details-row">
                <i class="fa-solid fa-clock"></i>
                08:00 - 21:00 (Từ Thứ Hai đến Chủ Nhật)
            </div>

        </div>

        <!-- FORM LIEN HE -->
        <div>

            <div
                id="contact-feedback"
                class="contact-feedback"
                role="status"
                aria-live="polite"
            ></div>

            <?php if (isset($contactResult)): ?>
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
                action=""
                method="POST"
            >

                <div class="form-group">
                    <label for="contact-fullname">
                        Họ và tên *
                    </label>

                    <input
                        id="contact-fullname"
                        type="text"
                        name="fullname"
                        class="form-control"
                        required
                        maxlength="150"
                        placeholder="Nguyễn Văn A"
                    >
                </div>

                <div class="grid grid-2">

                    <div class="form-group">
                        <label for="contact-email">
                            Địa chỉ Email *
                        </label>

                        <input
                            id="contact-email"
                            type="email"
                            name="email"
                            class="form-control"
                            required
                            maxlength="254"
                            placeholder="email@gmail.com"
                        >
                    </div>

                    <div class="form-group">
                        <label for="contact-phone">
                            Số điện thoại
                        </label>

                        <input
                            id="contact-phone"
                            type="tel"
                            name="phone"
                            class="form-control"
                            maxlength="30"
                            placeholder="09xxxxxxxx"
                        >
                    </div>

                </div>

                <div class="form-group">

                    <label for="contact-message">
                        Nội dung cần hỗ trợ *
                    </label>

                    <textarea
                        id="contact-message"
                        name="message"
                        class="form-control"
                        rows="4"
                        required
                        maxlength="5000"
                        placeholder="Nhập câu hỏi của bạn..."
                    ></textarea>

                </div>

                <button
                    id="contact-submit"
                    type="submit"
                    class="btn btn-primary contact-submit-btn"
                >
                    <i class="fa-solid fa-paper-plane"></i>
                    GỬI TIN NHẮN LIÊN HỆ
                </button>

            </form>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('contact-form');
    const button = document.getElementById('contact-submit');
    const feedback = document.getElementById('contact-feedback');

    if (!form || !button || !feedback) return;

    let sending = false;

    function showFeedback(message, success) {
        feedback.className =
            'contact-feedback ' + (success ? 'success' : 'error');

        feedback.replaceChildren();

        const icon = document.createElement('i');
        icon.className = success
            ? 'fa-solid fa-circle-check'
            : 'fa-solid fa-circle-exclamation';

        const text = document.createElement('span');
        text.textContent = message;

        feedback.appendChild(icon);
        feedback.appendChild(text);
    }

    form.addEventListener('submit', async function (event) {

        // Ngan trinh duyet tai lai trang
        event.preventDefault();

        if (sending) return;

        if (!form.reportValidity()) return;

        sending = true;
        button.disabled = true;

        const oldButtonContent = button.innerHTML;

        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> ĐANG GỬI...';

        feedback.className = 'contact-feedback';
        feedback.replaceChildren();

        try {
            const formData = new FormData(form);

            const response = await fetch(
                window.location.pathname,
                {
                    method: 'POST',
                    body: formData,
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
            } catch (error) {
                throw new Error(
                    'Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại.'
                );
            }

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Không thể gửi tin nhắn.'
                );
            }

            // Thong bao thanh cong
            showFeedback(
                result.message ||
                'Tin nhắn của bạn đã được gửi thành công!',
                true
            );

            // Chi xoa thong tin form khi server xac nhan da luu
            form.reset();

            // Khong cuon trang ve dau, khong chuyen huong

        } catch (error) {

            console.error('Contact form error:', error);

            showFeedback(
                error.message ||
                'Có lỗi xảy ra. Vui lòng thử lại.',
                false
            );

            // Giu nguyen noi dung nguoi dung da nhap

        } finally {

            sending = false;
            button.disabled = false;
            button.innerHTML = oldButtonContent;
        }

    });

});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
