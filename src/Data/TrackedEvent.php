<?php

namespace Gerenuk\Nudge\Data;

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Support\Attributes;

/**
 * The event Nudge recorded, as it appears on the contact's timeline.
 */
final readonly class TrackedEvent
{
    /**
     * @param  array<array-key, mixed>  $properties
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $properties,
        public ?CarbonImmutable $occurredAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $attributes = new Attributes($data);
        $details = new Attributes($attributes->array('data'));

        return new self(
            id: $attributes->int('id'),
            name: $details->string('name'),
            properties: $details->array('properties'),
            occurredAt: $attributes->date('occurred_at'),
        );
    }
}
