<?php

declare(strict_types=1);

namespace SlimKiller\Updates;

/** A newer Slim Killer than the one running: "v0.9.0 is out, this is v0.8.1". */
final readonly class UpdateNotice
{
    private function __construct(
        public string $installed,
        public string $latest,
    ) {}

    public static function between(?string $installed, ?string $latest): ?self
    {
        if ($installed === null || $latest === null || ! version_compare($latest, $installed, '>')) {
            return null;
        }

        return new self($installed, $latest);
    }
}
