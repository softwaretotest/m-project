<?php

namespace App\Constant;

class ShopConstant
{
    public const TABLE_NAME = t::SHOPS;

    public static function fields(): array
    {
        return [
            f::NAME,
            f::IMAGE,
            f::USER_ID,
        ];
    }
}
