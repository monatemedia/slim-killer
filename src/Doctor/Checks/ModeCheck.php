<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\AppEnv;

/** Where this runs (APP_ENV), first in the report, since it decides how strict the rest is. */
final readonly class ModeCheck implements HostCheck
{
    public function __construct(
        private AppConfig $config,
    ) {}

    public function check(): array
    {
        $results = [match ($this->config->env) {
            AppEnv::Local => CheckResult::info('Local', 'a developer\'s computer: email is saved to storage/mail, and this report only informs'),
            AppEnv::Staging => CheckResult::info('Staging', 'a test copy: hidden from search engines, and this report warns but doesn\'t fail'),
            AppEnv::Production => CheckResult::pass('Production', 'the live website: listed by search engines, and anything it needs missing fails'),
        }];

        if ($this->config->envSetting !== '' && AppEnv::fromSetting($this->config->envSetting) === null) {
            $results[] = CheckResult::warn('APP_ENV "' . $this->config->envSetting . '" isn\'t one this knows', 'it is treated as production', 'Set APP_ENV to local, staging or production in .env.');
        } elseif ($this->config->envFromOldSettings) {
            $results[] = CheckResult::warn('APP_ENV is not set', 'LOCAL_WEBSITE or HIDE_FROM_SEARCH still decides, for now', 'Put APP_ENV="' . $this->config->env->value . '" in .env, and remove the old line.');
        }

        return $results;
    }
}
