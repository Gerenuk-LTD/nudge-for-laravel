<?php

namespace Gerenuk\Nudge\Enums;

/**
 * The kinds of entry on a contact's timeline. `Custom` is an event your app sent.
 */
enum ActivityType: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Deferred = 'deferred';
    case Bounced = 'bounced';
    case Dropped = 'dropped';
    case Complained = 'complained';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Unsubscribed = 'unsubscribed';
    case Resubscribed = 'resubscribed';
    case Paused = 'paused';
    case Custom = 'custom';
    case AutomationEntered = 'automation_entered';
    case AutomationCompleted = 'automation_completed';
    case AutomationExited = 'automation_exited';
}
