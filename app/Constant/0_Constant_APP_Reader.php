<?php

namespace App\Constant;

use ReflectionClass;

class Constant_APP_Reader
{
    /** cache: class => [const_value => CONST_NAME] */
    private static array $const_map_cache = [];

    /**
     * SSOT: ความหมายของ d_params ตามลำดับ index (นับต่อจาก d_name)
     * อนาคตเพิ่ม type ใหม่ หรือเพิ่ม param ใหม่ แก้ที่นี่ที่เดียว
     */
    private static array $d_param_map = [
        'string'  => ['length'],
        'decimal' => ['total_digits', 'scale'],
        'integer' => [],
        'big_int' => [],
        'boolean' => [],
        'text'    => [],
    ];

    /** SSOT: d_name => PHP type */
    private static array $php_type_map = [
        'string'  => 'string',
        'text'    => 'string',
        'integer' => 'int',
        'big_int' => 'int',
        'boolean' => 'bool',
        'decimal' => 'string', // keep precision, prevent float rounding loss
    ];

    // ---------------------------------------------------------------
    // Core parser
    // ---------------------------------------------------------------

    /**
     * แปลง definition array -> metadata ที่ใช้งานได้จริง
     * @param array $definition e.g. ['price', [d::DECIMAL,10,2], u::NUMBER, [cd::DEFAULT,0], uf::CURRENCY]
     */
    public static function parse(array $definition): array
    {
        $out = [
            'name'        => $definition[0] ?? null,
            'd_name'      => null,
            'php_type'    => null,
            'params'      => [],
            'is_foreign'  => false,
            'is_required' => false,
            'has_default' => false,
            'default'     => null,
        ];

        $d_map   = self::constMap(d::class);
        $cd_map  = self::constMap(cd::class);
        $cud_map = self::constMap(cud::class);

        // index 0 = field name -> ตัดทิ้ง, ที่เหลือคือ metadata items
        $items = array_values(array_slice($definition, 1));

        foreach ($items as $i => $item) {
            $key = is_array($item) ? ($item[0] ?? null) : $item;
            if (!is_string($key)) {
                continue;
            }

            // 1) d:: อยู่ตำแหน่งแรกเสมอ (กันชนกับ u::TEXT ที่ค่าซ้ำกันได้)
            if ($i === 0 && isset($d_map[$key], self::$php_type_map[$key])) {
                $out['d_name']   = $key;
                $out['php_type'] = self::$php_type_map[$key];

                if (is_array($item)) {
                    foreach (self::$d_param_map[$key] ?? [] as $pos => $paramName) {
                        $value = $item[$pos + 1] ?? null;
                        if ($value !== null) {
                            $out['params'][$paramName] = $value;
                        }
                    }
                }
                continue;
            }

            // 2) cd:: constraints
            if (isset($cd_map[$key])) {
                if ($cd_map[$key] === 'FOREIGN') {
                    $out['is_foreign'] = true;
                    $out['php_type'] ??= 'int';
                } elseif ($cd_map[$key] === 'DEFAULT') {
                    $out['has_default'] = true;
                    $out['default']     = is_array($item) ? ($item[1] ?? null) : null;
                }
                continue;
            }

            // 3) cud:: constraints
            if (isset($cud_map[$key]) && $cud_map[$key] === 'REQUIRED') {
                $out['is_required'] = true;
                continue;
            }
        }

        $out['php_type'] ??= 'int'; // fallback (FK / ไม่ระบุ d::)
        $out['default']    = $out['has_default']
            ? self::castDefault($out['default'], $out['php_type'])
            : null;

        return $out;
    }

    // ---------------------------------------------------------------
    // Public API ที่ Generator เรียกใช้
    // ---------------------------------------------------------------

    /**
     * รับได้ทั้ง string (ชื่อฟิลด์) และ array (definition) — backward compatible
     * @return string|null e.g. 'readonly ?string'
     */
    public static function getContract($field): ?string
    {
        $definition = is_array($field) ? $field : self::findDefinition($field);
        if ($definition === null) {
            return null;
        }
        return 'readonly ?' . self::parse($definition)['php_type'];
    }

    /** @return string e.g. 'required|string|max:255' */
    public static function mapRules(array $definition): string
    {
        $meta  = self::parse($definition);
        $rules = [$meta['is_required'] ? 'required' : 'nullable'];

        switch ($meta['d_name']) {
            case 'string':
            case 'text':
                $rules[] = 'string';
                if (isset($meta['params']['length'])) {
                    $rules[] = 'max:' . (int) $meta['params']['length'];
                }
                break;

            case 'decimal':
                $rules[] = 'numeric';
                $scale = isset($meta['params']['scale']) ? (int) $meta['params']['scale'] : null;
                $total = isset($meta['params']['total_digits']) ? (int) $meta['params']['total_digits'] : null;
                if ($scale !== null) {
                    $rules[] = "decimal:0,{$scale}";          // จำนวนตำแหน่งทศนิยม
                }
                if ($total !== null && $scale !== null) {
                    $rules[] = 'max:' . self::maxValueOf($total, $scale); // กันเกิน total_digits
                }
                break;

            case 'integer':
            case 'big_int':
                $rules[] = 'integer';
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;
        }

        if ($meta['is_foreign']) {
            $rules[] = 'integer';
            $rules[] = 'exists:' . self::guessTable($meta['name']) . ',id';
        }

        return implode('|', array_unique($rules));
    }

    /** คืน default ที่ cast type ตรงกับ contract แล้ว (null = ไม่มี default) */
    public static function getDefault(array $definition)
    {
        return self::parse($definition)['default'];
    }

    public static function hasDefault(array $definition): bool
    {
        return self::parse($definition)['has_default'];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** map ค่าคงที่ -> ชื่อคงที่ ด้วย Reflection (ไม่ใช้ regex) */
    private static function constMap(string $class): array
    {
        if (!isset(self::$const_map_cache[$class])) {
            $map = [];
            if (class_exists($class)) {
                foreach ((new ReflectionClass($class))->getConstants() as $name => $value) {
                    if (is_string($value)) {
                        $map[$value] = $name;
                    }
                }
            }
            self::$const_map_cache[$class] = $map;
        }
        return self::$const_map_cache[$class];
    }

    /** ค้นหา definition จากชื่อฟิลด์ใน f:: แล้ว s:: */
    public static function findDefinition(string $fieldname): ?array
    {
        $FIELDNAME = strtoupper($fieldname);

        foreach ([f::class, s::class] as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $ref = new ReflectionClass($class);
            if ($ref->hasConstant($FIELDNAME)) {
                $value = $ref->getConstant($FIELDNAME);
                return is_array($value) ? $value : [$fieldname, $value];
            }
        }
        return null;
    }

    private static function castDefault($value, string $php_type)
    {
        if ($value === null) {
            return null;
        }
        return match ($php_type) {
            'string' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            'int'    => (int) $value,
            'bool'   => (bool) $value,
            default  => $value,
        };
    }

    /** DECIMAL(10,2) -> '99999999.99' */
    private static function maxValueOf(int $total, int $scale): string
    {
        $intDigits = max(0, $total - $scale);
        $head = $intDigits > 0 ? str_repeat('9', $intDigits) : '0';
        return $scale > 0 ? $head . '.' . str_repeat('9', $scale) : $head;
    }

    /** product_id -> products */
    private static function guessTable(?string $fieldName): string
    {
        $base = preg_replace('/_id$/', '', (string) $fieldName);
        if (preg_match('/[^aeiou]y$/', $base)) {
            return substr($base, 0, -1) . 'ies';
        }
        if (preg_match('/(s|x|z|ch|sh)$/', $base)) {
            return $base . 'es';
        }
        return $base . 's';
    }

    /** เก็บไว้เพื่อ backward compatibility */
    public static function get_d_name($d_Item): ?string
    {
        $d_name = is_array($d_Item) ? ($d_Item[0] ?? null) : (is_string($d_Item) ? $d_Item : null);
        if ($d_name && str_starts_with($d_name, 'd::')) {
            $d_name = substr($d_name, 3);
        }
        return $d_name;
    }
}
