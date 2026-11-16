<?php

namespace Gerenuk\Nudge\Contracts;

use Gerenuk\Nudge\Http\ApiRequest;

/**
 * Something that can be sent now or queued, such as a pending contact or event.
 */
interface BuildsRequest
{
    public function toRequest(): ApiRequest;
}
