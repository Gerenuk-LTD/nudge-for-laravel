<?php

namespace Gerenuk\Nudge\Exceptions;

/**
 * Nudge refused the request's data. The messages are keyed by field, as Laravel returns them.
 */
final class ValidationException extends RequestException
{
    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $errors = $this->response->json['errors'] ?? [];

        if (! is_array($errors)) {
            return [];
        }

        $messages = [];

        foreach ($errors as $field => $fieldMessages) {
            $messages[(string) $field] = array_values(array_map(strval(...), array_filter((array) $fieldMessages, is_scalar(...))));
        }

        return $messages;
    }
}
