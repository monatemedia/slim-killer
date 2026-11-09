<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Support\Paths;

final class StorageCheck implements HostCheck
{
    public function __construct(
        private readonly Paths $paths,
    ) {}

    public function check(): array
    {
        $storage = $this->paths->storage();

        if (! is_dir($storage) && ! @mkdir($storage, 0755, true)) {
            return [CheckResult::fail('Storage folder', $storage . ' does not exist and cannot be created')];
        }

        $probe = $storage . '/.doctor-' . bin2hex(random_bytes(4));
        $written = @file_put_contents($probe, 'ok') === 2;
        $read = $written && @file_get_contents($probe) === 'ok';
        $deleted = $written && @unlink($probe);

        if ($written && $read && $deleted) {
            return [CheckResult::pass('Storage folder is writable', $storage)];
        }

        return [CheckResult::fail(
            'Storage folder is not writable',
            $storage,
            'Make it writable by your account, for example: chmod 755 ' . $storage,
        )];
    }
}
