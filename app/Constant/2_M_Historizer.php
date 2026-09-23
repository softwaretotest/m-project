<?php

namespace App\Constant;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * PHP file historizer for archiving old generated files
 */
class M_Historizer
{
    const HISTORY_DIR = __DIR__ . '/../../../history';
    public static string $target_HISTORY_DIR;

    /**
     * 1. check if $sourceFile exist
     * 2. create folder ./history if not exist
     * 3. rename $sourceFile = unixtimestamp() + "_" + $sourceFile
     * 4. move $sourceFile to folder ./history
     * @param $sourceFile = e.g. M-Data.json
     */
    public static function move_old_file_to_history($sourceFile)
    {
        echo "----------------------------------------------------------------------\n";
        echo " ------------ [1] START move_old_file_to_history FOR : " . $sourceFile . " ------------ \n";
        echo "----------------------------------------------------------------------\n";

        $target_HISTORY_DIR = self::HISTORY_DIR . '/' . TargetManager::$activeTarget;

        $filePath = $sourceFile; // for EntityGenerator

        if (!file_exists($filePath)) {
            $filePath = $target_HISTORY_DIR . '/' . $sourceFile; // for M_Sync , M_Sync_JSON
        }

        echo "[2.1] Checking history directory existence: " . self::HISTORY_DIR . "\n";
        if (!file_exists($target_HISTORY_DIR)) {
            mkdir($target_HISTORY_DIR, 0755, true);
            echo "[2.2] Created history directory successfully ✅ at :.\n";
            echo $target_HISTORY_DIR . "\n\n";
        } else {
            echo "[2.2] History directory already exists.\n";
        }

        echo "[2.3] Checking if source file exists: {$sourceFile}\n";

        if (file_exists($filePath)) {
            $timestamp = time();
            $newFileName = $timestamp . '_' . basename($sourceFile);
            $destinationPath = $target_HISTORY_DIR . '/' . $newFileName;

            echo "[2.4] copy & rename file : {$sourceFile} \n to history as : {$destinationPath}\n\n";
            if (copy($filePath, $destinationPath)) {
                Logger::success("File successfully moved to history : $newFileName");
            } else {
                Logger::error("Failed to move file {$sourceFile} to history.");
            }
        } else {
            Logger::warning("Source file {$sourceFile} does not exist, skipping archive.");
        }
    }

    /**
     * * 3.1 get a List of Entities from $jsonFile 
     * * 3.2 Loop Entities : (USERS,PRODUCTS,ORDERS,etc.)
     * * 3.2.1 check if PHP file of each Entitiy exists , e.g. UserConstant.php 
     * * 3.2.2 if file exist rename e.g. UserConstant.php to unixtimestamp() + "_" + UserConstant.php
     * * 2.3 move php file to ./history
     * @param $jsonFile = Entities.json
     */
    public static function move_old_Entities_to_history($jsonFilePath, $target_Constant_Path)
    {
        echo "----------------------------------------------------------------------\n";
        echo " ------------ START move_old_Entities_to_history ------------ \n";
        echo "----------------------------------------------------------------------\n";

        $jsonFile = basename($jsonFilePath);

        echo "[3.1] Getting list of entities from {$jsonFile}\n\n";
        if (!file_exists($jsonFilePath)) {
            Logger::warning("JSON file not found: {$jsonFile}");
            return;
        }

        $result = file_get_contents($jsonFilePath);
        if (!$result) Logger::error("Failure - file_get_contents($jsonFilePath)");

        $jsonData = json_decode($result, true);
        if (!isset($jsonData['entities'])) {
            Logger::error("Invalid entities JSON structure.");
        }

        if (!file_exists(self::HISTORY_DIR)) {
            mkdir(self::HISTORY_DIR, 0755, true);
        }

        echo "[3.2] Looping through entities to archive existing PHP files...\n\n";
        foreach ($jsonData['entities'] as $entityName => $entityData) {
            $singularName = rtrim($entityName, 'S');
            $formattedEntityName = ucfirst(strtolower($singularName));
            $phpFileName = $formattedEntityName . 'Constant.php';
            $phpFilePath = $target_Constant_Path . '/' . $phpFileName;

            echo "[3.2.1] Checking entity file: {$phpFileName}\n";
            if (file_exists($phpFilePath)) {
                $timestamp = time();
                $newFileName = $timestamp . '_' . $phpFileName;
                $destinationPath = self::HISTORY_DIR . '/' . TargetManager::$activeTarget . '/' . $newFileName;

                echo "[3.2.2] Moving entity file {$phpFileName} to history as {$newFileName}\n";
                if (rename($phpFilePath, $destinationPath)) {
                    Logger::success("Entity file successfully moved to history.");
                } else {
                    Logger::error("Failed to move entity file {$phpFileName}.");
                }
            } else {
                Logger::warning("Entity file {$phpFileName} does not exist, skipping.");
            }
        }
    }
}
