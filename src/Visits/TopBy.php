<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

/** What VisitLog::top() groups the totals by. */
enum TopBy
{
    case Page;
    case Label;
    case Source;
    case Device;

    /** A page not found, with where its link was: "/old-page/ (from /services/)". */
    case PageAndLink;

    /** The column in visit_days. Fixed names only: never anything a visitor sent. */
    public function column(): string
    {
        return match ($this) {
            self::Page => 'path',
            self::Label => 'label',
            self::Source => "source || CASE WHEN medium = '' THEN '' ELSE ' / ' || medium END",
            self::Device => 'device',
            self::PageAndLink => "path || CASE WHEN label = '' THEN '' ELSE ' (from ' || label || ')' END",
        };
    }
}
