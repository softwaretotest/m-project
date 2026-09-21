<?php

namespace App\Constant;
//0_MakeMigration.php

class MakeMigration
{
    private static array $dbSchema = [];

    /**
     * Entry point to trigger migration analysis or creation.
     */
    public static function run(string $tableName, array $schema): void
    {
        echo "--- Maker: Get DB schema for {$tableName} ---\n\n";

        self::$dbSchema = [];
        foreach ($schema as $fieldName => $data) {
            self::$dbSchema[$fieldName] = $data['db'];
        }

        echo "--- Maker: Starting Migration Generation for [{$tableName}] ---\n\n";

        // Search for the migration file dynamically based on tableName
        // เดิม: $files = __DIR__ . "/../../database/migrations/*_create_{$tableName}_table.php"
        $pattern = (string) TargetManager::gen_path(
            "migrations/*_create_{$tableName}_table.php",
            'database'
        );

        $files = glob($pattern);


        /**
         * * * SPECIAL CASE users table in Laravel : 
         * * we must remove old *_create_{$tableName}_table.php  , before continue
         * * update existing file *_create_{$tableName}_table.php
         * * take to much effort and lead to bugs
         */
        if (!empty($files) && is_string($files[0])) {
            // delete *_create_{$tableName}_table.php
            @unlink($files[0]); // delete file from system like rm of CMD 
            echo "--- Maker: Removed old migration [" . basename($files[0]) . "] ---\n\n";
        }

        echo "--- Maker: Preparing to create migration file for [{$tableName}]. ---\n\n";
        MigrationFile::createNew($tableName);
    }

    /**
     * Overwrite the existing migration file with the processed content.
     */
    public static function replaceExisting(string $filePath, string $tableName): void
    {
        $content = file_get_contents($filePath);
        $newContent = self::updateMigration($content, $tableName);

        if ($content !== $newContent) {
            if (is_writable($filePath)) {
                file_put_contents($filePath, $newContent);
                echo "--- Maker: Successfully updated migration file at [{$filePath}] ---\n\n";
            } else {
                echo "--- Maker: Error! File is not writable: [{$filePath}] ---\n\n";
            }
        } else {
            echo "--- Maker: No changes detected. Migration file is already up to date. ---\n\n";
        }
    }

    /**
     * Orchestrates the transformation of the migration content.
     */
    private static function updateMigration(string $content, string $tableName): string
    {
        $content = str_replace('tablename', $tableName, $content);

        $lines = explode("\n", $content);
        $fields = [];

        /**
         * Before: $table->string('name');
         * After:  $table->string('name')->required();
         */
        $lines = DBOption::makeLines($lines, $fields, $tableName, self::$dbSchema);

        echo "--- Maker: Fields processed: " . implode(', ', $fields) . " ---\n\n";
        return implode("\n", $lines);
    }
}
