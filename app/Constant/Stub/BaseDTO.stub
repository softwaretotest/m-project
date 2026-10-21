<?php

namespace App\DTOs;

abstract class BaseDTO
{
    abstract public function toArray(): array;

    public static function getMetadata(): array
    {
        return [];
    }

    /** use with Validator::make($data, XxxDTO::rules()) — return field => 'required|string|...' */
    public static function rules(): array
    {
        $out = [];
        foreach (static::getMetadata() as $field => $meta) {
            $out[$field] = $meta['rules'] ?? '';
        }
        return $out;
    }

    /** set to Frontend to render form */
    public static function uiSchema(): array
    {
        $out = [];
        foreach (static::getMetadata() as $field => $meta) {
            $out[$field] = [
                'input'    => $meta['input']    ?? 'text',
                'ui'       => $meta['ui']       ?? null,
                'params'   => $meta['params']   ?? [],
                'required' => $meta['required'] ?? false,
                'default'  => $meta['default']  ?? null,
            ];
        }
        return $out;
    }

    public function toJson(int $flags = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $flags);
    }
}
