<?php

namespace App\Constant;

require __DIR__ . '/../../vendor/autoload.php';

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

/**
 * sync PHP to JSON
 */
class M_Sync
{
    public static string $target_Constant_Path = "";
    public static string $target_JSON_Path = "";

    public static function syncAll(): void
    {
        self::$target_Constant_Path = __DIR__ . '/../../../' . TargetManager::$activeTarget . '/app/Constant';

        self::$target_JSON_Path = self::$target_Constant_Path . "/M_JSON";

        DataHelper::ensureDir(self::$target_JSON_Path);

        M_Historizer::move_old_file_to_history(self::$target_JSON_Path . '/M-Data.json');
        M_Historizer::move_old_file_to_history(self::$target_JSON_Path . '/App-Data.json');
        M_Historizer::move_old_file_to_history(self::$target_JSON_Path . '/Entities.json');


        // Generate M-Data.json and App-Data.json
        self::run_PHP_to_JSON('0_Constant_M.php', 'M-Data.json');
        self::run_PHP_to_JSON('0_Constant_APP.php', 'App-Data.json');

        // Generate Entities data
        self::run_Entities_to_JSON('Entities.json');  //DOES NOT WORK always make Entities.json in m-project/app/Constant/
    }

    private static function run_PHP_to_JSON($sourceFile, $jsonFile): void
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        $code = '';

        $jsonFile_full_path = self::$target_JSON_Path . '/' . $jsonFile;

        $phpFile_full_path = self::$target_Constant_Path . '/' . $sourceFile;

        // check if Constant_M.php or Constant_APP.php exists at $target_app
        if (file_exists($phpFile_full_path)) {
            // CASE YES : copy from target_app
            $code = file_get_contents(self::$target_Constant_Path . '/' . $sourceFile);
        } else {
            // CASE NO  : copy from m-project
            $code = file_get_contents(__DIR__ . '/' . $sourceFile);
        }
        $ast = $parser->parse($code);

        $visitor = new Constant_M_APP_to_JSON();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $outputData = array_merge(["_comment" => $jsonFile], $visitor->data);
        file_put_contents($jsonFile_full_path, json_encode($outputData, JSON_PRETTY_PRINT));
        echo "--- M_Sync: Created {$jsonFile} ---\n";
    }

    /**
     * * Convert *Constant.php to Entities.json 
     * * using the Technic "JSON - Master Order"
     * * CASES:
     * 1. CASE : all tables exists on PHP and JSON  
     * *    => ordered by Entities.json
     * 2. CASE : There is a new table in PHP , but not exists in JSON
     * *    => put the new table at the end of JSON
     * 3. CASE : table not exist in JSON , but in JSON exists
     * *    => remove this trash table from JSON
     * 4. CASE : Mixing CASES 1 2 3 , e.g. :
     *           JSON has A B C
     *           PHP has A B C D
     *      => Union + Preserve Order , that means CASES 1 2 3
     *          1. keep JSON order
     *          2. put new PHP table at the end of JSON
     *          3. cut out none existing table from JSON
     *          4. alway overwrite JSON table content by PHP Source table
     * * ------------------------------------------------
     * * JSON - Master Order :
     * * --------------------
     * * add new order with more intelligent , 
     * * to keep order from Entities.json if json file exists, 
     * * and replace json table content by *Constant.php
     * * -------------------------------------------------
     * * FLOW :
     * 1. scan data from *Constant.php and keep in $php_entities
     * 2. check if old Entitites.json exist to use JSON - Master Order
     * 3. save Entities.json , keeping Master Order done by UI 
     */
    private static function run_Entities_to_JSON($jsonFile): void
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $scanner = new Entities_to_JSON();

        // 1. scan data from *Constant.php and keep in $php_entities
        foreach (glob(self::$target_Constant_Path . '/*Constant.php') as $file) {
            if (str_contains($file, 'Entities_to_JSON')) continue;

            $code = file_get_contents($file);
            $ast = $parser->parse($code);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($scanner);
            $traverser->traverse($ast);
        }

        // data from *Constant.php
        $php_entities = $scanner->entities;
        $final_entities = [];

        // full Path of Entities.json
        $jsonFilePath = self::$target_JSON_Path . '/' . $jsonFile;

        // 2. check if old Entitites.json exist to use JSON - Master Order
        if (file_exists($jsonFilePath)) {
            $json_data = json_decode(file_get_contents($jsonFilePath), true);
            $json_entities = $json_data['entities'] ?? [];

            // Case 1 & Case 3 : go through "Master Order" from old Entities.json
            foreach ($json_entities as $table_name => $fields) {
                // Case 1 : table exists on PHP and JSON
                if (isset($php_entities[$table_name])) {
                    /** 
                     * * if table_name exists -> use JSON-order for table,
                     * * but overwrite JSON 
                     * * with the table content (Fields) of *Constant.php
                     * */
                    $final_entities[$table_name] = $php_entities[$table_name];
                    // Case 3 : Mark that table_name is done the loop
                    unset($php_entities[$table_name]);
                }
                // if some *Constant.php was deleted, then skip this loop (Case 3)
            }

            /**
             * * Case 2 : if there are some tables left in $php_entities ,
             * * those are new tables
             */
            if (!empty($php_entities)) {
                foreach ($php_entities as $new_table_name => $new_fields) {
                    // put the new table at the end
                    $final_entities[$new_table_name] = $new_fields;
                }
            }
        } else {
            /**
             * * if there is no Entities.json, 
             * * then order by app/Constant/Entities/*Constant.php  
             * */
            $final_entities = $php_entities;
        }

        // 3. save Entities.json , keeping Master Order done by UI 
        $outputData = ["_comment" => $jsonFile, "entities" => $final_entities];
        file_put_contents($jsonFilePath, json_encode($outputData, JSON_PRETTY_PRINT));
        echo "--- M_Sync: Created {$jsonFile} ---\n";
    }
}

// Trigger sync
\App\Constant\M_Sync::syncAll();
