<?php

namespace Gerenuk\Nudge\Data;

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Support\Attributes;

/**
 * A saved set of conditions. `contactsCount` is only set when you find one segment, because
 * counting runs the segment's whole query.
 */
final readonly class Segment
{
    /**
     * @param  array<array-key, mixed>|null  $definition
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?array $definition = null,
        public ?int $contactsCount = null,
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
            definition: $attributes->has('definition') ? $attributes->array('definition') : null,
            contactsCount: $attributes->nullableInt('contacts_count'),
            createdAt: $attributes->date('created_at'),
            updatedAt: $attributes->date('updated_at'),
        );
    }
}
