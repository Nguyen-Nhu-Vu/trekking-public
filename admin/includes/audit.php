<?php
// VNN Travel - audit writes to existing admin_audit_logs table.
// Do not stop successful business operations when the log database is unavailable.
if (!function_exists('vnnAuditChange')) {
    function vnnAuditChange(PDO $pdo, string $category, string $action,
                            string $target, int $id, string $before, string $after): void {
        try {
            $actorId = (int)($_SESSION['user_id'] ?? 0);
            $q = $pdo->prepare('SELECT fullname FROM users WHERE id = ? LIMIT 1');
            $q->execute([$actorId]);
            $actor = (string)($q->fetchColumn() ?: ($_SESSION['fullname'] ?? 'Người quản lý'));
            $insert = $pdo->prepare('INSERT INTO admin_audit_logs
                (actor_id, actor_name, category, action, target_type, target_id, old_value, new_value)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$actorId ?: null, $actor, $category, $action,
                $target, (string)$id, $before, $after]);
        } catch (Throwable $e) {
            error_log('Admin audit: ' . $e->getMessage());
        }
    }
}
