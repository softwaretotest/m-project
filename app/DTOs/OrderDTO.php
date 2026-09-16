<?php

namespace App\DTOs;

final class OrderDTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $order_nr = null,
        public readonly ?int $product_id = null,
        public readonly ?string $quantity = '1',
        public readonly ?bool $confirm_order = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            order_nr: $data['order_nr'] ?? null,
            product_id: $data['product_id'] ?? null,
            quantity: $data['quantity'] ?? '1',
            confirm_order: $data['confirm_order'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'order_nr' => $this->order_nr,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'confirm_order' => $this->confirm_order,
        ];
    }

    public static function getMetadata(): array
    {
        return [
            'order_nr' => 'nullable|string|max:255',
            'product_id' => 'nullable|integer|exists:products,id',
            'quantity' => 'required|numeric|decimal:0,2|max:99999999.99',
            'confirm_order' => 'nullable|boolean',
        ];
    }
}
