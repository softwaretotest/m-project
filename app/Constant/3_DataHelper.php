<?php

namespace App\Constant;


use ReflectionClass;

/**
 * functions for file read/write, e.g. for Constant_APP_Reader, EntityGenerator, etc.
 */
class DataHelper
{
    public const PATH_M_JSON = '/M_JSON/';

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
     * * param = e.g. 'boolean'
     * * return = 
            Array
            (
                [group] => d
                [name] => BOOLEAN
                [value] => boolean
            )
     */
    public static function resolve($key): ?array
    {
        if (!is_string($key) || $key === '') {
            return null;
        }

        // e.g. 'decimal' / 'tel' -> find in $groups : d, u, uf, cd, cud
        foreach (self::$groups as $group => $class) {
            $map = self::constMap($class);
            if (isset($map[$key])) {
                return ['group' => $group, 'name' => $map[$key], 'value' => $key];
            }
        }
        return null;
    }

    /** e.g.
     * @param 
                 App\Constant\uf
     * @return 
                Array
                (
                    [currency] => CURRENCY
                )
     * @param 
                App\Constant\cd
     * @return 
                Array
                (
                    [nullable] => NULLABLE
                    [default] => DEFAULT
                    [unique] => UNIQUE
                    [index] => INDEX
                    [foreign] => FOREIGN
                )
     */
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

    /**
     * return default value for [d::DEFAUT, default_value]
     */
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
}
