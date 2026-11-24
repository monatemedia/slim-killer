<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck\Rules;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\SiteCheck\Listing;
use SlimKiller\SiteCheck\Site;
use SlimKiller\SiteCheck\SiteRule;

/**
 * The last build (site/dist): every page a search engine may list has a title, a
 * description, a canonical address, schema.org data and a share image. Pages that only
 * send the visitor on, the 404 page and pages marked noindex are left out.
 */
final class PagesRule implements SiteRule
{
    /** What each page needs, and how it's found in the HTML. */
    private const array NEEDS = [
        'a title' => '#<title>[^<]*\S[^<]*</title>#i',
        'a description' => '#<meta\s[^>]*name=["\']description["\'][^>]*content=["\'][^"\']*\S#i',
        'a canonical address' => '#<link\s[^>]*rel=["\']canonical["\']#i',
        'schema.org data' => '#<script\s[^>]*type=["\']application/ld\+json["\']#i',
        'a share image' => '#<meta\s[^>]*property=["\']og:image["\']#i',
    ];

    public function heading(): string
    {
        return 'Built pages';
    }

    public function check(Site $site): array
    {
        if (! is_dir($site->path('site/dist'))) {
            return [CheckResult::info('Not built yet', 'run "npm run build" in site/, then check again to check every page')];
        }

        $pages = [];
        foreach ($site->files('site/dist') as $file) {
            if (! str_ends_with($file, '.html') || str_contains($file, '/_slim-killer/') || basename($file) === '404.html') {
                continue;
            }
            $html = (string) $site->read($file);
            if (preg_match('#http-equiv=["\']refresh["\']#i', $html) === 1 || preg_match('#<meta\s[^>]*name=["\']robots["\'][^>]*noindex#i', $html) === 1) {
                continue;
            }
            $pages[self::address($file)] = $html;
        }

        $results = [$site->has('site/dist/sitemap-index.xml')
            ? CheckResult::pass('The sitemap was built', 'site/dist/sitemap-index.xml')
            : CheckResult::fail('No sitemap in the build', 'site/dist/sitemap-index.xml is missing')];

        foreach (self::NEEDS as $need => $pattern) {
            $without = [];
            foreach ($pages as $address => $html) {
                if (preg_match($pattern, $html) !== 1) {
                    $without[] = $address;
                }
            }
            $results[] = $without === []
                ? CheckResult::pass('Every page has ' . $need, count($pages) . ' pages')
                : CheckResult::fail('Pages without ' . $need, Listing::of($without), 'Give the layout\'s <head> ' . $need . ' for every page, from its content.');
        }

        return $results;
    }

    /** "site/dist/about/index.html" -> "/about/" */
    private static function address(string $file): string
    {
        $path = substr($file, strlen('site/dist'));

        return (string) preg_replace('#index\.html$#', '', $path);
    }
}
