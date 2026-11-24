<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck\Rules;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\SiteCheck\Site;
use SlimKiller\SiteCheck\SiteRule;

/** How the site is built (Astro site rules 11, 12 and 13). */
final class BuildRule implements SiteRule
{
    /** The Astro versions Slim Killer's control panel reads schemas from. */
    public const int ASTRO_MAJOR = 7;

    public function heading(): string
    {
        return 'Build';
    }

    public function check(Site $site): array
    {
        $package = $site->json('site/package.json') ?? [];
        $engines = is_array($package['engines'] ?? null) ? $package['engines'] : [];
        $node = is_string($engines['node'] ?? null) ? $engines['node'] : '';
        $dependencies = [
            ...(is_array($package['dependencies'] ?? null) ? $package['dependencies'] : []),
            ...(is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : []),
        ];
        $astro = is_string($dependencies['astro'] ?? null) ? $dependencies['astro'] : '';
        $config = (string) ($site->read('site/astro.config.mjs') ?? $site->read('site/astro.config.ts'));

        return [
            $node !== ''
                ? CheckResult::pass('Node is pinned', 'engines: ' . $node . ($site->has('site/.nvmrc') ? ', and .nvmrc' : ''))
                : CheckResult::fail('Node isn\'t pinned', 'no "engines": { "node": ... } in site/package.json', 'Astro site rule 12: pin Node in package.json and in site/.nvmrc.'),
            $site->has('site/package-lock.json')
                ? CheckResult::pass('Dependencies are pinned', 'site/package-lock.json')
                : CheckResult::fail('No site/package-lock.json', 'GitHub installs with npm ci, which needs it', 'Astro site rule 12: run npm install in site/ and commit package-lock.json.'),
            self::astro($astro),
            preg_match('/\bsite:\s*[\'"]https:\/\/[^\'"]+[\'"]/', $config) === 1
                ? CheckResult::pass('The site knows its address', 'site: in astro.config')
                : CheckResult::fail('No site address in astro.config', 'sitemaps, canonical addresses and share cards need it', 'Astro site rule 13: set site: \'https://example.com\' to the real address.'),
            $site->has('site/public/.htaccess')
                ? CheckResult::fail('An .htaccess in site/public/', 'the host writes its own rules; one in the build is ignored or gets in the way', 'Astro site rule 11: delete it, and put old addresses in "redirects" in site/slim-killer.json.')
                : CheckResult::pass('Nothing host-specific in the build', 'no .htaccess in site/public/'),
        ];
    }

    private static function astro(string $version): CheckResult
    {
        if ($version === '') {
            return CheckResult::fail('Astro isn\'t a dependency', 'no "astro" in site/package.json');
        }
        if (preg_match('/(\d+)\./', $version, $match) === 1 && (int) $match[1] === self::ASTRO_MAJOR) {
            return CheckResult::pass('Astro ' . self::ASTRO_MAJOR, $version);
        }

        return CheckResult::warn(
            'Astro ' . $version,
            'Slim Killer reads schemas from Astro ' . self::ASTRO_MAJOR,
            'Astro site rule 12: move the site to Astro ' . self::ASTRO_MAJOR . ', or check the control panel against it first.',
        );
    }
}
