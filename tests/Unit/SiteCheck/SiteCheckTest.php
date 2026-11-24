<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\SiteCheckCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Content\ContentLocation;
use SlimKiller\Doctor\Report;
use SlimKiller\SiteCheck\Site;
use SlimKiller\SiteCheck\SiteChecker;
use SlimKiller\Support\Folder;

const GOOD_PAGE = '<!doctype html><html><head><title>About</title><meta name="description" content="Who we are"><link rel="canonical" href="https://example.com/about/"><meta property="og:image" content="https://example.com/share.jpg"><script type="application/ld+json">{}</script></head><body></body></html>';

/**
 * A client site that follows every rule, with files changed or removed as a test asks
 * ([path => contents], or null to leave a file out).
 *
 * @param array<string, string|null> $changes
 */
function clientSite(array $changes = []): Site
{
    $root = temporaryFolder();
    $schema = json_encode(['type' => 'object', 'properties' => [
        '$schema' => ['type' => 'string'],
        'title' => ['type' => 'string', 'title' => 'Page title'],
        'photo' => ['type' => 'object', 'title' => 'Photo', 'properties' => [
            'src' => ['type' => 'string', 'title' => 'Image', 'input' => 'image'],
            'alt' => ['type' => 'string', 'title' => 'Image description'],
        ]],
        'faqs' => ['type' => 'array', 'title' => 'Questions', 'items' => ['type' => 'object', 'properties' => [
            'question' => ['type' => 'string', 'title' => 'Question'],
        ]]],
    ]]);
    $files = [
        'content/settings/index.md' => "---\nbusinessName: \"Acme Tours\"\n---\n",
        'content/about/index.md' => "---\ntitle: \"About\"\n---\nWe run **tours**. Write to us: `<b>` is code, not HTML.\n",
        'content/images/oryx.jpg' => '',
        'content/graphics/map.svg' => '<svg/>',
        '.github/workflows/build-site.yml' => 'name: Build',
        'site/package.json' => json_encode(['engines' => ['node' => '>=24 <25'], 'dependencies' => ['astro' => '^7.4.13', '@astrojs/sitemap' => '^3.7.6']]),
        'site/package-lock.json' => '{}',
        'site/.nvmrc' => '24',
        'site/astro.config.mjs' => "import sitemap from '@astrojs/sitemap';\nexport default { site: 'https://example.com', integrations: [sitemap()] };",
        'site/slim-killer.json' => json_encode(['addresses' => ['about' => '/about/', 'settings' => null], 'redirects' => ['/about-us' => '/about/']]),
        'site/public/robots.txt' => "User-agent: *\nAllow: /\nSitemap: https://example.com/sitemap-index.xml\n",
        'site/src/layouts/Layout.astro' => '<script type="application/ld+json" set:html={json} />',
        'site/src/pages/llms.txt.ts' => 'export const GET = () => new Response("# Acme");',
        'site/.astro/collections/about.schema.json' => $schema,
        'site/.astro/collections/settings.schema.json' => json_encode(['type' => 'object', 'properties' => ['businessName' => ['type' => 'string', 'title' => 'Business name']]]),
        'site/dist/sitemap-index.xml' => '<sitemapindex/>',
        'site/dist/about/index.html' => GOOD_PAGE,
        'site/dist/404.html' => '<title>Not found</title>',
        'site/dist/about-us/index.html' => '<meta http-equiv="refresh" content="0;url=/about/">',
    ];
    foreach ([...$files, ...$changes] as $path => $contents) {
        if ($contents === null) {
            continue;
        }
        is_dir(dirname($root . '/' . $path)) || mkdir(dirname($root . '/' . $path), 0755, true);
        file_put_contents($root . '/' . $path, $contents);
    }

    return new Site($root);
}

/** Each line of a report, as "status title: detail". @return list<string> */
function lines(Report $report): array
{
    $lines = [];
    foreach ($report->sections as $section) {
        foreach ($section->results as $result) {
            $lines[] = $result->status->label() . ' ' . $result->title . ($result->detail !== '' ? ': ' . $result->detail : '');
        }
    }

    return $lines;
}

/** The lines that aren't PASS or INFO. @return list<string> */
function problems(Site $site): array
{
    return array_values(array_filter(lines(SiteChecker::standard()->check($site)), static fn(string $line): bool => str_starts_with($line, 'WARN') || str_starts_with($line, 'FAIL')));
}

it('passes a site that follows every rule, in six sections', function (): void {
    $report = SiteChecker::standard()->check(clientSite());

    expect($report->passed())->toBeTrue()
        ->and(problems(clientSite()))->toBe([])
        ->and(array_map(static fn($section): string => $section->title, $report->sections))
        ->toBe(['Layout', 'Content', 'Schemas', 'Build', 'Search engines and AI search', 'Built pages'])
        ->and(lines($report))->toContain('INFO Old addresses sent on: 1 redirects, each a single 301')
        ->and(lines($report))->toContain('PASS Every page has a canonical address: 1 pages');
});

it('fails the layout a site must have from day one', function (): void {
    expect(problems(clientSite([
        'content/settings/index.md' => null,
        '.github/workflows/build-site.yml' => null,
        'site/slim-killer.json' => json_encode(['addresses' => ['settings' => null], 'redirects' => ['old' => 'https://elsewhere.com/']]),
    ])))->toBe([
        'FAIL No settings in content/settings/index.md: names, contact details and menus belong there, not in components',
        'FAIL No .github/workflows/build-site.yml: GitHub builds the site and publishes it to the dist branch',
        'FAIL Content folders without an address: about',
        'FAIL Redirects that are not addresses on this site: old',
    ])->and(problems(clientSite(['site/slim-killer.json' => null])))->toContain('FAIL No page addresses in site/slim-killer.json: the control panel reads each content folder\'s web address there');

    $bare = temporaryFolder();
    mkdir($bare . '/content/about', 0755, true);
    expect(problems(new Site($bare)))->toContain('FAIL No Astro project in site/: site/package.json and site/astro.config.mjs are missing');
});

it('finds graphics among the photos, names that can\'t be addresses, MDX and HTML', function (): void {
    expect(problems(clientSite([
        'content/images/logo.svg' => '<svg/>',
        'content/images/spinner.GIF' => '',
        'content/about/Our Team.md' => "---\ntitle: \"Team\"\n---\n",
        'content/about/story.mdx' => "---\ntitle: \"Story\"\n---\n<Gallery />\n",
        'content/about/visit.md' => "---\ntitle: \"Visit\"\n---\nCome by.\n\n<div class=\"map\"></div>\n",
        'content/about/_notes.md' => "Notes for the client, <b>never</b> published.\n",
    ])))->toBe([
        'FAIL Graphics among the photos: logo.svg, spinner.GIF',
        'FAIL Files that can\'t be web addresses: content/about/Our Team.md, content/about/story.mdx (MDX)',
        'FAIL HTML in Markdown bodies: content/about/_notes.md, content/about/visit.md',
    ]);
});

it('warns about a byte-order mark or Windows line endings', function (): void {
    expect(problems(clientSite([
        'content/about/index.md' => "\xEF\xBB\xBF---\ntitle: \"About\"\n---\n",
        'content/settings/index.md' => "---\r\nbusinessName: \"Acme\"\r\n---\r\n",
    ])))->toBe(['WARN Files in another style: content/about/index.md, content/settings/index.md']);
});

it('reads the schemas: one per folder, a title on every field, images marked as images', function (): void {
    $untitled = json_encode(['type' => 'object', 'properties' => [
        'intro' => ['type' => 'string'],
        'photo' => ['type' => 'object', 'title' => 'Photo', 'properties' => ['src' => ['type' => 'string', 'title' => 'Image'], 'alt' => ['type' => 'string', 'title' => 'Description']]],
        'faqs' => ['type' => 'array', 'title' => 'Questions', 'items' => ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]]],
    ]]);

    expect(problems(clientSite(['site/.astro/collections/about.schema.json' => $untitled, 'site/.astro/collections/settings.schema.json' => null])))->toBe([
        'FAIL Content folders without a schema: settings',
        'FAIL Fields without a title: about: intro, about: faqs[].answer',
        'FAIL Image fields the panel can\'t tell are images: about: photo',
    ]);

    $unsynced = clientSite();
    Folder::delete($unsynced->path('site/.astro'));
    expect(problems($unsynced))->toBe(['WARN Schemas not read: site/.astro/collections/ is missing']);
});

it('checks how the site is built: Node, the lock file, Astro, its address, no .htaccess', function (): void {
    expect(problems(clientSite([
        'site/package.json' => json_encode(['dependencies' => ['astro' => '^6.1.0', '@astrojs/sitemap' => '^3.7.6']]),
        'site/package-lock.json' => null,
        'site/astro.config.mjs' => "import sitemap from '@astrojs/sitemap';\nexport default { integrations: [sitemap()] };",
        'site/public/.htaccess' => 'Redirect 301 /old /new/',
    ])))->toBe([
        'FAIL Node isn\'t pinned: no "engines": { "node": ... } in site/package.json',
        'FAIL No site/package-lock.json: GitHub installs with npm ci, which needs it',
        'WARN Astro ^6.1.0: Slim Killer reads schemas from Astro 7',
        'FAIL No site address in astro.config: sitemaps, canonical addresses and share cards need it',
        'FAIL An .htaccess in site/public/: the host writes its own rules; one in the build is ignored or gets in the way',
    ]);
});

it('checks what search engines and AI search need, and only informs about llms.txt', function (): void {
    $site = clientSite([
        'site/package.json' => json_encode(['engines' => ['node' => '24'], 'dependencies' => ['astro' => '^7.4.13']]),
        'site/public/robots.txt' => "User-agent: *\nAllow: /\n",
        'site/src/layouts/Layout.astro' => '<head></head>',
        'site/src/pages/llms.txt.ts' => null,
    ]);

    expect(problems($site))->toBe([
        'FAIL No sitemap: search engines find new pages through it',
        'WARN robots.txt doesn\'t point to the sitemap: no "Sitemap:" line',
        'FAIL No schema.org data: search engines and AI search read the business, its address and prices from it',
    ])->and(lines(SiteChecker::standard()->check($site)))->toContain('INFO No llms.txt: optional: few AI tools read it yet, but it costs nothing when built from content');
});

it('checks every built page a search engine may list, and leaves out redirects, the 404 page and noindex pages', function (): void {
    $bare = '<!doctype html><html><head><title> </title></head><body>Hi</body></html>';

    expect(problems(clientSite([
        'site/dist/tours/index.html' => $bare,
        'site/dist/contact/thanks/index.html' => '<meta name="robots" content="noindex">' . $bare,
        'site/dist/sitemap-index.xml' => null,
    ])))->toBe([
        'FAIL No sitemap in the build: site/dist/sitemap-index.xml is missing',
        'FAIL Pages without a title: /tours/',
        'FAIL Pages without a description: /tours/',
        'FAIL Pages without a canonical address: /tours/',
        'FAIL Pages without schema.org data: /tours/',
        'FAIL Pages without a share image: /tours/',
    ]);

    $unbuilt = clientSite();
    Folder::delete($unbuilt->path('site/dist'));
    expect(lines(SiteChecker::standard()->check($unbuilt)))->toContain('INFO Not built yet: run "npm run build" in site/, then check again to check every page');
});

it('checks the folder it is given, or the site CONTENT_DIR points at, and says so when it has neither', function (): void {
    $run = static function (array $arguments, ContentLocation $location, bool $configured): array {
        $stream = fopen('php://memory', 'w+');
        $code = (new SiteCheckCommand($location, $configured))->run(new Input('site:check', $arguments), new Output($stream, false));
        rewind($stream);

        return [$code, (string) stream_get_contents($stream)];
    };
    $good = clientSite();
    [$passed, $text] = $run([$good->root], new ContentLocation('', ''), false);
    [$fromEnv] = $run([], new ContentLocation($good->path('content'), ''), true);
    [$failed] = $run([clientSite(['content/images/logo.svg' => ''])->root], new ContentLocation('', ''), false);
    [$asked, $askText] = $run([], new ContentLocation('', ''), false);
    [$notSite, $notText] = $run([temporaryFolder()], new ContentLocation('', ''), false);

    expect($passed)->toBe(0)
        ->and($text)->toContain('Slim Killer site check: ' . basename($good->root))
        ->and($text)->toContain('Follows the Astro site rules.')
        ->and($fromEnv)->toBe(0)
        ->and($failed)->toBe(1)
        ->and($asked)->toBe(2)
        ->and($askText)->toContain('site:check ../client-site')
        ->and($notSite)->toBe(2)
        ->and($notText)->toContain('has no content/ or site/ folder');
});
