<?php

namespace App\Constant;

/**
 * set target path for m-project to do admin on the target app e.g. ecommerce, blog, etc.
 */
class TargetManager
{
    public const SYNC_TARGET_ENV = 'M_PROJECT_ACTIVE_TARGET';

    public static $targets = [];
    private static $activeTarget = '';

    // Guard to prevent endless loop Logger <-> TargetManager
    private static bool $configLoaded = false;

    public const CONFIG_FILE = __DIR__ . '/3_M-Config.json';

    /**
     * getter เงียบ ไม่อ่านไฟล์ ไม่ log — ไว้ให้ Logger ใช้โดยเฉพาะ
     */
    public static function peek_activeTarget(): string
    {
        return self::$activeTarget;
    }

    /**
     * Read the active target, honoring a valid target override for Sync Manager worker scripts.
     *
     * @return string Active target name, or an empty string when config is unavailable.
     */
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
        self::$targets = $jsonData['targets'];
        $requested_Target = getenv(self::SYNC_TARGET_ENV);
        self::$activeTarget = is_string($requested_Target)
            && isset(self::$targets[$requested_Target])
            ? $requested_Target
            : $jsonData['activeTarget'];

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

}
