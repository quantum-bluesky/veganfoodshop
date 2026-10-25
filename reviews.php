<?php
declare(strict_types=1);

/**
 * Trang Đánh Giá & Cảm Nhận Của Khách Hàng (Issue #18)
 * VeganFoodShop - Bếp Chay An Yên
 * UTF-8 No BOM, chuẩn PHP 8.4+
 */

require_once __DIR__ . '/src/autoload.php';
$config = require __DIR__ . '/config/config.php';

use App\Database\Connection;
use App\Services\ConfigService;
use App\Services\ReviewService;
use App\Services\CustomerService;
use App\Services\ZaloAuthService;
use App\Exceptions\ValidationException;

$db = Connection::getInstance();
$configService = new ConfigService($db);
$reviewService = new ReviewService($db, $configService);
$customerService = new CustomerService($db);

$zaloService = new ZaloAuthService(
    $db,
    $configService,
    $customerService,
    $config['zalo_login'] ?? []
);

// Trạng thái bật/tắt của tính năng
$isReviewsEnabled = $reviewService->isReviewsEnabled();

// Lấy thông tin khách đã đăng nhập Zalo (nếu có)
$currentCustomer = $zaloService->getCurrentCustomer();

$successMessage = null;
$errorMessage = null;

// Xử lý gửi đánh giá
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    try {
        $result = $reviewService->createReview($_POST);
        $successMessage = $result['message'];
    } catch (ValidationException $e) {
        $errorMessage = $e->getMessage();
    } catch (\Throwable $e) {
        $errorMessage = 'Có lỗi xảy ra trong quá trình gửi đánh giá. Xin bạn thử lại nhé!';
    }
}

// Lấy dữ liệu đánh giá và tổng kết sao
$reviewSummary = $reviewService->getReviewSummary();
$approvedReviews = $reviewService->getApprovedReviews(30);

// Mã đơn hàng truyền qua URL (ví dụ từ trang thành công hoặc link SMS/Zalo)
$prefillOrderCode = trim((string)($_GET['order_code'] ?? ''));
$prefillPhone = $currentCustomer['phone'] ?? trim((string)($_GET['phone'] ?? ''));
$prefillName = $currentCustomer['name'] ?? trim((string)($_GET['name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Góc Cảm Nhận & Đánh Giá - Bếp Chay An Yên</title>
    
    <!-- Google Fonts: Lora (Tiêu đề thiền vị) & Quicksand (Nội dung mộc mạc) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,500;0,600;1,400&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-container {
            width: 100%;
            max-width: 480px;
            padding: 24px 16px 60px 16px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin: 0 auto;
        }

        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 8px;
            font-size: 1.8rem;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            cursor: pointer;
            color: #D1D5DB;
            transition: color 0.15s ease, transform 0.15s ease;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #F59E0B;
        }
        .star-rating label:hover {
            transform: scale(1.15);
        }

        .rating-bar-container {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-muted-warm);
        }
        .rating-bar {
            flex: 1;
            height: 6px;
            background: #E5E7EB;
            border-radius: 999px;
            overflow: hidden;
        }
        .rating-fill {
            height: 100%;
            background: #F59E0B;
            border-radius: 999px;
        }

        .review-card {
            background: #FFFFFF;
            border: 1px solid var(--brand-border-soft);
            border-radius: 18px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(91, 112, 101, 0.04);
        }

        .kitchen-reply-box {
            background: #FBF9F5;
            border-left: 3px solid var(--brand-tea-green);
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            color: var(--brand-tea-dark);
            margin-top: 4px;
        }
    </style>
</head>
<body>

    <main class="page-container">

        <!-- Header lời ngỏ -->
        <header style="text-align: center; padding: 10px 0 6px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <a href="index.php" class="action-btn" style="text-decoration: none; font-size: 0.78rem; border-radius: 999px; padding: 5px 12px;">
                    ← Về Đặt Chỗ
                </a>
                <span style="font-size: 0.75rem; color: var(--text-muted-warm); font-weight: 600;">
                    Bếp Chay An Yên
                </span>
            </div>

            <div style="font-size: 2.2rem; margin-bottom: 4px;">🌸</div>
            <h1 class="font-serif-zen" style="font-size: 1.55rem; color: var(--brand-tea-dark); margin-bottom: 4px;">
                Góc Cảm Nhận & Sẻ Chia
            </h1>
            <p style="font-size: 0.82rem; color: var(--text-muted-warm); line-height: 1.4;">
                Mỗi góp ý chân thành của bạn là một hạt giống an lành giúp Bếp hoàn thiện từng món chay thanh khiết.
            </p>
        </header>

        <!-- Thông báo thao tác -->
        <?php if ($successMessage): ?>
            <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 12px 16px; border-radius: 14px; font-size: 0.82rem; line-height: 1.4;">
                🌸 <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; padding: 12px 16px; border-radius: 14px; font-size: 0.82rem; line-height: 1.4;">
                ⚠️ <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!$isReviewsEnabled): ?>
            <!-- Trạng thái CHƯA BẬT (Feature Flag = OFF) -->
            <div class="zen-card" style="text-align: center; padding: 32px 20px; background: #FAF7F2; border: 1.5px dashed var(--brand-border-soft);">
                <div style="font-size: 2.4rem; margin-bottom: 10px;">🍃</div>
                <h2 class="font-serif-zen" style="font-size: 1.15rem; color: var(--brand-tea-dark); margin-bottom: 8px;">
                    Trang Cảm Nhận Sẽ Sớm Mở Cửa
                </h2>
                <p style="font-size: 0.82rem; color: var(--text-muted-warm); line-height: 1.5; margin-bottom: 18px;">
                    Hiện tại Bếp Chay An Yên đang chuẩn bị những mẻ nấm tươi và hoàn thiện thực đơn ấm cúng. Góc đánh giá sẽ được mở trong thời gian tới để lắng nghe trải nghiệm của bạn.
                </p>
                <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                    <a href="index.php" class="zen-btn-primary" style="text-decoration: none; display: inline-block; padding: 10px 20px; font-size: 0.85rem; border-radius: 12px;">
                        Ghé Xem Thực Đơn & Đặt Chỗ 🍲
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Trạng thái ĐÃ BẬT (Feature Flag = ON) -->

            <!-- Thống kê điểm số tổng quan -->
            <section class="zen-card" style="padding: 16px 18px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="text-align: center; min-width: 80px;">
                        <div style="font-size: 2.2rem; font-weight: 700; color: #D97706; line-height: 1;">
                            <?= number_format($reviewSummary['average_rating'], 1) ?>
                        </div>
                        <div style="color: #F59E0B; font-size: 0.85rem; margin-top: 2px;">
                            ★★★★★
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted-warm); margin-top: 4px;">
                            <?= (int)$reviewSummary['total_count'] ?> cảm nhận
                        </div>
                    </div>
                    <div style="flex: 1; display: flex; flex-direction: column; gap: 4px;">
                        <?php for ($star = 5; $star >= 1; $star--): ?>
                            <?php 
                            $cnt = $reviewSummary['breakdown'][$star] ?? 0;
                            $pct = $reviewSummary['total_count'] > 0 ? round(($cnt / $reviewSummary['total_count']) * 100) : 0;
                            ?>
                            <div class="rating-bar-container">
                                <span style="min-width: 16px;"><?= $star ?>★</span>
                                <div class="rating-bar">
                                    <div class="rating-fill" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <span style="min-width: 24px; text-align: right;"><?= $cnt ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </section>

            <!-- Form gửi đánh giá -->
            <section class="zen-card">
                <h2 class="font-serif-zen" style="font-size: 1.05rem; color: var(--brand-tea-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <span>✍️</span> Gửi Lời Nhắn Đến Bếp
                </h2>

                <form method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                    <input type="hidden" name="action" value="submit_review">

                    <!-- Chọn sao -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px; font-weight: 600;">
                            Mức độ hài lòng của bạn:
                        </label>
                        <div class="star-rating">
                            <input type="radio" id="star5" name="rating" value="5" checked><label for="star5" title="Tuyệt vời & An yên">★</label>
                            <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="Rất ngon lành">★</label>
                            <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="Hài lòng">★</label>
                            <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="Bình thường">★</label>
                            <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="Cần cải thiện">★</label>
                        </div>
                    </div>

                    <!-- Tên khách -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">
                            Tên hoặc biệt danh của bạn: <span style="color:#DC2626;">*</span>
                        </label>
                        <input type="text" name="customer_name" class="zen-input" value="<?= htmlspecialchars($prefillName, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ví dụ: Bạn An, Diệu Hương..." required>
                    </div>

                    <!-- Số điện thoại (tùy chọn) -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">
                            Số điện thoại (để nhận diện khách quen / bảo mật):
                        </label>
                        <input type="tel" name="customer_phone" class="zen-input" value="<?= htmlspecialchars($prefillPhone, ENT_QUOTES, 'UTF-8') ?>" placeholder="09xxxxxxxx (Bếp cam kết không công khai số)">
                    </div>

                    <!-- Mã đơn hàng (tùy chọn) -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">
                            Mã đơn hàng bạn đã đặt (tùy chọn):
                        </label>
                        <input type="text" name="order_code" class="zen-input" value="<?= htmlspecialchars($prefillOrderCode, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ví dụ: AY-20261025-0001 (được huy hiệu Đã dùng bữa)">
                    </div>

                    <!-- Nội dung cảm nhận -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">
                            Đôi dòng cảm nhận cùng Bếp: <span style="color:#DC2626;">*</span>
                        </label>
                        <textarea name="comment" class="zen-input" rows="4" placeholder="Hương vị món ăn, độ đậm đà của nước dùng hay cảm xúc an lành khi bạn ghé quán..." required style="resize: vertical;"></textarea>
                    </div>

                    <button type="submit" class="zen-btn-primary" style="padding: 12px; border-radius: 12px; font-size: 0.88rem; margin-top: 4px;">
                        Gửi Cảm Nhận Đến Bếp 🌸
                    </button>
                </form>
            </section>

        <?php endif; ?>

        <!-- Danh sách các cảm nhận đã duyệt -->
        <section style="display: flex; flex-direction: column; gap: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 4px;">
                <h3 class="font-serif-zen" style="font-size: 1rem; color: var(--brand-tea-dark);">
                    Cảm Nhận Từ Khách Thương (<?= count($approvedReviews) ?>)
                </h3>
            </div>

            <?php if (empty($approvedReviews)): ?>
                <div class="zen-card" style="text-align: center; padding: 28px; color: var(--text-muted-warm); font-size: 0.82rem;">
                    Bếp đang chờ đón những dòng cảm nhận đầu tiên từ bạn. Chúc bạn một ngày thanh tịnh! 🍃
                </div>
            <?php else: ?>
                <?php foreach ($approvedReviews as $rev): ?>
                    <article class="review-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <strong style="font-size: 0.88rem; color: var(--brand-tea-dark);">
                                    <?= htmlspecialchars($rev['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <?php if (!empty($rev['is_verified_buyer'])): ?>
                                    <span style="background: #ECFDF5; color: #047857; font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; font-weight: 700; margin-left: 4px;">
                                        ✓ Đã dùng bữa
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="color: #F59E0B; font-size: 0.78rem;">
                                <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5 - (int)$rev['rating']) ?>
                            </div>
                        </div>

                        <?php if (!empty($rev['title'])): ?>
                            <div style="font-weight: 600; font-size: 0.82rem; color: var(--brand-tea-green);">
                                <?= htmlspecialchars($rev['title'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <p style="font-size: 0.8rem; color: #374151; line-height: 1.45; margin: 2px 0;">
                            <?= nl2br(htmlspecialchars($rev['comment'], ENT_QUOTES, 'UTF-8')) ?>
                        </p>

                        <div style="font-size: 0.68rem; color: var(--text-muted-warm); display: flex; justify-content: space-between;">
                            <span><?= date('d/m/Y', strtotime($rev['created_at'])) ?></span>
                            <?php if (!empty($rev['order_code'])): ?>
                                <span>Đơn #<?= htmlspecialchars($rev['order_code'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($rev['kitchen_reply'])): ?>
                            <div class="kitchen-reply-box">
                                <div style="font-weight: 700; color: var(--brand-tea-green); margin-bottom: 2px; font-size: 0.75rem;">
                                    🌿 Bếp Chay An Yên phản hồi:
                                </div>
                                <div>
                                    <?= nl2br(htmlspecialchars($rev['kitchen_reply'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Footer -->
        <footer style="text-align: center; padding-top: 10px; font-size: 0.75rem; color: var(--text-muted-warm);">
            Bếp Chay An Yên • Bình an trong từng bữa ăn
        </footer>

    </main>

</body>
</html>
