<?php

use Gerenuk\Nudge\Data\Activity;
use Gerenuk\Nudge\Data\Contact;
use Gerenuk\Nudge\Enums\ActivityType;
use Gerenuk\Nudge\Enums\ContactStatus;
use Gerenuk\Nudge\Enums\CustomFieldType;
use Gerenuk\Nudge\Exceptions\NudgeException;
use Gerenuk\Nudge\Facades\Nudge;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('reads contacts: every page, one page, one contact, and archiving', function () {
    fakeNudgeApi();

    // Every page is fetched lazily, following the cursor.
    $emails = Nudge::contacts()->all(status: ContactStatus::Subscribed, tag: 9, archived: false)->map->email->all();

    expect($emails)->toBe(['ada@example.com', 'grace@example.com']);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'contacts?status=subscribed&tag=9&archived=0&per_page=500&cursor=page-2'));

    $page = Nudge::contacts()->list(search: 'ada', perPage: 1);
    expect($page->hasMorePages())->toBeTrue()->and($page->nextCursor)->toBe('page-2');

    // Finding a contact gives null rather than an exception when there isn't one.
    $contact = Nudge::contacts()->find('ada@example.com');

    expect($contact?->isSubscribed())->toBeFalse()
        ->and($contact?->unsubscribedAt?->toDateString())->toBe('2026-11-09')
        ->and($contact?->source)->toBeNull()
        ->and(Nudge::contacts()->find('nobody@example.com'))->toBeNull();

    Nudge::contacts()->archive('ada@example.com');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === 'https://nudge.test/api/v1/contacts/ada%40example.com');
});

it('reads timelines and statuses, keeping values it does not know as null', function () {
    fakeNudgeApi();

    $activity = Nudge::contacts()->allActivity('ada@example.com')->all();

    expect($activity)->toHaveCount(3)
        ->and($activity[0]->id)->toBe(30)
        ->and($activity[0]->eventName())->toBe('registered')
        ->and($activity[0]->properties())->toBe(['via' => 'site'])
        ->and($activity[1]->type)->toBe(ActivityType::Opened)
        ->and($activity[1]->campaignId)->toBe(5)
        ->and($activity[1]->eventName())->toBeNull()
        ->and($activity[2]->type)->toBeNull()
        ->and($activity[2]->rawType)->toBe('a_future_type');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'contacts/ada%40example.com/events?per_page=500'));

    $subscribed = Contact::fromArray(contactJson(['status' => 'subscribed']));
    $unsubscribed = Contact::fromArray(contactJson(['status' => 'unsubscribed']));
    $unknown = Contact::fromArray(contactJson(['status' => 'bounced']));
    $missing = Contact::fromArray(array_diff_key(contactJson(), ['status' => true]));

    expect($subscribed->status)->toBe(ContactStatus::Subscribed)
        ->and($subscribed->isSubscribed())->toBeTrue()
        ->and($subscribed->isUnsubscribed())->toBeFalse()
        ->and($unsubscribed->status)->toBe(ContactStatus::Unsubscribed)
        ->and($unsubscribed->isSubscribed())->toBeFalse()
        ->and($unsubscribed->isUnsubscribed())->toBeTrue()
        ->and($unknown->status)->toBeNull()
        ->and($unknown->isSubscribed())->toBeFalse()
        ->and($unknown->isUnsubscribed())->toBeFalse()
        ->and($missing->status)->toBeNull()
        ->and($missing->isSubscribed())->toBeFalse();

    // A date it cannot parse is null instead of an exception.
    $unreadable = Activity::fromArray(['type' => 'opened', 'data' => [], 'occurred_at' => 'not a date']);
    $garbled = Contact::fromArray(contactJson(['created_at' => 'garbage']));

    expect($unreadable->occurredAt)->toBeNull()
        ->and($garbled->createdAt)->toBeNull();
});

it('stops paging when Nudge repeats a cursor', function () {
    Http::fake(['nudge.test/api/v1/contacts*' => Http::response([
        'data' => [contactJson()],
        'meta' => ['next_cursor' => 'same'],
    ])]);

    $seen = [];

    expect(function () use (&$seen) {
        foreach (Nudge::contacts()->all() as $contact) {
            $seen[] = $contact->email;
        }
    })->toThrow(NudgeException::class, 'same page cursor twice');

    // The first page (no cursor) and the second (cursor "same") were read; the third request was never made.
    expect($seen)->toHaveCount(2);
    Http::assertSentCount(2);
});

it('manages lists, adding contacts in batches of 1,000', function () {
    fakeNudgeApi();

    expect(Nudge::lists()->all()->sole())->name->toBe('Customers')->contactsCount->toBe(12)
        ->and(Nudge::lists()->all()->sole()->updatedAt?->toDateString())->toBe('2026-02-01')
        ->and(Nudge::lists()->create('Renewals', 'Owners of last year'))->id->toBe(2)->description->toBe('Owners of last year')->contactsCount->toBe(0)
        ->and(Nudge::lists()->update(2, name: 'Renamed', description: ''))->name->toBe('Renamed')->contactsCount->toBe(3)
        ->and(Nudge::lists()->find(99))->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request->data() === ['name' => 'Renamed', 'description' => null]);

    $emails = collect(range(1, 1500))->map(fn (int $i): string => "user{$i}@example.com")->push('user1@example.com');
    $added = Nudge::lists()->addContacts(2, $emails);

    expect($added->count)->toBe(1498)
        ->and($added->notFound)->toBe(['user1000@example.com', 'user1500@example.com'])
        ->and(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), 'lists/2/contacts')))->toHaveCount(2);

    expect(Nudge::lists()->removeContacts(2, ['ada@example.com'])->count)->toBe(1);
    Nudge::lists()->delete(2);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), 'lists/2'));
});

it('reads tags, custom fields, segments and the token, and nudge:check reports a missing permission', function () {
    fakeNudgeApi();

    // Every page is fetched, following the cursor.
    $tags = Nudge::tags()->all()->all();

    expect($tags)->toHaveCount(2)
        ->and($tags[0])->name->toBe('customer')->contactsCount->toBe(40)
        ->and($tags[0]->updatedAt?->toDateString())->toBe('2026-10-02')
        ->and($tags[1]->name)->toBe('trial')
        ->and(Nudge::tags()->list(perPage: 1)->nextCursor)->toBe('tags-2');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'tags?per_page=500&cursor=tags-2'));

    expect(Nudge::customFields()->all()->sole()->type)->toBe(CustomFieldType::Number)
        ->and(Nudge::customFields()->all()->sole()->createdAt?->toDateString())->toBe('2026-10-01')
        ->and(Nudge::customFields()->create('first_order', 'First order', CustomFieldType::Date)->type)->toBe(CustomFieldType::Date);

    expect(Nudge::segments()->all()->sole())->name->toBe('Lapsed')->definition->toBe(['match' => 'all', 'rules' => []])->contactsCount->toBeNull()
        ->and(Nudge::segments()->find(3))->definition->toBe(['match' => 'all', 'rules' => []])->contactsCount->toBe(1)
        ->and(Nudge::segments()->allContacts(3)->count())->toBe(1);

    $me = Nudge::me();

    expect($me->workspaceName)->toBe('London Control')
        ->and($me->can('contacts.view'))->toBeTrue()
        ->and($me->can('contacts.manage'))->toBeFalse();

    $this->artisan('nudge:check')
        ->expectsOutputToContain('Connected to the "London Control" workspace')
        ->expectsOutputToContain("doesn't have the contacts.manage permission")
        ->assertFailed();
});
