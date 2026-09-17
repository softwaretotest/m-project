<?php

namespace App\Constant;

class f
{
    public const IMAGE = ['image', [d::STRING, 255], u::FILE, [cd::DEFAULT, '']];
    public const NAME = ['name', [d::STRING, 255], u::TEXT, cud::REQUIRED];
    public const PRICE = ['price', [d::DECIMAL, 10, 2], u::TEL, uf::CURRENCY, [cd::DEFAULT, 0], cud::REQUIRED];
    public const STOCK = ['stock', [d::DECIMAL, 15, 5], u::NUMBER, cud::REQUIRED, [cd::DEFAULT, 1]];
    public const IS_ACTIVE = ['is_active', d::BOOLEAN, u::SELECT, [cd::DEFAULT, true]];
    public const QUANTITY = ['quantity', [d::DECIMAL, 10, 2], u::NUMBER, [cd::DEFAULT, 1], cud::REQUIRED];
    public const CONFIRM_ORDER = ['confirm_order', d::BOOLEAN, u::SELECT, [cd::DEFAULT, false]];
    public const ORDER_NR = ['order_nr', [d::STRING, 255], u::TEXT];
    public const ORDER_ID = ['order_id', cd::FOREIGN];
    public const PRODUCT_ID = ['product_id', cd::FOREIGN];
    public const SHOP_ID = ['shop_id', cd::FOREIGN];
    public const USER_ID = ['user_id', cd::FOREIGN];
}

class t
{
    public const SHOPS = 'shops';
    public const PRODUCTS = 'products';
    public const ORDERS = 'orders';
    public const USERS = 'users';
}
