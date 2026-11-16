# Changelog

All notable changes to `nudge-for-laravel` will be documented in this file.

## Unreleased

First release. Requires PHP 8.4+, Laravel 13 and Nudge 1.x.

### Sending

- `Nudge::contact()` creates or updates a contact, sending only what's set: name, status, custom fields, tags and lists. Resubscribing is explicit, through `resubscribe()`.
- `Nudge::event()` sends a named event with properties, and can create the contact if Nudge doesn't have them. `send()` returns the recorded event as a `TrackedEvent`, with its `id`, `name`, `properties` and `occurredAt`.
- Both can be sent now (`save()`, `send()`) or queued (`queue()`). Queued requests retry connection failures, server errors and rate limits, and fail straight away when Nudge refuses them.
- `Nudge::queue()` chains several requests, so a contact is saved before its first event.
- Events are sent with an idempotency key, so a retried event is recorded once. `idempotencyKey()` sets your own.

### Reading

- Contacts: list with filters, iterate every page lazily, find by email, archive, and read each contact's activity.
- Lists: list, find, create, update, delete, and add or remove contacts in batches of 1,000.
- Tags, custom fields (list and create), segments (list, find and their contacts), and the token's workspace and permissions.
- Every `all()` fetches a page at a time as you iterate, and `list()` gives you one page with its cursor.
- Lists, tags, custom fields and segments have `createdAt` and `updatedAt`. Timeline entries have an `id`.
- Responses come back as readonly data objects with enums. Unknown values from a newer Nudge don't break them.
- A contact status the package doesn't know gives a null `status`, so `isSubscribed()` is never true by accident. `isUnsubscribed()` checks the other way. Dates Nudge sends that can't be read come back as null.

### Errors

- Error statuses become typed exceptions, all extending `NudgeException`, including the validation errors and `Retry-After`.
- `errorCode()` gives Nudge's code for the error, such as `contact_not_found`.
- Exception messages replace email addresses in the path with `{email}`, so they're safe for logs and error trackers.
- An invalid email given to `Nudge::contact()` or `Nudge::event()` throws an `InvalidArgumentException` that leaves the email out of its message.
- `Retry-After` is read as seconds or as an HTTP date.

### Operations

- `nudge:check` tests the URL, the token and its permissions.
- `NUDGE_ENABLED=false` turns sending off. Queued requests are dropped, and direct calls throw rather than appear to work.

### Testing

- `Nudge::fake()` records requests instead of sending them, answers like Nudge would, and can be stubbed with `respond()`. It includes assertions for saved contacts, tracked events and raw requests.
- Stub patterns match one path segment per `*`, and accept email addresses as written.
