<?php

use Gerenuk\Nudge\Exceptions\NudgeException;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Gerenuk\Nudge\Data')
    ->toBeReadonly()
    ->toBeFinal();

arch('every exception extends the base exception')
    ->expect('Gerenuk\Nudge\Exceptions')
    ->toExtend(NudgeException::class)
    ->ignoring(NudgeException::class);
