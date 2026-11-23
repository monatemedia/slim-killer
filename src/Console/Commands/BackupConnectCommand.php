<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Backups\BackupEncryption;
use SlimKiller\Backups\BackupFailed;
use SlimKiller\Backups\GoogleDrive;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\AppConfig;

/**
 * `hammer backup:connect`: lets backups go to the site's own Google Drive. Two steps,
 * since a shared host has no browser:
 *
 *   hammer backup:connect                    prints a link to open and allow
 *   hammer backup:connect --code="<address>" with the address the browser landed on
 */
final readonly class BackupConnectCommand implements Command
{
    public function __construct(
        private GoogleDrive $drive,
        private AppConfig $config,
        private BackupEncryption $encryption,
    ) {}

    public function name(): string
    {
        return 'backup:connect';
    }

    public function description(): string
    {
        return 'Connect the site\'s Google Drive for backups (--code=...)';
    }

    public function run(Input $input, Output $output): int
    {
        if (! $this->drive->isSetUp()) {
            $output->line('GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are not in .env yet.', 'red');
            $output->line('Make a "Desktop app" OAuth client in the site\'s own Google account first: see the wiki page Backups.');

            return 1;
        }

        $code = $input->option('code');
        if ($code === null) {
            $output->line('1. Open this link in a browser signed in to the site\'s Google account, and choose Allow:', 'green');
            $output->line();
            $output->line($this->drive->connectLink());
            $output->line();
            $output->line('2. The browser then fails to load a page at 127.0.0.1. That is expected. Copy the whole');
            $output->line('   address from its address bar, and run, within a few minutes:');
            $output->line();
            $output->line('   ' . $input->script . ' backup:connect --code="<the address>"', 'cyan');

            return 0;
        }

        try {
            $this->drive->connect($code);
        } catch (BackupFailed $problem) {
            $output->line($problem->getMessage(), 'red');

            return 1;
        }
        $output->line("Connected. Backups go to the folder \"{$this->config->backupFolder}\" in that Google Drive.", 'green');
        if (! $this->encryption->isSetUp()) {
            $output->line('Backups are sent encrypted: run ' . $input->script . ' backup:key next, and save the key in your password manager.', 'yellow');
        }
        $output->line('Run ' . $input->script . ' backup to send the first one now, then ' . $input->script . ' backup:check to test-restore it.');

        return 0;
    }
}
