<?php

namespace Gerenuk\Nudge\Data;

use Illuminate\Support\Collection;

/**
 * One page of a cursor-paginated list. Pass `nextCursor` back to get the next page.
 *
 * @template TItem
 */
final readonly class CursorPage
{
    /**
     * @param  Collection<int, TItem>  $items
     */
    public function __construct(
        public Collection $items,
        public ?string $nextCursor,
    ) {}

    public function hasMorePages(): bool
    {
        return $this->nextCursor !== null;
    }
}
