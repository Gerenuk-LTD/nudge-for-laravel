<?php

namespace Gerenuk\Nudge\Data;

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Enums\ContactSource;
use Gerenuk\Nudge\Enums\ContactStatus;
use Gerenuk\Nudge\Support\Attributes;

final readonly class Contact
{
    /**
     * @param  ContactStatus|null  $status  null for a status this version of the package doesn't know yet
     * @param  array<string, mixed>  $customFields
     * @param  list<string>  $tags  tag names
     * @param  list<int>  $lists  list IDs
     */
    public function __construct(
        public string $email,
        public ?string $firstName,
        public ?string $lastName,
        public ?ContactStatus $status,
        public ?ContactSource $source,
        public array $customFields,
        public array $tags,
        public array $lists,
        public ?CarbonImmutable $unsubscribedAt,
        public ?CarbonImmutable $archivedAt,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public bool $wasCreated = false,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, bool $wasCreated = false): self
    {
        $attributes = new Attributes($data);

        $customFields = [];

        foreach ($attributes->array('custom_fields') as $key => $value) {
            $customFields[(string) $key] = $value;
        }

        return new self(
            email: $attributes->string('email'),
            firstName: $attributes->nullableString('first_name'),
            lastName: $attributes->nullableString('last_name'),
            status: ContactStatus::tryFrom($attributes->string('status')),
            source: ContactSource::tryFrom($attributes->string('source')),
            customFields: $customFields,
            tags: $attributes->strings('tags'),
            lists: $attributes->ints('lists'),
            unsubscribedAt: $attributes->date('unsubscribed_at'),
            archivedAt: $attributes->date('archived_at'),
            createdAt: $attributes->date('created_at'),
            updatedAt: $attributes->date('updated_at'),
            wasCreated: $wasCreated,
        );
    }

    public function isSubscribed(): bool
    {
        return $this->status === ContactStatus::Subscribed;
    }

    public function isUnsubscribed(): bool
    {
        return $this->status === ContactStatus::Unsubscribed;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function hasTag(string $tag): bool
    {
        return in_array(mb_strtolower($tag), array_map(mb_strtolower(...), $this->tags), true);
    }

    public function name(): ?string
    {
        $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));

        return $name === '' ? null : $name;
    }
}
