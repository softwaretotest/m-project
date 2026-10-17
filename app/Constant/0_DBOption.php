<?php

namespace App\Constant;

//0_DBOption.php

class DBOption
{
    /**
     * * Main entry to process all lines in a migration file.
     * 1. Update existing lines
     * 2. Add missing lines
     * * ----------------------------------------------------
     * * VERY IMPORTAINT NOTE !!!
     * * &$duplicateFields ( & = passed by Ref )
     * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * * So, duplicateFields must be handled by both functions :
     * *    - find_Duplicate_Field_dbSchema()
     * * and then :
     * *    - addMissingLines()
     * * no matter if ($tableName === 'users') or not
     * * to complete the make Lines Process correctly
     * * without this line :
     * *    $lines = self::find_Duplicate_Field_dbSchema($lines, $duplicateFields, $tableName, $dbSchema);
     * * 0_Runner.php will add multiple same line like this:
     * *
     * *      $table->string('name');                     // from original Laravel migration
     * *      $table->string('name', 255)->required();    // from M_Project UserConstant.php
     * *
     */
    public static function makeLines(string $draftContent, string $tableName): string
    {
        $draftContent = str_replace('tablename', $tableName, $draftContent);

        $lines = explode("\n", $draftContent);
        $duplicateFields = [];

        /**
         * * * SPECIAL CASE users table in Laravel :
         * * do not remove this, even the code look unnecessary
         * * 1. Update existing lines
         **/
        self::find_Duplicate_Field_dbSchema(
            $lines,
            $duplicateFields,
            $tableName,
            MakeMigration::$dbSchema
        );

        // 2. Add missing lines
        self::addMissingLines(
            $lines,
            $duplicateFields,
            MakeMigration::$dbSchema
        );

        return implode("\n", $lines);
    }

    /**
     * * Loop all lines of Migration file --> Loop DbSchema
     * * and collect duplicate fieldname in duplicateFields[]
     * * if FOUND duplicate fieldname , then throw ERROR and stop for user to correct this
     * * NOTE : m-project does not allow multiple same fieldname in all laravel migration files
     */
    private static function find_Duplicate_Field_dbSchema(array $lines, array &$duplicateFields, string $tableName, array $dbSchema)
    {
        $inTable = false;
        foreach ($lines as $index => $line) {
            /**
             * * this if check if we are still in
             * *    Schema::create('tablename', function (Blueprint $table) {
             * *       ..........content...............
             * *    });
             */
            if (strpos($line, "Schema::create('{$tableName}'") !== false) {
                $inTable = true;
            }
            if ($inTable && strpos($line, "});") !== false) {
                $inTable = false;
            }

            if ($inTable === false) {
                continue;
            }

            foreach (array_keys($dbSchema) as $fieldName) {
                if (strpos($line, "'{$fieldName}'") !== false) {
                    // collect duplicate line
                    $duplicateFields[] = $fieldName;
                }
            }
        }
        if (false === empty($duplicateFields)) {
            Logger::warning(Logger::ERROR." Migration found same fields on table : $tableName  = [ ".implode(', ', $duplicateFields) ."]");
            Logger::warning(Logger::ERROR." same field will not be overwritten , please do following :");
            Logger::warning(Logger::ERROR." 1. remove all same fields in Tab ENTITIES (of m-project UI)");
            Logger::warning(Logger::ERROR." 2. re-run all Sync_Service Scripts");
            // Flow stop here , until user correct duplicate manually in En
            Logger::error(Logger::ERROR." !!! DUPLICATE FIELD FOUND ===> STOP ALL !!!");
        }
    }

    /**
     * 1. make new migration line if not exits , separate foreign key
     * 2. order foreing key to the end
     * 3. place $allNewLines over $table->timestamps();
     */
    private static function addMissingLines(array &$lines, array $duplicateFields, array $dbSchema): void
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
        foreach ($dbSchema as $fieldName => $dbOptions) {
            // echo "  fieldName = $fieldName ,  =>  \n\n";
            // echo "  dbOption = ";
            // echo print_r($dbOptions);
            // echo "\n-------END-----------\n\n";
            if (!in_array($fieldName, $duplicateFields)) {
                $newLine = self::makeLine($fieldName, $dbOptions);
                if ($dbOptions[0] === 'foreign') {
                    $foreignLines[] = "            " . $newLine . ";";
                } else {
                    $normalLines[] = "            " . $newLine . ";";
                }
            }
        }

        // 2. order foreing key to the end
        $allNewLines = array_merge($normalLines, $foreignLines);

        // 3. place $allNewLines over $table->timestamps();
        if (!empty($allNewLines)) {
            foreach ($lines as $index => $line) {
                if (strpos($line, '$table->timestamps();') !== false) {
                    /**
                     * splice add lines before $table->timestamps(); // index, 0
                     * e.g. before splice :
                        Schema::create('tablename', function (Blueprint $table) {
                            $table->id();
                            $table->timestamps();
                        });
                     * e.g. after splice :
                        Schema::create('tablename', function (Blueprint $table) {
                            $table->id();
                            $table->string('name');
                            $table->decimal('price', 10, 2);
                            $table->integer('stock');
                            $table->timestamps();
                        });
                     */
                    array_splice($lines, $index, 0, $allNewLines); // 0 = do not remove any old item
                    /**
                     * example of splice :
                     $letters = ['A', 'B', 'E'];
                     array_splice($letters, 2, 0, ['C', 'D']);
                            // 2 = position to add ,
                            // 0 = count of item to delete
                     $letters = ['A', 'B', 'C', 'D', 'E'];
                     */
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
