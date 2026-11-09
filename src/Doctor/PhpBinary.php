<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

/**
 * A PHP executable found on the server, with the version read from its path
 * (for example /opt/alt/php84/usr/bin/php is 8.4). Hosts often disable exec(), so the
 * binary is never run to ask it.
 */
final readonly class PhpBinary
{
    public function __construct(
        public string $path,
        public string $version,
    ) {}

    public static function fromPath(string $path): ?self
    {
        // php84, php8.4, php-8.4, ea-php84 ...
        if (preg_match('/php-?([5-9])\.?(\d)(?!\d)/', $path, $m) !== 1) {
            return null;
        }

        return new self($path, $m[1] . '.' . $m[2]);
    }

    public function meets(string $minimum): bool
    {
        return version_compare($this->version . '.99', $minimum, '>=');
    }
}
