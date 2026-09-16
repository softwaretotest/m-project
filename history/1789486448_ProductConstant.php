<?php

namespace App\Constant;

class ProductConstant
{
    public const TABLE_NAME = t::PRODUCTS;

    public static function fields(): array
    {
        return [
            f::NAME,
            f::IMAGE,
            f::SHOP_ID,
            f::PRICE,
            f::STOCK,
        ];
    }
}
