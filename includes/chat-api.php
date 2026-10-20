
<?php
// VNN Travel - Chat API
// Luu lich su chat trong MySQL, dong bo khach hang va nhan vien.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function chatResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    chatResponse([
        'status' => 'error',
        'message' => 'Vui lòng đăng nhập.'
    ], 401);
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$customer = isCustomer();
$staff = isStaff() || isManager() || isAdmin();

if ($currentUserId <= 0 || (!$customer && !$staff)) {
    chatResponse([
        'status' => 'error',
        'message' => 'Không có quyền truy cập.'
    ], 403);
}

// Giai phong session lock cho cac yeu cau chat song song.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$action = $_GET['action'] ?? '';

try {
    // DANH SACH KHACH HANG
    if ($action === 'get_conversations') {
        if (!$staff) {
            chatResponse([
                'status' => 'error',
                'message' => 'Không đủ quyền.'
            ], 403);
        }

        $sql = "
            SELECT u.id, u.fullname, u.email,
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
                ) AS last_message_id
            FROM users u
            WHERE u.role = 'customer'
            ORDER BY
                (last_message_id IS NULL) ASC,
                last_message_id DESC,
                u.id DESC
        ";

        $result = $pdo->query($sql)->fetchAll();

        chatResponse([
            'status' => 'success',
            'data' => $result
        ]);
    }

    // GUI TIN NHAN
    if ($action === 'send') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            chatResponse([
                'status' => 'error',
                'message' => 'Phương thức không hợp lệ.'
            ], 405);
        }

        $message = trim((string) ($_POST['message'] ?? ''));
        $receiverId = (int) ($_POST['receiver_id'] ?? 0);

        if ($message === '' || mb_strlen($message) > 5000) {
            chatResponse([
                'status' => 'error',
                'message' => 'Tin nhắn phải có từ 1 đến 5000 ký tự.'
            ], 400);
        }

        if ($customer) {
            // Khach hang chi duoc gui den nhan vien/quan ly.
            if ($receiverId > 0) {
                $stmt = $pdo->prepare("
                    SELECT id FROM users
                    WHERE id = ?
                    AND role IN ('manager','staff','admin')
                    LIMIT 1
                ");
                $stmt->execute([$receiverId]);

                if (!$stmt->fetchColumn()) {
                    $receiverId = 0;
                }
            }

            if ($receiverId === 0) {
                // Uu tien nguoi da trao doi truoc do.
                $stmt = $pdo->prepare("
                    SELECT CASE
                        WHEN m.sender_id = ? THEN m.receiver_id
                        ELSE m.sender_id
                    END AS staff_id
                    FROM messages m
                    JOIN users s ON s.id = m.sender_id
                    JOIN users r ON r.id = m.receiver_id
                    WHERE
                        (m.sender_id = ? OR m.receiver_id = ?)
                        AND s.role IN ('customer','manager','staff','admin')
                        AND r.role IN ('customer','manager','staff','admin')
                        AND (s.role IN ('manager','staff','admin')
                             OR r.role IN ('manager','staff','admin'))
                    ORDER BY m.id DESC
                    LIMIT 1
                ");
                $stmt->execute([
                    $currentUserId,
                    $currentUserId,
                    $currentUserId
                ]);
                $receiverId = (int) $stmt->fetchColumn();
            }

            if ($receiverId === 0) {
                $stmt = $pdo->query("
                    SELECT id FROM users
                    WHERE role IN ('manager','staff','admin')
                    ORDER BY
                        CASE role
                            WHEN 'manager' THEN 0
                            WHEN 'admin' THEN 1
                            ELSE 2
                        END,
                        id ASC
                    LIMIT 1
                ");
                $receiverId = (int) $stmt->fetchColumn();
            }
        } else {
            // Quan ly/nhan vien chi gui cho khach hang.
            $stmt = $pdo->prepare("
                SELECT id FROM users
                WHERE id = ? AND role = 'customer'
                LIMIT 1
            ");
            $stmt->execute([$receiverId]);

            if (!$stmt->fetchColumn()) {
                chatResponse([
                    'status' => 'error',
                    'message' => 'Không tìm thấy khách hàng.'
                ], 400);
            }
        }

        if ($receiverId <= 0 || $receiverId === $currentUserId) {
            chatResponse([
                'status' => 'error',
                'message' => 'Không tìm thấy người nhận.'
            ], 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO messages
                (sender_id, receiver_id, message)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $currentUserId,
            $receiverId,
            $message
        ]);

        chatResponse([
            'status' => 'success',
            'message' => 'Đã gửi tin nhắn.',
            'id' => (int) $pdo->lastInsertId(),
            'receiver_id' => $receiverId
        ]);
    }

    // LAY LICH SU TIN NHAN
    if ($action === 'get') {
        $withId = (int) ($_GET['with_id'] ?? 0);

        if ($customer) {
            // Lay toan bo tin giua khach hang va bo phan ho tro.
            $stmt = $pdo->prepare("
                SELECT m.*, u.fullname AS sender_name
                FROM messages m
                JOIN users u ON u.id = m.sender_id
                JOIN users s ON s.id = m.sender_id
                JOIN users r ON r.id = m.receiver_id
                WHERE
                    (
                        m.sender_id = ?
                        AND r.role IN ('manager','staff','admin')
                    )
                    OR
                    (
                        m.receiver_id = ?
                        AND s.role IN ('manager','staff','admin')
                    )
                ORDER BY m.id ASC
            ");
            $stmt->execute([
                $currentUserId,
                $currentUserId
            ]);

            $messages = $stmt->fetchAll();

            // Lay nhan vien da chat gan nhat de tiep tuc hoi thoai.
            $lastStaffId = 0;
            foreach ($messages as $m) {
                if ((int) $m['sender_id'] === $currentUserId) {
                    $lastStaffId = (int) $m['receiver_id'];
                } else {
                    $lastStaffId = (int) $m['sender_id'];
                }
            }

            chatResponse([
                'status' => 'success',
                'data' => $messages,
                'current_user_id' => $currentUserId,
                'with_id' => $lastStaffId,
                'target_name' => 'Bộ phận hỗ trợ VNN Travel'
            ]);
        }

        if ($withId <= 0) {
            chatResponse([
                'status' => 'success',
                'data' => [],
                'current_user_id' => $currentUserId,
                'with_id' => 0,
                'target_name' => 'Khách hàng'
            ]);
        }

        $stmt = $pdo->prepare("
            SELECT fullname FROM users
            WHERE id = ? AND role = 'customer'
            LIMIT 1
        ");
        $stmt->execute([$withId]);

        $targetName = $stmt->fetchColumn();

        if (!$targetName) {
            chatResponse([
                'status' => 'error',
                'message' => 'Không tìm thấy khách hàng.'
            ], 404);
        }

        // Quan ly xem duoc lich su giua khach va tat ca tu van vien.
        $stmt = $pdo->prepare("
            SELECT m.*, u.fullname AS sender_name
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            JOIN users s ON s.id = m.sender_id
            JOIN users r ON r.id = m.receiver_id
            WHERE
                (
                    m.sender_id = ?
                    AND r.role IN ('manager','staff','admin')
                )
                OR
                (
                    m.receiver_id = ?
                    AND s.role IN ('manager','staff','admin')
                )
            ORDER BY m.id ASC
        ");

        $stmt->execute([$withId, $withId]);

        chatResponse([
            'status' => 'success',
            'data' => $stmt->fetchAll(),
            'current_user_id' => $currentUserId,
            'with_id' => $withId,
            'target_name' => $targetName
        ]);
    }

    chatResponse([
        'status' => 'error',
        'message' => 'Action không hợp lệ.'
    ], 400);

} catch (Throwable $e) {
    error_log('VNN Chat Error: ' . $e->getMessage());

    chatResponse([
        'status' => 'error',
        'message' => 'Có lỗi máy chủ. Vui lòng thử lại.'
    ], 500);
}
