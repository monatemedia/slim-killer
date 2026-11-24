<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck\Rules;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\SiteCheck\Site;
use SlimKiller\SiteCheck\SiteRule;

/**
 * What search engines and AI search need from the site's code: a sitemap, robots.txt,
 * schema.org data, and (optional) llms.txt.
 */
final class SearchRule implements SiteRule
{
    public function heading(): string
    {
        return 'Search engines and AI search';
    }

    public function check(Site $site): array
    {
        $package = $site->json('site/package.json') ?? [];
        $dependencies = [
            ...(is_array($package['dependencies'] ?? null) ? $package['dependencies'] : []),
            ...(is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : []),
        ];
        $config = (string) ($site->read('site/astro.config.mjs') ?? $site->read('site/astro.config.ts'));
        $robots = $site->read('site/public/robots.txt') ?? $site->read('site/src/pages/robots.txt.ts');

        return [
            isset($dependencies['@astrojs/sitemap']) && str_contains($config, 'sitemap(')
                ? CheckResult::pass('A sitemap is built', '@astrojs/sitemap')
                : CheckResult::fail('No sitemap', 'search engines find new pages through it', 'Add @astrojs/sitemap to site/ and to the integrations in astro.config.'),
            match (true) {
                $robots === null => CheckResult::fail('No robots.txt', 'site/public/robots.txt or site/src/pages/robots.txt.ts', 'Allow everyone, keep /cp/ out, and point to the sitemap.'),
                ! str_contains(strtolower($robots), 'sitemap:') => CheckResult::warn('robots.txt doesn\'t point to the sitemap', 'no "Sitemap:" line', 'Add: Sitemap: https://example.com/sitemap-index.xml'),
                default => CheckResult::pass('robots.txt points to the sitemap'),
            },
            self::mentions($site, 'application/ld+json')
                ? CheckResult::pass('The site describes itself in schema.org', 'JSON-LD in site/src/')
                : CheckResult::fail('No schema.org data', 'search engines and AI search read the business, its address and prices from it', 'Add a JSON-LD block for the business to the layout, built from content/settings.'),
            $site->has('site/src/pages/llms.txt.ts') || $site->has('site/public/llms.txt')
                ? CheckResult::pass('llms.txt', 'the site in plain Markdown for AI assistants')
                : CheckResult::info('No llms.txt', 'optional: few AI tools read it yet, but it costs nothing when built from content'),
        ];
    }

    private static function mentions(Site $site, string $text): bool
    {
        foreach ($site->files('site/src') as $file) {
            if (str_contains((string) $site->read($file), $text)) {
                return true;
            }
        }

        return false;
    }
}
