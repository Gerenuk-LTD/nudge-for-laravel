<?php

use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Jobs\SendToNudge;
use Gerenuk\Nudge\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in(__DIR__);

/**
 * A contact as Nudge's API returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactJson(array $overrides = []): array
{
    return [
        'email' => 'ada@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'status' => 'subscribed',
        'source' => 'api',
        'custom_fields' => [],
        'tags' => ['customer'],
        'lists' => [1],
        'unsubscribed_at' => null,
        'archived_at' => null,
        'created_at' => '2026-11-10T09:00:00+00:00',
        'updated_at' => '2026-11-10T09:00:00+00:00',
        ...$overrides,
    ];
}

/**
 * Fake every reading endpoint of Nudge's API, so a test can walk through the whole client.
 */
function fakeNudgeApi(): void
{
    Http::fake(function (Request $request) {
        $path = str($request->url())->after('https://nudge.test/api/v1/')->before('?')->toString();
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match ($request->method() . ' ' . $path) {
            'GET contacts' => Http::response([
                'data' => [contactJson(['email' => isset($query['cursor']) ? 'grace@example.com' : 'ada@example.com'])],
                'links' => [],
                'meta' => ['per_page' => 1, 'next_cursor' => isset($query['cursor']) ? null : 'page-2', 'prev_cursor' => null],
            ]),
            'GET contacts/ada%40example.com' => Http::response(['data' => contactJson(['status' => 'unsubscribed', 'unsubscribed_at' => '2026-11-09T10:00:00+00:00', 'source' => 'something-new'])]),
            'GET contacts/ada%40example.com/events' => Http::response([
                'data' => [
                    ['id' => 30, 'type' => 'custom', 'data' => ['name' => 'registered', 'properties' => ['via' => 'site']], 'campaign_id' => null, 'occurred_at' => '2026-11-10T09:00:00+00:00'],
                    ['id' => 29, 'type' => 'opened', 'data' => [], 'campaign_id' => 5, 'occurred_at' => '2026-11-10T08:00:00+00:00'],
                    ['id' => 28, 'type' => 'a_future_type', 'data' => [], 'campaign_id' => null, 'occurred_at' => '2026-11-10T07:00:00+00:00'],
                ],
                'meta' => ['next_cursor' => null],
            ]),
            'DELETE contacts/ada%40example.com' => Http::response(null, 204),
            'GET lists' => Http::response(['data' => [['id' => 1, 'name' => 'Customers', 'description' => null, 'contacts_count' => 12, 'created_at' => '2026-01-01T00:00:00+00:00', 'updated_at' => '2026-02-01T00:00:00+00:00']], 'meta' => ['next_cursor' => null]]),
            'POST lists' => Http::response(['data' => ['id' => 2, 'name' => $request['name'], 'description' => $request['description'] ?? null, 'contacts_count' => 0, 'created_at' => '2026-11-10T09:00:00+00:00', 'updated_at' => '2026-11-10T09:00:00+00:00']], 201),
            'PUT lists/2' => Http::response(['data' => ['id' => 2, 'name' => 'Renamed', 'description' => null, 'contacts_count' => 3, 'created_at' => '2026-11-10T09:00:00+00:00', 'updated_at' => '2026-11-11T09:00:00+00:00']]),
            'POST lists/2/contacts' => Http::response(['data' => ['added' => count($request['emails']) - 1, 'not_found' => [collect($request['emails'])->last()]]]),
            'DELETE lists/2/contacts' => Http::response(['data' => ['removed' => 1, 'not_found' => []]]),
            'DELETE lists/2' => Http::response(null, 204),
            'GET tags' => Http::response([
                'data' => [isset($query['cursor'])
                    ? ['id' => 10, 'name' => 'trial', 'contacts_count' => 3, 'created_at' => '2026-11-01T00:00:00+00:00', 'updated_at' => '2026-11-01T00:00:00+00:00']
                    : ['id' => 9, 'name' => 'customer', 'contacts_count' => 40, 'created_at' => '2026-10-01T00:00:00+00:00', 'updated_at' => '2026-10-02T00:00:00+00:00']],
                'meta' => ['next_cursor' => isset($query['cursor']) ? null : 'tags-2'],
            ]),
            'GET custom-fields' => Http::response(['data' => [['key' => 'orders', 'label' => 'Orders', 'type' => 'number', 'created_at' => '2026-10-01T00:00:00+00:00', 'updated_at' => '2026-10-01T00:00:00+00:00']], 'meta' => ['next_cursor' => null]]),
            'POST custom-fields' => Http::response(['data' => ['key' => 'first_order', 'label' => 'First order', 'type' => 'date', 'created_at' => '2026-11-10T09:00:00+00:00', 'updated_at' => '2026-11-10T09:00:00+00:00']], 201),
            'GET segments' => Http::response(['data' => [['id' => 3, 'name' => 'Lapsed', 'definition' => ['match' => 'all', 'rules' => []], 'created_at' => '2026-10-01T00:00:00+00:00', 'updated_at' => '2026-10-01T00:00:00+00:00']], 'meta' => ['next_cursor' => null]]),
            'GET segments/3' => Http::response(['data' => ['id' => 3, 'name' => 'Lapsed', 'definition' => ['match' => 'all', 'rules' => []], 'contacts_count' => 1, 'created_at' => '2026-10-01T00:00:00+00:00', 'updated_at' => '2026-10-01T00:00:00+00:00']]),
            'GET segments/3/contacts' => Http::response(['data' => [contactJson()], 'meta' => ['next_cursor' => null]]),
            'GET me' => Http::response(['data' => ['workspace' => ['id' => 1, 'name' => 'London Control'], 'token' => ['name' => 'Website', 'permissions' => ['contacts.view']]]]),
            default => Http::response(['code' => 'not_found', 'message' => 'Not found.'], 404),
        };
    });
}

/**
 * Run a SendToNudge job for an event, the way a queue worker would, and return it to assert on.
 */
function runNudgeJob(): SendToNudge
{
    $job = (new SendToNudge(ApiRequest::post('events', ['email' => 'ada@example.com', 'event' => 'registered'])))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}
