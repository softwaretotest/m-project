<?php

namespace App\Constant;

use ReflectionClass;

class Constant_APP_Reader
{
    /** cache: class => [const_value => CONST_NAME] */
    private static array $const_map_cache = [];

    /** group prefix => class */
    private static array $groups = [
        'd'   => d::class,
        'u'   => u::class,
        'uf'  => uf::class,
        'cd'  => cd::class,
        'cud' => cud::class,
    ];

    /** SSOT: d value => ความหมายของ param ตามลำดับ */
    private static array $d_param_map = [
        'decimal'            => ['total_digits', 'scale'],
        'string'             => ['length'],
        'integer'            => [],
        'boolean'            => [],
        'unsignedBigInteger' => [],
    ];

    /** SSOT: d value => PHP type */
    private static array $php_type_map = [
        'string'             => 'string',
        'decimal'            => 'string', // กัน floating point precision loss
        'integer'            => 'int',
        'unsignedBigInteger' => 'int',
        'boolean'            => 'bool',
    ];

    // =================================================================
    // CORE
    // =================================================================

    /**
     * แปลง definition array -> metadata
     * e.g. ['price', [d::DECIMAL,10,2], [cd::DEFAULT,0], u::TEL, uf::CURRENCY]
     */
    public static function parse(array $definition): array
    {
        $out = [
            'name'        => $definition[0] ?? null,
            'd_name'      => null,   // 'decimal', 'string', ...
            'php_type'    => null,   // 'string', 'int', 'bool'
            'params'      => [],     // ['total_digits'=>10,'scale'=>2]
            'ui_input'    => null,   // จาก u::
            'ui_format'   => null,   // จาก uf::
            'is_foreign'  => false,
            'is_required' => false,
            'is_nullable' => false,
            'is_unique'   => false,
            'is_index'    => false,
            'has_default' => false,
            'default'     => null,
        ];

        // index 0 = ชื่อฟิลด์ -> ตัดทิ้ง
        $items = array_values(array_slice($definition, 1));

        foreach ($items as $i => $item) {
            $key = is_array($item) ? ($item[0] ?? null) : $item;
            $ref = self::resolve($key);
            if ($ref === null) {
                continue;
            }

            switch ($ref['group']) {
                // ---------- data type (บังคับอยู่ index 0 เพื่อกันค่าชนกัน) ----------
                case 'd':
                    if ($i !== 0 || !isset(self::$php_type_map[$ref['value']])) {
                        break;
                    }
                    $out['d_name']   = $ref['value'];
                    $out['php_type'] = self::$php_type_map[$ref['value']];

                    if (is_array($item)) {
                        foreach (self::$d_param_map[$ref['value']] ?? [] as $pos => $paramName) {
                            $value = $item[$pos + 1] ?? null;
                            if ($value !== null) {
                                $out['params'][$paramName] = $value;
                            }
                        }
                    }
                    break;

                // ---------- UI input type ----------
                case 'u':
                    $out['ui_input'] = $ref['value'];          // 'tel', 'number', 'select'...
                    if (is_array($item) && count($item) > 1) { // เผื่ออนาคต [u::SELECT, [...options]]
                        $out['params']['ui_options'] = array_slice($item, 1);
                    }
                    break;

                // ---------- UI format ----------
                case 'uf':
                    $out['ui_format'] = $ref['value'];         // 'currency'
                    break;

                // ---------- column constraints ----------
                case 'cd':
                    match ($ref['name']) {
                        'FOREIGN'  => [$out['is_foreign'] = true, $out['php_type'] ??= 'int'],
                        'DEFAULT'  => [$out['has_default'] = true,
                                       $out['default'] = is_array($item) ? ($item[1] ?? null) : null],
                        'NULLABLE' => $out['is_nullable'] = true,
                        'UNIQUE'   => $out['is_unique']   = true,
                        'INDEX'    => $out['is_index']    = true,
                        default    => null,
                    };
                    break;

                // ---------- user constraints ----------
                case 'cud':
                    if ($ref['name'] === 'REQUIRED') {
                        $out['is_required'] = true;
                    }
                    break;
            }
        }

        $out['php_type'] ??= 'int'; // fallback: FK / ไม่ระบุ d::
        $out['default']    = $out['has_default']
            ? self::castDefault($out['default'], $out['php_type'])
            : null;

        return $out;
    }

    // =================================================================
    // PUBLIC API
    // =================================================================

    /** metadata ก้อนเดียวจบ สำหรับ DTO -> Frontend */
    public static function getFieldMetadata(array $definition): array
    {
        $meta = self::parse($definition);

        return [
            'type'     => $meta['d_name'] ?? ($meta['is_foreign'] ? 'foreign' : null),
            'php_type' => $meta['php_type'],
            'input'    => $meta['ui_input'],   // <- u::  e.g. 'tel'
            'ui'       => $meta['ui_format'],  // <- uf:: e.g. 'currency'
            'params'   => $meta['params'],     // <- total_digits / scale / length
            'required' => $meta['is_required'],
            'default'  => $meta['default'],
            'rules'    => self::mapRules($definition),
        ];
    }

    /** @return string e.g. 'required|string|max:255' */
    public static function mapRules(array $definition): string
    {
        $meta  = self::parse($definition);
        $rules = [$meta['is_required'] ? 'required' : 'nullable'];

        switch ($meta['d_name']) {
            case 'string':
                $rules[] = 'string';
                if (isset($meta['params']['length'])) {
                    $rules[] = 'max:' . (int) $meta['params']['length'];
                }
                break;

            case 'decimal':
                $rules[] = 'numeric';
                $scale = isset($meta['params']['scale'])        ? (int) $meta['params']['scale']        : null;
                $total = isset($meta['params']['total_digits']) ? (int) $meta['params']['total_digits'] : null;
                if ($scale !== null) {
                    $rules[] = "decimal:0,{$scale}";
                }
                if ($total !== null && $scale !== null) {
                    $rules[] = 'max:' . self::maxValueOf($total, $scale);
                }
                break;

            case 'integer':
            case 'unsignedBigInteger':
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

        if ($meta['is_unique']) {
            $rules[] = 'unique:' . ($meta['name'] ?? '');
        }

        return implode('|', array_unique($rules));
    }

    public static function getContract($field): ?string
    {
        $definition = is_array($field) ? $field : self::findDefinition($field);
        return $definition === null
            ? null
            : 'readonly ?' . self::parse($definition)['php_type'];
    }

    public static function getDefault(array $definition)
    {
        return self::parse($definition)['default'];
    }

    public static function hasDefault(array $definition): bool
    {
        return self::parse($definition)['has_default'];
    }

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

    // =================================================================
    // HELPERS
    // =================================================================

    /**
     * รับได้ทั้ง 'tel' (ค่าจริงจาก PHP const) และ 'u::TEL' (รูปแบบใน M_value JSON)
     * @return array{group:string,name:string,value:string}|null
     */
    private static function resolve($key): ?array
    {
        if (!is_string($key) || $key === '') {
            return null;
        }

        // รูปแบบ A: มี prefix ชัดเจน "d::DECIMAL"
        if (str_contains($key, '::')) {
            [$prefix, $name] = explode('::', $key, 2);
            $prefix = strtolower($prefix);
            if (!isset(self::$groups[$prefix])) {
                return null;
            }
            $byName = array_flip(self::constMap(self::$groups[$prefix]));
            $NAME   = strtoupper($name);
            return isset($byName[$NAME])
                ? ['group' => $prefix, 'name' => $NAME, 'value' => $byName[$NAME]]
                : null;
        }

        // รูปแบบ B: ค่าดิบ 'decimal' / 'tel' -> ไล่หาใน d, u, uf, cd, cud ตามลำดับ
        foreach (self::$groups as $group => $class) {
            $map = self::constMap($class);
            if (isset($map[$key])) {
                return ['group' => $group, 'name' => $map[$key], 'value' => $key];
            }
        }
        return null;
    }

    /** map ค่าคงที่ -> ชื่อคงที่ ด้วย Reflection */
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

    /** backward compatibility */
    public static function get_d_name($d_Item): ?string
    {
        $d_name = is_array($d_Item) ? ($d_Item[0] ?? null) : (is_string($d_Item) ? $d_Item : null);
        if ($d_name && str_starts_with($d_name, 'd::')) {
            $d_name = substr($d_name, 3);
        }
        return $d_name;
    }
}