<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

requireManager();

$current_page = 'tours';
$page_title = 'Thêm Tour Mới - VNN Travel';

$tourFormMode = 'add';
$tourFormId = 0;

require __DIR__ . '/tour-form-common.php';
