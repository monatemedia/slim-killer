<?php

declare(strict_types=1);

use SlimKiller\Console\Input;

it('reads the command, arguments and options', function (): void {
    $input = Input::fromArgv(['hammer', 'doctor', 'extra', '--url=https://example.com', '--webroot', '~/public_html', '-v']);

    expect($input->command)->toBe('doctor')
        ->and($input->arguments)->toBe(['extra'])
        ->and($input->option('url'))->toBe('https://example.com')
        ->and($input->option('webroot'))->toBe('~/public_html')
        ->and($input->flag('v'))->toBeTrue()
        ->and($input->option('missing'))->toBeNull();
});

it('treats an option without a value as a flag', function (): void {
    $input = Input::fromArgv(['hammer', 'doctor', '--quiet']);

    expect($input->flag('quiet'))->toBeTrue()
        ->and($input->option('quiet'))->toBeNull();
});

it('has no command when none is given', function (): void {
    expect(Input::fromArgv(['hammer'])->command)->toBe('');
});
