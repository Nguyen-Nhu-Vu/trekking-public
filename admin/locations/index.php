<?php
require_once __DIR__ . '/../../config/database.php';
header('Location: ' . rtrim(BASE_URL, '/') . '/admin/tours/', true, 302);
exit;
