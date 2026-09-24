<?php

namespace App\Constant;

use Illuminate\Support\Facades\Log;

//0_Runner.php

class Runner
{
    public const MAX_MIGRATIONS = 10;
    /**
     * * Instance counter does :
     * * 1. limit Runner to MAX_MIGRATIONS
     * * 2. ensures unique migration 
     * * timestamps and prevents filename collisions.
     * * e.g.
     * * 2026_06_23_080333_01_create_shops_table.php
     * * 2026_06_23_080333_02_create_products_table.php
     */
    public static int $entityCounter = 0;

    public static function run(): void
    {
        // dynamicly get app/Constant/EntityContant.php 
        $entities = (array) self::get_Entities();

        $count = count($entities);

        if ($count === 0) {
            Logger::error("--- Runner: No entity found. Nothing to migrate. ---\n"
                . "    Target : " . TargetManager::get_activeTarget() . "\n"
                . "    Path   : " . (string)TargetManager::gen_path('Constant'));
        }

        if ($count > self::MAX_MIGRATIONS) {
            Logger::error("--- CRITICAL: Migration limit exceeded. "
                . "\n Found {$count} tables, limit is " . self::MAX_MIGRATIONS
                . "\n Please split your migration tasks across multiple runs. ---");
        }

        foreach ($entities as $entity) {
            /**
             * skip counting for users table , because made by Laravel
             * 0001_01_01_000000_create_users_table.php
             */
            if ($entity !== UserConstant::class) {
                self::$entityCounter++;
            }

            echo "--- MakerTest: Running for {$entity} (Index: " . self::$entityCounter . ") ---\n\n";

            Maker::run($entity);
        }
    }

    /**
     * * read Entities.from จาก target app 
     * * and conver to list Constant class
     * * ordered by Entities.json that DEV-User 
     * * defined in UI (ordering of table has impact with FK on Laravel migration)
     *
     * @return string[] FQCN list e.g. ['App\Constant\UserConstant', 'App\Constant\ShopConstant']
     */
    private static function get_Entities(): array
    {
        $entities = [];

        $jsonFilePath = (string) TargetManager::gen_path('Constant/M_JSON/Entities.json');

        if (!file_exists($jsonFilePath)) {
            echo "--- Runner: Entities.json NOT found at [{$jsonFilePath}] ---\n\n";
            return $entities;
        }

        $json = json_decode((string) file_get_contents($jsonFilePath), true);

        if (!isset($json['entities']) || !is_array($json['entities'])) {
            echo "--- Runner: Entities.json has no 'entities' key ---\n\n";
            return $entities;
        }

        foreach (array_keys($json['entities']) as $entityName) {
            $className = self::to_ClassName((string) $entityName);

            if (self::load_Target_Constant($className)) {
                $entities[] = $className;
            }
        }

        return $entities;
    }

    /**
     * change entity from JSON to FQCN of Constant class
     *
     * @param  string $entityName e.g. 'PRODUCTS' | 'products' | 'Products'
     * @return string             e.g. 'App\Constant\ProductConstant'
     */
    private static function to_ClassName(string $entityName): string
    {
        // ตัด s/S ท้ายคำ (plural -> singular) แบบปลอดภัย
        $singular = preg_replace('/s$/i', '', $entityName);

        return 'App\\Constant\\' . ucfirst(strtolower((string) $singular)) . 'Constant';
    }

    /**
     * * Load *Constant.php from target app to runtime
     * * use class_exists($c, false) to prevent autoload to get old m-project
     *
     * @param  string $className FQCN e.g. 'App\Constant\ProductConstant'
     * @return bool              true = class is ready to be used in memory
     */
    private static function load_Target_Constant(string $className): bool
    {
        if (class_exists($className, false)) {
            return true; // if Class already loaded
        }

        $shortName = substr($className, (int) strrpos($className, '\\') + 1);
        $file      = (string) TargetManager::gen_path('Constant/' . $shortName . '.php');

        if (!is_file($file)) {
            echo "--- Runner: SKIP [{$shortName}] -> file not found at [{$file}] ---\n\n";
            return false;
        }

        require_once $file;

        if (!class_exists($className, false)) {
            echo "--- Runner: SKIP [{$shortName}] -> file loaded but class [{$className}] not declared ---\n\n";
            return false;
        }

        return true;
    }
}
