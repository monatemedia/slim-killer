<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

use DateTimeImmutable;

/**
 * When someone's visit report comes: never, daily, weekly on a day, or monthly on a date
 * (or the last day), at an hour in the site's time zone. Stored cron-style, like
 * "0 7 * * 1" for Mondays at 07:00, but only these four shapes: shown as plain choices
 * in Preferences, never as cron.
 *
 * Each send covers the period before it: a daily report the day before, a weekly one
 * the seven days before, a monthly one the month up to the day before (sent on the 1st,
 * that is the whole of last month). Each is compared with the same stretch before it.
 */
final readonly class ReportSchedule
{
    /** A new site owner's report: weekly, Monday 07:00. */
    public const string DEFAULT_FOR_OWNERS = '0 7 * * 1';

    public const array WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /** The latest date a monthly report can choose; later than that is "the last day". */
    public const int LAST_DATE = 28;

    /**
     * @param 'off'|'daily'|'weekly'|'monthly' $frequency
     * @param int $weekday 1 (Monday) to 7 (Sunday), for weekly
     * @param int $date 1 to 28 for monthly, or 0 for the last day of the month
     */
    private function __construct(
        public string $frequency,
        public int $hour,
        public int $weekday,
        public int $date,
    ) {}

    public static function off(): self
    {
        return new self('off', 7, 1, 1);
    }

    /** Anything that isn't one of the four shapes is "off". */
    public static function fromCron(string $cron): self
    {
        $parts = preg_split('/\s+/', trim($cron)) ?: [];
        if (count($parts) !== 5 || $parts[0] !== '0' || ! ctype_digit($parts[1]) || (int) $parts[1] > 23 || $parts[3] !== '*') {
            return self::off();
        }
        [, $hour, $date, , $weekday] = $parts;

        return match (true) {
            $date === '*' && $weekday === '*' => new self('daily', (int) $hour, 1, 1),
            $date === '*' && ctype_digit($weekday) && (int) $weekday <= 7 => new self('weekly', (int) $hour, (int) $weekday === 0 ? 7 : (int) $weekday, 1),
            $weekday === '*' && $date === 'L' => new self('monthly', (int) $hour, 1, 0),
            $weekday === '*' && ctype_digit($date) && (int) $date >= 1 && (int) $date <= self::LAST_DATE => new self('monthly', (int) $hour, 1, (int) $date),
            default => self::off(),
        };
    }

    /** From the choices on Preferences; anything out of range is brought back into it. */
    public static function fromChoices(string $frequency, int $hour, int $weekday, int $date): self
    {
        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            return self::off();
        }

        return new self($frequency, max(0, min(23, $hour)), max(1, min(7, $weekday)), max(0, min(self::LAST_DATE, $date)));
    }

    public function isOff(): bool
    {
        return $this->frequency === 'off';
    }

    public function cron(): string
    {
        return match ($this->frequency) {
            'daily' => "0 {$this->hour} * * *",
            'weekly' => "0 {$this->hour} * * {$this->weekday}",
            'monthly' => "0 {$this->hour} " . ($this->date === 0 ? 'L' : $this->date) . ' * *',
            default => '',
        };
    }

    /** Like "Weekly, on Mondays at 07:00". */
    public function describe(): string
    {
        $at = sprintf('%02d:00', $this->hour);

        return match ($this->frequency) {
            'daily' => "Daily at {$at}",
            'weekly' => 'Weekly, on ' . self::WEEKDAYS[$this->weekday] . "s at {$at}",
            'monthly' => 'Monthly, on ' . ($this->date === 0 ? 'the last day' : 'the ' . self::ordinal($this->date)) . " at {$at}",
            default => 'Never',
        };
    }

    /** The last time it was due, at or before $now (in the site's time zone), or null when off. */
    public function lastDue(DateTimeImmutable $now): ?DateTimeImmutable
    {
        $today = $now->setTime($this->hour, 0);

        return match ($this->frequency) {
            'daily' => $today > $now ? $today->modify('-1 day') : $today,
            'weekly' => $this->stepTo($today, $now, -1),
            'monthly' => ($this->inMonth($today) > $now) ? $this->inMonth($today->modify('first day of last month')) : $this->inMonth($today),
            default => null,
        };
    }

    /** The next time it is due, after $now (in the site's time zone), or null when off. */
    public function nextDue(DateTimeImmutable $now): ?DateTimeImmutable
    {
        $today = $now->setTime($this->hour, 0);

        return match ($this->frequency) {
            'daily' => $today > $now ? $today : $today->modify('+1 day'),
            'weekly' => $this->stepTo($today, $now, 1),
            'monthly' => ($this->inMonth($today) > $now) ? $this->inMonth($today) : $this->inMonth($today->modify('first day of next month')),
            default => null,
        };
    }

    /**
     * What a send due at $due covers, what it is compared with, and the key it is
     * recorded under so it goes once.
     *
     * @return array{key: string, kind: string, first: string, last: string, before_first: string, before_last: string, before_count: int}
     */
    public function period(DateTimeImmutable $due): array
    {
        $day = static fn(DateTimeImmutable $date): string => $date->format('Y-m-d');
        $monthBefore = $this->monthBefore($due);
        [$first, $last, $beforeFirst, $beforeLast, $count] = match ($this->frequency) {
            'weekly' => [$due->modify('-7 days'), $due->modify('-1 day'), $due->modify('-35 days'), $due->modify('-8 days'), 4],
            'monthly' => [$monthBefore, $due->modify('-1 day'), $this->monthBefore($monthBefore), $monthBefore->modify('-1 day'), 1],
            default => [$due->modify('-1 day'), $due->modify('-1 day'), $due->modify('-8 days'), $due->modify('-2 days'), 7],
        };

        return [
            'key' => $this->frequency . ':' . $day($first),
            'kind' => $this->frequency,
            'first' => $day($first),
            'last' => $day($last),
            'before_first' => $day($beforeFirst),
            'before_last' => $day($beforeLast),
            'before_count' => $count,
        ];
    }

    /** The same date a month earlier; for "the last day", the last day of the month before. */
    private function monthBefore(DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->date === 0 ? $date->modify('last day of last month') : $date->modify('-1 month');
    }

    /** The chosen weekday nearest $today at or before $now (-1), or after it (+1). */
    private function stepTo(DateTimeImmutable $today, DateTimeImmutable $now, int $direction): DateTimeImmutable
    {
        $candidate = $today;
        for ($i = 0; $i < 8; $i++) {
            $right = (int) $candidate->format('N') === $this->weekday;
            if ($right && ($direction < 0 ? $candidate <= $now : $candidate > $now)) {
                return $candidate;
            }
            $candidate = $candidate->modify(($direction < 0 ? '-' : '+') . '1 day');
        }

        return $candidate;
    }

    /** The chosen date (or the last day) in the month of $moment, at the chosen hour. */
    private function inMonth(DateTimeImmutable $moment): DateTimeImmutable
    {
        $first = $moment->modify('first day of this month')->setTime($this->hour, 0);

        return $this->date === 0 ? $first->modify('last day of this month') : $first->modify('+' . ($this->date - 1) . ' days');
    }

    private static function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number . $suffix;
    }
}
