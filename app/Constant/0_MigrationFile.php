<?php

namespace App\Constant;

//0_MigrationFile.php

class MigrationFile
{
    private static string $tableName;
    private static string $draftFullPath;
    private static string $destinationPath;
    private static string $fileName;
    private static string $filePrefix;

    /**
     * ```text
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * *  [ PROD - FLOW ]      ADD NEW FIELDS
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * @param $new_fields = e.g.
     * ```php
            [users] => Array
                (
                    [0] => is_active
                    [1] => image
                )
     * ```
    */
    public static function add_new_Field(string $tableName, array $new_fields): void
    {

        // PROD FLOW
        Echo_::printBox_Emoji(' [ ✅😊😘🩷 ] NO_old_migration_file = FALSE');

        $draftFullPath = __DIR__ . '/0_add_new_fields_to_entity_table.php';
        self::set_config(
            $draftFullPath,
            $tableName,
            function () use ($tableName) {
                return self::$filePrefix . "_add_new_fields_to_{$tableName}_table.php";
            }
        );
    }

    /**
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * *  [ DEV - FLOW ]      CREATE NEW
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     */
    public static function createNew(string $tableName): void
    {
        $isUser = ($tableName === 'users');
        $draftFullPath = __DIR__ . '/' . (
            $isUser ? '0_create_users_table.php' : '0_create_entity_table.php'
        );

        self::set_config(
            $draftFullPath,
            $tableName,
            function () use ($isUser, $tableName) {
                return $isUser
                    ? '0001_01_01_000000_create_users_table.php'
                    : self::$filePrefix."_create_{$tableName}_table.php";
            },
            $isUser
        );

    }

    /**
     * set all Config = static Vars of Class MigrationFile
     */
    private static function set_config(
        string $draftFullPath,
        string $tableName,
        callable $get_fileName,
        bool $isUser = false
    ): void {
        self::$tableName = $tableName;

        self::$draftFullPath = $draftFullPath;

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

        self::$fileName = $get_fileName(self::$filePrefix);

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
        if (!file_exists(self::$draftFullPath)) {
            Logger::error("Error! Draft file not found at : " . self::$draftFullPath);
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
        $draftContent = file_get_contents(self::$draftFullPath);

        if ($draftContent === false) {
            Logger::error("Error! Failed to read draft file from : " . self::$draftFullPath);
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
