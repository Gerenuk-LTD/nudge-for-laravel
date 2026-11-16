<?php

namespace Gerenuk\Nudge\Pending;

use Gerenuk\Nudge\Contracts\BuildsRequest;
use Gerenuk\Nudge\Data\TrackedEvent;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Nudge;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A named event for a contact's timeline. Events can start automations in Nudge.
 */
final class PendingEvent implements BuildsRequest
{
    /** Nudge accepts letters, numbers, "_", ".", ":" and "-", up to 100 characters. */
    public const string NAME_PATTERN = '/^[A-Za-z0-9_.:-]{1,100}$/';

    /** Visible ASCII, 1 to 255 characters. */
    public const string KEY_PATTERN = '/^[\x21-\x7E]{1,255}$/';

    /** @var array<string, mixed> */
    private array $properties = [];

    private bool $createContact = false;

    private ?string $idempotencyKey = null;

    public function __construct(
        private readonly Nudge $nudge,
        private readonly string $event,
        private readonly string $email,
    ) {
        if (preg_match(self::NAME_PATTERN, $event) !== 1) {
            throw new InvalidArgumentException("[{$event}] is not a valid event name. Use up to 100 letters, numbers, \"_\", \".\", \":\" or \"-\".");
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('This is not a valid email address.');
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function properties(array $properties): self
    {
        $this->properties = [...$this->properties, ...$properties];

        return $this;
    }

    public function property(string $key, mixed $value): self
    {
        $this->properties[$key] = $value;

        return $this;
    }

    /**
     * Create the contact if Nudge doesn't have them yet. Without this, an event for an unknown
     * email is refused.
     */
    public function createContact(bool $createContact = true): self
    {
        $this->createContact = $createContact;

        return $this;
    }

    /**
     * Nudge records each key once. A key is made for you; pass your own, such as an order ID, when
     * your app might send the same event twice.
     */
    public function idempotencyKey(string $key): self
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('The idempotency key is not valid. Use 1 to 255 visible ASCII characters, with no spaces.');
        }

        $this->idempotencyKey = $key;

        return $this;
    }

    public function toRequest(): ApiRequest
    {
        $this->idempotencyKey ??= (string) Str::uuid();

        return ApiRequest::post('events', array_filter([
            'email' => $this->email,
            'event' => $this->event,
            'properties' => $this->properties === [] ? null : $this->properties,
            'create_contact' => $this->createContact ? true : null,
        ], fn (mixed $value): bool => $value !== null))->withIdempotencyKey($this->idempotencyKey);
    }

    /**
     * Send now.
     */
    public function send(): TrackedEvent
    {
        return TrackedEvent::fromArray($this->nudge->send($this)->data());
    }

    /**
     * Send in the background, with retries.
     */
    public function queue(): void
    {
        $this->nudge->queue($this);
    }
}
