<?php

namespace App\DTOs;

final class UserDTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?bool $is_active = true,
        public readonly ?string $image = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            is_active: $data['is_active'] ?? true,
            image: $data['image'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'image' => $this->image,
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
            'email' => [
                'type' => 'string',
                'php_type' => 'string',
                'input' => 'text',
                'ui' => NULL,
                'params' => [],
                'required' => false,
                'default' => NULL,
                'rules' => 'nullable|string|unique:email'
            ],
            'is_active' => [
                'type' => 'boolean',
                'php_type' => 'bool',
                'input' => 'select',
                'ui' => NULL,
                'params' => [],
                'required' => false,
                'default' => true,
                'rules' => 'nullable|boolean'
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
        ];
    }
}
