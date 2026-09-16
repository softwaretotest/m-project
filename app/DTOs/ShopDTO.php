<?php

namespace App\DTOs;

final class ShopDTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $image = null,
        public readonly ?int $user_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            image: $data['image'] ?? null,
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
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
