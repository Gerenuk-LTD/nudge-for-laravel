<?php

namespace Gerenuk\Nudge\Http;

/**
 * One call to the Nudge API. It holds plain data only, so it can travel on a queue.
 */
final readonly class ApiRequest
{
    /**
     * @param  array<string, scalar>  $query
     * @param  array<string, mixed>|null  $body
     * @param  string|null  $idempotencyKey  Sent as the `Idempotency-Key` header. Nudge treats a repeat with the same key as the same request.
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public ?array $body = null,
        public ?string $idempotencyKey = null,
    ) {}

    public function withIdempotencyKey(string $key): self
    {
        return new self($this->method, $this->path, $this->query, $this->body, $key);
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public static function get(string $path, array $query = []): self
    {
        return new self('GET', $path, array_filter($query, fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function post(string $path, array $body = []): self
    {
        return new self('POST', $path, body: $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function put(string $path, array $body = []): self
    {
        return new self('PUT', $path, body: $body);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function delete(string $path, ?array $body = null): self
    {
        return new self('DELETE', $path, body: $body);
    }

    /**
     * Encode a path segment such as an email address, which may hold "+" or "/".
     */
    public static function segment(string | int $value): string
    {
        return rawurlencode((string) $value);
    }

    /**
     * Whether this request has the method and matches the path pattern. `*` matches within one path
     * segment, and each segment is tried as sent and URL-decoded, so "contacts/ada@example.com"
     * matches the encoded path.
     */
    public function is(string $method, string $pattern): bool
    {
        if (strtoupper($method) !== $this->method) {
            return false;
        }

        $patternSegments = explode('/', trim($pattern, '/'));
        $pathSegments = explode('/', $this->path);

        if (count($patternSegments) !== count($pathSegments)) {
            return false;
        }

        foreach ($patternSegments as $index => $patternSegment) {
            $segment = $pathSegments[$index];

            if (! fnmatch($patternSegment, $segment) && ! fnmatch($patternSegment, rawurldecode($segment))) {
                return false;
            }
        }

        return true;
    }

    public function describe(): string
    {
        return $this->method . ' ' . $this->path;
    }

    /**
     * Like `describe()`, with any email address in the path replaced, so it's safe for logs and error messages.
     */
    public function redacted(): string
    {
        $segments = array_map(
            fn (string $segment): string => str_contains(rawurldecode($segment), '@') ? '{email}' : $segment,
            explode('/', $this->path),
        );

        return $this->method . ' ' . implode('/', $segments);
    }
}
