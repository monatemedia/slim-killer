<?php

declare(strict_types=1);

namespace SlimKiller\Cron;

enum JobOutcome: string
{
    case Done = 'done';
    case Failed = 'failed';
    /** It ran less than its interval ago. */
    case NotDue = 'not due';
    /** An earlier run of cron is still doing it. */
    case StillRunning = 'still running';
}
