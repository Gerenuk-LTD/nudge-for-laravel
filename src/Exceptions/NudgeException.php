<?php

namespace Gerenuk\Nudge\Exceptions;

use RuntimeException;

/**
 * The base of every exception this package throws, so one catch covers them all.
 */
class NudgeException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Nudge is not configured. Set NUDGE_URL and NUDGE_TOKEN, or set NUDGE_ENABLED=false.');
    }

    public static function repeatedCursor(): self
    {
        return new self('Nudge returned the same page cursor twice, so paging stopped rather than loop forever.');
    }
}
