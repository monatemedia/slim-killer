<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck\Rules;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\SiteCheck\Listing;
use SlimKiller\SiteCheck\Site;
use SlimKiller\SiteCheck\SiteRule;

/**
 * The schemas the control panel builds its forms from (Astro site rules 4 and 6), read
 * as `astro sync` writes them to site/.astro/collections/.
 */
final class SchemasRule implements SiteRule
{
    public function heading(): string
    {
        return 'Schemas';
    }

    public function check(Site $site): array
    {
        if (! is_dir($site->path('site/.astro/collections'))) {
            return [CheckResult::warn(
                'Schemas not read',
                'site/.astro/collections/ is missing',
                'Run "npx astro sync" (or a build) in site/, then check again.',
            )];
        }

        $missing = [];
        $untitled = [];
        $images = [];
        foreach ($site->collections() as $collection) {
            $schema = $site->json($site->schemaFile($collection));
            if ($schema === null) {
                $missing[] = $collection;

                continue;
            }
            self::walk($schema, $collection, $untitled, $images);
        }

        return [
            $missing === []
                ? CheckResult::pass('Every content folder has its own schema')
                : CheckResult::fail('Content folders without a schema', Listing::of($missing), 'Astro site rule 4: each is a collection in site/src/content.config.ts, loaded from ../content/<folder>.'),
            $untitled === []
                ? CheckResult::pass('Every field has its editor hint', 'a title, in the client\'s words')
                : CheckResult::fail('Fields without a title', Listing::of($untitled), 'Astro site rule 6: add .meta({ title: \'...\' }) in the client\'s words; the control panel shows it as the field\'s label.'),
            $images === []
                ? CheckResult::pass('Every image field is an image', 'input: "image"')
                : CheckResult::fail('Image fields the panel can\'t tell are images', Listing::of($images), 'Astro site rule 6: use image() from the building blocks, which gives { src, alt } with input: "image".'),
        ];
    }

    /**
     * Collects every field without a title, and every { src, alt } without input: "image".
     *
     * @param array<mixed> $node
     * @param list<string> $untitled
     * @param list<string> $images
     */
    private static function walk(array $node, string $path, array &$untitled, array &$images): void
    {
        $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];
        if (isset($properties['src'], $properties['alt']) && is_array($properties['src']) && ($properties['src']['input'] ?? null) !== 'image') {
            $images[] = $path;
        }
        foreach ($properties as $name => $field) {
            // "$schema" is Astro's own, for editors that read front matter; never a field.
            if (! is_array($field) || str_starts_with((string) $name, '$')) {
                continue;
            }
            $here = $path . ': ' . $name;
            if (str_contains($path, ': ')) {
                $here = $path . '.' . $name;
            }
            if (! isset($field['title']) || $field['title'] === '') {
                $untitled[] = $here;
            }
            self::walk($field, $here, $untitled, $images);
            if (is_array($field['items'] ?? null)) {
                self::walk($field['items'], $here . '[]', $untitled, $images);
            }
        }
    }
}
