<?php
// trekking/admin/chat/index.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireStaff(); // Cả Nhân viên và Quản lý đều có quyền trực chat

$current_page = 'chat';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tư Vấn Khách Hàng Trực Tuyến - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .chat-app-box {
            display: grid;
            grid-template-columns: 320px 1fr;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            height: calc(100vh - 140px);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .chat-sidebar-users {
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
        }
        .chat-users-header {
            padding: 16px;
            font-weight: 700;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .chat-users-list {
            flex: 1;
            overflow-y: auto;
        }
        .user-chat-row {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.2s;
        }
        .user-chat-row:hover, .user-chat-row.active {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
        }
        .user-circle-avatar {
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
        .user-chat-info h5 {
            margin: 0 0 4px 0;
            font-size: 0.95rem;
            color: #0f172a;
        }
        .user-chat-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 190px;
        }
        .chat-conversation-area {
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }
        .chat-top-header {
            padding: 14px 20px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-messages-container {
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
        .chat-bottom-input-bar {
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
        <div class="chat-app-box">
            <!-- CỘT TRÁI: DANH SÁCH KHÁCH HÀNG ĐANG NHẮN TIN CHO WEB -->
            <div class="chat-sidebar-users">
                <div class="chat-users-header">
                    <i class="fa-solid fa-comments" style="color:#16a34a;"></i> Khách Hàng Nhắn Tin
                </div>
                <div class="chat-users-list" id="users-list-box">
                    <div style="text-align:center; padding:30px; color:#94a3b8;">Đang tải danh sách...</div>
                </div>
            </div>

            <!-- CỘT PHẢI: KHUNG ĐỐI THOẠI TRẢ LỜI KHÁCH HÀNG -->
            <div class="chat-conversation-area">
                <div class="chat-top-header">
                    <div class="user-circle-avatar" id="active-user-avatar" style="background:#0284c7;">K</div>
                    <div>
                        <div id="active-user-name" style="font-weight:700; color:#0f172a;">Chọn khách hàng để trả lời</div>
                        <small style="color:#16a34a; font-weight:600;"><i class="fa-solid fa-circle" style="font-size:0.6rem;"></i> Đang kết nối trực tiếp</small>
                    </div>
                </div>

                <div class="chat-messages-container" id="chat-messages-box">
                    <div style="text-align:center; padding:60px; color:#94a3b8;">
                        <i class="fa-regular fa-comment-dots" style="font-size:3rem; margin-bottom:12px; display:block;"></i>
                        Nhấp vào tên một khách hàng từ danh sách bên trái để xem nội dung và trả lời thắc mắc.
                    </div>
                </div>

                <form class="chat-bottom-input-bar" onsubmit="handleSendReply(event)">
                    <input type="text" id="staff-reply-input" class="form-control" placeholder="Nhập câu trả lời tư vấn cho khách hàng..." required disabled>
                    <button type="submit" id="staff-reply-submit" class="btn btn-primary" style="padding:10px 24px;" disabled>
                        <i class="fa-solid fa-paper-plane"></i> Gửi
                    </button>
                </form>
            </div>
        </div>
    </main>

<script>
let activeCustomerId = 0;

function loadCustomers() {
    fetch('<?= BASE_URL ?>chat-api.php?action=get_conversations')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const box = document.getElementById('users-list-box');
                box.innerHTML = '';

                if (res.data.length === 0) {
                    box.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;">Chưa có khách hàng nào nhắn tin.</div>';
                    return;
                }

                res.data.forEach(c => {
                    const row = document.createElement('div');
                    row.className = 'user-chat-row' + (c.id == activeCustomerId ? ' active' : '');
                    row.onclick = () => selectCustomer(c.id, c.fullname);
                    row.innerHTML = `
                        <div class="user-circle-avatar">${c.fullname.charAt(0).toUpperCase()}</div>
                        <div class="user-chat-info">
                            <h5>${c.fullname}</h5>
                            <p>${c.last_message ? c.last_message : 'Bấm để trả lời...'}</p>
                        </div>
                    `;
                    box.appendChild(row);
                });

                if (activeCustomerId === 0 && res.data.length > 0) {
                    selectCustomer(res.data[0].id, res.data[0].fullname);
                }
            }
        });
}

function selectCustomer(id, name) {
    activeCustomerId = id;
    document.getElementById('active-user-name').innerText = name;
    document.getElementById('active-user-avatar').innerText = name.charAt(0).toUpperCase();
    document.getElementById('staff-reply-input').disabled = false;
    document.getElementById('staff-reply-submit').disabled = false;
    document.getElementById('staff-reply-input').focus();

    document.querySelectorAll('.user-chat-row').forEach(el => el.classList.remove('active'));

    loadMessages();
}

function loadMessages() {
    if (activeCustomerId === 0) return;

    fetch('<?= BASE_URL ?>chat-api.php?action=get&with_id=' + activeCustomerId)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const container = document.getElementById('chat-messages-box');
                container.innerHTML = '';

                if (res.data.length === 0) {
                    container.innerHTML = '<div style="text-align:center; color:#94a3b8; margin-top:40px;">Chưa có tin nhắn nào. Hãy gửi lời chào hỗ trợ khách hàng!</div>';
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

function handleSendReply(e) {
    e.preventDefault();
    const input = document.getElementById('staff-reply-input');
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
                loadCustomers();
            }
        });
}

loadCustomers();
setInterval(() => {
    if (activeCustomerId > 0) {
        loadMessages();
    }
}, 3000);
</script>
</body>
</html>