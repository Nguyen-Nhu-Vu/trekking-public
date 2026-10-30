<?php
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>assets/css/style.css">
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>assets/css/admin.css">
<style>
.vc-layout{display:grid;grid-template-columns:320px minmax(0,1fr);height:100%;min-height:0;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0}
.vc-left{display:flex;flex-direction:column;min-width:0;min-height:0;overflow:hidden;border-right:1px solid #e2e8f0}
.vc-title{padding:19px;font-weight:800;background:#f8fafc;color:#0f172a}
.vc-title i{color:#16a34a}
.vc-search{padding:12px;border-bottom:1px solid #e2e8f0}
.vc-search input,.vc-input{width:100%;min-width:0;padding:13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none}
.vc-list{flex:1 1 0;min-height:0;max-height:100%;overflow-y:auto;overscroll-behavior:contain;scrollbar-width:thin;scrollbar-color:#94a3b8 #f8fafc}
.vc-person{width:100%;border:0;border-bottom:1px solid #f1f5f9;background:#fff;padding:14px;display:flex;gap:10px;align-items:center;text-align:left;cursor:pointer}
 .vc-person:hover,.vc-person.active{background:#f0fdf4}
.vc-person.unanswered{background:#eff6ff;border-left:4px solid #2563eb;padding-left:10px}
.vc-person.unanswered .vc-name{font-weight:900;color:#0f172a}
.vc-person.unanswered .vc-preview{font-weight:700;color:#1d4ed8}
.vc-unanswered-dot{flex:none;width:10px;height:10px;border-radius:50%;background:#2563eb;margin-left:auto}
.vc-person.unanswered.active{background:#dbeafe}
.vc-error{padding:14px;color:#b91c1c;text-align:center;font-size:13px}
.vc-avatar{width:42px;height:42px;min-width:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#ea580c;color:white;font-weight:800}
.vc-detail{min-width:0;flex:1}
.vc-name{color:#0f172a;font-weight:750;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vc-preview{font-size:12px;color:#64748b;margin-top:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vc-right{display:flex;flex-direction:column;min-height:0;min-width:0;background:#f8fafc}
.vc-head{padding:16px 20px;background:white;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;min-height:82px}
.vc-online{color:#16a34a;font-size:12px;margin-top:5px}
.vc-messages{flex:1 1 0;min-height:0;overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:20px}
.vc-bubble{max-width:75%;padding:11px 15px;border-radius:14px;font-size:14px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}
.vc-bubble.mine{align-self:flex-end;background:#16a34a;color:white}
.vc-bubble.theirs{align-self:flex-start;background:white;color:#0f172a;border:1px solid #e2e8f0}
.vc-bubble.pending{opacity:.7}
.vc-bubble.failed{opacity:1;background:#fee2e2;color:#991b1b}
.vc-state{font-size:11px;margin-top:5px}
.vc-retry{margin-top:7px;border:1px solid #dc2626;background:white;color:#b91c1c;border-radius:7px;padding:5px 9px;cursor:pointer}
.vc-empty{text-align:center;color:#94a3b8;padding:30px 10px}
.vc-form{display:flex !important;flex:0 0 auto;min-height:76px;align-items:center;gap:11px;padding:14px;background:white;border-top:1px solid #e2e8f0;position:relative;z-index:2}
.vc-input{flex:1}
.vc-send{min-height:46px;padding:0 23px;background:#166534;color:white;border:0;border-radius:10px;font-weight:800;cursor:pointer}
.vc-send:disabled{opacity:.5}
@media(max-width:760px){
.vc-layout{grid-template-columns:145px minmax(0,1fr)}
.vc-person{padding:9px 5px}
.vc-avatar{width:30px;height:30px;min-width:30px}
.vc-name{font-size:12px}
.vc-bubble{max-width:95%}
.vc-form{padding:8px}
.vc-send{padding:0 12px}
.vc-messages{padding:10px}
}
/* Chat fills the available viewport; only conversation panes scroll. */
body.admin-body{height:100vh;height:100dvh;min-height:0;overflow:hidden}
.admin-main.vc-main{height:100vh;height:100dvh;min-height:0;overflow:hidden;display:flex;flex-direction:column;padding:24px !important}
.admin-main.vc-main .vc-layout{flex:1 1 0;min-height:0;max-height:100%}
@media(max-width:760px){.admin-main.vc-main{padding:10px !important}.vc-form{min-height:64px}}
</style>
</head>
<body class="admin-body">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main vc-main">
<div class="vc-layout">

<section class="vc-left">
    <div class="vc-title">
        <i class="fa-solid fa-comments"></i>
        Khách Hàng Nhắn Tin
    </div>

    <div class="vc-search">
        <input id="vcSearch" type="search" placeholder="Tìm khách hàng...">
    </div>

    <div class="vc-list" id="vcCustomers"></div>
</section>

<section class="vc-right">
    <div class="vc-head">
        <div class="vc-avatar" id="vcAvatar" style="background:#0284c7">K</div>
        <div>
            <div class="vc-name" id="vcActiveName">
                Chọn khách hàng để trò chuyện
            </div>
            <div class="vc-online">
                <i class="fa-solid fa-circle"></i>
                Bộ phận hỗ trợ VNN Travel
            </div>
        </div>
    </div>

    <div class="vc-messages" id="vcMessages">
        <div class="vc-empty">Chọn khách hàng để xem tin nhắn.</div>
    </div>

    <!-- Khong con dong trang thai loi API -->

    <form id="vcForm" class="vc-form">
        <input id="vcInput" class="vc-input"
               placeholder="Nhập câu trả lời cho khách hàng..."
               maxlength="5000" required disabled>
        <button id="vcSend" class="vc-send" disabled>
            <i class="fa-solid fa-paper-plane"></i> Gửi
        </button>
    </form>
</section>

</div>
</main>

<script>
'use strict';

const API = <?= json_encode($apiUrl, JSON_UNESCAPED_SLASHES) ?>;

const $ = id => document.getElementById(id);
let customers = [];
let selected = 0;
let selectedName = '';
let messages = [];
let pending = [];
let seq = 0;
let version = 0;
let loadingCustomers = false;
let loadingMessages = false;
let messageRequestToken = 0;
let loadingForCustomer = 0;

async function request(action, options = {}) {
    const url = API + (API.includes('?') ? '&' : '?') +
        'action=' + action;

    const response = await fetch(url, {
        credentials:'same-origin',
        cache:'no-store',
        ...options
    });

    const raw = await response.text();
    let result;

    try {
        result = JSON.parse(raw);
    } catch (_) {
        throw new Error('Phản hồi máy chủ không hợp lệ.');
    }

    if (!response.ok || result.status !== 'success') {
        throw new Error(result.message || 'Yêu cầu thất bại.');
    }

    return result;
}

function showCustomers() {
    const q = $('vcSearch').value.trim().toLowerCase();
    const list = $('vcCustomers');
    list.replaceChildren();

    const filtered = customers.filter(c =>
        (String(c.fullname || '') + ' ' +
         String(c.email || '')).toLowerCase().includes(q)
    );

    if (!filtered.length) {
        const empty = document.createElement('div');
        empty.className = 'vc-empty';
        empty.textContent = 'Chưa có khách hàng để hiển thị.';
        list.appendChild(empty);
        return;
    }

    for (const c of filtered) {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'vc-person' +
            (Number(c.id) === selected ? ' active' : '') +
            (Number(c.needs_reply) === 1 ? ' unanswered' : '');

        const avatar = document.createElement('div');
        avatar.className = 'vc-avatar';
        avatar.textContent = (c.fullname || 'K').charAt(0);

        const detail = document.createElement('div');
        detail.className = 'vc-detail';

        const name = document.createElement('div');
        name.className = 'vc-name';
        name.textContent = c.fullname || 'Khách hàng';

        const preview = document.createElement('div');
        preview.className = 'vc-preview';
        preview.textContent = c.last_message || 'Bấm để trò chuyện';

        detail.append(name, preview);
        row.append(avatar, detail);
        if (Number(c.needs_reply) === 1) {
            const dot = document.createElement('span');
            dot.className = 'vc-unanswered-dot';
            dot.title = 'Khách hàng đang chờ phản hồi';
            dot.setAttribute('aria-label', 'Đang chờ phản hồi');
            row.appendChild(dot);
        }
        row.onclick = () => selectCustomer(c.id, c.fullname);
        list.appendChild(row);
    }
}

async function loadCustomers() {
    if (loadingCustomers) return;
    loadingCustomers = true;

    try {
        const res = await request('get_conversations');
        customers = Array.isArray(res.data) ? res.data : [];
        showCustomers();

        if (!selected && customers.length) {
            selectCustomer(customers[0].id, customers[0].fullname);
        }
    } catch (e) {
        // Khong in loi lap vao giao dien.
        console.warn('Chat customers:', e.message);
    } finally {
        loadingCustomers = false;
    }
}

function selectCustomer(id, name) {
    const next = Number(id);
    if (!next) return;

    if (selected !== next) {
        version++;
        messageRequestToken++;
        messages = [];
    }

    selected = next;
    selectedName = String(name || 'Khách hàng');

    $('vcActiveName').textContent = selectedName;
    $('vcAvatar').textContent = selectedName.charAt(0);
    $('vcInput').disabled = false;
    $('vcSend').disabled = false;

    showCustomers();
    $('vcMessages').innerHTML = '<div class="vc-empty">Đang tải tin nhắn...</div>';
    loadMessages();
}

function bubble(m, temporary = false) {
    const mine = temporary || Number(m.sender_id) !== selected;
    const node = document.createElement('div');

    node.className = 'vc-bubble ' +
        (mine ? 'mine' : 'theirs');

    if (temporary) node.classList.add(m.state);

    const content = document.createElement('div');
    content.textContent = m.message || '';
    node.appendChild(content);

    if (temporary) {
        const status = document.createElement('div');
        status.className = 'vc-state';

        status.textContent = m.state === 'failed'
            ? 'Gửi thất bại'
            : m.state === 'pending'
                ? 'Đang gửi...'
                : 'Đã gửi';

        node.appendChild(status);

        if (m.state === 'failed') {
            const retry = document.createElement('button');
            retry.className = 'vc-retry';
            retry.type = 'button';
            retry.textContent = 'Thử lại';
            retry.onclick = () => retrySend(m.localId);
            node.appendChild(retry);
        }
    }

    return node;
}

function showMessages(toBottom = false) {
    const area = $('vcMessages');

    const nearBottom = area.scrollHeight -
        area.scrollTop - area.clientHeight < 120;

    area.replaceChildren();

    const local = pending.filter(m => m.customerId === selected);

    if (!messages.length && !local.length) {
        const empty = document.createElement('div');
        empty.className = 'vc-empty';
        empty.textContent = 'Chưa có tin nhắn.';
        area.appendChild(empty);
        return;
    }

    const serverIds = new Set(
        messages.map(m => Number(m.id))
    );

    for (const msg of messages) {
        area.appendChild(bubble(msg));
    }

    for (const msg of local) {
        if (msg.serverId && serverIds.has(msg.serverId)) continue;
        area.appendChild(bubble(msg, true));
    }

    if (nearBottom || toBottom) {
        area.scrollTop = area.scrollHeight;
    }
}

async function loadMessages() {
    if (!selected) return;
    // Only one in-flight request per selected conversation. A new selection
    // must not be blocked by a previous customer's request.
    const id = selected;
    if (loadingMessages && loadingForCustomer === id) return;
    const v = version;
    const token = ++messageRequestToken;
    loadingMessages = true;
    loadingForCustomer = id;

    try {
        const res = await request(
            'get&with_id=' + encodeURIComponent(id)
        );

        if (id !== selected || v !== version || token !== messageRequestToken) return;

        messages = Array.isArray(res.data) ? res.data : [];

        const ids = new Set(
            messages.map(m => Number(m.id))
        );

        pending = pending.filter(m =>
            !(m.serverId && ids.has(m.serverId))
        );

        showMessages();
    } catch (e) {
        console.warn('Chat messages:', e.message);
        if (id === selected && token === messageRequestToken) {
            const area = $('vcMessages');
            area.replaceChildren();
            const error = document.createElement('div');
            error.className = 'vc-error';
            error.textContent = 'Không tải được hội thoại: ' + e.message;
            area.appendChild(error);
        }
    } finally {
        if (token === messageRequestToken) {
            loadingMessages = false;
            loadingForCustomer = 0;
        }
    }
}

async function sendPending(item) {
    if (item.state === 'pending' && item.inFlight) return;

    item.state = 'pending';
    item.inFlight = true;

    if (selected === item.customerId) showMessages(true);

    const data = new FormData();
    data.append('receiver_id', String(item.customerId));
    data.append('message', item.message);

    try {
        const res = await request('send', {
            method:'POST',
            body:data
        });

        item.state = 'sent';
        item.serverId = Number(res.id || res.message_id) || null;

        if (selected === item.customerId) showMessages(true);

        await loadMessages();
        await loadCustomers();
    } catch (e) {
        item.state = 'failed';
        console.warn('Chat send:', e.message);

        if (selected === item.customerId) showMessages(true);
    } finally {
        item.inFlight = false;
    }
}

function retrySend(localId) {
    const item = pending.find(m => m.localId === localId);
    if (!item || item.inFlight || item.state !== 'failed') return;

    // Neu ket noi mat sau khi server da luu,
    // viec gui lai co the tao tin nhan trung.
    sendPending(item);
}

$('vcForm').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!selected) return;

    const text = $('vcInput').value.trim();
    if (!text) return;

    const item = {
        localId: ++seq,
        customerId: selected,
        message: text,
        state: 'pending',
        serverId: null,
        inFlight: false
    };

    pending.push(item);
    $('vcInput').value = '';

    // Hien bong chat truoc khi goi API.
    showMessages(true);
    $('vcInput').focus();

    sendPending(item);
});

$('vcSearch').addEventListener('input', showCustomers);

setInterval(() => {
    if (document.hidden) return;
    loadCustomers();
    if (selected) loadMessages();
}, 3000);

loadCustomers();
</script>
</body>
</html>
