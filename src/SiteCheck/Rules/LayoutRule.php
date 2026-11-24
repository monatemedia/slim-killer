<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck\Rules;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\SiteCheck\Listing;
use SlimKiller\SiteCheck\Site;
use SlimKiller\SiteCheck\SiteRule;

/** The client-site layout (Astro site rules 1, 5 and 14), and slim-killer.json. */
final class LayoutRule implements SiteRule
{
    public function heading(): string
    {
        return 'Layout';
    }

    public function check(Site $site): array
    {
        $results = [];

        $astro = $site->has('site/package.json') && ($site->has('site/astro.config.mjs') || $site->has('site/astro.config.ts'));
        $results[] = $astro
            ? CheckResult::pass('The Astro project is in site/')
            : CheckResult::fail('No Astro project in site/', 'site/package.json and site/astro.config.mjs are missing', 'Astro site rule 1: the Astro project lives in site/, beside content/.');

        $results[] = is_dir($site->path('content'))
            ? CheckResult::pass('Content is in content/', count($site->collections()) . ' collections')
            : CheckResult::fail('No content/ folder', 'everything the client edits belongs in content/', 'Astro site rule 1 and 2.');

        $results[] = $site->has('content/settings/index.md')
            ? CheckResult::pass('Settings are content', 'content/settings/index.md')
            : CheckResult::fail('No settings in content/settings/index.md', 'names, contact details and menus belong there, not in components', 'Astro site rule 5.');

        $results[] = $site->has('.github/workflows/build-site.yml')
            ? CheckResult::pass('It publishes the Slim Killer way', '.github/workflows/build-site.yml')
            : CheckResult::fail('No .github/workflows/build-site.yml', 'GitHub builds the site and publishes it to the dist branch', 'Astro site rule 14: copy the workflow from Slim Killer\'s template.');

        return [...$results, ...$this->addresses($site)];
    }

    /** @return list<CheckResult> */
    private function addresses(Site $site): array
    {
        $json = $site->json('site/slim-killer.json');
        $addresses = is_array($json['addresses'] ?? null) ? $json['addresses'] : null;
        if ($addresses === null) {
            return [CheckResult::fail(
                'No page addresses in site/slim-killer.json',
                'the control panel reads each content folder\'s web address there',
                'Give every content folder an address, like "about": "/about/", or null when it has no page of its own.',
            )];
        }

        $missing = array_values(array_diff($site->collections(), array_map('strval', array_keys($addresses))));
        $results = [$missing === []
            ? CheckResult::pass('Every content folder has its address', 'site/slim-killer.json')
            : CheckResult::fail(
                'Content folders without an address',
                Listing::of($missing),
                'Add each to "addresses" in site/slim-killer.json, or null when it has no page of its own.',
            )];

        $redirects = is_array($json['redirects'] ?? null) ? $json['redirects'] : [];
        $bad = [];
        foreach ($redirects as $from => $to) {
            if (! str_starts_with((string) $from, '/') || ! is_string($to) || ! str_starts_with($to, '/')) {
                $bad[] = (string) $from;
            }
        }
        if ($bad !== []) {
            $results[] = CheckResult::fail('Redirects that are not addresses on this site', Listing::of($bad), 'Both sides of a redirect start with "/", like "/old-page": "/new-page/".');
        } elseif ($redirects !== []) {
            $results[] = CheckResult::info('Old addresses sent on', count($redirects) . ' redirects, each a single 301');
        }

        return $results;
    }
}
