<?php

namespace Gerenuk\Nudge;

use Gerenuk\Nudge\Contracts\BuildsRequest;
use Gerenuk\Nudge\Contracts\Transport;
use Gerenuk\Nudge\Data\Me;
use Gerenuk\Nudge\Endpoints\Contacts;
use Gerenuk\Nudge\Endpoints\CustomFields;
use Gerenuk\Nudge\Endpoints\Lists;
use Gerenuk\Nudge\Endpoints\Segments;
use Gerenuk\Nudge\Endpoints\Tags;
use Gerenuk\Nudge\Exceptions\NudgeDisabledException;
use Gerenuk\Nudge\Exceptions\NudgeException;
use Gerenuk\Nudge\Exceptions\RequestException;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Http\ApiResponse;
use Gerenuk\Nudge\Jobs\SendToNudge;
use Gerenuk\Nudge\Pending\PendingContact;
use Gerenuk\Nudge\Pending\PendingEvent;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Bus;

class Nudge
{
    public function __construct(
        protected Transport $transport,
        protected Repository $config,
    ) {}

    /**
     * Create or update a contact. Call `save()` to send it now, or `queue()` to send it in the background.
     */
    public function contact(string $email): PendingContact
    {
        return new PendingContact($this, $email);
    }

    /**
     * Record a named event against a contact, such as "registered" or "order_completed".
     * Call `send()` to send it now, or `queue()` to send it in the background.
     */
    public function event(string $event, string $email): PendingEvent
    {
        return new PendingEvent($this, $event, $email);
    }

    public function contacts(): Contacts
    {
        return new Contacts($this);
    }

    public function lists(): Lists
    {
        return new Lists($this);
    }

    public function tags(): Tags
    {
        return new Tags($this);
    }

    public function customFields(): CustomFields
    {
        return new CustomFields($this);
    }

    public function segments(): Segments
    {
        return new Segments($this);
    }

    /**
     * The token's workspace and permissions. Useful for checking the connection.
     */
    public function me(): Me
    {
        return Me::fromArray($this->send(ApiRequest::get('me'))->data());
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('nudge.enabled', true);
    }

    /**
     * Send a request now.
     *
     * @throws NudgeDisabledException when sending is turned off
     * @throws RequestException when Nudge answers with an error status
     * @throws NudgeException when Nudge can't be reached or isn't configured
     */
    public function send(ApiRequest | BuildsRequest $request): ApiResponse
    {
        if (! $this->enabled()) {
            throw new NudgeDisabledException;
        }

        $request = $request instanceof BuildsRequest ? $request->toRequest() : $request;
        $response = $this->transport->send($request);

        if (! $response->successful()) {
            throw RequestException::from($request, $response);
        }

        return $response;
    }

    /**
     * Send requests in the background, with retries. Several requests are chained, so they're
     * sent in order and each waits for the one before it: queue a contact before its first event.
     * Nothing is queued while sending is turned off.
     */
    public function queue(ApiRequest | BuildsRequest ...$requests): void
    {
        if (! $this->enabled() || $requests === []) {
            return;
        }

        $this->dispatch(array_values(array_map(
            fn (ApiRequest | BuildsRequest $request): ApiRequest => $request instanceof BuildsRequest ? $request->toRequest() : $request,
            $requests,
        )));
    }

    /**
     * @param  non-empty-list<ApiRequest>  $requests
     */
    protected function dispatch(array $requests): void
    {
        $jobs = array_map(fn (ApiRequest $request): SendToNudge => new SendToNudge($request), $requests);

        if (count($jobs) === 1) {
            Bus::dispatch($jobs[0]);

            return;
        }

        Bus::chain($jobs)->dispatch();
    }
}
