<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Writes .env. It only ever adds keys that are missing: a value someone set by hand
 * is never overwritten.
 */
final class EnvFile
{
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * @param array<string, string> $values
     * @return list<string> The keys that were added.
     */
    public function addMissing(array $values): array
    {
        $existing = is_file($this->path) ? (string) file_get_contents($this->path) : '';
        $added = [];
        $lines = '';

        foreach ($values as $key => $value) {
            if (preg_match('/^' . preg_quote($key, '/') . '=/m', $existing) === 1) {
                continue;
            }
            $lines .= $key . '="' . addcslashes($value, '"\\$') . '"' . "\n";
            $added[] = $key;
        }

        if ($lines !== '') {
            $prefix = $existing === '' || str_ends_with($existing, "\n") ? '' : "\n";
            file_put_contents($this->path, $existing . $prefix . $lines, LOCK_EX);
            @chmod($this->path, 0600);
        }

        return $added;
    }
}
