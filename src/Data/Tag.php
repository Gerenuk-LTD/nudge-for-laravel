<?php

namespace Gerenuk\Nudge\Data;

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Support\Attributes;

final readonly class Tag
{
    public function __construct(
        public int $id,
        public string $name,
        public int $contactsCount,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $attributes = new Attributes($data);

        return new self(
            id: $attributes->int('id'),
            name: $attributes->string('name'),
            contactsCount: $attributes->int('contacts_count'),
            createdAt: $attributes->date('created_at'),
            updatedAt: $attributes->date('updated_at'),
        );
    }
}
