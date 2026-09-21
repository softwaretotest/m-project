<?php

namespace App\Constant;

require __DIR__ . '/../../vendor/autoload.php';

use ReflectionClass;

/**
 * Laravel Layers Classes for Entities
 */
class EntityGenerator
{
    private static bool $base_dto_deployed = false;
    private static bool $base_controller_deployed = false;

    public static function runAll()
    {

        $directory = dirname(__DIR__, 3) . '/' . TargetManager::$activeTarget . '/app/Constant';

        $files = glob($directory . '/*Constant.php');

        if (count($files) === 0) {
            die("======== GENERS FAILED : NO app/Constant/*Contstant.php found at " . $directory);
        }

        $target_APP_DIR = __DIR__ . '/../../../' . TargetManager::$activeTarget;

        M_Historizer::move_old_file_to_history($target_APP_DIR . '/app/Http/Controllers/BaseController.php');
        M_Historizer::move_old_file_to_history($target_APP_DIR . '/app/DTOs/BaseDTO.php');

        foreach ($files as $file) {
            $className = basename($file, '.php');
            $filename = $className . '.php';
            if (!class_exists($className, false)) {
                // แปลงชื่อคลาสให้เป็น path แล้ว require เข้ามาซะก่อน
                $target_file = (string)TargetManager::gen_path('Constant/' . $filename);
                if (file_exists($target_file)) {
                    require_once $target_file;
                }
            }
            $fullClassName = "App\\Constant\\" . $className;
            $entityName = str_replace('Constant', '', $className);

            self::historize_entity_files($entityName, $target_APP_DIR);

            if (class_exists($fullClassName) && method_exists($fullClassName, 'fields')) {
                $tableName  = $fullClassName::TABLE_NAME;
                $fields     = $fullClassName::fields();

                echo "Generating: {$entityName} (Table: {$tableName})\n";
                self::generate($entityName, $fields);
            } else {
                echo "======== GENERS FAILED : \n\n" . " class_exists( " . $fullClassName . ") = ";
                echo class_exists($fullClassName) ? 'TRUE' : 'FALSE';
                die("\n");
            }
        }

        echo "Generated successfully!\n";
    }

    private static function historize_entity_files(string $entityName, string $target_APP_DIR)
    {
        M_Historizer::move_old_file_to_history($target_APP_DIR . '/app/Models/' . $entityName . ".php");
        M_Historizer::move_old_file_to_history($target_APP_DIR . '/app/DTOs/' . $entityName . "_DTO.php");
        M_Historizer::move_old_file_to_history($target_APP_DIR . '/app/Http/Controllers/' . $entityName . "_Controller.php");
    }

    public static function generate($entityName, $fields)
    {

        echo "\n=====================================\n";
        echo "\n    BEGIN ARCHIVE : " . $entityName . "\n";
        echo "\n=====================================\n";

        if ($entityName === 'User') {
            EntityGenerator_UserModel::generateUserModel($fields);
        } else {
            self::generateModel($entityName, $fields);
        }

        self::generateDTO($entityName, $fields);
        self::generateController($entityName, $fields);
    }

    private static function generateModel($entityName, $fields)
    {
        $stub = file_get_contents(__DIR__ . '/Stub/model.stub');
        $methods = "";
        $fillableArray = [];

        foreach ($fields as $field) {
            // 1. keep filename to make fillable
            $fieldName = is_array($field) ? $field[0] : $field;
            $fillableArray[] = "'{$fieldName}'";

            // 2. make Relation if Foreign Key
            if (is_array($field) && in_array(cd::FOREIGN, $field, true)) {
                echo "  - Generating relation method for foreign key: {$field[0]}\n";

                $relationName = str_replace('_id', '', $field[0]);
                $relatedClass = ucfirst($relationName);

                $methods .= "\n    public function {$relationName}(): \Illuminate\Database\Eloquent\Relations\BelongsTo\n";
                $methods .= "    {\n";
                $methods .= "        return \$this->belongsTo(\App\Models\\{$relatedClass}::class);\n";
                $methods .= "    }\n";
            }
        }

        // make string for fillable
        $fillableCode = "protected \$fillable = [" . implode(', ', $fillableArray) . "];";

        // replace class Dummy by Entity
        $output = str_replace('class Dummy', "class {$entityName}", $stub);

        // replace/insert fillable at // FILLABLE_HERE in stub
        $output = str_replace('// FILLABLE_HERE', $fillableCode, $output);

        // replace '}' as last char (for Methods)
        $pos = strrpos($output, '}');
        if ($pos !== false) {
            $output = substr($output, 0, $pos) . $methods . "}\n";
        }

        DataHelper::ensureDir((string)TargetManager::gen_path('Models'));
        file_put_contents((string)TargetManager::gen_path("Models/{$entityName}.php"), $output);
    }

    // -----------------------------------------------------------------
    /**
     * $fields = [f::NAME, f::PRICE, ...] 
     * e.g. ['price', ['decimal',10,2], 'number', ['default',0], 'currency']
     */
    private static function generateDTO($entityName, $fields)
    {
        self::deployBaseDTO();

        $stub = file_get_contents(__DIR__ . '/Stub/dto.stub');

        $propLines = [];
        $mapLines  = [];
        $arrLines  = [];
        $metaLines = [];

        foreach ($fields as $field) {
            $definition = is_array($field) ? $field : [$field];
            $name       = $definition[0];

            $meta     = Constant_APP_Reader::parse($definition);
            $fieldMeta = Constant_APP_Reader::getFieldMetadata($definition);

            $defaultLiteral = $meta['has_default']
                ? var_export($meta['default'], true)
                : 'null';

            $propLines[] = "public readonly ?{$meta['php_type']} \${$name} = {$defaultLiteral},";
            $mapLines[]  = "{$name}: \$data['{$name}'] ?? {$defaultLiteral},";
            $arrLines[]  = "'{$name}' => \$this->{$name},";
            $metaLines[] = "'{$name}' => " . (string)self::exportPhp($fieldMeta) . ",";
        }

        $metadataMethod = "public static function getMetadata(): array\n"
            . "    {\n"
            . "        return [\n"
            . "            " . implode("\n            ", $metaLines) . "\n"
            . "        ];\n"
            . "    }";

        $output = str_replace('DummyDTO', "{$entityName}_DTO", $stub);
        $output = str_replace('//PROPERTIES', implode("\n        ", $propLines), $output);
        $output = str_replace('//MAPPING',    implode("\n            ", $mapLines), $output);
        $output = str_replace('//ARRAY_MAP',  implode("\n            ", $arrLines), $output);
        $output = str_replace('//METADATA',   $metadataMethod, $output);

        DataHelper::ensureDir((string)TargetManager::gen_path('DTOs'));
        file_put_contents((string)TargetManager::gen_path("DTOs/{$entityName}_DTO.php"), $output);
    }

    // -----------------------------------------------------------------
    private static function generateController($entityName)
    {
        self::deployBaseController();

        $stub = file_get_contents(__DIR__ . '/Stub/controller.stub');

        // 1. replace Dummy (Class/Model) by name of real Entity (e.g. Order)
        $output = str_replace('Dummy', $entityName, $stub);

        $targetPath = TargetManager::gen_path("Http/Controllers/{$entityName}_Controller.php");

        // 2. delete old file if exist
        if (file_exists((string)$targetPath)) {
            unlink((string)$targetPath);
        }

        file_put_contents((string)$targetPath, $output);
        echo "  - Generated Controller: {$entityName}_Controller.php\n";
    }

    /**
     * @param 
     * *       Array
     * *        (
     * *           [type] => string
     * *           [php_type] => string
     * *           [input] => file
     * *           [ui] =>
     * *           [params] => Array
     * *               (
     * *                 [length] => 255
     * *              )
     * *            [required] =>
     * *           [default] =>
     * *           [rules] => nullable|string|max:255
     * *           )
     * @return 
     * *       [
     * *           'type' => 'string',
     * *           'php_type' => 'string',
     * *           'input' => 'text',
     * *           'ui' => NULL,
     * *           'params' => [
     * *               'length' => 255
     * *           ],
     * *           'required' => true,
     * *           'default' => NULL,
     * *           'rules' => 'required|string|max:255'
     * *       ]

     * *
     * * FYI : there is only 1-2 layers of array(array()) in Class f
     * * e.g. public const ORDER_NR = ['order_nr', [d::STRING, 255], u::TEXT];
     * * so this simple solution must be enough (no recursive function , that calls itself)
     */
    private static function exportPhp(array $array): string
    {
        $parts = [];
        foreach ($array as $key => $value) {
            // CASE : Array ภายใน (ชั้นที่ 2)
            if (is_array($value)) {
                $innerParts = [];
                foreach ($value as $k => $v) {
                    $innerParts[] = "                    " . var_export($k, true) . " => " . var_export($v, true);
                }
                $valueString = empty($innerParts) ? "[]" :
                    "[\n" . implode(",\n", $innerParts) . "\n                ]";
            } else {
                // CASE : string
                $valueString = var_export($value, true);
            }

            $parts[] = "                " . var_export($key, true) . " => " . $valueString;
        }
        return "[\n" . implode(",\n", $parts) . "\n            ]";
    }

    // -----------------------------------------------------------------
    /** copy app/Constant/Stub/BaseController.php -> app/Http/Controllers/BaseController.php (SSOT at Stub) */
    private static function deployBaseController(): void
    {
        if (self::$base_controller_deployed) {
            return;
        }

        $source = __DIR__ . '/Stub/BaseController.stub';
        $dest   = (string)TargetManager::gen_path('Http/Controllers/BaseController.php');

        if (!file_exists($source)) {
            throw new \RuntimeException("Missing stub: {$source}");
        }

        DataHelper::ensureDir(dirname($dest));

        if (copy($source, $dest)) {
            echo "\n ========================================================================================== \n";
            echo "\n DEPLOY SUCCESS : $dest \n";
            echo "\n ========================================================================================== \n";
            self::$base_controller_deployed = true;
        } else {
            throw new \RuntimeException("Copy FAILD !!! : {$dest}");
        }
    }

    // -----------------------------------------------------------------
    /** copy app/Constant/Stub/BaseDTO.php -> app/DTOs/BaseDTO.php (SSOT in Stub) */
    private static function deployBaseDTO(): void
    {
        if (self::$base_dto_deployed) {
            return;
        }

        $source = __DIR__ . '/Stub/BaseDTO.stub';
        $dest   = (string)TargetManager::gen_path('DTOs/BaseDTO.php');

        if (!file_exists($source)) {
            throw new \RuntimeException("Missing stub: {$source}");
        }

        DataHelper::ensureDir(dirname($dest));

        if (copy($source, $dest)) {
            echo "\n ========================================================================================== \n";
            echo "\n DEPLOY SUCCESS : $dest \n";
            echo "\n ========================================================================================== \n";
            self::$base_dto_deployed = true;
        } else {
            throw new \RuntimeException("Copy FAILD !!! : {$dest}");
        }

        self::$base_dto_deployed = true;
    }
}

\App\Constant\EntityGenerator::runAll();
