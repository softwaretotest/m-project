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
        $directory = dirname(__DIR__, 2) . '/app/Constant';
        // echo "\n" . $directory . "\n";

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

        echo "\n=====================================\n";
        echo "\n    BEGIN ARCHIVE : " . $entityName . "\n";
        echo "\n=====================================\n";

        M_Historizer::move_old_file_to_history(__DIR__ . '/../../app/Models/' . $entityName . "_Model.php");
        M_Historizer::move_old_file_to_history(__DIR__ . '/../../app/DTOs/' . $entityName . "_DTO.php");
        M_Historizer::move_old_file_to_history(__DIR__ . '/../../app/Http/Controllers/' . $entityName . "_Controller.php");

        if ($entityName === 'User') {
            self::generateUserModel($fields);
        } else {
            self::generateModel($entityName, $fields);
        }

        self::generateDTO($entityName, $fields);
        self::generateController($entityName, $fields);
    }

    // private static function generateUserModel($fields)
    // {
    //     $target_User_Model = (string)TargetManager::gen_path("Models/User.php");
    //     $target_User_Reflection = (string)TargetManager::gen_path("Models/User");
    //     $target_User_Reflection = str_replace('/app/', '/App/', $target_User_Reflection);
    //     if (!file_exists($target_User_Model)) {
    //         $stub = file_get_contents(__DIR__ . '/Stub/user.model.stub');
    //         file_put_contents($target_User_Model, $stub);
    //     }
    //     //logic wait until file_put_contents finish
    //     // 1. ระบุ Path ไปยังไฟล์ User ของโปรเจกต์เป้าหมาย (ecommerce)

    //     // 2. ต้องมั่นใจว่าไฟล์นี้ถูกโหลดเข้า Memory แล้ว
    //     if (file_exists($target_User_Model)) {
    //         require_once $target_User_Model;

    //         // 3. ตอนนี้ PHP รู้จัก Class App\Models\User แล้ว
    //         // ต่อให้มันอยู่นอกโปรเจกต์ m-project ก็ตาม
    //         // $reflection = new ReflectionClass($target_User_Reflection);
    //         $reflection = new ReflectionClass('App\Models\User');

    //         // ดึงค่าได้เลย!
    //         if ($reflection->hasProperty('fillable')) {
    //             // ... โลจิกดึงค่าของคุณ ...
    //             //logic to make User Model
    //         }
    //     }
    // }

    /**
     * สร้าง/อัปเดต User Model ในโปรเจกต์เป้าหมาย
     *
     * @param array $fields ฟิลด์เพิ่มเติม เช่น ['is_active', 'image']
     *                      หรือ [['name' => 'is_active', ...], ...] ก็รองรับ
     */
    private static function generateUserModel($fields)
    {
        $target_User_Model = (string) TargetManager::gen_path("Models/User.php");

        // ---------- 1) ถ้ายังไม่มีไฟล์ ให้สร้างจาก stub ----------
        if (!file_exists($target_User_Model)) {
            $dir = dirname($target_User_Model);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $stubPath = __DIR__ . '/Stub/user.model.stub';
            if (!file_exists($stubPath)) {
                throw new \RuntimeException("Stub not found: {$stubPath}");
            }

            file_put_contents($target_User_Model, file_get_contents($stubPath));
            clearstatcache(true, $target_User_Model);
        }

        // ---------- 2) อ่านไฟล์ ----------
        $code = file_get_contents($target_User_Model);
        if ($code === false || trim($code) === '') {
            throw new \RuntimeException("Cannot read: {$target_User_Model}");
        }

        // ---------- 3) normalize ฟิลด์ที่ส่งเข้ามา ----------
        $newFields = self::normalizeFieldNames($fields);
        if (empty($newFields)) {
            return $target_User_Model; // ไม่มีอะไรต้องเพิ่ม
        }

        // ---------- 4) merge + เขียนกลับ ----------
        $updated = self::mergeFillable($code, $newFields);

        if ($updated !== $code) {
            // สำรองไฟล์เดิมไว้ก่อน (กันพลาด)
            @copy($target_User_Model, $target_User_Model . '.bak');
            file_put_contents($target_User_Model, $updated, LOCK_EX);
            clearstatcache(true, $target_User_Model);
        }

        return $target_User_Model;
    }

    private static function normalizeFieldNames($fields): array
    {
        $out = [];

        foreach ((array) $fields as $key => $value) {

            // กรณี f::NAME / s::EMAIL  →  ['name', d::STRING, ...]
            if (is_array($value)) {
                $candidate = $value[0] ?? ($value['name'] ?? null);

                // เผื่อซ้อนอีกชั้น
                while (is_array($candidate)) {
                    $candidate = $candidate[0] ?? null;
                }

                if (is_string($candidate) && $candidate !== '') {
                    $out[] = $candidate;
                }
                continue;
            }

            // กรณีส่งเป็น string ตรงๆ  →  'is_active'
            if (is_string($value)) {
                $out[] = $value;
                continue;
            }

            // กรณี ['is_active' => 'boolean']
            if (is_string($key)) {
                $out[] = $key;
            }
        }

        $blocked = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'];

        return array_values(array_filter(array_unique($out), function ($f) use ($blocked) {
            return $f !== '' && !in_array($f, $blocked, true);
        }));
    }

    private static function mergeFillable(string $code, array $newFields): string
    {
        // --- A) รูปแบบใหม่: #[Fillable([...])] ---
        $attrPattern = '/(#\[Fillable\(\s*\[)(.*?)(\]\s*\)\])/s';
        if (preg_match($attrPattern, $code)) {
            return preg_replace_callback($attrPattern, function ($m) use ($newFields) {
                $merged = self::mergeList($m[2], $newFields);
                return $m[1] . $merged . $m[3];   // เปลี่ยนแค่ตรงกลาง
            }, $code, 1);
        }

        // --- B) รูปแบบเก่า: protected $fillable = [...]; ---
        $propPattern = '/(\$fillable\s*=\s*\[)(.*?)(\]\s*;)/s';
        if (preg_match($propPattern, $code)) {
            return preg_replace_callback($propPattern, function ($m) use ($newFields) {
                $merged = self::mergeList($m[2], $newFields);
                return $m[1] . $merged . $m[3];
            }, $code, 1);
        }

        // --- C) ไม่มีทั้งคู่ → แทรก property ใหม่หลัง { ของ class ---
        return self::insertFillableProperty($code, $newFields);
    }

    private static function mergeList(string $rawInner, array $newFields): string
    {
        // ดึงของเดิมออกมา
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $rawInner, $m);
        $existing = $m[1];

        $merged = array_values(array_unique(array_merge($existing, $newFields)));

        // ไม่มีอะไรเปลี่ยน → คืนของเดิมเป๊ะๆ (ไม่แตะ format)
        if ($merged === $existing) {
            return $rawInner;
        }

        // ใช้ quote แบบเดียวกับของเดิม
        $q = (strpos($rawInner, '"') !== false && strpos($rawInner, "'") === false) ? '"' : "'";

        // ถ้าของเดิมเขียนหลายบรรทัด → คงรูปแบบหลายบรรทัดไว้
        if (strpos(trim($rawInner), "\n") !== false) {
            preg_match('/\n(\s+)/', $rawInner, $ind);
            $indent = $ind[1] ?? '        ';
            $closeIndent = substr($indent, 0, max(0, strlen($indent) - 4));

            $lines = array_map(fn($f) => $indent . $q . $f . $q . ',', $merged);
            return "\n" . implode("\n", $lines) . "\n" . $closeIndent;
        }

        // บรรทัดเดียว
        return implode(', ', array_map(fn($f) => $q . $f . $q, $merged));
    }

    private static function insertFillableProperty(string $code, array $newFields): string
    {
        $q = "'";
        $list = implode(', ', array_map(fn($f) => $q . $f . $q, $newFields));

        $pattern = '/(class\s+User\s+extends\s+[^\{]+\{)/';

        if (!preg_match($pattern, $code)) {
            return $code; // หา class ไม่เจอ → ไม่แตะดีกว่า
        }

        $property = "\n    protected \$fillable = [{$list}];\n";

        return preg_replace($pattern, '$1' . $property, $code, 1);
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

        self::ensureDir((string)TargetManager::gen_path('Models'));
        file_put_contents((string)TargetManager::gen_path("Models/{$entityName}_Model.php"), $output);
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

        self::ensureDir((string)TargetManager::gen_path('DTOs'));
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

        $source = __DIR__ . '/Stub/BaseDTO.stub';
        $dest   = (string)TargetManager::gen_path('DTOs/BaseDTO.php');

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
