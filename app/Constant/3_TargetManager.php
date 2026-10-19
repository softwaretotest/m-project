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

    public const CONFIG_FILE = __DIR__ . '/3_TargetManager_Config.json';

    /**
     * Read the active target, honoring a valid target override for Sync worker scripts.
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

        // 3. clear jsonData
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
     * @return string $path to target app_name_root/app or /any_defined_foldername
     */
    public static function gen_path($path = '', $baseFolder = 'app'): string
    {
        // if no activeTarget return reserve path
        if (empty(self::$activeTarget) || !isset(self::$targets[self::$activeTarget])) {
            // get latest set target
            self::get_activeTarget();
            if (empty(self::$activeTarget) || !isset(self::$targets[self::$activeTarget])) {
                return base_path($path); // guard return m-project  , but why ?
            }
        }

        $basePath = self::$targets[self::$activeTarget]['root_path'];
        return $basePath . '/' . $baseFolder . '/' . ltrim($path, '/');
    }


    /**
     * ```text
     * We must load Classes d,u,cd,cu,cud,uf,s,f,t of M-Project from Target ,
     * before running script with Reflection
     * otherwise there will be Exception like:
     * Fatal error: Uncaught Error: Class "App\Constant\t" not found
     * @return string app/Constant DIR of Target
     */
    public static function load_Classes_in_Constant_M_APP_from_Target()
    {
        $app_Constant_Dir = dirname(__DIR__, 3) . '/' . TargetManager::get_activeTarget() . '/app/Constant';

        // force to load file from Target before from m-project , to read from target ===================
        if (file_exists($app_Constant_Dir . '/0_Constant_APP.php')) {
            require_once $app_Constant_Dir . '/0_Constant_APP.php';
        }
        if (file_exists($app_Constant_Dir . '/0_Constant_M.php')) {
            require_once $app_Constant_Dir . '/0_Constant_M.php';
        }
        // ==============================================================================================
        return $app_Constant_Dir;
    }

}
