<?php

namespace App\Services;

/**
 * set target path for m-project to do admin on the target app e.g. ecommerce, blog, etc.
 */
class TargetManager
{
    // ตั้งค่าโปรเจกต์ปลายทางที่ต้องการให้ Engine ทำงานด้วย
    protected static $targets = [
        'ecommerce' => [
            'root_path' => 'C:/Users/o/vscode/react/ecommerce',
        ],
        // ในอนาคตคุณสามารถเพิ่มโปรเจกต์อื่นๆ เข้ามาตรงนี้ได้เลย
    ];

    protected static $activeTarget = 'ecommerce';

    /**
     * ดึง Path ของโปรเจกต์ปลายทางแบบ Dynamic
     */
    public static function getPath($subDir = '')
    {
        $base = self::$targets[self::$activeTarget]['root_path'];
        $path = $base . ($subDir ? '/' . $subDir : '');

        // ตรวจสอบและสร้างโฟลเดอร์ถ้าไม่มีอยู่จริง
        if (!is_dir($path) && str_contains($subDir, '/')) {
            mkdir($path, 0777, true);
        }

        return $path;
    }

    /**
     * สลับโปรเจกต์ปลายทาง (ถ้าต้องการเปลี่ยนไปทำโปรเจกต์อื่น)
     */
    public static function setTarget($targetName)
    {
        if (isset(self::$targets[$targetName])) {
            self::$activeTarget = $targetName;
            return true;
        }
        return false;
    }
}
