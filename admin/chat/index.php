
<?php
// VNN Travel - Admin Customer Chat
// Luu lich su tin nhan trong MySQL

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Kiem tra quyen truy cap
if (function_exists('requireStaff')) {
    requireStaff();
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, ['admin', 'manager', 'staff'], true)) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

$current_page = 'chat';

$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
$chatApiUrl = $baseUrl . 'chat-api.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Chat Trực Tuyến - VNN Travel</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet"
          href="<?= htmlspecialchars($baseUrl) ?>assets/css/style.css">

    <link rel="stylesheet"
          href="<?= htmlspecialchars($baseUrl) ?>assets/css/admin.css">

    <style>
        * {
            box-sizing: border-box;
        }

        .vnn-chat-layout {
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr);
            width: 100%;
            height: calc(100vh - 140px);
            min-height: 500px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08);
        }

        .vnn-chat-customers {
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e2e8f0;
            background: #ffffff;
        }

        .vnn-customers-title {
            padding: 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
        }

        .vnn-customers-title i {
            color: #16a34a;
            margin-right: 8px;
        }

        .vnn-customer-search {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .vnn-customer-search input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            outline: none;
            font-size: 14px;
        }

        .vnn-customer-search input:focus {
            border-color: #16a34a;
        }

        .vnn-customers-list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
        }

        .vnn-customer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 15px;
            border: 0;
            border-bottom: 1px solid #f1f5f9;
            background: #ffffff;
            text-align: left;
            cursor: pointer;
            transition: background 0.2s;
        }

        .vnn-customer-item:hover {
            background: #f0fdf4;
        }

        .vnn-customer-item.active {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
        }

        .vnn-avatar {
            flex-shrink: 0;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #ea580c;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
        }

        .vnn-customer-details {
            flex: 1;
            min-width: 0;
        }

        .vnn-customer-name {
            margin: 0;
            font-size: 15px;
            font-weight: 750;
            color: #0f172a;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .vnn-customer-preview {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .vnn-conversation {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            background: #f8fafc;
        }

        .vnn-conversation-header {
            min-height: 85px;
            padding: 15px 22px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
        }

        .vnn-conversation-header .vnn-avatar {
            background: #0284c7;
        }

        .vnn-conversation-name {
            font-weight: 800;
            color: #0f172a;
            font-size: 17px;
        }

        .vnn-online-label {
            margin-top: 5px;
            font-size: 13px;
            font-weight: 600;
            color: #16a34a;
        }

        .vnn-messages {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .vnn-message {
            max-width: 72%;
            padding: 11px 16px;
            border-radius: 15px;
            line-height: 1.5;
            font-size: 14px;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .vnn-message.mine {
            align-self: flex-end;
            background: #16a34a;
            color: #ffffff;
            border-bottom-right-radius: 4px;
        }

        .vnn-message.theirs {
            align-self: flex-start;
            background: #ffffff;
            color: #0f172a;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 4px;
        }

        .vnn-message-sender {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
            opacity: 0.8;
        }

        .vnn-chat-status {
            min-height: 22px;
            padding: 2px 22px;
            color: #64748b;
            background: #ffffff;
            font-size: 12px;
        }

        .vnn-reply-form {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 22px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
        }

        .vnn-reply-input {
            flex: 1;
            min-width: 0;
            padding: 13px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 14px;
            outline: none;
        }

        .vnn-reply-input:focus {
            border-color: #f97316;
        }

        .vnn-send-button {
            padding: 13px 23px;
            border: 0;
            border-radius: 12px;
            background: #166534;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .vnn-send-button:hover {
            background: #15803d;
        }

        .vnn-send-button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .vnn-empty-message {
            text-align: center;
            padding: 45px 20px;
            color: #94a3b8;
            margin: auto;
        }

        .vnn-empty-message i {
            display: block;
            font-size: 38px;
            margin-bottom: 14px;
        }

        @media (max-width: 800px) {
            .vnn-chat-layout {
                grid-template-columns: 145px minmax(0, 1fr);
                height: calc(100vh - 110px);
            }

            .vnn-customers-title {
                padding: 12px;
                font-size: 13px;
            }

            .vnn-customer-item {
                padding: 9px 6px;
                gap: 6px;
            }

            .vnn-avatar {
                width: 32px;
                height: 32px;
                font-size: 13px;
            }

            .vnn-customer-name {
                font-size: 12px;
            }

            .vnn-customer-preview {
                font-size: 11px;
            }

            .vnn-messages {
                padding: 10px;
            }

            .vnn-message {
                max-width: 92%;
            }

            .vnn-reply-form {
                padding: 8px;
                gap: 6px;
            }

            .vnn-send-button {
                padding: 12px;
            }
        }
    </style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main" style="padding: 25px 30px;">

    <div class="vnn-chat-layout">

        <!-- Customer list -->
        <section class="vnn-chat-customers">

            <div class="vnn-customers-title">
                <i class="fa-solid fa-comments"></i>
                Khách Hàng Nhắn Tin
            </div>

            <div class="vnn-customer-search">
                <input
                    type="search"
                    id="vnn-customer-search"
                    placeholder="Tìm khách hàng..."
                    autocomplete="off"
                >
            </div>

            <div id="vnn-customers-list" class="vnn-customers-list">
                <div class="vnn-empty-message">
                    Đang tải khách hàng...
                </div>
            </div>

        </section>

        <!-- Conversation -->
        <section class="vnn-conversation">

            <header class="vnn-conversation-header">

                <div id="vnn-active-avatar" class="vnn-avatar">
                    K
                </div>

                <div>
                    <div id="vnn-active-name" class="vnn-conversation-name">
                        Chọn khách hàng để trò chuyện
                    </div>

                    <div class="vnn-online-label">
                        <i class="fa-solid fa-circle" style="font-size: 9px;"></i>
                        Bộ phận hỗ trợ VNN Travel
                    </div>
                </div>

            </header>

            <div id="vnn-message-list" class="vnn-messages">
                <div class="vnn-empty-message">
                    <i class="fa-regular fa-comment-dots"></i>
                    Chọn khách hàng bên trái để xem lịch sử tin nhắn.
                </div>
            </div>

            <div id="vnn-chat-status" class="vnn-chat-status" aria-live="polite"></div>

            <form id="vnn-reply-form" class="vnn-reply-form">

                <input
                    id="vnn-reply-input"
                    class="vnn-reply-input"
                    type="text"
                    maxlength="5000"
                    placeholder="Nhập câu trả lời tư vấn cho khách hàng..."
                    autocomplete="off"
                    required
                    disabled
                >

                <button
                    id="vnn-send-button"
                    class="vnn-send-button"
                    type="submit"
                    disabled
                >
                    <i class="fa-solid fa-paper-plane"></i>
                    Gửi
                </button>

            </form>

        </section>

    </div>

</main>

<script>
'use strict';

const VNN_CHAT_API = <?= json_encode(
    $chatApiUrl,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) ?>;

const customerListEl =
    document.getElementById('vnn-customers-list');

const messageListEl =
    document.getElementById('vnn-message-list');

const activeNameEl =
    document.getElementById('vnn-active-name');

const activeAvatarEl =
    document.getElementById('vnn-active-avatar');

const replyFormEl =
    document.getElementById('vnn-reply-form');

const replyInputEl =
    document.getElementById('vnn-reply-input');

const sendButtonEl =
    document.getElementById('vnn-send-button');

const statusEl =
    document.getElementById('vnn-chat-status');

const customerSearchEl =
    document.getElementById('vnn-customer-search');

let activeCustomerId = 0;
let activeCustomerName = '';
let currentUserId = 0;

let allCustomers = [];

let customersLoading = false;
let messagesLoading = false;
let messageSending = false;

let customerSignature = null;
let messageSignature = null;

let conversationVersion = 0;

function setChatStatus(text) {
    statusEl.textContent = text || '';
}

function escapeText(value) {
    return String(value ?? '');
}

async function chatRequest(action, options = {}) {
    const separator = VNN_CHAT_API.includes('?') ? '&' : '?';

    const url = VNN_CHAT_API + separator + 'action=' + action;

    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        ...options
    });

    const raw = await response.text();

    let result;

    try {
        result = JSON.parse(raw);
    } catch (error) {
        throw new Error('API không trả về JSON hợp lệ.');
    }

    if (!response.ok || result.status !== 'success') {
        throw new Error(
            result.message || 'Không thể thực hiện yêu cầu.'
        );
    }

    return result;
}

function getCustomerSignature(customers) {
    return customers.map(customer => {
        return [
            customer.id,
            customer.fullname,
            customer.last_message_id,
            customer.last_message
        ].join(':');
    }).join('|');
}

function renderCustomers() {
    const keyword =
        customerSearchEl.value.trim().toLowerCase();

    customerListEl.replaceChildren();

    const filtered = allCustomers.filter(customer => {
        const name = String(customer.fullname || '').toLowerCase();
        const email = String(customer.email || '').toLowerCase();

        return name.includes(keyword) || email.includes(keyword);
    });

    if (filtered.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'vnn-empty-message';
        empty.textContent = 'Không tìm thấy khách hàng.';
        customerListEl.appendChild(empty);
        return;
    }

    filtered.forEach(customer => {
        const id = Number(customer.id);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'vnn-customer-item';

        if (id === activeCustomerId) {
            button.classList.add('active');
        }

        const avatar = document.createElement('div');
        avatar.className = 'vnn-avatar';

        avatar.textContent =
            String(customer.fullname || 'K')
                .charAt(0).toUpperCase();

        const details = document.createElement('div');
        details.className = 'vnn-customer-details';

        const title = document.createElement('h5');
        title.className = 'vnn-customer-name';
        title.textContent = customer.fullname || 'Khách hàng';

        const preview = document.createElement('p');
        preview.className = 'vnn-customer-preview';

        preview.textContent =
            customer.last_message || 'Bấm để trả lời...';

        details.appendChild(title);
        details.appendChild(preview);

        button.appendChild(avatar);
        button.appendChild(details);

        button.addEventListener('click', () => {
            selectCustomer(id, customer.fullname);
        });

        customerListEl.appendChild(button);
    });
}

async function loadCustomers() {
    if (customersLoading) return;

    customersLoading = true;

    try {
        const result = await chatRequest('get_conversations');

        const customers = Array.isArray(result.data)
            ? result.data
            : [];

        const signature = getCustomerSignature(customers);

        allCustomers = customers;

        if (signature !== customerSignature) {
            customerSignature = signature;
            renderCustomers();
        }

        if (!activeCustomerId && customers.length > 0) {
            const first = customers[0];

            selectCustomer(first.id, first.fullname);
        }

    } catch (error) {
        console.error('Load customers:', error);
        setChatStatus('Không thể tải danh sách khách hàng: ' + error.message);
    } finally {
        customersLoading = false;
    }
}

function selectCustomer(id, name) {
    const nextId = Number(id);

    if (!nextId) return;

    if (nextId !== activeCustomerId) {
        conversationVersion++;
        messageSignature = null;

        messageListEl.replaceChildren();

        const loading = document.createElement('div');
        loading.className = 'vnn-empty-message';
        loading.textContent = 'Đang tải lịch sử tin nhắn...';
        messageListEl.appendChild(loading);
    }

    activeCustomerId = nextId;
    activeCustomerName = name || 'Khách hàng';

    activeNameEl.textContent = activeCustomerName;

    activeAvatarEl.textContent =
        activeCustomerName.charAt(0).toUpperCase();

    replyInputEl.disabled = false;
    sendButtonEl.disabled = messageSending;

    renderCustomers();

    setChatStatus('');

    loadMessages();
}

function getMessageSignature(messages) {
    return messages.map(message => {
        return [
            message.id,
            message.sender_id,
            message.message
        ].join(':');
    }).join('|');
}

function renderMessages(messages) {
    const isFirstLoad = messageSignature === null;

    const nearBottom =
        messageListEl.scrollHeight -
        messageListEl.scrollTop -
        messageListEl.clientHeight < 120;

    messageListEl.replaceChildren();

    if (messages.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'vnn-empty-message';
        empty.textContent =
            'Chưa có tin nhắn. Bạn có thể gửi lời chào khách hàng.';
        messageListEl.appendChild(empty);
        return;
    }

    messages.forEach(message => {
        const bubble = document.createElement('div');

        const senderId = Number(message.sender_id);

        const isCustomerMessage =
            senderId === activeCustomerId;

        bubble.className =
            'vnn-message ' +
            (isCustomerMessage ? 'theirs' : 'mine');

        if (!isCustomerMessage && message.sender_name) {
            const senderLabel = document.createElement('div');
            senderLabel.className = 'vnn-message-sender';
            senderLabel.textContent = message.sender_name;
            bubble.appendChild(senderLabel);
        }

        const text = document.createElement('div');
        text.textContent = escapeText(message.message);

        bubble.appendChild(text);
        messageListEl.appendChild(bubble);
    });

    if (nearBottom || isFirstLoad) {
        messageListEl.scrollTop = messageListEl.scrollHeight;
    }
}

async function loadMessages() {
    if (!activeCustomerId || messagesLoading) return;

    messagesLoading = true;

    const requestedCustomer = activeCustomerId;
    const requestedVersion = conversationVersion;

    try {
        const result = await chatRequest(
            'get&with_id=' + encodeURIComponent(requestedCustomer)
        );

        if (
            requestedCustomer !== activeCustomerId ||
            requestedVersion !== conversationVersion
        ) {
            return;
        }

        currentUserId = Number(result.current_user_id) || 0;

        const messages = Array.isArray(result.data)
            ? result.data
            : [];

        const signature = getMessageSignature(messages);

        if (signature !== messageSignature) {
            renderMessages(messages);
            messageSignature = signature;
        }

        setChatStatus('');

    } catch (error) {
        console.error('Load messages:', error);

        if (requestedCustomer === activeCustomerId) {
            setChatStatus('Lỗi tải tin nhắn: ' + error.message);
        }

    } finally {
        messagesLoading = false;
    }
}

async function sendMessage(event) {
    event.preventDefault();

    if (messageSending || !activeCustomerId) return;

    const message = replyInputEl.value.trim();

    if (!message) return;

    const receiverId = activeCustomerId;

    messageSending = true;
    sendButtonEl.disabled = true;

    setChatStatus('Đang gửi tin nhắn...');

    try {
        const data = new FormData();

        data.append('receiver_id', String(receiverId));
        data.append('message', message);

        await chatRequest('send', {
            method: 'POST',
            body: data
        });

        replyInputEl.value = '';

        messageSignature = null;

        await loadMessages();
        await loadCustomers();

        setChatStatus('');

    } catch (error) {
        console.error('Send message:', error);

        setChatStatus('Gửi thất bại: ' + error.message);

    } finally {
        messageSending = false;
        sendButtonEl.disabled = activeCustomerId === 0;
        replyInputEl.focus();
    }
}

replyFormEl.addEventListener('submit', sendMessage);

customerSearchEl.addEventListener('input', renderCustomers);

// Load initial customers.
loadCustomers();

// Refresh every 5 seconds while visible.
setInterval(() => {
    if (document.hidden) return;

    loadMessages();
    loadCustomers();

}, 5000);

// Reload when user returns to tab.
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        loadMessages();
        loadCustomers();
    }
});
</script>

</body>
</html>
