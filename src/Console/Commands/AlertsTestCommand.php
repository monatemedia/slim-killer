<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Alerts\Alerts;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Mail\Mailer;
use SlimKiller\Support\Clock;

/** `hammer alerts:test`: sends a test alert, to try the address and the mailbox before anything breaks. */
final readonly class AlertsTestCommand implements Command
{
    public function __construct(
        private Alerts $alerts,
        private Clock $clock,
        private Mailer $mailer,
    ) {}

    public function name(): string
    {
        return 'alerts:test';
    }

    public function description(): string
    {
        return 'Send a test alert to ALERTS_TO (or VISIT_REPORT_TO)';
    }

    public function run(Input $input, Output $output): int
    {
        if (! $this->alerts->canSend()) {
            $output->line('Alerts go nowhere yet: set ALERTS_TO (or VISIT_REPORT_TO) and MAIL_FROM in .env.', 'red');

            return 1;
        }
        if (! $this->alerts->test($this->clock->now())) {
            $problem = $this->mailer->lastProblem();
            $output->line('The test alert was not sent' . ($problem !== '' ? ': ' . $problem : '. Check that MAIL_FROM is a mailbox on this website\'s domain.'), 'red');

            return 1;
        }
        $output->line('Sent a test alert to ' . $this->alerts->recipient() . '. Check that it arrived, and not in spam.', 'green');

        return 0;
    }
}
