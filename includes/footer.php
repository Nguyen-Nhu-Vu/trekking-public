
<?php
// VNN Travel - Footer and Customer Support Chat
$chatLoggedIn = isLoggedIn();
$chatBaseUrl = defined('BASE_URL') ? BASE_URL : '/';
?>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;height:50px;">
                    <div style="width:64px;height:48px;flex-shrink:0;">
                        <svg viewBox="0 0 180 115" xmlns="http://www.w3.org/2000/svg">
                            <polygon points="50,42 22,86 82,86" fill="#58a82d"/>
                            <polygon points="50,42 58,56 44,56" fill="#fff"/>
                            <polygon points="95,8 42,94 150,94" fill="#4d9e26"/>
                            <polygon points="95,8 116,40 105,36 95,48 85,36 76,42" fill="#fff"/>
                            <polygon points="135,38 106,86 170,86" fill="#3b7a1e"/>
                            <polygon points="135,38 144,52 128,52" fill="#fff"/>
                            <path d="M6,90 C48,58 132,58 174,90 C140,84 40,84 6,90 Z" fill="#6db832"/>
                            <path d="M16,88 C52,74 128,74 164,88 C138,102 42,102 16,88 Z" fill="#1b5e20"/>
                        </svg>
                    </div>
                    <div style="font-weight:900;line-height:1.1;">
                        <div style="font-size:1.7rem;letter-spacing:2px;">
                            <span style="color:#38bdf8;">V</span><span style="color:#f97316;">N</span><span style="color:#f59e0b;">N</span>
                        </div>
                        <div style="letter-spacing:3px;color:#cbd5e1;font-size:0.8rem;">TRAVEL</div>
                    </div>
                </div>
                <p style="font-size:0.9rem;line-height:1.7;">
                    Hệ thống cung cấp tour trekking, leo núi và dụng cụ
                    dã ngoại chuyên nghiệp tại Việt Nam.
                </p>
                <div style="margin-top:15px;display:flex;gap:12px;font-size:1.2rem;">
                    <a href="#"><i class="fa-brands fa-facebook" style="color:#38bdf8;"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube" style="color:#ef4444;"></i></a>
                    <a href="#"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="#"><i class="fa-brands fa-instagram" style="color:#ec4899;"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Về VNN Travel</h4>
                <ul>
                    <li><a href="<?= $chatBaseUrl ?>tours/tour.php">Danh sách tour trekking</a></li>
                    <li><a href="<?= $chatBaseUrl ?>pages/locations.php">Các đỉnh núi nổi tiếng</a></li>
                    <li><a href="<?= $chatBaseUrl ?>shop/shop.php">Dụng cụ dã ngoại</a></li>
                    <li><a href="<?= $chatBaseUrl ?>pages/blog.php">Kinh nghiệm leo núi</a></li>
                    <li><a href="<?= $chatBaseUrl ?>pages/contact.php">Hỗ trợ & Liên hệ</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Khách Hàng</h4>
                <ul>
                    <li><a href="<?= $chatBaseUrl ?>auth/profile.php">Tài khoản của tôi</a></li>
                    <li><a href="<?= $chatBaseUrl ?>tours/my-tours.php">Lịch sử đăng ký tour</a></li>
                    <li><a href="<?= $chatBaseUrl ?>shop/my-orders.php">Đơn mua trang bị</a></li>
                    <li><a href="<?= $chatBaseUrl ?>shop/cart.php">Giỏ hàng</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Liên Hệ Văn Phòng</h4>
                <p>Số 18 Hoàng Quốc Việt, Cầu Giấy, Hà Nội</p>
                <p>Hotline: 0988.888.888</p>
                <p>contact@vnntravel.vn</p>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            &copy; 2026 VNN Travel. Nền tảng Tour Trekking & Thiết Bị Dã Ngoại Chuyên Nghiệp.
        </div>
    </div>
</footer>

<style>
.chat-float-btn {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 65px;
    height: 65px;
    background: rgba(234,88,12,.25);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 99999;
    transition: transform .2s;
}
.chat-float-btn:hover { transform: scale(1.08); }
.chat-float-inner {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg,#ff6b35,#ea580c);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(234,88,12,.45);
}
.chat-float-inner i { color: #fff; font-size: 1.35rem; }
.chatbox-window {
    display: none;
    position: fixed;
    bottom: 100px;
    right: 25px;
    width: min(340px,calc(100vw - 30px));
    height: 440px;
    max-height: calc(100vh - 125px);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 35px rgba(0,0,0,.2);
    border: 1px solid #fed7aa;
    flex-direction: column;
    overflow: hidden;
    z-index: 99999;
}
.chatbox-head {
    background: linear-gradient(135deg,#f97316,#ea580c);
    color: #fff;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.chatbox-messages {
    flex: 1;
    min-height: 0;
    padding: 14px;
    background: #f8fafc;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.chat-bubble {
    max-width: 80%;
    padding: 8px 14px;
    font-size: .9rem;
    line-height: 1.45;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}
.chat-bubble.me {
    align-self: flex-end;
    background: #f97316;
    color: #fff;
    border-radius: 14px 14px 0 14px;
}
.chat-bubble.other {
    align-self: flex-start;
    background: #fff;
    color: #1e293b;
    border-radius: 14px 14px 14px 0;
    border: 1px solid #e2e8f0;
}
.chatbox-footer {
    display: flex;
    padding: 8px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
}
.chatbox-footer input { min-width: 0; }
.chatbox-footer button:disabled { opacity: .6; cursor: wait; }
.chat-status {
    font-size: .76rem;
    color: #64748b;
    padding: 5px 12px;
    background: #fff7ed;
}
</style>

<div class="chat-float-btn"
     onclick="toggleChatBox()"
     title="Trò chuyện trực tuyến"
     role="button"
     tabindex="0"
     onkeydown="if(event.key==='Enter')toggleChatBox()">
    <div class="chat-float-inner">
        <i class="fa-regular fa-comment"></i>
    </div>
</div>

<div id="chatbox-app" class="chatbox-window">
    <div class="chatbox-head">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-headset"></i>
            <div>
                <div id="chat-target-name"
                     style="font-weight:700;font-size:.95rem;">
                    Hỗ Trợ VNN Travel
                </div>
                <small style="font-size:.75rem;opacity:.85;">
                    Tư vấn khách hàng
                </small>
            </div>
        </div>
        <button type="button"
                onclick="toggleChatBox()"
                aria-label="Đóng chat"
                style="background:none;border:0;color:white;cursor:pointer;font-size:1.2rem;">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div id="chat-messages-container" class="chatbox-messages">
        <?php if (!$chatLoggedIn): ?>
            <div style="text-align:center;padding:30px 10px;color:#64748b;">
                <i class="fa-solid fa-lock"
                   style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                Vui lòng
                <a href="<?= $chatBaseUrl ?>auth/login.php"
                   style="color:#f97316;font-weight:bold;">đăng nhập</a>
                để trò chuyện!
            </div>
        <?php endif; ?>
    </div>

    <?php if ($chatLoggedIn): ?>
        <div id="customer-chat-status" class="chat-status" aria-live="polite"></div>
        <form onsubmit="handleSendChat(event)" class="chatbox-footer">
            <input type="text"
                   id="chat-input-text"
                   placeholder="Nhập câu hỏi..."
                   maxlength="5000"
                   class="form-control"
                   style="flex:1;border-radius:20px 0 0 20px;padding:8px 14px;"
                   required>
            <button id="customer-chat-submit"
                    type="submit"
                    class="btn btn-primary btn-sm"
                    style="border-radius:0 20px 20px 0;padding:8px 16px;">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
const customerChatEnabled = <?= $chatLoggedIn ? 'true' : 'false' ?>;
const chatApiUrl = <?= json_encode(
    $chatBaseUrl . 'chat-api.php',
    JSON_UNESCAPED_SLASHES
) ?>;

let chatWithUserId = 0;
let customerChatLoading = false;
let customerChatSending = false;
let customerLastSignature = null;
let customerChatRequestVersion = 0;

function customerChatStatus(text) {
    const el = document.getElementById('customer-chat-status');
    if (el) el.textContent = text || '';
}

function toggleChatBox() {
    const box = document.getElementById('chatbox-app');
    if (!box) return;

    const isOpen = box.style.display === 'flex';
    box.style.display = isOpen ? 'none' : 'flex';

    if (!isOpen && customerChatEnabled) {
        customerLastSignature = null;
        refreshChatMessages();
    }
}

async function customerApi(action, options = {}) {
    const response = await fetch(chatApiUrl + '?action=' + action, {
        credentials: 'same-origin',
        cache: 'no-store',
        ...options
    });

    const text = await response.text();
    let data;

    try {
        data = JSON.parse(text);
    } catch {
        throw new Error('API không trả về JSON hợp lệ.');
    }

    if (!response.ok || data.status !== 'success') {
        throw new Error(data.message || 'Yêu cầu thất bại.');
    }

    return data;
}

async function refreshChatMessages() {
    if (!customerChatEnabled || customerChatLoading) return;

    const box = document.getElementById('chatbox-app');
    if (!box || box.style.display !== 'flex') return;

    customerChatLoading = true;
    const version = ++customerChatRequestVersion;

    try {
        const result = await customerApi(
            'get&with_id=' + encodeURIComponent(chatWithUserId)
        );

        if (version !== customerChatRequestVersion) return;

        chatWithUserId = Number(result.with_id) || 0;
        const title = document.getElementById('chat-target-name');
        title.textContent =
            result.target_name || 'Bộ phận hỗ trợ VNN Travel';

        const container =
            document.getElementById('chat-messages-container');

        const messages = Array.isArray(result.data)
            ? result.data
            : [];

        const signature = messages
            .map(m => String(m.id) + ':' + String(m.message))
            .join('|');

        if (customerLastSignature !== signature) {
            const nearBottom =
                container.scrollHeight -
                container.scrollTop -
                container.clientHeight < 100;

            const firstLoad = customerLastSignature === null;
            container.replaceChildren();

            if (!messages.length) {
                const empty = document.createElement('div');
                empty.style.cssText =
                    'text-align:center;color:#64748b;padding:24px 8px;';
                empty.textContent =
                    'Hãy gửi lời chào tới VNN Travel!';
                container.appendChild(empty);
            }

            messages.forEach(message => {
                const bubble = document.createElement('div');
                const isMe =
                    Number(message.sender_id) ===
                    Number(result.current_user_id);

                bubble.className =
                    'chat-bubble ' + (isMe ? 'me' : 'other');
                bubble.textContent = message.message;
                container.appendChild(bubble);
            });

            if (nearBottom || firstLoad) {
                container.scrollTop = container.scrollHeight;
            }

            customerLastSignature = signature;
        }

        customerChatStatus('');
    } catch (error) {
        console.error('Customer chat:', error);
        customerChatStatus('Mất kết nối. Đang thử lại...');
    } finally {
        customerChatLoading = false;
    }
}

async function handleSendChat(event) {
    event.preventDefault();
    if (!customerChatEnabled || customerChatSending) return;

    const input = document.getElementById('chat-input-text');
    const button = document.getElementById('customer-chat-submit');
    const message = input.value.trim();

    if (!message) return;

    customerChatSending = true;
    button.disabled = true;
    customerChatStatus('Đang gửi...');

    try {
        const data = new FormData();
        data.append('receiver_id', String(chatWithUserId));
        data.append('message', message);

        const result = await customerApi('send', {
            method: 'POST',
            body: data
        });

        input.value = '';
        chatWithUserId =
            Number(result.receiver_id) || chatWithUserId;

        customerLastSignature = null;
        customerChatStatus('');
        await refreshChatMessages();
    } catch (error) {
        console.error('Send chat:', error);
        customerChatStatus(
            'Không gửi được tin nhắn. Hãy thử lại.'
        );
    } finally {
        customerChatSending = false;
        button.disabled = false;
        input.focus();
    }
}

// Poll only when chat is visible.
// Prevent overlapping requests.
setInterval(() => {
    if (!document.hidden) {
        refreshChatMessages();
    }
}, 5000);

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        refreshChatMessages();
    }
});
</script>

<script src="<?= $chatBaseUrl ?>assets/js/main.js"></script>
</body>
</html>
