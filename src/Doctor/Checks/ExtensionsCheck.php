<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\PhpCheck;
use SlimKiller\Doctor\Requirements;

final class ExtensionsCheck implements PhpCheck
{
    public function check(Environment $environment): array
    {
        $results = [];
        $present = [];

        foreach (Requirements::REQUIRED_EXTENSIONS as $extension => $why) {
            if ($environment->hasExtension($extension)) {
                $present[] = $extension;
            } else {
                $results[] = CheckResult::fail(
                    "Extension {$extension} is missing",
                    "needed because Slim Killer {$why}",
                    "Enable {$extension} for this PHP version in your hosting control panel (PHP extensions or PHP options).",
                );
            }
        }

        if ($present !== []) {
            array_unshift($results, CheckResult::pass('Required extensions', implode(', ', $present)));
        }

        foreach (Requirements::RECOMMENDED_EXTENSIONS as $extension => $fallback) {
            if (! $environment->hasExtension($extension)) {
                $results[] = CheckResult::warn("Extension {$extension} is not installed", $fallback);
            }
        }

        return $results;
    }
}
