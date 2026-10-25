
<?php
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Chi nguoi quan ly moi duoc truy cap.
requireManager();

// Danh muc da duoc gop vao trang kho hang.
// Chuyen ve trang quan ly chung.

$baseUrl = rtrim(BASE_URL, '/') . '/';

header(
    'Location: ' . $baseUrl . 'admin/products/',
    true,
    302
);

exit;
