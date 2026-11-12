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

        if ($environment->canWriteJpeg()) {
            $results[] = CheckResult::pass('Uploaded photos can be saved as JPEG');
        } else {
            $results[] = CheckResult::fail(
                'GD cannot write JPEG',
                'uploaded photos are re-encoded as JPEG; ask the host for GD with JPEG support',
            );
        }

        if ($environment->readsWebp()) {
            $results[] = CheckResult::info('WebP photos can be uploaded too');
        }

        return $results;
    }
}
