<?php
// trekking/google-callback.php
require_once 'config/database.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $credential = $_POST['credential'];
    
    $parts = explode('.', $credential);
    if (count($parts) === 3) {
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        $email = $payload['email'] ?? '';
        $fullname = $payload['name'] ?? 'Khách Google';
        
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $pwd_rand = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO users (fullname, email, password, role, status) VALUES (?, ?, ?, 'customer', 1)");
                $ins->execute([$fullname, $email, $pwd_rand]);
                $user_id = $pdo->lastInsertId();
                $user = ['id' => $user_id, 'fullname' => $fullname, 'email' => $email, 'role' => 'customer', 'status' => 1];
            }
            
            if ($user['status'] == 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                if (in_array($user['role'], ['admin', 'manager', 'staff'])) {
                    header("Location: admin/index.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $_SESSION['flash_error'] = "Tài khoản của bạn đã bị tạm khóa!";
                header("Location: login.php");
                exit;
            }
        }
    }
}
header("Location: login.php");
exit;