<?php

namespace App\Geners;

use App\Constant\Constant_APP_Reader;
use App\Constant\cd;

function gen_path($path = '')
{
    return dirname(__DIR__, 2) . '/app/' . ltrim($path, '/');
}

class EntityGenerator
{
    private static bool $base_dto_deployed = false;

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
    }

    // -----------------------------------------------------------------
    private static function generateModel($entityName, $fields)
    {
        $stub    = file_get_contents(gen_path('Geners/Stub/model.stub'));
        $methods = "";

        foreach ($fields as $field) {
            if (is_array($field) && in_array(cd::FOREIGN, $field, true)) {
                echo "  - Generating relation method for foreign key: {$field[0]}\n";

                $relationName = str_replace('_id', '', $field[0]);
                $relatedClass = ucfirst($relationName);

                $methods .= "\n    public function {$relationName}(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo\n";
                $methods .= "    {\n";
                $methods .= "        return \$this->belongsTo(\\App\\Models\\{$relatedClass}::class);\n";
                $methods .= "    }\n";
            }
        }

        $output = str_replace('class Dummy', "class {$entityName}", $stub);

        // แทนที่ '}' ตัวสุดท้ายเท่านั้น (ของเดิม str_replace แทนทุกตัว = พังถ้า stub มีหลายวงเล็บ)
        $pos = strrpos($output, '}');
        if ($pos !== false) {
            $output = substr($output, 0, $pos) . $methods . "}\n";
        }

        self::ensureDir(gen_path('Models'));
        file_put_contents(gen_path("Models/{$entityName}.php"), $output);
    }

    // -----------------------------------------------------------------
    /**
     * $fields = [f::NAME, f::PRICE, ...] ซึ่ง "เป็น definition array อยู่แล้ว"
     * e.g. ['price', ['decimal',10,2], 'number', ['default',0], 'currency']
     */
    private static function generateDTO($entityName, $fields)
    {
        self::deployBaseDTO();

        $stub = file_get_contents(gen_path('Geners/Stub/dto.stub'));

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
            $metaLines[] = "'{$name}' => " . self::exportPhp($fieldMeta, 3) . ",";
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

    /** export array เป็น short syntax [] (var_export ให้ array() แบบเก่า อ่านยาก) */
    private static function exportPhp($value, int $depth = 0): string
    {
        $pad     = str_repeat('    ', $depth);
        $padItem = str_repeat('    ', $depth + 1);

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }
            $isList = array_is_list($value);
            $parts  = [];
            foreach ($value as $k => $v) {
                $prefix  = $isList ? '' : var_export($k, true) . ' => ';
                $parts[] = $padItem . $prefix . self::exportPhp($v, $depth + 1);
            }
            return "[\n" . implode(",\n", $parts) . ",\n" . $pad . "]";
        }

        return var_export($value, true);
    }

    // -----------------------------------------------------------------
    /** copy app/Geners/Stub/BaseDTO.php -> app/DTOs/BaseDTO.php (SSOT อยู่ที่ Stub) */
    private static function deployBaseDTO(): void
    {
        if (self::$base_dto_deployed) {
            return;
        }

        $source = gen_path('Geners/Stub/BaseDTO.php');
        $dest   = gen_path('DTOs/BaseDTO.php');

        if (!file_exists($source)) {
            throw new \RuntimeException("Missing stub: {$source}");
        }

        self::ensureDir(dirname($dest));
        copy($source, $dest); // overwrite เสมอ: ห้ามแก้ปลายทางด้วยมือ

        self::$base_dto_deployed = true;
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
