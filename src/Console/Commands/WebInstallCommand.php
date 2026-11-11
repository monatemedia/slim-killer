<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use RuntimeException;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Publishing\WebRoot;

/** Sets up the website folder (WEB_ROOT). Run again after updating Slim Killer. */
final class WebInstallCommand implements Command
{
    public function __construct(
        private readonly WebRoot $webRoot,
    ) {}

    public function name(): string
    {
        return 'web:install';
    }

    public function description(): string
    {
        return 'Set up the website folder (WEB_ROOT): .htaccess, the control panel and its assets';
    }

    public function run(Input $input, Output $output): int
    {
        try {
            foreach ($this->webRoot->install() as $line) {
                $output->line($line, 'green');
            }
        } catch (RuntimeException $exception) {
            $output->line($exception->getMessage(), 'red');

            return 1;
        }
        $output->line('Next: php hammer deploy:fetch, to put the website itself in place.', 'grey');

        return 0;
    }
}
