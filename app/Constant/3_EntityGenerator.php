<?php

namespace App\Constant;

require __DIR__ . '/../../vendor/autoload.php';

function gen_path($path = '')
{
    return dirname(__DIR__, 2) . '/app/' . ltrim($path, '/');
}

/**
 * Laravel Layers Classes for Entities
 */
class EntityGenerator
{
    private static bool $base_dto_deployed = false;
    private static bool $base_controller_deployed = false;

    public static function runAll()
    {
        $directory = dirname(__DIR__, 2) . '/app/Constant';
        $files = glob($directory . '/*Constant.php');

        foreach ($files as $file) {
            $className     = basename($file, '.php');
            $fullClassName = "App\\Constant\\" . $className;

            if (class_exists($fullClassName) && method_exists($fullClassName, 'fields')) {
                $tableName  = $fullClassName::TABLE_NAME;
                $fields     = $fullClassName::fields();
                $entityName = str_replace('Constant', '', $className);

                echo "Generating: {$entityName} (Table: {$tableName})\n";
                self::generate($entityName, $fields);
            }
        }

        echo "Generated successfully!\n";
    }

    public static function generate($entityName, $fields)
    {
        self::generateModel($entityName, $fields);
        self::generateDTO($entityName, $fields);
        self::generateController($entityName, $fields);
    }

    private static function generateModel($entityName, $fields)
    {
        $stub = file_get_contents(gen_path('Constant/Stub/model.stub'));
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
        $fillableCode = "    protected \$fillable = [" . implode(', ', $fillableArray) . "];";

        // replace class Dummy by Entity
        $output = str_replace('class Dummy', "class {$entityName}", $stub);

        // replace/insert fillable at // FILLABLE_HERE in stub
        $output = str_replace('// FILLABLE_HERE', $fillableCode, $output);

        // replace '}' as last char (for Methods)
        $pos = strrpos($output, '}');
        if ($pos !== false) {
            $output = substr($output, 0, $pos) . $methods . "}\n";
        }

        self::ensureDir(gen_path('Models'));
        file_put_contents(gen_path("Models/{$entityName}.php"), $output);
    }

    // -----------------------------------------------------------------
    /**
     * $fields = [f::NAME, f::PRICE, ...] 
     * e.g. ['price', ['decimal',10,2], 'number', ['default',0], 'currency']
     */
    private static function generateDTO($entityName, $fields)
    {
        self::deployBaseDTO();

        $stub = file_get_contents(gen_path('Constant/Stub/dto.stub'));

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
            // $metaLines[] = "'{$name}' => " . self::exportPhp($fieldMeta, 3) . ",";
            $metaLines[] = "'{$name}' => " . self::exportPhp($fieldMeta) . ",";
        }

        $metadataMethod = "public static function getMetadata(): array\n"
            . "    {\n"
            . "        return [\n"
            . "            " . implode("\n            ", $metaLines) . "\n"
            . "        ];\n"
            . "    }";

        $output = str_replace('DummyDTO', "{$entityName}DTO", $stub);
        $output = str_replace('//PROPERTIES', implode("\n        ", $propLines), $output);
        $output = str_replace('//MAPPING',    implode("\n            ", $mapLines), $output);
        $output = str_replace('//ARRAY_MAP',  implode("\n            ", $arrLines), $output);
        $output = str_replace('//METADATA',   $metadataMethod, $output);

        self::ensureDir(gen_path('DTOs'));
        file_put_contents(gen_path("DTOs/{$entityName}DTO.php"), $output);
    }

    // -----------------------------------------------------------------
    private static function generateController($entityName)
    {
        self::deployBaseController();

        $stubPath = gen_path('Constant/Stub/controller.stub');
        $stub = file_get_contents($stubPath);

        // 1. แทนที่ Dummy (Class/Model) ด้วยชื่อ Entity (เช่น Order)
        $output = str_replace('Dummy', $entityName, $stub);

        $targetPath = gen_path("Http/Controllers/{$entityName}Controller.php");

        // 2. ลบไฟล์เดิมทิ้งก่อนเขียนใหม่ เพื่อป้องกันปัญหาเก่า
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }

        file_put_contents($targetPath, $output);
        echo "  - Generated Controller: {$entityName}Controller.php\n";
    }

    /**
     * * param  = ['length' => 255]
     * * return = 
     *      Array
     *      (
     *          [length] => 255
     *      )
     * 
     * * param  = 'required|string|max:255'
     * * return = 'required|string|max:255'
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

        $source = gen_path('Constant/Stub/BaseController.stub');
        $dest   = gen_path('Http/Controllers/BaseController.php');

        if (!file_exists($source)) {
            throw new \RuntimeException("Missing stub: {$source}");
        }

        self::ensureDir(dirname($dest));
        copy($source, $dest); // overwrite เสมอ: ห้ามแก้ปลายทางด้วยมือ

        self::$base_controller_deployed = true;
    }

    // -----------------------------------------------------------------
    /** copy app/Constant/Stub/BaseDTO.php -> app/DTOs/BaseDTO.php (SSOT in Stub) */
    private static function deployBaseDTO(): void
    {
        if (self::$base_dto_deployed) {
            return;
        }

        $source = gen_path('Constant/Stub/BaseDTO.stub');
        $dest   = gen_path('DTOs/BaseDTO.php');

        if (!file_exists($source)) {
            throw new \RuntimeException("Missing stub: {$source}");
        }

        self::ensureDir(dirname($dest));
        // always overwrite destination file by script, no manuel correction
        copy($source, $dest);

        self::$base_dto_deployed = true;
    }

    /**
     * make directory if not exist
     */
    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

\App\Constant\EntityGenerator::runAll();
