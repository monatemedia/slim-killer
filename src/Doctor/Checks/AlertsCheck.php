<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Alerts\Alerts;
use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Hammer;

/**
 * Will someone hear when something stops working? And is anything broken right now?
 */
final readonly class AlertsCheck implements HostCheck
{
    public function __construct(
        private Alerts $alerts,
        private AppConfig $config,
    ) {}

    public function check(): array
    {
        $results = [];
        foreach ($this->alerts->open() as $problem) {
            $results[] = CheckResult::warn(ucfirst($problem['what']) . ' has stopped working', $problem['message'], 'Since ' . $problem['since'] . '. It clears itself once it works again.');
        }

        if (! $this->alerts->canSend()) {
            $results[] = CheckResult::warn('Alerts go nowhere', 'nobody chose them in Preferences, and ALERTS_TO (or VISIT_REPORT_TO) and MAIL_FROM are needed to email when something stops working', 'Turn them on in Preferences, or set them in .env, then run ' . Hammer::command('alerts:test') . '.')
                ->weighed($this->config->env);
        } elseif ($results === []) {
            $results[] = CheckResult::pass('Alerts', 'go to ' . $this->alerts->recipientList() . ', and nothing has stopped working');
        }

        return $results;
    }
}
