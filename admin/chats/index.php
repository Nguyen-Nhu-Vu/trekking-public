<?php
// trekking/admin/chats/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireStaff(); // Cả Nhân viên và Quản lý đều vào được

$current_page = 'chats';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hỗ Trợ Khách Hàng Trực Tuyến - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .chat-admin-container {
            display: flex;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            height: calc(100vh - 140px);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .chat-customer-list {
            width: 320px;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
        }
        .chat-list-header {
            padding: 16px;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #1e293b;
        }
        .customer-items-box {
            flex: 1;
            overflow-y: auto;
        }
        .customer-chat-item {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.2s;
        }
        .customer-chat-item:hover, .customer-chat-item.active {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
        }
        .customer-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #ea580c;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .customer-info h5 {
            margin: 0 0 4px 0;
            font-size: 0.95rem;
            color: #0f172a;
        }
        .customer-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 190px;
        }
        .chat-main-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }
        .chat-header-bar {
            padding: 14px 20px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-messages-area {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-bubble-item {
            max-width: 70%;
            padding: 10px 16px;
            border-radius: 14px;
            font-size: 0.92rem;
            line-height: 1.45;
        }
        .chat-bubble-item.me {
            align-self: flex-end;
            background: #16a34a;
            color: white;
            border-bottom-right-radius: 2px;
        }
        .chat-bubble-item.other {
            align-self: flex-start;
            background: white;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 2px;
        }
        .chat-send-bar {
            padding: 12px 20px;
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main" style="padding: 25px 30px;">
        <div class="chat-admin-container">
            <!-- CỘT TRÁI: DANH SÁCH KHÁCH HÀNG -->
            <div class="chat-customer-list">
                <div class="chat-list-header">
                    <i class="fa-solid fa-comments" style="color:#16a34a;"></i> Tin Nhắn Khách Hàng
                </div>
                <div class="customer-items-box" id="customer-list-box">
                    <div style="text-align:center; padding:30px; color:#94a3b8;">Đang tải hội thoại...</div>
                </div>
            </div>

            <!-- CỘT PHẢI: KHUNG CHAT VỚI KHÁCH HÀNG ĐANG CHỌN -->
            <div class="chat-main-area">
                <div class="chat-header-bar">
                    <div class="customer-avatar" id="current-chat-avatar" style="background:#0284c7;">K</div>
                    <div>
                        <div id="current-chat-name" style="font-weight:700; color:#0f172a;">Chọn khách hàng để trả lời</div>
                        <small style="color:#16a34a; font-weight:600;"><i class="fa-solid fa-circle" style="font-size:0.6rem;"></i> Đang kết nối trực tiếp</small>
                    </div>
                </div>

                <div class="chat-messages-area" id="admin-chat-messages">
                    <div style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fa-regular fa-comment-dots" style="font-size:3rem; margin-bottom:12px; display:block;"></i>
                        Vui lòng chọn một khách hàng từ danh sách bên trái để xem nội dung và trả lời thắc mắc.
                    </div>
                </div>

                <form class="chat-send-bar" onsubmit="handleAdminSendMessage(event)">
                    <input type="text" id="admin-chat-input" class="form-control" placeholder="Nhập câu trả lời cho khách hàng..." required disabled>
                    <button type="submit" id="admin-chat-submit" class="btn btn-primary" style="padding:10px 24px;" disabled>
                        <i class="fa-solid fa-paper-plane"></i> Gửi
                    </button>
                </form>
            </div>
        </div>
    </main>

<script>
let activeCustomerId = 0;

// Tải danh sách khách hàng
function loadConversations() {
    fetch('<?= BASE_URL ?>chat-api.php?action=get_conversations')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const box = document.getElementById('customer-list-box');
                box.innerHTML = '';
                
                if (res.data.length === 0) {
                    box.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;">Chưa có khách hàng nào.</div>';
                    return;
                }

                res.data.forEach(c => {
                    const item = document.createElement('div');
                    item.className = 'customer-chat-item' + (c.id == activeCustomerId ? ' active' : '');
                    item.onclick = () => selectCustomer(c.id, c.fullname);
                    item.innerHTML = `
                        <div class="customer-avatar">${c.fullname.charAt(0).toUpperCase()}</div>
                        <div class="customer-info">
                            <h5>${c.fullname}</h5>
                            <p>${c.last_message ? c.last_message : 'Bấm để bắt đầu chat...'}</p>
                        </div>
                    `;
                    box.appendChild(item);
                });

                // Nếu chưa chọn ai, tự động chọn khách hàng đầu tiên
                if (activeCustomerId === 0 && res.data.length > 0) {
                    selectCustomer(res.data[0].id, res.data[0].fullname);
                }
            }
        });
}

function selectCustomer(id, name) {
    activeCustomerId = id;
    document.getElementById('current-chat-name').innerText = name;
    document.getElementById('current-chat-avatar').innerText = name.charAt(0).toUpperCase();
    document.getElementById('admin-chat-input').disabled = false;
    document.getElementById('admin-chat-submit').disabled = false;
    document.getElementById('admin-chat-input').focus();

    // Đánh dấu active trong danh sách
    document.querySelectorAll('.customer-chat-item').forEach(el => el.classList.remove('active'));

    loadMessages();
}

function loadMessages() {
    if (activeCustomerId === 0) return;

    fetch('<?= BASE_URL ?>chat-api.php?action=get&with_id=' + activeCustomerId)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const container = document.getElementById('admin-chat-messages');
                container.innerHTML = '';

                if (res.data.length === 0) {
                    container.innerHTML = '<div style="text-align:center; color:#94a3b8; margin-top:40px;">Chưa có tin nhắn nào với khách hàng này. Hãy gửi lời chào hỗ trợ!</div>';
                }

                res.data.forEach(m => {
                    const isMe = m.sender_id == res.current_user_id;
                    const bubble = document.createElement('div');
                    bubble.className = 'chat-bubble-item ' + (isMe ? 'me' : 'other');
                    bubble.innerText = m.message;
                    container.appendChild(bubble);
                });

                container.scrollTop = container.scrollHeight;
            }
        });
}

function handleAdminSendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('admin-chat-input');
    const msg = input.value.trim();
    if (!msg || activeCustomerId === 0) return;

    const fd = new FormData();
    fd.append('receiver_id', activeCustomerId);
    fd.append('message', msg);

    fetch('<?= BASE_URL ?>chat-api.php?action=send', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                input.value = '';
                loadMessages();
            }
        });
}

// Khởi chạy khi vào trang
loadConversations();

// Tự động tải lại tin nhắn mỗi 3 giây
setInterval(() => {
    if (activeCustomerId > 0) {
        loadMessages();
    }
}, 3000);
</script>
</body>
</html>