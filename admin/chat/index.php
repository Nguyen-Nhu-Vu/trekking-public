<?php
// VNN TRAVEL - ADMIN CHAT
// Hien thi tin nhan ngay khi bam Gui / Enter
// Dong bo Aiven MySQL bang API hien tai

ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

requireStaff();

$current_page = 'chat';
$page_title = 'Chat Trực Tuyến - VNN Travel';

$base = rtrim(BASE_URL, '/') . '/';
$apiUrl = $base . 'chat-api.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title><?= htmlspecialchars($page_title) ?></title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet"
href="<?= htmlspecialchars($base) ?>assets/css/style.css">

<link rel="stylesheet"
href="<?= htmlspecialchars($base) ?>assets/css/admin.css">

<style>
.vnn-chat {
    display:grid;
    grid-template-columns:320px minmax(0,1fr);
    height:calc(100vh - 140px);
    min-height:510px;
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    overflow:hidden;
    box-shadow:0 8px 26px #0f172a12;
}

.vnn-chat * {
    box-sizing:border-box;
}

.vnn-chat-left {
    display:flex;
    flex-direction:column;
    min-height:0;
    min-width:0;
    border-right:1px solid #e2e8f0;
    background:white;
}

.vnn-chat-heading {
    padding:20px;
    background:#f8fafc;
    color:#0f172a;
    font-weight:800;
    font-size:17px;
}

.vnn-chat-heading i {
    color:#16a34a;
}

.vnn-chat-search {
    padding:12px;
    border-bottom:1px solid #e2e8f0;
}

.vnn-chat-search input {
    width:100%;
    border:1px solid #cbd5e1;
    border-radius:10px;
    padding:12px;
    outline:none;
}

.vnn-chat-customers {
    overflow-y:auto;
    flex:1;
    min-height:0;
}

.vnn-chat-customer {
    width:100%;
    display:flex;
    align-items:center;
    gap:11px;
    padding:14px;
    border:0;
    border-bottom:1px solid #f1f5f9;
    cursor:pointer;
    background:white;
    text-align:left;
}

.vnn-chat-customer:hover,
.vnn-chat-customer.active {
    background:#f0fdf4;
}

.vnn-chat-customer.active {
    border-left:4px solid #16a34a;
}

.vnn-chat-avatar {
    flex-shrink:0;
    width:43px;
    height:43px;
    background:#ea580c;
    color:white;
    font-weight:800;
    border-radius:50%;
    display:flex;
    justify-content:center;
    align-items:center;
}

.vnn-chat-customer-detail {
    min-width:0;
    flex:1;
}

.vnn-chat-customer-name {
    font-weight:750;
    color:#0f172a;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.vnn-chat-preview {
    font-size:12px;
    color:#64748b;
    margin-top:6px;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.vnn-chat-right {
    display:flex;
    flex-direction:column;
    min-width:0;
    min-height:0;
    background:#f8fafc;
}

.vnn-chat-top {
    display:flex;
    align-items:center;
    gap:13px;
    background:white;
    padding:15px 20px;
    min-height:85px;
    border-bottom:1px solid #e2e8f0;
}

.vnn-chat-top-name {
    font-weight:800;
    color:#0f172a;
}

.vnn-chat-online {
    font-size:12px;
    color:#16a34a;
    margin-top:5px;
}

.vnn-chat-messages {
    flex:1;
    min-height:0;
    display:flex;
    flex-direction:column;
    overflow-y:auto;
    gap:10px;
    padding:20px;
}

.vnn-chat-bubble {
    position:relative;
    max-width:75%;
    border-radius:15px;
    padding:11px 15px;
    line-height:1.5;
    font-size:14px;
    overflow-wrap:anywhere;
    white-space:pre-wrap;
}

.vnn-chat-bubble.mine {
    align-self:flex-end;
    background:#16a34a;
    color:#fff;
}

.vnn-chat-bubble.theirs {
    align-self:flex-start;
    background:white;
    color:#0f172a;
    border:1px solid #e2e8f0;
}

.vnn-chat-bubble.pending {
    opacity:.72;
}

.vnn-chat-bubble.failed {
    background:#fee2e2;
    color:#991b1b;
    border:1px solid #fecaca;
    opacity:1;
}

.vnn-chat-sender {
    font-size:11px;
    opacity:.75;
    margin-bottom:5px;
}

.vnn-chat-state {
    font-size:11px;
    margin-top:5px;
    opacity:.85;
}

.vnn-chat-retry {
    display:inline-block;
    margin-top:8px;
    background:white;
    color:#b91c1c;
    border:1px solid #ef4444;
    border-radius:7px;
    padding:5px 10px;
    cursor:pointer;
    font-size:12px;
}

.vnn-chat-status {
    min-height:25px;
    background:white;
    color:#64748b;
    font-size:12px;
    padding:5px 17px;
}

.vnn-chat-form {
    display:flex;
    gap:11px;
    padding:14px;
    background:white;
    border-top:1px solid #e2e8f0;
}

.vnn-chat-input {
    flex:1;
    min-width:0;
    padding:13px 15px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    outline:none;
    font-size:14px;
}

.vnn-chat-input:focus {
    border-color:#16a34a;
}

.vnn-chat-send {
    padding:0 22px;
    border:0;
    border-radius:10px;
    background:#166534;
    color:white;
    font-weight:800;
    cursor:pointer;
}

.vnn-chat-send:disabled {
    opacity:.55;
    cursor:not-allowed;
}

.vnn-chat-empty {
    color:#94a3b8;
    text-align:center;
    padding:40px 15px;
}

@media(max-width:760px) {
    .vnn-chat {
        grid-template-columns:145px minmax(0,1fr);
        height:calc(100vh - 110px);
    }

    .vnn-chat-customer {
        padding:10px 5px;
    }

    .vnn-chat-avatar {
        width:30px;
        height:30px;
    }

    .vnn-chat-customer-name {
        font-size:12px;
    }

    .vnn-chat-preview {
        font-size:10px;
    }

    .vnn-chat-messages {
        padding:10px;
    }

    .vnn-chat-bubble {
        max-width:94%;
    }

    .vnn-chat-form {
        padding:8px;
        gap:6px;
    }

    .vnn-chat-send {
        padding:0 12px;
    }
}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main" style="padding:24px">

<div class="vnn-chat">

    <section class="vnn-chat-left">

        <div class="vnn-chat-heading">
            <i class="fa-solid fa-comments"></i>
            Khách Hàng Nhắn Tin
        </div>

        <div class="vnn-chat-search">
            <input
                id="customerSearch"
                placeholder="Tìm khách hàng..."
                type="search"
            >
        </div>

        <div class="vnn-chat-customers" id="customerList">
            <div class="vnn-chat-empty">
                Đang tải khách hàng...
            </div>
        </div>

    </section>

    <section class="vnn-chat-right">

        <div class="vnn-chat-top">

            <div class="vnn-chat-avatar"
                 id="activeAvatar"
                 style="background:#0284c7">
                K
            </div>

            <div>
                <div class="vnn-chat-top-name" id="activeName">
                    Chọn khách hàng để trò chuyện
                </div>

                <div class="vnn-chat-online">
                    <i class="fa-solid fa-circle"></i>
                    Bộ phận hỗ trợ VNN Travel
                </div>
            </div>

        </div>

        <div class="vnn-chat-messages" id="messageList">

            <div class="vnn-chat-empty">
                Chọn khách hàng để xem lịch sử tin nhắn.
            </div>

        </div>

        <div class="vnn-chat-status"
             id="chatStatus"
             role="status">
        </div>

        <form class="vnn-chat-form" id="chatForm">

            <input
                id="chatInput"
                class="vnn-chat-input"
                type="text"
                placeholder="Nhập câu trả lời cho khách hàng..."
                maxlength="5000"
                autocomplete="off"
                required
                disabled
            >

            <button
                class="vnn-chat-send"
                type="submit"
                id="sendButton"
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

// ========================================
// CAU HINH API
// ========================================

const CHAT_API = <?= json_encode(
    $apiUrl,
    JSON_UNESCAPED_SLASHES
) ?>;

const customerList = document.getElementById('customerList');
const messageList = document.getElementById('messageList');
const customerSearch = document.getElementById('customerSearch');
const chatInput = document.getElementById('chatInput');
const chatForm = document.getElementById('chatForm');
const sendButton = document.getElementById('sendButton');
const chatStatus = document.getElementById('chatStatus');

let customers = [];
let activeCustomerId = 0;
let activeCustomerName = '';

let storedMessages = [];
let pendingMessages = [];

let loadVersion = 0;
let loadingMessages = false;
let loadingCustomers = false;
let lastCustomerSignature = '';

let localCounter = 0;

function statusText(text) {
    chatStatus.textContent = text || '';
}

function scrollBottom() {
    messageList.scrollTop = messageList.scrollHeight;
}

// ========================================
// GOI API
// ========================================

async function request(action, options = {}) {

    const separator = CHAT_API.includes('?') ? '&' : '?';

    const url = CHAT_API + separator + 'action=' + action;

    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        ...options
    });

    const raw = await response.text();

    let data;

    try {
        data = JSON.parse(raw);
    } catch (error) {
        throw new Error('API không trả về JSON hợp lệ.');
    }

    if (!response.ok || data.status !== 'success') {
        throw new Error(
            data.message || 'Không thể kết nối máy chủ.'
        );
    }

    return data;
}

// ========================================
// HIEN THI KHACH HANG
// ========================================

function renderCustomers() {

    const keyword = customerSearch.value
        .trim()
        .toLowerCase();

    customerList.replaceChildren();

    const filtered = customers.filter(c => {
        const searchText = (
            String(c.fullname || '') + ' ' +
            String(c.email || '')
        ).toLowerCase();

        return searchText.includes(keyword);
    });

    if (!filtered.length) {
        customerList.innerHTML =
            '<div class="vnn-chat-empty">Không có khách hàng phù hợp.</div>';
        return;
    }

    filtered.forEach(customer => {

        const id = Number(customer.id);

        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'vnn-chat-customer';

        if (id === activeCustomerId) {
            row.classList.add('active');
        }

        const avatar = document.createElement('div');
        avatar.className = 'vnn-chat-avatar';
        avatar.textContent =
            String(customer.fullname || 'K')
                .charAt(0).toUpperCase();

        const detail = document.createElement('div');
        detail.className = 'vnn-chat-customer-detail';

        const name = document.createElement('div');
        name.className = 'vnn-chat-customer-name';
        name.textContent = customer.fullname || 'Khách hàng';

        const preview = document.createElement('div');
        preview.className = 'vnn-chat-preview';
        preview.textContent =
            customer.last_message || 'Bấm để trả lời...';

        detail.append(name, preview);
        row.append(avatar, detail);

        row.addEventListener('click', () => {
            selectCustomer(id, customer.fullname);
        });

        customerList.appendChild(row);
    });
}

async function loadCustomers() {

    if (loadingCustomers) return;

    loadingCustomers = true;

    try {
        const result = await request('get_conversations');
        const data = Array.isArray(result.data) ? result.data : [];

        const signature = data
            .map(c => [
                c.id,
                c.last_message_id,
                c.last_message
            ].join(':'))
            .join('|');

        customers = data;

        if (signature !== lastCustomerSignature) {
            lastCustomerSignature = signature;
            renderCustomers();
        }

        if (!activeCustomerId && data.length) {
            selectCustomer(data[0].id, data[0].fullname);
        }

    } catch (error) {
        statusText(
            'Không thể tải khách hàng: ' + error.message
        );
    } finally {
        loadingCustomers = false;
    }
}

// ========================================
// CHON KHACH HANG
// ========================================

function selectCustomer(id, name) {

    const nextId = Number(id);

    if (!nextId) return;

    if (nextId !== activeCustomerId) {
        loadVersion++;
        storedMessages = [];
    }

    activeCustomerId = nextId;
    activeCustomerName = name || 'Khách hàng';

    document.getElementById('activeName').textContent =
        activeCustomerName;

    document.getElementById('activeAvatar').textContent =
        activeCustomerName.charAt(0).toUpperCase();

    chatInput.disabled = false;
    sendButton.disabled = false;

    renderCustomers();
    renderMessages(true);
    loadMessages();

    chatInput.focus();
}

// ========================================
// TAO BONG CHAT
// ========================================

function createBubble(message, temporary = false) {

    const mine = temporary
        ? true
        : Number(message.sender_id) !== activeCustomerId;

    const bubble = document.createElement('div');

    bubble.className =
        'vnn-chat-bubble ' + (mine ? 'mine' : 'theirs');

    if (temporary) {
        bubble.classList.add(message.state);
    }

    const sender = document.createElement('div');
    sender.className = 'vnn-chat-sender';

    sender.textContent = mine
        ? 'Bộ phận hỗ trợ'
        : activeCustomerName;

    const content = document.createElement('div');
    content.textContent = message.message || '';

    bubble.append(sender, content);

    if (temporary) {

        const state = document.createElement('div');
        state.className = 'vnn-chat-state';

        if (message.state === 'pending') {
            state.textContent = 'Đang gửi...';
        } else if (message.state === 'failed') {
            state.textContent = 'Gửi thất bại';
        } else {
            state.textContent = 'Đã gửi';
        }

        bubble.appendChild(state);

        if (message.state === 'failed') {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'vnn-chat-retry';
            retry.textContent = 'Thử lại';

            retry.addEventListener('click', () => {
                retryMessage(message.localId);
            });

            bubble.appendChild(retry);
        }
    }

    return bubble;
}

// ========================================
// HIEN THI TIN NHAN
// ========================================

function renderMessages(forceBottom = false) {

    if (!activeCustomerId) return;

    const nearBottom =
        messageList.scrollHeight -
        messageList.scrollTop -
        messageList.clientHeight < 120;

    messageList.replaceChildren();

    const ownPending = pendingMessages.filter(
        m => m.customerId === activeCustomerId
    );

    if (!storedMessages.length && !ownPending.length) {
        const empty = document.createElement('div');
        empty.className = 'vnn-chat-empty';
        empty.textContent = 'Chưa có tin nhắn.';
        messageList.appendChild(empty);
        return;
    }

    const displayedServerIds = new Set();

    storedMessages.forEach(message => {

        displayedServerIds.add(Number(message.id));

        messageList.appendChild(
            createBubble(message, false)
        );
    });

    ownPending.forEach(message => {

        if (
            message.serverId &&
            displayedServerIds.has(Number(message.serverId))
        ) {
            return;
        }

        messageList.appendChild(
            createBubble(message, true)
        );
    });

    if (nearBottom || forceBottom) {
        scrollBottom();
    }
}

// ========================================
// TAI LICH SU TU MYSQL
// ========================================

async function loadMessages() {

    if (!activeCustomerId || loadingMessages) return;

    const customerId = activeCustomerId;
    const version = loadVersion;

    loadingMessages = true;

    try {

        const result = await request(
            'get&with_id=' + encodeURIComponent(customerId)
        );

        if (
            version !== loadVersion ||
            customerId !== activeCustomerId
        ) {
            return;
        }

        storedMessages = Array.isArray(result.data)
            ? result.data
            : [];

        // Tin da luu va co trong database
        // se khong can bong tam nua.
        const serverIds = new Set(
            storedMessages.map(m => Number(m.id))
        );

        pendingMessages = pendingMessages.filter(m => {
            if (
                m.serverId &&
                serverIds.has(Number(m.serverId))
            ) {
                return false;
            }

            return true;
        });

        renderMessages();

    } catch (error) {
        statusText(
            'Không thể cập nhật lịch sử: ' + error.message
        );
    } finally {
        loadingMessages = false;
    }
}

// ========================================
// GUI TIN NHAN NGAY LAP TUC
// ========================================

async function submitPending(item) {

    item.state = 'pending';

    if (item.customerId === activeCustomerId) {
        renderMessages(true);
    }

    const data = new FormData();

    data.append('receiver_id', String(item.customerId));
    data.append('message', item.message);

    try {

        const result = await request('send', {
            method: 'POST',
            body: data
        });

        item.serverId = Number(result.id) || null;
        item.state = 'sent';

        // Hien thi trang thai da gui ngay sau API.
        if (item.customerId === activeCustomerId) {
            renderMessages(true);
        }

        // Khong can doi fetch lich su de hien bong chat.
        // Lay lich su o nen de doi chieu ID.
        await loadMessages();
        await loadCustomers();

    } catch (error) {

        item.state = 'failed';

        if (item.customerId === activeCustomerId) {
            renderMessages(true);
            statusText('Tin nhắn chưa gửi được. Bấm "Thử lại".');
        }

        console.error('Chat send:', error);
    }
}

function sendMessage(event) {

    event.preventDefault();

    if (!activeCustomerId) return;

    const message = chatInput.value.trim();

    if (!message || message.length > 5000) return;

    // 1. Tao tin nhan tam trong bo nho trinh duyet.
    const item = {
        localId: ++localCounter,
        customerId: activeCustomerId,
        message: message,
        state: 'pending',
        serverId: null
    };

    pendingMessages.push(item);

    // 2. Xoa o nhap va hien bong chat NGAY.
    chatInput.value = '';
    renderMessages(true);
    chatInput.focus();

    // 3. Sau do moi gui API.
    // Khong can cho MySQL moi hien tin nhan.
    submitPending(item);
}

function retryMessage(localId) {

    const item = pendingMessages.find(
        m => m.localId === localId
    );

    if (!item || item.state !== 'failed') return;

    submitPending(item);
}

// ========================================
// PHIM ENTER VA NUT GUI
// ========================================

chatForm.addEventListener('submit', sendMessage);

customerSearch.addEventListener('input', renderCustomers);

// ========================================
// DONG BO TIN NHAN DINH KY
// ========================================

// Kiem tra 1.5 giay mot lan.
// Co the tang len 3000 neu Vercel ton request.

setInterval(() => {

    if (document.visibilityState !== 'visible') return;

    loadCustomers();

    if (activeCustomerId) {
        loadMessages();
    }

}, 1500);

// ========================================
// KHOI DONG
// ========================================

loadCustomers();
</script>

</body>
</html>
