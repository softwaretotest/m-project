<?php

namespace App\DTOs;

final class Product_DTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $image = '',
        public readonly ?int $shop_id = null,
        public readonly ?string $price = '0',
        public readonly ?string $stock = '1',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            image: $data['image'] ?? '',
            shop_id: $data['shop_id'] ?? null,
            price: $data['price'] ?? '0',
            stock: $data['stock'] ?? '1',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'image' => $this->image,
            'shop_id' => $this->shop_id,
            'price' => $this->price,
            'stock' => $this->stock,
        ];
    }

    public static function getMetadata(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'php_type' => 'string',
                'input' => 'text',
                'ui' => NULL,
                'params' => [
                    'length' => 255
                ],
                'required' => true,
                'default' => NULL,
                'rules' => 'required|string|max:255'
            ],
            'image' => [
                'type' => 'string',
                'php_type' => 'string',
                'input' => 'file',
                'ui' => NULL,
                'params' => [
                    'length' => 255
                ],
                'required' => false,
                'default' => '',
                'rules' => 'nullable|string|max:255'
            ],
            'shop_id' => [
                'type' => 'foreign',
                'php_type' => 'int',
                'input' => NULL,
                'ui' => NULL,
                'params' => [],
                'required' => false,
                'default' => NULL,
                'rules' => 'nullable|integer|exists:shops,id'
            ],
            'price' => [
                'type' => 'decimal',
                'php_type' => 'string',
                'input' => 'tel',
                'ui' => 'currency',
                'params' => [
                    'total_digits' => 10,
                    'scale' => 2
                ],
                'required' => true,
                'default' => '0',
                'rules' => 'required|numeric|decimal:0,2|max:99999999.99'
            ],
            'stock' => [
                'type' => 'decimal',
                'php_type' => 'string',
                'input' => 'number',
                'ui' => NULL,
                'params' => [
                    'total_digits' => 15,
                    'scale' => 5
                ],
                'required' => true,
                'default' => '1',
                'rules' => 'required|numeric|decimal:0,5|max:9999999999.99999'
            ],
        ];
    }
}
