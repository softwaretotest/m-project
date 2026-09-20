<?php

namespace App\Constant;

require __DIR__ . '/../../vendor/autoload.php';

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

/**
 * sync JSON to PHP
 */
class M_Sync_JSON
{
    public static function syncAll(): void
    {
        M_Historizer::move_old_file_to_history('0_Constant_M.php');
        M_Historizer::move_old_file_to_history('0_Constant_APP.php');
        M_Historizer::move_old_Entities_to_history('/M_JSON/Entities.json');

        self::run_JSON_to_PHP('0_Constant_M.php', '/M_JSON/M-Data.json');
        self::run_JSON_to_PHP('0_Constant_APP.php', '/M_JSON/App-Data.json');
        self::run_JSON_to_Entities('/M_JSON/Entities.json');

        echo "\n======================================================================\n";
        echo " [ END ] SYNCHRONIZATION PROCESS COMPLETED SUCCESSFULLY ✅                  \n";
        echo "======================================================================\n\n";
    }

    private static function run_JSON_to_PHP($sourceFile, $jsonFile): void
    {
        echo "\n----------------------------------------------------------------------\n";
        echo "[4] Processing JSON to PHP generation for {$sourceFile} using {$jsonFile}\n";
        echo "----------------------------------------------------------------------\n\n";
        M_Sync_JSON_App_Data::generate();
        M_Sync_JSON_M_Data::generate();
    }

    private static function run_JSON_to_Entities($jsonFile): void
    {
        echo "\n----------------------------------------------------------------------\n";
        echo "[5] Processing Entities JSON to PHP generation using {$jsonFile}\n";
        echo "----------------------------------------------------------------------\n\n";
        M_Sync_JSON_Entities::generate();
    }
}

\App\Constant\M_Sync_JSON::syncAll();
