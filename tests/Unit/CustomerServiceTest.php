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
}
