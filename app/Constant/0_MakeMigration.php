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
    public static array $user_fields_laravel = [];


    /**
     * glob (.... "migrations/*_{Maker::$tableName}_table.php" ...)
     * retsult = e.g.
     * ```php
      Array
        (
            [0] => C:/Users/o/.vscode/react/ecommerce/database/migrations/0001_01_01_000000_create_users_table.php
            [1] => C:/Users/o/.vscode/react/ecommerce/database/migrations/2026_10_20_235950_00_add_new_fields_to_users_table.php
            [2] => C:/Users/o/.vscode/react/ecommerce/database/migrations/2026_10_21_054238_00_alter_fields_to_users_table.php
        )
     * ```
     */
    public static array $allMigrationFiles = [];

    /**
     * set $user_fields_laravel
     * @see self::$user_fields_laravel
     */
    private static function get_user_fields_laravel(): void
    {
        $stubPath = __DIR__.'\0_create_users_table.php';

        if (Maker::$tableName === 'users' && file_exists($stubPath)) {
            $schemaLines = self::extract_schema_lines_from_file($stubPath);
            foreach ($schemaLines as $line) {
                self::$user_fields_laravel[$line] = true;
            }
        }
    }

    /**
     * Entry point to trigger migration analysis or creation.
     */
    public static function run(array $schema): void
    {
        // --- CLEAR STATIC STATE TO PREVENT POLLUTION ---
        self::$dbSchema = [];
        self::$allDuplicateFields = [];
        self::$new_fields = [];
        MigrationFile::$alter_fields = [];
        MigrationFile::$draftFilePath = '';
        // ---------------------------------------------

        // --- GET INIT Variable for run ---
        $pattern = TargetManager::gen_path("migrations/*_".Maker::$tableName."_table.php", 'database');
        self::$allMigrationFiles = glob($pattern);

        self::get_user_fields_laravel();

        Echo_::printBox("--- Maker: Get DB schema for ".Maker::$tableName." ---");

        foreach ($schema as $fieldName => $data) {
            self::$dbSchema[$fieldName] = $data['db'];
        }

        Echo_::printBox("--- Maker: Starting Migration Generation for [".Maker::$tableName."] ---");

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
        if (!empty(self::$allMigrationFiles)
            && Runner::$mode === 'DEV'
        ) {
            // can be commented out, if you don't want to full /history during DEV
            M_Historizer::move_old_file_to_history(self::$allMigrationFiles[0]);

            // delete *_".Maker::$tableName."_table.php
            // delete file from system like rm of CMD
            @unlink(self::$allMigrationFiles[0]);
            echo "--- Maker: Removed old migration [" . basename(self::$allMigrationFiles[0]) . "] ---\n\n";
        }

        if (Runner::$mode === 'DEV') {
            Echo_::printBox(" ----------- [ DEV ] mode selected ----------- ");
            echo "--- Maker: Preparing to CREATE NEW TABLE [".Maker::$tableName."]. ---\n\n";
            MigrationFile::createNew();
        }


        if (Runner::$mode === 'PROD') {
            Echo_::printBox(" ----------- [ PROD ] mode selected ----------- ");
            Echo_::printBox("----------- Maker: Preparing to ADD NEW FIELDS for [".Maker::$tableName."] -----------");

            $is_create_table_exist = self::check_is_create_table_exists();

            if ($is_create_table_exist) {
                Echo_::printBox("--- Maker: Create table migration not found. Generating create file first. ---");
            }

            $NO_old_migration_file = self::find_Duplicate_Field_allMigrationFiles();

            if ($NO_old_migration_file  || false === $is_create_table_exist) {
                MigrationFile::createNew();
            }

            /**
             * FLOW add_new_fields
             */
            if (!empty(self::$new_fields)) {
                MigrationFile::add_new_Field();
            }

            /**
             * FLOW alter_Field , if no new Field , but alter_fields[] exist
             */
            if (
                false === empty(MigrationFile::$alter_fields)
                &&
                $is_create_table_exist
            ) {
                MigrationFile::alter_Field();
            }

            /**
             * FLOW alter_Field after add_new_Field() or createNew() DONE
             */
            if (
                empty(MigrationFile::$alter_fields)
                &&
                empty(self::$new_fields)
                &&
                $is_create_table_exist
            ) {
                MigrationFile::alter_Field();
            }

        }
    }

    /**
 * Check if the create table migration file already exists for the current table
 */
    public static function check_is_create_table_exists(): bool
    {
        foreach (self::$allMigrationFiles as $filePath) {
            if (str_contains(basename($filePath), "create_".Maker::$tableName."_table")) {
                return true;
            }
        }

        return false;
    }

    /**
    * * PROD Mode: Find and scan duplicate fields across all existing migration files
    * 1. get all duplicate fieldnames in this table
    * 2. Loop to add found duplicate fieldnames
    * @return true if NO OLD migration files found
    */
    public static function find_Duplicate_Field_allMigrationFiles(): bool
    {
        if (empty(self::$allMigrationFiles)) {
            Echo_::printBox("--- PROD: NO OLD migration files found for [ ".Maker::$tableName." ] ---");
            return true; // NO_old_migration_file
        }

        Echo_::printBox("--- PROD: Scanning migration files for table [".Maker::$tableName."] ---");

        $allSchemaFields = array_keys(MakeMigration::$dbSchema);

        // 1. get all duplicate fieldnames in this table
        foreach (self::$allMigrationFiles as $filePath) {
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

        $schemaLines = self::extract_schema_lines_from_file($filePath);

        foreach ($schemaLines as $line) {
            // filter out Laravel laravel basic fields before add to $all_existing_dbSchema
            if (Maker::$tableName === 'users'
                &&
                isset(self::$user_fields_laravel[$line])
            ) {
                continue;
            }

            if (isset(self::$all_existing_dbSchema_of_allMigrationFiles[Maker::$tableName][$line])) {
                Logger::warning(
                    Logger::ERROR . "Duplicate schema line detected in table [".Maker::$tableName."]: {$line}"
                );
                exit(1);
            }

            /**
             * Collecting : Map content as key and correct table name as value for collision detection
             * e.g.
             * [$table->string('image', 255)->default('');] => 1
            */
            self::$all_existing_dbSchema_of_allMigrationFiles[Maker::$tableName][$line] = true;

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
    public static function extract_schema_lines_from_file(string $filePath): array
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
                if (strpos($line, "Schema::create('" . Maker::$tableName . "'") !== false ||
                    strpos($line, "Schema::table('" . Maker::$tableName . "'") !== false) {
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
}
