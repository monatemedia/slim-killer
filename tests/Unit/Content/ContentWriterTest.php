<?php

declare(strict_types=1);

use SlimKiller\Content\ContentWriter;
use SlimKiller\Content\Form\Editor;
use SlimKiller\Content\Form\Field;
use SlimKiller\Content\Form\FormReader;

const PAGE = <<<'MD'
---
title: "The Oryx"
lead: |
  First line.
  Second line.
featured: true
cta:
  heading: "Book now"
  label: "Go"
sections:
  - heading: "Desert"
    alt: ""
  - heading: "Water"

legacy: "kept by the panel"
---

## In the desert

The oryx can go for _weeks_ without water.

MD;

function pageFields(): array
{
    return [
        new Field('title', 'Page title', Editor::Text, true),
        new Field('lead', 'Opening paragraph', Editor::Textarea, false),
        new Field('featured', 'Featured', Editor::Toggle, false),
        new Field('hidden', 'Hidden', Editor::Toggle, false),
        new Field('eyebrow', 'Small heading', Editor::Text, false, default: 'Explore'),
        new Field('cta', 'Banner', Editor::Group, false, children: [
            new Field('heading', 'Heading', Editor::Text, true),
            new Field('label', 'Button text', Editor::Text, true),
        ]),
        new Field('sections', 'Sections', Editor::List, false, children: [
            new Field('heading', 'Heading', Editor::Text, true),
            new Field('alt', 'Image description', Editor::Text, false),
        ]),
    ];
}

/** The form exactly as the browser sends it back when nothing was touched. */
function untouched(): array
{
    return [
        'title' => 'The Oryx',
        'lead' => "First line.\r\nSecond line.",
        'featured' => '1',
        'hidden' => '0',
        'eyebrow' => '',
        'cta' => ['heading' => 'Book now', 'label' => 'Go'],
        'sections' => [['heading' => 'Desert', 'alt' => ''], ['heading' => 'Water', 'alt' => '']],
    ];
}

function save(array $posted, ?string $body = null, string $raw = PAGE): string
{
    $values = (new FormReader())->read(pageFields(), $posted)->values;

    return (new ContentWriter())->write($raw, pageFields(), $values, $body);
}

it('saves an untouched form as the same file, byte for byte', function (): void {
    expect(save(untouched(), "## In the desert\r\n\r\nThe oryx can go for _weeks_ without water."))->toBe(PAGE);
});

it('keeps a byte-order mark and Windows line endings', function (): void {
    $windows = "\xEF\xBB\xBF" . str_replace("\n", "\r\n", PAGE);

    expect(save(untouched(), null, $windows))->toBe($windows);
});

it('changes one line when one field changes', function (): void {
    $posted = untouched();
    $posted['title'] = 'The Gemsbok';

    expect(save($posted))->toBe(str_replace('title: "The Oryx"', 'title: "The Gemsbok"', PAGE));
});

it('rewrites only the group or list that changed, and keeps fields it does not know', function (): void {
    $posted = untouched();
    $posted['sections'][1]['heading'] = 'Rain';
    $saved = save($posted);

    expect($saved)->toContain("cta:\n  heading: \"Book now\"\n  label: \"Go\"\n")
        ->toContain('heading: "Rain"')
        ->toContain('legacy: "kept by the panel"')
        ->not->toContain('heading: "Water"');
});

it('removes a field the client cleared, and adds nothing for empty or default fields', function (): void {
    $posted = untouched();
    $posted['featured'] = '0';
    $posted['cta'] = ['heading' => '', 'label' => ''];
    $saved = save($posted);

    expect($saved)->toContain('featured: false')
        ->not->toContain('cta:')
        ->not->toContain('hidden:')
        ->not->toContain('eyebrow:');
});

it('writes a changed body, and keeps the body when only its line endings differ', function (): void {
    expect(save(untouched(), "## In the desert\n\nNew text.\n\n\n"))
        ->toEndWith("---\n\n## In the desert\n\nNew text.\n")
        ->and(save(untouched(), "## In the desert\r\n\r\nThe oryx can go for _weeks_ without water.\r\n"))->toBe(PAGE);
});

it('keeps a title split over two lines when the browser sends Windows line endings', function (): void {
    $raw = "---\ntitle: \"Wild Namibia,\\nUntamed Africa\"\n---\n";
    $values = (new FormReader())->read(pageFields(), ['title' => "Wild Namibia,\r\nUntamed Africa"])->values;

    expect($values['title'])->toBe("Wild Namibia,\nUntamed Africa")
        ->and((new ContentWriter())->write($raw, pageFields(), $values, null))->toBe($raw);
});
