<?php

namespace Gerenuk\Nudge\Data;

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Support\Attributes;

final readonly class ContactList
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public ?int $contactsCount,
        public ?CarbonImmutable $createdAt,
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
            description: $attributes->nullableString('description'),
            contactsCount: $attributes->nullableInt('contacts_count'),
            createdAt: $attributes->date('created_at'),
            updatedAt: $attributes->date('updated_at'),
        );
    }
}
