<?php

namespace Gerenuk\Nudge\Exceptions;

use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Http\ApiResponse;

/**
 * Nudge answered with an error status. Subclasses cover the statuses worth catching on their own.
 */
class RequestException extends NudgeException
{
    final public function __construct(
        public readonly ApiRequest $request,
        public readonly ApiResponse $response,
    ) {
        $message = $response->message() ?? "Nudge returned HTTP {$response->status}.";

        parent::__construct("{$message} ({$request->redacted()})", $response->status);
    }

    public static function from(ApiRequest $request, ApiResponse $response): self
    {
        $class = match ($response->status) {
            401 => AuthenticationException::class,
            403 => AuthorizationException::class,
            404 => NotFoundException::class,
            422 => ValidationException::class,
            429 => RateLimitedException::class,
            default => self::class,
        };

        return new $class($request, $response);
    }

    public function status(): int
    {
        return $this->response->status;
    }

    /**
     * Nudge's machine-readable code for the error, such as "contact_not_found". Null when the
     * response had none, such as from a proxy in front of Nudge.
     */
    public function errorCode(): ?string
    {
        return $this->response->errorCode();
    }

    /**
     * Whether sending the same request again can't succeed, such as failed validation or a bad token.
     */
    public function isPermanent(): bool
    {
        return $this->response->status >= 400
            && $this->response->status < 500
            && ! in_array($this->response->status, [408, 409, 425, 429], true);
    }
}
