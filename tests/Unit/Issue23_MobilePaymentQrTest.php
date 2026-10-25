<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\VietQrService;

/**
 * Test Suite cho Giải pháp Thanh Toán Mobile QR (Issue Mobile Payment):
 * - Deeplink mở trực tiếp App Ngân Hàng
 * - Lưu ảnh QR vào máy & quét từ thư viện ảnh
 */
class Issue23_MobilePaymentQrTest
{
    private VietQrService $vietQrService;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->vietQrService = new VietQrService('970422', '0987654321', 'BEP CHAY AN YEN');
    }

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue: Thanh Toán Mobile QR (Deeplink & Tải Ảnh QR) ===\n";

        $this->testGenerateUniversalLink();
        $this->testGetPopularBankingApps();
        $this->testProcessOrderContainsMobilePaymentUI();

        echo "=> Kết quả Mobile Payment Test: {$this->passed} PASS, {$this->failed} FAIL\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Có {$this->failed} test thất bại trong Issue23_MobilePaymentQrTest!");
        }
    }

    private function testGenerateUniversalLink(): void
    {
        $amount = 90000;
        $orderCode = 'AY-20261025-0012';

        $link = $this->vietQrService->generateUniversalLink($amount, $orderCode);

        $this->assert(str_starts_with($link, 'https://dl.vietqr.io/pay?'), "Universal link có tiền tố https://dl.vietqr.io/pay?");
        $this->assert(str_contains($link, 'bank=970422'), "Universal link chứa đúng mã ngân hàng 970422");
        $this->assert(str_contains($link, 'acc=0987654321'), "Universal link chứa đúng số tài khoản");
        $this->assert(str_contains($link, 'amount=90000'), "Universal link chứa đúng số tiền 90000");
        $this->assert(str_contains($link, 'des=AY-20261025-0012'), "Universal link chứa đúng mã đơn hàng trong des");
    }

    private function testGetPopularBankingApps(): void
    {
        $amount = 150000;
        $orderCode = 'AY-20261025-0088';

        $apps = $this->vietQrService->getPopularBankingApps($amount, $orderCode);

        $this->assert(count($apps) >= 5, "Trả về danh sách >= 5 app ngân hàng phổ biến");

        $codes = array_column($apps, 'code');
        $this->assert(in_array('vietqr', $codes, true), "Có lựa chọn VietQR / Napas tự động");
        $this->assert(in_array('vcb', $codes, true), "Có app Vietcombank");
        $this->assert(in_array('mb', $codes, true), "Có app MBBank");
        $this->assert(in_array('tcb', $codes, true), "Có app Techcombank");
        $this->assert(in_array('momo', $codes, true), "Có app Ví MoMo");

        // Kiểm tra scheme VCB
        foreach ($apps as $a) {
            if ($a['code'] === 'vcb') {
                $this->assert(str_starts_with($a['url'], 'vcb://'), "VCB có scheme vcb://");
                $this->assert(str_contains($a['url'], 'amount=150000'), "VCB url có đúng amount");
            }
            if ($a['code'] === 'mb') {
                $this->assert(str_starts_with($a['url'], 'mbmobile://'), "MBBank có scheme mbmobile://");
            }
        }
    }

    private function testProcessOrderContainsMobilePaymentUI(): void
    {
        $content = file_get_contents(__DIR__ . '/../../process_order.php');

        // 1. Kiểm tra có nút Deeplink mở App Ngân Hàng
        $this->assert(str_contains($content, 'Mở Ứng Dụng Ngân Hàng Thanh Toán'), "process_order.php có nút Mở Ứng Dụng Ngân Hàng Thanh Toán");
        $this->assert(str_contains($content, '$universalDeeplink'), "process_order.php sử dụng biến \$universalDeeplink");

        // 2. Kiểm tra có nút Lưu ảnh QR vào máy
        $this->assert(str_contains($content, 'Lưu Ảnh QR Vào Máy (Để Quét Từ Thư Viện)'), "process_order.php có nút Lưu Ảnh QR Vào Máy");
        $this->assert(str_contains($content, 'downloadQRCode'), "process_order.php gọi hàm downloadQRCode");

        // 3. Kiểm tra có hướng dẫn 2 bước
        $this->assert(str_contains($content, 'Ảnh từ thư viện (Album)'), "process_order.php có hướng dẫn quét QR từ thư viện ảnh");

        // 4. Kiểm tra có hàm JS downloadQRCode
        $this->assert(str_contains($content, 'function downloadQRCode'), "process_order.php chứa định nghĩa hàm JS downloadQRCode");

        // 5. Kiểm tra có khay mở trực tiếp app ngân hàng
        $this->assert(str_contains($content, '$popularBankingApps'), "process_order.php render danh sách \$popularBankingApps");
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo "  [PASS] {$message}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$message}\n";
            $this->failed++;
        }
    }
}
