<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CustomerService;

class CustomerServiceTest extends TestCase
{
    public function runAllTests(): void
    {
        $this->setUp();
        $this->testTrustedCustomerRecognition();
        $this->tearDown();

        $this->setUp();
        $this->testNewCustomerRecognition();
        $this->tearDown();

        $this->setUp();
        $this->testCustomerPhoneNormalization();
        $this->tearDown();

        $this->setUp();
        $this->testZaloCustomerIntegration();
        $this->tearDown();
    }

    /**
     * TC-UNIT-01: Nhận diện khách quen qua SĐT (is_trusted == 1)
     */
    public function testTrustedCustomerRecognition(): void
    {
        $service = new CustomerService($this->db);
        $customer = $service->findByPhone('0912345678');

        $this->assertTrue($customer !== null, 'Khách quen phải được tìm thấy');
        $this->assertEquals('Chị Lan', $customer['name'], 'Tên khách quen phải trùng khớp');
        $this->assertEquals(1, (int)$customer['is_trusted'], 'Khách quen phải có is_trusted = 1');
        $this->assertTrue($service->isTrusted('0912345678'), 'isTrusted() phải trả về true');
    }

    /**
     * TC-UNIT-02: Khách mới chưa tin cậy (is_trusted == 0)
     */
    public function testNewCustomerRecognition(): void
    {
        $service = new CustomerService($this->db);
        $customer = $service->findByPhone('0987654321');

        $this->assertTrue($customer !== null, 'Khách mới tìm thấy trong DB');
        $this->assertEquals(0, (int)$customer['is_trusted'], 'Khách mới chưa có is_trusted');
        $this->assertFalse($service->isTrusted('0987654321'), 'isTrusted() phải trả về false');

        // Số điện thoại hoàn toàn chưa có trong DB
        $unknown = $service->findByPhone('0999888777');
        $this->assertEquals(null, $unknown, 'Số chưa đặt phải trả về null');
        $this->assertFalse($service->isTrusted('0999888777'), 'Số lạ isTrusted() phải trả về false');
    }

    /**
     * Kiểm tra chuẩn hóa định dạng số điện thoại
     */
    public function testCustomerPhoneNormalization(): void
    {
        $service = new CustomerService($this->db);
        $clean = $service->normalizePhone(' 0912.345.678 ');
        $this->assertEquals('0912345678', $clean, 'Chuẩn hóa loại bỏ khoảng trắng và dấu chấm');

        $customer = $service->findByPhone('0912-345-678');
        $this->assertTrue($customer !== null, 'Tìm kiếm thành công kể cả khi có dấu gạch ngang');
    }

    /**
     * TC-UNIT-04: Lưu trữ và tìm kiếm khách hàng bằng Zalo ID
     */
    public function testZaloCustomerIntegration(): void
    {
        $service = new CustomerService($this->db);

        // Lưu khách hàng kèm zalo_id và avatar_url
        $customer = $service->saveOrUpdate(
            '0933112233',
            'Thanh Trúc',
            null,
            'zalo_user_999',
            'https://example.com/avatar.jpg'
        );

        $this->assertEquals('0933112233', $customer['phone']);
        $this->assertEquals('Thanh Trúc', $customer['name']);
        $this->assertEquals('zalo_user_999', $customer['zalo_id']);
        $this->assertEquals('https://example.com/avatar.jpg', $customer['avatar_url']);

        // Tìm bằng Zalo ID
        $byZalo = $service->findByZaloId('zalo_user_999');
        $this->assertTrue($byZalo !== null, 'Phải tìm thấy khách hàng bằng zalo_id');
        $this->assertEquals('0933112233', $byZalo['phone']);

        // Upsert cập nhật thông tin qua Zalo
        $upserted = $service->upsertZaloCustomer(
            'zalo_user_999',
            'Thanh Trúc (Đã đổi tên)',
            '0933112233',
            'https://example.com/new_avatar.jpg'
        );
        $this->assertEquals('Thanh Trúc (Đã đổi tên)', $upserted['name']);
        $this->assertEquals('https://example.com/new_avatar.jpg', $upserted['avatar_url']);
    }
}

