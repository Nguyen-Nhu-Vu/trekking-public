<?php
// trekking/tours/tour-detail.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT t.*, l.name as loc_name, l.region 
                       FROM tours t 
                       JOIN locations l ON t.location_id = l.id 
                       WHERE t.id = ? AND t.status = 1");
$stmt->execute([$id]);
$tour = $stmt->fetch();

if (!$tour) {
    header("Location: " . BASE_URL . "tours/tour.php");
    exit;
}

$page_title = $tour['title'];

// Lấy danh sách ngày khởi hành thực tế của tour này
$departures_stmt = $pdo->prepare("SELECT * FROM tour_departures WHERE tour_id = ? AND departure_date >= CURDATE() AND status = 1 ORDER BY departure_date ASC");
$departures_stmt->execute([$id]);
$departures = $departures_stmt->fetchAll();

// Lấy danh sách đánh giá
$reviews_stmt = $pdo->prepare("SELECT r.*, u.fullname 
                               FROM reviews r 
                               JOIN users u ON r.user_id = u.id 
                               WHERE r.tour_id = ? AND r.status = 1 
                               ORDER BY r.id DESC");
$reviews_stmt->execute([$id]);
$reviews = $reviews_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tour['title']) ?> - TrekHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/responsive.css">
    <style>
        html {
            scroll-behavior: smooth;
        }

        /* Ẩn duy nhất dải menu điều hướng trang chính (Giữ nguyên Logo, Hotline, Tài khoản, Đăng xuất) */
        .navbar .nav-menu {
            display: none !important;
        }
        .navbar .nav-actions form {
            display: none !important;
        }

        /* THANH CÔNG CỤ DÍNH CỦA TOUR - THOÁNG RỘNG, KHÔNG BỊ CHẬT */
        .tour-sticky-bar {
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            background: #ffffff;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
            z-index: 999;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 0; /* Tăng padding trên dưới để thoáng */
        }
        .tour-bar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: nowrap;
        }
        .tour-nav-tabs-group {
            display: flex;
            align-items: center;
            gap: 12px;
            overflow-x: auto;
            white-space: nowrap;
            padding: 4px 0;
        }
        .tour-tab-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.94rem;
            font-weight: 700;
            color: #475569;
            background: #f1f5f9;
            text-decoration: none;
            transition: all 0.25s ease;
            white-space: nowrap;
        }
        .tour-tab-link:hover {
            background: #fed7aa;
            color: #ea580c;
        }
        .tour-tab-link.active {
            background: #f97316 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.35);
        }

        /* BANNER HERO TỔNG QUAN */
        .tour-hero-section {
            position: relative;
            height: 480px;
            background: linear-gradient(rgba(15, 23, 42, 0.55), rgba(15, 23, 42, 0.8)), url('<?= htmlspecialchars($tour['image']) ?>') center/cover no-repeat;
            display: flex;
            align-items: flex-end;
            padding-bottom: 45px;
            color: white;
            scroll-margin-top: 85px;
        }
        .tour-hero-badge {
            display: inline-block;
            background: var(--accent);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        /* KHUNG ĐẶT TOUR BÊN PHẢI */
        .booking-side-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #fed7aa;
            overflow: hidden;
        }
        .booking-card-head {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            padding: 22px;
            text-align: center;
        }

        /* LỊCH TRÌNH FULL WIDTH */
        .schedule-grid-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        @media (max-width: 992px) {
            .schedule-grid-container {
                grid-template-columns: 1fr;
            }
        }
        .schedule-day-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: 5px solid #f97316;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
        }
        .schedule-day-badge {
            background: #ffedd5;
            color: #c2410c;
            font-weight: 800;
            font-size: 0.88rem;
            padding: 7px 16px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        /* ACCORDION DỊCH VỤ VÀ LƯU Ý */
        .accordion-item-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 14px;
            overflow: hidden;
            transition: border 0.25s ease;
        }
        .accordion-item-box.open {
            border: 2px solid #f97316;
        }
        .accordion-item-head {
            padding: 18px 22px;
            background: #f8fafc;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        .accordion-item-body {
            padding: 22px;
            display: none;
            background: #f0fdf4;
            color: #334155;
            line-height: 1.85;
            border-top: 1px solid #dcfce7;
        }
        .accordion-item-body p {
            margin-bottom: 12px;
        }
        .accordion-item-body p:last-child {
            margin-bottom: 0;
        }

        .scroll-section {
            scroll-margin-top: 90px;
            margin-bottom: 45px;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<!-- THANH CÔNG CỤ DÍNH CỦA TOUR - THOÁNG VÀ RỘNG RÃI -->
<div class="tour-sticky-bar">
    <div class="container tour-bar-inner">
        <a href="<?= BASE_URL ?>tours/tour.php" class="btn btn-outline btn-sm" style="border-radius:20px; white-space:nowrap;">
            <i class="fa-solid fa-arrow-left"></i> Tour khác
        </a>

        <div class="tour-nav-tabs-group">
            <a href="#tongquan" class="tour-tab-link active">
                <i class="fa-solid fa-mountain-sun"></i> Tổng Quan
            </a>
            <a href="#thongtin" class="tour-tab-link">
                <i class="fa-solid fa-circle-info"></i> Thông Tin & Đặt Tour
            </a>
            <a href="#lichtrinh" class="tour-tab-link">
                <i class="fa-solid fa-calendar-days"></i> Lịch Trình Chi Tiết
            </a>
            <a href="#luuy" class="tour-tab-link">
                <i class="fa-solid fa-shield-halved"></i> Dịch Vụ & Lưu Ý
            </a>
            <a href="#danhgia" class="tour-tab-link">
                <i class="fa-solid fa-star"></i> Đánh Giá (<?= count($reviews) ?>)
            </a>
        </div>

        <a href="<?= BASE_URL ?>tours/tour-register.php?id=<?= $tour['id'] ?>" class="btn btn-accent btn-sm" style="border-radius:20px; font-weight:bold; white-space:nowrap; padding:9px 18px;">
            <i class="fa-solid fa-ticket"></i> Đặt Chỗ Ngay
        </a>
    </div>
</div>

<!-- 1. TỔNG QUAN -->
<section id="tongquan" class="tour-hero-section scroll-section">
    <div class="container">
        <span class="tour-hero-badge"><i class="fa-solid fa-mountain"></i> <?= htmlspecialchars($tour['difficulty']) ?></span>
        <h1 style="font-size:2.8rem; font-weight:800; margin-bottom:12px; line-height:1.2;"><?= htmlspecialchars($tour['title']) ?></h1>
        <div style="display:flex; flex-wrap:wrap; gap:22px; font-size:1rem; opacity:0.95;">
            <span><i class="fa-solid fa-location-dot" style="color:var(--accent);"></i> <?= htmlspecialchars($tour['loc_name']) ?> (<?= htmlspecialchars($tour['region']) ?>)</span>
            <span><i class="fa-regular fa-clock" style="color:var(--accent);"></i> <?= htmlspecialchars($tour['duration']) ?></span>
            <span><i class="fa-solid fa-arrow-up-right-dots" style="color:var(--accent);"></i> <?= htmlspecialchars($tour['altitude']) ?></span>
            <span><i class="fa-solid fa-person-walking" style="color:var(--accent);"></i> <?= htmlspecialchars($tour['distance']) ?></span>
            <span><i class="fa-solid fa-user-ninja" style="color:var(--accent);"></i> HDV: <?= htmlspecialchars($tour['tour_guide'] ?? 'Leader TrekHub') ?></span>
        </div>
    </div>
</section>

<div class="container">
    <!-- KHU VỰC THÔNG TIN & ĐẶT TOUR -->
    <div id="thongtin" class="scroll-section" style="margin-bottom:45px;">
        <div class="grid grid-3" style="align-items:stretch; gap:30px;">
            <!-- CỘT TRÁI: GIỚI THIỆU TOUR -->
            <div style="grid-column: span 2; background:white; padding:32px; border-radius:14px; border:1px solid #e2e8f0; box-shadow:var(--shadow); display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <span class="section-subtitle"><i class="fa-solid fa-circle-info"></i> THÔNG TIN TOUR</span>
                    <h2 style="font-size:1.8rem; color:#0f172a; margin:8px 0 18px;">Hành Trình <?= htmlspecialchars($tour['title']) ?></h2>
                    <div style="line-height:1.85; color:#334155; font-size:1.02rem;">
                        <?= nl2br(htmlspecialchars($tour['description'] ?? 'Đang cập nhật nội dung giới thiệu cung đường...')) ?>
                    </div>
                </div>

                <div style="background:#fff7ed; border-left:4px solid #f97316; padding:18px; border-radius:8px; margin-top:25px; display:flex; gap:15px; align-items:center;">
                    <i class="fa-solid fa-user-ninja" style="font-size:2rem; color:#ea580c;"></i>
                    <div>
                        <strong style="color:#c2410c; display:block; margin-bottom:4px;">LỜI KHUYÊN TỪ LEADER:</strong>
                        <span style="font-size:0.92rem; color:#7c2d12; line-height:1.6;">
                            Thành viên cần rèn luyện thể lực trước 1-2 tuần. Chuẩn bị trang phục thoát mồ hôi ban ngày và áo ấm giữ nhiệt chuyên dụng khi đêm xuống trên lán nghỉ.
                        </span>
                    </div>
                </div>
            </div>

            <!-- CỘT PHẢI: KHUNG ĐẶT TOUR & CHỌN NGÀY KHỞI HÀNH -->
            <div>
                <div class="booking-side-card">
                    <div class="booking-card-head">
                        <span style="font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; opacity:0.9;">GIÁ TOUR TRỌN GÓI</span>
                        <div style="font-size:2.2rem; font-weight:800;"><?= format_price($tour['price']) ?></div>
                        <div style="font-size:0.85rem; opacity:0.9;">/ mỗi khách (Đã gồm ăn uống & bảo hiểm)</div>
                    </div>

                    <div style="padding:25px;">
                        <div style="background:#f8fafc; padding:16px; border-radius:10px; margin-bottom:20px; font-size:0.92rem; border:1px solid #e2e8f0;">
                            <div style="margin-bottom:12px;">
                                <label style="display:block; font-weight:700; color:#334155; margin-bottom:6px;">
                                    <i class="fa-regular fa-calendar-days" style="color:#ea580c;"></i> Chọn Ngày Xuất Phát:
                                </label>
                                <select id="selected_departure_date" class="form-control" style="font-weight:600; padding:8px 12px; border-radius:8px; border-color:#cbd5e1;">
                                    <?php if (count($departures) > 0): ?>
                                        <?php foreach ($departures as $dep): ?>
                                            <option value="<?= $dep['departure_date'] ?>">
                                                <?= date('d/m/Y', strtotime($dep['departure_date'])) ?> (Còn <?= $dep['available_slots'] ?> chỗ)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="custom">Liên hệ xếp lịch riêng linh hoạt</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <span>Thời lượng:</span>
                                <strong style="color:#ea580c;"><?= htmlspecialchars($tour['duration']) ?></strong>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <span>Điểm đón:</span>
                                <strong><?= htmlspecialchars($tour['meeting_point']) ?></strong>
                            </div>
                        </div>

                        <?php if ($tour['available_slots'] > 0): ?>
                            <a id="btnBookingDirect" href="<?= BASE_URL ?>tours/tour-register.php?id=<?= $tour['id'] ?>" class="btn btn-accent" style="width:100%; padding:14px; font-size:1.05rem; display:block; text-align:center; border-radius:30px; box-shadow:0 6px 18px rgba(234, 88, 12, 0.35);">
                                <i class="fa-solid fa-ticket"></i> ĐĂNG KÝ THEO NGÀY ĐÃ CHỌN
                            </a>
                        <?php else: ?>
                            <button class="btn btn-danger" disabled style="width:100%; padding:14px; border-radius:30px;">TOUR ĐÃ HẾT CHỖ</button>
                        <?php endif; ?>

                        <div style="margin-top:16px; text-align:center; font-size:0.82rem; color:#64748b; line-height:1.5;">
                            <i class="fa-solid fa-phone" style="color:#f97316;"></i> Hotline tư vấn 24/7: <strong>0988.888.888</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. LỊCH TRÌNH CHI TIẾT FULL WIDTH -->
    <div id="lichtrinh" class="scroll-section">
        <div style="text-align:left; margin-bottom:20px;">
            <span class="section-subtitle"><i class="fa-solid fa-route"></i> HÀNH TRÌNH KHÁM PHÁ</span>
            <h2 class="section-title">Lịch Trình Chi Tiết Từng Ngày</h2>
            <p style="color:#64748b; font-size:0.95rem; margin-top:6px;">
                <i class="fa-solid fa-bus"></i> Xe đưa đón: <?= htmlspecialchars($tour['transport']) ?> | <i class="fa-solid fa-map-pin"></i> Điểm tập trung: <?= htmlspecialchars($tour['meeting_point']) ?>
            </p>
        </div>

        <div class="schedule-grid-container">
            <?php
            $raw_schedule = trim($tour['schedule'] ?? '');
            if (!empty($raw_schedule)) {
                $days = preg_split('/(?=NGÀY\s+\d+)/u', $raw_schedule, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($days as $day_text) {
                    $lines = array_filter(array_map('trim', explode("\n", $day_text)));
                    if (!empty($lines)) {
                        $header = array_shift($lines);
                        echo '<div class="schedule-day-card">';
                        echo '<span class="schedule-day-badge"><i class="fa-regular fa-calendar-check"></i> ' . htmlspecialchars($header) . '</span>';
                        echo '<div style="font-size:0.93rem; line-height:1.8; color:#334155;">';
                        foreach ($lines as $line) {
                            echo '<div style="margin-bottom:8px;">' . htmlspecialchars($line) . '</div>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<div class="schedule-day-card" style="grid-column: span 3;"><p style="color:#64748b; text-align:center;">Lịch trình chi tiết đang được Leader hoàn thiện.</p></div>';
            }
            ?>
        </div>
    </div>

    <!-- 3. DỊCH VỤ & LƯU Ý -->
    <div id="luuy" class="scroll-section">
        <div style="background:white; padding:35px; border-radius:14px; border:1px solid #e2e8f0; box-shadow:var(--shadow);">
            <span class="section-subtitle"><i class="fa-solid fa-shield-halved"></i> QUY ĐỊNH & AN TOÀN</span>
            <h2 class="section-title" style="margin-bottom:25px;">Dịch Vụ Bao Gồm & Những Lưu Ý Quan Trọng</h2>

            <div class="accordion-item-box open">
                <div class="accordion-item-head" onclick="toggleAcc(this)">
                    <span><i class="fa-solid fa-circle-check" style="color:#16a34a; margin-right:8px;"></i> Dịch Vụ Tour Bao Gồm</span>
                    <i class="fa-solid fa-chevron-up"></i>
                </div>
                <div class="accordion-item-body" style="display:block;">
                    <p>✔ <strong>Phương tiện di chuyển:</strong> Xe du lịch giường nằm cao cấp đưa đón 2 chiều từ Hà Nội đến chân núi và ngược lại (xe đời mới, máy lạnh êm ái, có rèm che riêng tư).</p>
                    <p>✔ <strong>Đội ngũ chuyên nghiệp:</strong> 01 Hướng dẫn viên am hiểu địa hình dẫn đường suốt tuyến + Đội ngũ porter bản địa hỗ trợ mang vác toàn bộ đồ ăn, nước uống và trang thiết bị chung.</p>
                    <p>✔ <strong>Lưu trú & Chỗ ngủ:</strong> Chỗ ngủ tại lán gỗ kín gió hoặc lều cắm trại tiêu chuẩn, trang bị đầy đủ đệm cách nhiệt, chăn bông ấm áp hoặc túi ngủ chuyên dụng.</p>
                    <p>✔ <strong>Ăn uống tiêu chuẩn:</strong> Toàn bộ các bữa ăn nóng sốt trong hành trình (bữa sáng phở/mì nóng, bữa trưa tiếp sức giàu năng lượng, bữa tối tiệc nướng BBQ hoặc lẩu gà đồi ấm cúng giữa tiết trời se lạnh).</p>
                    <p>✔ <strong>Trang bị y tế & sinh tồn:</strong> Nước khoáng tinh khiết đóng chai (2 chai/ngày/khách), túi thuốc sơ cấp cứu y tế leo núi chuyên dụng (sát trùng, băng gạc, hạ sốt, bù điện giải oresol), gậy trekking trợ lực cho mỗi khách.</p>
                    <p>✔ <strong>Bảo hiểm du lịch:</strong> Gói bảo hiểm du lịch mạo hiểm với hạn mức bồi thường tối đa lên tới <strong>100.000.000 VNĐ / vụ</strong>.</p>
                    <p>✔ <strong>Tặng kèm:</strong> Huy chương chứng nhận chạm đỉnh độc quyền từ TrekHub + Dịch vụ ngâm chân lá thuốc Dao Đỏ sau khi xuống núi.</p>
                </div>
            </div>

            <div class="accordion-item-box">
                <div class="accordion-item-head" onclick="toggleAcc(this)">
                    <span><i class="fa-solid fa-circle-xmark" style="color:#dc2626; margin-right:8px;"></i> Dịch Vụ Tour Không Bao Gồm</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="accordion-item-body">
                    <p>✖ Chi phí tiêu dùng cá nhân phát sinh ngoài chương trình (mua sắm đặc sản bản địa, quà lưu niệm, đồ ăn gọi thêm ngoài thực đơn...).</p>
                    <p>✖ Các loại đồ uống có cồn (bia, rượu), nước ngọt đóng lon phát sinh ngoài tiêu chuẩn nước lọc được cấp.</p>
                    <p>✖ Dịch vụ thuê porter riêng gùi ba lô đồ cá nhân nặng trên 4kg (nếu có nhu cầu sẽ tính phụ thu riêng theo giá bản địa).</p>
                    <p>✖ Thuế VAT 8% - 10% (Chỉ phụ thu nếu quý khách có nhu cầu xuất hóa đơn đỏ điện tử).</p>
                    <p>✖ Tiền bồi dưỡng (Tip) cho Leader, tài xế và porter địa phương (hoàn toàn tùy tâm, không bắt buộc).</p>
                </div>
            </div>

            <div class="accordion-item-box">
                <div class="accordion-item-head" onclick="toggleAcc(this)">
                    <span><i class="fa-solid fa-bag-shopping" style="color:#ea580c; margin-right:8px;"></i> Bạn Cần Chuẩn Bị Những Gì Cho Chuyến Đi?</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="accordion-item-body">
                    <p>🎒 <strong>Balo trekking:</strong> Balo thể tích 20L - 30L có quai đai trợ lực hông và ngực kèm bọc che mưa chuyên dụng.</p>
                    <p>👟 <strong>Giày leo núi:</strong> Giày thể thao leo núi chuyên dụng có gai cao su bám đá chống trơn trượt (khuyến khích chọn size rộng hơn 0.5 - 1 size để không bị đau đầu ngón chân khi xuống dốc).</p>
                    <p>🧥 <strong>Trang phục:</strong> 02 bộ quần áo mỏng nhẹ mau khô khi leo ban ngày; 01 áo phao ấm hoặc áo lông cừu khi đêm xuống nhiệt độ hạ sâu dưới 5°C; 01 áo gió chống thấm nước.</p>
                    <p>🔦 <strong>Phụ kiện cá nhân:</strong> Đèn pin đội đầu loại sáng tốt kèm pin dự phòng; bình giữ nhiệt 0.5L - 1L; găng tay bảo hộ ma sát tốt; tất dày 2-3 đôi; mũ len, khăn rằn.</p>
                    <p>💊 <strong>Thuốc men cá nhân:</strong> Thuốc say xe (nếu có), kem chống muỗi, dầu gió, sạc dự phòng điện thoại.</p>
                </div>
            </div>

            <div class="accordion-item-box">
                <div class="accordion-item-head" onclick="toggleAcc(this)">
                    <span><i class="fa-solid fa-triangle-exclamation" style="color:#eab308; margin-right:8px;"></i> Quy Định Hoàn & Huỷ Tour An Toàn</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="accordion-item-body">
                    <p>📅 <strong>Thông báo hủy trước 07 ngày:</strong> TrekHub hoàn lại <strong>100%</strong> tiền cọc hoặc hỗ trợ bảo lưu chuyến đi sang ngày khác trong vòng 60 ngày miễn phí.</p>
                    <p>📅 <strong>Thông báo hủy từ 03 đến 06 ngày trước giờ khởi hành:</strong> Khấu trừ <strong>50%</strong> tiền cọc (để chi trả chi phí giữ xe và đặt lán nghỉ địa phương).</p>
                    <p>🚫 <strong>Thông báo hủy trong vòng 48 giờ trước giờ xe chạy:</strong> Không hoàn lại tiền cọc vì ban tổ chức đã chốt xe, mua vé rừng và thực phẩm tươi sống.</p>
                    <p>🌦️ <strong>Trường hợp bão lũ bất khả kháng:</strong> Nếu kiểm lâm cấm rừng hoặc thiên tai nguy hiểm, TrekHub sẽ dời ngày hoặc hoàn lại 100% chi phí.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. ĐÁNH GIÁ -->
    <div id="danhgia" class="scroll-section">
        <div style="background:white; padding:35px; border-radius:14px; border:1px solid #e2e8f0; box-shadow:var(--shadow);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:15px;">
                <h3 style="margin:0; color:#0f172a;"><i class="fa-solid fa-comments" style="color:#f97316;"></i> Đánh Giá Của Các Trekker (<?= count($reviews) ?>)</h3>
            </div>

            <?php if (isLoggedIn()): ?>
                <form action="<?= BASE_URL ?>tours/tour-review.php" method="POST" style="background:#f8fafc; padding:20px; border-radius:10px; margin-bottom:25px;">
                    <input type="hidden" name="tour_id" value="<?= $tour['id'] ?>">
                    <div class="form-group">
                        <label style="font-weight:600;">Mức độ hài lòng:</label>
                        <select name="rating" class="form-control" style="width:160px;">
                            <option value="5">⭐⭐⭐⭐⭐ 5 Sao</option>
                            <option value="4">⭐⭐⭐⭐ 4 Sao</option>
                            <option value="3">⭐⭐⭐ 3 Sao</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-weight:600;">Chia sẻ cảm nhận về chuyến đi:</label>
                        <textarea name="comment" class="form-control" rows="3" required placeholder="Hướng dẫn viên thế nào? Cảnh quan có đẹp không?..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Gửi Đánh Giá</button>
                </form>
            <?php else: ?>
                <p style="color:#64748b; margin-bottom:20px;">
                    Vui lòng <a href="<?= BASE_URL ?>auth/login.php" style="color:#f97316; font-weight:bold;">đăng nhập</a> để viết cảm nhận về chuyến đi này.
                </p>
            <?php endif; ?>

            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $rev): ?>
                    <div style="border-bottom:1px solid #f1f5f9; padding:15px 0;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <strong><?= htmlspecialchars($rev['fullname']) ?></strong>
                            <span style="color:#f59e0b;"><?= str_repeat('★', (int)$rev['rating']) ?></span>
                        </div>
                        <p style="font-size:0.92rem; color:#475569;"><?= htmlspecialchars($rev['comment']) ?></p>
                        <small style="color:#94a3b8;"><?= date('d/m/Y H:i', strtotime($rev['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; padding:25px; color:#94a3b8;">Chưa có nhận xét nào. Hãy là người đầu tiên trải nghiệm và chia sẻ!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Tự động cập nhật link đặt tour theo ngày xuất phát khách chọn
const selectDate = document.getElementById('selected_departure_date');
const btnBooking = document.getElementById('btnBookingDirect');
if (selectDate && btnBooking) {
    function updateBookingLink() {
        const dateVal = selectDate.value;
        btnBooking.href = '<?= BASE_URL ?>tours/tour-register.php?id=<?= $tour['id'] ?>&departure=' + encodeURIComponent(dateVal);
    }
    selectDate.addEventListener('change', updateBookingLink);
    updateBookingLink();
}

// Scrollspy nhận diện phần đang cuộn
const sections = document.querySelectorAll('.scroll-section');
const navLinks = document.querySelectorAll('.tour-tab-link');

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const currentId = entry.target.getAttribute('id');
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + currentId) {
                    link.classList.add('active');
                }
            });
        }
    });
}, {
    root: null,
    rootMargin: '-20% 0px -70% 0px',
    threshold: 0
});

sections.forEach(section => observer.observe(section));

// Accordion toggle
function toggleAcc(headElement) {
    const parentBox = headElement.parentElement;
    const body = headElement.nextElementSibling;
    const icon = headElement.querySelector('.fa-chevron-down, .fa-chevron-up');

    parentBox.classList.toggle('open');
    if (body.style.display === 'block') {
        body.style.display = 'none';
        if (icon) icon.className = 'fa-solid fa-chevron-down';
    } else {
        body.style.display = 'block';
        if (icon) icon.className = 'fa-solid fa-chevron-up';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>