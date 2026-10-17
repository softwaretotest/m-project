<?php

namespace App\Constant;

//0_MigrationFile.php

class MigrationFile
{
    private static string $tableName;
    private static string $draftPath;
    private static string $destinationPath;
    private static string $fileName;
    private static string $filePrefix;

    public static array $allDuplicateFields = []; // ตัวแปรสำหรับสะสมฟิลด์ที่ซ้ำจากทุกไฟล์

    /**
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * *  [ PROD - FLOW ]      ADD NEW FIELDS
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     */
    public static function add_new_Field(string $tableName): void
    {
        self::$tableName = $tableName;
        $NO_old_migration_file = self::find_Duplicate_Field_allMigrationFiles($tableName);

        if ($NO_old_migration_file) {
            Echo_::printBox(' [ ⚠️😡 ] NO_old_migration_file = TRUE');
            self::createNew($tableName); // all same like DEV FLOW
        } else { // PROD FLOW
            Echo_::printBox(' [ ✅😊😘🩷 ] NO_old_migration_file = FALSE');

            // Preparation Zone
            $isUser = ($tableName === 'users');
            self::$draftPath = __DIR__ . '/' . (
                $isUser ? '0_create_users_table.php' : '0_add_new_fields_to_orders_table.php' //use PROD stub
            );
            // next Logic to be analysed .......
        }
    }


    /**
     * * PROD Mode: Find and scan duplicate fields across all existing migration files
     * @return true if NO OLD migration files found
     */
    private static function find_Duplicate_Field_allMigrationFiles(string $tableName): bool
    {
        $pattern = TargetManager::gen_path("migrations/*_{$tableName}_table.php", 'database');

        $files = glob($pattern);

        $migrationFiles = [];

        if (!empty($files)) {
            Echo_::printBox("--- PROD: Scanning migration files for table [{$tableName}] ---");

            Logger::warning("jkfdlösajfkldösajfl  count files = " . count($files) . ' files');

            foreach ($files as $fileFullPath) {
                echo "Found File: " . basename($fileFullPath) . "\n";
                $migrationFiles[] = $fileFullPath;

                // เรียกใช้ฟังก์ชันตรวจสอบฟิลด์ซ้ำจากไฟล์นี้ โดยส่ง $fileFullPath และ schema ปัจจุบันเข้าไป
                // (สมมติว่าฟังก์ชันย่อยชื่อ check_duplicate_field_in_file)
                $duplicatesInThisFile = self::check_duplicate_field_in_file($fileFullPath, MakeMigration::$dbSchema);

                if (!empty($duplicatesInThisFile)) {

                    // วนลูปแปะชื่อตารางนำหน้าชื่อฟิลด์ที่ซ้ำทีละตัว
                    foreach ($duplicatesInThisFile as $fieldName) {
                        self::$allDuplicateFields[] = "{$tableName} --> {$fieldName}";
                    }

                }

            }

            Logger::warning("Found " . count($migrationFiles) . " migration file(s) for table: " . $tableName);
            return false; // แปลว่า "เจอไฟล์เก่าอยู่" (ไม่ได้ว่างเปล่า)
        } else {
            Echo_::printBox("--- PROD: NO OLD migration files found for [ {$tableName} ] ---");
            return true; // แปลว่า "ไม่เจอไฟล์เก่าเลย" (ทางสะดวก ไปสร้างตารางใหม่ได้)
        }
    }

    /**
     * * PROD Mode: Check duplicate fields between existing migration files and new schema.
     * * Compares existing columns against the incoming schema definition.
     */
    private static function check_duplicate_field_in_file(string $filePath, array $newSchema): array
    {
        $existingColumns = self::get_fieldname_from_migration_file($filePath); // ปรับใช้ฟังก์ชันอ่านไฟล์ที่คุณเขียนไว้
        $duplicates = [];

        foreach ($newSchema as $fieldName => $options) {
            if (in_array($fieldName, $existingColumns, true)) {
                $duplicates[] = $fieldName;
            }
        }

        return $duplicates; // ส่งคืนอาเรย์ฟิลด์ซ้ำให้แม่บ้านเอาไปรวมยอด
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

        // ใช้ Regex ค้นหาบล็อก Schema ทั้งหมดในไฟล์นี้ ( Schema::create | Schema::table ) (DEV | PROD)
        $block_pattern = "/Schema::(?:create|table)\s*\(\s*['\"].*?['\"]\s*,\s*function\s*\(\s*Blueprint\s+\\\$table\s*\)\s*\{.*?\}\);/s";

        if (preg_match_all($block_pattern, $content, $block_matches)) {
            foreach ($block_matches[0] as $target_schema_block) {
                // ดึงชื่อฟิลด์จากคำสั่ง $table->...
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
    // public static function get_fieldname_from_migration(string $tableName): array
    // {
    //     $existingColumns = [];
    //     $activeTarget = TargetManager::get_activeTarget();
    //     $pattern = dirname(__DIR__, 3) . '/' . $activeTarget . "/database/migrations/*_{$tableName}_table.php";

    //     $files = glob($pattern);
    //     if (empty($files)) {
    //         return $existingColumns;
    //     }

    //     foreach ($files as $filePath) {
    //         if (!is_file($filePath)) {
    //             continue;
    //         }

    //         $content = file_get_contents($filePath);

    //         $escaped_table_name = preg_quote($tableName, '/');
    //         /**
    //          * Regex breakdown for $block_pattern:
    //          * - Schema::(?:create|table) : Matches "Schema::create" or "Schema::table" (non-capturing group).
    //          * - \s*\(\s*                : Matches opening parenthesis "(" with optional whitespace before and after.
    //          * - ['\"]                   : Matches opening single or double quote delimiter around table name.
    //          * - {$escaped_table_name}   : Matches the exact target table name (escaped safely for regex).
    //          * - ['\"]                   : Matches closing single or double quote delimiter after table name.
    //          * - .*?\}\);                : Matches everything inside the schema block up to its closing "});" (dotall mode).
    //          */
    //         $block_pattern = "/Schema::(?:create|table)\s*\(\s*['\"]{$escaped_table_name}['\"].*?\}\);/s";

    //         if (preg_match($block_pattern, $content, $block_match)) {
    //             $target_schema_block = $block_match[0];

    //             /**
    //              * Regex breakdown:
    //              * - \\\$table-> : Matches literal "$table->" prefix.
    //              * - \w+         : Matches method name, e.g., "string", "integer", "foreignId".
    //              * - \(\s*       : Matches opening parenthesis "(" and optional spaces.
    //              * - ['\"]       : Matches single or double quote opening delimiter.
    //              * - ([^'\"]+)   : Capture Group 1 -> Extracts column name, e.g., "name", "email".
    //              * - ['\"]       : Matches single or double quote closing delimiter.
    //              */
    //             preg_match_all("/\\\$table->\w+\(\s*['\"]([^'\"]+)['\"]/", $target_schema_block, $matches);

    //             if (!empty($matches[1])) {
    //                 foreach ($matches[1] as $colName) {
    //                     $existingColumns[] = $colName;
    //                 }
    //             }
    //         }
    //     }
    //     return array_unique($existingColumns);
    // }

    /**
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * *  [ DEV - FLOW ]      CREATE NEW
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     */
    public static function createNew(string $tableName): void
    {
        // Preparation Zone
        self::$tableName = $tableName;
        $isUser = ($tableName === 'users');
        self::$draftPath = __DIR__ . '/' . (
            $isUser ? '0_create_users_table.php' : '0_create_entity_table.php'
        );

        $paddingLength = strlen((string) Runner::MAX_MIGRATIONS);

        /**
         * Example: If entityCounter is 1 and MAX_MIGRATIONS is 100
         * Before: 1 -> After: '01'
         */
        $counterPadding = str_pad(
            (string) Runner::$entityCounter,
            $paddingLength,
            '0',
            STR_PAD_LEFT
        );

        self::$filePrefix = date('Y_m_d_His') . "_{$counterPadding}";
        self::$fileName = $isUser
            ? '0001_01_01_000000_create_users_table.php'
            : self::$filePrefix . "_create_{$tableName}_table.php";

        self::$destinationPath = (string) TargetManager::gen_path(
            'migrations/' . self::$fileName,
            'database'
        );

        // check path target_app/database/migrations/ if exist before copy()
        DataHelper::ensureDir(self::$destinationPath);

        // Validation Zone
        self::dieSameMigration($isUser);

        // Execution Zone
        self::makeFile();
    }

    /**
     * prevent making same migration same prefix in target app
     *
     * @param  bool $isUser true = users table ( = Laravel takes care of User table itself)
     * @return void
     */
    private static function dieSameMigration(bool $isUser): void
    {
        if (!file_exists(self::$draftPath)) {
            Logger::error("Error! Draft file not found at : " . self::$draftPath);
        }

        if ($isUser) {
            Logger::warning(" Found Laravel User Migration -- skipt this , cause Laravel takes care of User table itself");
            return;
        }

        $pattern = (string) TargetManager::gen_path(
            'migrations/' . self::$filePrefix . '_*.php',
            'database'
        );

        if (!empty(glob($pattern))) {
            Logger::error("--- CRITICAL: Migration conflict detected. ---\n"
                . "A file with prefix [" . self::$filePrefix . "] already exists in target app.");
        }
    }

    /**
     * Process migration draft in-memory, handle history backup, and write the final file.
     *
     * Steps:
     * 1. Read the draft file contents directly into memory.
     * 2. Process and update the migration schema in-memory using DBOption::makeLines.
     * 3. Move any existing destination file to history storage.
     * 4. Write the final processed content to the destination path in a single step.
     */
    private static function makeFile(): void
    {
        // 1. Read draft file contents into memory
        $draftContent = file_get_contents(self::$draftPath);

        if ($draftContent === false) {
            Logger::error("Error! Failed to read draft file from : " . self::$draftPath);
        }

        // 2. Process migration content in-memory (In-Memory Processing First)
        $processedContent = DBOption::makeLines($draftContent, self::$tableName);

        // 3. Move old file to history if it already exists
        if (file_exists(self::$destinationPath)) {
            M_Historizer::move_old_file_to_history(self::$destinationPath);
        }

        // 4. Write the final processed content to the destination path
        if (is_writable(dirname(self::$destinationPath))) {
            file_put_contents(self::$destinationPath, $processedContent);

            Logger::success("--- Maker: Successfully created new migration: " . self::$fileName . " ---\n\n");

            echo "\n";
            echo "┌" . str_repeat("=", 48) . "┐\n";
            echo "|" . str_pad("SUCCESSFUL", 48, " ", STR_PAD_BOTH) . "|\n";
            echo "└" . str_repeat("=", 48) . "┘\n";
            echo "\n";
        } else {
            Logger::error("Error! Directory is not writable: " . dirname(self::$destinationPath));
        }


    }
}
