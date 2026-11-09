<?php

declare(strict_types=1);

use SlimKiller\Content\Form\Editor;
use SlimKiller\Content\Form\Field;
use SlimKiller\Content\Form\FieldFactory;

function fixtureSchema(string $name): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2) . "/Fixtures/site/schemas/{$name}.schema.json"), true);
}

/** @param list<Field> $fields */
function byName(array $fields): array
{
    return array_combine(array_map(fn($f) => $f->name, $fields), $fields);
}

it('uses the schema\'s labels, help and editor hints first', function (): void {
    $fields = byName((new FieldFactory())->fields(fixtureSchema('articles')));

    expect($fields['title']->label)->toBe('Page title')
        ->and($fields['description']->kind)->toBe(Editor::Textarea)
        ->and($fields['description']->hinted)->toBeTrue()
        ->and($fields['description']->help)->toBe('One or two sentences.')
        ->and($fields['title']->required)->toBeTrue()
        ->and(array_keys($fields))->not->toContain('$schema');
});

it('guesses the editor from the field\'s name and type when there is no hint', function (): void {
    $articles = byName((new FieldFactory())->fields(fixtureSchema('articles')));
    $sections = byName(byName((new FieldFactory())->fields(fixtureSchema('pages')))['sections']->children);

    expect($articles['heroImage']->kind)->toBe(Editor::Image)
        ->and($articles['heroImage']->hinted)->toBeFalse()
        ->and($articles['heroImage']->label)->toBe('Hero image')
        ->and($sections['imagePosition']->kind)->toBe(Editor::Select)
        ->and($sections['imagePosition']->options)->toBe(['left', 'right'])
        ->and($sections['featured']->kind)->toBe(Editor::Toggle)
        ->and($sections['order']->kind)->toBe(Editor::Number);
});

it('builds groups and lists of groups from nested schemas', function (): void {
    $fields = byName((new FieldFactory())->fields(fixtureSchema('pages')));
    $sections = byName($fields['sections']->children);
    $gallery = byName(byName((new FieldFactory())->fields(fixtureSchema('articles')))['gallery']->children);

    expect($fields['sections']->kind)->toBe(Editor::List)
        ->and($sections['quote']->kind)->toBe(Editor::Group)
        ->and(array_keys(byName($sections['quote']->children)))->toBe(['text', 'cite'])
        ->and($fields['tags']->kind)->toBe(Editor::Tags)
        ->and($gallery['src']->kind)->toBe(Editor::Image)
        ->and($gallery['alt']->kind)->toBe(Editor::Text);
});
