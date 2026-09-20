<?php

namespace App\DTOs;

final class Shop_DTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $image = '',
        public readonly ?int $user_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            image: $data['image'] ?? '',
            user_id: $data['user_id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'image' => $this->image,
            'user_id' => $this->user_id,
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
            'user_id' => [
                'type' => 'foreign',
                'php_type' => 'int',
                'input' => NULL,
                'ui' => NULL,
                'params' => [],
                'required' => false,
                'default' => NULL,
                'rules' => 'nullable|integer|exists:users,id'
            ],
        ];
    }
}
