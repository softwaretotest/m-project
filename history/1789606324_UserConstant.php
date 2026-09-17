<?php

namespace App\Constant;

class UserConstant
{
    public const TABLE_NAME = t::USERS;

    public static function fields(): array
    {
        return [
            f::NAME,
            f::IMAGE,
            s::EMAIL,
            f::IS_ACTIVE,
        ];
    }
}
