<?php
// trekking/chat-api.php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// 1. Quản lý / Nhân viên: Lấy danh sách khách hàng đang nhắn tin tới website
if ($action === 'get_conversations') {
    if (!isStaff() && !isManager() && !isAdmin()) {
        echo json_encode(['status' => 'error', 'message' => 'Không đủ quyền hạn']);
        exit;
    }

    // Lấy tất cả khách hàng có tin nhắn trong hệ thống (sắp xếp người mới nhắn lên đầu)
    $sql = "SELECT u.id, u.fullname, u.email,
                   (SELECT message FROM messages WHERE sender_id = u.id OR receiver_id = u.id ORDER BY id DESC LIMIT 1) as last_message,
                   (SELECT created_at FROM messages WHERE sender_id = u.id OR receiver_id = u.id ORDER BY id DESC LIMIT 1) as last_time
            FROM users u
            WHERE u.role = 'customer'
            ORDER BY last_time DESC, u.id DESC";

    $customers = $pdo->query($sql)->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $customers]);
    exit;
}

// 2. Gửi tin nhắn
if ($action === 'send') {
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $msg = sanitize($_POST['message'] ?? '');

    // Nếu khách hàng gửi mà chưa có người nhận -> tự chỉ định cho Quản lý hoặc Nhân viên đầu tiên
    if ($receiver_id === 0) {
        $st_staff = $pdo->query("SELECT id FROM users WHERE role IN ('manager', 'staff') ORDER BY role DESC, id ASC LIMIT 1");
        $receiver_id = (int)$st_staff->fetchColumn();
    }

    if (!empty($msg) && $receiver_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$current_user_id, $receiver_id, $msg]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nội dung hoặc người nhận không hợp lệ!']);
    }
    exit;
}

// 3. Tải tin nhắn giữa người dùng hiện tại và người đang trò chuyện
if ($action === 'get') {
    $with_id = intval($_GET['with_id'] ?? 0);

    // Nếu khách hàng mở chatbox mà chưa có ID tư vấn viên
    if ($with_id === 0 && isCustomer()) {
        $st_staff = $pdo->query("SELECT id FROM users WHERE role IN ('manager', 'staff') ORDER BY role DESC, id ASC LIMIT 1");
        $with_id = (int)$st_staff->fetchColumn();
    }

    $messages = [];
    $target_name = "Tư Vấn Viên";

    if ($with_id > 0) {
        $u_info = $pdo->prepare("SELECT fullname, role FROM users WHERE id = ?");
        $u_info->execute([$with_id]);
        $target_user = $u_info->fetch();
        if ($target_user) {
            $target_name = $target_user['fullname'] . ($target_user['role'] !== 'customer' ? ' (' . ucfirst($target_user['role']) . ')' : '');
        }

        $stmt = $pdo->prepare("SELECT m.*, u.fullname as sender_name 
                               FROM messages m 
                               JOIN users u ON m.sender_id = u.id 
                               WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                                  OR (m.sender_id = ? AND m.receiver_id = ?) 
                               ORDER BY m.id ASC");
        $stmt->execute([$current_user_id, $with_id, $with_id, $current_user_id]);
        $messages = $stmt->fetchAll();
    }

    echo json_encode([
        'status' => 'success',
        'data' => $messages,
        'current_user_id' => $current_user_id,
        'with_id' => $with_id,
        'target_name' => $target_name
    ]);
    exit;
}