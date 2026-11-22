<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The site's own time zone (TIMEZONE in .env), so a day in the visit counter is the
 * business's day, not the server's.
 */
final readonly class SiteTime
{
    public function __construct(
        public DateTimeZone $zone,
    ) {}

    /** A time zone name like "Africa/Johannesburg"; anything unknown means UTC. */
    public static function named(string $name): self
    {
        return new self(new DateTimeZone(in_array($name, DateTimeZone::listIdentifiers(), true) ? $name : 'UTC'));
    }

    /** The day, like "2026-11-22", in the site's time zone. */
    public function day(DateTimeImmutable $time): string
    {
        return $time->setTimezone($this->zone)->format('Y-m-d');
    }
}
