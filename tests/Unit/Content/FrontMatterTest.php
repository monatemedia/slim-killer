<?php

declare(strict_types=1);

use SlimKiller\Content\ContentFile;
use SlimKiller\Content\FrontMatter;
use SlimKiller\Content\GitBlob;
use Symfony\Component\Yaml\Yaml;

const SAMPLE = "title: \"Discover Namibia\"\ndescription: 'Known as \"the land of wide-open spaces\".'\n\neyebrow: \"Explore With Us\"\n# The page's sections\nsections:\n  - heading: \"Geography\"\n    body: |\n      Namibia spans 824,000 square kilometres.\n\n      Sossusvlei is a salt pan.\n    image: \"namibia-map.png\"\n  - heading: \"Climate\"\n    image: \"climate.png\"";

it('saves an unchanged file byte for byte, byte-order mark and Windows line endings included', function (): void {
    $raw = "\xEF\xBB\xBF---\r\n" . str_replace("\n", "\r\n", SAMPLE) . "\r\n---\r\n\r\nBody text.\r\n";
    $file = ContentFile::parse($raw);
    $frontMatter = FrontMatter::parse($file->yaml);

    expect($file->with($frontMatter->with($frontMatter->data()), $file->body()))->toBe($raw)
        ->and($file->body())->toBe("\nBody text.\n");
});

it('keeps a file that ends right after the front matter as it is', function (): void {
    $raw = "---\ntitle: \"Only front matter\"\n---";
    $file = ContentFile::parse($raw);

    expect($file->with($file->yaml, $file->body()))->toBe($raw);
});

it('changes one line when one simple field changes', function (): void {
    $frontMatter = FrontMatter::parse(SAMPLE);
    $data = $frontMatter->data();
    $data['title'] = 'Discover Namibia (edited)';
    $after = $frontMatter->with($data);

    $changed = array_diff_assoc(explode("\n", SAMPLE), explode("\n", $after));

    expect($changed)->toHaveCount(1)
        ->and(Yaml::parse($after))->toBe($data)
        ->and($after)->toContain("\n\neyebrow:")
        ->and($after)->toContain("# The page's sections");
});

it('rewrites only the list that changed, in house style, with every value exact', function (): void {
    $frontMatter = FrontMatter::parse(SAMPLE);
    $data = $frontMatter->data();
    $data['sections'][1]['heading'] = 'Weather';
    $after = $frontMatter->with($data);

    expect(Yaml::parse($after))->toBe($data)
        ->and($after)->toStartWith("title: \"Discover Namibia\"\ndescription: 'Known as")
        ->and($after)->toContain("  - heading: \"Weather\"\n    image: \"climate.png\"")
        ->and($after)->toContain("    body: |\n      Namibia spans");
});

it('adds new fields at the end and drops removed ones', function (): void {
    $frontMatter = FrontMatter::parse(SAMPLE);
    $data = $frontMatter->data();
    unset($data['eyebrow']);
    $data['seoTitle'] = 'Namibia';

    $after = $frontMatter->with($data);

    expect($after)->not->toContain('eyebrow')
        ->and($after)->toEndWith('seoTitle: "Namibia"')
        ->and(Yaml::parse($after))->toEqual($data);
});

it('names a file\'s version the way Git does', function (): void {
    // `printf 'hello\n' | git hash-object --stdin`
    expect(GitBlob::hash("hello\n"))->toBe('ce013625030ba8dba906f756967f9e9ca394464a');
});
