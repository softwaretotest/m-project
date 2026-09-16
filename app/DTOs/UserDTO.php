<?php

namespace App\DTOs;

final class UserDTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $image = null,
        public readonly ?string $email = null,
        public readonly ?bool $is_active = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            image: $data['image'] ?? null,
            email: $data['email'] ?? null,
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'image' => $this->image,
            'email' => $this->email,
            'is_active' => $this->is_active,
        ];
    }

    public static function getMetadata(): array
    {
        return [
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:255',
            'email' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}
