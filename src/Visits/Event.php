<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

/**
 * One thing the visit counter counts, read from what a visitor's browser sent
 * (public/cp/assets/visits.js). Holds nothing that names a person: the page's path
 * without its query, a button's label, where the visit came from, and the kind of device.
 */
final readonly class Event
{
    /** Search engines and social sites, by a part of their address. */
    private const array KNOWN = [
        'google.' => ['google', 'search'],
        'bing.com' => ['bing', 'search'],
        'duckduckgo.com' => ['duckduckgo', 'search'],
        'yahoo.' => ['yahoo', 'search'],
        'facebook.com' => ['facebook', 'social'],
        'instagram.com' => ['instagram', 'social'],
        'linkedin.com' => ['linkedin', 'social'],
        'lnkd.in' => ['linkedin', 'social'],
        't.co' => ['x', 'social'],
        'x.com' => ['x', 'social'],
        'twitter.com' => ['x', 'social'],
        'youtube.com' => ['youtube', 'social'],
        'tiktok.com' => ['tiktok', 'social'],
        'whatsapp.com' => ['whatsapp', 'social'],
    ];

    private function __construct(
        public EventKind $kind,
        /** The page, like "/services/", without the query. */
        public string $path,
        /**
         * For a tap, the button ("WhatsApp: WhatsApp Edward"); for a form, its name; for a
         * page not found, where its link was: "/services/" on this site, or another site's
         * address.
         */
        public string $label,
        /** Where the visit came from: "google", "facebook", a website's address, or "" when it says nothing. */
        public string $source,
        /** "cpc" for paid ads, "search", "social", "referral", or a campaign's own utm_medium. */
        public string $medium,
        public string $campaign,
        public Device $device,
    ) {}

    /**
     * What the browser sent, or null for anything that is not a visitor's browser.
     *
     * @param array<mixed> $data The beacon: k (kind), p (path), r (referrer), s, m, c (utm_source,
     *                           utm_medium, utm_campaign), g (1 when the address had a gclid), l (label).
     * @param string $siteHost The website's own host name, to tell its own links from other sites.
     */
    public static function fromBeacon(array $data, string $userAgent, string $siteHost): ?self
    {
        if (self::isRobot($userAgent)) {
            return null;
        }

        $kind = EventKind::tryFrom(self::text($data, 'k', 8));
        $path = explode('#', explode('?', self::text($data, 'p', 200))[0])[0];
        if ($kind === null || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        [$source, $medium, $campaign] = self::origin($data, self::bare($siteHost));

        return new self(
            $kind,
            $path,
            match ($kind) {
                EventKind::Page => '',
                EventKind::Missing => self::linkedFrom(self::text($data, 'r', 500), self::bare($siteHost)),
                default => self::text($data, 'l', 80),
            },
            $source,
            $medium,
            $campaign,
            Device::fromUserAgent($userAgent),
        );
    }

    /** Copies this event with the source of the visit it belongs to. */
    public function from(string $source, string $medium, string $campaign): self
    {
        return new self($this->kind, $this->path, $this->label, $source, $medium, $campaign, $this->device);
    }

    /**
     * Campaign tags first, then a Google Ads click, then the page the visitor came from.
     *
     * @param array<mixed> $data
     * @return array{string, string, string}
     */
    private static function origin(array $data, string $siteHost): array
    {
        $utmSource = strtolower(self::text($data, 's', 60));
        if ($utmSource !== '') {
            return [$utmSource, strtolower(self::text($data, 'm', 60)), self::text($data, 'c', 100)];
        }
        if (($data['g'] ?? null) === 1 || ($data['g'] ?? null) === '1') {
            return ['google', 'cpc', ''];
        }

        $host = self::bare((string) parse_url(self::text($data, 'r', 500), PHP_URL_HOST));
        if ($host === '' || $host === $siteHost) {
            return ['', '', ''];
        }
        foreach (self::KNOWN as $part => [$name, $medium]) {
            if (self::isPart($host, $part)) {
                return [$name, $medium, ''];
            }
        }

        return [$host, 'referral', ''];
    }

    /** The page a broken link was on: its path on this site, or the other site's address. */
    private static function linkedFrom(string $referrer, string $siteHost): string
    {
        $host = self::bare((string) parse_url($referrer, PHP_URL_HOST));
        if ($host === '') {
            return '';
        }
        if ($host !== $siteHost) {
            return $host;
        }
        $path = (string) parse_url($referrer, PHP_URL_PATH);

        return mb_substr($path === '' ? '/' : $path, 0, 120);
    }

    /** "google." matches google.com and google.co.za; "t.co" matches t.co and its subdomains only. */
    private static function isPart(string $host, string $part): bool
    {
        if (str_ends_with($part, '.')) {
            return str_starts_with($host, $part) || str_contains($host, '.' . $part);
        }

        return $host === $part || str_ends_with($host, '.' . $part);
    }

    private static function isRobot(string $userAgent): bool
    {
        return trim($userAgent) === ''
            || preg_match('/bot|crawl|spider|slurp|headless|lighthouse|pingdom|monitor|preview|facebookexternalhit|curl|wget|python|httpclient|java\/|php\//i', $userAgent) === 1;
    }

    /** A host name in lower case, without "www.". */
    private static function bare(string $host): string
    {
        $host = strtolower(trim($host));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /** @param array<mixed> $data */
    private static function text(array $data, string $key, int $max): string
    {
        $value = $data[$key] ?? '';
        if (! is_string($value)) {
            return '';
        }
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));

        return mb_substr($value, 0, $max);
    }
}
