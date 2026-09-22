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

        // ---------- 1) create file from stub if not exists ----------
        if (!file_exists($target_User_Model)) {
            $dir = dirname($target_User_Model);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $stubPath = __DIR__ . '/Stub/user.model.stub';
            if (!file_exists($stubPath)) {
                Logger::error("Stub not found: {$stubPath}");
            }

            file_put_contents($target_User_Model, file_get_contents($stubPath));
            clearstatcache(true, $target_User_Model);
        }

        // ---------- 2) read user.model.stub ----------
        $code = file_get_contents($target_User_Model);
        if ($code === false || trim($code) === '') {
            Logger::error("User Model : Cannot read: {$target_User_Model}");
        }

        // ---------- 3) normalize fields ----------
        $newFields = self::normalizeFieldNames($fields);
        if (empty($newFields)) {
            Logger::warning("No valid fields to merge for User model.");
            return; // if $fields is empty no further action needed
        }

        // ---------- 4) merge + เขียนกลับ ----------
        $updated = self::mergeFillable($code, $newFields);

        if ($updated !== $code) {
            file_put_contents($target_User_Model, $updated, LOCK_EX);
            clearstatcache(true, $target_User_Model);
            Logger::success("User's fillable fields = " . implode(' , ', $newFields));
        } else {
            Logger::warning("No valid fields to merge for User model.");
        }
        Logger::finish();
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
}
