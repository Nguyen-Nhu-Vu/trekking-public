<?php
// trekking/includes/footer.php
?>
<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <!-- LOGO CHÂN TRANG: NÚI BÊN TRÁI, VNN + TRAVEL 2 DÒNG BÊN PHẢI -->
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:15px; height:50px;">
                    <div style="width:64px; height:48px; flex-shrink:0;">
                        <svg viewBox="0 0 180 115" xmlns="http://www.w3.org/2000/svg">
                            <polygon points="50,42 22,86 82,86" fill="#58a82d" />
                            <polygon points="50,42 58,56 44,56" fill="#ffffff" />
                            <polygon points="95,8 42,94 150,94" fill="#4d9e26" />
                            <polygon points="95,8 116,40 105,36 95,48 85,36 76,42" fill="#ffffff" />
                            <polygon points="135,38 106,86 170,86" fill="#3b7a1e" />
                            <polygon points="135,38 144,52 128,52" fill="#ffffff" />
                            <path d="M6,90 C48,58 132,58 174,90 C140,84 40,84 6,90 Z" fill="#6db832" />
                            <path d="M16,88 C52,74 128,74 164,88 C138,102 42,102 16,88 Z" fill="#1b5e20" />
                            <path d="M88,80 C90,86 82,90 87,96 C90,98 104,98 107,92 C102,88 96,84 94,80 Z" fill="#ffffff" />
                        </svg>
                    </div>
                    <div style="display:flex; flex-direction:column; justify-content:space-between; height:46px;">
                        <div style="font-size:1.6rem; font-weight:900; line-height:1; letter-spacing:1px;">
                            <span style="color:#38bdf8;">V</span>
                            <span style="color:#f97316;">N</span>
                            <span style="color:#f59e0b;">N</span>
                        </div>
                        <div style="font-size:0.8rem; font-weight:800; color:#cbd5e1; letter-spacing:3px; line-height:1;">
                            TRAVEL
                        </div>
                    </div>
                </div>
                <p style="font-size:0.9rem; line-height:1.7;">Hệ thống cung cấp tour trải nghiệm trekking, leo núi chuyên nghiệp hàng đầu Việt Nam kết hợp cửa hàng dụng cụ leo núi dã ngoại chính hãng chuẩn quốc tế.</p>
                <div style="margin-top:15px; display:flex; gap:12px; font-size:1.2rem;">
                    <a href="#"><i class="fa-brands fa-facebook" style="color:#38bdf8;"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube" style="color:#ef4444;"></i></a>
                    <a href="#"><i class="fa-brands fa-tiktok" style="color:white;"></i></a>
                    <a href="#"><i class="fa-brands fa-instagram" style="color:#ec4899;"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Về VNN Travel</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>tours/tour.php">Danh sách tour trekking</a></li>
                    <li><a href="<?= BASE_URL ?>pages/locations.php">Các đỉnh núi nổi tiếng</a></li>
                    <li><a href="<?= BASE_URL ?>shop/shop.php">Dụng cụ dã ngoại</a></li>
                    <li><a href="<?= BASE_URL ?>pages/blog.php">Kinh nghiệm leo núi</a></li>
                    <li><a href="<?= BASE_URL ?>pages/contact.php">Hỗ trợ & Liên hệ</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Khách Hàng</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>auth/profile.php">Tài khoản của tôi</a></li>
                    <li><a href="<?= BASE_URL ?>tours/my-tours.php">Lịch sử đăng ký tour</a></li>
                    <li><a href="<?= BASE_URL ?>shop/my-orders.php">Đơn mua trang bị</a></li>
                    <li><a href="<?= BASE_URL ?>shop/cart.php">Giỏ hàng hiện tại</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Liên Hệ Văn Phòng</h4>
                <p style="font-size:0.9rem; margin-bottom:8px;"><i class="fa-solid fa-location-dot" style="color:var(--accent);"></i> Số 18 Hoàng Quốc Việt, Cầu Giấy, Hà Nội</p>
                <p style="font-size:0.9rem; margin-bottom:8px;"><i class="fa-solid fa-phone" style="color:var(--accent);"></i> Hotline: 0988.888.888</p>
                <p style="font-size:0.9rem; margin-bottom:8px;"><i class="fa-solid fa-envelope" style="color:var(--accent);"></i> contact@vnntravel.vn</p>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            &copy; 2026 VNN Travel. Nền tảng Tour Trekking & Thiết Bị Dã Ngoại Chuyên Nghiệp.
        </div>
    </div>
</footer>

<!-- NÚT CHATBOX NỔI GÓC PHẢI -->
<style>
.chat-float-btn {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 65px;
    height: 65px;
    background: rgba(234, 88, 12, 0.25);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 99999;
    transition: transform 0.25s ease;
}
.chat-float-btn:hover { transform: scale(1.08); }
.chat-float-inner {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #ff6b35, #ea580c);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.45);
}
.chat-float-inner i { color: #ffffff; font-size: 1.35rem; }
.chatbox-window {
    display: none;
    position: fixed;
    bottom: 100px;
    right: 25px;
    width: 340px;
    height: 440px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 35px rgba(0,0,0,0.2);
    border: 1px solid #fed7aa;
    flex-direction: column;
    overflow: hidden;
    z-index: 99999;
}
.chatbox-head {
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: white;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.chatbox-messages {
    flex: 1;
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
    font-size: 0.9rem;
    line-height: 1.45;
}
.chat-bubble.me {
    align-self: flex-end;
    background: #f97316;
    color: #ffffff;
    border-radius: 14px 14px 0 14px;
}
.chat-bubble.other {
    align-self: flex-start;
    background: #ffffff;
    color: #1e293b;
    border-radius: 14px 14px 14px 0;
    border: 1px solid #e2e8f0;
}
.chatbox-footer {
    display: flex;
    padding: 8px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
}
</style>

<div class="chat-float-btn" onclick="toggleChatBox()" title="Trò chuyện trực tuyến">
    <div class="chat-float-inner">
        <i class="fa-regular fa-comment"></i>
    </div>
</div>

<div id="chatbox-app" class="chatbox-window">
    <div class="chatbox-head">
        <div style="display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-headset"></i>
            <div>
                <div id="chat-target-name" style="font-weight:700; font-size:0.95rem;">Hỗ Trợ VNN Travel</div>
                <small style="font-size:0.75rem; opacity:0.85;">Trực tuyến 24/7</small>
            </div>
        </div>
        <i class="fa-solid fa-xmark" style="cursor:pointer; font-size:1.2rem;" onclick="toggleChatBox()"></i>
    </div>

    <div id="chat-messages-container" class="chatbox-messages">
        <?php if (!isLoggedIn()): ?>
            <div style="text-align:center; padding:30px 10px; color:#64748b; font-size:0.9rem;">
                <i class="fa-solid fa-lock" style="font-size:2rem; color:#cbd5e1; margin-bottom:10px; display:block;"></i>
                Vui lòng <a href="<?= BASE_URL ?>auth/login.php" style="color:#f97316; font-weight:bold;">đăng nhập</a> để bắt đầu trò chuyện!
            </div>
        <?php endif; ?>
    </div>

    <?php if (isLoggedIn()): ?>
        <form onsubmit="handleSendChat(event)" class="chatbox-footer">
            <input type="text" id="chat-input-text" placeholder="Nhập câu hỏi..." class="form-control" style="flex:1; border-radius:20px 0 0 20px; padding:8px 14px;" required>
            <button type="submit" class="btn btn-primary btn-sm" style="border-radius:0 20px 20px 0; padding:8px 16px;"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
    <?php endif; ?>
</div>

<script>
let chatWithUserId = 0;

function toggleChatBox() {
    const box = document.getElementById('chatbox-app');
    const isShowing = box.style.display === 'flex';
    box.style.display = isShowing ? 'none' : 'flex';
    <?php if (isLoggedIn()): ?>
        if (!isShowing) refreshChatMessages();
    <?php endif; ?>
}

<?php if (isLoggedIn()): ?>
function refreshChatMessages() {
    fetch('<?= BASE_URL ?>includes/chat-api.php?action=get&with_id=' + chatWithUserId)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                chatWithUserId = res.with_id;
                document.getElementById('chat-target-name').innerText = res.target_name || 'Tư Vấn Viên';
                const container = document.getElementById('chat-messages-container');
                container.innerHTML = '';
                
                res.data.forEach(msg => {
                    const isMe = msg.sender_id == res.current_user_id;
                    const div = document.createElement('div');
                    div.className = 'chat-bubble ' + (isMe ? 'me' : 'other');
                    div.innerText = msg.message;
                    container.appendChild(div);
                });
                container.scrollTop = container.scrollHeight;
            }
        });
}

function handleSendChat(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input-text');
    const msg = input.value.trim();
    if (!msg) return;

    const fd = new FormData();
    fd.append('receiver_id', chatWithUserId);
    fd.append('message', msg);

    fetch('<?= BASE_URL ?>includes/chat-api.php?action=send', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                input.value = '';
                refreshChatMessages();
            }
        });
}

setInterval(() => {
    const box = document.getElementById('chatbox-app');
    if (box && box.style.display === 'flex') refreshChatMessages();
}, 3000);
<?php endif; ?>
</script>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>