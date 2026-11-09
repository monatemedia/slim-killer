<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\PhpCheck;
use SlimKiller\Doctor\Requirements;
use SlimKiller\Support\Bytes;

final class LimitsCheck implements PhpCheck
{
    public function check(Environment $environment): array
    {
        $results = [$this->memory($environment), $this->time($environment)];

        // Upload limits only matter where uploads happen: the website.
        if (! $environment->isCommandLine()) {
            $results[] = $this->upload($environment);
        }

        return $results;
    }

    private function memory(Environment $environment): CheckResult
    {
        $limit = Bytes::fromIni((string) $environment->ini('memory_limit'));
        $minimum = Bytes::fromIni(Requirements::MIN_MEMORY);

        if ($limit === Bytes::UNLIMITED || $limit >= $minimum) {
            return CheckResult::pass('Memory limit', Bytes::human($limit));
        }

        return CheckResult::warn(
            'Memory limit is low',
            Bytes::human($limit) . ', recommended ' . Bytes::human($minimum),
            'Raise memory_limit in your PHP options. Large photo uploads may fail below this.',
        );
    }

    private function time(Environment $environment): CheckResult
    {
        $seconds = (int) $environment->ini('max_execution_time');

        if ($seconds === 0 || $seconds >= Requirements::MIN_EXECUTION_TIME) {
            return CheckResult::pass('Time limit', $seconds === 0 ? 'none' : $seconds . ' seconds');
        }

        return CheckResult::warn(
            'Time limit is short',
            $seconds . ' seconds, recommended ' . Requirements::MIN_EXECUTION_TIME,
            'Raise max_execution_time in your PHP options. Fetching a site release may be cut off.',
        );
    }

    private function upload(Environment $environment): CheckResult
    {
        $upload = Bytes::fromIni((string) $environment->ini('upload_max_filesize'));
        $post = Bytes::fromIni((string) $environment->ini('post_max_size'));
        $minimum = Bytes::fromIni(Requirements::MIN_UPLOAD);
        $effective = $post === Bytes::UNLIMITED ? $upload : ($upload === Bytes::UNLIMITED ? $post : min($upload, $post));

        if ($effective === Bytes::UNLIMITED || $effective >= $minimum) {
            return CheckResult::pass('Upload size limit', Bytes::human($effective));
        }

        return CheckResult::warn(
            'Upload size limit is small',
            Bytes::human($effective) . ', recommended ' . Bytes::human($minimum),
            'Raise upload_max_filesize and post_max_size in your PHP options, so photos from a phone can be uploaded.',
        );
    }
}
