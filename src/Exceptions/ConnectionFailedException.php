<?php

namespace Gerenuk\Nudge\Exceptions;

use Gerenuk\Nudge\Http\ApiRequest;
use Throwable;

/**
 * Nudge couldn't be reached, or didn't answer in time. Worth retrying.
 */
final class ConnectionFailedException extends NudgeException
{
    public function __construct(public readonly ApiRequest $request, ?Throwable $previous = null)
    {
        parent::__construct("Couldn't reach Nudge for {$request->redacted()}.", 0, $previous);
    }
}
