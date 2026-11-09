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

    /** @param list<Command> $commands */
    public function __construct(array $commands)
    {
        foreach ($commands as $command) {
            $this->commands[$command->name()] = $command;
        }
        ksort($this->commands);
    }

    public function run(Input $input, Output $output): int
    {
        $name = $input->command;

        if ($name === '' || $name === 'list' || $name === 'help') {
            $this->renderList($output);

            return 0;
        }

        if ($name === 'artisan') {
            $output->line("It's a hammer, not an artisan. Try `php hammer list`.", 'yellow');

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

    private function renderList(Output $output): void
    {
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
