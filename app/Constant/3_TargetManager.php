<?php

namespace App\Constant;

/**
 * set target path for m-project to do admin on the target app e.g. ecommerce, blog, etc.
 */
class TargetManager
{
    /**
     * * generate target app path (e.g. ecommerce) to put generated files on it, e.g.
     * @param $path = migrations/2026_09_20_162853_01_create_orders_table.php
     * @param $baseFolder = database
     * @return C:/Users/o/.vscode/react/ecommerce/database/migrations/2026_09_20_162853_01_create_orders_table.php
     */
    public static function gen_path($path = '', $baseFolder = 'app'): string
    {
        $basePath = self::$targets[self::$activeTarget]['root_path'];
        return $basePath . '/' . $baseFolder . '/' . ltrim($path, '/');
    }

    // target path of app 
    public static $targets = [
        'ecommerce' => [
            'root_path' => 'C:/Users/o/.vscode/react/ecommerce',
        ],
    ];

    public static $activeTarget = 'ecommerce';

    /**
     * get Path of Dynamic
     */
    // public static function getPath($subDir = '')
    // {
    //     $base = self::$targets[self::$activeTarget]['root_path'];
    //     $path = $base . ($subDir ? '/' . $subDir : '');

    //     // ตรวจสอบและสร้างโฟลเดอร์ถ้าไม่มีอยู่จริง
    //     if (!is_dir($path) && str_contains($subDir, '/')) {
    //         mkdir($path, 0777, true);
    //     }

    //     return $path;
    // }

    // /**
    //  * สลับโปรเจกต์ปลายทาง (ถ้าต้องการเปลี่ยนไปทำโปรเจกต์อื่น)
    //  */
    // public static function setTarget($targetName)
    // {
    //     if (isset(self::$targets[$targetName])) {
    //         self::$activeTarget = $targetName;
    //         return true;
    //     }
    //     return false;
    // }

    /**
     * root path ของ target app ที่ active อยู่
     *
     * @return string e.g. 'C:/Users/o/.vscode/react/ecommerce'
     */
    public static function root(): string
    {
        return self::$targets[self::$activeTarget]['root_path'];
    }
}
