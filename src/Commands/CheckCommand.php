<?php

namespace Gerenuk\Nudge\Commands;

use Gerenuk\Nudge\Exceptions\NudgeException;
use Gerenuk\Nudge\Nudge;
use Illuminate\Console\Command;

/**
 * Checks the URL and token work, and that the token has the permissions this package uses.
 * Worth running after a deploy.
 */
final class CheckCommand extends Command
{
    protected $signature = 'nudge:check';

    protected $description = 'Check the connection to Nudge and the API token\'s permissions';

    public function handle(Nudge $nudge): int
    {
        if (! $nudge->enabled()) {
            $this->components->warn('Nudge is disabled (NUDGE_ENABLED=false), so nothing is sent.');

            return self::SUCCESS;
        }

        try {
            $me = $nudge->me();
        } catch (NudgeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Connected to the \"{$me->workspaceName}\" workspace with the \"{$me->tokenName}\" token.");

        $missing = array_values(array_filter(
            ['contacts.view', 'contacts.manage'],
            fn (string $permission): bool => ! $me->can($permission),
        ));

        foreach ($missing as $permission) {
            $this->components->warn("The token doesn't have the {$permission} permission.");
        }

        return $missing === [] ? self::SUCCESS : self::FAILURE;
    }
}
