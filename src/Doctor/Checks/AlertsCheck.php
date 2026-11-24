<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Alerts\Alerts;
use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;

/**
 * Will someone hear when something stops working? And is anything broken right now?
 */
final readonly class AlertsCheck implements HostCheck
{
    public function __construct(
        private Alerts $alerts,
    ) {}

    public function check(): array
    {
        $results = [];
        foreach ($this->alerts->open() as $problem) {
            $results[] = CheckResult::warn(ucfirst($problem['what']) . ' has stopped working', $problem['message'], 'Since ' . $problem['since'] . '. It clears itself once it works again.');
        }

        if (! $this->alerts->canSend()) {
            $results[] = CheckResult::warn('Alerts go nowhere', 'ALERTS_TO (or VISIT_REPORT_TO) and MAIL_FROM are needed to email when something stops working', 'Set them in .env, then run hammer alerts:test.');
        } elseif ($results === []) {
            $results[] = CheckResult::pass('Alerts', 'go to ' . $this->alerts->recipient() . ', and nothing has stopped working');
        }

        return $results;
    }
}
