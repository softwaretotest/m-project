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

    public static function createNew(string $tableName): void
    {
        // Preparation Zone
        self::$tableName = $tableName;
        $isUser = ($tableName === 'users');
        self::$draftPath = __DIR__ . '/' . ($isUser ? '0_create_users_table.php' : '0_create_entity_table.php');

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
