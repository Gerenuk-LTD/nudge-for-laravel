<?php

namespace Gerenuk\Nudge\Contracts;

use Gerenuk\Nudge\Exceptions\ConnectionFailedException;
use Gerenuk\Nudge\Exceptions\NudgeException;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Http\ApiResponse;

/**
 * Carries a request to Nudge and brings back whatever it answered, error statuses included.
 */
interface Transport
{
    /**
     * @throws ConnectionFailedException when Nudge can't be reached
     * @throws NudgeException when the URL or token isn't set
     */
    public function send(ApiRequest $request): ApiResponse;
}
