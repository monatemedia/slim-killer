<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck;

/** A short list for a report line: the first few, then how many more. */
final class Listing
{
    public const int SHOWN = 6;

    /** @param list<string> $items */
    public static function of(array $items): string
    {
        $shown = array_slice($items, 0, self::SHOWN);
        $more = count($items) - count($shown);

        return implode(', ', $shown) . ($more > 0 ? " and {$more} more" : '');
    }
}
