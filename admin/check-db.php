
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireManager();

header('Content-Type: text/plain; charset=utf-8');

echo "Database website dang su dung: ";
echo $pdo->query("SELECT DATABASE()")->fetchColumn();
