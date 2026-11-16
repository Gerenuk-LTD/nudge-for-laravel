<?php

namespace Gerenuk\Nudge\Testing;

use Closure;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Http\ApiResponse;
use Gerenuk\Nudge\Nudge;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Records requests instead of sending them. Queued requests are recorded straight away, so tests
 * don't need a queue worker. It follows `nudge.enabled` like the real client, so tests can check
 * nothing is sent while it's turned off.
 */
class NudgeFake extends Nudge
{
    /** @var list<RecordedRequest> */
    private array $recorded = [];

    private FakeTransport $fakeTransport;

    public function __construct(Repository $config)
    {
        $this->fakeTransport = new FakeTransport(function (ApiRequest $request): void {
            $this->recorded[] = new RecordedRequest($request, queued: false);
        });

        parent::__construct($this->fakeTransport, $config);
    }

    /**
     * Answer matching requests with your own response. `$pattern` matches the path after the API
     * prefix. `*` matches within one segment, such as "contacts/*", and emails can be written as they
     * are: "contacts/ada@example.com". Newer stubs win.
     *
     * @param  array<array-key, mixed>|Closure(ApiRequest): (array<array-key, mixed>|ApiResponse)  $response
     */
    public function respond(string $method, string $pattern, array | Closure $response, int $status = 200): static
    {
        $this->fakeTransport->stub($method, $pattern, $response, $status);

        return $this;
    }

    /**
     * @param  (Closure(RecordedRequest): bool)|null  $callback
     * @return Collection<int, RecordedRequest>
     */
    public function recorded(?Closure $callback = null): Collection
    {
        return collect($this->recorded)->filter($callback ?? fn (): bool => true)->values();
    }

    /**
     * @param  Closure(RecordedRequest): bool  $callback
     */
    public function assertSent(Closure $callback): void
    {
        PHPUnit::assertTrue($this->recorded($callback)->isNotEmpty(), 'An expected Nudge request was not sent.');
    }

    /**
     * @param  Closure(RecordedRequest): bool  $callback
     */
    public function assertNotSent(Closure $callback): void
    {
        PHPUnit::assertTrue($this->recorded($callback)->isEmpty(), 'An unexpected Nudge request was sent.');
    }

    public function assertNothingSent(): void
    {
        $sent = $this->recorded()->map(fn (RecordedRequest $recorded): string => $recorded->request->describe());

        PHPUnit::assertTrue($sent->isEmpty(), "Nudge requests were sent unexpectedly:\n" . $sent->implode("\n"));
    }

    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->recorded, "Expected {$count} Nudge requests, but {$this->recorded()->count()} were sent.");
    }

    /**
     * @param  (Closure(array<string, mixed>): bool)|null  $callback  receives the request body
     */
    public function assertContactSaved(string $email, ?Closure $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->contactSaves($email, $callback)->isNotEmpty(),
            "The contact [{$email}] was not saved" . ($callback ? ' with the expected data.' : '.'),
        );
    }

    public function assertContactNotSaved(string $email): void
    {
        PHPUnit::assertTrue($this->contactSaves($email)->isEmpty(), "The contact [{$email}] was saved unexpectedly.");
    }

    /**
     * @param  (Closure(array<array-key, mixed>, string): bool)|null  $callback  receives the properties and the email
     */
    public function assertEventTracked(string $event, ?Closure $callback = null, ?int $times = null): void
    {
        $count = $this->events($event, $callback)->count();

        if ($times === null) {
            PHPUnit::assertTrue($count > 0, "The [{$event}] event was not tracked" . ($callback ? ' with the expected data.' : '.'));

            return;
        }

        PHPUnit::assertSame($times, $count, "The [{$event}] event was tracked {$count} times instead of {$times}.");
    }

    /**
     * @param  (Closure(array<array-key, mixed>, string): bool)|null  $callback
     */
    public function assertEventNotTracked(string $event, ?Closure $callback = null): void
    {
        PHPUnit::assertTrue($this->events($event, $callback)->isEmpty(), "The [{$event}] event was tracked unexpectedly.");
    }

    /**
     * @param  non-empty-list<ApiRequest>  $requests
     */
    protected function dispatch(array $requests): void
    {
        foreach ($requests as $request) {
            $this->recorded[] = new RecordedRequest($request, queued: true);
        }
    }

    /**
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     * @return Collection<int, RecordedRequest>
     */
    private function contactSaves(string $email, ?Closure $callback = null): Collection
    {
        return $this->recorded(fn (RecordedRequest $recorded): bool => $recorded->isContactSave()
            && $recorded->isFor($email)
            && ($callback === null || $callback($recorded->body())));
    }

    /**
     * @param  (Closure(array<array-key, mixed>, string): bool)|null  $callback
     * @return Collection<int, RecordedRequest>
     */
    private function events(string $event, ?Closure $callback): Collection
    {
        return $this->recorded(fn (RecordedRequest $recorded): bool => $recorded->eventName() === $event
            && ($callback === null || $callback($recorded->properties(), (string) $recorded->email())));
    }
}
