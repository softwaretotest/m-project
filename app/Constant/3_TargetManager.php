<?php

namespace App\Constant;

use Psy\Readline\Hoa\Console;

/**
 * set target path for m-project to do admin on the target app e.g. ecommerce, blog, etc.
 */
class TargetManager
{
    public static $targets = [];
    private static $activeTarget = '';

    // Guard to prevent endless loop Logger <-> TargetManager
    private static bool $configLoaded = false;

    const CONFIG_FILE = __DIR__ . '/3_M-Config.json';

    /**
     * getter เงียบ ไม่อ่านไฟล์ ไม่ log — ไว้ให้ Logger ใช้โดยเฉพาะ 
     */
    public static function peek_activeTarget(): string
    {
        return self::$activeTarget;
    }

    public static function get_activeTarget(): string
    {
        // Case Dev run e.g. M_Sync on vscode
        // 🛡️ 0. guard prevent endless loop between Logger <-> TargetManager
        if (self::$configLoaded) {
            return self::$activeTarget;
        }

        // 🛡️ 0. check and set has tried loading config (although maybe config not found)
        self::$configLoaded = true;

        // 1. Warning if not config
        if (!file_exists(self::CONFIG_FILE)) {
            // Logger::warning("M-Config file not found at: " . self::CONFIG_FILE);
            self::$activeTarget = '';
            self::$targets = [];

            return self::$activeTarget;
        }

        // 2. get config data from JSON
        $jsonData = json_decode(file_get_contents(self::CONFIG_FILE), true);

        // 3. throw Error if wrong jsonData 
        if (!isset($jsonData['activeTarget']) || !isset($jsonData['targets'])) {
            // Logger::error("Invalid Config format in: " . self::CONFIG_FILE);
            self::$activeTarget = '';
            self::$targets = [];

            return self::$activeTarget;
        }

        // 4. save data to runtime vars
        self::$activeTarget = $jsonData['activeTarget'];
        self::$targets = $jsonData['targets'];

        return self::$activeTarget;
    }

    /**
     * * generate target app path (e.g. ecommerce) to put generated files on it, e.g.
     * @param $path = migrations/2026_09_20_162853_01_create_orders_table.php
     * @param $baseFolder = database
     * @return path to target app_name_root/app or /any_defined_foldername
     */
    public static function gen_path($path = '', $baseFolder = 'app'): string
    {
        // ถ้ายังไม่มี activeTarget ให้คืนค่าพาธสำรองหรือหยุดเตือนทันที
        if (empty(self::$activeTarget) || !isset(self::$targets[self::$activeTarget])) {
            // ดึงค่าล่าสุดมาก่อนเผื่อยังไม่ได้โหลด
            self::get_activeTarget();

            if (empty(self::$activeTarget) || !isset(self::$targets[self::$activeTarget])) {
                return base_path($path); // คืนค่าพาธหลักของ m-project กันพัง
            }
        }

        $basePath = self::$targets[self::$activeTarget]['root_path'];
        return $basePath . '/' . $baseFolder . '/' . ltrim($path, '/');
    }

    /**
     * root path ของ target app ที่ active อยู่
     *
     * @return string e.g. 'C:/Users/o/.vscode/react/ecommerce'
     */
    public static function root(): string
    {
        return self::$targets[self::$activeTarget]['root_path'];
    }

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
    //  * change target app (if you want to manage other project metadata)
    //  */
    // public static function setTarget($targetName)
    // {
    //     if (isset(self::$targets[$targetName])) {
    //         self::$activeTarget = $targetName;
    //         return true;
    //     }
    //     return false;
    // }

}
