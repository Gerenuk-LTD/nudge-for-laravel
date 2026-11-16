<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When false, nothing is sent to Nudge. Queued requests are dropped, both
    | when they're queued and when the job runs, and direct calls throw a
    | NudgeDisabledException rather than pretending to succeed.
    |
    */

    'enabled' => (bool) env('NUDGE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    |
    | The address of your Nudge install, and the API path it serves. The
    | token is created in Nudge under API tokens. Sending contacts and events
    | needs the contacts.manage permission, and reading needs contacts.view.
    |
    */

    'url' => env('NUDGE_URL'),

    'path' => env('NUDGE_API_PATH', 'api/v1'),

    'token' => env('NUDGE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | In seconds. Direct calls aren't retried, so keep these short when a
    | request runs while a user waits. Queued requests retry on their own.
    |
    */

    'timeout' => (int) env('NUDGE_TIMEOUT', 10),

    'connect_timeout' => (int) env('NUDGE_CONNECT_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Where queued requests go, and how they retry. Connection failures,
    | server errors and rate limits are retried. Requests Nudge refuses, such
    | as failed validation, fail straight away, as retrying can't fix them.
    |
    */

    'queue' => [
        'connection' => env('NUDGE_QUEUE_CONNECTION'),
        'name' => env('NUDGE_QUEUE'),
        'tries' => 5,
        'backoff' => [10, 60, 300, 900],
    ],

];
