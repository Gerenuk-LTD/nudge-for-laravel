<?php

namespace Gerenuk\Nudge\Enums;

/**
 * Dates are sent as `Y-m-d`.
 */
enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
}
