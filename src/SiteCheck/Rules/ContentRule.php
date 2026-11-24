<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck\Rules;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\SiteCheck\Listing;
use SlimKiller\SiteCheck\Site;
use SlimKiller\SiteCheck\SiteRule;

/** Images, file names and Markdown bodies (Astro site rules 3, 8, 9 and 10). */
final class ContentRule implements SiteRule
{
    /** Files that are never photos: they go in content/graphics/. */
    private const array NOT_PHOTOS = ['svg', 'gif', 'ico'];

    public function heading(): string
    {
        return 'Content';
    }

    public function check(Site $site): array
    {
        $markdown = [];
        foreach ($site->collections() as $collection) {
            foreach ($site->files('content/' . $collection) as $file) {
                $markdown[] = $file;
            }
        }

        return [
            $this->images($site),
            $this->names($markdown),
            $this->bodies($site, $markdown),
            $this->style($site, $markdown),
        ];
    }

    private function images(Site $site): CheckResult
    {
        $wrong = [];
        foreach ($site->files('content/images') as $file) {
            if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::NOT_PHOTOS, true)) {
                $wrong[] = basename($file);
            }
        }

        return $wrong === []
            ? CheckResult::pass('Photos and graphics are apart', 'no SVG, GIF or ICO in content/images/')
            : CheckResult::fail('Graphics among the photos', Listing::of($wrong), 'Astro site rule 3: move them to content/graphics/, which is copied as it is.');
    }

    /** @param list<string> $files */
    private function names(array $files): CheckResult
    {
        $wrong = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (str_starts_with($name, '_')) {
                continue;
            }
            if (str_ends_with($name, '.mdx')) {
                $wrong[] = $file . ' (MDX)';
            } elseif (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*\.md$/', $name) !== 1) {
                $wrong[] = $file;
            }
        }

        return $wrong === []
            ? CheckResult::pass('File names are web addresses', 'lowercase, hyphens, .md')
            : CheckResult::fail('Files that can\'t be web addresses', Listing::of($wrong), 'Astro site rules 8 and 9: plain Markdown files, named in lowercase with hyphens, like fish-river-canyon.md.');
    }

    /** @param list<string> $files */
    private function bodies(Site $site, array $files): CheckResult
    {
        $html = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '.md') && self::hasHtml(self::body((string) $site->read($file)))) {
                $html[] = $file;
            }
        }

        return $html === []
            ? CheckResult::pass('Bodies are plain Markdown', 'no HTML in them')
            : CheckResult::fail('HTML in Markdown bodies', Listing::of($html), 'Astro site rule 8: the editor can\'t keep HTML. Use front matter fields the template renders instead.');
    }

    /** @param list<string> $files */
    private function style(Site $site, array $files): CheckResult
    {
        $odd = [];
        foreach ($files as $file) {
            $text = (string) $site->read($file);
            if (str_starts_with($text, "\xEF\xBB\xBF") || str_contains($text, "\r\n")) {
                $odd[] = $file;
            }
        }

        return $odd === []
            ? CheckResult::pass('One file style', 'UTF-8 without a byte-order mark, Unix line endings')
            : CheckResult::warn('Files in another style', Listing::of($odd), 'Astro site rule 10: save them as UTF-8 without a byte-order mark, with Unix line endings (.gitattributes can do it).');
    }

    /** A Markdown file's body: what comes after its front matter. */
    private static function body(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);

        return preg_match('/^\x{FEFF}?---\n.*?\n---\n?(.*)$/su', $text, $match) === 1 ? $match[1] : $text;
    }

    /** HTML tags outside code; comments are allowed. */
    private static function hasHtml(string $body): bool
    {
        $prose = (string) preg_replace(['/^(```|~~~).*?^\1/ms', '/`[^`\n]*`/', '/<!--.*?-->/s'], '', $body);

        return preg_match('#</?[a-zA-Z][a-zA-Z0-9-]*(\s[^<>]*)?/?>#', $prose) === 1;
    }
}
