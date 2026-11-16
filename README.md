<img src="https://banners.beyondco.de/Nudge%20for%20Laravel.png?theme=light&packageManager=composer+require&packageName=gerenuk%2Fnudge-for-laravel&pattern=brickWall&style=style_1&description=Send+contacts+and+events+to+Nudge+from+your+Laravel+app&md=1&showWatermark=0&fontSize=100px&images=paper-airplane" alt="Project banner">

# Nudge for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gerenuk/nudge-for-laravel.svg?style=flat-square)](https://packagist.org/packages/gerenuk/nudge-for-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/gerenuk-ltd/nudge-for-laravel/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/gerenuk-ltd/nudge-for-laravel/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/gerenuk/nudge-for-laravel.svg?style=flat-square)](https://packagist.org/packages/gerenuk/nudge-for-laravel)
[![Licence](https://img.shields.io/github/license/Gerenuk-LTD/nudge-for-laravel?color=blue&label=license&style=flat-square)](https://github.com/Gerenuk-LTD/nudge-for-laravel/blob/main/LICENSE.md)

This package is the Laravel client for the [Nudge](https://github.com/Gerenuk-LTD/marketing-platform) marketing platform's API. Send contacts and events from your app, in the background with retries, and read contacts, lists, tags, custom fields and segments back as typed objects.

## Table of Contents
1. [Introduction](#nudge-for-laravel)
2. [Version Compatibility](#version-compatibility)
3. [Installation](#installation)
    - [Configuration](#configuration)
4. [Usage](#usage)
    - [Sending Now or Later](#sending-now-or-later)
    - [Saving Contacts](#saving-contacts)
    - [Unsubscribing and Resubscribing](#unsubscribing-and-resubscribing)
    - [Reading Contacts](#reading-contacts)
    - [Events](#events)
    - [Sending an Event Once](#sending-an-event-once)
    - [Reading a Timeline](#reading-a-timeline)
    - [Lists, Tags, Custom Fields and Segments](#lists-tags-custom-fields-and-segments)
    - [Errors](#errors)
    - [Turning Sending Off](#turning-sending-off)
    - [Testing Your App](#testing-your-app)
    - [Using the Client Without the Facade](#using-the-client-without-the-facade)
5. [Testing](#testing)
6. [Changelog](#changelog)
7. [Contributing](#contributing)
8. [Security Vulnerabilities](#security-vulnerabilities)
9. [Credits](#credits)
10. [License](#license)

## Version Compatibility

| Package | Nudge | Laravel | PHP      |
|---------|-------|---------|----------|
| 1.x     | 1.x   | 13.x    | 8.4\|8.5 |

You also need a Nudge API token. Sending needs the `contacts.manage` permission, and reading needs `contacts.view`.

## Installation

You can install the package via composer:

```bash
composer require gerenuk/nudge-for-laravel
```

Add your Nudge address and token to `.env`:

```dotenv
NUDGE_URL=https://nudge.example.com
NUDGE_TOKEN=gmk_...
```

Then check the connection and the token's permissions:

```bash
php artisan nudge:check
```

### Configuration

To change the defaults, publish the config:

```bash
php artisan vendor:publish --tag="nudge-for-laravel-config"
```

| Key | Env | Default | Notes |
|---|---|---|---|
| `enabled` | `NUDGE_ENABLED` | `true` | When false, nothing is sent. See [Turning Sending Off](#turning-sending-off) |
| `url` | `NUDGE_URL` | | Your Nudge install |
| `path` | `NUDGE_API_PATH` | `api/v1` | The API's path on that install |
| `token` | `NUDGE_TOKEN` | | Created in Nudge under API tokens |
| `timeout` | `NUDGE_TIMEOUT` | `10` | Seconds |
| `connect_timeout` | `NUDGE_CONNECT_TIMEOUT` | `5` | Seconds |
| `queue.connection` | `NUDGE_QUEUE_CONNECTION` | app default | |
| `queue.name` | `NUDGE_QUEUE` | app default | |
| `queue.tries` | | `5` | |
| `queue.backoff` | | `[10, 60, 300, 900]` | Seconds between tries |

## Usage

Everything starts from the `Nudge` facade:

```php
use Gerenuk\Nudge\Facades\Nudge;

Nudge::contact($user->email)
    ->name($user->first_name, $user->last_name)
    ->tags(['customer'])
    ->queue();

Nudge::event('order.completed', $user->email)
    ->properties(['order_id' => $order->id, 'total' => $order->total])
    ->queue();
```

### Sending Now or Later

Every contact and event can be sent two ways.

- **Now**, with `save()` for a contact or `send()` for an event. These call Nudge straight away and return what it answered: a `Contact` from `save()`, a `TrackedEvent` from `send()`. Use them when you need the result, such as in an admin screen. They aren't retried.
- **Later**, with `queue()`. This sends from a queued job, so a slow or unavailable Nudge never holds up your app. The job retries connection failures, server errors and rate limits, following Nudge's `Retry-After`. A request Nudge refuses, such as failed validation, fails straight away, because retrying can't fix it. Failed jobs go to your failed jobs table as usual.

Pass several requests to `Nudge::queue()` to send them in order. Each waits for the one before it:

```php
Nudge::queue(
    Nudge::contact($user->email)->name($user->name)->tags(['customer']),
    Nudge::event('registered', $user->email),
);
```

### Saving Contacts

`Nudge::contact()` creates the contact or updates it. Only what you set is sent, so anything you leave out keeps its value in Nudge. Custom fields are merged, and tags and lists are only ever added.

```php
$contact = Nudge::contact('ada@example.com')
    ->name('Ada', 'Lovelace')            // or ->firstName() and ->lastName()
    ->tags(['customer', 'newsletter'])   // by name; Nudge creates new tags
    ->lists([3])                         // by list ID
    ->customFields([
        'orders' => 2,                   // each field must already exist in Nudge
        'first_order' => $order->created_at, // dates are sent as Y-m-d
    ])
    ->customField('plan', 'pro')         // or one field at a time
    ->save();                            // or ->queue()

$contact->wasCreated; // true if Nudge didn't have them before
```

An invalid email throws an `InvalidArgumentException` straight away, before anything is sent or queued.

### Unsubscribing and Resubscribing

Once someone unsubscribes, saving them as subscribed doesn't change that. Only `resubscribe()` does, so use it only when they've opted back in:

```php
use Gerenuk\Nudge\Enums\ContactStatus;

Nudge::contact('ada@example.com')->unsubscribe()->save();
Nudge::contact('ada@example.com')->resubscribe()->save();

// The same as unsubscribe()
Nudge::contact('ada@example.com')->status(ContactStatus::Unsubscribed)->save();

// Doesn't resubscribe someone who unsubscribed
Nudge::contact('ada@example.com')->status(ContactStatus::Subscribed)->save();
```

### Reading Contacts

```php
use Gerenuk\Nudge\Data\Contact;
use Gerenuk\Nudge\Enums\ContactStatus;

Nudge::contacts()->find('ada@example.com');       // Contact, or null
Nudge::contacts()->archive('ada@example.com');    // stops emails and keeps their history

// One page at a time
$page = Nudge::contacts()->list(status: ContactStatus::Subscribed, tag: 9, perPage: 100);
$next = Nudge::contacts()->list(tag: 9, cursor: $page->nextCursor);

// Or every match, fetched a page at a time as you iterate
Nudge::contacts()->all(list: 3)->each(fn (Contact $contact) => ...);
```

`list()` and `all()` filter by `status`, `list`, `tag` and `segment` (IDs), `archived` and `search`.

A `Contact` is read-only:

```php
$contact->email;
$contact->name();             // first and last name, or null
$contact->isSubscribed();
$contact->isUnsubscribed();
$contact->isArchived();
$contact->hasTag('customer'); // not case-sensitive
$contact->customFields['orders'];
```

`status` is null when Nudge sends a status this version of the package doesn't know. Then `isSubscribed()` and `isUnsubscribed()` are both false, so check `isSubscribed()` before you email someone. Dates Nudge sends that can't be read are null too.

### Events

Events go on the contact's timeline in Nudge and can start automations.

```php
Nudge::event('order.completed', 'ada@example.com')
    ->properties(['order_id' => 7, 'products' => ['installer-2026']])
    ->property('total', 39.55)
    ->createContact()   // create the contact if Nudge doesn't have them
    ->queue();          // or ->send()
```

Event names can use letters, numbers, `_`, `.`, `:` and `-`, up to 100 characters. A bad name throws an `InvalidArgumentException` straight away, before anything is queued.

Properties can have up to 50 top-level keys, nested up to 4 levels deep, and 16 KB as JSON. Nudge refuses bigger ones with a `ValidationException`.

`send()` gives back a `TrackedEvent` with the event's `id`, `name`, `properties` and `occurredAt`, as it appears on the contact's timeline.

Without `createContact()`, Nudge refuses an event for an email it doesn't know. To send a new contact and their first event together, [queue them in order](#sending-now-or-later).

### Sending an Event Once

Each event carries a key, so when a retry reaches Nudge twice, it's recorded once. Pass your own key when your app might send the same event twice, such as from a form that can be submitted again:

```php
Nudge::event('order.completed', $order->email)
    ->idempotencyKey("order-{$order->id}")
    ->queue();
```

A key uses 1 to 255 visible ASCII characters.

### Reading a Timeline

A contact's timeline, including emails sent, opened and clicked:

```php
foreach (Nudge::contacts()->allActivity('ada@example.com') as $activity) {
    $activity->id;
    $activity->type;         // ActivityType, such as Opened or Custom
    $activity->eventName();  // the name, for events your app sent; otherwise null
    $activity->properties(); // their properties; otherwise empty
    $activity->occurredAt;
}
```

For one page at a time, use `Nudge::contacts()->activity($email, perPage: 100, cursor: $page->nextCursor)`.

### Lists, Tags, Custom Fields and Segments

```php
use Gerenuk\Nudge\Enums\CustomFieldType;

Nudge::lists()->all();                                             // every list, lazily
Nudge::lists()->find(3);                                           // ContactList, or null
Nudge::lists()->create('Renewals', 'Owners of last year');
Nudge::lists()->update(3, name: 'Renewals 2027', description: '');   // '' clears the description
Nudge::lists()->delete(3);                                         // keeps the contacts

$change = Nudge::lists()->addContacts(3, $emails);   // sent in batches of 1,000
$change->count;      // contacts added
$change->notFound;   // emails Nudge has no contact for. These aren't created
Nudge::lists()->removeContacts(3, $emails);

Nudge::tags()->all();

Nudge::customFields()->all();
Nudge::customFields()->create('first_order', 'First order', CustomFieldType::Date);

Nudge::segments()->all();               // with their definitions
Nudge::segments()->find(5);             // Segment with its definition and contact count, or null
Nudge::segments()->contacts(5);         // one page of its contacts
Nudge::segments()->allContacts(5);      // every contact in it, lazily

Nudge::me();   // the token's workspace and permissions
```

Each `all()` fetches a page at a time as you iterate. For one page, use `list(perPage: 100, cursor: $page->nextCursor)` on lists, tags, custom fields and segments. Each of these has `createdAt` and `updatedAt`.

### Errors

Every exception is in `Gerenuk\Nudge\Exceptions` and extends `NudgeException`. Messages never include the contact's email address, so they're safe to log. The full request is on `$exception->request`.

```php
use Gerenuk\Nudge\Exceptions\NudgeException;
use Gerenuk\Nudge\Exceptions\ValidationException;

try {
    Nudge::contact($email)->customFields(['plan' => $plan])->save();
} catch (ValidationException $exception) {
    $exception->errors();   // ['custom_fields.plan' => ['...']]
} catch (NudgeException $exception) {
    report($exception);
}
```

| Exception | When |
|---|---|
| `ValidationException` | Nudge refused the data (422). `errors()` gives the messages by field |
| `AuthenticationException` | The token is missing, wrong or expired (401) |
| `AuthorizationException` | The token lacks a permission (403) |
| `NotFoundException` | Nothing at that address (404). `find()` methods return null instead |
| `RateLimitedException` | Too many requests (429). `retryAfter()` gives the wait in seconds |
| `RequestException` | Any other error status. The ones above extend it, and `status()`, `errorCode()` and `isPermanent()` work on all of them |
| `ConnectionFailedException` | Nudge couldn't be reached or timed out |
| `NudgeDisabledException` | A direct call while sending is turned off |
| `NudgeException` itself | The URL or token isn't set |

`errorCode()` gives Nudge's code for the error, such as `contact_not_found` or `validation_failed`. Use it to tell apart errors with the same status:

```php
use Gerenuk\Nudge\Exceptions\NotFoundException;

try {
    Nudge::event('registered', $email)->send();
} catch (NotFoundException $exception) {
    if ($exception->errorCode() === 'contact_not_found') {
        // Nudge doesn't have this contact yet
    }
}
```

### Turning Sending Off

Set `NUDGE_ENABLED=false` to stop sending, for example on a staging site or while Nudge is down for maintenance.

- `queue()` does nothing, and jobs already queued are dropped when they run.
- `save()`, `send()` and reads throw a `NudgeDisabledException`, so nothing appears to have worked when it hasn't. Check `Nudge::enabled()` first where that matters.

### Testing Your App

`Nudge::fake()` records requests instead of sending them. Queued requests are recorded straight away, so tests don't need a queue worker.

```php
use Gerenuk\Nudge\Facades\Nudge;
use Gerenuk\Nudge\Testing\RecordedRequest;

$fake = Nudge::fake();

// ... run the code under test

$fake->assertContactSaved('ada@example.com', fn (array $body) => in_array('customer', $body['tags']));
$fake->assertEventTracked('order.completed', fn (array $properties, string $email) => $properties['order_id'] === 7);
$fake->assertEventTracked('downloaded', times: 2);
$fake->assertEventNotTracked('registered');
$fake->assertContactNotSaved('grace@example.com');
$fake->assertSentCount(3);
$fake->assertNothingSent();
$fake->assertSent(fn (RecordedRequest $recorded) => $recorded->queued && $recorded->isEvent());
$fake->assertNotSent(fn (RecordedRequest $recorded) => $recorded->request->is('DELETE', 'contacts/*'));
```

The fake answers like Nudge would. Saves and events succeed, lists come back empty, and finding a single record gives null. Stub anything else with `respond()`. Patterns match the path after the API prefix. `*` matches one segment, so `contacts/*` doesn't match `contacts/*/events`, and emails can be written as they are. Newer stubs win:

```php
use Gerenuk\Nudge\Http\ApiRequest;

$fake->respond('GET', 'contacts/*', ['data' => ['email' => 'ada@example.com', 'status' => 'unsubscribed']]);
$fake->respond('PUT', 'contacts/*', ['code' => 'validation_failed', 'message' => 'Invalid.', 'errors' => ['tags.0' => ['Too long.']]], 422);
$fake->respond('GET', 'contacts/ada@example.com', ['data' => ['email' => 'ada@example.com', 'status' => 'subscribed']]);
$fake->respond('GET', 'segments/*', fn (ApiRequest $request) => ['data' => [...]]);
```

To stub a page of a list, put the records in `data` and the cursor for the next page in `meta.next_cursor`. Leave it null on the last page:

```php
$fake->respond('GET', 'lists', fn (ApiRequest $request) => match ($request->query['cursor'] ?? null) {
    null => ['data' => [['id' => 1, 'name' => 'Customers']], 'meta' => ['next_cursor' => 'page-2']],
    default => ['data' => [['id' => 2, 'name' => 'Trials']], 'meta' => ['next_cursor' => null]],
});
```

The fake follows `nudge.enabled` like the real client, so you can test that nothing is sent while it's off.

### Using the Client Without the Facade

You can inject the client instead. `Nudge::fake()` replaces it in the container too, so anything resolved after you call it gets the fake.

```php
use Gerenuk\Nudge\Nudge;

class SyncCustomer
{
    public function __construct(private Nudge $nudge) {}

    public function handle(User $user): void
    {
        $this->nudge->contact($user->email)->tags(['customer'])->queue();
    }
}
```

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Kieran Proctor](https://github.com/KieranLProctor)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
