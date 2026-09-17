<?php

namespace App\Constant;

class UserConstant
{
    public const TABLE_NAME = t::USERS;

    public static function fields(): array
    {
        return [
            f::NAME,
            s::EMAIL,
            f::IS_ACTIVE,
            f::IMAGE,
        ];
    }
}
