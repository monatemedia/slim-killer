<?php

declare(strict_types=1);

namespace SlimKiller\Alerts;

use DateTimeImmutable;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Cron\Heartbeat;
use SlimKiller\Mail\LetterWriter;
use SlimKiller\Mail\Mailer;
use SlimKiller\Support\Ago;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Hammer;
use SlimKiller\Support\Paths;
use SlimKiller\Visits\SiteTime;

/**
 * Tells the site's administrator by email when something stops working, and once more
 * when it works again. Never an email a minute: each problem is written down in
 * storage/alerts.json, and its email goes once.
 *
 * - A cron job that fails twice in a row (once can be a passing hiccup).
 * - Cron going quiet for 15 minutes. Cron can't report its own silence, so the
 *   website notices: the visit counter's endpoint looks at cron's last note.
 * - A build that fails on GitHub, at once: the change is saved but the website is
 *   unchanged.
 *
 * Alerts go to everyone who chose them in Preferences (administrators start with them
 * on). While nobody has, they go to ALERTS_TO, or VISIT_REPORT_TO when that is empty.
 * Always from MAIL_FROM.
 *
 * When the site sends through its mailbox (SMTP) and that fails, an alert is not lost
 * with it: it goes again through the host's own mail(), and says that email through the
 * mailbox has stopped working, and why. (SmartSMTP does the same for WordPress.)
 */
final readonly class Alerts
{
    public const string FILE = 'alerts.json';

    public const int FAILURES_BEFORE_ALERT = 2;

    public const int CRON_QUIET_AFTER_SECONDS = 900;

    /** What each cron job is called in an email, and the hammer command that shows why it failed. */
    private const array JOBS = [
        'backup' => ['the daily backup', 'backup', ''],
        'deploy-fetch' => ['fetching new builds of the website', 'deploy:fetch', ' An expired GitHub token is the usual cause.'],
        'visit-report' => ['the morning visit report', 'cron:run -v', ' Check that MAIL_FROM is a mailbox on this website\'s domain.'],
        'visits' => ['adding up the day\'s visits', 'cron:run -v', ''],
        'contact-form' => ['sending held contact messages', 'cron:run -v', ''],
    ];

    public function __construct(
        private Paths $paths,
        private LetterWriter $writer,
        private Mailer $mailer,
        private AppConfig $config,
        private SiteTime $time,
        private ?Mailer $fallback = null,
        private ?UserRepository $users = null,
    ) {}

    /**
     * Who alerts go to: the people who chose them in Preferences, or else ALERTS_TO, or
     * else VISIT_REPORT_TO; empty when there is nobody.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        $people = $this->users?->alertAddresses() ?? [];
        if ($people !== []) {
            return $people;
        }
        $setting = $this->config->alertsTo !== '' ? $this->config->alertsTo : $this->config->visitReportTo;

        return $setting === '' ? [] : [$setting];
    }

    /** Who alerts go to, in words, for the doctor and hammer alerts:test. */
    public function recipientList(): string
    {
        return implode(', ', $this->recipients());
    }

    /** Can an alert be sent at all: someone to send it to, and a mailbox to send it from? */
    public function canSend(): bool
    {
        return $this->recipients() !== [] && $this->config->mailFrom !== '';
    }

    public function jobFailed(string $job, string $message, DateTimeImmutable $now): void
    {
        if ($job === 'heartbeat') {
            return;
        }
        [$what, $command, $more] = self::JOBS[$job] ?? ["the cron job \"{$job}\"", 'cron:run -v', ''];
        $next = 'Run ' . Hammer::command($command) . ' on the server to see why.' . $more;
        $state = $this->read();
        $entry = $state['job:' . $job] ?? ['what' => $what, 'since' => $now->format(DATE_ATOM), 'failures' => 0, 'threshold' => self::FAILURES_BEFORE_ALERT, 'alerted' => false];
        $entry['failures'] = (int) $entry['failures'] + 1;
        $entry['message'] = $message;
        if ($entry['alerted'] !== true && $entry['failures'] >= self::FAILURES_BEFORE_ALERT) {
            $entry['alerted'] = $this->send('problem', $entry, $next);
        }
        $state['job:' . $job] = $entry;
        $this->write($state);
    }

    public function jobDone(string $job, DateTimeImmutable $now): void
    {
        $this->resolve('job:' . $job, $now);
    }

    /** The website asks: has cron gone quiet? Cheap when all is well: two small files read. */
    public function checkCron(Heartbeat $heartbeat, DateTimeImmutable $now): void
    {
        $file = $heartbeat->file();
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data) || ! is_string($data['at'] ?? null)) {
            return; // Cron was never set up: hammer doctor says so.
        }
        $quiet = $now->getTimestamp() - (new DateTimeImmutable($data['at']))->getTimestamp();
        $state = $this->read();
        if ($quiet < self::CRON_QUIET_AFTER_SECONDS || isset($state['cron'])) {
            return;
        }

        $entry = [
            'what' => 'cron (the jobs that run on a timer)',
            'since' => $data['at'],
            'failures' => 1,
            'threshold' => 1,
            'message' => 'Cron last ran ' . Ago::seconds($quiet) . ' ago. Backups, publishing and the morning report wait for it.',
        ];
        $entry['alerted'] = $this->send('problem', $entry, 'Check the cron job in the host\'s control panel (on Hostinger: Advanced, Cron Jobs), then run ' . Hammer::command('doctor') . '.');
        $state['cron'] = $entry;
        $this->write($state);
    }

    public function cronRan(DateTimeImmutable $now): void
    {
        $this->resolve('cron', $now);
    }

    /** A build failed on GitHub: one email for that change. */
    public function buildFailed(string $commit, DateTimeImmutable $now): void
    {
        $state = $this->read();
        if (($state['build']['commit'] ?? null) === $commit) {
            return;
        }
        $entry = [
            'what' => 'building the website',
            'since' => $now->format(DATE_ATOM),
            'failures' => 1,
            'threshold' => 1,
            'commit' => $commit,
            'message' => 'GitHub\'s build for change ' . substr($commit, 0, 7) . ' failed. The change is saved; the website is unchanged.',
        ];
        $entry['alerted'] = $this->send('problem', $entry, 'Open the Actions tab of the site\'s repository on GitHub to see why.');
        $state['build'] = $entry;
        $this->write($state);
    }

    public function buildLive(DateTimeImmutable $now): void
    {
        $this->resolve('build', $now);
    }

    /**
     * The problems not fixed yet, for hammer doctor.
     *
     * @return list<array{what: string, since: string, message: string}>
     */
    public function open(): array
    {
        $open = [];
        foreach ($this->read() as $entry) {
            $failures = is_int($entry['failures'] ?? null) ? $entry['failures'] : 0;
            $threshold = is_int($entry['threshold'] ?? null) ? $entry['threshold'] : self::FAILURES_BEFORE_ALERT;
            if (is_string($entry['what'] ?? null) && is_string($entry['since'] ?? null) && $failures >= $threshold) {
                $open[] = ['what' => $entry['what'], 'since' => $entry['since'], 'message' => is_string($entry['message'] ?? null) ? $entry['message'] : ''];
            }
        }

        return $open;
    }

    /** Sends a test alert, so the address and the mailbox can be tried before anything breaks. */
    public function test(DateTimeImmutable $now): bool
    {
        return $this->send('test', [
            'what' => 'a test alert',
            'since' => $now->format(DATE_ATOM),
            'message' => 'Nothing is wrong. This is what an alert looks like, sent by ' . Hammer::command('alerts:test') . '.',
        ], 'Nothing to do.');
    }

    private function resolve(string $key, DateTimeImmutable $now): void
    {
        $state = $this->read();
        if (! isset($state[$key])) {
            return;
        }
        $entry = $state[$key];
        if (($entry['alerted'] ?? false) === true) {
            $this->send('fixed', $entry + ['fixed' => $now->format(DATE_ATOM)], 'Nothing to do.');
        }
        unset($state[$key]);
        $this->write($state);
    }

    /** @param array<string, mixed> $entry */
    private function send(string $kind, array $entry, string $next): bool
    {
        if (! $this->canSend()) {
            return false;
        }
        $what = is_string($entry['what'] ?? null) ? $entry['what'] : 'something';
        $subject = $this->config->name . ': ' . match ($kind) {
            'fixed' => $what . ' works again',
            'test' => 'a test alert',
            default => $what . ' has stopped working',
        };
        $fromPreferences = ($this->users?->alertAddresses() ?? []) !== [];
        $details = [
            'kind' => $kind,
            'what' => $what,
            'since' => $this->when((string) ($entry['since'] ?? '')),
            'fixed' => $this->when((string) ($entry['fixed'] ?? '')),
            'message' => (string) ($entry['message'] ?? ''),
            'next' => $next,
            'from_setting' => $fromPreferences ? 'preferences' : ($this->config->alertsTo !== '' ? 'ALERTS_TO' : 'VISIT_REPORT_TO'),
            'smtp_host' => $this->config->smtpHost,
            'smtp_problem' => '',
        ];

        // Sent if it reached at least one person.
        $sent = false;
        foreach ($this->recipients() as $to) {
            $sent = $this->sendTo($to, $subject, $details) || $sent;
        }

        return $sent;
    }

    /** @param array<string, mixed> $details */
    private function sendTo(string $to, string $subject, array $details): bool
    {
        if ($this->mailer->send($this->writer->write('alert', $to, $subject, $details))) {
            return true;
        }
        if ($this->fallback === null) {
            return false;
        }

        // The mailbox refused or couldn't be reached: the host's own mail() carries the
        // alert, and the alert says so.
        $problem = $this->mailer->lastProblem();
        $details['smtp_problem'] = $problem !== '' ? $problem : 'no reason given';

        return $this->fallback->send($this->writer->write('alert', $to, $subject, $details));
    }

    private function when(string $atom): string
    {
        if ($atom === '') {
            return '';
        }

        return (new DateTimeImmutable($atom))->setTimezone($this->time->zone)->format('j F Y, H:i');
    }

    /** @return array<string, array<string, mixed>> */
    private function read(): array
    {
        $file = $this->paths->storage(self::FILE);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? array_filter($data, 'is_array') : [];
    }

    /** @param array<string, array<string, mixed>> $state */
    private function write(array $state): void
    {
        $file = $this->paths->storage(self::FILE);
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
