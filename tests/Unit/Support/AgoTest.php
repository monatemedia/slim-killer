<?php

declare(strict_types=1);

use SlimKiller\Support\Ago;

it('says how long ago, the way people do', function (int $seconds, string $expected): void {
    expect(Ago::seconds($seconds))->toBe($expected);
})->with([
    [-5, '0 seconds'],
    [40, '40 seconds'],
    [720, '12 minutes'],
    [10800, '3 hours'],
    [259200, '3 days'],
]);
