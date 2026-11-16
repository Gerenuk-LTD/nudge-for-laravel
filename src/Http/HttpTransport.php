<?php

namespace Gerenuk\Nudge\Http;

use DateTimeImmutable;
use DateTimeZone;
use Gerenuk\Nudge\Contracts\Transport;
use Gerenuk\Nudge\Exceptions\ConnectionFailedException;
use Gerenuk\Nudge\Exceptions\NudgeException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

/**
 * Sends requests through Laravel's HTTP client, so `Http::fake()` and `Http::preventStrayRequests()` apply.
 */
final readonly class HttpTransport implements Transport
{
    public function __construct(
        private Factory $http,
        private Repository $config,
    ) {}

    public function send(ApiRequest $request): ApiResponse
    {
        $url = $this->config->get('nudge.url');
        $token = $this->config->get('nudge.token');

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            throw NudgeException::notConfigured();
        }

        $options = array_filter([
            'query' => $request->query === [] ? null : $request->query,
            'json' => $request->body,
        ], fn (mixed $value): bool => $value !== null);

        try {
            $response = $this->http
                ->baseUrl(rtrim($url, '/') . '/' . trim((string) $this->config->get('nudge.path', 'api/v1'), '/'))
                ->withToken($token)
                ->acceptJson()
                ->withUserAgent('nudge-for-laravel')
                ->timeout((int) $this->config->get('nudge.timeout', 10))
                ->connectTimeout((int) $this->config->get('nudge.connect_timeout', 5))
                ->withHeaders($request->idempotencyKey === null ? [] : ['Idempotency-Key' => $request->idempotencyKey])
                ->send($request->method, $request->path, $options);
        } catch (ConnectionException $exception) {
            throw new ConnectionFailedException($request, $exception);
        }

        $json = $response->json();

        return new ApiResponse(
            $response->status(),
            is_array($json) ? $json : [],
            $this->retryAfter($response->header('Retry-After')),
        );
    }

    /**
     * Retry-After is either seconds or an HTTP date. Gives the wait in seconds, never negative.
     */
    private function retryAfter(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $date = DateTimeImmutable::createFromFormat(DATE_RFC7231, $value, new DateTimeZone('UTC'));

        return $date === false ? null : max(0, $date->getTimestamp() - now()->getTimestamp());
    }
}
