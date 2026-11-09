<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

enum Status: string
{
    /** Meets what Slim Killer needs. */
    case Pass = 'pass';

    /** Works, with a limitation or an accepted alternative. Read the hint. */
    case Warn = 'warn';

    /** Slim Killer cannot work like this. Must be fixed. */
    case Fail = 'fail';

    /** Useful information, neither good nor bad. */
    case Info = 'info';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function colour(): string
    {
        return match ($this) {
            self::Pass => 'green',
            self::Warn => 'yellow',
            self::Fail => 'red',
            self::Info => 'cyan',
        };
    }
}
