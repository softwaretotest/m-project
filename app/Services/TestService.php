<?php

namespace App\Services;

use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;

class TestService
{
    public static function runProductTest()
    {
        // 1. เรียก ProductController
        $controller = new ProductController();

        // 2. ทดสอบ Index (ดึงข้อมูลสินค้าทั้งหมด)
        // ส่ง Request เปล่าไปเพราะ ProductController ของคุณใช้ดึงข้อมูลพื้นฐาน
        $response = $controller->index(new Request());

        $result = json_decode($response->getContent(), true);

        return [
            'status' => 'success',
            'message' => 'ทดสอบ ProductController สำเร็จ',
            'data_count' => isset($result['data']) ? count($result['data']) : 0,
            'data' => $result['data'] ?? []
        ];
    }
}
