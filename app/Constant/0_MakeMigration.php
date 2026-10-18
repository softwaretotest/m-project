<?php

namespace App\Constant;

/**
 * * find duplicate fields
 * * find new fields
 * * call MigrationFile
 **/
class MakeMigration
{
    public static array $dbSchema = [];

    public static array $allDuplicateFields = []; // all duplicate fields

    /**
     * * show tableName --> fieldName per item
     * * to show all errors at the end of Runner::run
     */
    public static array $duplicate_table_fields = [];

    /**
     * e.g.
     * ```php
            [users] => Array
                (
                    [0] => is_active
                    [1] => image
                )
     * ```
     */
    public static array $new_fields = [];

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

        Echo_::printBox("--- Maker: Starting Migration Generation for [{$tableName}] ---");

        // Search for the migration file dynamically based on tableName
        $pattern = (string) TargetManager::gen_path(
            "migrations/*_{$tableName}_table.php",
            'database'
        );

        $files = glob($pattern);

        /**
         * * * SPECIAL CASE users table in Laravel :
         * * mode DEV :
         * * xxxxxxxxxx
         * * we must remove old *_create_{$tableName}_table.php  , before continue
         * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
         * * mode PROD :
         * * xxxxxxxxxx
         * * we do not update existing field , but
         * 1. use stub for PROD
         * * 0_add_new_fields_to_orders_table.php
         * 2. checkDuplicate fields first
         * * if duplicate field -> Logger::error
         * * else add only new field in 0_add_new_fields_to_orders_table.php
         */
        if (!empty($files)
            && Runner::$mode === 'DEV'
        ) {
            // delete *_{$tableName}_table.php
            // delete file from system like rm of CMD
            @unlink($files[0]);
            echo "--- Maker: Removed old migration [" . basename($files[0]) . "] ---\n\n";
        }

        if (Runner::$mode === 'DEV') {
            Echo_::printBox(" ----------- [ DEV ] mode selected ----------- ");
            echo "--- Maker: Preparing to CREATE NEW TABLE [{$tableName}]. ---\n\n";
            MigrationFile::createNew($tableName);
        }

        if (
            Runner::$mode === 'PROD'
            && self::find_Duplicate_Field_allMigrationFiles($tableName)
        ) {
            // selected PROD !!! But, no duplicate fields found , then use same DEV FLOW
            MigrationFile::createNew($tableName);
            return ;
        }

        if (Runner::$mode === 'PROD') {
            Echo_::printBox(" ----------- [ PROD ] mode selected ----------- ");
            echo "--- Maker: Preparing to ADD NEW FIELDS for [{$tableName}]. ---\n\n";
            MigrationFile::add_new_Field($tableName, self::$new_fields);
        }
    }

    /**
        * * PROD Mode: Find and scan duplicate fields across all existing migration files
        * 1. get all duplicate fieldnames in this table
        * 2. Loop to add found duplicate fieldname
        * @return true if NO OLD migration files found
        */
    private static function find_Duplicate_Field_allMigrationFiles(string $tableName): bool
    {
        $pattern = TargetManager::gen_path("migrations/*_{$tableName}_table.php", 'database');

        $files = glob($pattern);

        $migrationFiles = [];

        $NO_old_migration_file = false;
        if (empty($files)) {
            Echo_::printBox("--- PROD: NO OLD migration files found for [ {$tableName} ] ---");
            $NO_old_migration_file = true;
            return $NO_old_migration_file;
        }

        Echo_::printBox("--- PROD: Scanning migration files for table [{$tableName}] ---");

        // Logger::warning("jkfdlösajfkldösajfl  count files = " . count($files) . ' files');

        foreach ($files as $fileFullPath) {
            echo "Found File: " . basename($fileFullPath) . "\n";
            $migrationFiles[] = $fileFullPath;

            // 1. get all duplicate fieldnames in this table
            $duplicatesInThisFile = self::check_duplicate_field_in_file($fileFullPath, MakeMigration::$dbSchema);

            /**
             * allSchemaFields e.g.
               [users] => Array
                (
                    [0] => is_active
                    [1] => image
                )
             */
            $allSchemaFields = array_keys(MakeMigration::$dbSchema);

            // find new fields
            self::$new_fields[$tableName] = array_diff($allSchemaFields, self::$allDuplicateFields);


            if (!empty($duplicatesInThisFile)) {
                self::$allDuplicateFields = array_merge(self::$allDuplicateFields, $duplicatesInThisFile);

                // 2. Loop to add found duplicate fieldname
                foreach ($duplicatesInThisFile as $fieldName) {
                    self::$duplicate_table_fields[] = "{$tableName} --> {$fieldName}";
                }
            }
        }

        Logger::warning("Found " . count($migrationFiles) . " migration file(s) for table: " . $tableName);

        return $NO_old_migration_file;
    }

    /**
      * * PROD Mode: Check duplicate fields between existing migration files and new schema.
      * * Compares existing columns against the incoming schema definition.
      */
    private static function check_duplicate_field_in_file(string $filePath, array $newSchema): array
    {
        $existingColumns = self::get_fieldname_from_migration_file($filePath);
        $duplicates = [];

        foreach (array_keys($newSchema) as $fieldName) {
            if (in_array($fieldName, $existingColumns, true)) {
                $duplicates[] = $fieldName;
            }
        }
        return $duplicates;
    }

    /**
    * Extracts existing field names from migration files matching target table schema block only.
    * e.g. Schema::create('shops', function...) -> captures only fields inside this specific block.
    *
    * Regex Breakdown for $block_pattern:
    * - Schema::(?:create|table) : Matches "Schema::create" or "Schema::table" (non-capturing).
    * - \s*\(\s*                : Matches opening parenthesis with optional surrounding whitespace.
    * - ['\"]                   : Matches opening single or double quote for table name.
    * - {$escaped_table_name}   : Matches the exact dynamic target table name safely.
    * - ['\"]                   : Matches closing single or double quote for table name.
    * - .*?                     : Lazily matches parameters (e.g., function(Blueprint $table) {...}).
    * - \}\);\s*                : Matches closing block syntax "});" with trailing whitespace.
    *
    * Example Matched Block:
    * Schema::create('shops', function (Blueprint $table) {
    *     $table->id();
    *     $table->string('name');
    * });
    *
    * @param string $filePath Path to the migration file.
    * @return array Unique list of column names found inside the target schema block.
    */
    public static function get_fieldname_from_migration_file(string $filePath): array
    {
        $existingColumns = [];

        if (!is_file($filePath)) {
            return $existingColumns;
        }

        $content = file_get_contents($filePath);

        // Regex to find Schema ( Schema::create | Schema::table ) (DEV | PROD)
        $block_pattern = "/Schema::(?:create|table)\s*\(\s*['\"].*?['\"]\s*,\s*function\s*\(\s*Blueprint\s+\\\$table\s*\)\s*\{.*?\}\);/s";

        if (preg_match_all($block_pattern, $content, $block_matches)) {
            foreach ($block_matches[0] as $target_schema_block) {
                // get line info of : $table->...
                preg_match_all("/\\\$table->\w+\s*\(\s*['\"]([^'\"]+)['\"]/", $target_schema_block, $matches);

                if (!empty($matches[1])) {
                    foreach ($matches[1] as $colName) {
                        $existingColumns[] = $colName;
                    }
                }
            }
        }

        return array_unique($existingColumns);
    }

}
