<?php

namespace Gerenuk\Nudge\Data;

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Enums\ActivityType;
use Gerenuk\Nudge\Support\Attributes;

/**
 * One entry on a contact's timeline, such as an email sent, opened or clicked, an unsubscribe, or
 * an event your app sent.
 */
final readonly class Activity
{
    /**
     * @param  ActivityType|null  $type  null for a type this version of the package doesn't know yet
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(
        public int $id,
        public ?ActivityType $type,
        public string $rawType,
        public array $data,
        public ?int $campaignId,
        public ?CarbonImmutable $occurredAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $attributes = new Attributes($data);

        return new self(
            id: $attributes->int('id'),
            type: ActivityType::tryFrom($attributes->string('type')),
            rawType: $attributes->string('type'),
            data: $attributes->array('data'),
            campaignId: $attributes->nullableInt('campaign_id'),
            occurredAt: $attributes->date('occurred_at'),
        );
    }

    /**
     * The event name your app sent, for custom events.
     */
    public function eventName(): ?string
    {
        return $this->type === ActivityType::Custom ? (new Attributes($this->data))->nullableString('name') : null;
    }

    /**
     * The properties your app sent with a custom event.
     *
     * @return array<array-key, mixed>
     */
    public function properties(): array
    {
        return (new Attributes($this->data))->array('properties');
    }
}
