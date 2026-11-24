<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use DateTimeImmutable;
use PDO;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Content\PageAddresses;
use SlimKiller\Database\Database;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Hammer;
use SlimKiller\Visits\Event;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitLog;

/**
 * Fills a local copy with made-up visits, so the Visits page and the visit report can
 * be seen before a real website has any: `hammer visits:sample` (the last 400 days, so
 * even 12 months has a year before it to compare with), `--days=90` for fewer,
 * `--fresh` to delete the counter's numbers first.
 *
 * Only on a local copy: APP_URL must end in .test or be localhost. It never runs on a
 * real website.
 */
final class VisitsSampleCommand implements Command
{
    /** Used when the site's last build listed no page addresses. */
    private const array PAGES = ['/' => 10, '/services/' => 5, '/about/' => 3, '/contact/' => 2];

    /** @var array<string, int> page => weight: the site's own pages when its build listed them */
    private array $pages = self::PAGES;

    /** @var array<string, array{int, array<string, mixed>}> name => [weight, what the browser sends] */
    private const array SOURCES = [
        'direct' => [34, []],
        'google search' => [28, ['r' => 'https://www.google.com/']],
        'google ads' => [8, ['g' => 1]],
        'facebook' => [10, ['r' => 'https://l.facebook.com/']],
        'linkedin' => [5, ['r' => 'https://www.linkedin.com/']],
        'bing' => [3, ['r' => 'https://www.bing.com/']],
        'a newsletter' => [5, ['s' => 'newsletter', 'm' => 'email', 'c' => 'monthly']],
        'another website' => [7, ['r' => 'https://www.example.co.za/links/']],
    ];

    private const array DEVICES = [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1' => 45,
        'Mozilla/5.0 (Linux; Android 14; SM-A546B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36' => 25,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36' => 25,
        'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148' => 5,
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly VisitLog $log,
        private readonly SiteTime $time,
        private readonly Clock $clock,
        private readonly AppConfig $config,
        private readonly PageAddresses $addresses,
    ) {}

    public function name(): string
    {
        return 'visits:sample';
    }

    public function description(): string
    {
        return 'Fill a local copy with made-up visits, to see the Visits page (--days=400, --fresh)';
    }

    public function run(Input $input, Output $output): int
    {
        $host = strtolower((string) parse_url($this->config->url, PHP_URL_HOST));
        if (! str_ends_with($host, '.test') && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            $output->line('Made-up visits only go into a local copy: APP_URL must end in .test or be localhost.', 'red');

            return 1;
        }
        if (! Database::hasTable($this->pdo, 'visit_events')) {
            $output->line('The visit counter is not set up yet. Run ' . Hammer::command('migrate') . ' first.', 'red');

            return 1;
        }

        if ($input->flag('fresh')) {
            $this->pdo->exec('DELETE FROM visit_events; DELETE FROM visit_days; DELETE FROM visit_days_done; DELETE FROM visit_reports;');
        } elseif ($this->log->hasAny()) {
            $output->line('The visit counter already has numbers. Add --fresh to delete them first.', 'red');

            return 1;
        }

        // The pages of the site this copy is pointed at, the home page most of all.
        // Pages only: the 404 page and files such as llms.txt are left out.
        $known = array_values(array_filter($this->addresses->all(), static fn(string $address): bool => str_ends_with($address, '/')));
        if ($known !== []) {
            $this->pages = array_fill_keys($known, 2);
            $this->pages['/'] = 10;
        }

        $days = max(1, min(800, (int) ($input->option('days') ?? 400)));
        $now = $this->clock->now();
        $events = 0;

        $this->pdo->beginTransaction();
        for ($back = $days; $back >= 0; $back--) {
            $date = $now->modify("-{$back} days");
            $day = $this->time->day($date);
            // Slowly growing, busier on weekdays, never quite the same; today only up to now.
            $visitors = (int) round((6 + ($days - $back) / 30) * ((int) $date->format('N') <= 5 ? 1.2 : 0.7) * random_int(70, 130) / 100);
            if ($back === 0) {
                $visitors = (int) ceil($visitors * ((int) $date->setTimezone($this->time->zone)->format('G') + 1) / 24);
            }

            for ($v = 0; $v < $visitors; $v++) {
                $events += $this->visit($day, $date->setTime(random_int(6, 21), random_int(0, 59)));
            }
        }
        $this->pdo->commit();

        $added = $this->log->addUpBefore($this->time->day($now), $now);
        $output->line(sprintf('Made up %d events over %d days, and added up %d days. Open /cp/visits to see them.', $events, $days + 1, count($added)), 'green');

        return 0;
    }

    /** One made-up visitor: a source, a device, a few pages, maybe a tap or a form. Returns how many events. */
    private function visit(string $day, DateTimeImmutable $at): int
    {
        $visitor = bin2hex(random_bytes(8));
        $userAgent = self::pick(self::DEVICES);
        $source = self::SOURCES[self::pick(array_map(static fn(array $source): int => $source[0], self::SOURCES))] ?? null;
        $origin = $source === null ? [] : $source[1];
        $count = 0;

        $pages = random_int(1, 100) <= 60 ? 1 : random_int(2, 4);
        for ($p = 0; $p < $pages; $p++) {
            $count += $this->record(['k' => 'page', 'p' => self::pick($this->pages), ...($p === 0 ? $origin : [])], $userAgent, $visitor, $day, $at);
        }
        if (random_int(1, 100) <= 14) {
            $count += $this->record(['k' => 'tap', 'p' => '/', 'l' => random_int(1, 4) === 1 ? 'Email' : 'WhatsApp: WhatsApp us'], $userAgent, $visitor, $day, $at);
        }
        if (random_int(1, 100) <= 3) {
            $count += $this->record(['k' => 'form', 'p' => self::pick($this->pages), 'l' => 'contact'], $userAgent, $visitor, $day, $at);
        }

        return $count;
    }

    /** @param array<string, mixed> $data */
    private function record(array $data, string $userAgent, string $visitor, string $day, DateTimeImmutable $at): int
    {
        $event = Event::fromBeacon($data, $userAgent, 'slim-killer.test');
        if ($event === null) {
            return 0;
        }
        $this->log->record($event, $visitor, $day, $at);

        return 1;
    }

    /**
     * A key, chosen by weight.
     *
     * @param array<string, int> $weights
     */
    private static function pick(array $weights): string
    {
        $roll = random_int(1, max(1, array_sum($weights)));
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }
}
