<?php

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Exceptions\AuthenticationException;
use Gerenuk\Nudge\Exceptions\AuthorizationException;
use Gerenuk\Nudge\Exceptions\ConnectionFailedException;
use Gerenuk\Nudge\Exceptions\NudgeDisabledException;
use Gerenuk\Nudge\Exceptions\NudgeException;
use Gerenuk\Nudge\Exceptions\RateLimitedException;
use Gerenuk\Nudge\Exceptions\RequestException;
use Gerenuk\Nudge\Exceptions\ValidationException;
use Gerenuk\Nudge\Facades\Nudge;
use Gerenuk\Nudge\Http\ApiRequest;
use Illuminate\Support\Facades\Http;

it('turns error statuses into typed exceptions whose messages leave out the email', function () {
    $failures = [
        [422, ['code' => 'validation_failed', 'message' => 'The given data was invalid.', 'errors' => ['tags.0' => ['Too long.']]], [], ValidationException::class, true, 'The given data was invalid. (PUT contacts/{email})', 'validation_failed'],
        [401, ['code' => 'unauthenticated', 'message' => 'Unauthenticated.'], [], AuthenticationException::class, true, 'Unauthenticated. (PUT contacts/{email})', 'unauthenticated'],
        [403, ['code' => 'missing_permission', 'message' => 'This token does not have the contacts.manage permission.'], [], AuthorizationException::class, true, 'This token does not have the contacts.manage permission. (PUT contacts/{email})', 'missing_permission'],
        [429, ['code' => 'rate_limited', 'message' => 'Too many requests.'], ['Retry-After' => '30'], RateLimitedException::class, false, 'Too many requests. (PUT contacts/{email})', 'rate_limited'],
        [500, 'Server error', [], RequestException::class, false, 'Nudge returned HTTP 500. (PUT contacts/{email})', null],
    ];

    // Http::fake() keeps earlier stubs, so one stub answers with whatever $respond gives.
    $respond = null;
    Http::fake(function () use (&$respond) {
        return $respond();
    });

    foreach ($failures as [$status, $body, $headers, $class, $permanent, $message, $code]) {
        $respond = fn () => Http::response($body, $status, $headers);

        try {
            Nudge::contact('ada@example.com')->tags('customer')->save();
            $this->fail("Expected {$class}.");
        } catch (RequestException $exception) {
            expect($exception)->toBeInstanceOf($class, "HTTP {$status}")
                ->and($exception->isPermanent())->toBe($permanent, "HTTP {$status}")
                ->and($exception->getMessage())->toBe($message, "HTTP {$status}")
                ->and($exception->errorCode())->toBe($code, "HTTP {$status}")
                ->and($exception->getMessage())->not->toContain('ada', "HTTP {$status}")
                ->and($exception->request->path)->toBe('contacts/ada%40example.com', "HTTP {$status}");
        }
    }

    $respond = fn () => Http::response(['message' => 'Invalid.', 'errors' => ['tags.0' => ['Too long.']]], 422);

    try {
        Nudge::contact('ada@example.com')->save();
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['tags.0' => ['Too long.']]);
    }
});

it('reads Retry-After as seconds or a date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));

    $cases = [
        'seconds' => [['Retry-After' => '30'], 30],
        'http date' => [['Retry-After' => 'Tue, 10 Nov 2026 09:02:00 GMT'], 120],
        'date in the past' => [['Retry-After' => 'Tue, 10 Nov 2026 08:00:00 GMT'], 0],
        'negative seconds' => [['Retry-After' => '-5'], 0],
        'unreadable' => [['Retry-After' => 'soon'], null],
        'missing' => [[], null],
    ];

    $respond = null;
    Http::fake(function () use (&$respond) {
        return $respond();
    });

    foreach ($cases as $label => [$headers, $expected]) {
        $respond = fn () => Http::response(null, 429, $headers);

        try {
            Nudge::me();
            $this->fail("Expected a RateLimitedException ({$label}).");
        } catch (RateLimitedException $exception) {
            expect($exception->retryAfter())->toBe($expected, "Retry-After {$label}");
        }
    }
});

it('reports connection failures without the email, and nudge:check says so', function () {
    Http::fake(['*' => Http::failedConnection()]);

    expect(fn () => Nudge::contacts()->find('ada@example.com'))
        ->toThrow(ConnectionFailedException::class, "Couldn't reach Nudge for GET contacts/{email}.");

    $this->artisan('nudge:check')->expectsOutputToContain("Couldn't reach Nudge")->assertFailed();

    expect(ApiRequest::put('contacts/' . ApiRequest::segment('ada+lc@example.com'))->redacted())->toBe('PUT contacts/{email}')
        ->and(ApiRequest::get('contacts/' . ApiRequest::segment('ada@example.com') . '/events')->redacted())->toBe('GET contacts/{email}/events')
        ->and(ApiRequest::get('lists/3')->redacted())->toBe('GET lists/3')
        ->and(ApiRequest::get('me')->redacted())->toBe('GET me');
});

it('refuses to send when not configured or turned off, and nudge:check says so', function () {
    config()->set('nudge.token', null);

    expect(fn () => Nudge::me())->toThrow(NudgeException::class, 'Set NUDGE_URL and NUDGE_TOKEN');

    config()->set('nudge.enabled', false);

    expect(fn () => Nudge::me())->toThrow(NudgeDisabledException::class);

    $this->artisan('nudge:check')->expectsOutputToContain('Nudge is disabled')->assertSuccessful();
});
