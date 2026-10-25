<?php
declare(strict_types=1);

/**
 * Trang Giới Thiệu - Nguồn Gốc, Sự Hình Thành & Tôn Chỉ Bếp Chay An Yên (Issue #22)
 * VeganFoodShop - Bếp Chay An Yên
 * UTF-8 No BOM, chuẩn PHP 8.4+
 */

require_once __DIR__ . '/src/autoload.php';
$config = require __DIR__ . '/config/config.php';

use App\Database\Connection;
use App\Services\ConfigService;

$db = Connection::getInstance();
$configService = new ConfigService($db);
$brandName = $configService->get('brand_name', 'Bếp Chay An Yên');
$sellerAddress = $configService->get('seller_address', 'Số 12 Ngõ An Bình, Phố Tràng An, Hà Nội');
$sellerPhone = $configService->get('seller_phone', '0987654321');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8008';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$fullCurrentUrl = $protocol . $host . $requestUri;
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$previewImageUrl = $protocol . $host . ($scriptDir !== '' ? $scriptDir : '') . '/assets/images/preview.jpg';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Về Bếp Chay An Yên - Nguồn Gốc & Tôn Chỉ Thuần Chay</title>
    <meta name="description" content="Khám phá câu chuyện khởi nguồn và 4 tôn chỉ ẩm thực thuần chay thanh tịnh tại Bếp Chay An Yên: Thuần khiết tự nhiên, nấu theo ca giới hạn suất, nuôi dưỡng lòng từ và trân quý từng thực khách.">

    <!-- Các thẻ Open Graph cơ bản (Issue #31) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($fullCurrentUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="Về Bếp Chay An Yên - Nguồn Gốc & Bốn Tôn Chỉ Thuần Chay">
    <meta property="og:description" content="Khám phá câu chuyện khởi nguồn và 4 tôn chỉ ẩm thực thuần chay thanh tịnh tại Bếp Chay An Yên: Thuần khiết tự nhiên, nấu theo ca giới hạn suất, nuôi dưỡng lòng từ và trân quý từng thực khách.">
    <meta property="og:image" content="<?= htmlspecialchars($previewImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="Về Bếp Chay An Yên - Nguồn Gốc & Tôn Chỉ Thuần Chay">

    <!-- Thẻ hỗ trợ riêng cho Twitter / X -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Về Bếp Chay An Yên - Nguồn Gốc & Bốn Tôn Chỉ Thuần Chay">
    <meta name="twitter:description" content="Khám phá câu chuyện khởi nguồn và 4 tôn chỉ ẩm thực thuần chay thanh tịnh tại Bếp Chay An Yên: Thuần khiết tự nhiên, nấu theo ca giới hạn suất, nuôi dưỡng lòng từ và trân quý từng thực khách.">
    <meta name="twitter:image" content="<?= htmlspecialchars($previewImageUrl, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Google Fonts: Lora (Tiêu đề thiền vị) & Quicksand (Nội dung mộc mạc) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-container {
            width: 100%;
            max-width: 480px;
            padding: 20px 16px 64px 16px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin: 0 auto;
        }

        /* Thanh điều hướng tối giản */
        .zen-nav-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            background: #FAF7F2;
            border: 1px solid var(--brand-border-soft);
            border-radius: 999px;
            font-size: 0.76rem;
            margin-bottom: 6px;
        }
        .zen-nav-link {
            text-decoration: none;
            color: var(--brand-tea-dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.15s ease;
        }
        .zen-nav-link:hover {
            color: var(--brand-tea-green);
        }

        /* Thẻ nội dung phong cách Giấy Washi */
        .story-card {
            background: #FFFFFF;
            border: 1px solid var(--brand-border-soft);
            border-radius: 22px;
            padding: 24px 20px;
            box-shadow: 0 4px 18px rgba(91, 112, 101, 0.05);
            display: flex;
            flex-direction: column;
            gap: 14px;
            line-height: 1.68;
            color: #344037;
            font-size: 0.88rem;
        }

        .story-title {
            font-family: 'Lora', serif;
            font-size: 1.22rem;
            font-weight: 600;
            color: var(--brand-tea-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            padding-bottom: 8px;
            border-bottom: 1px dashed #E5DCD2;
        }

        /* 4 Tôn chỉ cốt lõi (Zen Principles) */
        .principles-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .principle-item {
            background: #FAF7F2;
            border: 1px solid #EAE3D9;
            border-radius: 18px;
            padding: 16px 16px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            transition: transform 0.15s ease;
        }
        .principle-item:hover {
            transform: translateY(-2px);
        }
        .principle-icon {
            font-size: 1.6rem;
            line-height: 1;
            padding: 8px;
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E5DCD2;
            flex-shrink: 0;
        }
        .principle-content h3 {
            font-family: 'Lora', serif;
            font-size: 0.98rem;
            font-weight: 600;
            color: var(--brand-tea-dark);
            margin: 0 0 4px 0;
        }
        .principle-content p {
            margin: 0;
            font-size: 0.82rem;
            color: #4B5563;
            line-height: 1.55;
        }

        /* Lời ngỏ người đứng bếp */
        .chef-letter {
            background: linear-gradient(145deg, #FCFAF6 0%, #F5ECE1 100%);
            border: 1px solid #DDD0BF;
            border-radius: 20px;
            padding: 22px 18px;
            font-family: 'Lora', serif;
            font-style: italic;
            color: #2F2B28;
            position: relative;
            line-height: 1.7;
        }
        .chef-letter::before {
            content: "“";
            position: absolute;
            top: 4px;
            left: 12px;
            font-size: 3.8rem;
            line-height: 1;
            color: rgba(91, 112, 101, 0.14);
            pointer-events: none;
        }

        /* Thẻ không gian */
        .space-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #EEF2E6;
            color: var(--brand-tea-dark);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <main class="page-container">

        <!-- Thanh điều hướng đầu trang -->
        <nav class="zen-nav-bar">
            <a href="index.php" class="zen-nav-link">
                🍲 Đặt Chỗ
            </a>
            <span style="color: #D1D5DB;">•</span>
            <span style="font-weight: 700; color: var(--brand-tea-green);">
                🌿 Về Bếp An Yên
            </span>
            <span style="color: #D1D5DB;">•</span>
            <a href="reviews.php" class="zen-nav-link">
                🌸 Cảm Nhận
            </a>
        </nav>

        <!-- Header Banner Lời chào Thiền vị -->
        <header style="text-align: center; padding: 12px 0 6px 0;">
            <div style="font-size: 2.4rem; margin-bottom: 6px;">🍵</div>
            <h1 class="font-serif-zen" style="font-size: 1.65rem; color: var(--brand-tea-dark); margin-bottom: 6px;">
                Bếp Chay An Yên
            </h1>
            <p style="font-size: 0.88rem; color: var(--brand-tea-green); font-family: 'Lora', serif; font-style: italic; max-width: 360px; margin: 0 auto;">
                "Một bát canh thanh ngọt, vỗ về những bộn bề ngày thường"
            </p>
        </header>

        <!-- KHỐI 1: NGUỒN GỐC & SỰ HÌNH THÀNH -->
        <section class="story-card">
            <h2 class="story-title">
                <span>🌱</span> Khởi Nguồn & Sự Hình Thành
            </h2>
            <p>
                <strong>Bếp Chay An Yên</strong> được nhen nhóm từ một gian bếp nhỏ nép mình trong con ngõ tĩnh lặng giữa lòng thành phố tất bật. Những người sáng lập bếp là những người con tìm về với nếp sống thuần thực vật, thấu hiểu cảm giác thèm một bữa ăn thanh lành sau những chuỗi ngày làm việc căng thẳng.
            </p>
            <p>
                Giữa nhịp sống hiện đại, khi bữa ăn ngoài hàng quán thường quá đậm vị bởi bột ngọt, hạt nêm hóa học và thức ăn nhanh chế biến sẵn, Bếp Chay An Yên ra đời như một <em>chốn quay về an yên</em> — nơi bạn có thể thong thả ngồi xuống, hít hà hương nấm thơm lừng và nhâm nhi một chén trà sen ngọt hậu.
            </p>
            <div style="background: #FAF7F2; padding: 12px 14px; border-radius: 12px; font-size: 0.82rem; border-left: 3px solid var(--brand-tea-green); font-style: italic;">
                "Bếp không mong trở thành một nhà hàng công nghiệp tấp nập, mà mong là một gian bếp ấm cúng của người thân, nơi luôn chừa sẵn một phần cơm nóng cho bạn khi trở về."
            </div>
        </section>

        <!-- KHỐI 2: 4 TÔN CHỈ CỦA BẾP (THE 4 ZEN PRINCIPLES) -->
        <section class="story-card">
            <h2 class="story-title">
                <span>🌸</span> Bốn Tôn Chỉ Ẩm Thực Cốt Lõi
            </h2>
            <div class="principles-grid">
                <!-- Tôn chỉ 1 -->
                <div class="principle-item">
                    <div class="principle-icon">🌿</div>
                    <div class="principle-content">
                        <h3>1. Thuần Khiết Tự Nhiên</h3>
                        <p>
                            100% nguyên liệu có nguồn gốc thảo mộc tươi trong ngày. Tuyệt đối <strong>không sử dụng bột ngọt, mì chính, chất bảo quản hay phụ gia công nghiệp</strong>. Vị ngọt thanh của nước dùng được ninh kỳ công hơn 4 giờ từ củ cải trắng, bắp ngọt non, mía lau và các loại nấm rừng.
                        </p>
                    </div>
                </div>

                <!-- Tôn chỉ 2 -->
                <div class="principle-item">
                    <div class="principle-icon">⏱️</div>
                    <div class="principle-content">
                        <h3>2. Nấu Theo Ca & Giới Hạn Suất (Slot Pre-order)</h3>
                        <p>
                            Bếp không nhận khách dồn dập vượt quá khả năng phục vụ. Mỗi ca ăn chỉ nhận đúng số lượng suất ăn tối đa (`max_capacity`) đã đặt trước. Nhờ vậy, từng tô phở, đĩa bún khi dọn ra bàn luôn đạt độ tươi mới, nóng hổi và trọn vẹn sự chăm chút của người nấu.
                        </p>
                    </div>
                </div>

                <!-- Tôn chỉ 3 -->
                <div class="principle-item">
                    <div class="principle-icon">🕊️</div>
                    <div class="principle-content">
                        <h3>3. Nuôi Dưỡng Lòng Từ & Chậm Lại</h3>
                        <p>
                            Mỗi bữa ăn thuần thực vật không chỉ thanh lọc thân thể mà còn là hành động thực tập yêu thương muôn loài và trân quý đất mẹ. Ăn chậm, nhai kỹ, cảm nhận trọn vẹn hương vị rau củ để tìm lại sự bình yên ngay trong hiện tại.
                        </p>
                    </div>
                </div>

                <!-- Tôn chỉ 4 -->
                <div class="principle-item">
                    <div class="principle-icon">🍵</div>
                    <div class="principle-content">
                        <h3>4. Đón Tiếp Như Người Thân Quen Trở Về</h3>
                        <p>
                            Bếp luôn ghi nhớ thói quen và khẩu vị của những thực khách đã gắn bó. Khách quen được tự động nhận diện với sự tin cậy tuyệt đối, có thể thanh toán sau khi dùng bữa và luôn nhận được sự tiếp đón nồng hậu như người nhà.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- KHỐI 3: KHÔNG GIAN & TRẢI NGHIỆM TẠI BẾP -->
        <section class="story-card">
            <h2 class="story-title">
                <span>🏡</span> Không Gian & Phục Vụ
            </h2>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="space-tag">🍃 Ghé Bếp Dùng Bữa (`dine_in`)</span>
                <span class="space-tag">📦 Gửi Ship Tận Nơi (`takeaway`)</span>
                <span class="space-tag">🍲 Mâm Cỗ Chay Trọn Vị</span>
            </div>
            <p>
                Tại quán, bàn ghế gỗ mộc mạc, ánh đèn vàng ấm áp và tiếng nhạc thiền nhè nhẹ hòa cùng tiếng chuông gió bên hiên. Nếu bạn bận rộn không thể ghé trực tiếp, Bếp hỗ trợ đóng gói cẩn thận bằng hộp giấy và vật liệu thân thiện môi trường để món ăn gửi tới tay bạn vẫn thơm ngát và ấm nóng.
            </p>
        </section>

        <!-- KHỐI 4: LỜI NGỎ TỪ NGƯỜI ĐỨNG BẾP -->
        <section class="chef-letter">
            <p style="margin: 0 0 10px 0; font-size: 0.94rem; line-height: 1.7;">
                "Nấu một bữa ăn chay đối với chúng mình không phải là một công việc đơn thuần, mà là một thời thiền định. Khi nhặt từng nhánh ngò, thái từng lát nấm hay canh lửa cho nồi nước dùng, tâm mình phải thật tĩnh thì món ăn mới toát lên được vị an lành.
            </p>
            <p style="margin: 0; font-size: 0.94rem; line-height: 1.7;">
                Cảm ơn bạn đã hữu duyên ghé qua gian bếp nhỏ. Chúc bạn và gia đình luôn dồi dào sức khỏe, tâm an và vạn sự cát tường!"
            </p>
            <div style="text-align: right; margin-top: 14px; font-weight: 600; font-style: normal; font-size: 0.88rem; color: var(--brand-tea-dark);">
                — Người Đứng Bếp An Yên —
            </div>
        </section>

        <!-- KHỐI 5: NÚT HÀNH ĐỘNG (CALL TO ACTION) -->
        <section class="zen-card" style="text-align: center; padding: 22px 18px; background: #FAF7F2; border: 1.5px solid var(--brand-border-soft);">
            <div style="font-size: 1.8rem; margin-bottom: 6px;">🍲</div>
            <h3 class="font-serif-zen" style="font-size: 1.08rem; color: var(--brand-tea-dark); margin-bottom: 6px;">
                Mời Bạn Ghé Bếp Dùng Bữa
            </h3>
            <p style="font-size: 0.8rem; color: var(--text-muted-warm); margin-bottom: 14px;">
                Chọn khung giờ phù hợp và đặt món trước để Bếp chuẩn bị chu đáo nhất bạn nhé!
            </p>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <a href="index.php" class="zen-btn-primary" style="text-decoration: none; padding: 12px; font-size: 0.88rem; border-radius: 12px; display: block;">
                    Xem Thực Đơn & Đặt Suất Trước ➔
                </a>
                <a href="reviews.php" class="action-btn" style="text-decoration: none; padding: 10px; font-size: 0.82rem; border-radius: 12px; display: block; text-align: center;">
                    Đọc Góc Cảm Nhận & Kỷ Niệm 🌸
                </a>
            </div>
        </section>

        <!-- Footer -->
        <footer style="text-align: center; padding-top: 10px; font-size: 0.75rem; color: var(--text-muted-warm); line-height: 1.6;">
            <div style="display: flex; justify-content: center; gap: 12px; margin-bottom: 6px;">
                <a href="index.php" style="color: var(--brand-tea-green); text-decoration: none;">Trang Chủ Đặt Món</a>
                <span>•</span>
                <a href="reviews.php" style="color: var(--brand-tea-green); text-decoration: none;">Cảm Nhận & Kỷ Niệm</a>
                <span>•</span>
                <a href="admin.php" style="color: #9CA3AF; text-decoration: none;">Quản Trị Bếp</a>
            </div>
            <p style="margin: 0;"><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($sellerAddress, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin: 2px 0 0 0;">Bình an trong từng bữa ăn thanh khiết</p>
        </footer>

    </main>

</body>
</html>
