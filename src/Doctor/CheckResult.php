<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

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
}
