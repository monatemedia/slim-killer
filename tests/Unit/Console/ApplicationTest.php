<?php

declare(strict_types=1);

use SlimKiller\Console\Application;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Insults;
use SlimKiller\Console\Output;

function runApplication(Application $application, array $argv, string $script = 'hammer'): array
{
    $stream = fopen('php://memory', 'w+');
    $code = $application->run(Input::fromArgv([$script, ...$argv]), new Output($stream, false));
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

it('shows the logo above the list of commands', function (): void {
    [, $text] = runApplication(new Application([greetCommand()]), []);

    expect($text)->toContain('SLIM-KILLER CLI RUNTIME KERNEL')
        ->and(strpos($text, 'KERNEL'))->toBeLessThan(strpos($text, 'Commands:'));
});

it('leaves the logo out when the command is unknown, so the problem comes first', function (): void {
    [, $text] = runApplication(new Application([greetCommand()]), ['nope']);

    expect($text)->not->toContain('KERNEL')
        ->and($text)->toStartWith('There is no command called');
});

it('has a word for `php hammer artisan`', function (): void {
    [$code, $text] = runApplication(new Application([], fn(int $count): int => 4), ['artisan']);

    expect($code)->toBe(1)
        ->and($text)->toContain('Does it come with a little beret?')
        ->and($text)->toContain('php hammer list');
});

it('has a word for `php artisan`, then runs the command anyway', function (): void {
    [$code, $text] = runApplication(new Application([greetCommand()], fn(int $count): int => 1), ['greet:hello', '--name=Edward'], 'artisan');

    expect($code)->toBe(0)
        ->and($text)->toStartWith('Artisan? Who? We crush bugs with Hammers here.')
        ->and($text)->toContain('Hello, Edward');
});

it('picks from every insult', function (): void {
    $seen = [];
    foreach (array_keys(Insults::ALL) as $index) {
        [, $text] = runApplication(new Application([], fn(int $count): int => $index), ['artisan']);
        // hammer ends lines with PHP_EOL, which is "\r\n" on Windows.
        $seen[] = strtok($text, "\r\n");
    }

    expect($seen)->toBe(Insults::ALL);
});

it('knows it was started as artisan, whatever the path', function (): void {
    expect(Input::fromArgv(['C:\\Users\\me\\site\\artisan', 'list'])->script)->toBe('artisan')
        ->and(Input::fromArgv(['./hammer'])->script)->toBe('hammer')
        ->and(Input::fromArgv([])->script)->toBe('hammer');
});
