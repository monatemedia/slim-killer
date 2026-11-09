<?php

declare(strict_types=1);

use SlimKiller\Support\AppConfig;

it('reads the site\'s name from .env, and falls back to Slim Killer', function (): void {
    expect(AppConfig::fromEnvironment(['APP_NAME' => ' Honey Badger Namibia '])->name)->toBe('Honey Badger Namibia')
        ->and(AppConfig::fromEnvironment([])->name)->toBe('Slim Killer')
        ->and(AppConfig::fromEnvironment(['APP_URL' => 'https://a.example/'])->url)->toBe('https://a.example');
});
