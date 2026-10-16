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
     * * &$processedFields ( & = passed by Ref )
     * * So, processedFields must be handled by both functions updateLines() and addMissingLines()
     * * no matter if ($tableName === 'users') or not
     * * to complete the make Lines Process correctly
     * * without this line :
     * *    $lines = self::updateLines($lines, $processedFields, $tableName, $dbSchema);
     * * 0_Runner.php will add multiple same line like this:
     * *
     * *      $table->string('name');                     // from original Laravel migration
     * *      $table->string('name', 255)->required();    // from M_Project UserConstant.php
     * *
     */
    public static function makeLines(array $lines, array &$processedFields, string $tableName, array $dbSchema): array
    {
        /**
         * * * SPECIAL CASE users table in Laravel :
         * * do not remove this, even the code look unnecessary
         * * 1. Update existing lines
         **/
        $lines = self::updateLines($lines, $processedFields, $tableName, $dbSchema);

        // 2. Add missing lines
        self::addMissingLines($lines, $processedFields, $dbSchema);

        return $lines;
    }

    /**
     * * [BEHAVIOR: Overwrite / Replace Existing Fields]
     * * Loop all lines of Migration file and replace if matches DBOption found
     */
    private static function updateLines(array $lines, array &$duplicateFields, string $tableName, array $dbSchema): array
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

            foreach ($dbSchema as $fieldName => $dbOptions) {
                if (strpos($line, "'{$fieldName}'") !== false) {
                    /**
                     * * * SPECIAL CASE users table in Laravel :
                     * * this line overwrite existing Laravel migration methode
                     * * e.g.
                     * *    $table->string('name');
                     * *    $table->string('email')->unique();
                     * *    ... etc.
                     * * So, this must be commented out // $lines[$index] = ....
                     */
                    // $lines[$index] = "            " . self::makeLine($fieldName, $dbOptions) . ";";

                    /**
                     * * but, we still need to set
                        $duplicateFields[] = $fieldName;
                     * * to make to make function makeLines() works correctly
                     * * otherwise it can overwrite laravel methode
                     * * e.g. from $table->string('name', 255) to $table->decimal('name', 10, 2);
                     * * and can cause error
                     */
                    $duplicateFields[] = $fieldName;
                }
            }
        }
        if (false === empty($duplicateFields)) {
            Logger::warning(" Migration found same fields on table : $tableName  = [ ".implode(', ', $duplicateFields) ."]");
            Logger::error(" same field will not be overwritten , please correct Entities.json , remove the same fields!!!");
        }
        return $lines;
    }

    /**
     * 1. make new migration line if not exits , separate foreign key
     * 2. order foreing key to the end
     * 3. place $allNewLines over $table->timestamps();
     */
    private static function addMissingLines(array &$lines, array $processedFields, array $dbSchema): void
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
            if (!in_array($fieldName, $processedFields)) {
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
                    array_splice($lines, $index, 0, $allNewLines);
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
