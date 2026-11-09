<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

use Closure;

/**
 * Looks for PHP executables in the places shared hosts usually install them.
 */
final class BinaryFinder
{
    private readonly Closure $glob;

    /**
     * @param list<string> $patterns glob patterns, see config/container.php
     * @param (Closure(string): list<string>)|null $glob replaced in tests
     */
    public function __construct(
        private readonly array $patterns,
        ?Closure $glob = null,
    ) {
        $this->glob = $glob ?? static function (string $pattern): array {
            $matches = glob($pattern);

            return array_values(array_filter(
                is_array($matches) ? $matches : [],
                static fn(string $path): bool => is_file($path) && is_executable($path),
            ));
        };
    }

    /** @return list<PhpBinary> sorted by version, oldest first */
    public function find(): array
    {
        $found = [];
        foreach ($this->patterns as $pattern) {
            foreach (($this->glob)($pattern) as $path) {
                $binary = PhpBinary::fromPath($path);
                if ($binary !== null) {
                    $found[$path] = $binary;
                }
            }
        }

        $binaries = array_values($found);
        usort($binaries, static fn(PhpBinary $a, PhpBinary $b): int => version_compare($a->version, $b->version));

        return $binaries;
    }

    /** The oldest binary that still meets the minimum: the safest choice for cron. */
    public function best(string $minimum): ?PhpBinary
    {
        foreach ($this->find() as $binary) {
            if ($binary->meets($minimum)) {
                return $binary;
            }
        }

        return null;
    }
}
