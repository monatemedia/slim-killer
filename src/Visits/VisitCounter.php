<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

use DateTimeImmutable;

/**
 * Takes one beacon from a visitor's browser and records it. Called by
 * public/cp/visit.php, which answers with the status this returns.
 *
 * Nothing is recorded when the browser asks not to be tracked (Do Not Track or Global
 * Privacy Control), for robots, or for anything sent from another website.
 */
final readonly class VisitCounter
{
    /** A beacon is a few hundred bytes; anything bigger is not ours. */
    public const int MAX_BYTES = 2048;

    public function __construct(
        private VisitLog $log,
        private VisitorCodes $codes,
        private SiteTime $time,
    ) {}

    /**
     * @param array<mixed> $server $_SERVER, or the same keys in a test
     * @return int the HTTP status to answer with: 204 whether counted or not, 4xx for a bad request
     */
    public function count(array $server, string $body, DateTimeImmutable $now): int
    {
        if (self::header($server, 'REQUEST_METHOD') !== 'POST') {
            return 405;
        }

        $host = strtolower(self::header($server, 'HTTP_HOST'));
        $origin = strtolower((string) parse_url(self::header($server, 'HTTP_ORIGIN'), PHP_URL_HOST));
        $hostName = strtolower((string) parse_url('//' . $host, PHP_URL_HOST));
        if ($host === '' || $origin === '' || $origin !== $hostName) {
            return 403;
        }

        if (self::header($server, 'HTTP_DNT') === '1' || self::header($server, 'HTTP_SEC_GPC') === '1') {
            return 204;
        }

        if (strlen($body) > self::MAX_BYTES) {
            return 413;
        }
        $data = json_decode($body, true);
        if (! is_array($data)) {
            return 400;
        }

        $userAgent = self::header($server, 'HTTP_USER_AGENT');
        $event = Event::fromBeacon($data, $userAgent, $hostName);
        if ($event === null) {
            return 204;
        }

        $day = $this->time->day($now);
        $visitor = $this->codes->codeFor($day, self::header($server, 'REMOTE_ADDR'), $userAgent, $hostName);
        $this->log->record($event, $visitor, $day, $now);

        return 204;
    }

    /** @param array<mixed> $server */
    private static function header(array $server, string $key): string
    {
        $value = $server[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
