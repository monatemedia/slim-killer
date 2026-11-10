<?php

declare(strict_types=1);

use SlimKiller\Content\Form\Editor;
use SlimKiller\Content\Form\Field;
use SlimKiller\Content\Form\FormReader;

function form(): array
{
    return [
        new Field('title', 'Page title', Editor::Text, true, maxLength: 10),
        new Field('lead', 'Opening paragraph', Editor::Textarea, false),
        new Field('order', 'Place in lists', Editor::Number, true, integer: true),
        new Field('price', 'Price', Editor::Number, false),
        new Field('featured', 'Featured', Editor::Toggle, false),
        new Field('side', 'Image side', Editor::Select, false, options: ['left', 'right']),
        new Field('tags', 'Tags', Editor::Tags, false),
        new Field('cta', 'Banner', Editor::Group, false, children: [
            new Field('heading', 'Heading', Editor::Text, true),
            new Field('label', 'Button text', Editor::Text, true),
        ]),
        new Field('sections', 'Sections', Editor::List, false, children: [
            new Field('heading', 'Heading', Editor::Text, true),
            new Field('body', 'Text', Editor::Markdown, false),
        ]),
    ];
}

it('turns a posted form into the values the front matter stores', function (): void {
    $submission = (new FormReader())->read(form(), [
        'title' => '  The Oryx ',
        'lead' => "First line\r\nSecond line\r\n\r\n",
        'order' => '3',
        'price' => '12.5',
        'featured' => '1',
        'side' => 'right',
        'tags' => 'desert, , wildlife ',
        'cta' => ['heading' => 'Book now', 'label' => 'Go'],
        'sections' => [
            ['heading' => 'One', 'body' => "**Bold**\r\n"],
            ['heading' => '', 'body' => ''],
            ['heading' => 'Two', 'body' => ''],
        ],
    ]);

    expect($submission->isValid())->toBeTrue()
        ->and($submission->values)->toBe([
            'title' => 'The Oryx',
            'lead' => "First line\nSecond line",
            'order' => 3,
            'price' => 12.5,
            'featured' => true,
            'side' => 'right',
            'tags' => ['desert', 'wildlife'],
            'cta' => ['heading' => 'Book now', 'label' => 'Go'],
            'sections' => [['heading' => 'One', 'body' => '**Bold**'], ['heading' => 'Two']],
        ]);
});

it('leaves empty optional fields out, so the site uses its defaults', function (): void {
    $submission = (new FormReader())->read(form(), [
        'title' => 'The Oryx', 'order' => '1', 'featured' => '0', 'side' => '', 'tags' => '',
        'cta' => ['heading' => '', 'label' => ''], 'sections' => [],
    ]);

    expect($submission->errors)->toBe([])
        ->and($submission->values)->toBe(['title' => 'The Oryx', 'order' => 1, 'featured' => false]);
});

it('describes each problem in the client\'s words, keyed by the field\'s path', function (): void {
    $submission = (new FormReader())->read(form(), [
        'title' => 'Far too long a title',
        'order' => '2.5',
        'price' => 'cheap',
        'side' => 'middle',
        'cta' => ['heading' => 'Book now', 'label' => ''],
        'sections' => [['heading' => '', 'body' => 'Text without a heading']],
    ]);

    expect($submission->errors)->toBe([
        'title' => 'Page title can have up to 10 characters. This has 20.',
        'order' => 'Place in lists must be a whole number.',
        'price' => 'Price must be a number.',
        'side' => 'Choose one of the options for Image side.',
        'cta.label' => 'Button text is needed.',
        'sections.0.heading' => 'Heading is needed.',
    ])->and($submission->values['title'])->toBe('Far too long a title');
});

it('counts characters, not bytes, against a limit', function (): void {
    $submission = (new FormReader())->read(form(), ['title' => '4×4 drives', 'order' => '1']);

    expect($submission->isValid())->toBeTrue();
});
