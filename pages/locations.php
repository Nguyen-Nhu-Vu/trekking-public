<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Chuẩn hóa đường dẫn ảnh để không bị lỗi khi đang ở /pages/locations.php
 */
function location_image_url($path)
{
    if (!$path || trim($path) === '') {
        return '/assets/images/no-image.jpg';
    }

    $path = trim($path);

    // Nếu là link đầy đủ thì dùng luôn
    if (
        strpos($path, 'http://') === 0 ||
        strpos($path, 'https://') === 0 ||
        strpos($path, '//') === 0
    ) {
        return $path;
    }

    // Bỏ ./ ở đầu nếu có
    if (strpos($path, './') === 0) {
        $path = substr($path, 2);
    }

    // Nếu đang lưu kiểu ../uploads/...
    if (strpos($path, '../') === 0) {
        $path = str_replace('../', '', $path);
    }

    // Nếu chưa có / ở đầu thì thêm vào
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return $path;
}

try {
    // Lấy danh sách địa điểm + số tour hoạt động
    $sql = "
        SELECT 
            l.*,
            COUNT(t.id) AS total_tours
        FROM locations l
        LEFT JOIN tours t 
            ON t.location_id = l.id 
            AND (t.status = 'active' OR t.status IS NULL)
        GROUP BY l.id
        ORDER BY l.id DESC
    ";

    $stmt = $pdo->query($sql);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $locations = [];
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<style>
    .locations-page {
        padding: 30px 0 60px;
        background: #f6f7fb;
        min-height: 100vh;
    }

    .locations-container {
        width: 100%;
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 16px;
    }

    .locations-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 28px;
    }

    .location-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        border: 1px solid #e9edf3;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .location-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.12);
    }

    .location-image {
        width: 100%;
        height: 240px;
        overflow: hidden;
        background: #eef2f7;
    }

    .location-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .location-content {
        padding: 20px 20px 18px;
    }

    .location-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .location-badge {
        display: inline-block;
        background: #1f6b3b;
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 999px;
    }

    .tour-count {
        color: #667085;
        font-size: 15px;
        font-weight: 600;
    }

    .location-title {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 14px;
        line-height: 1.35;
    }

    .location-desc {
        font-size: 16px;
        color: #475467;
        line-height: 1.7;
        min-height: 78px;
        margin-bottom: 18px;
    }

    .location-btn {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        min-height: 48px;
        border: 2px solid #1f6b3b;
        border-radius: 12px;
        text-decoration: none;
        font-size: 17px;
        font-weight: 700;
        color: #1f6b3b;
        transition: 0.2s ease;
    }

    .location-btn:hover {
        background: #1f6b3b;
        color: #fff;
    }

    .empty-box {
        background: #fff;
        border-radius: 16px;
        padding: 40px 20px;
        text-align: center;
        color: #667085;
        font-size: 18px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }

    @media (max-width: 1100px) {
        .locations-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 700px) {
        .locations-grid {
            grid-template-columns: 1fr;
        }

        .location-image {
            height: 220px;
        }
    }
</style>

<section class="locations-page">
    <div class="locations-container">

        <?php if (!empty($locations)): ?>
            <div class="locations-grid">
                <?php foreach ($locations as $location): ?>
                    <?php
                        // Đổi tên cột ảnh tại đây nếu DB của bạn khác
                        $rawImage = $location['image'] 
                            ?? $location['image_url'] 
                            ?? $location['thumbnail'] 
                            ?? $location['photo'] 
                            ?? '';

                        $imageUrl = location_image_url($rawImage);

                        $locationId = (int)($location['id'] ?? 0);
                        $locationName = htmlspecialchars($location['name'] ?? 'Chưa có tên');
                        $locationRegion = htmlspecialchars($location['region'] ?? 'Tây Bắc');
                        $locationDesc = htmlspecialchars($location['description'] ?? 'Đang cập nhật mô tả địa điểm.');
                        $totalTours = (int)($location['total_tours'] ?? 0);
                    ?>
                    <div class="location-card">
                        <div class="location-image">
                            <img 
                                src="<?php echo $imageUrl; ?>" 
                                alt="<?php echo $locationName; ?>"
                                onerror="this.onerror=null;this.src='/assets/images/no-image.jpg';"
                            >
                        </div>

                        <div class="location-content">
                            <div class="location-top">
                                <span class="location-badge"><?php echo $locationRegion; ?></span>
                                <span class="tour-count"><?php echo $totalTours; ?> Tour hoạt động</span>
                            </div>

                            <h3 class="location-title"><?php echo $locationName; ?></h3>

                            <p class="location-desc"><?php echo $locationDesc; ?></p>

                            <a class="location-btn" href="/pages/location-detail.php?id=<?php echo $locationId; ?>">
                                Xem Chi Tiết Điểm Đến →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-box">
                Chưa có địa điểm nào để hiển thị.
            </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
