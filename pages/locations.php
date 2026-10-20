
<?php
// VNN Travel - Danh sach dia diem
// Uu tien anh dia diem, neu loi thi su dung anh tour.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Địa Điểm Trekking';
$active_menu = 'location';

// Chuan hoa URL anh cho Vercel va XAMPP
function vnn_location_image_url($path)
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    // URL anh ben ngoai
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    if (strpos($path, '//') === 0) {
        return 'https:' . $path;
    }

    // Bo dau duong dan tuong doi
    $path = str_replace('\\', '/', $path);

    while (strpos($path, '../') === 0) {
        $path = substr($path, 3);
    }

    while (strpos($path, './') === 0) {
        $path = substr($path, 2);
    }

    // Ho tro duong dan cua XAMPP
    $path = preg_replace(
        '~^/?trekking/~i',
        '',
        $path
    );

    $path = ltrim($path, '/');

    if ($path === '') {
        return '';
    }

    // URL dia phuong, khong cho phep path traversal
    $segments = explode('/', $path);

    if (in_array('..', $segments, true)) {
        return '';
    }

    return '/' . $path;
}

// Lay anh tu cac cot co the co trong bang locations
function vnn_location_original_image($location)
{
    foreach (['image', 'image_url', 'thumbnail', 'photo'] as $column) {
        if (!empty($location[$column])) {
            return vnn_location_image_url($location[$column]);
        }
    }

    return '';
}

// Lay danh sach dia diem va anh tour tuong ung
try {
    $sql = "
        SELECT
            l.*,

            (
                SELECT COUNT(*)
                FROM tours t
                WHERE t.location_id = l.id
            ) AS total_tours,

            (
                SELECT t.image
                FROM tours t
                WHERE t.location_id = l.id
                  AND t.image IS NOT NULL
                  AND TRIM(t.image) <> ''
                ORDER BY t.id DESC
                LIMIT 1
            ) AS tour_image

        FROM locations l
        ORDER BY l.id DESC
    ";

    $stmt = $pdo->query($sql);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Locations query: ' . $e->getMessage());
    $locations = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.locations-page {
    padding: 30px 0 65px;
    background: #f6f7fb;
    min-height: 75vh;
}

.locations-container {
    max-width: 1280px;
    width: 100%;
    margin: 0 auto;
    padding: 0 16px;
}

.locations-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 28px;
}

.location-card {
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e9edf3;
    box-shadow: 0 6px 18px rgba(0,0,0,0.07);
    transition: transform 0.25s, box-shadow 0.25s;
}

.location-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.12);
}

.location-image {
    width: 100%;
    height: 220px;
    background: linear-gradient(135deg, #d9f3df, #dbeafe);
    position: relative;
    overflow: hidden;
}

.location-image img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    transition: transform 0.3s;
}

.location-card:hover .location-image img {
    transform: scale(1.04);
}

.location-fallback {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 12px;
    color: #166534;
    text-align: center;
    padding: 20px;
}

.location-fallback i {
    font-size: 42px;
}

.location-fallback span {
    font-size: 16px;
    font-weight: 700;
}

.location-content {
    padding: 20px;
}

.location-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}

.location-badge {
    display: inline-block;
    background: #185b30;
    color: #ffffff;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}

.tour-count {
    color: #64748b;
    font-size: 14px;
    font-weight: 600;
}

.location-title {
    color: #0f172a;
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 12px;
    line-height: 1.4;
}

.location-desc {
    color: #64748b;
    font-size: 15px;
    line-height: 1.7;
    min-height: 75px;
    margin-bottom: 18px;
}

.location-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    text-decoration: none;
    min-height: 42px;
    padding: 10px;
    border: 2px solid #185b30;
    border-radius: 12px;
    color: #185b30;
    font-weight: 700;
    font-size: 15px;
    transition: 0.2s;
}

.location-btn:hover {
    background: #185b30;
    color: #ffffff;
}

.locations-empty {
    background: #ffffff;
    padding: 50px 20px;
    text-align: center;
    border-radius: 16px;
    color: #64748b;
}

@media (max-width: 1100px) {
    .locations-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .locations-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .location-image {
        height: 220px;
    }

    .location-desc {
        min-height: auto;
    }
}
</style>

<section class="locations-page">
    <div class="locations-container">

        <?php if (!empty($locations)): ?>

            <div class="locations-grid">

                <?php foreach ($locations as $location): ?>

                    <?php
                    $locationId = (int) ($location['id'] ?? 0);
                    $locationName = (string) ($location['name'] ?? 'Địa điểm trekking');
                    $region = (string) ($location['region'] ?? 'Việt Nam');
                    $description = (string) ($location['description'] ?? '');
                    $totalTours = (int) ($location['total_tours'] ?? 0);

                    $originalImage = vnn_location_original_image($location);

                    $tourImage = vnn_location_image_url(
                        $location['tour_image'] ?? ''
                    );

                    // Uu tien anh goc, sau do den anh tour.
                    $primaryImage = $originalImage !== ''
                        ? $originalImage
                        : $tourImage;

                    // Anh thu hai khi anh dau bi loi.
                    $secondaryImage = (
                        $originalImage !== '' &&
                        $tourImage !== '' &&
                        $tourImage !== $originalImage
                    ) ? $tourImage : '';

                    $safeName = htmlspecialchars(
                        $locationName,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                    <article class="location-card">

                        <div class="location-image">

                            <div class="location-fallback">
                                <i class="fa-solid fa-mountain-sun"></i>
                                <span>
                                    <?= $safeName ?>
                                </span>
                            </div>

                            <?php if ($primaryImage !== ''): ?>
                                <img
                                    src="<?= htmlspecialchars(
                                        $primaryImage,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="<?= $safeName ?>"
                                    loading="lazy"
                                    data-alternate="<?= htmlspecialchars(
                                        $secondaryImage,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    data-tried-alternate="0"
                                    onerror="
                                        if (
                                            this.dataset.triedAlternate === '0' &&
                                            this.dataset.alternate
                                        ) {
                                            this.dataset.triedAlternate = '1';
                                            this.src = this.dataset.alternate;
                                        } else {
                                            this.style.display = 'none';
                                        }
                                    "
                                >
                            <?php endif; ?>

                        </div>

                        <div class="location-content">

                            <div class="location-top">

                                <span class="location-badge">
                                    <?= htmlspecialchars(
                                        $region,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <span class="tour-count">
                                    <?= $totalTours ?> Tour
                                </span>

                            </div>

                            <h3 class="location-title">
                                <?= $safeName ?>
                            </h3>

                            <p class="location-desc">
                                <?= htmlspecialchars(
                                    $description !== ''
                                        ? $description
                                        : 'Khám phá thiên nhiên và các cung đường trekking.',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                            <a
                                class="location-btn"
                                href="<?= htmlspecialchars(
                                    rtrim(BASE_URL, '/') .
                                    '/pages/location-detail.php?id=' .
                                    $locationId,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                Xem Chi Tiết Điểm Đến →
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="locations-empty">
                <i class="fa-solid fa-mountain"
                   style="font-size:40px;margin-bottom:15px;"></i>

                <h3>Chưa có địa điểm nào</h3>

                <p>Danh sách địa điểm đang được cập nhật.</p>
            </div>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
