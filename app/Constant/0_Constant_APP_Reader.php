<?php

namespace App\Constant;

use ReflectionClass;

class Constant_APP_Reader
{
    // /** cache: class => [const_value => CONST_NAME] */
    // private static array $const_map_cache = [];

    // /** group prefix => class */
    // private static array $groups = [
    //     'd'   => d::class,
    //     'u'   => u::class,
    //     'uf'  => uf::class,
    //     'cd'  => cd::class,
    //     'cud' => cud::class,
    // ];

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
            $ref = FileHelper::resolve($key);
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
                        'DEFAULT'  => [
                            $out['has_default'] = true,
                            $out['default'] = is_array($item) ? ($item[1] ?? null) : null
                        ],
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
            ? FileHelper::castDefault($out['default'], $out['php_type'])
            : null;

        return $out;
    }

    // =================================================================
    // PUBLIC API
    // =================================================================

    /** metadata array for DTO -> Frontend */
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
                    $rules[] = 'max:' . FileHelper::maxValueOf($total, $scale);
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
            $rules[] = 'exists:' . FileHelper::guessTable($meta['name']) . ',id';
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
}
