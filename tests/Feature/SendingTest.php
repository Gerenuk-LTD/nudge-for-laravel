<?php

use Carbon\CarbonImmutable;
use Gerenuk\Nudge\Data\Contact;
use Gerenuk\Nudge\Enums\ContactSource;
use Gerenuk\Nudge\Enums\ContactStatus;
use Gerenuk\Nudge\Exceptions\ConnectionFailedException;
use Gerenuk\Nudge\Exceptions\NudgeException;
use Gerenuk\Nudge\Exceptions\RequestException;
use Gerenuk\Nudge\Facades\Nudge;
use Gerenuk\Nudge\Jobs\SendToNudge;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('saves a contact, sending only what was set, and resubscribes explicitly', function () {
    Http::fake([
        'nudge.test/api/v1/contacts/*' => Http::sequence()
            ->push(['data' => contactJson([
                'email' => 'ada+lc@example.com',
                'last_name' => null,
                'custom_fields' => ['first_order' => '2026-11-01', 'orders' => 2],
                'tags' => ['customer', 'marketing'],
                'lists' => [3, 4],
            ])], 201)
            ->push(['data' => contactJson()], 200),
    ]);

    $contact = Nudge::contact('ada+lc@example.com')
        ->name('Ada', null)
        ->tags(['customer', 'marketing'])
        ->tags('customer')
        ->lists([3, 4])
        ->customFields(['first_order' => CarbonImmutable::parse('2026-11-01 14:30'), 'orders' => 2])
        ->save();

    // Only what was set is sent, and the email is encoded so "+" survives the path.
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://nudge.test/api/v1/contacts/ada%2Blc%40example.com'
        && $request->hasHeader('Authorization', 'Bearer gmk_test')
        && $request->hasHeader('Accept', 'application/json')
        && $request->data() === [
            'first_name' => 'Ada',
            'last_name' => null,
            'custom_fields' => ['first_order' => '2026-11-01', 'orders' => 2],
            'tags' => ['customer', 'marketing'],
            'lists' => [3, 4],
        ]);

    expect($contact)
        ->toBeInstanceOf(Contact::class)
        ->email->toBe('ada+lc@example.com')
        ->wasCreated->toBeTrue()
        ->status->toBe(ContactStatus::Subscribed)
        ->source->toBe(ContactSource::Api)
        ->lists->toBe([3, 4])
        ->customFields->toBe(['first_order' => '2026-11-01', 'orders' => 2])
        ->and($contact->name())->toBe('Ada')
        ->and($contact->hasTag('Marketing'))->toBeTrue()
        ->and($contact->createdAt?->toIso8601String())->toBe('2026-11-10T09:00:00+00:00');

    // Resubscribing is explicit, and sets the status with it.
    expect(Nudge::contact('ada@example.com')->resubscribe()->save()->wasCreated)->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->data() === ['status' => 'subscribed', 'resubscribe' => true]);
});

it('tracks an event, and refuses bad input before sending anything', function () {
    Http::fake(['nudge.test/api/v1/events' => Http::response(['data' => [
        'id' => 42,
        'type' => 'custom',
        'data' => ['name' => 'order.completed', 'properties' => ['order_id' => 7, 'total' => 39.55, 'products' => ['installer-2026']]],
        'campaign_id' => null,
        'occurred_at' => '2026-11-10T09:00:00+00:00',
    ]], 202)]);

    // Bad input is refused before anything is sent or queued.
    expect(fn () => Nudge::event('order completed', 'ada@example.com'))->toThrow(InvalidArgumentException::class, 'not a valid event name')
        ->and(fn () => Nudge::event(str_repeat('a', 101), 'ada@example.com'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Nudge::contact('not-an-email'))->toThrow(InvalidArgumentException::class, 'not a valid email');

    // The bad email stays out of the message, so it's safe to log.
    foreach ([fn () => Nudge::contact('ada@@example.com'), fn () => Nudge::event('registered', 'ada@@example.com')] as $build) {
        try {
            $build();
            $message = null;
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }

        expect($message)->toContain('not a valid email')->not->toContain('ada@@example.com');
    }

    Http::assertNothingSent();

    $event = Nudge::event('order.completed', 'ada+lc@example.com')
        ->properties(['order_id' => 7, 'total' => 39.55])
        ->property('products', ['installer-2026'])
        ->createContact()
        ->send();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://nudge.test/api/v1/events'
        && $request->data() === [
            'email' => 'ada+lc@example.com',
            'event' => 'order.completed',
            'properties' => ['order_id' => 7, 'total' => 39.55, 'products' => ['installer-2026']],
            'create_contact' => true,
        ]);

    expect($event->id)->toBe(42)
        ->and($event->name)->toBe('order.completed')
        ->and($event->properties)->toBe(['order_id' => 7, 'total' => 39.55, 'products' => ['installer-2026']])
        ->and($event->occurredAt?->toDateString())->toBe('2026-11-10');
});

it('sends each event with an idempotency key, made once and kept for retries', function () {
    Http::fake(['nudge.test/api/v1/*' => Http::response(['data' => ['id' => 1, 'type' => 'custom', 'data' => ['name' => 'registered', 'properties' => []], 'campaign_id' => null, 'occurred_at' => '2026-11-10T09:00:00+00:00']], 202)]);

    Nudge::event('registered', 'ada@example.com')->send();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key')
        && Str::isUuid($request->header('Idempotency-Key')[0]));

    // The key is fixed once made, and each event gets its own.
    $event = Nudge::event('registered', 'ada@example.com');

    expect($event->toRequest()->idempotencyKey)->toBeString()->toBe($event->toRequest()->idempotencyKey)
        ->and(Nudge::event('registered', 'ada@example.com')->toRequest()->idempotencyKey)->not->toBe($event->toRequest()->idempotencyKey);

    // A queued event keeps its key when the job is serialised for a retry.
    Queue::fake();
    $queued = Nudge::event('registered', 'ada@example.com');
    $queued->queue();

    $job = Queue::pushed(SendToNudge::class)->first();

    expect($job->request->idempotencyKey)->toBeString()
        ->and(unserialize(serialize($job))->request->idempotencyKey)->toBe($job->request->idempotencyKey)
        ->and($queued->toRequest()->idempotencyKey)->toBe($job->request->idempotencyKey);
});

it('uses your own idempotency key, and sends none with a contact save', function () {
    Http::fake([
        'nudge.test/api/v1/events' => Http::response(['data' => ['id' => 1, 'type' => 'custom', 'data' => ['name' => 'order.completed', 'properties' => []], 'campaign_id' => null, 'occurred_at' => '2026-11-10T09:00:00+00:00']], 202),
        'nudge.test/api/v1/contacts/*' => Http::response(['data' => contactJson()], 200),
    ]);

    Nudge::event('order.completed', 'ada@example.com')->idempotencyKey('order-42')->send();
    Nudge::contact('ada@example.com')->tags('customer')->save();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/events')
        && $request->hasHeader('Idempotency-Key', 'order-42'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/contacts/')
        && ! $request->hasHeader('Idempotency-Key'));
});

it('refuses a bad idempotency key without repeating it', function (string $key) {
    $message = null;

    try {
        Nudge::event('order.completed', 'ada@example.com')->idempotencyKey($key);
    } catch (InvalidArgumentException $exception) {
        $message = $exception->getMessage();
    }

    // An empty key is in every message, so only longer keys can be checked.
    expect($message)->toBeString()
        ->and($key === '' || ! str_contains($message ?? '', $key))->toBeTrue();
})->with([
    'empty' => '',
    'too long' => str_repeat('k', 256),
    'space' => 'has space',
]);
it('queues on the configured queue, chains several requests, and queues nothing while off', function () {
    Bus::fake();

    Nudge::contact('ada@example.com')->tags('customer')->queue();

    Bus::assertDispatched(SendToNudge::class, fn (SendToNudge $job): bool => $job->request->is('PUT', 'contacts/ada%40example.com')
        && $job->request->body === ['tags' => ['customer']]
        && $job->connection === 'redis'
        && $job->queue === 'marketing'
        && $job->tries() === 5
        && $job->backoff() === [10, 60, 300, 900]
        && $job->displayName() === 'Nudge: PUT contacts');

    // A contact and its first event are chained, so the event can't arrive before the contact exists.
    Nudge::queue(
        Nudge::contact('grace@example.com')->name('Grace', 'Hopper'),
        Nudge::event('registered', 'grace@example.com'),
    );

    Bus::assertChained([
        fn (SendToNudge $job): bool => $job->request->is('PUT', 'contacts/grace%40example.com'),
        fn (SendToNudge $job): bool => $job->request->is('POST', 'events') && $job->request->body === ['email' => 'grace@example.com', 'event' => 'registered'],
    ]);

    // Nothing is queued while sending is off.
    config()->set('nudge.enabled', false);
    Nudge::event('registered', 'linus@example.com')->queue();

    // The single job, plus the head of the chain.
    Bus::assertDispatchedTimes(SendToNudge::class, 2);
});

it('finishes when Nudge accepts, fails for good when it refuses, and drops jobs while off', function () {
    // Http::fake() keeps earlier stubs, so one stub answers with whatever $respond gives.
    $respond = null;
    Http::fake(function () use (&$respond) {
        return $respond();
    });

    $respond = fn () => Http::response(['data' => ['id' => 1, 'type' => 'custom', 'data' => ['name' => 'registered', 'properties' => []], 'campaign_id' => null, 'occurred_at' => '2026-11-10T09:00:00+00:00']], 202);
    runNudgeJob()->assertNotFailed()->assertNotReleased();

    $respond = fn () => Http::response(['code' => 'contact_not_found', 'message' => 'No contact with that email.'], 404);
    runNudgeJob()->assertFailed();

    Http::assertSentCount(2);

    // A job queued before sending was turned off is dropped when it runs, without a request.
    config()->set('nudge.enabled', false);
    runNudgeJob()->assertNotFailed();

    Http::assertSentCount(2);
});

it('releases a rate-limited job for as long as Nudge says', function (array $headers, int $seconds) {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
    Http::fake(['*' => Http::response(['message' => 'Too many attempts.'], 429, $headers)]);

    runNudgeJob()->assertReleased($seconds);
})->with([
    'seconds' => [['Retry-After' => '30'], 30],
    'http date' => [['Retry-After' => 'Tue, 10 Nov 2026 09:05:00 GMT'], 300],
    'no header' => [[], 60],
]);

it('throws so the queue retries', function (string $scenario) {
    [$class, $message] = match ($scenario) {
        'server error' => [RequestException::class, 'Server error'],
        'connection failure' => [ConnectionFailedException::class, "Couldn't reach Nudge"],
        'not configured' => [NudgeException::class, 'not configured'],
    };

    match ($scenario) {
        'server error' => Http::fake(['*' => Http::response(['message' => 'Server error'], 500)]),
        'connection failure' => Http::fake(['*' => Http::failedConnection()]),
        'not configured' => config()->set('nudge.token', null),
    };

    expect(fn () => runNudgeJob())->toThrow($class, $message);
})->with(['server error', 'connection failure', 'not configured']);
