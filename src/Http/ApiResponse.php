<?php

namespace Gerenuk\Nudge\Http;

final readonly class ApiResponse
{
    /**
     * @param  array<array-key, mixed>  $json
     */
    public function __construct(
        public int $status,
        public array $json = [],
        public ?int $retryAfter = null,
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * The response's "data" key, which every Nudge response with a body uses.
     *
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        $data = $this->json['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * The machine-readable error code Nudge sends with every error, such as "contact_not_found".
     */
    public function errorCode(): ?string
    {
        $code = $this->json['code'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function message(): ?string
    {
        $message = $this->json['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }
}
