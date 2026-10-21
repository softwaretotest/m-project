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
     * E.g.
     * ```php
     * [
     *      'users' => [
     *           "$table->boolean('is_active')->default(true);",
     *           "$table->string('image',255)->default('');",
     *      ],
     *      'products' => [
     *           "$table->string('name',255)->default('');",
     *      ],
     *      ... etc.
     * ]
     */
    public static array $all_existing_dbSchema_of_allMigrationFiles = [];

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
     * ```php
     Array
        (
            [$table->id();] => 1
            [$table->string('name');] => 1
            [$table->string('email')->unique();] => 1
            [$table->timestamp('email_verified_at')->nullable();] => 1
            [$table->string('password');] => 1
            [$table->rememberToken();] => 1
            [$table->timestamps();] => 1
        )
     */
    public static $user_fields_laravel = [];

    /**
     * set $user_fields_laravel
     * @see self::$user_fields_laravel
     */
    private static function get_user_fields_laravel(): void
    {
        $stubPath = __DIR__.'\0_create_users_table.php';

        if (Maker::$tableName === 'users' && file_exists($stubPath)) {
            $schemaLines = self::extract_schema_lines_from_file($stubPath, 'users');
            foreach ($schemaLines as $line) {
                self::$user_fields_laravel[$line] = true;
            }
        }
    }

    /**
     * Entry point to trigger migration analysis or creation.
     */
    public static function run(string $tableName, array $schema): void
    {
        self::get_user_fields_laravel();

        Echo_::printBox("--- Maker: Get DB schema for {$tableName} ---");
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
         * * we must remove old *_create_{$tableName}_table.php  , before continue , to simplify DEV
         *
         * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
         * * mode PROD :
         * * xxxxxxxxxx
         * * we do not update existing field , but
         *
         * 1. use stub for PROD
         * * 0_add_new_fields_to_orders_table.php
         *
         * 2. checkDuplicate fields first
         * * if duplicate field -> Logger::error
         * * else add only new field in 0_add_new_fields_to_orders_table.php
         */
        if (!empty($files)
            && Runner::$mode === 'DEV'
        ) {
            // can be commented out, if you don't want to full /history during DEV
            M_Historizer::move_old_file_to_history($files[0]);

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

        if (Runner::$mode === 'PROD') {
            Echo_::printBox(" ----------- [ PROD ] mode selected ----------- ");
            echo "--- Maker: Preparing to ADD NEW FIELDS for [{$tableName}]. ---\n\n";

            $NO_old_migration_file = self::find_Duplicate_Field_allMigrationFiles($tableName);

            if ($NO_old_migration_file) {
                MigrationFile::createNew($tableName);
                return;
            }

            if (!empty(self::$new_fields)) {
                MigrationFile::add_new_Field();
            }
        }
    }

    public static function get_allMigrationFiles(string $tableName): array
    {
        $pattern = TargetManager::gen_path("migrations/*_{$tableName}_table.php", 'database');
        return glob($pattern);
    }

    /**
    * * PROD Mode: Find and scan duplicate fields across all existing migration files
    * 1. get all duplicate fieldnames in this table
    * 2. Loop to add found duplicate fieldnames
    * @return true if NO OLD migration files found
    */
    public static function find_Duplicate_Field_allMigrationFiles(string $tableName): bool
    {
        $migrationFiles = self::get_allMigrationFiles(Maker::$tableName);

        if (empty($migrationFiles)) {
            Echo_::printBox("--- PROD: NO OLD migration files found for [ {$tableName} ] ---");
            return true; // NO_old_migration_file
        }

        Echo_::printBox("--- PROD: Scanning migration files for table [{$tableName}] ---");

        // reset old data before checking
        self::$allDuplicateFields = [];
        $allSchemaFields = array_keys(MakeMigration::$dbSchema);

        // 1. get all duplicate fieldnames in this table
        foreach ($migrationFiles as $filePath) {
            echo "Found File: " . basename($filePath) . "\n";

            // 2. Loop to add found duplicate fieldnames
            $duplicates = self::check_duplicate_field_in_file($filePath, MakeMigration::$dbSchema);

            if (!empty($duplicates)) {
                self::$allDuplicateFields = array_merge(self::$allDuplicateFields, $duplicates);
            }
        }

        // cut out multiple duplicate fields
        self::$allDuplicateFields = array_unique(self::$allDuplicateFields);

        // get new fields by comparing with db schema( db opton ) with duplicate fields
        self::$new_fields = array_diff($allSchemaFields, self::$allDuplicateFields);

        return false; // has_old_migration_file
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
     * ```text
     * e.g. found fields in the filePath:
     * $table->boolean('is_active')
     * $table->string('image', 255)
     * @return array e.g.
     * [ 'is_active' , 'image' ]
     *
     * * additional job : collect $all_existing_dbSchema_of_allMigrationFiles [ ]
     * ```php
        Array
        (
            [users] => Array
                (
                    [$table->boolean('is_active')->default(true);] => 1
                    [$table->string('image', 255)->default('');] => 1
                )
            [orders] => Array
                (
                    [$table->id();] => 1
                    ...
                    [$table->timestamps();] => 1
                )

        )
     */
    private static function get_fieldname_from_migration_file(string $filePath): array
    {
        $existingColumns = [];

        $tableName = Maker::$tableName;

        $schemaLines = self::extract_schema_lines_from_file($filePath, $tableName);

        foreach ($schemaLines as $line) {
            // filter out Laravel laravel basic fields before add to $all_existing_dbSchema
            if ($tableName === 'users' && isset(self::$user_fields_laravel[$line])) {
                continue;
            }

            if (isset(self::$all_existing_dbSchema_of_allMigrationFiles[$tableName][$line])) {
                Logger::warning(
                    Logger::ERROR . "Duplicate schema line detected in table [{$tableName}]: {$line}"
                );
                exit(1);
            }

            /**
             * Collecting : Map content as key and correct table name as value for collision detection
             * e.g.
             * [$table->string('image', 255)->default('');] => 1
            */
            self::$all_existing_dbSchema_of_allMigrationFiles[$tableName][$line] = true;

            $firstQuote = strpos($line, "'");
            if ($firstQuote === false) {
                $firstQuote = strpos($line, '"');
            }

            /**
             * e.g. "$table->string('name', 255);"
             *      fine text between 1st quote ' and 2nd qoute '
             *      $colName = name
             */
            if ($firstQuote !== false) {
                $quoteChar = $line[$firstQuote];
                $secondQuote = strpos($line, $quoteChar, $firstQuote + 1);

                if ($secondQuote !== false) {
                    $colName = substr($line, $firstQuote + 1, $secondQuote - $firstQuote - 1);
                    if (!empty($colName)) {
                        $existingColumns[] = $colName;
                    }
                }
            }

            Logger::warning(
                '[ DEBUG 1 ] '.
                'self::$all_existing_dbSchema_of_allMigrationFiles = '.
                print_r(self::$all_existing_dbSchema_of_allMigrationFiles, true)
            );
        }

        return $existingColumns;
    }

    /**
     * content of $fiePath = e.g.
     * ```php
     ...
       public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->string('image', 255)->default('');
        });
    }
     ...
     * @return array e.g.
    [
        $table->boolean('is_active')->default(true);
        $table->string('image', 255)->default('');
    ]
     */
    public static function extract_schema_lines_from_file(string $filePath, string $tableName): array
    {
        $schemaLines = [];
        if (!is_file($filePath)) {
            return $schemaLines;
        }

        $lines = file($filePath);
        $inUpFunction = false;
        $inTargetSchemaBlock = false;
        $schemaBraceDepth = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if (strpos($line, 'public function up()') !== false) {
                $inUpFunction = true;
                continue;
            }

            if (strpos($line, 'public function down()') !== false) {
                $inUpFunction = false;
                $inTargetSchemaBlock = false;
                continue;
            }

            if (!$inUpFunction) {
                continue;
            }

            if (!$inTargetSchemaBlock) {
                if (strpos($line, "Schema::create('" . $tableName . "'") !== false ||
                    strpos($line, "Schema::table('" . $tableName . "'") !== false) {
                    $inTargetSchemaBlock = true;
                    $schemaBraceDepth = substr_count($line, '{') - substr_count($line, '}');
                    continue;
                }
            } else {
                $schemaBraceDepth += substr_count($line, '{') - substr_count($line, '}');
                if ($schemaBraceDepth <= 0) {
                    $inTargetSchemaBlock = false;
                    continue;
                }

                if (strpos($line, '$table->') !== false) {
                    $schemaLines[] = $line;
                }
            }
        }

        return $schemaLines;
    }


    // Old version with regex solution , difficult to understand, but it works
    // /**
    // * Extracts existing field names from migration files matching target table schema block only.
    // * e.g. Schema::create('shops', function...) -> captures only fields inside this specific block.
    // *
    // * Regex Breakdown for $block_pattern:
    // * - Schema::(?:create|table) : Matches "Schema::create" or "Schema::table" (non-capturing).
    // * - \s*\(\s*                : Matches opening parenthesis with optional surrounding whitespace.
    // * - ['\"]                   : Matches opening single or double quote for table name.
    // * - {$escaped_table_name}   : Matches the exact dynamic target table name safely.
    // * - ['\"]                   : Matches closing single or double quote for table name.
    // * - .*?                     : Lazily matches parameters (e.g., function(Blueprint $table) {...}).
    // * - \}\);\s*                : Matches closing block syntax "});" with trailing whitespace.
    // *
    // * Example Matched Block:
    // * Schema::create('shops', function (Blueprint $table) {
    // *     $table->id();
    // *     $table->string('name');
    // * });
    // *
    // * @param string $filePath Path to the migration file.
    // * @return array Unique list of column names found inside the target schema block.
    // */
    // public static function get_fieldname_from_migration_file(string $filePath): array
    // {
    //     $existingColumns = [];

    //     if (!is_file($filePath)) {
    //         return $existingColumns;
    //     }

    //     $content = file_get_contents($filePath);

    //     // Regex to find Schema ( Schema::create | Schema::table ) (DEV | PROD)
    //     $block_pattern = "/Schema::(?:create|table)\s*\(\s*['\"].*?['\"]\s*,\s*function\s*\(\s*Blueprint\s+\\\$table\s*\)\s*\{.*?\}\);/s";

    //     if (preg_match_all($block_pattern, $content, $block_matches)) {
    //         foreach ($block_matches[0] as $target_schema_block) {
    //             // get line info of : $table->...
    //             preg_match_all("/\\\$table->\w+\s*\(\s*['\"]([^'\"]+)['\"]/", $target_schema_block, $matches);

    //             if (!empty($matches[1])) {
    //                 foreach ($matches[1] as $colName) {
    //                     $existingColumns[] = $colName;
    //                 }
    //             }
    //         }
    //     }

    //     return array_unique($existingColumns);
    // }

}
