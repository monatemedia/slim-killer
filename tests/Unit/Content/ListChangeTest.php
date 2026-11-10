<?php

declare(strict_types=1);

use SlimKiller\Content\Form\ListChange;

function sections(): array
{
    return ['title' => 'Page', 'sections' => [['heading' => 'One'], ['heading' => 'Two'], ['heading' => 'Three']]];
}

it('adds an empty item at the end and puts the cursor in it', function (): void {
    [$values, $focus] = ListChange::from('add:sections')->apply(sections());

    expect($values['sections'])->toHaveCount(4)
        ->and($values['sections'][3])->toBe([])
        ->and($focus)->toBe('sections.3');
});

it('adds the first item to a list that is not there yet, inside a group', function (): void {
    [$values, $focus] = ListChange::from('add:gallery.images')->apply(['title' => 'Page']);

    expect($values['gallery'])->toBe(['images' => [[]]])
        ->and($focus)->toBe('gallery.images.0');
});

it('removes an item and moves items up and down', function (): void {
    [$removed] = ListChange::from('remove:sections.1')->apply(sections());
    [$up, $focus] = ListChange::from('up:sections.2')->apply(sections());
    [$down] = ListChange::from('down:sections.0')->apply(sections());

    expect(array_column($removed['sections'], 'heading'))->toBe(['One', 'Three'])
        ->and(array_column($up['sections'], 'heading'))->toBe(['One', 'Three', 'Two'])
        ->and($focus)->toBe('sections.1')
        ->and(array_column($down['sections'], 'heading'))->toBe(['Two', 'One', 'Three']);
});

it('leaves a list alone when an item cannot move further', function (): void {
    [$values, $focus] = ListChange::from('up:sections.0')->apply(sections());

    expect($values)->toBe(sections())->and($focus)->toBeNull();
});

it('ignores button values that are not list changes', function (string $button): void {
    expect(ListChange::from($button))->toBeNull();
})->with(['', 'save', 'drop:sections.1', 'remove:sections', 'add:../etc', 'up:sections.x']);
