
<?php
// trekking/shop/product-review.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../includes/review-eligibility.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 5);
    $comment = sanitize($_POST['comment'] ?? '');

    if ($product_id > 0 && $rating >= 1 && $rating <= 5 && !empty($comment) && vnnCanReviewProduct($pdo, (int)$_SESSION['user_id'], $product_id)) {
        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, comment, status) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$_SESSION['user_id'], $product_id, $rating, $comment]);
    }
    header("Location: " . BASE_URL . "shop/product-detail.php?id=" . $product_id);
    exit;
}
header("Location: " . BASE_URL . "shop/shop.php");
exit;
