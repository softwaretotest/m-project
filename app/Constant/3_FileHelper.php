<?php

namespace App\Constant;


use ReflectionClass;

/**
 * functions for file read/write, e.g. for Constant_APP_Reader, EntityGenerator, etc.
 */
class FileHelper
{
    /** cache: class => [const_value => CONST_NAME] */
    public static array $const_map_cache = [];

    /** group prefix => class */
    public static array $groups = [
        'd'   => d::class,
        'u'   => u::class,
        'uf'  => uf::class,
        'cd'  => cd::class,
        'cud' => cud::class,
    ];

    /**
     * รับได้ทั้ง 'tel' (ค่าจริงจาก PHP const) และ 'u::TEL' (รูปแบบใน M_value JSON)
     * @return array{group:string,name:string,value:string}|null
     */
    public static function resolve($key): ?array
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
    public static function constMap(string $class): array
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

    public static function castDefault($value, string $php_type)
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
    public static function maxValueOf(int $total, int $scale): string
    {
        $intDigits = max(0, $total - $scale);
        $head = $intDigits > 0 ? str_repeat('9', $intDigits) : '0';
        return $scale > 0 ? $head . '.' . str_repeat('9', $scale) : $head;
    }

    /** product_id -> products */
    public static function guessTable(?string $fieldName): string
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
