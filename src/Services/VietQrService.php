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
