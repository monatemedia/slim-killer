<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Database\Database;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Support\Clock;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitLog;
use SlimKiller\Visits\VisitReport;

/**
 * /cp/visits: what the visit counter counted. The last 7, 30 or 90 days or 12 months
 * (?days=), each number next to the same length of time before it, visitors day by
 * day, where they came from, and today so far.
 */
final class VisitsAction
{
    /** @var array<int, string> days => what the switch says */
    public const array PERIODS = [7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'];

    private const int DEFAULT_DAYS = 30;

    private const array MEASURES = [
        'visitors' => 'Visitors',
        'pages' => 'Pages opened',
        'taps' => 'Buttons tapped',
        'forms' => 'Forms sent',
    ];

    public function __construct(
        private readonly View $view,
        private readonly PDO $pdo,
        private readonly VisitLog $log,
        private readonly VisitReport $report,
        private readonly SiteTime $time,
        private readonly Clock $clock,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (! Database::hasTable($this->pdo, 'visit_events')) {
            return $this->view->render($response, 'cp/visits.twig', ['ready' => false, 'counted' => false]);
        }

        $asked = $request->getQueryParams()['days'] ?? null;
        $days = is_string($asked) && isset(self::PERIODS[(int) $asked]) ? (int) $asked : self::DEFAULT_DAYS;

        $now = $this->clock->now();
        $last = $this->time->day($now->modify('-1 day'));
        $first = $this->time->day($now->modify("-{$days} days"));
        $totals = $this->log->summary($first, $last);
        $before = $this->log->summary(
            $this->time->day($now->modify('-' . (2 * $days) . ' days')),
            $this->time->day($now->modify('-' . ($days + 1) . ' days')),
        );

        $measures = [];
        foreach (self::MEASURES as $key => $label) {
            $measures[] = [
                'label' => $label,
                'value' => $totals[$key],
                // Percent up or down on the period before; null when there is nothing to compare with.
                'change' => $before[$key] === 0 ? null : (int) round(($totals[$key] - $before[$key]) / $before[$key] * 100),
            ];
        }

        $chart = $this->log->visitorsByDay($first, $last);
        $lists = [];
        foreach ($this->report->lists($first, $last) as $list) {
            $lists[] = [...$list, 'most' => max([1, ...array_map(static fn(array $row): int => $row['events'], $list['rows'])])];
        }

        return $this->view->render($response, 'cp/visits.twig', [
            'ready' => true,
            'counted' => $this->log->hasAny(),
            'periods' => self::PERIODS,
            'days' => $days,
            'first' => $first,
            'last' => $last,
            'measures' => $measures,
            'chart' => $chart,
            // The real most, 0 when nothing is added up yet; the template draws no chart then.
            'chart_most' => max([0, ...array_map(static fn(array $day): int => $day['visitors'], $chart)]),
            'lists' => $lists,
            'today' => $this->log->soFar($this->time->day($now)),
        ]);
    }
}
