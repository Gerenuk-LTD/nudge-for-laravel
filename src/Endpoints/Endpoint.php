<?php

namespace Gerenuk\Nudge\Endpoints;

use Closure;
use Gerenuk\Nudge\Data\CursorPage;
use Gerenuk\Nudge\Exceptions\NotFoundException;
use Gerenuk\Nudge\Exceptions\NudgeException;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Http\ApiResponse;
use Gerenuk\Nudge\Nudge;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * @internal
 */
abstract readonly class Endpoint
{
    public function __construct(protected Nudge $nudge) {}

    /**
     * Send a request, giving null when Nudge has nothing at that address.
     */
    protected function sendOrNull(ApiRequest $request): ?ApiResponse
    {
        try {
            return $this->nudge->send($request);
        } catch (NotFoundException) {
            return null;
        }
    }

    /**
     * @template TItem
     *
     * @param  Closure(array<array-key, mixed>): TItem  $map
     * @return Collection<int, TItem>
     */
    protected function collect(ApiResponse $response, Closure $map): Collection
    {
        return collect($response->data())
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): mixed => $map($item))
            ->values();
    }

    /**
     * Read a cursor page. Every Nudge list puts the next cursor under "meta".
     *
     * @template TItem
     *
     * @param  Closure(array<array-key, mixed>): TItem  $map
     * @return CursorPage<TItem>
     */
    protected function page(ApiResponse $response, Closure $map): CursorPage
    {
        $meta = $response->json['meta'] ?? null;
        $cursor = is_array($meta) ? ($meta['next_cursor'] ?? null) : null;

        return new CursorPage($this->collect($response, $map), is_string($cursor) && $cursor !== '' ? $cursor : null);
    }

    /**
     * Walk every page lazily, fetching the next one only when it's needed.
     * Throws a NudgeException if Nudge sends a cursor it already sent.
     *
     * @template TItem
     *
     * @param  Closure(?string): CursorPage<TItem>  $fetch
     * @return LazyCollection<int, TItem>
     */
    protected function everyPage(Closure $fetch): LazyCollection
    {
        return LazyCollection::make(function () use ($fetch) {
            $cursor = null;
            $seen = [];

            do {
                $page = $fetch($cursor);

                // Yielded one by one so keys run on across pages instead of restarting at 0.
                foreach ($page->items as $item) {
                    yield $item;
                }

                $cursor = $page->nextCursor;

                if ($cursor !== null) {
                    if (isset($seen[$cursor])) {
                        throw NudgeException::repeatedCursor();
                    }

                    $seen[$cursor] = true;
                }
            } while ($cursor !== null);
        });
    }
}
