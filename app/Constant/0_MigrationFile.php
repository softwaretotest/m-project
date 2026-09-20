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

        self::$destinationPath = (string)TargetManager::gen_path('migrations/' . self::$fileName, 'database');

        // self::$destinationPath = __DIR__ . "/../../database/migrations/" . self::$fileName;


        // Validation Zone
        self::dieSameMigration($isUser);

        // Execution Zone
        self::makeFile($isUser);
    }

    private static function dieSameMigration(bool $isUser): void
    {
        if (!file_exists(self::$draftPath)) {
            die("\n--- Maker: Error! Draft file not found at [" . self::$draftPath . "] ---\n\n");
        }

        if (!$isUser) {
            $existingFiles = glob(__DIR__ . "/../../database/migrations/" . self::$filePrefix . "_*.php");
            if (!empty($existingFiles)) {
                die("\n--- CRITICAL: Migration conflict detected. ---"
                    . "\nA file with prefix [" . self::$filePrefix . "] already exists."
                    . "\n\n");
            }
        }
    }

    private static function makeFile($isUser): void
    {
        echo "--- Maker: Found draft file. Copying to migrations directory... ---\n\n";

        if (copy(self::$draftPath, self::$destinationPath)) {
            echo "--- Maker: Successfully created new migration: " . self::$fileName . " ---\n\n";
            // if (!$isUser) {
            MakeMigration::replaceExisting(self::$destinationPath, self::$tableName);
            // }
            // output successful
            echo "\n";
            echo "╔" . str_repeat("═", 48) . "╗\n";
            echo "║" . str_pad("SUCCESSFUL", 48, " ", STR_PAD_BOTH) . "║\n";
            echo "╚" . str_repeat("═", 48) . "╝\n";
            echo "\n";
        } else {
            echo "\n--- Maker: Error! Failed to copy migration file. ---\n\n";
        }
    }
}
