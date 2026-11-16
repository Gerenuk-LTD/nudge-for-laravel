<?php

namespace Gerenuk\Nudge\Data;

use Gerenuk\Nudge\Support\Attributes;

/**
 * The workspace a token belongs to, and what the token may do.
 */
final readonly class Me
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public int $workspaceId,
        public string $workspaceName,
        public string $tokenName,
        public array $permissions,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $attributes = new Attributes($data);
        $workspace = new Attributes($attributes->array('workspace'));
        $token = new Attributes($attributes->array('token'));

        return new self($workspace->int('id'), $workspace->string('name'), $token->string('name'), $token->strings('permissions'));
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
