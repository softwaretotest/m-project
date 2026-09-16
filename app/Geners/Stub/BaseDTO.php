<?php

namespace App\DTOs;

abstract class BaseDTO
{
    abstract public function toArray(): array;

    public static function getMetadata(): array
    {
        return [];
    }

    public function toJson(int $flags = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $flags);
    }
}
