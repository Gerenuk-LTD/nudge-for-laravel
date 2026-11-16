<?php

namespace Gerenuk\Nudge\Pending;

use DateTimeInterface;
use Gerenuk\Nudge\Contracts\BuildsRequest;
use Gerenuk\Nudge\Data\Contact;
use Gerenuk\Nudge\Enums\ContactStatus;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Nudge;
use InvalidArgumentException;

/**
 * A contact to create or update. Only what you set is sent, so anything left out keeps its
 * current value in Nudge. Custom fields are merged, and tags and lists are only ever added.
 */
final class PendingContact implements BuildsRequest
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, mixed> */
    private array $customFields = [];

    /** @var array<string, true> */
    private array $tags = [];

    /** @var array<int, true> */
    private array $lists = [];

    private bool $resubscribe = false;

    public function __construct(
        private readonly Nudge $nudge,
        private readonly string $email,
    ) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('This is not a valid email address.');
        }
    }

    public function firstName(?string $firstName): self
    {
        $this->attributes['first_name'] = $firstName;

        return $this;
    }

    public function lastName(?string $lastName): self
    {
        $this->attributes['last_name'] = $lastName;

        return $this;
    }

    public function name(?string $firstName, ?string $lastName = null): self
    {
        return $this->firstName($firstName)->lastName($lastName);
    }

    /**
     * Setting a contact to subscribed doesn't subscribe someone who unsubscribed. Use
     * `resubscribe()` for that, and only when they've asked.
     */
    public function status(ContactStatus $status): self
    {
        $this->attributes['status'] = $status->value;

        return $this;
    }

    public function unsubscribe(): self
    {
        return $this->status(ContactStatus::Unsubscribed);
    }

    /**
     * Subscribe the contact again if they had unsubscribed. Only do this when they've opted back in.
     */
    public function resubscribe(bool $resubscribe = true): self
    {
        $this->resubscribe = $resubscribe;

        if ($resubscribe) {
            $this->status(ContactStatus::Subscribed);
        }

        return $this;
    }

    /**
     * Set custom field values, keyed by the field's key. Each field must already exist in Nudge.
     * Dates are sent as `Y-m-d`.
     *
     * @param  array<string, string|int|float|bool|DateTimeInterface|null>  $fields
     */
    public function customFields(array $fields): self
    {
        foreach ($fields as $key => $value) {
            $this->customField($key, $value);
        }

        return $this;
    }

    public function customField(string $key, string | int | float | bool | DateTimeInterface | null $value): self
    {
        $this->customFields[$key] = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;

        return $this;
    }

    /**
     * Add tags by name. Nudge creates any that don't exist yet.
     *
     * @param  iterable<string>|string  $tags
     */
    public function tags(iterable | string $tags): self
    {
        foreach (is_string($tags) ? [$tags] : $tags as $tag) {
            $this->tags[$tag] = true;
        }

        return $this;
    }

    /**
     * Add the contact to lists, by list ID.
     *
     * @param  iterable<int>|int  $lists
     */
    public function lists(iterable | int $lists): self
    {
        foreach (is_int($lists) ? [$lists] : $lists as $list) {
            $this->lists[$list] = true;
        }

        return $this;
    }

    public function toRequest(): ApiRequest
    {
        return ApiRequest::put('contacts/' . ApiRequest::segment($this->email), array_filter([
            ...$this->attributes,
            'custom_fields' => $this->customFields === [] ? null : $this->customFields,
            'tags' => $this->tags === [] ? null : array_map(strval(...), array_keys($this->tags)),
            'lists' => $this->lists === [] ? null : array_keys($this->lists),
            'resubscribe' => $this->resubscribe ? true : null,
        ], fn (mixed $value, string $key): bool => $value !== null || array_key_exists($key, $this->attributes), ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Send now and return the contact as Nudge holds it.
     */
    public function save(): Contact
    {
        $response = $this->nudge->send($this);

        return Contact::fromArray($response->data(), wasCreated: $response->status === 201);
    }

    /**
     * Send in the background, with retries.
     */
    public function queue(): void
    {
        $this->nudge->queue($this);
    }
}
