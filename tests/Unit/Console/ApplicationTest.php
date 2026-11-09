<?php

declare(strict_types=1);

use SlimKiller\Console\Application;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;

function runApplication(Application $application, array $argv): array
{
    $stream = fopen('php://memory', 'w+');
    $code = $application->run(Input::fromArgv(['hammer', ...$argv]), new Output($stream, false));
    rewind($stream);

    return [$code, stream_get_contents($stream)];
}

function greetCommand(): Command
{
    return new class implements Command {
        public function name(): string
        {
            return 'greet:hello';
        }

        public function description(): string
        {
            return 'Say hello';
        }

        public function run(Input $input, Output $output): int
        {
            $output->line('Hello, ' . ($input->option('name') ?? 'world'));

            return 0;
        }
    };
}

it('runs the named command with its options', function (): void {
    [$code, $text] = runApplication(new Application([greetCommand()]), ['greet:hello', '--name=Edward']);

    expect($code)->toBe(0)->and($text)->toContain('Hello, Edward');
});

it('lists the commands, grouped by prefix, when no command is given', function (): void {
    [$code, $text] = runApplication(new Application([greetCommand()]), []);

    expect($code)->toBe(0)
        ->and($text)->toContain('greet:hello')
        ->and($text)->toContain('Say hello')
        ->and($text)->toContain('list');
});

it('says so and lists the commands when the command is unknown', function (): void {
    [$code, $text] = runApplication(new Application([greetCommand()]), ['nope']);

    expect($code)->toBe(1)
        ->and($text)->toContain('There is no command called "nope"')
        ->and($text)->toContain('greet:hello');
});

it('reminds you it is a hammer, not an artisan', function (): void {
    [$code, $text] = runApplication(new Application([]), ['artisan']);

    expect($code)->toBe(1)->and($text)->toContain("It's a hammer, not an artisan");
});
