<?php

namespace Gerenuk\Nudge\Testing;

use Closure;
use Gerenuk\Nudge\Contracts\Transport;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Http\ApiResponse;

/**
 * Answers requests the way Nudge would, without sending anything. Writes succeed and echo back
 * what was sent, lists come back empty, and finding one record gives a 404 unless it's stubbed.
 *
 * @internal
 */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, pattern: string, response: Closure(ApiRequest): ApiResponse}> */
    private array $stubs = [];

    /** @var array<string, true> */
    private array $savedEmails = [];

    private int $nextId = 1;

    /** @var Closure(ApiRequest): void */
    private Closure $record;

    /**
     * @param  Closure(ApiRequest): void  $record
     */
    public function __construct(Closure $record)
    {
        $this->record = $record;
    }

    /**
     * @param  array<array-key, mixed>|Closure(ApiRequest): (array<array-key, mixed>|ApiResponse)  $response
     */
    public function stub(string $method, string $pattern, array | Closure $response, int $status): void
    {
        array_unshift($this->stubs, [
            'method' => strtoupper($method),
            'pattern' => ltrim($pattern, '/'),
            'response' => function (ApiRequest $request) use ($response, $status): ApiResponse {
                $result = $response instanceof Closure ? $response($request) : $response;

                return $result instanceof ApiResponse ? $result : new ApiResponse($status, $result);
            },
        ]);
    }

    public function send(ApiRequest $request): ApiResponse
    {
        ($this->record)($request);

        foreach ($this->stubs as $stub) {
            if ($request->is($stub['method'], $stub['pattern'])) {
                return ($stub['response'])($request);
            }
        }

        return $this->answer($request);
    }

    private function answer(ApiRequest $request): ApiResponse
    {
        $body = $request->body ?? [];
        $now = now()->toIso8601String();

        return match (true) {
            $request->is('GET', 'me') => new ApiResponse(200, ['data' => [
                'workspace' => ['id' => 1, 'name' => 'Fake workspace'],
                'token' => ['name' => 'Fake token', 'permissions' => ['contacts.view', 'contacts.manage']],
            ]]),
            $request->is('PUT', 'contacts/*') => $this->savedContact($request, $now),
            $request->is('POST', 'events') => new ApiResponse(202, ['data' => [
                'id' => $this->nextId++,
                'type' => 'custom',
                'data' => ['name' => $body['event'] ?? null, 'properties' => $body['properties'] ?? []],
                'campaign_id' => null,
                'occurred_at' => $now,
            ]]),
            $request->is('GET', 'contacts'),
            $request->is('GET', 'contacts/*/events'),
            $request->is('GET', 'segments/*/contacts'),
            $request->is('GET', 'lists'),
            $request->is('GET', 'tags'),
            $request->is('GET', 'custom-fields'),
            $request->is('GET', 'segments') => new ApiResponse(200, [
                'data' => [],
                'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
                'meta' => ['per_page' => $request->query['per_page'] ?? 100, 'next_cursor' => null, 'prev_cursor' => null],
            ]),
            $request->is('POST', 'lists') => new ApiResponse(201, ['data' => [
                'id' => $this->nextId++,
                'name' => $body['name'] ?? null,
                'description' => $body['description'] ?? null,
                'contacts_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]]),
            $request->is('PUT', 'lists/*') => new ApiResponse(200, ['data' => [
                'id' => (int) substr($request->path, 6),
                'name' => $body['name'] ?? 'List',
                'description' => $body['description'] ?? null,
                'contacts_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]]),
            $request->is('POST', 'lists/*/contacts') => new ApiResponse(200, ['data' => ['added' => count((array) ($body['emails'] ?? [])), 'not_found' => []]]),
            $request->is('DELETE', 'lists/*/contacts') => new ApiResponse(200, ['data' => ['removed' => count((array) ($body['emails'] ?? [])), 'not_found' => []]]),
            $request->is('POST', 'custom-fields') => new ApiResponse(201, ['data' => [...$body, 'created_at' => $now, 'updated_at' => $now]]),
            $request->is('DELETE', 'contacts/*'), $request->is('DELETE', 'lists/*') => new ApiResponse(204),
            default => new ApiResponse(404, ['code' => 'not_found', 'message' => 'Not found. Stub this request with Nudge::fake()->respond().']),
        };
    }

    private function savedContact(ApiRequest $request, string $now): ApiResponse
    {
        $body = $request->body ?? [];
        $email = mb_strtolower(rawurldecode(substr($request->path, 9)));
        $created = ! isset($this->savedEmails[$email]);
        $this->savedEmails[$email] = true;

        return new ApiResponse($created ? 201 : 200, ['data' => [
            'email' => $email,
            'first_name' => $body['first_name'] ?? null,
            'last_name' => $body['last_name'] ?? null,
            'status' => $body['status'] ?? 'subscribed',
            'source' => 'api',
            'custom_fields' => $body['custom_fields'] ?? [],
            'tags' => $body['tags'] ?? [],
            'lists' => $body['lists'] ?? [],
            'unsubscribed_at' => null,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }
}
