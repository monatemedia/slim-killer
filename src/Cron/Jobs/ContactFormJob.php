<?php

declare(strict_types=1);

namespace SlimKiller\Cron\Jobs;

use DateTimeImmutable;
use SlimKiller\Cron\CronJob;
use SlimKiller\Cron\JobFailed;
use SlimKiller\Publishing\Releases;

/**
 * Sends the contact form's held messages when they are due: a batch whose time has
 * come, or a message the host could not send before (the circuit breaker in
 * slim-killer-forms).
 *
 * The form is the site's own `contact.php`, built into each release. Shared hosts
 * disable proc_open, so instead of running `php contact.php send-due` this loads the
 * live release's copy and calls the same code. Loaded this way, the file only defines
 * its classes. The website already runs this file for every visitor, so loading it here
 * trusts nothing new.
 */
final readonly class ContactFormJob implements CronJob
{
    public const string FILE = 'contact.php';

    private const string NAMESPACE = 'MonateMedia\\SlimKillerForms\\';

    public function __construct(
        private Releases $releases,
    ) {}

    public function name(): string
    {
        return 'contact-form';
    }

    public function everyMinutes(): int
    {
        return 1;
    }

    public function run(DateTimeImmutable $now): string
    {
        $live = $this->releases->live();
        $file = $live === null ? '' : $this->releases->folder($live->id) . '/' . self::FILE;
        if ($file === '' || ! is_file($file)) {
            return 'This website has no contact form.';
        }

        $config = self::NAMESPACE . 'config';
        $outbox = self::NAMESPACE . 'outbox';
        // Loaded once for each run of cron: a second copy would define the functions twice.
        if (! function_exists($config)) {
            require_once $file;
        }
        if (! function_exists($config) || ! function_exists($outbox)) {
            return 'This contact form sends every message at once, so it holds nothing.';
        }

        $settings = $config();
        if ($settings === null) {
            throw new JobFailed('The contact form is not set up: contact-config.php is missing or wrong. PHP\'s error log says which.');
        }
        $box = $outbox($settings);
        $sent = is_object($box) && method_exists($box, 'sendDue') ? $box->sendDue($now->getTimestamp()) : null;
        if (! is_int($sent)) {
            throw new JobFailed('The contact form did not answer as expected. Is contact.php from slim-killer-forms?');
        }

        return match ($sent) {
            0 => 'No held messages are due.',
            1 => 'Sent 1 held message.',
            default => "Sent {$sent} held messages.",
        };
    }
}
