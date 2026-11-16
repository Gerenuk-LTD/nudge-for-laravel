<?php

namespace Gerenuk\Nudge\Data;

/**
 * The result of adding contacts to a list or removing them. Unknown emails are reported back,
 * never created.
 */
final readonly class ListMembershipChange
{
    /**
     * @param  int  $count  contacts added or removed
     * @param  list<string>  $notFound  emails with no contact in Nudge
     */
    public function __construct(
        public int $count,
        public array $notFound,
    ) {}
}
