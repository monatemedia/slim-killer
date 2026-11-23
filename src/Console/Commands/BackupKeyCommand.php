<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Backups\BackupEncryption;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\EnvFile;

/**
 * `hammer backup:key`: makes the key that encrypts the backups sent to Google Drive, adds
 * it to .env and shows it once, to keep in a password manager. It never replaces a key:
 * the backups already on Google Drive open only with the one they were made with.
 */
final readonly class BackupKeyCommand implements Command
{
    public function __construct(
        private EnvFile $env,
        private BackupEncryption $encryption,
    ) {}

    public function name(): string
    {
        return 'backup:key';
    }

    public function description(): string
    {
        return 'Make the key that encrypts backups, and add it to .env';
    }

    public function run(Input $input, Output $output): int
    {
        if ($this->encryption->isSetUp()) {
            $output->line('BACKUP_KEY is already in .env. It is never replaced: the backups on Google Drive open only with it.', 'yellow');

            return 0;
        }

        $key = BackupEncryption::newKey();
        if ($this->env->addMissing(['BACKUP_KEY' => $key]) === []) {
            $output->line('.env has a BACKUP_KEY line that is empty or not 64 hexadecimal characters.', 'red');
            $output->line('Delete that line, then run this again.');

            return 1;
        }

        $output->line('Added BACKUP_KEY to .env. Save it in your password manager now, under this website:', 'green');
        $output->line();
        $output->line('  ' . $key, 'cyan');
        $output->line();
        $output->line('If this server is lost, the backups on Google Drive open only with this key.');
        $output->line('Keep it out of chats, emails and screenshots.');

        return 0;
    }
}
