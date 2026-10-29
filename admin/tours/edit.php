<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

requireManager();

$current_page = 'tours';
$page_title = 'Chỉnh Sửa Tour - VNN Travel';

$tourFormMode = 'edit';

$tourFormId = filter_var(
    $_GET['id'] ?? $_POST['tour_id'] ?? 0,
    FILTER_VALIDATE_INT
);

if (!$tourFormId || $tourFormId <= 0) {
    http_response_code(400);
    exit('Mã tour không hợp lệ.');
}

require __DIR__ . '/tour-form-common.php';
