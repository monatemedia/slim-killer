<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\Invites;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\AppConfig;

/**
 * For "I lost my phone" and "I forgot my password": clears the password, two-factor
 * and recovery codes, signs the person out everywhere, and prints a new welcome link.
 */
final class UserResetCommand implements Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Invites $invites,
        private readonly AuditLog $audit,
        private readonly AppConfig $config,
    ) {}

    public function name(): string
    {
        return 'user:reset';
    }

    public function description(): string
    {
        return 'Reset someone\'s password and two-factor, and print a new welcome link (--email=)';
    }

    public function run(Input $input, Output $output): int
    {
        $user = $this->users->byEmail($input->option('email') ?? '');

        if ($user === null) {
            $output->line('No user with that email address. Usage:', 'red');
            $output->line('  php hammer user:reset --email=client@example.com');

            return 1;
        }

        $this->users->resetCredentials($user->id);
        $this->audit->record('user_reset', $user->id);
        $token = $this->invites->issue($user->id);

        $output->line("Reset {$user->name} <{$user->email}>: signed out everywhere, password and two-factor cleared.", 'green');
        $output->line('Send them this welcome link (works for 72 hours):', 'bold');
        $output->line('  ' . $this->config->link(Invites::pathFor($token)));

        return 0;
    }
}
