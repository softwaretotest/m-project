<?php

namespace App\Constant;

require __DIR__ . '/../../vendor/autoload.php';

use ReflectionClass;

/**
 * * Laravel specific Models/User.php
 * * Laravel 3.32 change User.php like this :
 * *
 * * use Illuminate\Foundation\Auth\User as Authenticatable;
 * *
 * * #[Fillable(['name', 'email', 'password', 'is_active', 'image'])]
 * * #[Hidden(['password', 'remember_token'])]
 * * class User extends Authenticatable{}
 * *
 * * so we need to update script to insert fillable fields
 */
class EntityGenerator_UserModel
{
    /**
     * create/update User Model of target project
     *
     * @param array $fields 
     * * e.g. for table User
     * * Array
     * * (
     * *     [0] => Array
     * *         (
     * *             [0] => name
     * *             [1] => Array
     * *                 (
     * *                    [0] => string
     * *                    [1] => 255
     * *                 )
     * *             [2] => text
     * *             [3] => required
     * *         )
     * *    [1] => Array
     * *        (
     * *             [0] => email
     * *             [1] => string
     * *             [2] => text
     * *             [3] => unique
     * *        )
     * *     [2] => Array
     * *         (
     * *             [0] => is_active
     * *             [1] => boolean
     * *             [2] => select
     * *             [3] => Array
     * *                 (
     * *                     [0] => default
     * *                     [1] => 1
     * *                 )
     * *         )
     * *     [3] => Array
     * *         (
     * *             [0] => image
     * *             [1] => Array
     * *                (
     * *                     [0] => string
     * *                     [1] => 255
     * *                 )
     * *             [2] => file
     * *             [3] => Array
     * *                 (
     * *                     [0] => default
     * *                     [1] =>
     * *                 )
     * *         )
     * * )
     */
    public static function generateUserModel($fields): void
    {
        $target_User_Model = (string) TargetManager::gen_path("Models/User.php");

        // ---------- 0) check target folder /Models  ----------
        $dir = dirname($target_User_Model);
        DataHelper::ensureDir($dir);

        // ---------- 0) check stub  ----------
        $stubPath = __DIR__ . '/Stub/user.model.stub';
        if (!file_exists($stubPath)) {
            Logger::error("Stub not found: {$stubPath}");
        }

        // ---------- 1) create app/Models/User.php from stub if not exists ----------
        if (!file_exists($target_User_Model)) {
            $result = file_put_contents($target_User_Model, file_get_contents($stubPath));
            if ($result === false) {
                Logger::error("Could not create file : $target_User_Model");
            }
            clearstatcache(true, $target_User_Model);
        }

        // ---------- 2) read user.model.stub ----------
        $old_code = file_get_contents($target_User_Model);
        if ($old_code === false || trim($old_code) === '') {
            Logger::error("Empty User Model or cannot read file: {$target_User_Model}");
        }

        // ---------- 3) normalize fields ----------
        $newFields = self::normalizeFieldNames($fields);
        if (empty($newFields)) {
            Logger::warning("No valid fields to merge for User model.");
            return; // if $fields is empty no further action needed
        }

        $updated_code = self::mergeFillable($old_code, $newFields);
        if ($updated_code === $old_code) {
            Logger::warning("No valid fields to merge for User model.");
        }

        // ---------- 4) merge and  ----------
        $result = file_put_contents($target_User_Model, $updated_code, LOCK_EX);
        if ($result) {
            Logger::success("User's fillable fields = " . implode(' , ', $newFields));
            Logger::success("Created User Model : $target_User_Model");
            clearstatcache(true, $target_User_Model);
        } else {
            Logger::error("Could not create User Model : $target_User_Model");
        }

        Logger::finish();
    }

    /**
     * * e.g. nomarlizeFieldNames for tabel User
     * @param see function generateUserModel
     * @return 
     * *    Array
     * *   (
     * *       [0] => name
     * *       [1] => email
     * *       [2] => is_active
     * *       [3] => image
     * *   )
     */
    private static function normalizeFieldNames($fields): array
    {
        $out = [];

        foreach ((array) $fields as $key => $value) {

            // CASE f::NAME / s::EMAIL  →  ['name', d::STRING, ...]
            if (is_array($value)) {
                $candidate = $value[0] ?? ($value['name'] ?? null);

                // if Array in side Array
                while (is_array($candidate)) {
                    $candidate = $candidate[0] ?? null;
                }

                if (is_string($candidate) && $candidate !== '') {
                    $out[] = $candidate;
                }
                continue;
            }

            // CASE string →  'is_active'
            if (is_string($value)) {
                $out[] = $value;
                continue;
            }

            // CASE ['is_active' => 'boolean']
            if (is_string($key)) {
                $out[] = $key;
            }
        }

        // step 1: remove duplicated field names
        $uniqueFields = array_unique($out);

        // step 2: keep only field names that pass the validation rule
        $validFields = [];

        foreach ($uniqueFields as $fieldName) {
            if (!self::isAllowedFieldName($fieldName)) {
                continue;
            }
            $validFields[] = $fieldName;
        }

        // step 3: return the clean list (index is already re-numbered by [] assignment)
        return $validFields;
    }

    /**
     * Check whether a field name is allowed to be used in $fillable.
     *
     * @param mixed $fieldName Field name to validate
     * @param array $blocked   List of reserved system field names
     * @return bool
     */
    private static function isAllowedFieldName($fieldName): bool
    {
        // list of system fields that must never be written into $fillable
        $blocked = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'];

        // rule 1: must be a non-empty string
        if (!is_string($fieldName) || $fieldName === '') {
            return false;
        }

        // rule 2: must not be a reserved system field
        if (in_array($fieldName, $blocked, true)) {
            return false;
        }

        return true;
    }

    /**
     * * e.g. newFields for Table User
     * @param  
     * *    Array
     * *   (
     * *       [0] => name
     * *       [1] => email
     * *       [2] => is_active
     * *       [3] => image
     * *   )
     * @return #[Fillable(['name', 'email', 'password', 'is_active', 'image'])]
     */
    private static function mergeFillable(string $code, array $newFields)
    {
        // --- A) Laravel 13.32 : #[Fillable([...])] ---
        $attrPattern = '/(#\[Fillable\(\s*\[)(.*?)(\]\s*\)\])/s';

        // // --- B) Laravel 13.15 : protected $fillable = [...]; ---
        // $propPattern = '/(\$fillable\s*=\s*\[)(.*?)(\]\s*;)/s';

        //handle replace fillable part $m[2] if found e.g. Normal Laravel User fields = 'name', 'email', 'password'
        if (preg_match($attrPattern, $code)) {
            return preg_replace_callback($attrPattern, function ($m) use ($newFields) {
                $merged = self::mergeList($m[2], $newFields);
                //change only in the middle
                return $m[1] . $merged . $m[3];
            }, $code, 1);
        } else {
            Logger::error("NO Fillable found in Models/User.php");
        }
    }

    /**
     * * e.g. nomarlizeFieldNames for tabel User
     * @param $newField see function generateUserModel
     * @param $laravel_user_fields_string e.g. 'name', 'email', 'password'
     * 
     * @return 
     * *    Array
     * *   (
     * *       [0] => name
     * *       [1] => email
     * *       [2] => is_active
     * *       [3] => image
     * *   )
     */
    private static function mergeList(string $laravel_user_fields_string, array $newFields): string
    {
        // get the first part of fillable  #[Fillable([
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $laravel_user_fields_string, $m);
        $existing = $m[1];

        /**
         * * array_values correct Index (Keys) after array_merge 
         * * e.g.
         * * before [0 => 'name', 1 => 'email', 3 => 'is_active']
         * * after  [0 => 'name', 1 => 'email', 2 => 'is_active']
         */
        $merged = array_values(
            array_unique(
                array_merge($existing, $newFields)
            )
        );

        // if no change → do not format anything
        if ($merged === $existing) {
            return $laravel_user_fields_string;
        }

        // handel quote ' or "
        // 1. check Double Quote (") 
        $hasDoubleQuote = strpos($laravel_user_fields_string, '"') !== false;

        // 2. check Single Quote (') 
        $hasNoSingleQuote = strpos($laravel_user_fields_string, "'") === false;

        // 3. choose the Quote charater
        if ($hasDoubleQuote && $hasNoSingleQuote) {
            $quote = '"'; // use Double Quote like Laravel use
        } else {
            $quote = "'"; // else use Single Quote 
        }

        /**
         * * indent = Einrücken (Tabulator-Zeichen)
         * * if has NewLine then keep it
            protected $fillable = [
                'name',  // ใช้ $indent (8 spaces)
                'email', // ใช้ $indent (8 spaces)
            ];           // ใช้ $closeIndent (4 spaces)
         */
        if (strpos(trim($laravel_user_fields_string), "\n") !== false) {
            preg_match('/\n(\s+)/', $laravel_user_fields_string, $indent_array);
            $indent = $indent_array[1] ?? '        ';
            $closeIndent = substr($indent, 0, max(0, strlen($indent) - 4));

            $lines = [];
            foreach ($merged as $fieldName) {
                $lines[] = self::formatFieldLine($fieldName, $indent, $quote);
            }
            return "\n" . implode("\n", $lines) . "\n" . $closeIndent;
        }

        /**
         * * for inline
         * * protected $fillable = ['name', 'email']; 
         */
        $inlineItems = [];
        foreach ($merged as $fieldName) {
            $inlineItems[] = self::formatFieldInline($fieldName, $quote);
        }
        return implode(', ', $inlineItems);
    }

    /**
     * Format a single field name with indentation, quotes, and a comma.
     * 
     * @param string $fieldName The field name (e.g., 'name')
     * @param string $indent    The spacing/indentation (e.g., '    ')
     * @param string $quote     The quote character (' or ")
     * @return string           Formatted line (e.g., "    'name',")
     * * protected $fillable = [
     * *            'name',  // ใช้ $indent (8 spaces)
     * *            'email', // ใช้ $indent (8 spaces)
     * *        ];  
     */
    private static function formatFieldLine(string $fieldName, string $indent, string $quote): string
    {
        return $indent . $quote . $fieldName . $quote . ',';
    }

    /**
     * Format a single field name as inline without indentation and comma.
     * 
     * @param string $fieldName The field name (e.g., 'name')
     * @param string $quote     The quote character (' or ")
     * @return string           Formatted inline field (e.g., "'name'")
     * * protected $fillable = ['name', 'email'];  
     */
    private static function formatFieldInline(string $fieldName, string $quote): string
    {
        return $quote . $fieldName . $quote;
    }
}
