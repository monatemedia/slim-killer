<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\PhpCheck;
use SlimKiller\Doctor\Requirements;

final class PhpVersionCheck implements PhpCheck
{
    public function check(Environment $environment): array
    {
        $version = $environment->phpVersion();
        $title = 'PHP version';

        if (version_compare($version, Requirements::MIN_PHP, '>=')) {
            return [CheckResult::pass($title, $version . ' (' . $environment->sapi() . ')')];
        }

        $hint = $environment->isCommandLine()
            ? 'Run hammer with the full path of a PHP ' . Requirements::MIN_PHP_LABEL . ' binary (see "PHP for cron and hammer").'
            : "Set this website's PHP version to " . Requirements::MIN_PHP_LABEL . ' or newer in your hosting control panel.';

        return [CheckResult::fail($title, $version . ', needs ' . Requirements::MIN_PHP_LABEL . ' or newer', $hint)];
    }
}
