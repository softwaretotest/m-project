<?php

namespace App\Constant;

//0_DBOption.php

/**
 * factory for $lines (content) for new MigrationFile
 * using $dbSchema
 */
class DBOption
{
    public static array $allNewLines;

    private static array $duplicateFields;

    /**
     * * Main entry to process all lines in a migration file.
     * 1. Update existing lines
     * 2. Add missing lines
     * * ----------------------------------------------------
     * * VERY IMPORTAINT NOTE !!!
     * * &$duplicateFields ( & = passed by Ref )
     * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * * So, duplicateFields must be handled by both functions :
     * *    - find_Duplicate_Fieldname_in_dbSchema()
     * * and then :
     * *    - addMissingLines()
     * * no matter if ($tableName === 'users') or not
     * * to complete the make Lines Process correctly
     * * without this line :
     * *    $lines = self::find_Duplicate_Fieldname_in_dbSchema();
     * * 0_Runner.php will add multiple same line like this:
     * *
     * *      $table->string('name');                     // from original Laravel migration
     * *      $table->string('name', 255)->required();    // from M_Project UserConstant.php
     * *
     */
    public static function makeLines(string $draftContent): string
    {
        $draftContent = str_replace('tablename', Maker::$tableName, $draftContent);

        $lines = explode("\n", $draftContent);
        self::$duplicateFields = [];

        /**
         * * * SPECIAL CASE users table in Laravel :
         * * do not remove this, even the code look unnecessary
         * * 1. Update existing lines
         **/
        $allMigrationFiles = MakeMigration::get_allMigrationFiles(Maker::$tableName);
        $schemaLines = [];
        foreach ($allMigrationFiles as $filePath) {
            $new_schemaLines = MakeMigration::extract_schema_lines_from_file(
                $filePath,
                Maker::$tableName
            );
            Logger::warning('new_schemaLines = '.print_r($new_schemaLines, true));
            $schemaLines = array_merge($schemaLines, $new_schemaLines);
        }

        Logger::warning('schemaLines = '.print_r($schemaLines, true));

        self::find_Duplicate_Fieldname_in_dbSchema($schemaLines);

        self::find_alter_lines($schemaLines);

        // 2. Add missing lines
        self::addMissingLines($lines);

        return implode("\n", $lines);
    }

    private static function find_alter_lines(array $schemaLines): void
    {
        // 1. ดึงชื่อฟิลด์และบรรทัดเดิมจากไฟล์เก่าเก็บไว้
        $existing = [];
        foreach ($schemaLines as $line) {
            if (preg_match("/->\w+\('([^']+)'/", $line, $match)) {
                $existing[$match[1]] = $line;
            }
        }

        // 2. รีเซ็ตค่าเก่าทิ้งก่อนเริ่มรอบใหม่
        MigrationFile::$alter_fields = [];

        // 3. วนลูปเทียบข้อมูลเฉพาะฟิลด์ที่มีการเปลี่ยนแปลง
        foreach (array_unique(self::$duplicateFields) as $fieldName) {
            $target = self::makeLine($fieldName, MakeMigration::$dbSchema[$fieldName]);
            $old = $existing[$fieldName] ?? '';

            if (self::normalize_line($target) !== self::normalize_line($old)) {
                // กลับมาใช้โครงสร้างเดิมที่มีทั้ง up และ down ครบถ้วนตามมาตรฐาน Laravel
                MigrationFile::$alter_fields[$fieldName] = [
                    'up'   => $target,
                    'down' => $old,
                ];
            }
        }
    }

    private static function normalize_line(string $line): string
    {
        $line = preg_replace('/\s+/', '', $line);
        return str_replace(['->change()', ';', '"'], ['', '', "'"], $line);
    }

    /**
     * * Loop all lines of Migration file --> Loop DbSchema
     * * and collect duplicate fieldname in duplicateFields[]
     * * if FOUND duplicate fieldname , then throw ERROR and stop for user to correct this
     * * NOTE : m-project does not allow multiple same fieldname in all laravel migration files
     */
    private static function find_Duplicate_Fieldname_in_dbSchema(array $schemaLines)
    {
        $inTable = false;
        foreach ($schemaLines as $index => $line) {

            foreach (array_keys(MakeMigration::$dbSchema) as $fieldName) {
                if (strpos($line, "'{$fieldName}'") !== false) {
                    // collect duplicate line
                    self::$duplicateFields[] = $fieldName;
                }
            }
        }
    }

    /**
     * 1. make new migration line if not exits , separate foreign key
     * 2. order foreing key to the end
     * 3. place $allNewLines over $table->timestamps();
     */
    private static function addMissingLines(array &$lines): void
    {
        $normalLines = [];
        $foreignLines = [];

        /* * 1. make new migration line if not exits , separate foreign key
         * * fieldName = confirm_order ,  =>
         * *          dbOption = Array
         * *         (
         * *             [0] => boolean
         * *             [1] => Array
         * *                 (
         * *                     [0] => default
         * *                     [1] => true
         * *                 )
         * *        )
         */
        foreach (MakeMigration::$dbSchema as $fieldName => $dbOptions) {
            // echo "  fieldName = $fieldName ,  =>  \n\n";
            // echo "  dbOption = ";
            // echo print_r($dbOptions);
            // echo "\n-------END-----------\n\n";
            if (!in_array($fieldName, self::$duplicateFields)) {  // Condition for New Field only !!!
                $newLine = self::makeLine($fieldName, $dbOptions);
                if ($dbOptions[0] === 'foreign') {
                    $foreignLines[] = "            " . $newLine . ";";
                } else {
                    $normalLines[] = "            " . $newLine . ";";
                }
            }
        }

        // 2. order foreing key to the end
        self::$allNewLines = array_merge($normalLines, $foreignLines);

        // 3. place $allNewLines over $table->timestamps();
        if (!empty(self::$allNewLines)) {

            $draftText = implode("\n", $lines);
            $isCreateDraft = strpos($draftText, 'Schema::create(') !== false;
            if ($isCreateDraft) { //if (Runner::$mode === 'DEV' or 'PROD' but no duplicate fieldname found) {
                foreach ($lines as $index => $line) {
                    if (strpos($line, '$table->timestamps();') !== false) {
                        /**
                         * example of splice :
                        $letters = ['A', 'B', 'E'];
                        array_splice($letters, 2, 0, ['C', 'D']);
                                // 2 = position to add ,
                                // 0 = count of item to delete
                        $letters = ['A', 'B', 'C', 'D', 'E'];
                        */
                        array_splice($lines, $index, 0, self::$allNewLines);
                        break;
                    }
                }
            } else { //if (Runner::$mode === 'PROD') {
                self::apply_prod_migration_lines($lines, self::$allNewLines);
            }
        }
    }

    /**
     * Applies up() missing lines and down() dropColumn lines into PROD migration file.
     *
     * @param array $lines Reference to migration lines array.
     * @param array $allNewLines Array of generated schema lines for up().
     * @param array self::$duplicateFields Fields already generated or existing in previous migrations.
     * @return void
     */
    private static function apply_prod_migration_lines(array &$lines, array $allNewLines): void
    {
        $flipped_processed_fields = array_flip(self::$duplicateFields);
        $diff_schema_keys = array_diff_key(MakeMigration::$dbSchema, $flipped_processed_fields);
        $new_field_names = array_keys($diff_schema_keys);

        $dropLine = [];
        if (!empty($new_field_names)) {
            $imploded_fields = implode("', '", $new_field_names);
            $formatted_fields = "'" . $imploded_fields . "'";
            $drop_column_statement = "\$table->dropColumn([{$formatted_fields}]);";
            $dropLine = [
                "            " . $drop_column_statement
            ];
        }

        // Step 1: Replace '//' in up() section
        foreach ($lines as $index => $line) {
            $has_placeholder_comment = (strpos($line, '//') !== false);
            if ($has_placeholder_comment) {
                array_splice($lines, $index, 1, $allNewLines);
                break;
            }
        }

        // Step 2: Replace '//' in down() section (Re-scan $lines with updated array structure)
        if (!empty($dropLine)) {
            foreach ($lines as $index => $line) {
                $has_placeholder_comment = (strpos($line, '//') !== false);
                if ($has_placeholder_comment) {
                    array_splice($lines, $index, 1, $dropLine);
                    break;
                }
            }
        }
    }

    /**
     * @param string $fieldName
     * @param array $dbOptions
     * * from JSON = e.g., ['d::BOOLEAN', [cd::DEFAULT, true] )
     * *-------------------------------------------------------------------------
     * * from PHP = e.g.
        fieldName = confirm_order ,  =>
         dbOption = Array
         (
             [0] => boolean
             [1] => Array
                 (
                     [0] => default
                     [1] => true
                 )
        )
     */
    public static function makeLine(string $fieldName, array $dbOptions): string
    {
        // 1. Extract Type: assume first element is always the type definition
        $typeDef = array_shift($dbOptions);

        // Handle Foreign
        if ($typeDef === 'foreign') {
            echo $fieldName . "\n";
            $targetTable = str_replace('_id', 's', $fieldName);
            // $onDelete = 'cascade'; // better not use cascade on production , can delete many data by accident
            $onDelete = 'restrict';
            return "\$table->foreignId('{$fieldName}')->constrained('{$targetTable}')->onDelete('{$onDelete}')";
        }

        // 2. Identify the Method Name (Type)
        // If $typeDef is array [d::DECIMAL, 10, 2], Method is 'decimal'
        // If $typeDef is string 'string', Method is 'string'
        $method = is_array($typeDef) ? $typeDef[0] : $typeDef;

        // 3. Build base line
        $line = "\$table->{$method}('{$fieldName}'";

        // If $typeDef was array with params (like decimal, 10, 2), add them
        if (is_array($typeDef) && count($typeDef) > 1) {
            $params = array_slice($typeDef, 1);
            $line .= ', ' . implode(', ', $params);
        }
        $line .= ")"; //close the method call

        // 4. Append additional constraints
        foreach ($dbOptions as $option) {
            $line .= self::format($option);
        }

        /**
         * check if line exists in old migration file,
         * if not note the new line in alter_fields
         * for Alter_Flow ==> will be check again at makeFile()
         */
        // Logger::warning('$line = '.$line);
        // Logger::warning(
        //     '$all_existing_dbSchema_of_allMigrationFiles = '.
        //     print_r(MakeMigration::$all_existing_dbSchema_of_allMigrationFiles, true)
        // );
        // if (!in_array($line, MakeMigration::$all_existing_dbSchema_of_allMigrationFiles)) {
        //     Echo_::printBox_Emoji('✅😊✅✅😊✅✅😊✅✅😊✅ adding $allter_field[] = $line = '.$line);
        //     MigrationFile::$alter_fields[] = $line;
        // }

        return $line;
    }

    /**
     * * format to laravel migration methode
     * @example ->nullable()
     * @example ->default(0)
     * @example ->decimal(10, 2)
     */
    public static function format(string|array $option): string
    {
        // 1. Handle String
        if (is_string($option)) {
            return "->{$option}()";
        }

        // 2. Handle Array
        if (is_array($option)) {
            $method = array_shift($option);
            $params = [];

            foreach ($option as $val) {
                if (is_bool($val)) {
                    $params[] = $val ? 'true' : 'false';
                } elseif (is_string($val)) {
                    $params[] = "'{$val}'";
                } else {
                    $params[] = $val;
                }
            }

            $paramString = implode(', ', $params);
            return "->{$method}({$paramString})";
        }

        return "";
    }
}
