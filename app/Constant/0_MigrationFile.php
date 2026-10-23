<?php

namespace App\Constant;

//0_MigrationFile.php

class MigrationFile
{
    public static string $draftFilePath;

    private static string $destinationPath;
    private static string $fileName;
    private static string $filePrefix;

    public static array $alter_fields;

    /**
     * ```text
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * *  [ PROD - FLOW ]      ADD NEW FIELDS
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    */
    public static function add_new_Field(): void
    {
        $draftFilePath = __DIR__ . '/0_add_new_fields_to_entity_table.php';
        self::set_config_and_makeFile(
            $draftFilePath,
            function () {
                return self::$filePrefix . "_add_new_fields_to_".Maker::$tableName."_table.php";
            }
        );
    }

    /**
     * ```text
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * *  [ PROD - FLOW ]      ALTER FIELDS
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    */
    public static function alter_Field(): void
    {
        Echo_::printBox('---- ALTER FIELDS FLOW TRIGGERED ----');

        $draftFullPath = __DIR__ . '/0_alter_fields_to_entity_table.php';

        self::set_config_and_makeFile(
            $draftFullPath,
            function () {
                return self::$filePrefix . "_alter_fields_to_".Maker::$tableName."_table.php";
            }
        );
    }

    /**
     * ```text
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * *  [ DEV - FLOW ]      CREATE NEW
     * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     */
    public static function createNew(): void
    {
        $isUser = (Maker::$tableName === 'users');
        $draftFilePath = __DIR__ . '/' . (
            $isUser ? '0_create_users_table.php' : '0_create_entity_table.php'
        );

        self::set_config_and_makeFile(
            $draftFilePath,
            function () use ($isUser) {
                return $isUser
                    ? '0001_01_01_000000_create_users_table.php'
                    : self::$filePrefix."_create_".Maker::$tableName."_table.php";
            },
            $isUser
        );

    }

    /**
     * set all Config = static Vars of Class MigrationFile
     */
    private static function set_config_and_makeFile(
        string $draftFilePath,
        callable $get_fileName,
        bool $isUser = false
    ): void {

        self::$draftFilePath = $draftFilePath;

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
        if (Runner::$mode === 'DEV') {
            self::dieSameMigration($isUser);
        }

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
        if (!file_exists(self::$draftFilePath)) {
            Logger::error("Draft file not found at : " . self::$draftFilePath);
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
     * 2. Process and update the migration schema in-memory
     *  ==> 2.1 GARD : DO NOT make any migration file, if no new line to add
     * 3. Move any existing destination file to history storage.
     * 4. Write the final processed content to the destination path in a single step.
     */
    private static function makeFile(): void
    {
        // 1. Read draft file contents into memory
        $draftContent = file_get_contents(self::$draftFilePath);

        if ($draftContent === false) {
            Logger::error("Error! Failed to read draft file from : " . self::$draftFilePath);
        }

        /**
         * this must be call at least once, even when it is alter_fields Flow
         * to find out if alter_field exist
         */
        $processedContent = DBOption::makeLines($draftContent);

        // 2. Process migration content in-memory (In-Memory Processing First)
        // FLOW alter_fields , if $alter_fields exists
        if (!empty(self::$alter_fields)) {
            $processedContent = self::process_Alter_Fields_Content($draftContent);
        }

        // 2.1 GARD : DO NOT make any migration file, if no new line and no alter field to add
        if (empty(DBOption::$allNewLines) && empty(self::$alter_fields)) {
            return;
        }

        // 3. Move old file to history if it already exists (for PROD FLOW , DEV FLOW unlink(file) at MakeMigration)
        if (file_exists(self::$destinationPath)) {
            M_Historizer::move_old_file_to_history(self::$destinationPath);
        }

        // 4. Write the final processed content to the destination path
        if (is_writable(dirname(self::$destinationPath))) {
            file_put_contents(self::$destinationPath, $processedContent);

            Logger::success("--- Maker: Successfully created new migration: " . self::$fileName . " ---\n\n");

            Echo_::printBox('                   SUCCESSFUL                  ');
        } else {
            Logger::error("Error! Directory is not writable: " . dirname(self::$destinationPath));
        }
    }

    /**
     *
     */
    private static function process_Alter_Fields_Content(string $draftContent): string
    {
        $draftContent = str_replace('tablename', Maker::$tableName, $draftContent);
        $lines = explode("\n", $draftContent);

        Logger::warning('self::$alter_fields = '.print_r(self::$alter_fields, true));

        $cleanAlterFields = [];
        /**
         * Loop through all alter fields to filter out numeric keys
         * before
            [
                [1] => $table->string('name')
                [2] => $table->string('email')
                [is_active] => $table->boolean('is_active')
            ]
        * after
            [
                [is_active] => $table->boolean('is_active')
            ]
         */
        foreach (self::$alter_fields as $key => $value) {
            if (!is_numeric($key)) {
                $cleanAlterFields[$key] = $value;
            }
        }

        $upLines = [];
        $downLines = [];

        foreach ($cleanAlterFields as $fieldName => $data) {
            if (is_array($data)) {
                if (!empty($data['up'])) {
                    $upLines[] = "            " . rtrim(trim($data['up']), ';') . "->change();";
                }
                if (!empty($data['down'])) {
                    $downLines[] = "            " . rtrim(trim($data['down']), ';') . "->change();";
                }
            }
        }

        // Step 1: 1st find placeholder '//' (in function up()) and replace line
        foreach ($lines as $index => $line) {
            if (strpos($line, '//') !== false) {
                array_splice($lines, $index, 1, $upLines);
                break;
            }
        }

        // Step 2: 1st find placeholder '//' (in function down()) and replace line
        foreach ($lines as $index => $line) {
            if (strpos($line, '//') !== false) {
                array_splice($lines, $index, 1, $downLines);
                break;
            }
        }

        return implode("\n", $lines);
    }
}
