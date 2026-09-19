<?php

namespace App\Constant;

class OrderConstant
{
    public const TABLE_NAME = t::ORDERS;

    public static function fields(): array
    {
        return [
            f::ORDER_NR,
            f::PRODUCT_ID,
            f::QUANTITY,
            f::CONFIRM_ORDER,
        ];
    }
}
