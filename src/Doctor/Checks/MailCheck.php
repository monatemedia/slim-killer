<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Mail\Mailer;
use SlimKiller\Mail\SmtpMailer;
use SlimKiller\Support\AppConfig;

/**
 * How email leaves this website. Through its own mailbox (SMTP), the doctor signs in to
 * prove it works, and signs out again without sending anything.
 */
final readonly class MailCheck implements HostCheck
{
    public function __construct(
        private Mailer $mailer,
        private AppConfig $config,
    ) {}

    public function check(): array
    {
        if ($this->mailer instanceof SmtpMailer) {
            $problem = $this->mailer->check();

            return [$problem === ''
                ? CheckResult::pass('Email goes through ' . $this->config->smtpHost, 'signed in as ' . $this->config->smtpUser)
                : CheckResult::fail('Email can\'t be sent through ' . $this->config->smtpHost, $problem, 'Check SMTP_HOST, SMTP_PORT, SMTP_USER and SMTP_PASSWORD in .env.')];
        }
        if ($this->config->mailFrom === '') {
            return [];
        }

        return [CheckResult::info('Email goes through the host\'s mail()', 'it isn\'t DKIM-signed for this website\'s domain, so it fails DMARC. Set SMTP_HOST to send through the site\'s own mailbox (see the wiki page Email)')];
    }
}
