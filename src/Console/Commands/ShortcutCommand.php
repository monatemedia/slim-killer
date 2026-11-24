<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use RuntimeException;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\HammerShortcut;
use SlimKiller\Support\Paths;

/** `hammer shortcut`: type "hammer doctor" from any folder, instead of the PHP's whole path. */
final readonly class ShortcutCommand implements Command
{
    public function __construct(
        private Paths $paths,
        private string $php = PHP_BINARY,
    ) {}

    public function name(): string
    {
        return 'shortcut';
    }

    public function description(): string
    {
        return 'Let "hammer" work from any folder, with the right PHP';
    }

    public function run(Input $input, Output $output): int
    {
        $shortcut = HammerShortcut::forAccount($this->paths->root());
        if ($shortcut === null) {
            $output->line('The hosting account\'s home folder is unknown, so there is nowhere to put the shortcut.', 'red');

            return 1;
        }

        try {
            $changed = $shortcut->install($this->php);
        } catch (RuntimeException $problem) {
            $output->line($problem->getMessage(), 'red');

            return 1;
        }

        if ($changed === []) {
            $output->line('The hammer shortcut is already set up: hammer doctor works from any folder.', 'green');

            return 0;
        }
        foreach ($changed as $line) {
            $output->line($line, 'green');
        }
        $output->line();
        $output->line('Start a new SSH session (or run: source ~/.bashrc), then: hammer doctor');

        return 0;
    }
}
