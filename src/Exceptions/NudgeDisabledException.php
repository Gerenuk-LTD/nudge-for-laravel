<?php

namespace Gerenuk\Nudge\Exceptions;

/**
 * A direct call was made while `nudge.enabled` is false. Queued requests are dropped instead.
 */
final class NudgeDisabledException extends NudgeException
{
    public function __construct()
    {
        parent::__construct('Nudge is disabled. Set NUDGE_ENABLED=true to send requests.');
    }
}
