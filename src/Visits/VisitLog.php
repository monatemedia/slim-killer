<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

use DateTimeImmutable;
use PDO;

/**
 * The visit counter's records: every single event, and the daily totals. The totals are
 * kept for good; single events too, unless the site sets VISIT_EVENTS_MONTHS.
 */
final readonly class VisitLog
{
    public function __construct(
        private PDO $pdo,
    ) {}

    /**
     * Records one event. An event that doesn't say where the visit came from (a second
     * page, a tap) takes the source of the visitor's first event that day, or "direct".
     */
    public function record(Event $event, string $visitor, string $day, DateTimeImmutable $now): void
    {
        if ($event->source === '') {
            $statement = $this->pdo->prepare('SELECT source, medium, campaign FROM visit_events WHERE day = ? AND visitor = ? ORDER BY id LIMIT 1');
            $statement->execute([$day, $visitor]);
            $first = $statement->fetch(PDO::FETCH_ASSOC);
            $event = is_array($first)
                ? $event->from((string) $first['source'], (string) $first['medium'], (string) $first['campaign'])
                : $event->from('direct', '', '');
        }

        $this->pdo->prepare(
            'INSERT INTO visit_events (at, day, visitor, kind, path, label, source, medium, campaign, device) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([
            $now->getTimestamp(), $day, $visitor, $event->kind->value, $event->path, $event->label,
            $event->source, $event->medium, $event->campaign, $event->device->value,
        ]);
    }

    /**
     * Adds up every finished day (any day before $today) that has not been added up yet.
     *
     * @return list<string> the days added up now
     */
    public function addUpBefore(string $today, DateTimeImmutable $now): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT day FROM visit_events WHERE day < ? AND day NOT IN (SELECT day FROM visit_days_done) ORDER BY day',
        );
        $statement->execute([$today]);
        $days = array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));

        foreach ($days as $day) {
            $this->pdo->beginTransaction();
            $this->pdo->prepare(
                'INSERT OR REPLACE INTO visit_days (day, kind, path, label, source, medium, campaign, device, visitors, events)
                 SELECT day, kind, path, label, source, medium, campaign, device, COUNT(DISTINCT visitor), COUNT(*)
                 FROM visit_events WHERE day = ?
                 GROUP BY day, kind, path, label, source, medium, campaign, device',
            )->execute([$day]);
            // One more row per source and device: how many visitors, whatever they did.
            $this->pdo->prepare(
                "INSERT OR REPLACE INTO visit_days (day, kind, path, label, source, medium, campaign, device, visitors, events)
                 SELECT day, 'visit', '', '', source, medium, campaign, device, COUNT(DISTINCT visitor), COUNT(DISTINCT visitor)
                 FROM visit_events WHERE day = ?
                 GROUP BY day, source, medium, campaign, device",
            )->execute([$day]);
            $this->pdo->prepare('INSERT INTO visit_days_done (day, at) VALUES (?, ?)')->execute([$day, $now->getTimestamp()]);
            $this->pdo->commit();
        }

        return $days;
    }

    /** Deletes single events from days before $day that have been added up. Returns how many. */
    public function deleteEventsBefore(string $day): int
    {
        $statement = $this->pdo->prepare('DELETE FROM visit_events WHERE day < ? AND day IN (SELECT day FROM visit_days_done)');
        $statement->execute([$day]);

        return $statement->rowCount();
    }

    /**
     * Totals for the days from $first to $last, from the daily totals.
     *
     * Visitors are added up day by day: someone who came on two days counts twice,
     * because nobody can be recognised from one day to the next.
     *
     * @return array{visitors: int, pages: int, taps: int, forms: int}
     */
    public function summary(string $first, string $last): array
    {
        $statement = $this->pdo->prepare(
            "SELECT kind, SUM(events) AS events FROM visit_days WHERE day BETWEEN ? AND ? AND kind <> 'visit' GROUP BY kind",
        );
        $statement->execute([$first, $last]);
        $events = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $events[(string) $row['kind']] = (int) $row['events'];
        }

        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(visitors), 0) FROM visit_days WHERE day BETWEEN ? AND ? AND kind = 'visit'",
        );
        $statement->execute([$first, $last]);

        return [
            'visitors' => (int) $statement->fetchColumn(),
            'pages' => $events[EventKind::Page->value] ?? 0,
            'taps' => $events[EventKind::Tap->value] ?? 0,
            'forms' => $events[EventKind::Form->value] ?? 0,
        ];
    }

    /**
     * The most counted pages, sources, buttons or forms for the days from $first to $last.
     * With no kind, it counts visitors instead of events (for sources and devices).
     *
     * @return list<array{name: string, events: int}>
     */
    public function top(?EventKind $kind, TopBy $by, string $first, string $last, int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            "SELECT {$by->column()} AS name, SUM(events) AS events FROM visit_days
             WHERE day BETWEEN ? AND ? AND kind = ? GROUP BY name ORDER BY events DESC, name LIMIT " . max(1, $limit),
        );
        $statement->execute([$first, $last, $kind === null ? 'visit' : $kind->value]);

        return array_map(
            static fn(array $row): array => ['name' => (string) $row['name'], 'events' => (int) $row['events']],
            array_values(array_filter($statement->fetchAll(PDO::FETCH_ASSOC), 'is_array')),
        );
    }
}
