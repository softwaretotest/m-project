<?php

namespace App\Constant;

require __DIR__ . '/../../vendor/autoload.php';

use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

/**
 * sync PHP to JSON
 */
class M_Sync
{
    public static string $target_Constant_Path = "";
    public static string $target_JSON_Path = "";
    private static bool $Entities_json_not_exists = false;

    public static function syncAll(): void
    {
        self::$target_Constant_Path = __DIR__ . '/../../../' . TargetManager::get_activeTarget() . '/app/Constant';

        self::$target_JSON_Path = self::$target_Constant_Path . "/M_JSON";

        DataHelper::ensureDir(self::$target_JSON_Path);

        M_Historizer::move_old_file_to_history(self::$target_JSON_Path . '/M-Data.json');
        M_Historizer::move_old_file_to_history(self::$target_JSON_Path . '/App-Data.json');
        M_Historizer::move_old_file_to_history(self::$target_JSON_Path . '/Entities.json');


        // Generate M-Data.json and App-Data.json
        self::run_PHP_to_JSON('0_Constant_M.php', 'M-Data.json');
        self::run_PHP_to_JSON('0_Constant_APP.php', 'App-Data.json');

        // Generate Entities data
        self::run_Entities_to_JSON('Entities.json');

        // full Path of Entities.json
        $jsonFilePath = self::$target_JSON_Path . '/Entities.json';

        if (self::$Entities_json_not_exists) {
            Logger::warning("File not exist : {$jsonFilePath}");
            Logger::warning("The Laravel Migration Order of Entities could be wrong \n and 'php artisan migrate:fresh' will be failed !!!");
        } else {
            Logger::finish();
        }
    }

    /**
     * AST Parsing & Traversing Mechanism (External Library: nikic/php-parser)
     * 
     * Concept: 
     * We use an AST (Abstract Syntax Tree) parser to read PHP constant files as text data 
     * without actually 'including' or executing them. 
     * 
     * How it works (Magic Flow):
     * 1. $parser->parse($code)  -> Converts PHP raw code into a tree structure (AST).
     * 2. new NodeTraverser()    -> Creates a tree-walker (the framework engine).
     * 3. addVisitor($visitor)   -> Attaches our custom node-processor class (whether you call it $visitor or $scanner).
     * 4. traverse($ast)         -> Walks through every node in the tree. Whenever it hits 
     *                              a target node, it automatically triggers enterNode($node) 
     *                              inside our visitor class via Inversion of Control (IoC).
     * *xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * *$traverser->traverse($ast); // Automatically fires function enterNode($node) under the hood , if the funct. exist
     * *xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * * NOTICE : &$visitor is passed by Ref.
     */
    private static function getData_from_M_APP_Entitiy_Constant_PHP_to_visitor(string $code, NodeVisitorAbstract &$visitor): void
    {
        // Unified Example for Refactoring:
        $parser     = (new ParserFactory())->createForNewestSupportedVersion();
        $ast        = $parser->parse($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor); // this calls enterNode($node) automatically , if methode exist
        $traverser->traverse($ast); // Automatically fires function enterNode($node) under the hood , if the funct. exist
    }

    private static function run_PHP_to_JSON($sourceFile, $jsonFile): void
    {
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
        $visitor = new Constant_M_APP_to_JSON();

        self::getData_from_M_APP_Entitiy_Constant_PHP_to_visitor($code, $visitor);

        $outputData = array_merge(["_comment" => $jsonFile], $visitor->data);
        $result = file_put_contents($jsonFile_full_path, json_encode($outputData, JSON_PRETTY_PRINT));
        if ($result)
            Logger::success("File has been created : {$jsonFile}");
        else
            Logger::error("Could not create file : {$jsonFile}");
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
        $visitor = new Entities_to_JSON();

        // 1. scan data from *Constant.php and keep in $php_entities
        foreach (glob(self::$target_Constant_Path . '/*Constant.php') as $file) {
            if (str_contains($file, 'Entities_to_JSON')) continue;
            $code = file_get_contents($file);
            self::getData_from_M_APP_Entitiy_Constant_PHP_to_visitor($code, $visitor);
        }

        // data from *Constant.php
        $php_entities = $visitor->entities;
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

            self::$Entities_json_not_exists = true; // for warning 
        }

        // 3. save Entities.json , keeping Master Order done by UI 
        $outputData = ["_comment" => $jsonFile, "entities" => $final_entities];
        $result = file_put_contents($jsonFilePath, json_encode($outputData, JSON_PRETTY_PRINT));
        if ($result)
            Logger::success("File has been created : $jsonFilePath");
        else
            Logger::error("Could not create file : $jsonFilePath");
    }
}

// Trigger sync
\App\Constant\M_Sync::syncAll();
