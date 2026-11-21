<?php

declare(strict_types=1);

namespace SlimKiller\Support;

use RuntimeException;

/**
 * A lock held through a file, so two processes never do the same work at once: the
 * webhook and cron both fetching, or two minutes of cron both running a slow job.
 *
 * flock() is released by the system when the process ends, so a crashed run never
 * leaves a lock behind.
 */
final class Lock
{
    private bool $held = true;

    /** @param resource $handle */
    private function __construct(
        private readonly mixed $handle,
    ) {}

    /**
     * Takes the lock when nobody holds it. Null when somebody does.
     *
     * @throws RuntimeException when the lock file cannot be opened
     */
    public static function attempt(string $file): ?self
    {
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }
        $handle = @fopen($file, 'c');
        if ($handle === false) {
            throw new RuntimeException("Could not open {$file}. Is the storage folder writable?");
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return new self($handle);
    }

    public function release(): void
    {
        if (! $this->held) {
            return;
        }
        $this->held = false;
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
