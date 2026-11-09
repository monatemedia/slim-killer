<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\EmailAddress;
use SlimKiller\Auth\InvalidEmailAddress;
use SlimKiller\Auth\Invites;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\UserAlreadyExists;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\AppConfig;

/**
 * Adds someone to the control panel and prints their welcome link, to send them. They
 * choose their own password, so nobody else ever knows it.
 */
final class UserCreateCommand implements Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Invites $invites,
        private readonly AuditLog $audit,
        private readonly AppConfig $config,
    ) {}

    public function name(): string
    {
        return 'user:create';
    }

    public function description(): string
    {
        return 'Add someone and print their welcome link (--email= --name= [--role=owner|admin])';
    }

    public function run(Input $input, Output $output): int
    {
        $name = trim($input->option('name') ?? '');
        $role = Role::tryFrom($input->option('role') ?? Role::Owner->value);

        if ($input->option('email') === null || $name === '' || $role === null) {
            $output->line('Give an email address and a name, and optionally a role (owner, the default, or admin):', 'red');
            $output->line('  php hammer user:create --email=client@example.com --name="Client Name" --role=owner');

            return 2;
        }

        try {
            $user = $this->users->create(EmailAddress::from((string) $input->option('email')), $name, $role);
        } catch (InvalidEmailAddress|UserAlreadyExists $e) {
            $output->line($e->getMessage(), 'red');

            return 1;
        }

        $this->audit->record('user_created', $user->id);
        $token = $this->invites->issue($user->id);

        $output->line("Added {$user->name} <{$user->email}> as {$user->role->label()}.", 'green');
        $output->line('Send them this welcome link (works for 72 hours):', 'bold');
        $output->line('  ' . $this->config->link(Invites::pathFor($token)));

        return 0;
    }
}
