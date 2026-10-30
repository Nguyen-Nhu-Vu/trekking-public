<?php
// VNN Travel - Chat API
// Shared conversation history stored in Aiven MySQL

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function chatReply($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    chatReply([
        'status' => 'error',
        'message' => 'Vui lòng đăng nhập.'
    ], 401);
}

$userId = (int) $_SESSION['user_id'];
$role = getRole();
$isCustomer = $role === 'customer';
$isStaff = in_array($role, ['admin', 'manager', 'staff'], true);

if ($userId <= 0 || (!$isCustomer && !$isStaff)) {
    chatReply([
        'status' => 'error',
        'message' => 'Không có quyền sử dụng chat.'
    ], 403);
}

// Release PHP session lock before network/database work.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$action = $_GET['action'] ?? '';

try {
    // List customers with their latest support messages.
    if ($action === 'get_conversations') {
        if (!$isStaff) {
            chatReply([
                'status' => 'error',
                'message' => 'Không đủ quyền.'
            ], 403);
        }

        $sql = "
            SELECT
                u.id,
                u.fullname,
                u.email,
                (
                    SELECT m.message
                    FROM messages m
                    JOIN users s ON s.id = m.sender_id
                    JOIN users r ON r.id = m.receiver_id
                    WHERE
                        (m.sender_id = u.id
                          AND r.role IN ('manager','staff','admin'))
                        OR
                        (m.receiver_id = u.id
                          AND s.role IN ('manager','staff','admin'))
                    ORDER BY m.id DESC
                    LIMIT 1
                ) AS last_message,
                (
                    SELECT MAX(m.id)
                    FROM messages m
                    JOIN users s ON s.id = m.sender_id
                    JOIN users r ON r.id = m.receiver_id
                    WHERE
                        (m.sender_id = u.id
                          AND r.role IN ('manager','staff','admin'))
                        OR
                        (m.receiver_id = u.id
                          AND s.role IN ('manager','staff','admin'))
                ) AS last_message_id,
                (
                    SELECT CASE WHEN s.role = 'customer' THEN 1 ELSE 0 END
                    FROM messages m
                    JOIN users s ON s.id = m.sender_id
                    JOIN users r ON r.id = m.receiver_id
                    WHERE (m.sender_id = u.id AND r.role IN ('manager','staff','admin'))
                       OR (m.receiver_id = u.id AND s.role IN ('manager','staff','admin'))
                    ORDER BY m.id DESC LIMIT 1
                ) AS needs_reply
            FROM users u
            WHERE u.role = 'customer'
            ORDER BY
                (last_message_id IS NULL) ASC,
                last_message_id DESC,
                u.id DESC
        ";

        chatReply([
            'status' => 'success',
            'data' => $pdo->query($sql)->fetchAll()
        ]);
    }

    // Load all historical support messages for one customer.
    if ($action === 'get') {
        $customerId = $isCustomer
            ? $userId
            : (int) ($_GET['with_id'] ?? 0);

        if ($customerId <= 0) {
            chatReply([
                'status' => 'success',
                'data' => [],
                'current_user_id' => $userId,
                'with_id' => 0,
                'target_name' => 'Khách hàng'
            ]);
        }

        if ($isStaff) {
            $stmt = $pdo->prepare("
                SELECT fullname
                FROM users
                WHERE id = ? AND role = 'customer'
                LIMIT 1
            ");
            $stmt->execute([$customerId]);
            $name = $stmt->fetchColumn();

            if ($name === false) {
                chatReply([
                    'status' => 'error',
                    'message' => 'Không tìm thấy khách hàng.'
                ], 404);
            }
        } else {
            $name = 'Bộ phận hỗ trợ VNN Travel';
        }

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
            JOIN users s ON s.id = m.sender_id
            JOIN users r ON r.id = m.receiver_id
            WHERE
                (
                    m.sender_id = ?
                    AND r.role IN ('admin','manager','staff')
                )
                OR
                (
                    m.receiver_id = ?
                    AND s.role IN ('admin','manager','staff')
                )
            ORDER BY m.id ASC
        ");
        $stmt->execute([$customerId, $customerId]);
        $messages = $stmt->fetchAll();

        $lastStaffId = 0;

        if ($isCustomer) {
            foreach ($messages as $message) {
                $lastStaffId =
                    (int) $message['sender_id'] === $userId
                    ? (int) $message['receiver_id']
                    : (int) $message['sender_id'];
            }
        }

        chatReply([
            'status' => 'success',
            'data' => $messages,
            'current_user_id' => $userId,
            'with_id' => $isCustomer ? $lastStaffId : $customerId,
            'target_name' => $name
        ]);
    }

    // Send a support message.
    if ($action === 'send') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            chatReply([
                'status' => 'error',
                'message' => 'Phương thức không hợp lệ.'
            ], 405);
        }

        $message = trim((string) ($_POST['message'] ?? ''));
        $receiverId = (int) ($_POST['receiver_id'] ?? 0);

        if ($message === '' || mb_strlen($message, 'UTF-8') > 5000) {
            chatReply([
                'status' => 'error',
                'message' => 'Tin nhắn phải có từ 1 đến 5000 ký tự.'
            ], 400);
        }

        if ($isCustomer) {
            // Prefer the most recent existing support contact.
            $stmt = $pdo->prepare("
                SELECT
                    CASE
                        WHEN m.sender_id = ?
                        THEN m.receiver_id
                        ELSE m.sender_id
                    END AS staff_id
                FROM messages m
                JOIN users s ON s.id = m.sender_id
                JOIN users r ON r.id = m.receiver_id
                WHERE
                    (
                        (m.sender_id = ?
                         AND r.role IN ('admin','manager','staff'))
                        OR
                        (m.receiver_id = ?
                         AND s.role IN ('admin','manager','staff'))
                    )
                ORDER BY m.id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $userId, $userId]);
            $previousStaff = (int) $stmt->fetchColumn();

            if ($previousStaff > 0) {
                $receiverId = $previousStaff;
            } else {
                $stmt = $pdo->query("
                    SELECT id
                    FROM users
                    WHERE role IN ('manager','staff','admin')
                    ORDER BY
                        CASE role
                            WHEN 'manager' THEN 0
                            WHEN 'staff' THEN 1
                            ELSE 2
                        END,
                        id ASC
                    LIMIT 1
                ");
                $receiverId = (int) $stmt->fetchColumn();
            }

            if ($receiverId > 0) {
                $stmt = $pdo->prepare("
                    SELECT id FROM users
                    WHERE id = ?
                    AND role IN ('admin','manager','staff')
                    LIMIT 1
                ");
                $stmt->execute([$receiverId]);

                if (!$stmt->fetchColumn()) {
                    $receiverId = 0;
                }
            }
        } else {
            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = ? AND role = 'customer'
                LIMIT 1
            ");
            $stmt->execute([$receiverId]);

            if (!$stmt->fetchColumn()) {
                chatReply([
                    'status' => 'error',
                    'message' => 'Không tìm thấy khách hàng.'
                ], 400);
            }
        }

        if ($receiverId <= 0 || $receiverId === $userId) {
            chatReply([
                'status' => 'error',
                'message' => 'Không tìm thấy người nhận.'
            ], 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO messages
                (sender_id, receiver_id, message)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([$userId, $receiverId, $message]);

        chatReply([
            'status' => 'success',
            'message' => 'Đã gửi tin nhắn.',
            'id' => (int) $pdo->lastInsertId(),
            'receiver_id' => $receiverId
        ]);
    }

    chatReply([
        'status' => 'error',
        'message' => 'Action không hợp lệ.'
    ], 400);

} catch (Throwable $e) {
    error_log('VNN Chat: ' . $e->getMessage());

    chatReply([
        'status' => 'error',
        'message' => 'Máy chủ không xử lý được yêu cầu.'
    ], 500);
}
