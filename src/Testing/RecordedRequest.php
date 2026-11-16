<?php

namespace Gerenuk\Nudge\Testing;

use Gerenuk\Nudge\Http\ApiRequest;

/**
 * A request the fake saw, and whether it was sent now or queued.
 */
final readonly class RecordedRequest
{
    public function __construct(
        public ApiRequest $request,
        public bool $queued,
    ) {}

    public function isContactSave(): bool
    {
        return $this->request->is('PUT', 'contacts/*');
    }

    public function isEvent(): bool
    {
        return $this->request->is('POST', 'events');
    }

    /**
     * The contact's email, for contact saves and events.
     */
    public function email(): ?string
    {
        if ($this->isContactSave()) {
            return rawurldecode(substr($this->request->path, 9));
        }

        $email = $this->request->body['email'] ?? null;

        return is_string($email) ? $email : null;
    }

    public function eventName(): ?string
    {
        $event = $this->isEvent() ? ($this->request->body['event'] ?? null) : null;

        return is_string($event) ? $event : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->request->body ?? [];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function properties(): array
    {
        $properties = $this->request->body['properties'] ?? [];

        return is_array($properties) ? $properties : [];
    }

    public function isFor(string $email): bool
    {
        return mb_strtolower((string) $this->email()) === mb_strtolower($email);
    }
}
