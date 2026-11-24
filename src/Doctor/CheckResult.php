<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

use SlimKiller\Support\AppEnv;

/**
 * One line of the doctor's report: what was checked, the outcome, and (when something
 * needs doing) a hint in plain language.
 */
final readonly class CheckResult
{
    public function __construct(
        public Status $status,
        public string $title,
        public string $detail = '',
        public string $hint = '',
    ) {}

    public static function pass(string $title, string $detail = ''): self
    {
        return new self(Status::Pass, $title, $detail);
    }

    public static function warn(string $title, string $detail = '', string $hint = ''): self
    {
        return new self(Status::Warn, $title, $detail, $hint);
    }

    public static function fail(string $title, string $detail = '', string $hint = ''): self
    {
        return new self(Status::Fail, $title, $detail, $hint);
    }

    public static function info(string $title, string $detail = ''): self
    {
        return new self(Status::Info, $title, $detail);
    }

    /**
     * As strict as the place it runs in: on a developer's computer a warning or a
     * failure only informs; on the live website a warning fails. Staging leaves it.
     */
    public function weighed(AppEnv $env): self
    {
        return match (true) {
            $env === AppEnv::Local && in_array($this->status, [Status::Warn, Status::Fail], true) => new self(Status::Info, $this->title, $this->detail, $this->hint),
            $env === AppEnv::Production && $this->status === Status::Warn => new self(Status::Fail, $this->title, $this->detail, $this->hint),
            default => $this,
        };
    }
}
