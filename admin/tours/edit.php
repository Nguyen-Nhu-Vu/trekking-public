
<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireManager();

$current_page = 'tours';
$page_title = 'Chỉnh Sửa Tour - VNN Travel';

$tourFormMode = 'edit';
$tourFormId = filter_var(
    $_GET['id'] ?? $_POST['tour_id'] ?? 0,
    FILTER_VALIDATE_INT
);

if (!$tourFormId || $tourFormId <= 0) {
    header('Location: index.php', true, 303);
    exit;
}

require __DIR__ . '/tour-form-common.php';
