<?php

namespace Gerenuk\Nudge\Facades;

use Gerenuk\Nudge\Nudge as NudgeClient;
use Gerenuk\Nudge\Testing\NudgeFake;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Gerenuk\Nudge\Pending\PendingContact contact(string $email)
 * @method static \Gerenuk\Nudge\Pending\PendingEvent event(string $event, string $email)
 * @method static \Gerenuk\Nudge\Endpoints\Contacts contacts()
 * @method static \Gerenuk\Nudge\Endpoints\Lists lists()
 * @method static \Gerenuk\Nudge\Endpoints\Tags tags()
 * @method static \Gerenuk\Nudge\Endpoints\CustomFields customFields()
 * @method static \Gerenuk\Nudge\Endpoints\Segments segments()
 * @method static \Gerenuk\Nudge\Data\Me me()
 * @method static bool enabled()
 * @method static \Gerenuk\Nudge\Http\ApiResponse send(\Gerenuk\Nudge\Http\ApiRequest|\Gerenuk\Nudge\Contracts\BuildsRequest $request)
 * @method static void queue(\Gerenuk\Nudge\Http\ApiRequest|\Gerenuk\Nudge\Contracts\BuildsRequest ...$requests)
 *
 * @see NudgeClient
 */
class Nudge extends Facade
{
    /**
     * Replace Nudge with a fake that records requests instead of sending them. Queued requests
     * are recorded straight away, so tests don't need to run the queue.
     */
    public static function fake(): NudgeFake
    {
        $fake = new NudgeFake(static::getFacadeApplication()?->make(Repository::class) ?? config());

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return NudgeClient::class;
    }
}
