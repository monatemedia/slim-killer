<?php

declare(strict_types=1);

use SlimKiller\Doctor\BinaryFinder;
use SlimKiller\Doctor\Checks\PhpBinaryCheck;
use SlimKiller\Doctor\PhpBinary;

function hostingerFinder(): BinaryFinder
{
    // What spike S2 found on Hostinger Premium.
    $paths = [];
    foreach (['44', '56', '74', '82', '83', '84', '85'] as $v) {
        $paths[] = "/opt/alt/php{$v}/usr/bin/php";
    }

    return new BinaryFinder(['/opt/alt/php*/usr/bin/php'], fn(string $pattern): array => $paths);
}

it('reads versions from binary paths', function (string $path, ?string $version): void {
    expect(PhpBinary::fromPath($path)?->version)->toBe($version);
})->with([
    ['/opt/alt/php84/usr/bin/php', '8.4'],
    ['/usr/bin/php8.3', '8.3'],
    ['/opt/cpanel/ea-php82/root/usr/bin/php', '8.2'],
    ['/usr/bin/php', null],
    ['/usr/bin/phpize8.3', null],
    ['/opt/alt/php44/usr/bin/php', null],
]);

it('finds PHP versions in order and picks the oldest one that is new enough', function (): void {
    $finder = hostingerFinder();

    expect(array_map(fn($b) => $b->version, $finder->find()))->toBe(['5.6', '7.4', '8.2', '8.3', '8.4', '8.5'])
        ->and($finder->best('8.4.0')?->path)->toBe('/opt/alt/php84/usr/bin/php');
});

it('reports the PHP that hammer is running on, and the versions installed', function (): void {
    $results = (new PhpBinaryCheck(environment(), hostingerFinder()))->check();

    expect(statuses($results))->toBe(['pass', 'info'])
        ->and($results[0]->detail)->toContain('/opt/alt/php84/usr/bin/php')
        ->and($results[1]->detail)->toContain('8.5');
});
