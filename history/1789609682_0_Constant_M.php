<?php

namespace App\Constant;

class d
{
    public const BOOLEAN = 'boolean';
    public const INTEGER = 'integer';
    public const DECIMAL = 'decimal';
    public const STRING = 'string';
    public const UNSIGNED_BINT = 'unsignedBigInteger';
}

class u
{
    public const TEXT = 'text';
    public const NUMBER = 'number';
    public const SELECT = 'select';
    public const FILE = 'file';
    public const TEL = 'tel';
}

class uf
{
    public const CURRENCY = 'currency';
}

class cd
{
    public const NULLABLE = 'nullable';
    public const DEFAULT = 'default';
    public const UNIQUE = 'unique';
    public const INDEX = 'index';
    public const FOREIGN = 'foreign';
}

class cu
{
    public const READONLY = 'readonly';
    public const DISABLED = 'disabled';
}

class cud
{
    public const REQUIRED = 'required';
}

class s
{
    public const EMAIL = ['email', d::STRING, u::TEXT, cd::UNIQUE];
}

