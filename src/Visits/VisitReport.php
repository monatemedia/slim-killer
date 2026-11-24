<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

use DateTimeImmutable;

/**
 * Puts the visit counter's numbers together for people to read: the daily email (one
 * day next to the 7-day average before it, templates/mail/visit-report.*.twig) and
 * the lists `hammer visits` prints.
 */
final readonly class VisitReport
{
    private const array MEASURES = [
        'visitors' => 'Visitors',
        'pages' => 'Pages opened',
        'taps' => 'Buttons tapped',
        'forms' => 'Forms sent',
    ];

    public function __construct(
        private VisitLog $log,
    ) {}

    /** The daily email's subject, like "Visits to Monate Media, 21 November: 12 visitors". */
    public function subject(string $day, string $siteName): string
    {
        $visitors = $this->log->summary($day, $day)['visitors'];

        return sprintf('Visits to %s, %s: %s', $siteName, (new DateTimeImmutable($day))->format('j F'), $visitors === 1 ? '1 visitor' : "{$visitors} visitors");
    }

    /**
     * What the daily email shows for $day.
     *
     * @return array{date: string, measures: list<array{label: string, day: int, average: float}>, lists: list<array{heading: string, rows: list<array{name: string, events: int}>}>}
     */
    public function daily(string $day): array
    {
        $date = new DateTimeImmutable($day);
        $totals = $this->log->summary($day, $day);
        $before = $this->log->summary($date->modify('-7 days')->format('Y-m-d'), $date->modify('-1 day')->format('Y-m-d'));

        $measures = [];
        foreach (self::MEASURES as $key => $label) {
            $measures[] = ['label' => $label, 'day' => $totals[$key], 'average' => round($before[$key] / 7, 1)];
        }

        return ['date' => $date->format('l j F Y'), 'measures' => $measures, 'lists' => $this->lists($day, $day)];
    }

    /**
     * Where visitors came from, pages, pages not found, buttons, forms and devices, for the days from
     * $first to $last. Lists with nothing in them are left out.
     *
     * @return list<array{heading: string, rows: list<array{name: string, events: int}>}>
     */
    public function lists(string $first, string $last): array
    {
        $lists = [
            'Where visitors came from' => $this->log->top(null, TopBy::Source, $first, $last),
            'Pages' => $this->log->top(EventKind::Page, TopBy::Page, $first, $last),
            'Pages not found' => $this->log->top(EventKind::Missing, TopBy::PageAndLink, $first, $last),
            'Buttons tapped' => $this->log->top(EventKind::Tap, TopBy::Label, $first, $last),
            'Forms sent' => $this->log->top(EventKind::Form, TopBy::Label, $first, $last),
            'Devices' => $this->log->top(null, TopBy::Device, $first, $last),
        ];

        $shown = [];
        foreach ($lists as $heading => $rows) {
            if ($rows !== []) {
                $shown[] = ['heading' => $heading, 'rows' => $rows];
            }
        }

        return $shown;
    }
}
