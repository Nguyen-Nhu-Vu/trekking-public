
<?php
// trekking/tours/tour-review.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../includes/review-eligibility.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tour_id = intval($_POST['tour_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 5);
    $comment = sanitize($_POST['comment'] ?? '');

    if ($tour_id > 0 && $rating >= 1 && $rating <= 5 && !empty($comment) && vnnCanReviewTour($pdo, (int)$_SESSION['user_id'], $tour_id)) {
        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, tour_id, rating, comment, status) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$_SESSION['user_id'], $tour_id, $rating, $comment]);
    }
    header("Location: " . BASE_URL . "tours/tour-detail.php?id=" . $tour_id);
    exit;
}
header("Location: " . BASE_URL . "tours/tour.php");
exit;
