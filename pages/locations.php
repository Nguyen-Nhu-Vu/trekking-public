<?php
require_once __DIR__ . '/../config/database.php';
header('Location: ' . rtrim(BASE_URL, '/') . '/tours/tour.php', true, 302);
exit;
