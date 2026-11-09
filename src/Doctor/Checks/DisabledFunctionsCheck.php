<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\PhpCheck;
use SlimKiller\Doctor\Requirements;

final class DisabledFunctionsCheck implements PhpCheck
{
    public function check(Environment $environment): array
    {
        $disabled = $environment->disabledFunctions();
        $blocking = array_values(array_intersect(Requirements::REQUIRED_FUNCTIONS, $disabled));
        $harmless = array_values(array_intersect(Requirements::NOT_NEEDED_FUNCTIONS, $disabled));

        $results = [];

        if ($blocking === []) {
            $results[] = CheckResult::pass('Functions Slim Killer needs', 'none are disabled');
        } else {
            $results[] = CheckResult::fail(
                'Disabled functions Slim Killer needs',
                implode(', ', $blocking),
                'Remove them from "disable_functions" in your PHP options, or ask your host to.',
            );
        }

        if ($harmless !== []) {
            $results[] = CheckResult::info(
                'Disabled, and not needed',
                implode(', ', $harmless) . ' (Slim Killer never runs shell commands)',
            );
        }

        return $results;
    }
}
