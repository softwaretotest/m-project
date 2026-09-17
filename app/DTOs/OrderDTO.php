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
            'order_nr' => [
                'type' => 'string',
                'php_type' => 'string',
                'input' => 'text',
                'ui' => NULL,
                'params' => [
                    'length' => 255,
                ],
                'required' => false,
                'default' => NULL,
                'rules' => 'nullable|string|max:255',
            ],
            'product_id' => [
                'type' => 'foreign',
                'php_type' => 'int',
                'input' => NULL,
                'ui' => NULL,
                'params' => [],
                'required' => false,
                'default' => NULL,
                'rules' => 'nullable|integer|exists:products,id',
            ],
            'quantity' => [
                'type' => 'decimal',
                'php_type' => 'string',
                'input' => 'number',
                'ui' => NULL,
                'params' => [
                    'total_digits' => 10,
                    'scale' => 2,
                ],
                'required' => true,
                'default' => '1',
                'rules' => 'required|numeric|decimal:0,2|max:99999999.99',
            ],
            'confirm_order' => [
                'type' => 'boolean',
                'php_type' => 'bool',
                'input' => 'select',
                'ui' => NULL,
                'params' => [],
                'required' => false,
                'default' => false,
                'rules' => 'nullable|boolean',
            ],
        ];
    }
}
