<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\EmailAddress;
use SlimKiller\Auth\InvalidEmailAddress;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Auth\WelcomeLinks;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Database\Migrator;
use SlimKiller\Support\EnvFile;

/**
 * First-time setup on a new host: .env, the database, and the first user, who gets a
 * welcome link to choose a password and set up two-factor in the browser.
 */
final class InstallCommand implements Command
{
    public function __construct(
        private readonly EnvFile $env,
        private readonly Migrator $migrator,
        private readonly UserRepository $users,
        private readonly WelcomeLinks $welcomeLinks,
        private readonly AuditLog $audit,
    ) {}

    public function name(): string
    {
        return 'install';
    }

    public function description(): string
    {
        return 'Set up this site: .env, the database and the first user (--url= --email= --name= [--site-name=])';
    }

    public function run(Input $input, Output $output): int
    {
        $url = rtrim($input->option('url') ?? '', '/');
        $name = trim($input->option('name') ?? '');

        if ($url === '' || $name === '' || $input->option('email') === null) {
            $output->line('Give the website\'s address, and your email address and name:', 'red');
            $output->line('  php hammer install --url=https://example.com --email=you@example.com --name="Your Name"');

            return 2;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! preg_match('#^https?://#', $url)) {
            $output->line("\"{$url}\" is not a website address. Start it with https://", 'red');

            return 2;
        }

        try {
            $email = EmailAddress::from((string) $input->option('email'));
        } catch (InvalidEmailAddress $e) {
            $output->line($e->getMessage(), 'red');

            return 2;
        }

        $settings = ['APP_URL' => $url];
        $siteName = trim($input->option('site-name') ?? '');
        if ($siteName !== '') {
            $settings['APP_NAME'] = $siteName;
        }
        $added = $this->env->addMissing($settings);
        $output->line($added === [] ? '.env already has these settings; left as they are.' : 'Wrote ' . implode(' and ', $added) . ' to .env.', 'green');

        $ran = $this->migrator->migrate();
        $output->line($ran === [] ? 'The database is up to date.' : 'Created the database (' . implode(', ', $ran) . ').', 'green');

        if ($this->users->any()) {
            $output->line('This site is already installed. Add people with: php hammer user:create', 'yellow');

            return 1;
        }

        $user = $this->users->create($email, $name, Role::Admin);
        $this->audit->record('user_created', $user->id);
        $token = $this->welcomeLinks->issue($user->id);

        $output->line();
        $output->line('Installed. Finish setting up your account in the browser (link works for 72 hours):', 'bold');
        $output->line('  ' . $url . WelcomeLinks::pathFor($token));
        $output->line();
        $output->line('Then run php hammer doctor: it checks this server and gives the cron line to add.');
        $output->line();

        return 0;
    }
}
