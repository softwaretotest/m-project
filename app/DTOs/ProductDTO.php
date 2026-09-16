<?php

namespace App\DTOs;

final class ProductDTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $image = null,
        public readonly ?int $shop_id = null,
        public readonly ?string $price = '0',
        public readonly ?string $stock = '0',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            image: $data['image'] ?? null,
            shop_id: $data['shop_id'] ?? null,
            price: $data['price'] ?? '0',
            stock: $data['stock'] ?? '0',
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
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:255',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'price' => 'nullable|numeric|decimal:0,2|max:99999999.99',
            'stock' => 'required|numeric|decimal:0,10|max:0.9999999999',
        ];
    }
}
