<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\PhpCheck;

final class ImageSupportCheck implements PhpCheck
{
    public function check(Environment $environment): array
    {
        if (! $environment->hasExtension('gd')) {
            return []; // Already reported as a missing required extension.
        }

        $results = [];

        if ($environment->canWriteWebp()) {
            $results[] = CheckResult::pass('Photos can be saved as WebP');
        } else {
            $results[] = CheckResult::warn(
                'GD cannot write WebP',
                'uploaded photos will be saved as JPEG instead (larger files)',
            );
        }

        if ($environment->supportsAvif()) {
            $results[] = CheckResult::info('AVIF images are supported too');
        }

        return $results;
    }
}
