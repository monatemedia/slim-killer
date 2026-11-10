<?php

declare(strict_types=1);

namespace SlimKiller\Console;

/**
 * Finds the command named on the command line and runs it. With no command it lists
 * the commands; with an unknown one it says so and lists them.
 *
 * The list is built in here rather than being a command of its own, because a "list"
 * command would need the application that contains it.
 */
final class Application
{
    public const string NAME = 'Slim Killer';

    /** @var array<string, Command> */
    private array $commands = [];

    /** @var \Closure(int): int Picks an insult by index, from 0 to the number given minus one. */
    private \Closure $pick;

    /**
     * @param list<Command> $commands
     * @param (\Closure(int): int)|null $pick for tests; random by default
     */
    public function __construct(array $commands, ?\Closure $pick = null)
    {
        $this->pick = $pick ?? static fn(int $count): int => random_int(0, $count - 1);
        foreach ($commands as $command) {
            $this->commands[$command->name()] = $command;
        }
        ksort($this->commands);
    }

    public function run(Input $input, Output $output): int
    {
        $name = $input->command;

        // `php artisan …` gets a word about the name, then runs the command anyway.
        if ($input->script === 'artisan') {
            $this->insult($output);
            $output->line();
        }

        if ($name === '' || $name === 'list' || $name === 'help') {
            $this->renderList($output, banner: true);

            return 0;
        }

        // `php hammer artisan`: the same habit, one word later.
        if ($name === 'artisan') {
            $this->insult($output);
            $output->line('Try `php hammer list`.');

            return 1;
        }

        if (! isset($this->commands[$name])) {
            $output->line("There is no command called \"{$name}\".", 'red');
            $output->line();
            $this->renderList($output);

            return 1;
        }

        return $this->commands[$name]->run($input, $output);
    }

    private function insult(Output $output): void
    {
        $output->line(Insults::ALL[($this->pick)(count(Insults::ALL))] ?? Insults::ALL[0], 'yellow');
    }

    private function renderList(Output $output, bool $banner = false): void
    {
        if ($banner) {
            foreach (Banner::lines() as $line) {
                $output->line($line, 'orange');
            }
            $output->line();
        }
        $output->line(self::NAME . ' ' . $output->paint('hammer', 'cyan'), 'bold');
        $output->line();
        $output->line('Usage:', 'yellow');
        $output->line('  php hammer <command> [options]');
        $output->line();
        $output->line('Commands:', 'yellow');

        $all = ['list' => 'List the available commands'];
        foreach ($this->commands as $name => $command) {
            $all[$name] = $command->description();
        }

        $groups = [];
        foreach ($all as $name => $description) {
            $group = str_contains($name, ':') ? explode(':', $name, 2)[0] : '';
            $groups[$group][$name] = $description;
        }
        ksort($groups);

        $width = max(array_map('strlen', array_keys($all))) + 2;
        foreach ($groups as $group => $commands) {
            if ($group !== '') {
                $output->line(' ' . $group, 'yellow');
            }
            ksort($commands);
            foreach ($commands as $name => $description) {
                $output->line('  ' . $output->paint(str_pad($name, $width), 'green') . $description);
            }
        }
    }
}
