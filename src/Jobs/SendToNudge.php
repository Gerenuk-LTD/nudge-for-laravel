<?php

namespace Gerenuk\Nudge\Jobs;

use Gerenuk\Nudge\Exceptions\RateLimitedException;
use Gerenuk\Nudge\Exceptions\RequestException;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Nudge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one request to Nudge in the background. Connection failures, server errors and rate
 * limits are retried. A request Nudge refuses, such as failed validation, fails straight away.
 */
final class SendToNudge implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly ApiRequest $request)
    {
        $connection = config('nudge.queue.connection');
        $queue = config('nudge.queue.name');

        $this->onConnection(is_string($connection) && $connection !== '' ? $connection : null);
        $this->onQueue(is_string($queue) && $queue !== '' ? $queue : null);
    }

    public function handle(Nudge $nudge): void
    {
        // Sending was turned off after this was queued.
        if (! $nudge->enabled()) {
            return;
        }

        try {
            $nudge->send($this->request);
        } catch (RateLimitedException $exception) {
            $this->release($exception->retryAfter() ?? 60);
        } catch (RequestException $exception) {
            if (! $exception->isPermanent()) {
                throw $exception;
            }

            $this->fail($exception);
        }
    }

    public function tries(): int
    {
        return max(1, (int) config('nudge.queue.tries', 5));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $backoff = config('nudge.queue.backoff', [10, 60, 300, 900]);

        return array_values(array_map(intval(...), is_array($backoff) ? $backoff : [(int) $backoff]));
    }

    public function displayName(): string
    {
        // The path can hold an email address, so only the endpoint's first segment is shown.
        return 'Nudge: ' . $this->request->method . ' ' . strtok($this->request->path, '/');
    }
}
