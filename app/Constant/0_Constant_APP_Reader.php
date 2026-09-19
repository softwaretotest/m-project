<?php

namespace App\Constant;

use ReflectionClass;

class Constant_APP_Reader
{

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
     * * convert definition array -> metadata
     * @param 
     * * -----------------------
                Array
                (
                    [0] => price
                    [1] => Array
                        (
                            [0] => decimal
                            [1] => 10
                            [2] => 2
                        )

                    [2] => tel
                    [3] => currency
                    [4] => Array
                        (
                            [0] => default
                            [1] => 0
                        )

                    [5] => required
                )
     * @return 
     * * -----------------------
                Array
                (
                    [name] => price
                    [d_name] => decimal
                    [php_type] => string
                    [params] => Array
                        (
                            [total_digits] => 10
                            [scale] => 2
                        )

                    [ui_input] => tel
                    [ui_format] => currency
                    [is_foreign] =>
                    [is_required] => 1
                    [is_nullable] =>
                    [is_unique] =>
                    [is_index] =>
                    [has_default] => 1
                    [default] => 0
                )
     */
    public static function parse(array $definition): array
    {
        $out = [
            'name'        => $definition[0] ?? null,
            'd_name'      => null,   // 'decimal', 'string', ...
            'php_type'    => null,   // 'string', 'int', 'bool'
            'params'      => [],     // ['total_digits'=>10,'scale'=>2]
            'ui_input'    => null,   // from u::
            'ui_format'   => null,   // from  uf::
            'is_foreign'  => false,
            'is_required' => false,
            'is_nullable' => false,
            'is_unique'   => false,
            'is_index'    => false,
            'has_default' => false,
            'default'     => null,
        ];

        // index 0 = fieldname, is cut out from $items
        $items = array_values(array_slice($definition, 1));

        foreach ($items as $i => $item) {
            $key = is_array($item) ? ($item[0] ?? null) : $item;
            $ref = DataHelper::resolve($key);
            if ($ref === null) {
                continue;
            }

            switch ($ref['group']) {
                // ------- db data type (บังคับอยู่ index 0 เพื่อกันค่าชนกัน) --------
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

        $out['php_type'] ??= 'int'; // fallback: FK / not d::
        $out['default']    = $out['has_default']
            ? DataHelper::castDefault($out['default'], $out['php_type'])
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
                    $rules[] = 'max:' . DataHelper::maxValueOf($total, $scale);
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
            $rules[] = 'exists:' . DataHelper::guessTable($meta['name']) . ',id';
        }

        if ($meta['is_unique']) {
            $rules[] = 'unique:' . ($meta['name'] ?? '');
        }

        return implode('|', array_unique($rules));
    }
}
