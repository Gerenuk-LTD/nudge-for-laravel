<?php

use Gerenuk\Nudge\Exceptions\NudgeDisabledException;
use Gerenuk\Nudge\Exceptions\ValidationException;
use Gerenuk\Nudge\Facades\Nudge;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Testing\RecordedRequest;
use PHPUnit\Framework\ExpectationFailedException;

it('records requests and answers like Nudge would', function () {
    $fake = Nudge::fake();

    $fake->assertNothingSent();

    // Saves and events answer like Nudge would, with nothing sent over HTTP.
    $contact = Nudge::contact('Ada@Example.com')->name('Ada', 'Lovelace')->tags(['customer'])->save();

    expect($contact)->email->toBe('ada@example.com')->wasCreated->toBeTrue()->tags->toBe(['customer'])
        ->and(Nudge::contact('ada@example.com')->save()->wasCreated)->toBeFalse()
        ->and(Nudge::event('registered', 'ada@example.com')->send()->name)->toBe('registered');

    // Queued requests are recorded straight away.
    Nudge::event('order.completed', 'ada@example.com')->queue();

    expect($fake->recorded(fn (RecordedRequest $recorded): bool => $recorded->queued))->toHaveCount(1)
        ->and($fake->recorded())->toHaveCount(4);

    // Reads come back empty, and finding one record gives null.
    expect(Nudge::contacts()->find('ada@example.com'))->toBeNull()
        ->and(Nudge::lists()->all())->toBeEmpty()
        ->and(Nudge::me()->can('contacts.manage'))->toBeTrue();

    // Deletes are answered too: an unanswered request would come back as a 404 and throw.
    Nudge::contacts()->archive('ada@example.com');
    Nudge::lists()->delete(3);

    $fake->assertSent(fn (RecordedRequest $recorded): bool => $recorded->request->is('DELETE', 'contacts/*'));
    $fake->assertSent(fn (RecordedRequest $recorded): bool => $recorded->request->is('DELETE', 'lists/*'));
});

it('asserts on what was sent, and explains failures', function () {
    $fake = Nudge::fake();

    Nudge::contact('Ada@Example.com')->name('Ada', 'Lovelace')->tags(['customer'])->save();
    Nudge::contact('ada@example.com')->save();
    Nudge::event('registered', 'ada@example.com')->send();
    Nudge::event('order.completed', 'ada@example.com')->properties(['order_id' => 7])->queue();
    Nudge::event('order.completed', 'grace@example.com')->properties(['order_id' => 8])->queue();

    $fake->assertSentCount(5);
    $fake->assertContactSaved('ada@example.com', fn (array $body): bool => ($body['first_name'] ?? null) === 'Ada' && $body['tags'] === ['customer']);
    $fake->assertContactNotSaved('grace@example.com');
    $fake->assertEventTracked('registered');
    $fake->assertEventTracked('order.completed', times: 2);
    $fake->assertEventTracked('order.completed', fn (array $properties, string $email): bool => $email === 'grace@example.com' && $properties['order_id'] === 8, times: 1);
    $fake->assertEventNotTracked('downloaded');
    $fake->assertSent(fn (RecordedRequest $recorded): bool => $recorded->queued && $recorded->eventName() === 'order.completed');
    $fake->assertNotSent(fn (RecordedRequest $recorded): bool => $recorded->request->method === 'DELETE');

    // Failed assertions explain what went wrong.
    expect(fn () => $fake->assertEventTracked('downloaded'))->toThrow(ExpectationFailedException::class, 'The [downloaded] event was not tracked.')
        ->and(fn () => $fake->assertEventTracked('registered', times: 2))->toThrow(ExpectationFailedException::class, 'tracked 1 times instead of 2')
        ->and(fn () => $fake->assertNothingSent())->toThrow(ExpectationFailedException::class, 'PUT contacts/Ada%40Example.com');
});

it('answers stubbed requests, matching one path segment per wildcard', function () {
    $fake = Nudge::fake();

    $fake->respond('GET', 'contacts/*', fn (ApiRequest $request): array => ['data' => contactJson(['email' => rawurldecode(substr($request->path, 9))])])
        ->respond('PUT', 'contacts/*', ['message' => 'The given data was invalid.', 'errors' => ['tags.0' => ['Too long.']]], 422);

    expect(Nudge::contacts()->find('grace@example.com')?->email)->toBe('grace@example.com')
        ->and(fn () => Nudge::contact('ada@example.com')->save())->toThrow(ValidationException::class);

    // A contacts stub does not answer the activity endpoint.
    expect(Nudge::contacts()->allActivity('ada@example.com')->all())->toBe([]);

    // A stub can name a plain email address, matching it as written or encoded.
    $other = Nudge::fake();
    $other->respond('GET', 'contacts/ada+lc@example.com', ['data' => contactJson(['email' => 'ada+lc@example.com'])]);

    expect(Nudge::contacts()->find('ada+lc@example.com')?->email)->toBe('ada+lc@example.com')
        ->and(Nudge::contacts()->find('grace@example.com'))->toBeNull();

    $email = ApiRequest::segment('ada+lc@example.com');

    // [path of the request, method to match, pattern to match, expected]
    $cases = [
        ['contacts/ada%40example.com', 'GET', 'contacts/*', true],
        ['contacts/ada%40example.com/events', 'GET', 'contacts/*', false],
        ['contacts/ada%40example.com/events', 'GET', 'contacts/*/events', true],
        ['contacts', 'GET', 'contacts/*', false],
        ['me', 'get', 'me', true],
        ['me', 'POST', 'me', false],
        ["contacts/{$email}", 'GET', 'contacts/ada+lc@example.com', true],
        ["contacts/{$email}", 'GET', 'contacts/ada%2Blc%40example.com', true],
        ["contacts/{$email}", 'GET', 'contacts/grace@example.com', false],
        ['lists/3', 'GET', '/lists/3', true],
    ];

    foreach ($cases as [$path, $method, $pattern, $expected]) {
        expect(ApiRequest::get($path)->is($method, $pattern))->toBe($expected, "{$method} {$path} vs {$pattern}");
    }
});

it('follows the off switch', function () {
    $fake = Nudge::fake();

    config()->set('nudge.enabled', false);

    Nudge::event('downloaded', 'ada@example.com')->queue();

    expect(fn () => Nudge::me())->toThrow(NudgeDisabledException::class)
        ->and($fake->recorded())->toHaveCount(0);
});
