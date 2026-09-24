<?php

namespace App\Constant;

/**
 * set target path for m-project to do admin on the target app e.g. ecommerce, blog, etc.
 */
class TargetManager
{
    public static $targets = [];
    public static $activeTarget = '';

    // // target path of app 
    // public static $targets = [
    //     'ecommerce' => [
    //         'root_path' => 'C:/Users/o/.vscode/react/ecommerce',
    //     ],
    // ];

    // public static $activeTarget = 'ecommerce';

    const CONFIG_FILE = __DIR__ . '/3_M-Config.json';

    public static function loadConfig(): void
    {
        // create config if not exists
        if (!file_exists(self::CONFIG_FILE)) {
            $defaultConfig = [
                'activeTarget' => 'ecommerce',
                'targets' => [
                    'ecommerce' => [
                        'root_path' => 'C:/Users/o/.vscode/react/ecommerce'
                    ]
                ]
            ];

            // make JSON with readable JSON_PRETTY_PRINT
            $result = file_put_contents(self::CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT));
            if ($result === false) {
                Logger::error("Could not create file : " . self::CONFIG_FILE);
            }

            self::$activeTarget = $defaultConfig['activeTarget'];
            self::$targets = $defaultConfig['targets'];

            Logger::warning("Generated default M-Config at: " . self::CONFIG_FILE);
        } else {
            // if CONFIG_FILE exists , convert to Array
            $jsonData = json_decode(file_get_contents(self::CONFIG_FILE), true);

            if (isset($jsonData['activeTarget']) && isset($jsonData['targets'])) {
                self::$activeTarget = $jsonData['activeTarget'];
                self::$targets = $jsonData['targets'];
            } else {
                Logger::error("Invalid Config format in: " . self::CONFIG_FILE);
            }
        }
    }

    /**
     * * generate target app path (e.g. ecommerce) to put generated files on it, e.g.
     * @param $path = migrations/2026_09_20_162853_01_create_orders_table.php
     * @param $baseFolder = database
     * @return path to target app_name_root/app or /any_defined_foldername
     */
    public static function gen_path($path = '', $baseFolder = 'app'): string
    {
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
