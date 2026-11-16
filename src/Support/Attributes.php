<?php

namespace Gerenuk\Nudge\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Reads typed values out of a decoded API response, so a missing key gives a sensible default
 * instead of an error.
 *
 * @internal
 */
final readonly class Attributes
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(private array $data) {}

    public function string(string $key): string
    {
        return $this->nullableString($key) ?? '';
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function int(string $key): int
    {
        return $this->nullableInt($key) ?? 0;
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function date(string $key): ?CarbonImmutable
    {
        $value = $this->nullableString($key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->data[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        return array_values(array_map(strval(...), array_filter($this->array($key), is_scalar(...))));
    }

    /**
     * @return list<int>
     */
    public function ints(string $key): array
    {
        return array_values(array_map(intval(...), array_filter($this->array($key), is_numeric(...))));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
