<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Support\Hammer;
use SlimKiller\Updates\UpdateCheckFailed;
use SlimKiller\Updates\Updates;

/** Is this the newest Slim Killer? Asks GitHub each time, and notes the answer for the dashboard. */
final readonly class UpdatesCheck implements HostCheck
{
    public function __construct(
        private Updates $updates,
    ) {}

    public function check(): array
    {
        $installed = $this->updates->installed();
        if ($installed === null) {
            return [CheckResult::info('Slim Killer version unknown', 'CHANGELOG.md names no release')];
        }

        try {
            $latest = $this->updates->check();
        } catch (UpdateCheckFailed $problem) {
            return [CheckResult::info("Slim Killer v{$installed}", 'could not check for a newer one: ' . $problem->getMessage())];
        }

        $notice = $this->updates->newer();
        if ($notice === null) {
            return [CheckResult::pass("Slim Killer v{$installed}", 'the newest')];
        }

        return [CheckResult::warn(
            "Slim Killer v{$notice->latest} is out",
            "this is v{$notice->installed}. What's new: " . Updates::CHANGELOG_URL,
            "To update: git fetch --tags && git checkout v{$latest} && composer install --no-dev && "
                . Hammer::command('migrate') . ' && ' . Hammer::command('web:install')
                . ", and anything else CHANGELOG.md asks under [{$latest}].",
        )];
    }
}
