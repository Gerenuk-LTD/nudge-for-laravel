<?php

namespace Gerenuk\Nudge\Data;

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Enums\CustomFieldType;
use Gerenuk\Nudge\Support\Attributes;

final readonly class CustomField
{
    public function __construct(
        public string $key,
        public string $label,
        public CustomFieldType $type,
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
            key: $attributes->string('key'),
            label: $attributes->string('label'),
            type: CustomFieldType::tryFrom($attributes->string('type')) ?? CustomFieldType::Text,
            createdAt: $attributes->date('created_at'),
            updatedAt: $attributes->date('updated_at'),
        );
    }
}
