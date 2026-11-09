<?php

declare(strict_types=1);

use SlimKiller\Support\Bytes;

it('reads PHP ini sizes', function (string $value, int $bytes): void {
    expect(Bytes::fromIni($value))->toBe($bytes);
})->with([
    ['512M', 512 * 1024 ** 2],
    ['1G', 1024 ** 3],
    ['64k', 64 * 1024],
    ['1000', 1000],
    ['-1', Bytes::UNLIMITED],
]);

it('writes sizes for people', function (): void {
    expect(Bytes::human(512 * 1024 ** 2))->toBe('512 MB')
        ->and(Bytes::human(Bytes::UNLIMITED))->toBe('unlimited');
});
