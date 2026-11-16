<?php

namespace Gerenuk\Nudge\Enums;

/**
 * How a contact first reached Nudge.
 */
enum ContactSource: string
{
    case Manual = 'manual';
    case Api = 'api';
    case Import = 'import';
}
