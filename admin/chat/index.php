<?php
// VNN Travel - Chat quan ly
// API va giao dien cung mot file
// Su dung bang users va messages hien co

ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function vnnChatJson($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$chatUserId = (int)($_SESSION['user_id'] ?? 0);
$chatRole = (string)($_SESSION['role'] ?? '');

$allowedRoles = ['admin', 'manager', 'staff'];

$isAuthorized =
    $chatUserId > 0 &&
    in_array($chatRole, $allowedRoles, true);

if (empty($_SESSION['vnn_chat_csrf'])) {
    $_SESSION['vnn_chat_csrf'] = bin2hex(random_bytes(16));
}

$csrf = $_SESSION['vnn_chat_csrf'];

$action = (string)(
    $_GET['chat_action'] ??
    $_POST['chat_action'] ??
    ''
);

// ===================================
// API NOI BO
// ===================================

if ($action !== '') {

    if (!$isAuthorized) {
        vnnChatJson([
            'status' => 'error',
            'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'
        ], 401);
    }

    try {

        if ($action === 'customers') {

            $stmt = $pdo->query("
                SELECT
                    u.id,
                    u.fullname,
                    u.email,
                    (
                        SELECT m.message
                        FROM messages m
                        WHERE m.sender_id = u.id
                           OR m.receiver_id = u.id
                        ORDER BY m.id DESC
                        LIMIT 1
                    ) AS last_message,
                    (
                        SELECT MAX(m.id)
                        FROM messages m
                        WHERE m.sender_id = u.id
                           OR m.receiver_id = u.id
                    ) AS last_message_id
                FROM users u
                WHERE u.role = 'customer'
                  AND EXISTS (
                      SELECT 1
                      FROM messages m
                      WHERE m.sender_id = u.id
                         OR m.receiver_id = u.id
                  )
                ORDER BY last_message_id DESC
            ");

            vnnChatJson([
                'status' => 'success',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'current_user_id' => $chatUserId
            ]);
        }

        if ($action === 'messages') {

            $customerId = (int)($_GET['customer_id'] ?? 0);

            if ($customerId <= 0) {
                vnnChatJson([
                    'status' => 'error',
                    'message' => 'Chưa chọn khách hàng.'
                ], 400);
            }

            $stmt = $pdo->prepare("
                SELECT id, fullname
                FROM users
                WHERE id = ?
                  AND role = 'customer'
                LIMIT 1
            ");

            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                vnnChatJson([
                    'status' => 'error',
                    'message' => 'Không tìm thấy khách hàng.'
                ], 404);
            }

            // Xem lich su tin nhan giua
            // khach hang va bo phan ho tro.
            $stmt = $pdo->prepare("
                SELECT
                    m.id,
                    m.sender_id,
                    m.receiver_id,
                    m.message,
                    m.created_at,
                    u.fullname AS sender_name
                FROM messages m
                JOIN users u ON u.id = m.sender_id
                WHERE
                    (
                        m.sender_id = ?
                        AND EXISTS (
                            SELECT 1
                            FROM users staff
                            WHERE staff.id = m.receiver_id
                              AND staff.role IN ('admin','manager','staff')
                        )
                    )
                    OR
                    (
                        m.receiver_id = ?
                        AND EXISTS (
                            SELECT 1
                            FROM users staff
                            WHERE staff.id = m.sender_id
                              AND staff.role IN ('admin','manager','staff')
                        )
                    )
                ORDER BY m.id ASC
                LIMIT 1000
            ");

            $stmt->execute([$customerId, $customerId]);

            vnnChatJson([
                'status' => 'success',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'customer' => $customer,
                'current_user_id' => $chatUserId
            ]);
        }

        if ($action === 'send') {

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                vnnChatJson([
                    'status' => 'error',
                    'message' => 'Phương thức không hợp lệ.'
                ], 405);
            }

            $sentCsrf = (string)($_POST['csrf'] ?? '');

            if (!hash_equals($csrf, $sentCsrf)) {
                vnnChatJson([
                    'status' => 'error',
                    'message' => 'Phiên gửi tin nhắn không hợp lệ.'
                ], 403);
            }

            $customerId = (int)($_POST['customer_id'] ?? 0);
            $message = trim((string)($_POST['message'] ?? ''));

            if (
                $customerId <= 0 ||
                $message === '' ||
                mb_strlen($message, 'UTF-8') > 5000
            ) {
                vnnChatJson([
                    'status' => 'error',
                    'message' => 'Nội dung tin nhắn không hợp lệ.'
                ], 400);
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = ? AND role = 'customer'
                LIMIT 1
            ");

            $stmt->execute([$customerId]);

            if (!$stmt->fetchColumn()) {
                vnnChatJson([
                    'status' => 'error',
                    'message' => 'Không tìm thấy khách hàng.'
                ], 404);
            }

            $stmt = $pdo->prepare("
                INSERT INTO messages
                    (sender_id, receiver_id, message)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $chatUserId,
                $customerId,
                $message
            ]);

            vnnChatJson([
                'status' => 'success',
                'message' => 'Đã gửi tin nhắn thành công.',
                'id' => (int)$pdo->lastInsertId()
            ]);
        }

        vnnChatJson([
            'status' => 'error',
            'message' => 'Chức năng không hợp lệ.'
        ], 400);

    } catch (Throwable $e) {

        error_log('VNN Admin Chat: ' . $e->getMessage());

        vnnChatJson([
            'status' => 'error',
            'message' => 'Máy chủ chưa xử lý được yêu cầu. Vui lòng thử lại.'
        ], 500);
    }
}

// ===================================
// PHAN QUYEN TRANG QUAN LY
// ===================================

if (!$isAuthorized) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$current_page = 'chat';

$chatApi = $_SERVER['SCRIPT_NAME'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chat trực tuyến - VNN Travel</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet"
href="<?= htmlspecialchars(BASE_URL) ?>assets/css/style.css">

<link rel="stylesheet"
href="<?= htmlspecialchars(BASE_URL) ?>assets/css/admin.css">

<style>
*{box-sizing:border-box}

.vnn-chat{
    display:grid;
    grid-template-columns:320px minmax(0,1fr);
    height:calc(100vh - 140px);
    min-height:520px;
    background:white;
    border-radius:16px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    box-shadow:0 8px 28px #0f172a12;
}

.vnn-left{
    border-right:1px solid #e2e8f0;
    display:flex;
    flex-direction:column;
    min-height:0;
}

.vnn-left-title{
    padding:20px;
    font-size:17px;
    font-weight:800;
    background:#f8fafc;
}

.vnn-search{
    padding:12px;
    border-bottom:1px solid #e2e8f0;
}

.vnn-search input{
    width:100%;
    padding:12px;
    border:1px solid #cbd5e1;
    border-radius:10px;
}

.vnn-customers{
    overflow-y:auto;
    flex:1;
    min-height:0;
}

.vnn-customer{
    width:100%;
    display:flex;
    align-items:center;
    gap:12px;
    padding:15px;
    text-align:left;
    background:white;
    border:0;
    border-bottom:1px solid #f1f5f9;
    cursor:pointer;
}

.vnn-customer.active,
.vnn-customer:hover{
    background:#f0fdf4;
}

.vnn-avatar{
    width:42px;
    height:42px;
    min-width:42px;
    border-radius:50%;
    background:#ea580c;
    color:white;
    display:flex;
    justify-content:center;
    align-items:center;
    font-weight:800;
}

.vnn-customer-info{
    flex:1;
    min-width:0;
}

.vnn-customer-name{
    font-weight:750;
    color:#0f172a;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.vnn-preview{
    color:#64748b;
    font-size:12px;
    margin-top:5px;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.vnn-right{
    display:flex;
    flex-direction:column;
    min-width:0;
    min-height:0;
    background:#f8fafc;
}

.vnn-header{
    display:flex;
    align-items:center;
    gap:13px;
    min-height:85px;
    padding:15px 22px;
    background:white;
    border-bottom:1px solid #e2e8f0;
}

.vnn-header-name{
    font-weight:800;
    color:#0f172a;
}

.vnn-online{
    color:#16a34a;
    font-size:12px;
    margin-top:5px;
}

.vnn-messages{
    flex:1;
    overflow-y:auto;
    min-height:0;
    padding:20px;
    display:flex;
    flex-direction:column;
    gap:10px;
}

.vnn-bubble{
    max-width:75%;
    border-radius:14px;
    padding:11px 15px;
    overflow-wrap:anywhere;
    white-space:pre-wrap;
    line-height:1.5;
    font-size:14px;
}

.vnn-bubble.me{
    align-self:flex-end;
    background:#16a34a;
    color:white;
}

.vnn-bubble.other{
    align-self:flex-start;
    background:white;
    color:#0f172a;
    border:1px solid #e2e8f0;
}

.vnn-bubble small{
    display:block;
    opacity:.7;
    font-size:11px;
    margin-bottom:3px;
}

.vnn-status{
    padding:5px 20px;
    background:white;
    min-height:27px;
    color:#64748b;
    font-size:12px;
}

.vnn-form{
    background:white;
    display:flex;
    gap:12px;
    padding:15px;
    border-top:1px solid #e2e8f0;
}

.vnn-input{
    flex:1;
    min-width:0;
    padding:13px;
    border:1px solid #cbd5e1;
    border-radius:11px;
    font-size:14px;
}

.vnn-send{
    background:#166534;
    color:white;
    border:0;
    border-radius:11px;
    padding:0 24px;
    font-weight:700;
    cursor:pointer;
}

.vnn-send:disabled{
    opacity:.5;
    cursor:not-allowed;
}

.vnn-empty{
    padding:35px 15px;
    text-align:center;
    color:#94a3b8;
}

@media(max-width:760px){
    .vnn-chat{
        grid-template-columns:140px minmax(0,1fr);
        height:calc(100vh - 110px);
    }
    .vnn-customer{padding:10px 5px;gap:5px}
    .vnn-avatar{width:30px;height:30px;min-width:30px}
    .vnn-customer-name{font-size:12px}
    .vnn-form{padding:8px;gap:6px}
    .vnn-send{padding:0 13px}
    .vnn-messages{padding:10px}
    .vnn-bubble{max-width:95%}
}
</style>
</head>

<body class="admin-body">

<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="admin-main" style="padding:24px">

<div class="vnn-chat">

    <section class="vnn-left">

        <div class="vnn-left-title">
            <i class="fa-solid fa-comments"
               style="color:#16a34a"></i>
            Khách hàng nhắn tin
        </div>

        <div class="vnn-search">
            <input type="search"
                   id="customerSearch"
                   placeholder="Tìm khách hàng...">
        </div>

        <div class="vnn-customers" id="customerList">
            <div class="vnn-empty">Đang tải khách hàng...</div>
        </div>

    </section>

    <section class="vnn-right">

        <div class="vnn-header">
            <div class="vnn-avatar" id="activeAvatar"
                 style="background:#0284c7">K</div>
            <div>
                <div class="vnn-header-name" id="activeName">
                    Chọn khách hàng để trò chuyện
                </div>
                <div class="vnn-online">
                    <i class="fa-solid fa-circle"></i>
                    Bộ phận hỗ trợ VNN Travel
                </div>
            </div>
        </div>

        <div class="vnn-messages" id="messageList">
            <div class="vnn-empty">
                Chọn khách hàng ở bên trái để xem tin nhắn.
            </div>
        </div>

        <div class="vnn-status" id="chatStatus"></div>

        <form class="vnn-form" id="chatForm">
            <input
                id="replyInput"
                class="vnn-input"
                placeholder="Nhập câu trả lời cho khách hàng..."
                maxlength="5000"
                autocomplete="off"
                required
                disabled
            >

            <button class="vnn-send"
                    id="sendButton"
                    type="submit"
                    disabled>
                <i class="fa-solid fa-paper-plane"></i>
                Gửi
            </button>
        </form>

    </section>

</div>

</main>

<script>
'use strict';

const CHAT_API = <?= json_encode(
    $chatApi,
    JSON_UNESCAPED_SLASHES
) ?>;

const CHAT_CSRF = <?= json_encode($csrf) ?>;

const CURRENT_ID = <?= (int)$chatUserId ?>;

const customersEl = document.getElementById('customerList');
const messagesEl = document.getElementById('messageList');
const searchEl = document.getElementById('customerSearch');
const inputEl = document.getElementById('replyInput');
const buttonEl = document.getElementById('sendButton');
const formEl = document.getElementById('chatForm');
const statusEl = document.getElementById('chatStatus');

let activeId = 0;
let activeName = '';
let customers = [];
let previousMessageSignature = '';
let busy = false;
let loading = false;

function setStatus(text) {
    statusEl.textContent = text || '';
}

async function api(action, params = {}, body = null) {

    const url = new URL(CHAT_API, window.location.href);
    url.searchParams.set('chat_action', action);

    for (const [key, value] of Object.entries(params)) {
        url.searchParams.set(key, value);
    }

    const options = {
        method: body ? 'POST' : 'GET',
        credentials: 'same-origin',
        cache: 'no-store'
    };

    if (body) {
        options.body = new URLSearchParams(body);
    }

    const response = await fetch(url, options);
    const raw = await response.text();

    let result;

    try {
        result = JSON.parse(raw);
    } catch (e) {
        throw new Error(
            'Máy chủ không trả về dữ liệu JSON hợp lệ.'
        );
    }

    if (!response.ok || result.status !== 'success') {
        throw new Error(result.message || 'Yêu cầu thất bại.');
    }

    return result;
}

function renderCustomers() {

    const keyword = searchEl.value.toLowerCase().trim();

    const filtered = customers.filter(c =>
        (String(c.fullname || '') + ' ' +
         String(c.email || '')).toLowerCase().includes(keyword)
    );

    customersEl.replaceChildren();

    if (!filtered.length) {
        customersEl.innerHTML =
            '<div class="vnn-empty">Không có khách hàng phù hợp.</div>';
        return;
    }

    for (const customer of filtered) {

        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'vnn-customer' +
            (Number(customer.id) === activeId ? ' active' : '');

        const avatar = document.createElement('div');
        avatar.className = 'vnn-avatar';
        avatar.textContent =
            (customer.fullname || 'K').trim().charAt(0).toUpperCase();

        const info = document.createElement('div');
        info.className = 'vnn-customer-info';

        const name = document.createElement('div');
        name.className = 'vnn-customer-name';
        name.textContent = customer.fullname || 'Khách hàng';

        const preview = document.createElement('div');
        preview.className = 'vnn-preview';
        preview.textContent =
            customer.last_message || 'Chọn để trả lời...';

        info.append(name, preview);
        row.append(avatar, info);

        row.addEventListener('click', () => {
            selectCustomer(customer.id, customer.fullname);
        });

        customersEl.appendChild(row);
    }
}

async function loadCustomers() {

    try {
        const result = await api('customers');
        customers = result.data || [];
        renderCustomers();
        setStatus('');
    } catch (e) {
        setStatus('Không thể tải danh sách: ' + e.message);

        if (!customers.length) {
            customersEl.innerHTML =
                '<div class="vnn-empty">Không thể tải danh sách khách hàng.</div>';
        }
    }
}

function selectCustomer(id, name) {

    activeId = Number(id);
    activeName = String(name || 'Khách hàng');
    previousMessageSignature = '';

    document.getElementById('activeName').textContent = activeName;
    document.getElementById('activeAvatar').textContent =
        activeName.charAt(0).toUpperCase();

    inputEl.disabled = false;
    buttonEl.disabled = false;

    renderCustomers();

    messagesEl.innerHTML =
        '<div class="vnn-empty">Đang tải tin nhắn...</div>';

    loadMessages();
    inputEl.focus();
}

async function loadMessages() {

    if (!activeId || loading) return;

    loading = true;
    const requestedId = activeId;

    try {
        const result = await api('messages', {
            customer_id: requestedId
        });

        if (requestedId !== activeId) return;

        const messages = result.data || [];

        const signature = messages
            .map(m => m.id + ':' + m.message)
            .join('|');

        if (signature === previousMessageSignature) return;

        previousMessageSignature = signature;

        const nearBottom =
            messagesEl.scrollHeight -
            messagesEl.scrollTop -
            messagesEl.clientHeight < 100;

        messagesEl.replaceChildren();

        if (!messages.length) {
            messagesEl.innerHTML =
                '<div class="vnn-empty">Chưa có tin nhắn.</div>';
        }

        for (const message of messages) {

            const bubble = document.createElement('div');

            const mine = Number(message.sender_id) !== requestedId;

            bubble.className =
                'vnn-bubble ' + (mine ? 'me' : 'other');

            const sender = document.createElement('small');

            sender.textContent = mine
                ? (message.sender_name || 'Nhân viên hỗ trợ')
                : (message.sender_name || activeName);

            const content = document.createElement('div');
            content.textContent = message.message || '';

            bubble.append(sender, content);
            messagesEl.appendChild(bubble);
        }

        if (nearBottom || !messages.length) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        setStatus('');

    } catch (e) {
        setStatus('Không thể tải tin nhắn: ' + e.message);
    } finally {
        loading = false;
    }
}

formEl.addEventListener('submit', async function(event) {
    event.preventDefault();

    if (!activeId || busy) return;

    const message = inputEl.value.trim();

    if (!message) return;

    busy = true;
    buttonEl.disabled = true;
    setStatus('Đang gửi tin nhắn...');

    try {
        await api('send', {}, {
            chat_action: 'send',
            csrf: CHAT_CSRF,
            customer_id: activeId,
            message: message
        });

        inputEl.value = '';
        previousMessageSignature = '';

        setStatus('Đã gửi tin nhắn thành công.');

        await loadMessages();
        await loadCustomers();

    } catch (e) {
        // Khong xoa noi dung neu gui that bai.
        setStatus('Không thể gửi tin nhắn: ' + e.message);
    } finally {
        busy = false;
        buttonEl.disabled = !activeId;
        inputEl.focus();
    }
});

searchEl.addEventListener('input', renderCustomers);

// Tu dong kiem tra tin nhan moi moi 3 giay.
setInterval(() => {
    if (document.visibilityState !== 'visible') return;

    loadCustomers();

    if (activeId) {
        loadMessages();
    }
}, 3000);

loadCustomers();
</script>

</body>
</html>
