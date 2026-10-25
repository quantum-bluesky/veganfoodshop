<?php
declare(strict_types=1);

namespace App\Services;

class VietQrService
{
    private string $bankId;
    private string $accountNo;
    private string $accountName;
    private string $template;

    public function __construct(
        string $bankId = '970422',
        string $accountNo = '0987654321',
        string $accountName = 'BEP CHAY AN YEN',
        string $template = 'compact2'
    ) {
        $this->bankId = $bankId;
        $this->accountNo = $accountNo;
        $this->accountName = $accountName;
        $this->template = $template;
    }

    /**
     * Sinh URL mã QR chuyển khoản VietQR chuẩn Napas
     */
    public function generateQrUrl(int $amount, string $orderCode): string
    {
        $cleanBankId = urlencode($this->bankId);
        $cleanAccountNo = urlencode($this->accountNo);
        $cleanTemplate = urlencode($this->template);

        $params = [
            'amount' => max(0, $amount),
            'addInfo' => $orderCode,
            'accountName' => $this->accountName,
        ];

        return sprintf(
            'https://img.vietqr.io/image/%s-%s-%s.png?%s',
            $cleanBankId,
            $cleanAccountNo,
            $cleanTemplate,
            http_build_query($params)
        );
    }

    /**
     * Sinh Universal Deeplink VietQR / Napas247 mở thẳng app ngân hàng trên điện thoại
     */
    public function generateUniversalLink(int $amount, string $orderCode): string
    {
        $params = [
            'bank' => $this->bankId,
            'acc' => $this->accountNo,
            'amount' => max(0, $amount),
            'des' => $orderCode,
        ];
        return 'https://dl.vietqr.io/pay?' . http_build_query($params);
    }

    /**
     * Danh sách các ứng dụng ngân hàng phổ biến kèm custom App Schemes & Universal Link
     */
    public function getPopularBankingApps(int $amount, string $orderCode): array
    {
        $cleanAmount = max(0, $amount);
        $cleanOrderCode = urlencode($orderCode);
        $acc = urlencode($this->accountNo);
        $bank = urlencode($this->bankId);
        $universal = $this->generateUniversalLink($amount, $orderCode);

        return [
            [
                'code' => 'vietqr',
                'name' => 'VietQR / Napas',
                'short_name' => 'Mở App Bất Kỳ',
                'color' => '#166534',
                'bg' => '#DCFCE7',
                'icon' => '⚡',
                'url' => $universal,
            ],
            [
                'code' => 'vcb',
                'name' => 'Vietcombank',
                'short_name' => 'VCB Digibank',
                'color' => '#005D30',
                'bg' => '#E6F4EA',
                'icon' => '🏦',
                'url' => "vcb://qr/transfer?bank={$bank}&acc={$acc}&amount={$cleanAmount}&des={$cleanOrderCode}",
                'fallback_url' => $universal,
            ],
            [
                'code' => 'mb',
                'name' => 'MBBank',
                'short_name' => 'MB Bank',
                'color' => '#1E40AF',
                'bg' => '#EFF6FF',
                'icon' => '⭐',
                'url' => "mbmobile://transfer?bank={$bank}&acc={$acc}&amount={$cleanAmount}&des={$cleanOrderCode}",
                'fallback_url' => $universal,
            ],
            [
                'code' => 'tcb',
                'name' => 'Techcombank',
                'short_name' => 'Techcombank',
                'color' => '#991B1B',
                'bg' => '#FEF2F2',
                'icon' => '🔴',
                'url' => "tcb://transfer?bank={$bank}&acc={$acc}&amount={$cleanAmount}&des={$cleanOrderCode}",
                'fallback_url' => $universal,
            ],
            [
                'code' => 'bidv',
                'name' => 'BIDV',
                'short_name' => 'SmartBanking',
                'color' => '#075985',
                'bg' => '#F0F9FF',
                'icon' => '💎',
                'url' => "bidvsmartbanking://qr/transfer?bank={$bank}&acc={$acc}&amount={$cleanAmount}&des={$cleanOrderCode}",
                'fallback_url' => $universal,
            ],
            [
                'code' => 'momo',
                'name' => 'Ví MoMo',
                'short_name' => 'Ví MoMo',
                'color' => '#831843',
                'bg' => '#FDF2F8',
                'icon' => '👛',
                'url' => "momo://transfer?bank={$bank}&acc={$acc}&amount={$cleanAmount}&des={$cleanOrderCode}",
                'fallback_url' => $universal,
            ],
        ];
    }

    public function getBankId(): string
    {
        return $this->bankId;
    }

    public function getAccountNo(): string
    {
        return $this->accountNo;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }
}
