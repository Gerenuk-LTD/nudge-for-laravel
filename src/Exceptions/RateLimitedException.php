<?php

namespace Gerenuk\Nudge\Exceptions;

/**
 * Too many requests. Queued requests wait for the Retry-After time before trying again.
 */
final class RateLimitedException extends RequestException
{
    public function retryAfter(): ?int
    {
        return $this->response->retryAfter;
    }
}
